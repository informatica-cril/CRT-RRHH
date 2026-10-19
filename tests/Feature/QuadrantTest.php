<?php

namespace Tests\Feature;

use App\Models\QuadrantDia;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Quadrant: RRHH i admin canvien l'horari d'una persona dia a dia i aquell horari mana sobre la
 * plantilla setmanal en tot el que mira l'horari (trams, aprovació automàtica…). El treballador
 * veu el seu; coordinació no hi entra.
 */
class QuadrantTest extends TestCase
{
    use RefreshDatabase;

    private WorkSchedule $matins;
    private WorkSchedule $tardes;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 500)]);
        $feiners = fn ($a, $b) => collect([1, 2, 3, 4, 5])->map(fn ($d) => ['day' => $d, 'start' => $a, 'end' => $b])->all();
        $this->matins = WorkSchedule::create(['name' => 'Matins', 'total_hours_weekly' => 35, 'days' => $feiners('08:00', '15:00')]);
        $this->tardes = WorkSchedule::create(['name' => 'Tardes', 'total_hours_weekly' => 35, 'days' => $feiners('15:00', '22:00')]);
    }

    private function fisio(): User
    {
        return User::create(['name' => 'Fisio', 'email' => 'f' . uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'active' => true, 'work_schedule_id' => $this->matins->id]);
    }

    private function gestio(string $rol = 'hr'): User
    {
        return User::create(['name' => 'Gestió', 'email' => $rol . uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'active' => true]);
    }

    /** Fitxa el dimecres 21-10-2026 de $entrada a $sortida (la sortida per l'API, com l'app). */
    private function jornada(User $w, string $entrada, string $sortida): WorkLog
    {
        Carbon::setTestNow(Carbon::parse("2026-10-21 {$sortida}:00", 'Europe/Madrid'));
        $log = WorkLog::create([
            'user_id' => $w->id, 'date' => '2026-10-21', 'start_time' => "2026-10-21 {$entrada}:00",
            'status' => 'pending', 'start_location_match' => true, 'break_status' => 'completed',
        ]);
        Sanctum::actingAs($w);
        $this->putJson("/api/v1/work-logs/{$log->id}", ['end_time' => '2020-01-01 09:00:00', 'end_location_match' => true])->assertOk();

        return $log->fresh();
    }

    private function desa(array $cos): \Illuminate\Testing\TestResponse
    {
        return $this->putJson('/api/v1/quadrant', $cos);
    }

    public function test_rrhh_posa_un_mes_de_tardes_i_la_resta_segueix_la_plantilla(): void
    {
        $w = $this->fisio();
        Sanctum::actingAs($this->gestio('hr'));
        $dies = collect(range(1, 30))->map(fn ($d) => sprintf('2026-11-%02d', $d))->all();
        $this->desa(['user_ids' => [$w->id], 'dies' => $dies, 'accio' => 'plantilla', 'work_schedule_id' => $this->tardes->id])
            ->assertOk()->assertJsonPath('canviats', 30);

        $nov = collect($this->getJson('/api/v1/quadrant?mes=2026-11')->assertOk()->json('persones'))->firstWhere('id', $w->id);
        $this->assertSame([['start' => '15:00', 'end' => '22:00']], $nov['dies']['2026-11-02']['trams']);   // dilluns
        $this->assertSame('quadrant', $nov['dies']['2026-11-02']['origen']);
        $this->assertSame([], $nov['dies']['2026-11-01']['trams']);                                          // diumenge: lliure
        $this->assertSame(21 * 7 * 60, $nov['minuts']);

        $oct = collect($this->getJson('/api/v1/quadrant?mes=2026-10')->json('persones'))->firstWhere('id', $w->id);
        $this->assertSame('plantilla', $oct['dies']['2026-10-21']['origen']);
        $this->assertSame([['start' => '08:00', 'end' => '15:00']], $oct['dies']['2026-10-21']['trams']);
    }

    public function test_el_fitxatge_del_dia_canviat_es_mira_amb_el_quadrant_i_s_aprova_sol(): void
    {
        $w = $this->fisio();
        Sanctum::actingAs($this->gestio('admin'));
        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-21'], 'accio' => 'plantilla', 'work_schedule_id' => $this->tardes->id])->assertOk();

        $log = $this->jornada($w, '15:00', '22:00');

        $this->assertSame('approved', $log->status);
        $this->assertSame(0, $log->segments()->where('in_schedule', false)->count());
    }

    public function test_jornada_partida_a_mida_i_validacio_dels_trams(): void
    {
        $w = $this->fisio();
        Sanctum::actingAs($this->gestio('hr'));
        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-21'], 'accio' => 'trams',
            'trams' => [['start' => '16:00', 'end' => '20:00'], ['start' => '09:00', 'end' => '13:00']]])->assertOk();
        $this->assertSame([['start' => '09:00', 'end' => '13:00'], ['start' => '16:00', 'end' => '20:00']],
            \App\Services\HorariDia::trams($w, '2026-10-21'));

        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-22'], 'accio' => 'trams',
            'trams' => [['start' => '09:00', 'end' => '13:00'], ['start' => '12:00', 'end' => '15:00']]])->assertStatus(422);
        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-22'], 'accio' => 'trams',
            'trams' => [['start' => '13:00', 'end' => '09:00']]])->assertStatus(422);
    }

    public function test_lliure_i_tornar_a_la_plantilla(): void
    {
        $w = $this->fisio();
        Sanctum::actingAs($this->gestio('hr'));
        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-21'], 'accio' => 'lliure'])->assertOk();
        $this->assertSame([], \App\Services\HorariDia::trams($w, '2026-10-21'));

        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-21'], 'accio' => 'esborra'])->assertOk();
        $this->assertSame(0, QuadrantDia::count());
        $this->assertSame([['start' => '08:00', 'end' => '15:00']], \App\Services\HorariDia::trams($w, '2026-10-21'));
    }

    public function test_canviar_el_quadrant_recalcula_els_trams_d_un_pendent(): void
    {
        $w = $this->fisio();
        $log = $this->jornada($w, '15:00', '22:00');          // amb la plantilla de matins: fora d'horari
        $this->assertSame('pending', $log->status);
        $this->assertTrue($log->segments()->where('in_schedule', false)->exists());

        Sanctum::actingAs($this->gestio('hr'));
        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-21'], 'accio' => 'plantilla', 'work_schedule_id' => $this->tardes->id])
            ->assertOk()->assertJsonPath('trams_recalculats', 1);

        $this->assertFalse($log->fresh()->segments()->where('in_schedule', false)->exists());
    }

    public function test_copiar_un_mes_respecta_el_dia_de_la_setmana(): void
    {
        $w = $this->fisio();
        Sanctum::actingAs($this->gestio('hr'));
        // Octubre 2026: el primer dilluns és el dia 5. Només aquell dilluns, de tardes.
        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-05'], 'accio' => 'plantilla', 'work_schedule_id' => $this->tardes->id])->assertOk();

        $this->postJson('/api/v1/quadrant/copia', ['user_ids' => [$w->id], 'de' => '2026-10', 'a' => '2026-11'])->assertOk();

        // Novembre 2026: el primer dilluns és el dia 2.
        $this->assertSame([['start' => '15:00', 'end' => '22:00']], \App\Services\HorariDia::trams($w, '2026-11-02'));
        $this->assertSame([['start' => '08:00', 'end' => '15:00']], \App\Services\HorariDia::trams($w, '2026-11-09'));
        $this->assertSame(1, QuadrantDia::whereBetween('dia', ['2026-11-01', '2026-11-30'])->count());
    }

    public function test_el_personal_d_administracio_surt_al_quadrant_encara_que_no_sigui_treballador(): void
    {
        $admin = \App\Models\Department::where('code', 'ADMIN')->firstOrFail();
        // (Coordinació es desa com a treballador: per provar un altre rol, RRHH.)
        $administrativa = $this->gestio('hr');
        $administrativa->departments()->attach($admin->id);
        $senseDepartament = $this->gestio('hr');
        $fisio = $this->fisio();

        Sanctum::actingAs($this->gestio('hr'));
        $tots = collect($this->getJson('/api/v1/quadrant?mes=2026-10')->assertOk()->json('persones'))->pluck('id');
        $this->assertTrue($tots->contains($administrativa->id));
        $this->assertTrue($tots->contains($fisio->id));
        $this->assertFalse($tots->contains($senseDepartament->id));

        $r = $this->getJson("/api/v1/quadrant?mes=2026-10&department_id={$admin->id}")->assertOk();
        $this->assertSame([$administrativa->id], collect($r->json('persones'))->pluck('id')->all());
        $this->assertContains('Administració', collect($r->json('departaments'))->pluck('name')->all());
    }

    public function test_el_treballador_veu_el_seu_quadrant_i_ningu_mes_hi_toca(): void
    {
        $w = $this->fisio();
        Sanctum::actingAs($this->gestio('hr'));
        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-21'], 'accio' => 'lliure', 'nota' => 'Canvi amb un company'])->assertOk();

        Sanctum::actingAs($w);
        $r = $this->getJson('/api/v1/el-meu-quadrant?mes=2026-10')->assertOk();
        $this->assertSame([], $r->json('dies.2026-10-21.trams'));
        $this->assertSame('Canvi amb un company', $r->json('dies.2026-10-21.nota'));
        $this->getJson('/api/v1/quadrant?mes=2026-10')->assertForbidden();
        $this->desa(['user_ids' => [$w->id], 'dies' => ['2026-10-22'], 'accio' => 'lliure'])->assertForbidden();

        Sanctum::actingAs($this->gestio('coordinator'));
        $this->getJson('/api/v1/quadrant?mes=2026-10')->assertForbidden();
        $this->getJson("/api/v1/quadrant/any?user_id={$w->id}&any=2026")->assertForbidden();
        $this->postJson('/api/v1/quadrant/copia', ['user_ids' => [$w->id], 'de' => '2026-10', 'a' => '2026-11'])->assertForbidden();
    }
}
