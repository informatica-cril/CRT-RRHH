<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Importar CSV (/users/bulk): errors clars i tot o res, en lloc d'un «Server Error». */
class ImportCsvBulkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('x'), 'role' => 'admin', 'active' => true]);
    }

    public function test_un_correu_que_ja_existeix_diu_la_fila_i_no_crea_ningu(): void
    {
        $a = $this->admin();
        User::create(['name' => 'Ja hi és', 'email' => 'rrhh@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);

        $r = $this->actingAs($a, 'sanctum')->postJson('/api/v1/users/bulk', ['users' => [
            ['name' => 'Nova Persona', 'email' => 'nova@test.local', 'role' => 'worker'],
            ['name' => 'RRHH', 'email' => 'RRHH@test.local', 'role' => 'worker'],
        ]])->assertStatus(422);

        $this->assertStringContainsString('Fila 2', $r->json('message'));
        $this->assertFalse(User::where('email', 'nova@test.local')->exists());
    }

    public function test_dni_null_i_horari_inexistent_no_fan_fallar_l_alta(): void
    {
        $a = $this->admin();
        $this->actingAs($a, 'sanctum')->postJson('/api/v1/users/bulk', ['users' => [
            ['name' => 'Persona Nova', 'email' => 'p@test.local', 'dni' => 'NULL', 'work_schedule_id' => 999, 'role' => 'worker'],
        ]])->assertCreated();

        $u = User::where('email', 'p@test.local')->first();
        $this->assertNull($u->dni);
        $this->assertNull($u->work_schedule_id);
    }
}
