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
    private function jornades(User $w, string $entrada, string $sortida, array $puntual = [], string $estat = 'pending'): void
    {
        for ($d = Carbon::parse('2026-09-28'); $d->lte(Carbon::parse('2026-10-16')); $d->addDay()) {
            if ($d->isWeekend()) continue;
            $dia = $d->toDateString();
            $ent = in_array($dia, $puntual, true) ? '08:00' : $entrada;
            WorkLog::create([
                'user_id' => $w->id, 'date' => $dia, 'status' => $estat,
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

    public function test_els_fitxatges_ja_aprovats_no_sumen_a_la_ratxa(): void
    {
        $w = $this->treballadora('Tot aprovat');
        $this->jornades($w, '08:08', '15:00', [], 'approved');

        Sanctum::actingAs($this->gestio('hr'));
        $this->assertSame([], $this->avisos()->all());
    }

    public function test_els_aprovats_no_compten_pero_queden_marcats_a_la_ratxa(): void
    {
        $w = $this->treballadora('Barrejat');
        $this->jornades($w, '08:08', '15:00');                          // pendents: ratxa
        \App\Models\WorkLog::where('user_id', $w->id)->whereDate('date', '2026-10-15')->update(['status' => 'approved']);

        Sanctum::actingAs($this->gestio('hr'));
        $a = $this->avisos()[$w->id];
        $aprovat = \App\Models\WorkLog::where('user_id', $w->id)->whereDate('date', '2026-10-15')->value('id');
        $this->assertNotContains($aprovat, $a['log_ids']);              // no suma
        $this->assertContains($aprovat, $a['marcats_ids']);             // però surt marcat
    }

    public function test_els_aprovats_surten_marcats_encara_que_no_hi_hagi_avis(): void
    {
        $w = $this->treballadora('Tot aprovat marcat');
        $this->jornades($w, '08:08', '15:00', [], 'approved');

        Sanctum::actingAs($this->gestio('hr'));
        $this->assertSame([], $this->avisos()->all());                   // cap avís: tot revisat
        $ids = $this->getJson('/api/v1/avisos-marge/marques')->assertOk()->json('ids');
        $this->assertSame(\App\Models\WorkLog::where('user_id', $w->id)->count(), count($ids));   // però tots marcats
        $rang = array_values($this->getJson('/api/v1/avisos-marge/marques')->json('rangs'))[0];
        $this->assertSame(['2026-09-28', '2026-10-16'], $rang);                                      // amb el rang de la ratxa
    }

    public function test_es_poden_veure_els_dies_de_la_ratxa_d_un_fitxatge(): void
    {
        $w = $this->treballadora('Amb ratxa');
        $this->jornades($w, '08:08', '15:00', [], 'approved');
        $log = \App\Models\WorkLog::where('user_id', $w->id)->whereDate('date', '2026-10-07')->first();

        Sanctum::actingAs($this->gestio('hr'));
        $r = $this->getJson("/api/v1/avisos-marge/ratxa/{$log->id}")->assertOk();
        $this->assertSame(\App\Models\WorkLog::where('user_id', $w->id)->count(), count($r->json('dies')));
        $this->assertSame(8, $r->json('dies.0.tard'));
        $this->assertSame('08:08', $r->json('dies.0.entrada'));

        Sanctum::actingAs($w);
        $this->getJson("/api/v1/avisos-marge/ratxa/{$log->id}")->assertForbidden();
    }

    public function test_les_diferencies_que_cobreix_la_bossa_no_avisen(): void
    {
        $w = $this->treballadora('Quasi puntual');
        $this->jornades($w, '08:03', '15:04');   // 3 min tard i 4 min de més: dins dels 5 min de la bossa

        Sanctum::actingAs($this->gestio('hr'));
        $this->assertSame([], $this->avisos()->all());
    }

    public function test_passar_se_just_del_marge_si_avisa(): void
    {
        $w = $this->treballadora('Sis minuts');
        $this->jornades($w, '08:06', '15:00');

        Sanctum::actingAs($this->gestio('hr'));
        $this->assertCount(1, $this->avisos());
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