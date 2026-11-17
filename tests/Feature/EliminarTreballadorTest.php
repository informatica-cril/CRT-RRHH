<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Eliminar una fitxa: s'esborra només si no ha fitxat mai; si no, baixa. Guardes per a admin i per a un mateix. */
class EliminarTreballadorTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol): User
    {
        return User::create(['name' => 'P ' . $rol . uniqid(), 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    public function test_sense_fitxatges_s_elimina_i_amb_fitxatges_es_dona_de_baixa(): void
    {
        $hr = $this->usuari('hr');
        $nou = $this->usuari('worker');
        $vell = $this->usuari('worker');
        DB::table('work_logs')->insert(['user_id' => $vell->id, 'date' => '2026-11-02', 'start_time' => '2026-11-02 08:00:00', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($hr);

        $this->deleteJson("/api/v1/users/{$nou->id}")->assertOk()->assertJson(['deactivated' => false]);
        $this->assertNull(User::find($nou->id));

        $this->deleteJson("/api/v1/users/{$vell->id}")->assertOk()->assertJson(['deactivated' => true]);
        $this->assertFalse((bool) User::find($vell->id)->active);
    }

    public function test_ningu_s_elimina_a_si_mateix_i_rrhh_no_toca_admins(): void
    {
        $hr = $this->usuari('hr');
        $admin = $this->usuari('admin');
        Sanctum::actingAs($hr);
        $this->deleteJson("/api/v1/users/{$hr->id}")->assertStatus(422);
        $this->deleteJson("/api/v1/users/{$admin->id}")->assertForbidden();
        $this->assertNotNull(User::find($admin->id));
    }

    public function test_no_es_pot_eliminar_l_unic_admin_actiu(): void
    {
        $unic = $this->usuari('admin');
        $altre = $this->usuari('admin');
        $altre->update(['active' => false]);
        Sanctum::actingAs($altre->fresh()->forceFill(['active' => true]));

        $this->deleteJson("/api/v1/users/{$unic->id}")->assertStatus(422);
    }
}
