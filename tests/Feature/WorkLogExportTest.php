<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Exportació a Excel del registre de jornada: el que es veu a la pantalla (mes, persona i vista),
 * només per al personal de gestió, i sense coordenades.
 */
class WorkLogExportTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol, string $nom = 'Persona'): User
    {
        return User::create([
            'name' => $nom, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'active' => true, 'dni' => strtoupper(substr(uniqid(), -8)) . 'X',
        ]);
    }

    private function fitxatge(User $u, string $dia, string $estat, array $extra = []): void
    {
        DB::table('work_logs')->insert($extra + [
            'user_id' => $u->id, 'date' => $dia, 'start_time' => "{$dia} 08:00:00", 'end_time' => "{$dia} 15:00:00",
            'total_hours_worked' => 7, 'effective_hours' => 7, 'status' => $estat, 'hour_status' => 'ok',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function llegeix($resp): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $resp->streamedContent());
        $files = IOFactory::load($tmp)->getActiveSheet()->toArray(null, false, false, false);
        unlink($tmp);
        return $files;
    }

    public function test_exporta_el_mes_amb_la_vista_triada(): void
    {
        $treb = $this->usuari('worker', 'ANNA PROVA');
        $this->fitxatge($treb, '2026-07-01', 'approved');
        $this->fitxatge($treb, '2026-07-02', 'pending', ['start_location_lat' => 41.38, 'start_location_lng' => 2.17]);
        $this->fitxatge($treb, '2026-08-01', 'pending'); // un altre mes: no hi ha de sortir

        Sanctum::actingAs($this->usuari('admin'));

        $tots = $this->get('/api/v1/work-logs/export?mes=2026-07');
        $tots->assertOk();
        $this->assertStringContainsString('spreadsheetml', $tots->headers->get('Content-Type'));
        $files = $this->llegeix($tots);
        $dades = array_values(array_filter($files, fn ($f) => $f[0] === 'ANNA PROVA'));
        $this->assertCount(2, $dades);
        $this->assertSame('01/07/2026', $dades[0][2]);
        $this->assertSame('08:00', $dades[0][3]);

        // Ni latitud ni longitud enlloc del fitxer (EIPD §6.3).
        $text = json_encode($files);
        $this->assertStringNotContainsString('41.38', $text);
        $this->assertStringNotContainsString('2.17', $text);

        $pend = $this->llegeix($this->get('/api/v1/work-logs/export?mes=2026-07&vista=pendents'));
        $this->assertCount(1, array_filter($pend, fn ($f) => $f[0] === 'ANNA PROVA'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'EXPORT_WORKLOGS']);
    }

    public function test_un_treballador_no_pot_exportar(): void
    {
        Sanctum::actingAs($this->usuari('worker'));
        $this->get('/api/v1/work-logs/export?mes=2026-07')->assertForbidden();
    }

    public function test_cal_un_mes_valid(): void
    {
        Sanctum::actingAs($this->usuari('admin'));
        $this->getJson('/api/v1/work-logs/export?mes=juliol')->assertStatus(422);
    }
}
