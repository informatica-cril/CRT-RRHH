<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Aprovació conjunta de fitxatges: el mateix que el botó «Aprovar», per a una selecció. */
class WorkLogBulkTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol): User
    {
        return User::create(['name' => 'P ' . $rol, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    private function fitxatge(User $u, string $estat, array $extra = []): int
    {
        return DB::table('work_logs')->insertGetId($extra + [
            'user_id' => $u->id, 'date' => '2026-09-10', 'start_time' => '2026-09-10 08:00:00', 'end_time' => '2026-09-10 15:00:00',
            'total_hours_worked' => 7, 'status' => $estat, 'hour_status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_aprova_nomes_els_pendents_i_recupera_les_hores_fora_de_zona(): void
    {
        $t = $this->usuari('worker');
        $normal = $this->fitxatge($t, 'pending');
        $foraZona = $this->fitxatge($t, 'pending', ['total_hours_worked' => 5, 'hours_out_of_area' => 2, 'hour_status' => 'out_of_area']);
        $rebutjat = $this->fitxatge($t, 'rejected');

        Sanctum::actingAs($this->usuari('hr'));
        $this->postJson('/api/v1/work-logs/aprova-seleccionats', ['ids' => [$normal, $foraZona, $rebutjat]])
            ->assertOk()->assertJson(['aprovats' => 2, 'omesos' => 1]);

        $this->assertDatabaseHas('work_logs', ['id' => $normal, 'status' => 'approved']);
        $this->assertDatabaseHas('work_logs', ['id' => $foraZona, 'status' => 'approved', 'hour_status' => 'ok', 'hours_out_of_area' => 0, 'total_hours_worked' => 7]);
        $this->assertDatabaseHas('work_logs', ['id' => $rebutjat, 'status' => 'rejected']);
        $this->assertSame(2, DB::table('work_log_modifications')->where('action', 'approved')->count());
    }

    public function test_aprovar_fora_de_zona_no_duplica_les_hores(): void
    {
        // El servidor ja desa les brutes a total_hours_worked: 09:08 → 13:28 = 4h20.
        $t = $this->usuari('worker');
        $id = $this->fitxatge($t, 'pending', [
            'start_time' => '2026-10-05 09:08:00', 'end_time' => '2026-10-05 13:28:00',
            'total_hours_worked' => 4.33, 'hours_worked' => 0, 'hours_out_of_area' => 4.33, 'hour_status' => 'out_of_area',
        ]);

        Sanctum::actingAs($this->usuari('hr'));
        $this->postJson('/api/v1/work-logs/aprova-seleccionats', ['ids' => [$id]])->assertOk();

        $l = DB::table('work_logs')->find($id);
        $this->assertEquals(4.33, (float) $l->total_hours_worked);
        $this->assertEquals(4.33, (float) $l->hours_worked);
        $this->assertEquals(0, (float) $l->hours_out_of_area);
    }

    public function test_l_app_publicada_que_aprova_amb_la_suma_tampoc_duplica(): void
    {
        $t = $this->usuari('worker');
        $id = $this->fitxatge($t, 'pending', [
            'start_time' => '2026-10-05 09:08:00', 'end_time' => '2026-10-05 13:28:00',
            'total_hours_worked' => 4.33, 'hours_worked' => 0, 'hours_out_of_area' => 4.33, 'hour_status' => 'out_of_area',
        ]);

        Sanctum::actingAs($this->usuari('hr'));
        $this->putJson("/api/v1/work-logs/{$id}", [
            'status' => 'approved', 'total_hours_worked' => 8.66, 'hours_worked' => 8.66,
            'hours_out_of_area' => 0, 'hour_status' => 'ok',
        ])->assertOk();

        $l = DB::table('work_logs')->find($id);
        $this->assertSame('approved', $l->status);
        $this->assertEquals(4.33, (float) $l->total_hours_worked);
        $this->assertEquals(4.33, (float) $l->hours_worked);
    }

    public function test_el_llistat_mostra_el_temps_fora_d_horari_pendent(): void
    {
        $t = $this->usuari('worker');
        $id = $this->fitxatge($t, 'pending', ['segmented' => true]);
        foreach ([[120, true, 'approved'], [117, false, 'pending'], [30, false, 'rejected']] as $i => [$min, $horari, $estat]) {
            DB::table('work_log_segments')->insert([
                'work_log_id' => $id, 'segment_number' => $i + 1,
                'start_time' => '2026-09-10 08:00:00', 'end_time' => '2026-09-10 09:00:00',
                'duration_minutes' => $min, 'in_zone' => true, 'in_schedule' => $horari, 'status' => $estat,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($this->usuari('hr'));
        $fila = collect($this->getJson('/api/v1/work-logs')->assertOk()->json())->firstWhere('id', $id);
        $this->assertEquals(117, (int) $fila['minuts_fora_horari_pendents']);
    }

    public function test_un_treballador_no_s_aprova_ni_retoca_el_seu_fitxatge_tancat(): void
    {
        $t = $this->usuari('worker');
        $id = $this->fitxatge($t, 'pending');
        Sanctum::actingAs($t);

        $this->putJson("/api/v1/work-logs/{$id}", ['status' => 'approved', 'total_hours_worked' => 12, 'hours_worked' => 12])->assertOk();
        // Tornar a fitxar la sortida d'una jornada ja tancada no la mou.
        $this->putJson("/api/v1/work-logs/{$id}", ['end_time' => now()->toIso8601String(), 'status' => 'pending'])->assertOk();

        $l = DB::table('work_logs')->find($id);
        $this->assertSame('pending', $l->status);
        $this->assertEquals(7, (float) $l->total_hours_worked);
        $this->assertSame('2026-09-10 15:00:00', $l->end_time);
    }

    public function test_un_treballador_pot_fitxar_la_sortida_de_la_jornada_oberta(): void
    {
        $t = $this->usuari('worker');
        $id = $this->fitxatge($t, 'pending', ['end_time' => null, 'start_time' => now()->subHours(2)->toDateTimeString(), 'date' => now()->toDateString()]);
        Sanctum::actingAs($t);

        $this->putJson("/api/v1/work-logs/{$id}", ['end_time' => now()->toIso8601String(), 'status' => 'approved'])->assertOk();

        $l = DB::table('work_logs')->find($id);
        $this->assertNotNull($l->end_time);
        $this->assertSame('pending', $l->status);
        $this->assertGreaterThan(1.9, (float) $l->total_hours_worked);
    }

    public function test_un_treballador_no_pot_aprovar(): void
    {
        $t = $this->usuari('worker');
        $id = $this->fitxatge($t, 'pending');
        Sanctum::actingAs($t);
        $this->postJson('/api/v1/work-logs/aprova-seleccionats', ['ids' => [$id]])->assertForbidden();
        $this->assertDatabaseHas('work_logs', ['id' => $id, 'status' => 'pending']);
    }
}
