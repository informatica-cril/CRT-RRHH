<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fitxatge automàtic (gerència): qui el té activat no fitxa; l'app li crea l'entrada i la sortida
 * de cada tram del seu horari del dia. Només admin el pot activar.
 */
class FitxatgeAutomaticTest extends TestCase
{
    use RefreshDatabase;

    private function gerent(bool $actiu = true): User
    {
        // Dilluns a divendres, jornada partida de 9 a 14 i de 15 a 18.
        $dies = [];
        foreach ([1, 2, 3, 4, 5] as $d) {
            $dies[] = ['day' => $d, 'start' => '09:00', 'end' => '14:00'];
            $dies[] = ['day' => $d, 'start' => '15:00', 'end' => '18:00'];
        }
        $s = WorkSchedule::create(['name' => 'Gerència', 'total_hours_weekly' => 40, 'days' => $dies]);
        $u = User::create(['name' => 'Gerent', 'email' => 'g' . uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'active' => true, 'work_schedule_id' => $s->id]);
        $u->forceFill(['fitxatge_automatic' => $actiu])->save();

        return $u;
    }

    private function executa(string $ara, ?string $dia = null): void
    {
        Carbon::setTestNow(Carbon::parse($ara, 'Europe/Madrid'));
        $this->artisan('worklogs:fitxatge-automatic', $dia ? ['--dia' => $dia] : [])->assertSuccessful();
    }

    public function test_crea_l_entrada_i_la_sortida_de_cada_tram_aprovades(): void
    {
        $g = $this->gerent();
        $this->executa('2026-10-21 23:30:00');   // dimecres

        $logs = WorkLog::where('user_id', $g->id)->whereDate('date', '2026-10-21')->orderBy('start_time')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('2026-10-21 09:00:00', $logs[0]->getRawOriginal('start_time'));
        $this->assertSame('2026-10-21 14:00:00', $logs[0]->getRawOriginal('end_time'));
        $this->assertSame('2026-10-21 15:00:00', $logs[1]->getRawOriginal('start_time'));
        $this->assertTrue($logs->every(fn ($l) => $l->status === 'approved' && $l->fitxatge_automatic));
        $this->assertEquals(8, $logs->sum(fn ($l) => (float) $l->total_hours_worked));
    }

    public function test_tornar_a_executar_no_duplica_i_no_fitxa_el_futur(): void
    {
        $g = $this->gerent();
        $this->executa('2026-10-21 16:00:00');   // el segon tram encara no ha acabat
        $this->assertSame(1, WorkLog::where('user_id', $g->id)->whereDate('date', '2026-10-21')->count());
        $this->assertSame(2, WorkLog::where('user_id', $g->id)->whereDate('date', '2026-10-20')->count());   // i ahir, sencer

        $this->executa('2026-10-21 23:30:00');   // ja hi ha un fitxatge aquell dia: no es toca
        $this->assertSame(1, WorkLog::where('user_id', $g->id)->whereDate('date', '2026-10-21')->count());
    }

    public function test_ni_festius_ni_permisos_ni_dies_que_ja_ha_fitxat_ni_qui_no_ho_te(): void
    {
        $g = $this->gerent();
        $normal = $this->gerent(false);
        Holiday::create(['date' => '2026-10-12', 'name' => 'Festa', 'type' => 'national', 'year' => 2026]);
        $tipus = AbsenceType::create(['name' => 'Vacances', 'category' => 'vacation']);
        Absence::create(['user_id' => $g->id, 'absence_type_id' => $tipus->id, 'start_date' => '2026-10-13', 'end_date' => '2026-10-13', 'approved' => true]);
        WorkLog::create(['user_id' => $g->id, 'date' => '2026-10-14', 'start_time' => '2026-10-14 10:00:00', 'end_time' => '2026-10-14 12:00:00', 'status' => 'pending']);

        foreach (['2026-10-12', '2026-10-13', '2026-10-14'] as $dia) {
            $this->executa('2026-10-21 23:30:00', $dia);
        }
        $this->executa('2026-10-21 23:30:00', '2026-10-18');   // diumenge: sense horari

        $this->assertSame(1, WorkLog::where('user_id', $g->id)->count());          // només el que va fitxar el 14
        $this->assertSame(0, WorkLog::where('fitxatge_automatic', true)->count());
        $this->executa('2026-10-21 23:30:00');
        $this->assertSame(0, WorkLog::where('user_id', $normal->id)->count());
    }

    public function test_nomes_admin_l_activa(): void
    {
        $g = $this->gerent(false);
        $hr = User::create(['name' => 'RRHH', 'email' => 'h' . uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'hr', 'active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'a' . uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'admin', 'active' => true]);

        Sanctum::actingAs($hr);
        $this->putJson("/api/v1/users/{$g->id}/fitxatge-automatic", ['actiu' => true])->assertForbidden();
        Sanctum::actingAs($g);
        $this->putJson("/api/v1/users/{$g->id}/fitxatge-automatic", ['actiu' => true])->assertForbidden();
        $this->assertFalse($g->fresh()->fitxatge_automatic);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/users/{$g->id}/fitxatge-automatic", ['actiu' => true])->assertOk();
        $this->assertTrue($g->fresh()->fitxatge_automatic);
        $this->assertSame($admin->id, $g->fresh()->fitxatge_automatic_by);
    }
}
