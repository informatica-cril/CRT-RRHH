<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * % de jornada del contracte i el seu mòdul diari: els números han de coincidir amb el full
 * de revisió de jornades de RRHH (2026, 249 dies laborables − 22 de vacances = 227 mòduls).
 */
class JornadaContracteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): User
    {
        return User::create(['name' => 'Gestió', 'email' => $role . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true]);
    }

    /** Els 12 festius entre setmana de Barcelona 2026 (el 15-8, l'1-11 i el 26-12 cauen en cap de setmana). */
    private function festius2026(): void
    {
        foreach (['01-01', '01-06', '04-03', '04-06', '05-01', '05-25', '06-24', '08-15',
                  '09-11', '09-24', '10-12', '11-01', '12-08', '12-25', '12-26'] as $d) {
            Holiday::create(['date' => "2026-{$d}", 'name' => 'Festiu', 'type' => 'regional', 'year' => 2026]);
        }
    }

    public function test_el_modul_coincideix_amb_el_full_de_rrhh(): void
    {
        Sanctum::actingAs($this->admin());
        $this->festius2026();

        $this->getJson('/api/v1/jornada/modul?pct=75&any=2026')->assertOk()->assertJson([
            'dies_laborables' => 249, 'festius_entre_setmana' => 12, 'moduls' => 227,
            'maxim_anual_min' => 77670,       // 1294 h 30 min
            'modul_diari_min' => 342,         // 5 h 42 min
            'setmana_referencia_min' => 1710, // 28 h 30 min
            'resta_min' => 36,
            'avis' => null,
        ]);
        $this->getJson('/api/v1/jornada/modul?pct=100&any=2026')->assertOk()
            ->assertJson(['maxim_anual_min' => 103560, 'modul_diari_min' => 456, 'resta_min' => 48]);
    }

    /** Excel donava 59.546 per l'error de decimals; el càlcul en enters dona el valor exacte. */
    public function test_el_57_5_no_perd_cap_minut_per_arrodoniment(): void
    {
        Sanctum::actingAs($this->admin());
        $this->festius2026();

        $this->getJson('/api/v1/jornada/modul?pct=57.5&any=2026')->assertOk()
            ->assertJson(['maxim_anual_min' => 59547, 'modul_diari_min' => 262, 'resta_min' => 73]);
    }

    public function test_sense_festius_de_l_any_avisa(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/jornada/modul?pct=75&any=2026')->assertOk()
            ->assertJson(['festius_entre_setmana' => 0, 'dies_laborables' => 261])
            ->assertJsonPath('avis', 'No hi ha festius de 2026 registrats a Festius: els dies laborables no en descompten cap.');
    }

    public function test_el_percentatge_ha_de_ser_valid(): void
    {
        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/jornada/modul?pct=120')->assertStatus(422);
        $this->getJson('/api/v1/jornada/modul')->assertStatus(422);
    }

    public function test_nomes_gestio_pot_calcular_el_modul(): void
    {
        $w = $this->admin('worker');
        Sanctum::actingAs($w);
        $this->getJson('/api/v1/jornada/modul?pct=75')->assertStatus(403);

        Sanctum::actingAs($this->admin('hr'));
        $this->getJson('/api/v1/jornada/modul?pct=75')->assertOk();
    }

    private function treballadora(): User
    {
        return User::create(['name' => 'Treballadora', 'email' => 'w' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
    }

    /** Ampliació a mig any: l'historial guarda els dos % i el màxim de l'any es fa a trams. */
    public function test_un_canvi_a_mig_any_es_guarda_a_l_historial_i_l_any_es_calcula_a_trams(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-16 10:00', 'Europe/Madrid'));
        Sanctum::actingAs($this->admin('hr'));
        $this->festius2026();
        $w = $this->treballadora();

        $this->postJson("/api/v1/users/{$w->id}/jornada", ['pct' => 75, 'valid_from' => '2026-01-01'])->assertCreated();
        $this->postJson("/api/v1/users/{$w->id}/jornada", ['pct' => 100, 'valid_from' => '2026-07-01', 'motiu' => 'Ampliació'])->assertCreated();

        $r = $this->getJson("/api/v1/users/{$w->id}/jornada?any=2026")->assertOk();
        $r->assertJsonPath('vigent.pct', 100)->assertJsonPath('vigent.valid_from', '2026-07-01')
          ->assertJsonCount(2, 'historial')->assertJsonCount(2, 'trams')
          ->assertJsonPath('trams.0.pct', 75)->assertJsonPath('trams.1.pct', 100)
          ->assertJsonPath('trams.1.des', '2026-07-01')
          ->assertJsonPath('modul.modul_diari_min', 456);

        $n1 = $r->json('trams.0.dies_laborables');
        $n2 = $r->json('trams.1.dies_laborables');
        $this->assertSame(249, $n1 + $n2);
        $this->assertSame(intdiv(103560 * 7500 * $n1, 10000 * 249) + intdiv(103560 * 10000 * $n2, 10000 * 249),
            $r->json('maxim_anual_min'));
        $this->assertSame('Ampliació', $r->json('historial.1.motiu'));

        // Corregir el mateix dia substitueix el registre en lloc de duplicar-lo
        $this->postJson("/api/v1/users/{$w->id}/jornada", ['pct' => 90, 'valid_from' => '2026-07-01'])->assertCreated();
        $this->assertSame(2, \App\Models\JornadaContracte::where('user_id', $w->id)->count());

        // I es pot treure un canvi entrat per error
        $id = \App\Models\JornadaContracte::where('user_id', $w->id)->whereDate('valid_from', '2026-07-01')->value('id');
        $this->deleteJson("/api/v1/users/{$w->id}/jornada/{$id}")->assertOk();
        $this->getJson("/api/v1/users/{$w->id}/jornada?any=2026")->assertJsonPath('vigent.pct', 75)->assertJsonCount(1, 'trams');
    }

    /**
     * Saldo de gener al 50%: 20 dies laborables (sense l'1 i el 6) − 2 d'absència aprovada = 18
     * mòduls de 228 min. Fet: 4 h + 3 h, de les quals 30 min són complementàries autoritzades.
     */
    public function test_el_saldo_mensual_separa_les_complementaries_i_acumula(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-02-15 10:00', 'Europe/Madrid'));
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $this->festius2026();
        $w = $this->treballadora();
        \App\Models\JornadaContracte::create(['user_id' => $w->id, 'pct' => 50, 'valid_from' => '2026-01-01']);

        $tipus = \Illuminate\Support\Facades\DB::table('absence_types')->insertGetId(['name' => 'Vacances', 'category' => 'vacation']);
        \App\Models\Absence::create(['user_id' => $w->id, 'absence_type_id' => $tipus,
            'start_date' => '2026-01-08', 'end_date' => '2026-01-09', 'approved' => true]);

        \Illuminate\Support\Facades\DB::table('authorization_codes')->insert([
            'code' => 'COMPL1', 'concept' => 'Pacte', 'authorized_hours' => 10, 'type' => 'complementaria',
            'time_slot_start' => '08:00', 'time_slot_end' => '20:00', 'generated_by' => $admin->id, 'user_id' => $w->id,
            'valid_from' => '2026-01-01 00:00:00', 'valid_to' => '2026-12-31 23:59:59',
        ]);
        \Illuminate\Support\Facades\DB::table('work_logs')->insert([
            ['user_id' => $w->id, 'date' => '2026-01-05', 'start_time' => '2026-01-05 08:00:00', 'end_time' => '2026-01-05 12:00:00',
             'total_hours_worked' => 4, 'status' => 'approved', 'authorized_extra_code' => null, 'extra_hours_authorized' => 0],
            ['user_id' => $w->id, 'date' => '2026-01-07', 'start_time' => '2026-01-07 08:00:00', 'end_time' => '2026-01-07 11:00:00',
             'total_hours_worked' => 3, 'status' => 'pending', 'authorized_extra_code' => 'COMPL1', 'extra_hours_authorized' => 0.5],
            // Rebutjat: no compta
            ['user_id' => $w->id, 'date' => '2026-01-12', 'start_time' => '2026-01-12 08:00:00', 'end_time' => '2026-01-12 16:00:00',
             'total_hours_worked' => 8, 'status' => 'rejected', 'authorized_extra_code' => null, 'extra_hours_authorized' => 0],
        ]);

        $r = $this->getJson("/api/v1/users/{$w->id}/jornada?any=2026")->assertOk();
        $r->assertJsonPath('mesos.0.esperat_min', 18 * 228)
          ->assertJsonPath('mesos.0.ordinari_min', 390)
          ->assertJsonPath('mesos.0.complementaries_min', 30)
          ->assertJsonPath('mesos.0.pendents_validar_min', 180)
          ->assertJsonPath('mesos.0.diferencia_min', 390 - 18 * 228)
          ->assertJsonPath('mesos.0.acumulat_min', 390 - 18 * 228);
        // El març encara no ha arribat: no surt en negatiu
        $r->assertJsonPath('mesos.2.esperat_min', 0)->assertJsonPath('mesos.2.diferencia_min', null);
        // El febrer compta fins avui (dia 15) i s'acumula amb el gener
        $this->assertSame($r->json('mesos.0.acumulat_min') + $r->json('mesos.1.diferencia_min'), $r->json('mesos.1.acumulat_min'));
    }

    public function test_el_canvi_de_jornada_es_valida_i_nomes_el_fa_gestio(): void
    {
        $w = $this->treballadora();
        Sanctum::actingAs($w);
        $this->postJson("/api/v1/users/{$w->id}/jornada", ['pct' => 100, 'valid_from' => '2026-01-01'])->assertStatus(403);
        $this->getJson("/api/v1/users/{$w->id}/jornada")->assertStatus(403);

        Sanctum::actingAs($this->admin('hr'));
        $this->postJson("/api/v1/users/{$w->id}/jornada", ['pct' => 120])
            ->assertStatus(422)->assertJsonValidationErrors(['pct', 'valid_from']);
    }

    public function test_el_percentatge_de_retribucio_es_desa_a_part_i_un_treballador_no_se_l_canvia(): void
    {
        Sanctum::actingAs($this->admin('hr'));
        $w = $this->treballadora();

        $this->putJson("/api/v1/users/{$w->id}", ['retribucio_pct' => 81.25])->assertOk();
        $this->assertEquals(81.25, (float) $w->fresh()->retribucio_pct);
        $this->putJson("/api/v1/users/{$w->id}", ['retribucio_pct' => 120])
            ->assertStatus(422)->assertJsonValidationErrors(['retribucio_pct']);
        // No toca la jornada
        $this->assertSame(0, \App\Models\JornadaContracte::where('user_id', $w->id)->count());

        Sanctum::actingAs($w);
        $this->putJson("/api/v1/users/{$w->id}", ['retribucio_pct' => 100]);
        $this->assertEquals(81.25, (float) $w->fresh()->retribucio_pct);
    }
}
