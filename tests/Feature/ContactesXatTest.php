<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El treballador ha de poder triar destinatari al xat sense accedir a la plantilla sencera. */
class ContactesXatTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_treballador_veu_els_contactes_actius_nomes_amb_nom_i_perfil(): void
    {
        $w = User::create(['name' => 'Treballadora', 'email' => 'w@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true, 'dni' => '12345678Z']);
        User::create(['name' => 'Baixa', 'email' => 'b@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => false]);

        $this->actingAs($w, 'sanctum')->getJson('/api/v1/users')->assertForbidden();   // la plantilla segueix tancada

        $r = $this->actingAs($w, 'sanctum')->getJson('/api/v1/chat/contactes')->assertOk();
        $this->assertSame(['Treballadora'], array_column($r->json(), 'name'));
        $this->assertSame(['id', 'name', 'job_profile', 'active'], array_keys($r->json()[0]));   // ni DNI ni correu
    }
}
