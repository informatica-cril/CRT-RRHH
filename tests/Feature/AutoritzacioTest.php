<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AUTORITZACIÓ backend (B1 de la revisió pre-producció). Fixa que un 'worker'
 * autenticat NO pot fer accions de gestió ni accedir a dades de tercers. Abans
 * d'aquest treball, l'enforcement vivia gairebé només al frontend; aquests tests
 * demostren que ara el backend és fail-closed.
 *
 * Regla: personal de gestió = admin/coordinator/hr (isStaff). worker = només el seu.
 */
class AutoritzacioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 500)]);
    }

    private function make(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($role) . ' ' . uniqid(),
            'email' => $role . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ], $extra));
    }

    // ── Usuaris: apropiació de compte i CRUD ──────────────────────────────

    public function test_worker_no_pot_resetejar_contrasenya_daltri(): void
    {
        $victima = $this->make('admin');
        $atacant = $this->make('worker');
        Sanctum::actingAs($atacant);

        $r = $this->putJson("/api/v1/users/{$victima->id}", ['password' => 'segrestada123']);
        $this->assertContains($r->getStatusCode(), [403], 'un worker no pot tocar un altre usuari');
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('x', $victima->fresh()->password),
            'la contrasenya de la víctima NO s\'ha d\'haver canviat'
        );
    }

    public function test_worker_no_pot_canviar_se_la_propia_contrasenya_via_users_update(): void
    {
        // El canvi propi té endpoint dedicat (auth/change-password); users/update no és la via.
        $w = $this->make('worker');
        Sanctum::actingAs($w);

        $this->putJson("/api/v1/users/{$w->id}", ['password' => 'novapass123']);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('x', $w->fresh()->password),
            'users/update no ha de canviar la contrasenya ni la pròpia');
    }

    public function test_worker_pot_editar_el_seu_nom(): void
    {
        $w = $this->make('worker');
        Sanctum::actingAs($w);

        $this->putJson("/api/v1/users/{$w->id}", ['name' => 'Nou Nom'])->assertStatus(200);
        $this->assertSame('Nou Nom', $w->fresh()->name);
    }

    public function test_worker_no_pot_crear_ni_esborrar_usuaris(): void
    {
        $altre = $this->make('worker');
        Sanctum::actingAs($this->make('worker'));

        $this->postJson('/api/v1/users', [
            'name' => 'X', 'email' => 'x@test.local', 'password' => 'secret123', 'role' => 'admin',
        ])->assertStatus(403);
        $this->deleteJson("/api/v1/users/{$altre->id}")->assertStatus(403);
        $this->assertNotNull($altre->fresh(), 'la víctima no s\'ha d\'haver esborrat');
    }

    public function test_admin_si_pot_crear_usuaris(): void
    {
        Sanctum::actingAs($this->make('admin'));
        $this->postJson('/api/v1/users', [
            'name' => 'Nou', 'email' => 'nou@test.local', 'password' => 'secret123', 'role' => 'worker',
        ])->assertStatus(201);
    }

    // ── Nòmines ───────────────────────────────────────────────────────────

    public function test_worker_no_pot_llistar_totes_les_nomines(): void
    {
        Sanctum::actingAs($this->make('worker'));
        $this->getJson('/api/v1/payroll-files')->assertStatus(403);
    }

    public function test_worker_no_pot_veure_nomines_daltri(): void
    {
        $altre = $this->make('worker');
        Sanctum::actingAs($this->make('worker'));
        $this->getJson("/api/v1/payroll-files/worker/{$altre->id}")->assertStatus(403);
    }

    public function test_worker_si_pot_veure_les_seves_nomines(): void
    {
        $w = $this->make('worker');
        Sanctum::actingAs($w);
        $this->getJson("/api/v1/payroll-files/worker/{$w->id}")->assertStatus(200);
    }

    // ── Fichatges ─────────────────────────────────────────────────────────

    public function test_worker_no_pot_llistar_tots_els_fichatges(): void
    {
        Sanctum::actingAs($this->make('worker'));
        $this->getJson('/api/v1/work-logs')->assertStatus(403);
    }

    public function test_worker_no_pot_veure_fichatges_daltri(): void
    {
        $altre = $this->make('worker');
        Sanctum::actingAs($this->make('worker'));
        $this->getJson("/api/v1/work-logs/user/{$altre->id}")->assertStatus(403);
    }

    public function test_worker_no_pot_aprovar_trams_de_fichatge(): void
    {
        $victima = $this->make('worker');
        $log = WorkLog::create([
            'user_id' => $victima->id, 'date' => Carbon::now()->toDateString(),
            'start_time' => Carbon::now('UTC')->subHours(2)->toDateTimeString(), 'status' => 'pending',
        ]);
        $seg = \App\Models\WorkLogSegment::create([
            'work_log_id' => $log->id, 'segment_number' => 1,
            'start_time' => $log->start_time, 'end_time' => Carbon::now('UTC')->toDateTimeString(),
            'in_zone' => false, 'in_schedule' => false, 'duration_minutes' => 120, 'status' => 'pending',
        ]);
        Sanctum::actingAs($victima); // el propi titular tampoc s'autoaprova
        $this->postJson("/api/v1/work-logs/{$log->id}/segments/{$seg->id}/approve")->assertStatus(403);
        $this->assertSame('pending', $seg->fresh()->status);
    }

    // ── Absències ─────────────────────────────────────────────────────────

    public function test_worker_no_pot_aprovar_absencies(): void
    {
        $w = $this->make('worker');
        $abs = \App\Models\Absence::create([
            'user_id' => $w->id, 'absence_type_id' => 1,
            'start_date' => '2026-08-01', 'end_date' => '2026-08-02', 'approved' => null,
        ]);
        Sanctum::actingAs($w);
        $this->putJson("/api/v1/absences/{$abs->id}", ['approved' => true])->assertStatus(403);
        $this->assertNull($abs->fresh()->approved, 'un worker no s\'aprova la seva absència');
    }

    // ── Auditoria ENS ─────────────────────────────────────────────────────

    public function test_worker_no_pot_llegir_ni_injectar_auditoria(): void
    {
        Sanctum::actingAs($this->make('worker'));
        $this->getJson('/api/v1/audit-logs')->assertStatus(403);
        $this->postJson('/api/v1/audit-logs', [
            'action' => 'FAKE', 'entity_type' => 'x', 'description' => 'injectada',
        ])->assertStatus(403);
    }

    // ── Config sensible ───────────────────────────────────────────────────

    public function test_worker_no_pot_canviar_config_de_correu_ni_pauses(): void
    {
        Sanctum::actingAs($this->make('worker'));
        $this->putJson('/api/v1/mail-settings', ['mail_host' => 'evil.example'])->assertStatus(403);
        $this->putJson('/api/v1/break-settings', ['enabled' => false])->assertStatus(403);
    }

    // ── Chat (IDOR) ───────────────────────────────────────────────────────

    public function test_worker_no_pot_llistar_totes_les_converses_ni_cercar_global(): void
    {
        Sanctum::actingAs($this->make('worker'));
        $this->getJson('/api/v1/chat/conversations/all')->assertStatus(403);
        $this->getJson('/api/v1/chat/search?q=x')->assertStatus(403);
        $this->getJson('/api/v1/chat/alerts')->assertStatus(403);
    }

    // ── Catàlegs / recursos de gestió ─────────────────────────────────────

    public function test_worker_no_pot_gestionar_catalegs(): void
    {
        Sanctum::actingAs($this->make('worker'));
        $this->postJson('/api/v1/zones', ['name' => 'Z', 'type' => 'CP'])->assertStatus(403);
        $this->postJson('/api/v1/auth-codes', ['code' => 'X'])->assertStatus(403);
        $this->postJson('/api/v1/absence-types', ['name' => 'X'])->assertStatus(403);
    }

    public function test_worker_no_pot_llegir_conversa_ni_missatges_daltri(): void
    {
        $a = $this->make('worker');
        $b = $this->make('worker');
        $conv = \App\Models\ChatConversation::create(['type' => 'dm', 'created_by' => $a->id]);
        $conv->users()->sync([$a->id, $b->id]);
        \App\Models\ChatMessage::create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'content' => 'privat']);

        $intrus = $this->make('worker');
        Sanctum::actingAs($intrus);
        $this->getJson("/api/v1/chat/conversations/{$conv->id}/messages")->assertStatus(403);
        $this->getJson("/api/v1/chat/conversations/{$conv->id}")->assertStatus(403);
    }

    public function test_worker_no_pot_enviar_missatge_en_nom_daltri(): void
    {
        $a = $this->make('worker');
        $conv = \App\Models\ChatConversation::create(['type' => 'dm', 'created_by' => $a->id]);
        $intrus = $this->make('worker');
        $conv->users()->sync([$a->id, $intrus->id]);

        Sanctum::actingAs($intrus);
        // sender_id falsejat (l'atacant diu ser $a)
        $this->postJson('/api/v1/chat/messages', [
            'conversation_id' => $conv->id, 'sender_id' => $a->id, 'content' => 'suplantant',
        ])->assertStatus(403);
        $this->assertSame(0, \App\Models\ChatMessage::where('conversation_id', $conv->id)->count());
    }

    public function test_participant_si_pot_llegir_els_seus_missatges(): void
    {
        $a = $this->make('worker');
        $b = $this->make('worker');
        $conv = \App\Models\ChatConversation::create(['type' => 'dm', 'created_by' => $a->id]);
        $conv->users()->sync([$a->id, $b->id]);
        \App\Models\ChatMessage::create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'content' => 'hola']);

        Sanctum::actingAs($b);
        $this->getJson("/api/v1/chat/conversations/{$conv->id}/messages")->assertStatus(200)->assertJsonCount(1);
    }

    // ── Coordinador: sí és staff ──────────────────────────────────────────

    public function test_coordinator_es_staff_i_veu_fichatges(): void
    {
        Sanctum::actingAs($this->make('coordinator'));
        $this->getJson('/api/v1/work-logs')->assertStatus(200);
    }
}
