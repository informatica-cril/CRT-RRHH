<?php

namespace Tests\Feature;

use App\Models\AprovacioAutomaticaSetting;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Aprovació automàtica: un fitxatge correcte (horari ± 10 min, dins de zona, sense extres ni
 * incidències) s'aprova sol en fitxar la sortida; la resta es queda pendent com fins ara.
 */
class AprovacioAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 500)]); // cap API de temps real: rellotge local
    }

    /** Treballadora amb horari de dimecres de 08:20 a 16:00. */
    private function treballadora(): User
    {
        $horari = WorkSchedule::create(['name' => 'Matins', 'total_hours_weekly' => 38.33,
            'days' => [['day' => 3, 'start' => '08:20', 'end' => '16:00']]]);

        return User::create(['name' => 'Treballadora', 'email' => 'w' . uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'active' => true, 'work_schedule_id' => $horari->id]);
    }

    private function gestio(string $rol = 'hr'): User
    {
        return User::create(['name' => 'Gestió', 'email' => $rol . uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'active' => true]);
    }

    /** Entra a l'hora donada del dimecres 14-10-2026, fa la pausa i fitxa la sortida a $sortida. */
    private function jornada(User $w, string $entrada, string $sortida, array $extra = []): WorkLog
    {
        Carbon::setTestNow(Carbon::parse("2026-10-14 {$sortida}:00", 'Europe/Madrid'));
        $log = WorkLog::create(array_merge([
            'user_id' => $w->id, 'date' => '2026-10-14', 'start_time' => "2026-10-14 {$entrada}:00",
            'status' => 'pending', 'start_location_match' => true, 'break_status' => 'completed',
        ], $extra));
        Sanctum::actingAs($w);
        $this->putJson("/api/v1/work-logs/{$log->id}", ['end_time' => '2020-01-01 09:00:00', 'end_location_match' => true])->assertOk();

        return $log->fresh();
    }

    public function test_un_fitxatge_correcte_s_aprova_sol_en_fitxar_la_sortida(): void
    {
        $log = $this->jornada($this->treballadora(), '08:20', '16:00');

        $this->assertSame('approved', $log->status);
        $this->assertNotNull($log->auto_approved_at);
        $this->assertTrue($log->modifications()->where('action', 'approved')
            ->where('comment', 'like', 'Aprovat automàticament%')->exists());
    }

    public function test_dins_del_marge_de_10_minuts_s_aprova_i_tambe_el_tram_de_mes(): void
    {
        $log = $this->jornada($this->treballadora(), '08:12', '16:08');

        $this->assertSame('approved', $log->status);
        $this->assertSame(0, $log->segments()->where('status', 'pending')->count());
    }

    public function test_fora_del_marge_es_queda_pendent(): void
    {
        $w = $this->treballadora();
        $this->assertSame('pending', $this->jornada($w, '08:20', '16:25')->status);   // 25 min de més
        $this->assertSame('pending', $this->jornada($w, '08:45', '16:00')->status);   // arriba 25 min tard
        $this->assertSame('pending', $this->jornada($w, '08:20', '13:00')->status);   // marxa molt abans
    }

    public function test_entrada_fora_de_zona_pausa_sense_fer_o_extres_es_queden_pendents(): void
    {
        $w = $this->treballadora();
        $this->assertSame('pending', $this->jornada($w, '08:20', '16:00', ['start_location_match' => false])->status);
        $this->assertSame('pending', $this->jornada($w, '08:20', '16:00', ['break_status' => null])->status);
        $this->assertSame('pending', $this->jornada($w, '08:20', '16:00', ['extra_hours_unauthorized' => 0.5])->status);
    }

    public function test_sense_horari_o_amb_l_interruptor_apagat_no_s_aprova(): void
    {
        $sense = User::create(['name' => 'Sense horari', 'email' => 's@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
        $this->assertSame('pending', $this->jornada($sense, '08:20', '16:00')->status);

        AprovacioAutomaticaSetting::getSettings()->update(['enabled' => false]);
        $this->assertSame('pending', $this->jornada($this->treballadora(), '08:20', '16:00')->status);
    }

    public function test_aprovar_tots_els_correctes_aprova_els_pendents_que_compleixen_i_diu_per_que_no_la_resta(): void
    {
        AprovacioAutomaticaSetting::getSettings()->update(['enabled' => false]);
        $w = $this->treballadora();
        $bo = $this->jornada($w, '08:20', '16:00');
        $tard = $this->jornada($w, '08:20', '16:30');
        $this->assertSame('pending', $bo->status);

        Sanctum::actingAs($this->gestio('hr'));
        $r = $this->postJson('/api/v1/work-logs/aprova-correctes')->assertOk();
        $r->assertJsonPath('aprovats', 1)->assertJsonPath('pendents', 1)->assertJsonPath("motius.temps fora d'horari", 1);

        $this->assertSame('approved', $bo->fresh()->status);
        $this->assertNotNull($bo->fresh()->auto_approved_at);
        $this->assertSame('pending', $tard->fresh()->status);
    }

    public function test_la_configuracio_la_canvia_rrhh_i_no_un_treballador(): void
    {
        Sanctum::actingAs($this->gestio('hr'));
        $this->getJson('/api/v1/aprovacio-automatica')->assertOk()->assertJson(['enabled' => true, 'marge_minuts' => 10]);
        $this->putJson('/api/v1/aprovacio-automatica', ['enabled' => false, 'marge_minuts' => 5])->assertOk()
            ->assertJson(['enabled' => false, 'marge_minuts' => 5]);

        Sanctum::actingAs($this->treballadora());
        $this->putJson('/api/v1/aprovacio-automatica', ['enabled' => true])->assertForbidden();
        $this->postJson('/api/v1/work-logs/aprova-correctes')->assertForbidden();
    }
}
