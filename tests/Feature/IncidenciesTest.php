<?php

namespace Tests\Feature;

use App\Mail\IncidenciaMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Incidències: el treballador n'envia; RRHH i Superior les veuen, reben el correu i les resolen. */
class IncidenciesTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol): User
    {
        return User::create(['name' => 'P ' . $rol, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    public function test_el_treballador_envia_rrhh_rep_el_correu_i_la_resol(): void
    {
        Mail::fake();
        $t = $this->usuari('worker');
        Sanctum::actingAs($t);
        $this->postJson('/api/v1/incidencies', ['tipus' => 'fitxatge', 'assumpte' => 'No em deixa fitxar',
            'descripcio' => "Avui al matí l'app no carregava el botó."])->assertCreated();

        Mail::assertSent(IncidenciaMail::class, fn ($m) => $m->hasTo(config('mail.reply_to.address')));
        $this->getJson('/api/v1/incidencies')->assertForbidden();   // el treballador només l'envia

        Sanctum::actingAs($this->usuari('hr'));
        $llista = $this->getJson('/api/v1/incidencies?estat=oberta')->assertOk()->json();
        $this->assertCount(1, $llista);
        $this->assertSame($t->name, $llista[0]['user']['name']);
        $this->assertSame(1, collect($this->getJson('/api/v1/safata/resum')->json('cues'))->firstWhere('clau', 'incidencies')['n']);

        $this->postJson("/api/v1/incidencies/{$llista[0]['id']}/resol", ['nota' => 'Ja funciona'])->assertOk()->assertJson(['estat' => 'resolta']);
        $this->assertCount(0, $this->getJson('/api/v1/incidencies?estat=oberta')->json());
    }

    public function test_dades_obligatories(): void
    {
        Sanctum::actingAs($this->usuari('worker'));
        $this->postJson('/api/v1/incidencies', ['tipus' => 'inventat', 'assumpte' => '', 'descripcio' => 'curt'])
            ->assertStatus(422);
    }
}
