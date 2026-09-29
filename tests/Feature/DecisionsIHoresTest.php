<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\AuditLog;
use App\Models\AuthorizationCode;
use App\Models\ChatConversation;
use App\Models\User;
use App\Models\WorkLogAlert;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Garanties de la gestió de RRHH: rastre de les decisions (qui, quan, per què),
 * topalls d'hores fail-closed, avís al treballador i abast de les dades.
 */
class DecisionsIHoresTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::create(['name' => 'RRHH', 'email' => 'hr' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'hr', 'active' => true]);
    }

    private function quadre(float $setmanals): WorkSchedule
    {
        $dies = [];
        for ($d = 1; $d <= 5; $d++) {
            $h = $setmanals / 5;
            $fi = sprintf('%02d:%02d', 8 + (int) $h, (int) round(($h - (int) $h) * 60));
            $dies[] = ['day' => $d, 'active' => true, 'start' => '08:00', 'end' => $fi];
        }
        return WorkSchedule::create(['name' => 'Quadre ' . $setmanals, 'total_hours_weekly' => $setmanals, 'days' => $dies]);
    }

    private function treballador(?WorkSchedule $q = null): User
    {
        return User::create(['name' => 'Treballadora', 'email' => 'w' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
            'work_schedule_id' => $q?->id]);
    }

    private function absencia(User $u): Absence
    {
        $t = AbsenceType::create(['name' => 'Assumptes propis', 'category' => 'permission']);
        return Absence::create(['user_id' => $u->id, 'absence_type_id' => $t->id,
            'start_date' => '2026-09-07', 'end_date' => '2026-09-08']);
    }

    private function codi(User $hr, array $extra): array
    {
        return array_merge([
            'code' => 'C-' . strtoupper(uniqid()),
            'concept' => 'Prova',
            'authorized_hours' => 10,
            'time_slot_start' => '09:00', 'time_slot_end' => '13:00',
            'generated_by' => $hr->id,
            'valid_from' => '2026-08-14', 'valid_to' => '2026-12-31',
        ], $extra);
    }

    // ── Decisions: qui, quan i per què ──

    public function test_l_aprovador_surt_del_token_i_no_del_cos(): void
    {
        $hr = $this->hr();
        $altre = $this->hr();
        $a = $this->absencia($this->treballador());
        Sanctum::actingAs($hr);

        $this->putJson("/api/v1/absences/{$a->id}", ['approved' => true, 'approved_by' => $altre->id])
            ->assertOk();

        $a->refresh();
        $this->assertSame($hr->id, $a->approved_by);
        $this->assertNotNull($a->approved_at);
    }

    public function test_denegar_sense_motiu_es_rebutja_i_amb_motiu_es_desa(): void
    {
        $hr = $this->hr();
        $a = $this->absencia($this->treballador());
        Sanctum::actingAs($hr);

        $this->putJson("/api/v1/absences/{$a->id}", ['approved' => false])->assertStatus(422);
        $this->assertNull($a->fresh()->approved);

        $this->putJson("/api/v1/absences/{$a->id}", ['approved' => false, 'denial_reason' => 'Servei mínim'])
            ->assertOk();
        $this->assertSame('Servei mínim', $a->fresh()->denial_reason);
    }

    public function test_la_decisio_deixa_auditoria_i_avisa_el_treballador(): void
    {
        $hr = $this->hr();
        $u = $this->treballador();
        $a = $this->absencia($u);
        Sanctum::actingAs($hr);

        $this->putJson("/api/v1/absences/{$a->id}", ['approved' => true])->assertOk();

        $this->assertTrue(AuditLog::where('entity_type', 'absence')->where('entity_id', $a->id)
            ->where('user_id', $hr->id)->exists());
        $this->assertTrue(WorkLogAlert::where('user_id', $u->id)->where('type', 'resolucio_rrhh')->exists());
    }

    // ── Topalls d'hores ──

    public function test_sense_quadre_horari_no_s_autoritzen_hores(): void
    {
        $hr = $this->hr();
        $u = $this->treballador();               // sense quadre horari
        Sanctum::actingAs($hr);

        $this->postJson('/api/v1/auth-codes', $this->codi($hr, ['user_id' => $u->id, 'authorized_hours' => 500]))
            ->assertStatus(422);
        $this->assertSame(0, AuthorizationCode::count());
    }

    public function test_jornada_completa_no_pot_fer_complementaries_pero_si_extraordinaries(): void
    {
        $hr = $this->hr();
        $u = $this->treballador($this->quadre(37.5));
        Sanctum::actingAs($hr);

        $this->postJson('/api/v1/auth-codes', $this->codi($hr, ['user_id' => $u->id, 'type' => 'complementaria']))
            ->assertStatus(422);

        $this->postJson('/api/v1/auth-codes', $this->codi($hr, ['user_id' => $u->id, 'type' => 'extraordinaria']))
            ->assertStatus(201);
        $this->assertSame('1.25', (string) AuthorizationCode::first()->factor);
    }

    public function test_les_extraordinaries_tenen_topall_anual(): void
    {
        $hr = $this->hr();
        $u = $this->treballador($this->quadre(37.5));
        Sanctum::actingAs($hr);

        $this->postJson('/api/v1/auth-codes',
            $this->codi($hr, ['user_id' => $u->id, 'type' => 'extraordinaria', 'authorized_hours' => 81]))
            ->assertStatus(422);
    }

    public function test_una_parcial_pot_fer_complementaries_dins_del_topall(): void
    {
        $hr = $this->hr();
        $u = $this->treballador($this->quadre(20));
        Sanctum::actingAs($hr);

        $this->postJson('/api/v1/auth-codes', $this->codi($hr, ['user_id' => $u->id, 'type' => 'complementaria']))
            ->assertStatus(201);
    }

    public function test_el_quadre_de_control_no_amaga_ningu_i_marca_qui_no_es_calculable(): void
    {
        $hr = $this->hr();
        $this->treballador($this->quadre(37.5));
        $this->treballador();                    // sense quadre horari
        Sanctum::actingAs($hr);

        $files = $this->getJson('/api/v1/auth-codes/hours-control?year=2026')->assertOk()->json('rows');
        $this->assertCount(2, $files);
        $this->assertSame(1, collect($files)->where('calculable', false)->count());
        $this->assertNotNull(collect($files)->firstWhere('calculable', false)['motiu_no_calculable']);
    }

    public function test_la_bossa_anual_llista_tothom_amb_una_sola_jornada_i_un_sol_factor(): void
    {
        $hr = $this->hr();
        $this->treballador($this->quadre(37.5));
        $this->treballador();
        Sanctum::actingAs($hr);

        $r = $this->getJson('/api/v1/bossa-anual/tots')->assertOk()->json();
        $this->assertCount(2, $r['bosses']);
        $this->assertSame(1, $r['no_calculables']);
        $this->assertSame(1726.0, (float) $r['parametres']['jornada_anual_h']);
        $this->assertSame(1.25, (float) $r['parametres']['extra_factor']);
        $this->assertSame(1.25, (float) collect($r['bosses'])->firstWhere('calculable', true)['extra_factor']);
    }

    // ── Abast de les dades ──

    public function test_el_treballador_nomes_veu_el_seu_quadre_horari(): void
    {
        $meu = $this->quadre(20);
        $this->quadre(37.5);
        $u = $this->treballador($meu);
        Sanctum::actingAs($u);

        $r = $this->getJson('/api/v1/work-schedules')->assertOk()->json();
        $this->assertCount(1, $r);
        $this->assertSame($meu->id, $r[0]['id']);
    }

    public function test_rrhh_no_llegeix_converses_de_les_quals_no_es_part(): void
    {
        $hr = $this->hr();
        $a = $this->treballador();
        $b = $this->treballador();
        $conv = ChatConversation::create(['type' => 'dm', 'created_by' => $a->id]);
        $conv->users()->sync([$a->id, $b->id]);

        Sanctum::actingAs($hr);
        $this->getJson("/api/v1/chat/conversations/{$conv->id}/messages")->assertStatus(403);
        $this->getJson("/api/v1/chat/conversations/{$conv->id}/last-message")->assertStatus(403);
        $this->getJson("/api/v1/chat/conversations/{$conv->id}")->assertStatus(403);
        $this->getJson("/api/v1/chat/conversations?user_id={$a->id}")->assertStatus(403);

        Sanctum::actingAs($a);
        $this->getJson("/api/v1/chat/conversations/{$conv->id}/messages")->assertOk();
    }

    // ── Fitxa de la persona ──

    public function test_rrhh_pot_corregir_el_contacte_pero_no_els_camps_privilegiats(): void
    {
        $hr = $this->hr();
        $u = $this->treballador();
        Sanctum::actingAs($hr);

        $this->putJson("/api/v1/users/{$u->id}", ['device_phone' => '600111222', 'role' => 'admin'])
            ->assertOk();

        $u->refresh();
        $this->assertSame('600111222', $u->device_phone);
        $this->assertSame('worker', $u->role);
    }

    public function test_qui_te_registre_horari_es_dona_de_baixa_i_no_s_esborra(): void
    {
        $hr = $this->hr();
        $u = $this->treballador();
        \Illuminate\Support\Facades\DB::table('work_logs')->insert([
            'user_id' => $u->id, 'date' => '2026-08-10',
            'start_time' => '2026-08-10 08:00:00', 'end_time' => '2026-08-10 15:00:00',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($hr);

        $this->deleteJson("/api/v1/users/{$u->id}")->assertOk()->assertJson(['deactivated' => true]);
        $this->assertNotNull(User::find($u->id));
        $this->assertFalse((bool) User::find($u->id)->active);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('work_logs')->where('user_id', $u->id)->count());
    }

    // ── Justificant (part mèdic) ──

    public function test_el_justificant_es_puja_a_disc_privat_i_nomes_el_baixa_qui_toca(): void
    {
        Storage::fake();
        $u = $this->treballador();
        $altre = $this->treballador();
        $a = $this->absencia($u);

        Sanctum::actingAs($u);
        $this->postJson("/api/v1/absences/{$a->id}/justificant", [
            'justificant' => UploadedFile::fake()->create('part.pdf', 20, 'application/pdf'),
        ])->assertStatus(201);

        $a->refresh();
        $this->assertSame('part.pdf', $a->justificant_name);
        Storage::assertExists($a->justificant_path);
        // La ruta del fitxer no viatja mai per l'API.
        $this->assertArrayNotHasKey('justificant_path', $a->toArray());

        $this->get("/api/v1/absences/{$a->id}/justificant")->assertOk();

        Sanctum::actingAs($altre);
        $this->getJson("/api/v1/absences/{$a->id}/justificant")->assertStatus(403);
    }
}
