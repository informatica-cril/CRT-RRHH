<?php

namespace Tests\Feature;

use App\Mail\SenseSortidaMail;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Jornades d'altres dies sense sortida: es declaren i s'avisa, però el registre no es toca mai.
 */
class JornadesSenseSortidaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 7, 9, 0, 0, 'Europe/Madrid'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role = 'worker'): User
    {
        return User::create([
            'name' => 'Persona ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);
    }

    private function oberta(User $u, string $dia): WorkLog
    {
        return WorkLog::create([
            'user_id' => $u->id, 'date' => $dia, 'start_time' => "{$dia} 08:27:00", 'status' => 'pending',
        ]);
    }

    public function test_la_persona_veu_les_seves_jornades_d_altres_dies_sense_sortida(): void
    {
        $u = $this->user();
        $this->oberta($u, '2026-10-02');
        $this->oberta($u, '2026-10-07'); // avui: encara es pot tancar normalment
        $this->oberta($this->user(), '2026-10-02'); // d'una altra persona
        Sanctum::actingAs($u);

        $this->getJson('/api/v1/work-logs/sense-sortida')
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.date', '2026-10-02')
            ->assertJsonPath('0.start_time', '2026-10-02 08:27:00')
            ->assertJsonPath('0.declaracio', null);
    }

    public function test_declarar_la_sortida_no_modifica_el_registre(): void
    {
        $u = $this->user();
        $log = $this->oberta($u, '2026-10-02');
        Sanctum::actingAs($u);

        $this->postJson("/api/v1/work-logs/{$log->id}/declara-sortida", [
            'sortida' => '2026-10-02 15:00', 'explicacio' => 'Em vaig oblidar de fitxar en marxar',
        ])->assertCreated()->assertJsonPath('declaracio.sortida', '2026-10-02 15:00');

        $log->refresh();
        $this->assertNull($log->end_time);
        $this->assertSame('pending', $log->status);
        $this->assertDatabaseHas('work_log_modifications', ['work_log_id' => $log->id, 'user_id' => $u->id, 'action' => 'allegacio']);

        // Només una declaració per jornada
        $this->postJson("/api/v1/work-logs/{$log->id}/declara-sortida", [
            'sortida' => '2026-10-02 16:00', 'explicacio' => 'Una altra versió dels fets',
        ])->assertStatus(422);
    }

    public function test_validacions_de_la_declaracio(): void
    {
        $u = $this->user();
        $log = $this->oberta($u, '2026-10-02');
        $avui = $this->oberta($u, '2026-10-07');
        $explicacio = 'Explicació prou llarga';

        Sanctum::actingAs($this->user());
        $this->postJson("/api/v1/work-logs/{$log->id}/declara-sortida", ['sortida' => '2026-10-02 15:00', 'explicacio' => $explicacio])
            ->assertForbidden(); // no és la titular

        Sanctum::actingAs($u);
        $this->postJson("/api/v1/work-logs/{$log->id}/declara-sortida", ['sortida' => '2026-10-02 08:00', 'explicacio' => $explicacio])->assertStatus(422);
        $this->postJson("/api/v1/work-logs/{$log->id}/declara-sortida", ['sortida' => '2026-10-04 15:00', 'explicacio' => $explicacio])->assertStatus(422);
        $this->postJson("/api/v1/work-logs/{$log->id}/declara-sortida", ['sortida' => '2026-10-02 15:00', 'explicacio' => 'curt'])->assertStatus(422);
        $this->postJson("/api/v1/work-logs/{$avui->id}/declara-sortida", ['sortida' => '2026-10-07 08:50', 'explicacio' => $explicacio])->assertStatus(422);
    }

    public function test_gestio_veu_la_llista_i_la_safata_les_compta(): void
    {
        $u = $this->user();
        $this->oberta($u, '2026-10-02');
        $this->oberta($u, '2026-10-06');

        Sanctum::actingAs($this->user('coordinator'));
        $this->getJson('/api/v1/work-logs/sense-sortida/equip')->assertOk()->assertJsonCount(2)->assertJsonPath('0.nom', $u->name);
        $cua = collect($this->getJson('/api/v1/safata/resum')->json('cues'))->firstWhere('clau', 'sensesortida');
        $this->assertSame(2, $cua['n']);

        Sanctum::actingAs($u);
        $this->getJson('/api/v1/work-logs/sense-sortida/equip')->assertForbidden();
    }

    public function test_avis_per_correu_nomes_a_qui_no_ha_declarat(): void
    {
        Mail::fake();
        $senseDeclarar = $this->user();
        $this->oberta($senseDeclarar, '2026-10-02');
        $this->oberta($senseDeclarar, '2026-10-06');
        $declarada = $this->user();
        $log = $this->oberta($declarada, '2026-10-05');
        Sanctum::actingAs($declarada);
        $this->postJson("/api/v1/work-logs/{$log->id}/declara-sortida", ['sortida' => '2026-10-05 15:00', 'explicacio' => 'Oblit en marxar del centre'])->assertCreated();

        $this->artisan('worklogs:avis-sense-sortida')->assertSuccessful();

        Mail::assertSent(SenseSortidaMail::class, 1);
        Mail::assertSent(SenseSortidaMail::class, fn ($m) => $m->hasTo($senseDeclarar->email) && $m->logs->count() === 2);
    }
}
