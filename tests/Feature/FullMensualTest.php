<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Full mensual d'hores amb signatures: només per a gestió, per persona i sense coordenades. */
class FullMensualTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol, string $nom): User
    {
        return User::create(['name' => $nom, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'active' => true, 'dni' => strtoupper(substr(uniqid(), -8)) . 'X']);
    }

    private function fitxatge(User $u, string $dia): void
    {
        DB::table('work_logs')->insert([
            'user_id' => $u->id, 'date' => $dia, 'start_time' => "{$dia} 09:00:00", 'end_time' => "{$dia} 13:05:00",
            'total_hours_worked' => 4.08, 'effective_hours' => 4.08, 'status' => 'approved', 'hour_status' => 'ok',
            'start_location_lat' => 41.38, 'start_location_lng' => 2.17, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_el_pdf_porta_les_hores_i_les_dues_signatures(): void
    {
        $treb = $this->usuari('worker', 'ÓSCAR PROVA');
        $this->fitxatge($treb, '2026-11-09');
        Sanctum::actingAs($this->usuari('hr', 'RRHH'));

        $r = $this->get("/api/v1/work-logs/full-mensual?mes=2026-11&user_id={$treb->id}&format=pdf");

        $r->assertOk();
        $this->assertStringContainsString('text/html', $r->headers->get('Content-Type'));
        $r->assertSee('ÓSCAR PROVA');
        $r->assertSee('4 h 05 min');
        $r->assertSee('Signatura del treballador/a', false);
        $r->assertSee("Signatura de l'empresa", false);
        $r->assertSee('SONIA GOMEZ (RRHH)');
        $this->assertStringNotContainsString('41.38', $r->getContent());
    }

    public function test_word_es_baixa_com_a_document(): void
    {
        $treb = $this->usuari('worker', 'ANNA');
        Sanctum::actingAs($this->usuari('admin', 'ADMIN'));

        $r = $this->get("/api/v1/work-logs/full-mensual?mes=2026-11&user_id={$treb->id}&format=word");

        $r->assertOk();
        $this->assertStringContainsString('application/msword', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
    }

    public function test_un_treballador_no_el_pot_demanar_i_cal_persona(): void
    {
        $treb = $this->usuari('worker', 'ANNA');
        Sanctum::actingAs($treb);
        $this->get("/api/v1/work-logs/full-mensual?mes=2026-11&user_id={$treb->id}")->assertForbidden();

        Sanctum::actingAs($this->usuari('hr', 'RRHH'));
        $this->getJson('/api/v1/work-logs/full-mensual?mes=2026-11')->assertStatus(422);
    }
}
