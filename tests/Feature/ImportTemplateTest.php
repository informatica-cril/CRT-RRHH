<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'adm@test.local',
            'password' => Hash::make('x'), 'role' => 'admin', 'active' => true]);
    }

    public function test_nomes_admin_pot_descarregar(): void
    {
        $worker = User::create(['name' => 'W', 'email' => 'w@test.local',
            'password' => Hash::make('x'), 'role' => 'worker', 'active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($worker);
        $this->get('/api/v1/users/import-template')->assertForbidden();
    }

    public function test_descarrega_xlsx_valid(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());
        $r = $this->get('/api/v1/users/import-template');
        $r->assertOk();
        $r->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Desa i re-obre amb PhpSpreadsheet: ha de tenir 2 fulls i la capçalera esperada.
        $tmp = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        file_put_contents($tmp, $r->streamedContent());
        $ss = IOFactory::load($tmp);
        @unlink($tmp);

        $this->assertSame(['Altes', 'Instruccions'], $ss->getSheetNames());
        $altes = $ss->getSheetByName('Altes');
        $this->assertSame('Nom complet', $altes->getCell('A1')->getValue());
        $this->assertSame('DNI/NIE', $altes->getCell('B1')->getValue());
        // Les dues files d'exemple (laboral i autònom) hi són.
        $this->assertSame('laboral', $altes->getCell('L2')->getValue());
        $this->assertSame('autonom', $altes->getCell('L3')->getValue());
        // El full d'instruccions documenta cada columna.
        $this->assertSame('Columna', $ss->getSheetByName('Instruccions')->getCell('A1')->getValue());
    }
}
