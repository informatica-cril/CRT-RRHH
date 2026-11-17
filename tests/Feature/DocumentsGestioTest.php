<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\OnboardingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Documents: RRHH els edita, els marca o desmarca com a urgents i els esborra (si no tenen firmes). */
class DocumentsGestioTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $rol): User
    {
        return User::create(['name' => 'P ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    private function doc(array $extra = []): Document
    {
        return Document::create($extra + ['title' => 'Circular', 'category' => 'laboral', 'target_users' => 'all',
            'requires_signature' => false, 'is_urgent' => false]);
    }

    public function test_es_pot_editar_i_canviar_la_urgencia(): void
    {
        Sanctum::actingAs($this->persona('hr'));
        $d = $this->doc();

        $this->putJson("/api/v1/documents/{$d->id}", ['title' => 'Circular nova', 'is_urgent' => true])->assertOk();
        $d->refresh();
        $this->assertSame('Circular nova', $d->title);
        $this->assertTrue($d->is_urgent);
        $this->assertTrue($d->requires_signature);   // urgent implica firma

        $this->putJson("/api/v1/documents/{$d->id}", ['is_urgent' => false])->assertOk();
        $this->assertFalse($d->fresh()->is_urgent);
        $this->assertTrue($d->fresh()->requires_signature);   // treure la urgència no treu la firma

        $this->putJson("/api/v1/documents/{$d->id}", ['requires_signature' => false, 'is_urgent' => true])->assertOk();
        $this->assertTrue($d->fresh()->is_urgent);   // is_urgent explícit guanya: segueix demanant firma
    }

    public function test_treure_la_firma_treu_tambe_la_urgencia(): void
    {
        Sanctum::actingAs($this->persona('hr'));
        $d = $this->doc(['requires_signature' => true, 'is_urgent' => true]);

        $this->putJson("/api/v1/documents/{$d->id}", ['requires_signature' => false])->assertOk();
        $this->assertFalse($d->fresh()->is_urgent);
        $this->assertFalse($d->fresh()->requires_signature);
    }

    public function test_un_treballador_no_pot_editar_ni_esborrar(): void
    {
        $d = $this->doc();
        Sanctum::actingAs($this->persona('worker'));
        $this->putJson("/api/v1/documents/{$d->id}", ['title' => 'X'])->assertForbidden();
        $this->deleteJson("/api/v1/documents/{$d->id}")->assertForbidden();
    }

    public function test_esborra_un_document_sense_firmes_i_el_treu_dels_perfils(): void
    {
        Sanctum::actingAs($this->persona('hr'));
        $d = $this->doc();
        $altre = $this->doc(['title' => 'Altre']);
        $p = OnboardingProfile::create(['name' => 'P', 'document_ids' => [$d->id, $altre->id],
            'doc_modes' => [$d->id => 'view_only', $altre->id => 'signature'], 'is_default' => false]);

        $this->deleteJson("/api/v1/documents/{$d->id}")->assertNoContent();
        $this->assertNull(Document::find($d->id));
        $p->refresh();
        $this->assertSame([$altre->id], $p->document_ids);
        $this->assertArrayNotHasKey($d->id, $p->doc_modes);
    }

    public function test_no_esborra_un_document_que_ja_te_firmes(): void
    {
        Sanctum::actingAs($this->persona('hr'));
        $d = $this->doc(['requires_signature' => true]);
        DocumentSignature::create(['document_id' => $d->id, 'user_id' => $this->persona('worker')->id, 'viewed_at' => now()]);

        $this->deleteJson("/api/v1/documents/{$d->id}")->assertStatus(422);
        $this->assertNotNull(Document::find($d->id));
    }

    public function test_un_document_ocult_no_el_veu_el_treballador_ni_el_bloqueja(): void
    {
        $t = $this->persona('worker');
        Document::query()->delete();   // sense els textos legals que ja porten les migracions
        // Dirigits a ell per id: la consulta de «tots» depèn del JSON de MySQL i a SQLite no es pot comprovar.
        $visible = $this->doc(['title' => 'Visible', 'target_users' => [$t->id]]);
        $ocult = $this->doc(['title' => 'Ocult', 'requires_signature' => true, 'is_urgent' => true, 'target_users' => [$t->id]]);

        Sanctum::actingAs($this->persona('hr'));
        $this->putJson("/api/v1/documents/{$ocult->id}", ['ocult' => true])->assertOk();
        $this->assertCount(2, $this->getJson('/api/v1/documents')->json());   // RRHH els segueix veient tots

        Sanctum::actingAs($t);
        $titols = collect($this->getJson("/api/v1/documents/user/{$t->id}")->assertOk()->json())->pluck('title')->all();
        $this->assertSame(['Visible'], $titols);
        $this->assertSame([], $this->getJson("/api/v1/document-signatures/urgent-unsigned/{$t->id}")->assertOk()->json());

        Sanctum::actingAs($this->persona('hr'));
        $this->putJson("/api/v1/documents/{$ocult->id}", ['ocult' => false])->assertOk();
        Sanctum::actingAs($t);
        $this->assertCount(2, $this->getJson("/api/v1/documents/user/{$t->id}")->json());
        $this->assertCount(1, $this->getJson("/api/v1/document-signatures/urgent-unsigned/{$t->id}")->json());
    }
}
