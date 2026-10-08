<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportPersonalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'adm@test.local',
            'password' => Hash::make('x'), 'role' => 'admin', 'active' => true]);
    }

    /** Construeix un .xlsx amb full 'Altes', capçalera + les files donades. */
    private function xlsx(array $files): UploadedFile
    {
        $ss = new Spreadsheet();
        $s = $ss->getActiveSheet();
        $s->setTitle('Altes');
        $cols = ['nom_complet', 'dni', 'email', 'telefon', 'data_naixement', 'usuari_domi', 'privilegi_domi',
            'rol_rrhh', 'ambit', 'categoria', 'jornada_setmanal_h', 'relacio', 'disponibilitat_h', 'segon_factor',
            'municipis', 'especialitat'];
        $cc = fn(int $col, int $row) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
        foreach ($cols as $i => $c) $s->setCellValue($cc($i + 1, 1), $c);
        $r = 2;
        foreach ($files as $f) { foreach ($f as $i => $v) $s->setCellValueExplicit($cc($i + 1, $r), (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); $r++; }
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new Xlsx($ss))->save($path);
        return new UploadedFile($path, 'altes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // fila: nom, dni, email, tel, naix, usuari, privilegi, rol, ambit, cat, jornada, relacio, dispo, 2fa, munis, esp
    private function filaLaboral(): array
    {
        return ['Anna Fisio', '11111111A', 'anna@crtbcn.cat', '', '', 'A.Fisio', 'Domiciliaria', 'worker',
            'DOMICILIARIA', 'Fisioterapeuta', '37.5', 'laboral', '', 'dispositiu', '', '', 'B1', 'RHB_DOMI'];
    }

    public function test_nomes_admin(): void
    {
        $w = User::create(['name' => 'W', 'email' => 'w@test.local', 'password' => Hash::make('x'), 'role' => 'worker', 'active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($w);
        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$this->filaLaboral()])])->assertForbidden();
    }

    public function test_rrhh_no_crea_ni_fa_administradors_per_excel(): void
    {
        $hr = User::create(['name' => 'RRHH', 'email' => 'hr@test.local', 'dni' => '22222222B',
            'password' => Hash::make('x'), 'role' => 'hr', 'active' => true]);
        $adm = User::create(['name' => 'Adm', 'email' => 'adm2@test.local', 'dni' => '33333333C',
            'password' => Hash::make('x'), 'role' => 'admin', 'active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($hr);

        $nouAdmin = $this->filaLaboral(); $nouAdmin[7] = 'admin';
        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$nouAdmin])])->assertStatus(422);
        $this->assertNull(User::where('dni', '11111111A')->first());

        // RRHH es fa admin a si mateixa (actualització per DNI): no.
        $joAdmin = $this->filaLaboral(); $joAdmin[1] = '22222222B'; $joAdmin[2] = 'hr@test.local'; $joAdmin[7] = 'admin';
        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$joAdmin])])->assertStatus(422);
        $this->assertSame('hr', $hr->fresh()->role);

        // Treure el rol a un administrador: tampoc.
        $baixa = $this->filaLaboral(); $baixa[1] = '33333333C'; $baixa[2] = 'adm2@test.local'; $baixa[7] = 'worker';
        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$baixa])])->assertStatus(422);
        $this->assertSame('admin', $adm->fresh()->role);

        // Una alta normal sí.
        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$this->filaLaboral()])])->assertOk();
    }

    public function test_alta_correcta(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());
        $r = $this->post('/api/v1/users/import', ['file' => $this->xlsx([$this->filaLaboral()])]);
        $r->assertOk()->assertJson(['ok' => true, 'creats' => 1, 'actualitzats' => 0]);

        $u = User::where('dni', '11111111A')->first();
        $this->assertNotNull($u);
        $this->assertSame('A.Fisio', $u->domi_username);
        $this->assertSame('Domiciliaria', $u->domi_privilege);
        $this->assertSame('laboral', $u->relacio);
        $this->assertTrue((bool) $u->must_change_password, 'l\'alta força canvi de contrasenya');
    }

    public function test_idempotent_per_dni(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());
        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$this->filaLaboral()])])->assertOk();
        // Mateix DNI, dades canviades -> ACTUALITZA, no duplica.
        $f = $this->filaLaboral(); $f[0] = 'Anna Fisio Cognom'; $f[6] = 'Valorador';
        $r = $this->post('/api/v1/users/import', ['file' => $this->xlsx([$f])]);
        $r->assertOk()->assertJson(['creats' => 0, 'actualitzats' => 1]);
        $this->assertSame(1, User::where('dni', '11111111A')->count());
        $this->assertSame('Valorador', User::where('dni', '11111111A')->first()->domi_privilege);
    }

    public function test_tot_o_res_amb_errors(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());
        $bona = $this->filaLaboral();
        $dolenta = $this->filaLaboral();
        $dolenta[1] = '22222222B'; $dolenta[2] = 'email-invalid'; $dolenta[6] = 'RolInventat';
        $r = $this->post('/api/v1/users/import', ['file' => $this->xlsx([$bona, $dolenta])]);
        $r->assertStatus(422)->assertJson(['ok' => false, 'importats' => 0]);
        // NI la bona s'ha importat (tot o res).
        $this->assertSame(0, User::whereIn('dni', ['11111111A', '22222222B'])->count());
    }

    public function test_autonom_sense_disponibilitat_error(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());
        $f = $this->filaLaboral(); $f[11] = 'autonom'; $f[12] = '';   // autònom sense disponibilitat
        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$f])])->assertStatus(422);
    }

    public function test_dry_run_no_escriu(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());
        $r = $this->post('/api/v1/users/import?dry=1', ['file' => $this->xlsx([$this->filaLaboral()])]);
        $r->assertOk()->assertJson(['dry_run' => true, 'validades' => 1]);
        $this->assertSame(0, User::where('dni', '11111111A')->count());
    }

    public function test_dni_repetit_dins_del_fitxer(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());
        $r = $this->post('/api/v1/users/import', ['file' => $this->xlsx([$this->filaLaboral(), $this->filaLaboral()])]);
        $r->assertStatus(422);
    }

    /* ── LOT I DEPARTAMENT A L'ALTA MASSIVA ─────────────────────────────────────────
       Direcció (31-07-2026): en posar les apps en producció es tornarà a donar d'alta
       TOTA la plantilla. O sigui que aquesta és la via per on entrarà tothom, i el que
       no entri per aquí quedarà per assignar a mà persona a persona. */

    public function test_l_alta_massiva_assigna_lot_i_departament(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());

        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$this->filaLaboral()])])
            ->assertOk();

        $u = User::where('dni', '11111111A')->firstOrFail();
        $this->assertSame(['B1'], $u->lots->pluck('code')->all());
        $this->assertSame(['RHB_DOMI'], $u->departments->pluck('code')->all());
    }

    public function test_una_cel_la_pot_portar_mes_d_un_codi(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());

        $fila = $this->filaLaboral();
        $fila[16] = 'B1; B9';
        $fila[17] = 'RHB_DOMI; RHB_AMBU';

        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$fila])])->assertOk();

        $u = User::where('dni', '11111111A')->firstOrFail();
        $this->assertSame(['B1', 'B9'], $u->lots->pluck('code')->sort()->values()->all());
        $this->assertCount(2, $u->departments);
    }

    /**
     * Un codi mal escrit ha de PARAR l'import. Si s'ignorés, la persona entraria sense
     * assignació i quedaria fora de tots els filtres del quadre de disponibilitat: invisible
     * per a qui reparteix la feina, i sense que ningú se n'assabentés.
     */
    public function test_un_codi_desconegut_atura_l_import(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());

        $fila = $this->filaLaboral();
        $fila[16] = 'B7';                      // no existeix

        $r = $this->post('/api/v1/users/import', ['file' => $this->xlsx([$fila])]);

        $this->assertNotSame(200, $r->status(), 'Un lot inexistent no ha aturat l\'import.');
        $this->assertNull(User::where('dni', '11111111A')->first(), 'No s\'ha de crear res.');
    }

    /**
     * Una cel·la BUIDA vol dir «no ho toquis», no «treu-li\'ls». En una reimportació parcial
     * —que és el que passarà mentre es depura el data entry— entendre-ho al revés
     * desassignaria mitja plantilla sense que ningú ho hagués demanat.
     */
    public function test_una_cel_la_buida_no_desassigna(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());

        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$this->filaLaboral()])])->assertOk();

        $fila = $this->filaLaboral();
        $fila[16] = '';
        $fila[17] = '';
        $this->post('/api/v1/users/import', ['file' => $this->xlsx([$fila])])->assertOk();

        $u = User::where('dni', '11111111A')->firstOrFail();
        $this->assertSame(['B1'], $u->lots->pluck('code')->all(), 'La cel·la buida ha desassignat.');
    }
}
