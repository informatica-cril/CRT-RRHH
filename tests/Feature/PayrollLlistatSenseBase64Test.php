<?php

namespace Tests\Feature;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CONTRACTE DEL LLISTAT DE NÒMINES (GET /api/v1/payroll-files).
 *
 * Amb 1.598 nòmines reals el llistat serialitzava el PDF de cada nòmina DUES vegades
 * (payroll_base64 + l'accessor appended pdf_data): ~50 MB de JSON i 500 per
 * "Allowed memory size of 134217728 bytes exhausted".
 *
 * Aquestes proves fixen que el PDF NO viatja al llistat i que segueix arribant per
 * fila a GET /payroll-files/{payroll}, que és d'on el treu ara el frontend.
 */
class PayrollLlistatSenseBase64Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin ' . uniqid(), 'email' => 'admin' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true,
        ]);
    }

    private function treballador(): User
    {
        return User::create([
            'name' => 'Worker ' . uniqid(), 'email' => 'worker' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
        ]);
    }

    /** Nòmina amb un PDF gran de debò, per veure si el pes es filtra a la resposta. */
    private function nomina(User $titular, int $mes, string $pdf): Payroll
    {
        return Payroll::create([
            'user_id' => $titular->id,
            'title' => "Nòmina {$mes}",
            'month' => $mes,
            'year' => 2026,
            'file_name' => "nomina-{$mes}.pdf",
            'payroll_base64' => $pdf,
        ]);
    }

    public function test_el_llistat_no_serialitza_el_pdf(): void
    {
        $titular = $this->treballador();
        $pdf = 'data:application/pdf;base64,' . str_repeat('A', 200_000); // ~200 KB per fila
        foreach ([5, 6, 7] as $mes) {
            $this->nomina($titular, $mes, $pdf);
        }

        Sanctum::actingAs($this->admin());
        $resposta = $this->getJson('/api/v1/payroll-files')->assertStatus(200);

        $cru = $resposta->getContent();
        $this->assertStringNotContainsString('payroll_base64', $cru,
            'El llistat torna a exposar la columna del PDF: això és el que petava el memory_limit.');
        $this->assertStringNotContainsString(str_repeat('A', 1000), $cru,
            'El contingut del PDF viatja dins del llistat (via pdf_data o qualsevol altre àlies).');

        // 3 nòmines × 200 KB = 600 KB de PDF; el llistat ha de quedar en metadades.
        $this->assertLessThan(20_000, strlen($cru),
            'El llistat pesa ' . strlen($cru) . ' bytes: creix amb el PDF, no amb el nombre de files.');

        // I les metadades que la vista d'admin necessita hi segueixen sent.
        $primera = $resposta->json('0');
        foreach (['id', 'user_id', 'month', 'year', 'file_name', 'created_at', 'expires_at', 'signed_at', 'viewed_at'] as $camp) {
            $this->assertArrayHasKey($camp, $primera, "Falta el camp {$camp} al llistat.");
        }
        $this->assertSame('Nòmina 7', $primera['title']);
        $this->assertArrayHasKey('user', $primera);
    }

    public function test_el_pdf_arriba_per_fila(): void
    {
        $titular = $this->treballador();
        $pdf = 'data:application/pdf;base64,' . str_repeat('B', 5_000);
        $nomina = $this->nomina($titular, 7, $pdf);

        Sanctum::actingAs($this->admin());
        $this->getJson("/api/v1/payroll-files/{$nomina->id}")
            ->assertStatus(200)
            ->assertJsonPath('pdf_data', $pdf);

        // El titular també (la vista del treballador en depèn).
        Sanctum::actingAs($titular);
        $this->getJson("/api/v1/payroll-files/{$nomina->id}")
            ->assertStatus(200)
            ->assertJsonPath('pdf_data', $pdf);
    }

    /**
     * Caracterització: l'ordre del llistat no canvia en treure el base64.
     *
     * OJO — payrolls.month és VARCHAR, així que l'ORDER BY del controlador ordena
     * el mes com a TEXT ('7' > '3' > '11'), no com a número. Això ja passava abans
     * d'aquest canvi i el frontend torna a ordenar en client (filteredPayrolls),
     * per això aquí es fixa el comportament real, no el desitjable.
     */
    public function test_el_llistat_conserva_lordre_del_backend(): void
    {
        $titular = $this->treballador();
        $this->nomina($titular, 3, 'AAAA');
        $this->nomina($titular, 11, 'AAAA');
        $this->nomina($titular, 7, 'AAAA');

        Sanctum::actingAs($this->admin());
        $mesos = collect($this->getJson('/api/v1/payroll-files')->assertStatus(200)->json())
            ->pluck('month')->map(fn ($m) => (string) $m)->all();

        $this->assertSame(['7', '3', '11'], $mesos);
    }
}
