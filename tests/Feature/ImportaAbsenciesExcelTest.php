<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Bolcat de l'Excel de RRHH a les absències: mateixes fulles i columnes que «RRHH 2026.xlsx»
 * (dates com a número de sèrie, hores com a fracció de dia), amb dades inventades.
 */
class ImportaAbsenciesExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $anna;
    private User $pere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hr = User::create(['name' => 'Gestió RRHH', 'email' => 'hr@test.local', 'password' => bcrypt('x'), 'role' => 'hr', 'active' => true]);
        $this->anna = User::create(['name' => 'Anna Garcia Puig', 'email' => 'anna@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
        $this->pere = User::create(['name' => 'Pere Martí Soler', 'email' => 'pere@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
        // Els tipus «de sempre» (la migració ja crea els que l'Excel té de nous)
        AbsenceType::create(['name' => 'Vacances', 'category' => 'vacation', 'codi_rrhh' => 'vacances', 'max_days' => 30]);
        AbsenceType::create(['name' => 'Assumptes propis', 'category' => 'personal', 'codi_rrhh' => 'assumptes propis', 'quantitat' => 3, 'unitat' => 'dies', 'periode' => 'any']);
        AbsenceType::create(['name' => 'Baixa IT (malaltia comuna)', 'category' => 'baja', 'codi_rrhh' => 'incapacitat']);
    }

    private static function serial(string $data): int
    {
        return (int) (strtotime($data . ' UTC') / 86400) + 25569;
    }

    private function excel(): UploadedFile
    {
        $s = new Spreadsheet();
        $fulla = function (string $nom, array $files) use ($s) {
            $ws = $s->getSheetCount() === 1 && $s->getSheet(0)->getTitle() === 'Worksheet' ? $s->getSheet(0) : $s->createSheet();
            $ws->setTitle($nom);
            foreach ($files as $i => $fila) {
                foreach ($fila as $col => $v) $ws->setCellValue($col . ($i + 1), $v);
            }
        };
        $d = fn ($x) => self::serial($x);
        // Nom a l'Excel amb els cognoms davant (com el de RRHH) i un amb una lletra canviada.
        $fulla('permisos', [
            ['A' => 'NOM TREBALLADOR', 'B' => 'motiu', 'C' => 'data inici', 'D' => 'data final', 'I' => 'estat'],
            ['A' => 'Garcia Puig Anna', 'B' => 'assumptes propis ', 'C' => $d('2026-03-02'), 'D' => $d('2026-03-02'), 'I' => 'concedit'],
            ['A' => 'Garcia Puig Anna', 'B' => 'metge propi', 'C' => $d('2026-03-05'), 'D' => $d('2026-03-05'), 'F' => 9 / 24, 'G' => 10.5 / 24, 'H' => 1.5 / 24, 'I' => 'concedit'],
            ['A' => 'Marti Solér Pere', 'B' => 'metge propi', 'C' => $d('2026-03-06'), 'D' => $d('2026-03-06'), 'F' => '11H', 'G' => '13H', 'I' => 'petició'],
            ['A' => 'Persona Que No Hi Es', 'B' => 'assumptes propis', 'C' => $d('2026-04-01'), 'D' => $d('2026-04-01'), 'I' => 'concedit'],
            ['A' => 'Garcia Puig Anna', 'B' => 'assumptes propis', 'I' => 'concedit'],                       // sense data
            ['A' => 'Garcia Puig Anna', 'B' => 'motiu inventat', 'C' => $d('2026-04-02'), 'D' => $d('2026-04-02'), 'I' => 'concedit'],
        ]);
        $fulla('incapacitats', [
            ['A' => 'NOM TREBALLADOR', 'B' => 'motiu incapacitat', 'C' => 'data inici', 'D' => 'data final', 'F' => 'estat'],
            ['A' => 'Garcia Puig Anna', 'B' => 'Grip', 'C' => $d('2026-02-09'), 'D' => $d('2026-02-11'), 'F' => 'concedit'],
        ]);
        $vac = [
            ['B' => 'NOM TREBALLADOR', 'C' => 'motiu', 'D' => 'data inici', 'E' => 'data final', 'G' => 'estat'],
            ['B' => 'Garcia Puig Anna', 'C' => 'vacances', 'D' => $d('2026-08-03'), 'E' => $d('2026-08-14'), 'G' => 'concedit'],
            ['B' => 'Garcia Puig Anna', 'C' => 'dia conveni SS', 'D' => $d('2026-04-07'), 'E' => $d('2026-04-07'), 'G' => 'concedit'],
        ];
        $fulla('Petició vacances', $vac);
        // La fulla de domiciliària repeteix les mateixes files (com a l'Excel real)
        $fulla('Petició vacances DOMICILIARIA', array_map(fn ($f) => ['A' => $f['B'], 'B' => $f['C'], 'C' => $f['D'], 'D' => $f['E'], 'F' => $f['G']], $vac));
        $fulla('hores sindicals', [
            ['B' => 'NOM TREBALLADOR', 'C' => 'motiu', 'D' => 'data inici', 'E' => 'data final', 'F' => 'total dies', 'G' => 'estat'],
            ['B' => 'Martí Soler Pere', 'C' => 'accions sindicals', 'D' => $d('2026-05-04'), 'E' => $d('2026-05-04'), 'F' => 2 / 24, 'G' => 'concedit'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'rrhh') . '.xlsx';
        (new Xlsx($s))->save($path);

        return new UploadedFile($path, 'RRHH 2026.xlsx', null, null, true);
    }

    public function test_la_previsualitzacio_no_escriu_res_i_explica_que_fara(): void
    {
        Sanctum::actingAs($this->hr);
        $r = $this->post('/api/v1/absences/importa-excel/previsualitza', ['file' => $this->excel()], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(0, Absence::count());
        $r->assertJsonPath('resum.duplicats_excel', 2)       // la fulla DOMICILIARIA
          ->assertJsonPath('resum.sense_data', 1)
          ->assertJsonPath('resum.sense_tipus', 1)
          ->assertJsonPath('resum.sense_persona', 1)
          ->assertJsonPath('resum.a_crear', 7);
        $persones = collect($r->json('persones'))->keyBy('nom');
        $this->assertSame('exacte', $persones['Garcia Puig Anna']['casament']);
        $this->assertSame($this->pere->id, $persones['Marti Solér Pere']['user_id']);   // accents i ordre
        $this->assertSame('cap', $persones['Persona Que No Hi Es']['casament']);

        $metge = collect($r->json('registres'))->firstWhere('ini', '2026-03-05');
        $this->assertSame(['09:00', '10:30', 90], [$metge['hora_inici'], $metge['hora_fi'], $metge['minuts']]);
        $sind = collect($r->json('registres'))->firstWhere('tipus', 'Accions sindicals');
        $this->assertSame(120, $sind['minuts']);
        $this->assertSame('11:00', collect($r->json('registres'))->firstWhere('ini', '2026-03-06')['hora_inici']);
    }

    public function test_aplicar_crea_les_absencies_i_tornar_a_importar_no_duplica(): void
    {
        Sanctum::actingAs($this->hr);
        $prev = $this->post('/api/v1/absences/importa-excel/previsualitza', ['file' => $this->excel()], ['Accept' => 'application/json'])->json();

        // RRHH assigna a mà la persona que no s'ha trobat
        $this->postJson('/api/v1/absences/importa-excel/aplica', [
            'registres' => $prev['registres'],
            'assignacions' => ['Persona Que No Hi Es' => $this->pere->id],
            'amb_motiu_baixes' => false,
        ])->assertOk()->assertJson(['creades' => 8, 'saltades' => 0]);

        $this->assertSame(8, Absence::count());
        $this->assertTrue(Absence::where('user_id', $this->anna->id)->whereDate('start_date', '2026-08-03')->first()->approved);
        $this->assertNull(Absence::where('user_id', $this->pere->id)->whereDate('start_date', '2026-03-06')->first()->approved); // petició = pendent
        $baixa = Absence::where('user_id', $this->anna->id)->whereDate('start_date', '2026-02-09')->first();
        $this->assertSame('Baixa IT (malaltia comuna)', $baixa->absenceType->name);
        $this->assertNull($baixa->reason, 'sense el motiu de la baixa si no es demana');
        $this->assertSame(90, Absence::where('user_id', $this->anna->id)->whereDate('start_date', '2026-03-05')->value('minuts'));

        // Segona vegada: res de nou
        $prev2 = $this->post('/api/v1/absences/importa-excel/previsualitza', ['file' => $this->excel()], ['Accept' => 'application/json'])->json();
        $this->assertSame(0, $prev2['resum']['a_crear']);
        $this->postJson('/api/v1/absences/importa-excel/aplica', ['registres' => $prev2['registres'], 'assignacions' => ['Persona Que No Hi Es' => $this->pere->id]])
            ->assertOk()->assertJson(['creades' => 0]);
        $this->assertSame(8, Absence::count());
    }

    /** Mana l'Excel: si una absència que ja hi era diu una altra cosa a l'Excel, s'actualitza. */
    public function test_mana_l_excel_sobre_el_que_ja_hi_havia(): void
    {
        $vac = AbsenceType::where('codi_rrhh', 'vacances')->first();
        $entrada = Absence::create(['user_id' => $this->anna->id, 'absence_type_id' => $vac->id,
            'start_date' => '2026-08-03', 'end_date' => '2026-08-14', 'approved' => null]);
        Sanctum::actingAs($this->hr);
        $prev = $this->post('/api/v1/absences/importa-excel/previsualitza', ['file' => $this->excel()], ['Accept' => 'application/json'])->json();
        $this->assertSame('actualitzar', collect($prev['registres'])->firstWhere('ini', '2026-08-03')['accio']);

        $this->postJson('/api/v1/absences/importa-excel/aplica', ['registres' => $prev['registres']])->assertOk();
        $this->assertTrue($entrada->fresh()->approved);
        $this->assertSame(1, Absence::where('user_id', $this->anna->id)->whereDate('start_date', '2026-08-03')->count());
    }

    public function test_nomes_gestio_pot_importar(): void
    {
        Sanctum::actingAs($this->anna);
        $this->post('/api/v1/absences/importa-excel/previsualitza', ['file' => $this->excel()], ['Accept' => 'application/json'])->assertStatus(403);
        $this->postJson('/api/v1/absences/importa-excel/aplica', ['registres' => []])->assertStatus(403);
    }

    public function test_un_fitxer_que_no_es_excel_es_rebutja_amb_el_motiu(): void
    {
        Sanctum::actingAs($this->hr);
        $this->post('/api/v1/absences/importa-excel/previsualitza',
            ['file' => UploadedFile::fake()->createWithContent('dades.csv', "a;b\n")], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', 'Ha de ser un Excel .xlsx (el format antic .xls no es pot llegir: deseu-lo com a .xlsx).');
    }

    /** El catàleg queda com la fulla CONCEPTOS. */
    public function test_el_cataleg_te_els_permisos_de_l_excel(): void
    {
        $t = AbsenceType::where('codi_rrhh', 'assumptes propis hores')->first();
        $this->assertSame([24.0, 'hores', 'any', true], [$t->quantitat, $t->unitat, $t->periode, $t->recoverable]);
        $this->assertSame(15.0, AbsenceType::where('codi_rrhh', 'accions sindicals')->value('quantitat'));
        $this->assertNotNull(AbsenceType::where('codi_rrhh', 'dia conveni ss')->first());
    }

    /** Un permís d'unes hores només treu aquests minuts del saldo; un que es recupera, cap. */
    public function test_el_saldo_de_jornada_descompta_nomes_les_hores_del_permis(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-03-31 20:00', 'Europe/Madrid'));
        Sanctum::actingAs($this->hr);
        \App\Models\JornadaContracte::create(['user_id' => $this->anna->id, 'pct' => 100, 'valid_from' => '2026-01-01']);
        $antes = $this->getJson("/api/v1/users/{$this->anna->id}/jornada?any=2026")->json('trams.0.mesos.2.esperat_min');

        $metge = AbsenceType::where('codi_rrhh', 'metge propi')->first();
        $hores = AbsenceType::where('codi_rrhh', 'assumptes propis hores')->first();   // es recupera
        $this->postJson('/api/v1/absences', ['user_id' => $this->anna->id, 'absence_type_id' => $metge->id,
            'start_date' => '2026-03-05', 'end_date' => '2026-03-05', 'hora_inici' => '09:00', 'hora_fi' => '10:30'])->assertCreated();
        Absence::query()->update(['approved' => true]);
        Absence::create(['user_id' => $this->anna->id, 'absence_type_id' => $hores->id, 'start_date' => '2026-03-06',
            'end_date' => '2026-03-06', 'minuts' => 120, 'approved' => true]);

        $despres = $this->getJson("/api/v1/users/{$this->anna->id}/jornada?any=2026")->json('trams.0.mesos.2.esperat_min');
        $this->assertSame($antes - 90, $despres);
        $this->assertSame(90, Absence::whereDate('start_date', '2026-03-05')->value('minuts'));
    }

    public function test_les_hores_del_permis_es_validen(): void
    {
        Sanctum::actingAs($this->anna);
        $metge = AbsenceType::where('codi_rrhh', 'metge propi')->first();
        $this->postJson('/api/v1/absences', ['user_id' => $this->anna->id, 'absence_type_id' => $metge->id,
            'start_date' => '2026-03-05', 'end_date' => '2026-03-05', 'hora_inici' => '11:00', 'hora_fi' => '10:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['hora_fi']);
    }
}
