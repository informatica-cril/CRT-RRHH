<?php

namespace Tests\Feature;

use App\Models\SaldoMinutsApunt;
use App\Models\SaldoMinutsSetting;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkSchedule;
use App\Services\SaldoMinuts;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Saldo de minuts: finestra de ±5 min als límits de l'horari, tope ±15, llibre a part i idempotent. */
class SaldoMinutsTest extends TestCase
{
    use RefreshDatabase;

    private User $treb;

    protected function setUp(): void
    {
        parent::setUp();
        $dies = [];
        foreach ([1, 2, 3, 4, 5] as $d) $dies[] = ['day' => $d, 'start' => '08:20', 'end' => '16:00'];
        $horari = WorkSchedule::create(['name' => 'prova', 'total_hours_weekly' => 38.33, 'days' => $dies]);
        $this->treb = User::factory()->create(['role' => 'worker', 'active' => true, 'work_schedule_id' => $horari->id]);
        $this->activa();
    }

    private function activa(bool $on = true): void
    {
        SaldoMinutsSetting::query()->delete();
        SaldoMinutsSetting::create(['enabled' => $on, 'finestra_minuts' => 5, 'tope_minuts' => 15, 'actiu_des' => '2026-10-19']);
        SaldoMinuts::oblida();
    }

    private function fitxatge(string $dia, string $ini, string $fi, float $extra = 0.0): WorkLog
    {
        return WorkLog::create([
            'user_id' => $this->treb->id, 'date' => $dia, 'start_time' => "$dia $ini", 'end_time' => "$dia $fi",
            'total_hours_worked' => 7.7, 'hours_worked' => 7.7, 'extra_hours_unauthorized' => $extra,
            'hour_status' => $extra > 0 ? 'extra' : 'ok', 'status' => 'pending',
            'start_location_match' => true, 'end_location_match' => true,
        ]);
    }

    private function c(string $h): Carbon
    {
        return Carbon::parse("2026-10-19 $h", 'Europe/Madrid');
    }

    public function test_deltes_nomes_dins_de_la_finestra(): void
    {
        $f = [[$this->c('08:20'), $this->c('16:00')]];
        $this->assertSame([2, 0], SaldoMinuts::deltes($f, $this->c('08:18:30'), $this->c('16:00:20'), 5));
        $this->assertSame([-3, 4], SaldoMinuts::deltes($f, $this->c('08:23'), $this->c('16:04'), 5));
        $this->assertSame([5, 5], SaldoMinuts::deltes($f, $this->c('08:15'), $this->c('16:05'), 5));
        $this->assertSame([0, 0], SaldoMinuts::deltes($f, $this->c('08:10'), $this->c('16:00'), 5));   // 10 min abans: fora
        $this->assertSame([0, -4], SaldoMinuts::deltes($f, $this->c('08:20'), $this->c('15:56'), 5));
        $this->assertSame([0, 0], SaldoMinuts::deltes($f, $this->c('08:20'), $this->c('15:50'), 5));   // 10 min abans de sortir: fora
    }

    public function test_deltes_amb_jornada_partida_fa_servir_el_limit_mes_proper(): void
    {
        $p = [[$this->c('09:00'), $this->c('13:45')], [$this->c('13:50'), $this->c('15:50')]];
        $this->assertSame([3, 3], SaldoMinuts::deltes($p, $this->c('08:57'), $this->c('15:53'), 5));
    }

    public function test_tope(): void
    {
        $this->assertSame([10, 0, 10], SaldoMinuts::aplicaTope(0, 10, 15));
        $this->assertSame([2, 8, 15], SaldoMinuts::aplicaTope(13, 10, 15));
        $this->assertSame([-1, -3, -15], SaldoMinuts::aplicaTope(-14, -4, 15));
    }

    public function test_el_saldo_s_acumula_i_resta_les_hores_de_mes_sense_autoritzar(): void
    {
        $l1 = $this->fitxatge('2026-10-19', '08:18:30', '16:00:20', 0.03);
        $this->fitxatge('2026-10-20', '08:23:00', '16:04:00');
        $this->fitxatge('2026-10-21', '08:10:00', '16:00:00', 0.17);   // fora de la finestra: no hi entra res

        $this->assertSame(3, SaldoMinuts::saldo($this->treb->id));
        $this->assertSame(2, SaldoMinutsApunt::count());
        $l1->refresh();
        $this->assertEquals(0.0, (float) $l1->extra_hours_unauthorized);
        $this->assertSame('ok', $l1->hour_status);
    }

    public function test_repetir_el_calcul_no_resta_dos_cops(): void
    {
        $l = $this->fitxatge('2026-10-22', '08:15:00', '16:05:00', 0.17);
        $this->assertEquals(0.14, (float) $l->fresh()->extra_hours_unauthorized);
        $l->update(['extra_hours_unauthorized' => 0.17]);   // l'app el torna a enviar
        $this->assertEquals(0.14, (float) $l->fresh()->extra_hours_unauthorized);
        SaldoMinuts::recalcula($this->treb->id);
        SaldoMinuts::recalcula($this->treb->id);
        $this->assertEquals(0.14, (float) $l->fresh()->extra_hours_unauthorized);
    }

    public function test_un_fitxatge_rebutjat_surt_del_llibre(): void
    {
        $this->fitxatge('2026-10-19', '08:18', '16:00');
        $l2 = $this->fitxatge('2026-10-20', '08:15', '16:05');
        $this->assertSame(12, SaldoMinuts::saldo($this->treb->id));
        $l2->update(['status' => 'rejected']);
        $this->assertSame(2, SaldoMinuts::saldo($this->treb->id));
    }

    public function test_desactivat_no_fa_res(): void
    {
        $this->activa(false);
        $l = $this->fitxatge('2026-10-19', '08:15', '16:05', 0.17);
        $this->assertSame(0, SaldoMinutsApunt::count());
        $this->assertEquals(0.17, (float) $l->fresh()->extra_hours_unauthorized);
    }

    public function test_no_compta_abans_de_la_data_d_activacio(): void
    {
        $this->fitxatge('2026-10-16', '08:15', '16:05');
        $this->assertSame(0, SaldoMinutsApunt::count());
    }
    public function test_previsio_avisa_abans_de_fitxar(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 19, 8, 17, 30, 'Europe/Madrid'));   // dilluns, 3 min abans de les 08:20
        $r = SaldoMinuts::previsio($this->treb, 'entrada');
        $this->assertTrue($r['activa']);
        $this->assertSame(3, $r['delta']);
        $this->assertSame(3, $r['saldo_previst']);

        // Jornada oberta des de les 08:17 i sortida 5 min abans de les 16:00: -5 + 3 = -2.
        WorkLog::create(['user_id' => $this->treb->id, 'date' => '2026-10-19', 'start_time' => '2026-10-19 08:17:00']);
        Carbon::setTestNow(Carbon::create(2026, 10, 19, 15, 55, 10, 'Europe/Madrid'));
        $r = SaldoMinuts::previsio($this->treb, 'sortida');
        $this->assertSame(-5, $r['delta']);
        $this->assertSame(3, $r['entrada_delta']);
        $this->assertSame(-2, $r['saldo_previst']);
        Carbon::setTestNow();
    }

    public function test_previsio_desactivat(): void
    {
        $this->activa(false);
        $this->assertSame(['activa' => false], SaldoMinuts::previsio($this->treb, 'entrada'));
    }
    public function test_gestio_configura_el_saldo_i_nomes_rrhh_o_admin(): void
    {
        $this->activa(false);
        SaldoMinutsSetting::query()->update(['actiu_des' => null]);
        SaldoMinuts::oblida();

        Sanctum::actingAs($this->treb);
        $this->putJson('/api/v1/saldo-minuts/config', ['enabled' => true, 'finestra_minuts' => 5, 'tope_minuts' => 15])->assertStatus(403);

        Sanctum::actingAs(User::factory()->create(['role' => 'hr', 'active' => true]));
        $this->putJson('/api/v1/saldo-minuts/config', ['enabled' => true, 'finestra_minuts' => 5, 'tope_minuts' => 15])
            ->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('actiu_des', Carbon::now('Europe/Madrid')->toDateString());
        $this->putJson('/api/v1/saldo-minuts/config', ['enabled' => true, 'finestra_minuts' => 40, 'tope_minuts' => 15])->assertStatus(422);
    }

    public function test_la_decisio_de_rrhh_compensa_ajusta_o_tanca_a_zero(): void
    {
        $this->fitxatge('2026-10-19', '08:15', '16:05');          // +10
        $this->assertSame(10, SaldoMinuts::saldo($this->treb->id));
        Sanctum::actingAs(User::factory()->create(['role' => 'hr', 'active' => true]));
        $url = "/api/v1/saldo-minuts/persones/{$this->treb->id}/ajust";

        $this->postJson($url, ['decisio' => 'compensat'])->assertStatus(422);                                  // sense motiu
        $this->postJson($url, ['decisio' => 'ajustat', 'minuts' => 10, 'nota' => 'passaria del tope'])->assertStatus(422);
        $this->postJson($url, ['decisio' => 'ajustat', 'minuts' => -4, 'nota' => 'sortida abans acordada'])->assertOk()->assertJsonPath('saldo', 6);
        $this->postJson($url, ['decisio' => 'compensat', 'nota' => 'compensat el 30/10'])->assertOk()->assertJsonPath('saldo', 0);
        $this->postJson($url, ['decisio' => 'zero', 'nota' => 'sense saldo'])->assertStatus(422);              // ja és zero

        $this->assertSame(2, SaldoMinutsApunt::where('tipus', 'ajust')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'SALDO_MINUTS_AJUST']);
    }

    public function test_un_ajust_sobreviu_a_un_recalcul(): void
    {
        $this->fitxatge('2026-10-19', '08:15', '16:05');          // +10
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        $this->postJson("/api/v1/saldo-minuts/persones/{$this->treb->id}/ajust", ['decisio' => 'compensat', 'nota' => 'compensat'])->assertOk();
        SaldoMinuts::recalcula($this->treb->id);
        $this->assertSame(0, SaldoMinuts::saldo($this->treb->id));
    }

    public function test_el_llistat_de_saldos_es_nomes_per_a_rrhh_i_admin(): void
    {
        $this->fitxatge('2026-10-19', '08:15', '16:05');
        Sanctum::actingAs($this->treb);
        $this->getJson('/api/v1/saldo-minuts/persones')->assertStatus(403);
        Sanctum::actingAs(User::factory()->create(['role' => 'hr', 'active' => true]));
        $this->getJson('/api/v1/saldo-minuts/persones')->assertOk()->assertJsonPath('persones.0.saldo', 10);
        $this->getJson('/api/v1/saldo-minuts/persones?min=11')->assertOk()->assertJsonCount(0, 'persones');
    }
}
