<?php

namespace Tests\Feature;

use App\Models\AuthorizationCode;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El treballador que passa de l'hora de sortida pot demanar autorització des del comptador.
 * RRHH l'autoritza (extraordinàries a jornada completa, complementàries a temps parcial) i,
 * si la jornada ja és tancada, el codi s'aplica sol al fitxatge.
 */
class SollicitudHoresForaHorariTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::create(['name' => 'RRHH', 'email' => 'hr' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'hr', 'active' => true]);
    }

    private function treballador(float $setmanals): User
    {
        $dies = [];
        for ($d = 1; $d <= 5; $d++) {
            $h = $setmanals / 5;
            $fi = sprintf('%02d:%02d', 8 + (int) $h, (int) round(($h - (int) $h) * 60));
            $dies[] = ['day' => $d, 'active' => true, 'start' => '08:00', 'end' => $fi];
        }
        $q = WorkSchedule::create(['name' => 'Quadre ' . $setmanals, 'total_hours_weekly' => $setmanals, 'days' => $dies]);

        return User::create(['name' => 'Treballadora', 'email' => 'w' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true, 'work_schedule_id' => $q->id]);
    }

    public function test_el_treballador_demana_i_es_reaprofita_la_sollicitud_del_dia(): void
    {
        $w = $this->treballador(37.5);
        Sanctum::actingAs($w);

        $this->postJson('/api/v1/overtime-requests/meves', ['hours' => 1, 'reason' => 'Formació IA RRHH'])
            ->assertStatus(201);
        // Tornar a demanar el mateix dia actualitza la mateixa sol·licitud.
        $this->postJson('/api/v1/overtime-requests/meves', ['hours' => 1.5, 'reason' => 'Formació IA RRHH (s\'allarga)'])
            ->assertStatus(201);

        $this->assertSame(1, OvertimeRequest::count());
        $r = OvertimeRequest::first();
        $this->assertSame('treballador', $r->origen);
        $this->assertSame('pending', $r->status);
        $this->assertEquals(1.5, (float) $r->hours);

        $this->getJson('/api/v1/overtime-requests/meves')
            ->assertOk()->assertJsonPath('0.status', 'pending');
    }

    public function test_el_motiu_es_obligatori(): void
    {
        Sanctum::actingAs($this->treballador(37.5));

        $this->postJson('/api/v1/overtime-requests/meves', ['hours' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_un_treballador_no_pot_autoritzar(): void
    {
        $w = $this->treballador(37.5);
        $r = OvertimeRequest::create(['user_id' => $w->id, 'hours' => 1, 'period_from' => now()->toDateString(),
            'period_to' => now()->toDateString(), 'requested_by' => $w->name, 'origen' => 'treballador', 'status' => 'pending']);
        Sanctum::actingAs($w);

        $this->postJson("/api/v1/overtime-requests/{$r->id}/authorize")->assertStatus(403);
    }

    public function test_jornada_completa_extraordinaries_i_s_aplica_al_fitxatge_tancat(): void
    {
        $w = $this->treballador(37.5);
        $avui = now('Europe/Madrid')->toDateString();
        $log = WorkLog::create(['user_id' => $w->id, 'date' => $avui,
            'start_time' => $avui . ' 08:00:00', 'end_time' => $avui . ' 17:00:00',
            'extra_hours_unauthorized' => 1.0, 'extra_hours_authorized' => 0, 'status' => 'pending']);
        $r = OvertimeRequest::create(['user_id' => $w->id, 'hours' => 1, 'period_from' => $avui, 'period_to' => $avui,
            'requested_by' => $w->name, 'reason' => 'Formació', 'origen' => 'treballador',
            'work_log_id' => $log->id, 'status' => 'pending']);

        Sanctum::actingAs($this->hr());
        $this->postJson("/api/v1/overtime-requests/{$r->id}/authorize")
            ->assertOk()->assertJsonPath('aplicades', 1);

        $codi = AuthorizationCode::first();
        $this->assertSame('extraordinaria', $codi->type);
        $this->assertEquals(1.25, (float) $codi->factor);
        $this->assertTrue($codi->used);
        // Vàlid fins al final del dia, no fins a les 00:00.
        $this->assertSame('23:59:59', $codi->valid_to->format('H:i:s'));

        $log->refresh();
        $this->assertEquals(1.0, (float) $log->extra_hours_authorized);
        $this->assertEquals(0.0, (float) $log->extra_hours_unauthorized);
        $this->assertSame($codi->code, $log->authorized_extra_code);
    }

    public function test_temps_parcial_complementaries_i_jornada_oberta_no_es_toca(): void
    {
        $w = $this->treballador(20);
        $avui = now('Europe/Madrid')->toDateString();
        $log = WorkLog::create(['user_id' => $w->id, 'date' => $avui, 'start_time' => $avui . ' 08:00:00', 'status' => 'pending']);
        $r = OvertimeRequest::create(['user_id' => $w->id, 'hours' => 1, 'period_from' => $avui, 'period_to' => $avui,
            'requested_by' => $w->name, 'origen' => 'treballador', 'work_log_id' => $log->id, 'status' => 'pending']);

        Sanctum::actingAs($this->hr());
        $this->postJson("/api/v1/overtime-requests/{$r->id}/authorize")
            ->assertOk()->assertJsonPath('aplicades', null);

        $codi = AuthorizationCode::first();
        $this->assertSame('complementaria', $codi->type);
        $this->assertFalse($codi->used); // l'app l'aplicarà en fitxar la sortida

        // El treballador veu el codi a les seves sol·licituds.
        Sanctum::actingAs($w);
        $this->getJson('/api/v1/overtime-requests/meves')
            ->assertOk()->assertJsonPath('0.status', 'authorized')->assertJsonPath('0.code', $codi->code);
    }
}
