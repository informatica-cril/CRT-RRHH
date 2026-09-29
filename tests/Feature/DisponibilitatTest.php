<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DisponibilitatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Quadre de disponibilitat setmanal (petició de Direcció, 31-07-2026).
 *
 * PER QUÈ AQUESTA SUITE I NO UNA COMPROVACIÓ A MÀ
 *   La taula `absences` de desenvolupament està BUIDA (0 files, mesurat el 31-07-2026), o
 *   sigui que provant contra la base real no s'exercita mai el cas que dona sentit a tota la
 *   pantalla: que una absència aprovada tregui algú del quadre. Sense aquestes proves, la
 *   funcionalitat estaria escrita i no comprovada.
 *
 * El que aquí es fixa és la REGLA DE NEGOCI, no la implementació:
 *   · aprovada  → absent
 *   · pendent   → segueix treballant (demanar vacances no és tenir-les)
 *   · denegada  → segueix treballant
 *   · festiu    → mana sobre tot
 *   · sense horari → NO és «disponible»: és «no ho sabem»
 */
class DisponibilitatTest extends TestCase
{
    use RefreshDatabase;

    private const DILLUNS = '2026-08-03';

    private function horariDlDv(string $inici = '08:00', string $fi = '14:00'): WorkSchedule
    {
        $dies = [];
        foreach ([1 => 'Dilluns', 2 => 'Dimarts', 3 => 'Dimecres', 4 => 'Dijous', 5 => 'Divendres'] as $n => $nom) {
            $dies[] = ['day' => $n, 'name' => $nom, 'start' => $inici, 'end' => $fi];
        }
        return WorkSchedule::create([
            'name' => "Dl-Dv $inici-$fi", 'total_hours_weekly' => 30, 'days' => $dies,
        ]);
    }

    private function treballador(?WorkSchedule $horari = null): User
    {
        return User::factory()->create([
            'role' => 'worker', 'active' => true,
            'work_schedule_id' => $horari?->id,
        ]);
    }

    private function quadre(array $filtres = []): array
    {
        return app(DisponibilitatService::class)->setmana(self::DILLUNS, $filtres);
    }

    /** Estat de la casella del dilluns per a un treballador. */
    private function dilluns(array $quadre, int $userId): array
    {
        $fila = collect($quadre['files'])->firstWhere('id', $userId);
        $this->assertNotNull($fila, "El treballador $userId no surt al quadre.");
        return $fila['dies'][0];
    }

    public function test_qui_te_horari_surt_treballant_amb_el_seu_tram(): void
    {
        $u = $this->treballador($this->horariDlDv('09:00', '15:00'));

        $c = $this->dilluns($this->quadre(), $u->id);

        $this->assertSame(DisponibilitatService::TREBALLA, $c['estat']);
        $this->assertSame('09:00–15:00', $c['detall']);
    }

    public function test_una_absencia_APROVADA_el_treu_del_quadre(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Vacances']);

        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => self::DILLUNS, 'end_date' => '2026-08-05',
            'approved' => true,
        ]);

        $c = $this->dilluns($this->quadre(), $u->id);
        $this->assertSame(DisponibilitatService::ABSENT, $c['estat']);
        $this->assertSame('Vacances', $c['detall']);
    }

    /** El cas que justifica tota la pantalla: demanar-les no és tenir-les. */
    public function test_una_absencia_PENDENT_no_el_treu(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Assumptes personals']);

        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => self::DILLUNS, 'end_date' => '2026-08-05',
            'approved' => null,
        ]);

        $this->assertSame(DisponibilitatService::TREBALLA, $this->dilluns($this->quadre(), $u->id)['estat']);
    }

    public function test_una_absencia_DENEGADA_no_el_treu(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Formació']);

        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => self::DILLUNS, 'end_date' => '2026-08-05',
            'approved' => false,
        ]);

        $this->assertSame(DisponibilitatService::TREBALLA, $this->dilluns($this->quadre(), $u->id)['estat']);
    }

    /**
     * Una baixa llarga no comença ni acaba dins de la setmana consultada. Si la consulta
     * busqués absències CONTINGUDES a la setmana en comptes de SOLAPADES, aquesta persona
     * sortiria treballant estant de baixa: l'error més car de tota la pantalla.
     */
    public function test_una_baixa_que_engloba_la_setmana_sencera_el_treu(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Baixa mèdica']);

        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => '2026-05-01', 'end_date' => '2026-12-31',
            'approved' => true,
        ]);

        $this->assertSame(DisponibilitatService::ABSENT, $this->dilluns($this->quadre(), $u->id)['estat']);
    }

    /**
     * ⚠️ TROBALLA DEL MODEL DE DADES (31-07-2026): `absences.end_date` és NOT NULL.
     *
     * O sigui que una BAIXA MÈDICA OBERTA —que és com comencen gairebé totes: es sap el dia
     * que la persona plega, no el dia que torna— NO es pot registrar tal com és. Qui la doni
     * d'alta ha de posar-hi una data de fi inventada, i llavors el quadre dirà que aquella
     * persona torna a treballar el dia que algú va escriure a l'atzar.
     *
     * Això NO es toca aquí: canviar l'esquema pertany a la finestra de migració (bloc C del
     * pla d'objectius), no a una funcionalitat nova. El servei ja tracta `end_date = null`
     * com a baixa oberta, i el dia que la columna admeti nuls funcionarà sol.
     *
     * Aquesta prova FIXA la limitació perquè consti i no se'ns oblidi: si algun dia la
     * columna passa a admetre nuls, aquesta prova fallarà i caldrà substituir-la per la
     * versió bona (que és la de dalt, amb `end_date => null`).
     */
    public function test_avui_una_baixa_oberta_NO_es_pot_registrar(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Baixa mèdica']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => '2026-07-01', 'end_date' => null,
            'approved' => true,
        ]);
    }

    /** Mentre la columna no admeti nuls, la baixa oberta s'entra amb una fi llunyana. */
    public function test_una_baixa_amb_fi_llunyana_el_treu(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Baixa mèdica']);

        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => '2026-07-01', 'end_date' => '2027-07-01',
            'approved' => true,
        ]);

        $this->assertSame(DisponibilitatService::ABSENT, $this->dilluns($this->quadre(), $u->id)['estat']);
    }

    public function test_el_festiu_mana_sobre_l_horari(): void
    {
        $u = $this->treballador($this->horariDlDv());
        Holiday::create(['date' => self::DILLUNS, 'name' => 'Festa major', 'type' => 'local', 'year' => 2026]);

        $c = $this->dilluns($this->quadre(), $u->id);
        $this->assertSame(DisponibilitatService::FESTIU, $c['estat']);
        $this->assertSame('Festa major', $c['detall']);
    }

    public function test_sense_horari_no_compta_com_a_disponible(): void
    {
        $u = $this->treballador(null);

        $c = $this->dilluns($this->quadre(), $u->id);
        $this->assertSame(DisponibilitatService::SENSE_HORARI, $c['estat']);
        $this->assertNotSame(DisponibilitatService::TREBALLA, $c['estat']);
    }

    /**
     * Jornada partida: dos trams el mateix dia. Si el codi indexés els dies del JSON per
     * número en comptes d'agrupar-los, es menjaria el segon tram i comptaria la meitat
     * d'hores. 09:00–13:00 + 15:00–19:00 = 8 h el dilluns.
     */
    public function test_la_jornada_partida_compta_els_dos_trams(): void
    {
        $horari = WorkSchedule::create([
            'name' => 'Partida', 'total_hours_weekly' => 40,
            'days' => [
                ['day' => 1, 'name' => 'Dilluns', 'start' => '09:00', 'end' => '13:00'],
                ['day' => 1, 'name' => 'Dilluns', 'start' => '15:00', 'end' => '19:00'],
            ],
        ]);
        $u = $this->treballador($horari);

        $q = $this->quadre();
        $fila = collect($q['files'])->firstWhere('id', $u->id);

        $this->assertSame('09:00–13:00 i 15:00–19:00', $fila['dies'][0]['detall']);
        $this->assertEqualsWithDelta(8.0, $fila['hores_setmana'], 0.01);
    }

    public function test_el_resum_per_dia_quadra_amb_les_files(): void
    {
        $this->treballador($this->horariDlDv());
        $this->treballador($this->horariDlDv());
        $absent = $this->treballador($this->horariDlDv());
        $tipus  = AbsenceType::create(['name' => 'Vacances']);
        Absence::create([
            'user_id' => $absent->id, 'absence_type_id' => $tipus->id,
            'start_date' => self::DILLUNS, 'end_date' => self::DILLUNS, 'approved' => true,
        ]);

        $q = $this->quadre();
        $dl = $q['resum']['per_dia'][0];

        $this->assertSame(2, $dl['treballa']);
        $this->assertSame(1, $dl['absent']);
        /* El recompte del resum ha de sumar exactament les files, ni una més ni una menys. */
        $suma = $dl['treballa'] + $dl['absent'] + $dl['festiu'] + $dl['no_treballa'] + $dl['sense_horari'];
        $this->assertSame(count($q['files']), $suma);
    }

    public function test_nomes_admin_i_hr_hi_arriben(): void
    {
        foreach (['admin', 'hr'] as $rol) {
            Sanctum::actingAs(User::factory()->create(['role' => $rol]));
            $this->getJson('/api/v1/disponibilitat')->assertOk();
        }
        foreach (['worker', 'coordinator'] as $rol) {
            Sanctum::actingAs(User::factory()->create(['role' => $rol]));
            $this->getJson('/api/v1/disponibilitat')->assertForbidden();
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────────────
       CANAL DE SERVEI CAP A DOMI

       domi programa visites de laborals I autònoms, i avui decideix les absències amb una
       taula seva. Les absències aprovades dels laborals viuen aquí. Sense aquest canal,
       domi pot programar una visita a algú que RRHH té de vacances aprovades.
       ───────────────────────────────────────────────────────────────────────────────── */

    public function test_domi_rep_els_dies_absents_per_identificador_de_rrhh(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Vacances']);
        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => '2026-08-04', 'end_date' => '2026-08-06', 'approved' => true,
        ]);

        $r = app(DisponibilitatService::class)->perDomi('2026-08-03', '2026-08-09', [$u->id]);
        $p = collect($r['persones'])->firstWhere('rrhh_id', $u->id);

        $this->assertSame(['2026-08-04', '2026-08-05', '2026-08-06'], $p['absent']);
        $this->assertTrue($p['te_horari']);
        // Amb els trams, domi sap no només SI pot programar, sinó entre quines hores.
        $this->assertSame('08:00', $p['horari_setmanal'][1][0]['start']);
    }

    /**
     * El blindatge que no es pot perdre mai: domi ha de saber QUE la persona no hi és, no
     * PER QUÈ. El motiu («Baixa mèdica») és dada de salut i no ha de sortir d'aquí.
     */
    public function test_a_domi_no_hi_viatja_MAI_el_motiu_de_l_absencia(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Baixa mèdica']);
        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => '2026-08-03', 'end_date' => '2026-08-03', 'approved' => true,
        ]);

        $json = json_encode(app(DisponibilitatService::class)->perDomi('2026-08-03', '2026-08-09', [$u->id]));

        $this->assertStringNotContainsString('Baixa mèdica', $json);
        $this->assertStringNotContainsString('absence_type', $json);
    }

    public function test_una_absencia_pendent_no_arriba_a_domi(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Assumptes personals']);
        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => '2026-08-03', 'end_date' => '2026-08-03', 'approved' => null,
        ]);

        $r = app(DisponibilitatService::class)->perDomi('2026-08-03', '2026-08-09', [$u->id]);
        $this->assertSame([], collect($r['persones'])->firstWhere('rrhh_id', $u->id)['absent']);
    }

    /** Una baixa que ve de molt abans s'ha de retallar al rang demanat, no desbordar-lo. */
    public function test_una_absencia_mes_llarga_que_el_rang_es_retalla(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $tipus = AbsenceType::create(['name' => 'Baixa mèdica']);
        Absence::create([
            'user_id' => $u->id, 'absence_type_id' => $tipus->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'approved' => true,
        ]);

        $r = app(DisponibilitatService::class)->perDomi('2026-08-03', '2026-08-05', [$u->id]);
        $absent = collect($r['persones'])->firstWhere('rrhh_id', $u->id)['absent'];

        $this->assertSame(['2026-08-03', '2026-08-04', '2026-08-05'], $absent);
    }

    public function test_el_canal_de_domi_es_nomes_per_al_compte_de_servei(): void
    {
        $params = '?desde=2026-08-03&fins=2026-08-09';

        Sanctum::actingAs(User::factory()->create(['role' => 'service']), \App\Support\DomiScopes::permisos());
        $this->getJson('/api/v1/domi/disponibilitat' . $params)->assertOk();

        foreach (['worker', 'coordinator', 'hr', 'admin'] as $rol) {
            Sanctum::actingAs(User::factory()->create(['role' => $rol]));
            $this->getJson('/api/v1/domi/disponibilitat' . $params)->assertForbidden();
        }
    }

    public function test_el_canal_de_domi_posa_sostre_al_rang(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'service']), \App\Support\DomiScopes::permisos());
        $this->getJson('/api/v1/domi/disponibilitat?desde=2026-01-01&fins=2027-01-01')
            ->assertStatus(422);
    }

    /* ─────────────────────────────────────────────────────────────────────────────────
       LOT I DEPARTAMENT (definits per Direcció el 31-07-2026)

       Lot   = territori ample del contracte: B1 Barcelona, B9 Vallès. Els codis són els
               que ja fa servir la facturació de domi (crt_lot), no me'ls invento.
       Depto = servei × modalitat: RHB/LOGO/TO × ambulatòria/domiciliària.

       Les dues relacions són N:M a posta: hi ha qui cobreix els dos lots i qui fa
       ambulatòria i domiciliària alhora.
       ───────────────────────────────────────────────────────────────────────────────── */

    public function test_els_lots_i_departaments_del_contracte_hi_son(): void
    {
        $this->assertSame(['B1', 'B9'], \App\Models\Lot::orderBy('sort')->pluck('code')->all());
        $this->assertSame(
            ['RHB_DOMI', 'RHB_AMBU', 'LOGO_DOMI', 'LOGO_AMBU', 'TO_DOMI'],
            \App\Models\Department::orderBy('sort')->pluck('code')->all()
        );
    }

    public function test_el_filtre_de_lot_deixa_fora_qui_no_hi_es(): void
    {
        $b1 = \App\Models\Lot::where('code', 'B1')->first();
        $b9 = \App\Models\Lot::where('code', 'B9')->first();

        $bcn  = $this->treballador($this->horariDlDv());
        $vall = $this->treballador($this->horariDlDv());
        $tots = $this->treballador($this->horariDlDv());

        $bcn->lots()->attach($b1->id);
        $vall->lots()->attach($b9->id);
        $tots->lots()->attach([$b1->id, $b9->id]);   // qui cobreix els dos territoris

        $ids = collect($this->quadre(['lot_id' => $b1->id])['files'])->pluck('id');

        $this->assertTrue($ids->contains($bcn->id));
        $this->assertTrue($ids->contains($tots->id), 'Qui cobreix els dos lots ha de sortir a tots dos.');
        $this->assertFalse($ids->contains($vall->id));
    }

    public function test_el_filtre_de_departament_separa_ambulatoria_de_domiciliaria(): void
    {
        $domi = \App\Models\Department::where('code', 'RHB_DOMI')->first();
        $ambu = \App\Models\Department::where('code', 'RHB_AMBU')->first();

        $aDomi = $this->treballador($this->horariDlDv());
        $aAmbu = $this->treballador($this->horariDlDv());
        $aDomi->departments()->attach($domi->id);
        $aAmbu->departments()->attach($ambu->id);

        $ids = collect($this->quadre(['departament_id' => $domi->id])['files'])->pluck('id');

        $this->assertTrue($ids->contains($aDomi->id));
        $this->assertFalse($ids->contains($aAmbu->id));
    }

    /** Els dos filtres alhora es combinen (AND), no s'ignoren l'un a l'altre. */
    public function test_lot_i_departament_es_combinen(): void
    {
        $b1   = \App\Models\Lot::where('code', 'B1')->first();
        $domi = \App\Models\Department::where('code', 'RHB_DOMI')->first();

        $bo = $this->treballador($this->horariDlDv());
        $bo->lots()->attach($b1->id);
        $bo->departments()->attach($domi->id);

        $nomesLot = $this->treballador($this->horariDlDv());
        $nomesLot->lots()->attach($b1->id);

        $ids = collect($this->quadre(['lot_id' => $b1->id, 'departament_id' => $domi->id])['files'])->pluck('id');

        $this->assertTrue($ids->contains($bo->id));
        $this->assertFalse($ids->contains($nomesLot->id));
    }

    /* ── Assignació des de la FITXA DEL TREBALLADOR ──────────────────────────────────── */

    public function test_es_pot_assignar_lot_i_departament_des_de_la_fitxa(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $u = $this->treballador($this->horariDlDv());

        /* Per CODI i no per id: així un Excel d'alta massiva pot dir «B1» sense saber quin
           número li ha tocat a la taula d'aquesta instància. */
        $this->putJson("/api/v1/users/{$u->id}", [
            'lots' => ['B1', 'B9'], 'departments' => ['RHB_DOMI'],
        ])->assertOk();

        $this->assertSame(['B1', 'B9'], $u->fresh()->lots->pluck('code')->sort()->values()->all());
        $this->assertSame(['RHB_DOMI'], $u->fresh()->departments->pluck('code')->all());
    }

    /** Desassignar ha de funcionar: enviar la llista buida els treu tots. */
    public function test_enviar_la_llista_buida_treu_les_assignacions(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $u = $this->treballador($this->horariDlDv());
        $u->lots()->attach(\App\Models\Lot::where('code', 'B1')->first()->id);

        $this->putJson("/api/v1/users/{$u->id}", ['lots' => []])->assertOk();

        $this->assertCount(0, $u->fresh()->lots);
    }

    /**
     * Un coordinador reparteix feina però no decideix a quin lot pertany ningú: això és
     * organització de plantilla, i és d'Administració i RRHH.
     */
    public function test_un_coordinador_no_pot_canviar_el_lot_de_ningu(): void
    {
        $u = $this->treballador($this->horariDlDv());
        $b1 = \App\Models\Lot::where('code', 'B1')->first();
        $u->lots()->attach($b1->id);

        Sanctum::actingAs(User::factory()->create(['role' => 'coordinator']));
        $this->putJson("/api/v1/users/{$u->id}", ['lots' => []]);

        // Tant si el talla el 403 com si l'ignora, el que NO pot passar és que ho canviï.
        $this->assertCount(1, $u->fresh()->lots, 'Un coordinador ha pogut canviar el lot.');
    }

    public function test_la_fitxa_torna_els_lots_i_departaments_per_poder_pintar_los(): void
    {
        Sanctum::actingAs($admin = User::factory()->create(['role' => 'admin']));
        $u = $this->treballador($this->horariDlDv());
        $u->departments()->attach(\App\Models\Department::where('code', 'TO_DOMI')->first()->id);

        $this->getJson("/api/v1/users/{$u->id}")
            ->assertOk()
            ->assertJsonPath('departments.0.code', 'TO_DOMI');
    }

    public function test_el_filtre_de_zona_deixa_fora_qui_no_hi_es(): void
    {
        $zona = \App\Models\Zone::create([
            'name' => 'Zona de prova', 'type' => 'MUNICIPALITY', 'province' => 'Barcelona',
            'postal_codes' => [], 'municipalities' => ['RUBI'], 'active' => true,
        ]);
        $dins = $this->treballador($this->horariDlDv());
        $fora = $this->treballador($this->horariDlDv());
        $dins->zones()->attach($zona->id);

        $ids = collect($this->quadre(['zona_id' => $zona->id])['files'])->pluck('id');

        $this->assertTrue($ids->contains($dins->id));
        $this->assertFalse($ids->contains($fora->id));
    }
}
