<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Pràctiques: modalitat de conveni i hores que es van restant amb els fitxatges APROVATS del període. */
class PractiquesTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol): User
    {
        return User::create(['name' => 'P ' . $rol, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    private function fitxatge(User $u, string $dia, float $hores, string $estat): void
    {
        DB::table('work_logs')->insert([
            'user_id' => $u->id, 'date' => $dia, 'start_time' => "{$dia} 08:00:00", 'end_time' => "{$dia} 14:00:00",
            'total_hours_worked' => $hores, 'effective_hours' => $hores, 'status' => $estat, 'hour_status' => 'ok',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_les_hores_validades_es_resten_del_conveni(): void
    {
        $alumne = $this->usuari('worker');
        Sanctum::actingAs($this->usuari('hr'));
        $this->putJson("/api/v1/practiques/{$alumne->id}", [
            'practiques' => true, 'practiques_tipus' => 'fp_superior', 'practiques_hores' => 300,
            'practiques_inici' => '2026-09-01', 'practiques_centre' => 'Institut X',
            'detall' => ['estudis' => 'DAM', 'tutor_centre_nom' => 'Tutora', 'ects' => 12],
        ])->assertOk()->assertJson(['practiques' => true, 'tipus' => 'fp_superior', 'hores_conveni' => 300, 'restants' => 300])
          // Els ECTS no són d'aquesta modalitat: no es desen.
          ->assertJsonPath('detall.estudis', 'DAM')->assertJsonMissingPath('detall.ects');

        $this->fitxatge($alumne, '2026-08-31', 6, 'approved');  // abans de l'inici: no compta
        $this->fitxatge($alumne, '2026-09-02', 6, 'approved');
        $this->fitxatge($alumne, '2026-09-03', 5.5, 'approved');
        $this->fitxatge($alumne, '2026-09-04', 6, 'pending');   // encara no validat

        Sanctum::actingAs($alumne);
        $this->getJson("/api/v1/practiques/{$alumne->id}")->assertOk()
            ->assertJson(['fetes' => 11.5, 'pendents_validar' => 6, 'restants' => 288.5, 'percentatge' => 4, 'excedides' => 0])
            ->assertJsonPath('cataleg.uni_curriculars.ects', true);
    }

    public function test_cal_tipus_hores_i_inici_i_nomes_les_posa_gestio(): void
    {
        $alumne = $this->usuari('worker');
        Sanctum::actingAs($this->usuari('hr'));
        $this->putJson("/api/v1/practiques/{$alumne->id}", ['practiques' => true, 'practiques_hores' => 10, 'practiques_inici' => '2026-09-01'])
            ->assertStatus(422)->assertJsonValidationErrors('practiques_tipus');

        Sanctum::actingAs($alumne);
        $this->putJson("/api/v1/practiques/{$alumne->id}", ['practiques' => true, 'practiques_tipus' => 'altres', 'practiques_hores' => 10, 'practiques_inici' => '2026-09-01'])
            ->assertForbidden();
        $this->putJson("/api/v1/users/{$alumne->id}", ['practiques' => true, 'practiques_hores' => 10]);
        $this->assertFalse((bool) $alumne->fresh()->practiques);
    }

    public function test_les_no_laborals_exigeixen_beca(): void
    {
        $alumne = $this->usuari('worker');
        Sanctum::actingAs($this->usuari('admin'));
        $base = ['practiques' => true, 'practiques_tipus' => 'no_laborals', 'practiques_hores' => 400, 'practiques_inici' => '2026-09-01'];
        $this->putJson("/api/v1/practiques/{$alumne->id}", $base)->assertStatus(422);
        $this->putJson("/api/v1/practiques/{$alumne->id}", $base + ['detall' => ['beca_mensual' => 480]])->assertOk();
    }

    public function test_un_altre_treballador_no_ho_veu(): void
    {
        $alumne = $this->usuari('worker');
        Sanctum::actingAs($this->usuari('worker'));
        $this->getJson("/api/v1/practiques/{$alumne->id}")->assertForbidden();
    }
}
