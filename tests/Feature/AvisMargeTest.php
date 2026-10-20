<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Avís de fora d'horari: qui porta 3 fitxatges seguits entrant o sortint fora de l'horari (de més
 * o de menys) surt a la llista de RRHH i admin; un dia puntual talla la ratxa i es pot descartar.
 */
class AvisMargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 500)]);
        Carbon::setTestNow(Carbon::parse('2026-10-16 18:00:00', 'Europe/Madrid')); // divendres
    }

    /** Persona amb horari de dilluns a divendres de 08:00 a 15:00. */
    private function treballadora(string $nom = 'Treballadora'): User
    {
        $horari = WorkSchedule::create(['name' => 'Matins', 'total_hours_weekly' => 35,
            'days' => collect([1, 2, 3, 4, 5])->map(fn ($d) => ['day' => $d, 'start' => '08:00', 'end' => '15:00'])->all()]);

        return User::create(['name' => $nom, 'email' => 'w' . uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'active' => true, 'work_schedule_id' => $horari->id]);
    }

    private function gestio(string $rol = 'hr'): User
    {
        return User::create(['name' => 'Gestió', 'email' => $rol . uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'active' => true]);
    }

    /** Un fitxatge per cada dia laborable entre $des i $fins; els dies de $puntual entra a les 08:00. */
    private function jornades(User $w, string $entrada, string $sortida, array $puntual = []): void
    {
        for ($d = Carbon::parse('2026-09-28'); $d->lte(Carbon::parse('2026-10-16')); $d->addDay()) {
            if ($d->isWeekend()) continue;
            $dia = $d->toDateString();
            $ent = in_array($dia, $puntual, true) ? '08:00' : $entrada;
            WorkLog::create([
                'user_id' => $w->id, 'date' => $dia, 'status' => 'approved',
                'start_time' => "{$dia} {$ent}:00", 'end_time' => "{$dia} {$sortida}:00",
            ]);
        }
    }

    private function avisos(): \Illuminate\Support\Collection
    {
        return collect($this->getJson('/api/v1/avisos-marge')->assertOk()->json())->keyBy('user_id');
    }

    public function test_tres_setmanes_entrant_8_min_tard_surt_a_l_avis(): void
    {
        $tard = $this->treballadora('Tard');
        $this->jornades($tard, '08:08', '15:00');

        Sanctum::actingAs($this->gestio('hr'));
        $a = $this->avisos();

        $this->assertTrue($a->has($tard->id));
        $this->assertSame(15, $a[$tard->id]['dies']);
        $this->assertSame(15, $a[$tard->id]['entrades_tard']);
        $this->assertSame('2026-09-28', $a[$tard->id]['inici']);
    }

    public function test_sortir_abans_dins_del_marge_tambe_compta(): void
    {
        $abans = $this->treballadora('Abans');
        $this->jornades($abans, '08:00', '14:52');

        Sanctum::actingAs($this->gestio('admin'));
        $this->assertSame(15, $this->avisos()[$abans->id]['sortides_abans']);
    }

    public function test_fitxar_de_mes_tambe_compta(): void
    {
        $mes = $this->treballadora('De més');
        $this->jornades($mes, '07:30', '16:10');   // entra abans i surt més tard, molt més que el marge

        Sanctum::actingAs($this->gestio('hr'));
        $a = $this->avisos()[$mes->id];
        $this->assertSame(15, $a['entrades_aviat']);
        $this->assertSame(15, $a['sortides_tard']);
    }

    public function test_qui_es_puntual_no_surt(): void
    {
        $puntual = $this->treballadora('Puntual');
        $this->jornades($puntual, '08:00', '15:00');

        Sanctum::actingAs($this->gestio('hr'));
        $this->assertFalse($this->avisos()->has($puntual->id));
    }

    public function test_un_dia_puntual_fa_poc_talla_la_ratxa(): void
    {
        $w = $this->treballadora();
        $this->jornades($w, '08:08', '15:00', ['2026-10-15']);   // només 1 dia seguit des d'aleshores

        Sanctum::actingAs($this->gestio('hr'));
        $this->assertFalse($this->avisos()->has($w->id));
    }

    public function test_descartar_l_avis_el_treu_de_la_llista(): void
    {
        $w = $this->treballadora();
        $this->jornades($w, '08:08', '15:00');

        Sanctum::actingAs($this->gestio('hr'));
        $this->postJson("/api/v1/avisos-marge/{$w->id}/descarta", ['inici' => '2026-09-28'])->assertOk();

        $this->assertFalse($this->avisos()->has($w->id));
    }

    public function test_el_treballador_veu_el_seu_avis_encara_que_rrhh_l_hagi_descartat(): void
    {
        $w = $this->treballadora();
        $this->jornades($w, '08:08', '15:00');
        $puntual = $this->treballadora('Puntual');
        $this->jornades($puntual, '08:00', '15:00');

        Sanctum::actingAs($this->gestio('hr'));
        $this->postJson("/api/v1/avisos-marge/{$w->id}/descarta", ['inici' => '2026-09-28'])->assertOk();

        Sanctum::actingAs($w);
        $this->getJson('/api/v1/el-meu-avis-marge')->assertOk()->assertJson(['avis' => ['user_id' => $w->id, 'entrades_tard' => 15]]);
        Sanctum::actingAs($puntual);
        $this->assertNull($this->getJson('/api/v1/el-meu-avis-marge')->assertOk()->json('avis'));
    }

    public function test_coordinacio_i_treballadors_no_ho_veuen(): void
    {
        Sanctum::actingAs($this->gestio('coordinator'));
        $this->getJson('/api/v1/avisos-marge')->assertForbidden();

        $w = $this->treballadora();
        Sanctum::actingAs($w);
        $this->getJson('/api/v1/avisos-marge')->assertForbidden();
        $this->postJson("/api/v1/avisos-marge/{$w->id}/descarta", ['inici' => '2026-09-28'])->assertForbidden();
    }
}