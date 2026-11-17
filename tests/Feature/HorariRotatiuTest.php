<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\HorariDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Horari rotatiu opcional: la setmana 1 és la plantilla de sempre i `rotacio` porta les altres. */
class HorariRotatiuTest extends TestCase
{
    use RefreshDatabase;

    private function dies(array $dies, string $ini, string $fi): array
    {
        return array_map(fn ($d) => ['day' => $d, 'active' => true, 'start' => $ini, 'end' => $fi], $dies);
    }

    private function persona(?array $rotacio): User
    {
        // Setmana 1: dl-dv 09:30-14:30. Setmana 2: dl, dc, dv 14:00-19:00 i dm, dj 09:30-14:30.
        $s = WorkSchedule::create(['name' => 'Rotatiu', 'total_hours_weekly' => 25,
            'days' => $this->dies([1, 2, 3, 4, 5], '09:30', '14:30'), 'rotacio' => $rotacio]);

        return User::create(['name' => 'E', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'active' => true, 'work_schedule_id' => $s->id]);
    }

    private function rot(): array
    {
        return ['inici' => '2026-11-09', 'setmanes' => [array_merge($this->dies([1, 3, 5], '14:00', '19:00'), $this->dies([2, 4], '09:30', '14:30'))]];
    }

    private function hores(User $u, string $dia): string
    {
        return collect(HorariDia::trams($u, $dia))->map(fn ($t) => "{$t['start']}-{$t['end']}")->implode(',');
    }

    public function test_alterna_les_setmanes_del_cicle(): void
    {
        $u = $this->persona($this->rot());

        $this->assertSame('09:30-14:30', $this->hores($u, '2026-11-09'));   // dilluns setmana 1
        $this->assertSame('14:00-19:00', $this->hores($u, '2026-11-16'));   // dilluns setmana 2
        $this->assertSame('09:30-14:30', $this->hores($u, '2026-11-17'));   // dimarts setmana 2
        $this->assertSame('09:30-14:30', $this->hores($u, '2026-11-23'));   // torna la setmana 1
        $this->assertSame('14:00-19:00', $this->hores($u, '2026-11-30'));
    }

    public function test_funciona_tambe_abans_de_l_inici_del_cicle(): void
    {
        $u = $this->persona($this->rot());

        $this->assertSame('14:00-19:00', $this->hores($u, '2026-11-02'));   // setmana anterior = la 2
        $this->assertSame('09:30-14:30', $this->hores($u, '2026-10-26'));
    }

    public function test_sense_rotacio_es_comporta_com_sempre(): void
    {
        $u = $this->persona(null);

        $this->assertSame('09:30-14:30', $this->hores($u, '2026-11-16'));
    }

    public function test_cicle_de_tres_setmanes(): void
    {
        $r = ['inici' => '2026-11-09', 'setmanes' => [$this->dies([1], '10:00', '11:00'), $this->dies([1], '12:00', '13:00')]];
        $u = $this->persona($r);

        $this->assertSame(['09:30-14:30', '10:00-11:00', '12:00-13:00', '09:30-14:30'],
            array_map(fn ($d) => $this->hores($u, $d), ['2026-11-09', '2026-11-16', '2026-11-23', '2026-11-30']));
    }

    public function test_l_inici_es_porta_al_dilluns(): void
    {
        $r = WorkSchedule::normalizeRotacio(['inici' => '2026-11-12', 'setmanes' => [$this->dies([1], '10:00', '11:00')]]);

        $this->assertSame('2026-11-09', $r['inici']);
        $this->assertNull(WorkSchedule::normalizeRotacio(['inici' => '2026-11-09', 'setmanes' => []]));
    }

    public function test_es_pot_crear_i_desar_des_de_l_api_i_no_es_confon_amb_un_de_fix(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'hr', 'active' => true]));
        $base = ['days' => $this->dies([1, 2, 3, 4, 5], '09:30', '14:30')];

        $fix = $this->postJson('/api/v1/work-schedules', $base)->assertStatus(201)->json();
        $rot = $this->postJson('/api/v1/work-schedules', $base + ['rotacio' => $this->rot()])->assertStatus(201)->json();

        $this->assertNotSame($fix['id'], $rot['id']);
        $this->assertSame('2026-11-09', $rot['rotacio']['inici']);
        $this->assertEquals(25.0, (float) $fix['total_hours_weekly']);
        $this->assertEquals(25.0, (float) $rot['total_hours_weekly']);   // 25 h i 25 h: mitjana 25

        // La mateixa rotació es reutilitza; treure-la deixa l'horari fix.
        $this->assertTrue($this->postJson('/api/v1/work-schedules', $base + ['rotacio' => $this->rot()])->json('reutilitzada'));
        $this->putJson("/api/v1/work-schedules/{$rot['id']}", ['rotacio' => null])->assertOk()->assertJsonPath('rotacio', null);
    }

    public function test_per_setmana_del_mes_compta_pel_dilluns_i_repeteix_el_cicle(): void
    {
        $u = $this->persona(['mode' => 'mes', 'setmanes' => [array_merge($this->dies([1, 3, 5], '14:00', '19:00'), $this->dies([2, 4], '09:30', '14:30'))]]);

        // Novembre 2026: dilluns 2 (1a), 9 (2a), 16 (3a), 23 (4a), 30 (5a). Amb 2 horaris: 1,2,1,2,1.
        $dll = array_map(fn ($d) => $this->hores($u, $d), ['2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23', '2026-11-30']);
        $this->assertSame(['09:30-14:30', '14:00-19:00', '09:30-14:30', '14:00-19:00', '09:30-14:30'], $dll);
        // Un diumenge pertany a la setmana del seu dilluns (dg 8 → setmana del dl 2).
        $this->assertSame('', $this->hores($u, '2026-11-08'));
        // El mes següent torna a començar: dilluns 7 de desembre és la 1a.
        $this->assertSame('09:30-14:30', $this->hores($u, '2026-12-07'));
        $this->assertSame('14:00-19:00', $this->hores($u, '2026-12-14'));
    }

    public function test_el_mode_mes_no_demana_data_d_inici_i_no_es_confon_amb_alternes(): void
    {
        $this->assertSame('mes', WorkSchedule::normalizeRotacio(['mode' => 'mes', 'setmanes' => [$this->dies([1], '10:00', '11:00')]])['mode']);
        $a = WorkSchedule::signatura($this->dies([1], '09:00', '10:00'), ['mode' => 'mes', 'setmanes' => [$this->dies([1], '10:00', '11:00')]]);
        $b = WorkSchedule::signatura($this->dies([1], '09:00', '10:00'), ['mode' => 'alternes', 'inici' => '2026-11-09', 'setmanes' => [$this->dies([1], '10:00', '11:00')]]);
        $this->assertNotSame($a, $b);
    }
}
