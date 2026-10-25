<?php

namespace Tests\Feature;

use App\Models\QuadrantDia;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Neteja de plantilles: no se'n creen de repetides, es veu qui fa servir cada una, s'ajunten les que
 * fan el mateix horari i s'esborren les que no fa servir ningú (mai una que algú fa servir).
 */
class PlantillesHorariTest extends TestCase
{
    use RefreshDatabase;

    private function dies(string $a, string $b): array
    {
        return collect([1, 2, 3, 4, 5])->map(fn ($d) => ['day' => $d, 'start' => $a, 'end' => $b])->all();
    }

    private function plantilla(string $nom, string $a = '08:00', string $b = '14:00', float $h = 30): WorkSchedule
    {
        return WorkSchedule::create(['name' => $nom, 'total_hours_weekly' => $h, 'days' => $this->dies($a, $b)]);
    }

    private function persona(?WorkSchedule $s, string $rol = 'worker', bool $actiu = true): User
    {
        return User::create(['name' => 'P', 'email' => 'p' . uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'active' => $actiu, 'work_schedule_id' => $s?->id]);
    }

    public function test_crear_una_plantilla_igual_que_una_que_ja_hi_es_torna_la_que_hi_havia(): void
    {
        $hi = $this->plantilla('30h Dl-Dv 08:00-14:00');
        Sanctum::actingAs($this->persona(null, 'hr'));

        $r = $this->postJson('/api/v1/work-schedules', ['name' => 'Una altra amb el mateix horari', 'total_hours_weekly' => 30,
            'days' => $this->dies('8:00', '14:00')])->assertOk();

        $this->assertSame($hi->id, $r->json('id'));
        $this->assertTrue($r->json('reutilitzada'));
        $this->assertSame(1, WorkSchedule::count());

        $this->postJson('/api/v1/work-schedules', ['name' => 'Tardes', 'total_hours_weekly' => 30, 'days' => $this->dies('14:00', '20:00')])
            ->assertCreated();
        $this->assertSame(2, WorkSchedule::count());
    }

    public function test_sense_nom_la_plantilla_es_diu_com_el_seu_horari(): void
    {
        Sanctum::actingAs($this->persona(null, 'hr'));
        $dies = array_merge(
            collect([1, 3, 5])->map(fn ($d) => ['day' => $d, 'start' => '08:00', 'end' => '14:00'])->all(),
            collect([2, 4])->map(fn ($d) => ['day' => $d, 'start' => '12:00', 'end' => '18:00'])->all(),
        );

        $r = $this->postJson('/api/v1/work-schedules', ['days' => $dies])->assertCreated();

        $this->assertSame('30h · Dl, Dc, Dv 08:00-14:00 / Dm, Dj 12:00-18:00', $r->json('name'));
        $this->assertEquals(30, $r->json('total_hours_weekly'));
        $this->assertSame('25h · Dl-Dv 09:00-14:00', WorkSchedule::nomAutomatic($this->dies('09:00', '14:00')));
    }

    public function test_el_llistat_diu_qui_la_fa_servir_i_les_hores_reals(): void
    {
        $s = $this->plantilla('35h Dl-Dv 09:00-14:00', '09:00', '14:00', 35);   // el nom diu 35 h, l'horari en fa 25
        $this->persona($s);
        $this->persona($s, 'worker', false);
        $hr = $this->persona(null, 'hr');
        QuadrantDia::create(['user_id' => $hr->id, 'dia' => '2026-10-21', 'trams' => [], 'lliure' => true, 'work_schedule_id' => $s->id]);

        Sanctum::actingAs($hr);
        $fila = collect($this->getJson('/api/v1/plantilles-horari')->assertOk()->json())->firstWhere('id', $s->id);

        $this->assertSame(1, $fila['persones_actives']);
        $this->assertSame(1, $fila['persones_inactives']);
        $this->assertSame(1, $fila['dies_quadrant']);
        $this->assertSame(25 * 60, $fila['minuts_reals']);
    }

    public function test_ajuntar_passa_la_gent_a_una_sola_i_esborra_les_altres(): void
    {
        $bona = $this->plantilla('30h Dl-Dv 08:00-14:00');
        $copia = WorkSchedule::create(['name' => '30h Dl-Dv 08:00 - 14:00', 'total_hours_weekly' => 30, 'days' => $this->dies('08:00', '14:00')]);
        $diferent = $this->plantilla('Tardes', '14:00', '20:00');
        $p = $this->persona($copia);
        $hr = $this->persona(null, 'hr');
        $q = QuadrantDia::create(['user_id' => $p->id, 'dia' => '2026-10-21', 'trams' => [['start' => '08:00', 'end' => '14:00']], 'work_schedule_id' => $copia->id]);

        Sanctum::actingAs($hr);
        $this->postJson("/api/v1/plantilles-horari/{$bona->id}/ajunta", ['ids' => [$diferent->id]])->assertStatus(422);
        $this->postJson("/api/v1/plantilles-horari/{$bona->id}/ajunta", ['ids' => [$copia->id]])
            ->assertOk()->assertJsonPath('persones', 1);

        $this->assertSame($bona->id, $p->fresh()->work_schedule_id);
        $this->assertSame($bona->id, $q->fresh()->work_schedule_id);
        $this->assertNull(WorkSchedule::find($copia->id));
        $this->assertNotNull(WorkSchedule::find($diferent->id));
    }

    public function test_nomes_s_esborren_les_que_no_fa_servir_ningu(): void
    {
        $usada = $this->plantilla('Usada');
        $deInactiu = $this->plantilla('D\'un inactiu', '09:00', '13:00');
        $soles = [$this->plantilla('Sola 1', '10:00', '14:00'), $this->plantilla('Sola 2', '11:00', '15:00')];
        $this->persona($usada);
        $this->persona($deInactiu, 'worker', false);

        Sanctum::actingAs($this->persona(null, 'admin'));
        $this->deleteJson("/api/v1/plantilles-horari/{$usada->id}")->assertStatus(422);
        $this->deleteJson("/api/v1/plantilles-horari/{$soles[0]->id}")->assertOk();
        $this->postJson('/api/v1/plantilles-horari/esborra-sense-us')->assertOk()->assertJsonPath('esborrades', 1);

        $this->assertEqualsCanonicalizing([$usada->id, $deInactiu->id], WorkSchedule::pluck('id')->all());
    }

    public function test_coordinacio_i_treballadors_no_hi_entren(): void
    {
        $s = $this->plantilla('Sola');
        foreach (['coordinator', 'worker'] as $rol) {
            Sanctum::actingAs($this->persona(null, $rol));
            $this->getJson('/api/v1/plantilles-horari')->assertForbidden();
            $this->deleteJson("/api/v1/plantilles-horari/{$s->id}")->assertForbidden();
            $this->postJson('/api/v1/plantilles-horari/esborra-sense-us')->assertForbidden();
        }
        $this->assertNotNull(WorkSchedule::find($s->id));
    }
}
