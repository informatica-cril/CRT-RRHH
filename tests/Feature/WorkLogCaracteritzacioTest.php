<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogModification;
use App\Models\WorkLogSegment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CARACTERITZACIÓ del comportament actual dels endpoints de fichatge, escrita
 * ABANS de refactoritzar (FormRequests / WorkLogService). Si un d'aquests tests
 * canvia de resultat, el refactor ha canviat comportament observable.
 *
 * El contracte que es fixa:
 *   - L'hora d'inici/fi la imposa el SERVIDOR (UTC), mai el client.
 *   - Sense GPS i sense via_domi, la justificació (min 15) és OBLIGATÒRIA i es
 *     traça com a WorkLogModification, MAI dins del work_log (EIPD §6.4).
 *   - Una sessió oberta duplicada retorna l'existent (200), no en crea una altra.
 *   - El tancament calcula les hores server-side i segmenta el fichatge.
 *   - El detall AMAGA les coordenades fora de conflicte per a tercers no admin
 *     i les mostra al titular (EIPD §6.3).
 */
class WorkLogCaracteritzacioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // getAccurateTime(): l'API externa de temps ha de FALLAR perquè el codi
        // caigui al rellotge local (cap petició real des dels tests).
        Http::fake(['*' => Http::response(null, 500)]);
    }

    private function worker(): User
    {
        return User::create([
            'name' => 'Treballador Fichatge', 'email' => 'w' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
        ]);
    }

    private function coordinator(): User
    {
        return User::create([
            'name' => 'Coordinació', 'email' => 'c' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'coordinator', 'active' => true,
        ]);
    }

    /** Fichatge obert (sense end_time) creat directament amb la convenció BD: hora de Madrid directa. */
    private function worklogObert(User $user, int $hoursAgo = 4, array $extra = []): WorkLog
    {
        $start = Carbon::now('Europe/Madrid')->subHours($hoursAgo);

        return WorkLog::create(array_merge([
            'user_id' => $user->id,
            'date' => $start->copy()->setTimezone('Europe/Madrid')->toDateString(),
            'start_time' => $start->toDateTimeString(),
            'status' => 'pending',
        ], $extra));
    }

    private function payloadFitxada(User $user, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'date' => '2020-01-01',
            'start_time' => '2020-01-01 08:00:00', // el servidor l'ha d'ignorar
            'start_location_lat' => 41.38,
            'start_location_lng' => 2.17,
            'start_location_match' => true,
        ], $overrides);
    }

    public function test_fitxar_amb_gps_crea_worklog_amb_hora_de_servidor(): void
    {
        $w = $this->worker();
        Sanctum::actingAs($w);

        $r = $this->postJson('/api/v1/work-logs', $this->payloadFitxada($w));
        $r->assertStatus(201);

        $log = WorkLog::findOrFail($r->json('id'));
        $this->assertSame(Carbon::now()->toDateString(), $log->date->toDateString(),
            'la data l\'imposa el servidor, no el client');
        $startLocal = Carbon::parse($log->getRawOriginal('start_time'), 'Europe/Madrid');
        $this->assertTrue($startLocal->diffInMinutes(Carbon::now('Europe/Madrid')) < 2,
            'start_time ha de ser l\'hora del servidor (Madrid), no la del client (2020)');
        $this->assertNull($log->end_time);
    }

    public function test_fitxar_sense_gps_sense_justificacio_es_rebutja_422(): void
    {
        $w = $this->worker();
        Sanctum::actingAs($w);

        $r = $this->postJson('/api/v1/work-logs', $this->payloadFitxada($w, [
            'start_location_lat' => null, 'start_location_lng' => null,
            'start_location_match' => null,
        ]));
        $r->assertStatus(422)->assertJsonValidationErrors(['justificacio']);
        $this->assertSame(0, WorkLog::count());
    }

    public function test_fitxar_sense_gps_amb_justificacio_traça_modificacio_fora_del_worklog(): void
    {
        $w = $this->worker();
        Sanctum::actingAs($w);

        $r = $this->postJson('/api/v1/work-logs', $this->payloadFitxada($w, [
            'start_location_lat' => null, 'start_location_lng' => null,
            'start_location_match' => null,
            'justificacio' => 'El mòbil corporatiu s\'ha quedat sense bateria',
        ]));
        $r->assertStatus(201);

        $mod = WorkLogModification::where('work_log_id', $r->json('id'))->first();
        $this->assertNotNull($mod, 'la justificació ha de quedar traçada com a modificació');
        $this->assertSame('registre_manual', $mod->action);
        $this->assertStringContainsString('bateria', $mod->comment);
        // EIPD §6.4: la justificació MAI viatja dins del work_log
        $this->assertArrayNotHasKey('justificacio', $r->json());
    }

    public function test_via_domi_sense_gps_no_exigeix_justificacio(): void
    {
        $w = $this->worker();
        Sanctum::actingAs($w);

        $r = $this->postJson('/api/v1/work-logs', $this->payloadFitxada($w, [
            'start_location_lat' => null, 'start_location_lng' => null,
            'start_location_match' => null,
            'via_domi' => true,
        ]));
        $r->assertStatus(201);
    }

    public function test_sessio_oberta_duplicada_retorna_existent_sense_crear_ne_una_altra(): void
    {
        // Rellotge congelat a mig matí: el fichatge existent es crea 1 h abans d'"ara" i el
        // controlador busca la sessió oberta del DIA D'AVUI (Europe/Madrid). Executant la suite
        // entre les 00:00 i les 01:59 de Madrid, "ara − 1 h" cau al dia anterior i la cerca no
        // trobava res (201 en lloc de 200). El test provava la zona horària, no el duplicat.
        Carbon::setTestNow(Carbon::parse('2026-05-20 10:00:00', 'Europe/Madrid'));

        $w = $this->worker();
        Sanctum::actingAs($w);
        $existent = $this->worklogObert($w, 1);

        $r = $this->postJson('/api/v1/work-logs', $this->payloadFitxada($w));
        $r->assertStatus(200);
        $this->assertSame($existent->id, $r->json('id'));
        $this->assertSame(1, WorkLog::where('user_id', $w->id)->count());
    }

    public function test_tancament_calcula_hores_server_side_i_segmenta(): void
    {
        $w = $this->worker(); // sense horari definit → un únic tram
        Sanctum::actingAs($w);
        $log = $this->worklogObert($w, 4, [
            'start_location_match' => true,
        ]);

        $r = $this->putJson("/api/v1/work-logs/{$log->id}", [
            'end_time' => '2020-01-01 09:00:00', // el servidor l'ha d'ignorar
            'end_location_match' => true,
            'total_hours_worked' => 99, // el servidor l'ha de recalcular
        ]);
        $r->assertStatus(200);

        $log->refresh();
        $endLocal = Carbon::parse($log->getRawOriginal('end_time'), 'Europe/Madrid');
        $this->assertTrue($endLocal->diffInMinutes(Carbon::now('Europe/Madrid')) < 2,
            'end_time ha de ser l\'hora del servidor');
        $this->assertEqualsWithDelta(4.0, (float) $log->total_hours_worked, 0.05,
            'les hores les calcula el servidor a partir dels seus propis timestamps');
        $this->assertSame(0, (int) $log->complementary_minutes, 'sota 8h no hi ha complementàries');

        $this->assertTrue((bool) $log->segmented);
        $segments = WorkLogSegment::where('work_log_id', $log->id)->get();
        $this->assertCount(1, $segments, 'sense horari definit: un únic tram');
        $this->assertSame('approved', $segments->first()->status,
            'tram dins de zona (inici i fi) s\'autoaprova');
        $this->assertIsArray($r->json('segments'), 'la resposta inclou els trams');
    }

    public function test_tancament_mes_de_8h_genera_minuts_complementaris(): void
    {
        $w = $this->worker();
        Sanctum::actingAs($w);
        $log = $this->worklogObert($w, 9, ['start_location_match' => true]);

        $this->putJson("/api/v1/work-logs/{$log->id}", [
            'end_time' => Carbon::now()->toDateTimeString(),
            'end_location_match' => true,
        ])->assertStatus(200);

        $log->refresh();
        $this->assertEqualsWithDelta(60, (int) $log->complementary_minutes, 3,
            '9h treballades → ~60 minuts complementaris (sobre les 8h)');
    }

    public function test_detail_amaga_coordenades_a_tercers_fora_de_conflicte(): void
    {
        $w = $this->worker();
        $log = $this->worklogObert($w, 2, [
            'start_location_lat' => '41.38', 'start_location_lng' => '2.17',
            'location_match' => true, 'start_location_match' => true,
        ]);

        // Un tercer NO admin (coordinació) sense conflicte ni motiu: SENSE coordenades
        Sanctum::actingAs($this->coordinator());
        $r = $this->getJson("/api/v1/work-logs/{$log->id}/detail");
        $r->assertStatus(200);
        $this->assertTrue((bool) $r->json('coords_restringides'));
        $this->assertArrayNotHasKey('start_location_lat', $r->json());

        // El TITULAR de les dades sí que les veu (art. 15 RGPD)
        Sanctum::actingAs($w);
        $r2 = $this->getJson("/api/v1/work-logs/{$log->id}/detail");
        $r2->assertStatus(200);
        $this->assertNull($r2->json('coords_restringides'));
        $this->assertSame('41.38', (string) $r2->json('start_location_lat'));
    }
}
