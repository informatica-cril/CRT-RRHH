<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Treballadors: entitat i hospital a la llista. Registres: pendents per mes. */
class EntitatIPendentsTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $rol = 'worker', array $extra = []): User
    {
        return User::create($extra + ['name' => 'P ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    public function test_rrhh_canvia_l_entitat_i_la_llista_porta_els_departaments(): void
    {
        $t = $this->persona();
        Sanctum::actingAs($this->persona('hr'));

        $this->putJson("/api/v1/users/{$t->id}", ['entitat' => 'CRIL_CRT'])->assertOk();
        $this->assertSame('CRIL_CRT', $t->fresh()->entitat);
        $this->putJson("/api/v1/users/{$t->id}", ['entitat' => 'HOSPITAL'])->assertStatus(422)
            ->assertJsonFragment(['L\'entitat no és vàlida (CRIL, CRT o totes dues).']);

        $fila = collect($this->getJson('/api/v1/users/bulk-index')->assertOk()->json('users'))->firstWhere('id', $t->id);
        $this->assertArrayHasKey('departments', $fila);   // la pantalla hi mira si és de l'hospital
    }

    public function test_pendents_per_mes(): void
    {
        $t = $this->persona();
        foreach ([['2026-10-02', 'pending'], ['2026-09-15', 'pending'], ['2026-09-20', 'pending'], ['2026-08-03', 'approved']] as [$d, $e]) {
            DB::table('work_logs')->insert(['user_id' => $t->id, 'date' => $d, 'start_time' => "$d 08:00:00", 'end_time' => "$d 15:00:00",
                'status' => $e, 'created_at' => now(), 'updated_at' => now()]);
        }
        // Un fitxatge obert (sense sortida) no es pot aprovar: no compta com a pendent per revisar.
        DB::table('work_logs')->insert(['user_id' => $t->id, 'date' => '2026-10-05', 'start_time' => '2026-10-05 08:00:00',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($this->persona('hr'));

        $this->getJson('/api/v1/work-logs/pendents-per-mes')->assertOk()
            ->assertExactJson([['mes' => '2026-10', 'n' => 1], ['mes' => '2026-09', 'n' => 2]]);

        Sanctum::actingAs($t);
        $this->getJson('/api/v1/work-logs/pendents-per-mes')->assertForbidden();
    }

    public function test_el_llistat_diu_quins_fitxatges_tenen_trossos_fora_de_zona_pendents(): void
    {
        $t = $this->persona();
        $id = DB::table('work_logs')->insertGetId(['user_id' => $t->id, 'date' => '2026-11-16', 'start_time' => '2026-11-16 09:13:00',
            'end_time' => '2026-11-16 13:25:00', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[1, '09:13', '13:00', true, 227], [2, '13:00', '13:25', false, 25]] as [$n, $a, $b, $dins, $m]) {
            DB::table('work_log_segments')->insert(['work_log_id' => $id, 'segment_number' => $n, 'start_time' => "2026-11-16 $a:00",
                'end_time' => "2026-11-16 $b:00", 'in_zone' => false, 'in_schedule' => $dins, 'duration_minutes' => $m,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }
        Sanctum::actingAs($this->persona('hr'));

        $f = collect($this->getJson('/api/v1/work-logs?mes=2026-11')->assertOk()->json())->firstWhere('id', $id);
        $this->assertSame(2, (int) $f['trams_fora_zona_pendents']);
        $this->assertSame(252, (int) $f['minuts_fora_zona_pendents']);
        $this->assertSame(227, (int) $f['minuts_dins_horari_pendents']);

        DB::table('work_log_segments')->where('work_log_id', $id)->update(['status' => 'approved']);
        $f = collect($this->getJson('/api/v1/work-logs?mes=2026-11')->json())->firstWhere('id', $id);
        $this->assertSame(0, (int) $f['trams_fora_zona_pendents']);   // aprovat: deixa de marcar-se
    }
}
