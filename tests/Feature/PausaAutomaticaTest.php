<?php

namespace Tests\Feature;

use App\Models\BreakSetting;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La pausa obligatòria la porta el servidor: s'obre i es tanca encara que la pantalla
 * del treballador no estigui oberta (ordinadors compartits per diverses persones).
 */
class PausaAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 500)]);
        // Dimarts 6-10-2026, 14:00 (Madrid)
        Carbon::setTestNow(Carbon::create(2026, 10, 6, 14, 0, 0, 'Europe/Madrid'));
        BreakSetting::getSettings()->update([
            'enabled' => true, 'threshold_hours' => 5, 'break_duration_minutes' => 20,
            'break_start_mode' => 'offset', 'break_start_offset_minutes' => 300,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function worker(array $extra = []): User
    {
        $schedule = WorkSchedule::create([
            'name' => 'Jornada 8h', 'total_hours_weekly' => 40,
            'days' => [['day' => 2, 'active' => true, 'start' => '08:00', 'end' => '16:00']],
        ]);

        return User::create(array_merge([
            'name' => 'Treballador Pausa', 'email' => 'p' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
            'work_schedule_id' => $schedule->id,
        ], $extra));
    }

    private function jornada(User $user, string $inici, array $extra = []): WorkLog
    {
        return WorkLog::create(array_merge([
            'user_id' => $user->id, 'date' => '2026-10-06',
            'start_time' => "2026-10-06 {$inici}:00", 'status' => 'pending',
        ], $extra));
    }

    public function test_obre_la_pausa_quan_toca_sense_cap_pantalla_oberta(): void
    {
        $log = $this->jornada($this->worker(), '08:30'); // offset 5 h → 13:30

        $this->artisan('worklogs:pausa-automatica')->assertSuccessful();

        $log->refresh();
        $this->assertSame('active', $log->break_status);
        $this->assertSame('2026-10-06 14:00:00', $log->getRawOriginal('break_start_time'));
        $this->assertDatabaseHas('work_log_modifications', ['work_log_id' => $log->id, 'action' => 'break_started']);
    }

    public function test_no_obre_la_pausa_abans_d_hora(): void
    {
        $log = $this->jornada($this->worker(), '10:00'); // toca a les 15:00

        $this->artisan('worklogs:pausa-automatica');

        $this->assertSame('pending', $log->refresh()->break_status);
    }

    public function test_no_obre_pausa_si_la_jornada_prevista_no_arriba_al_llindar(): void
    {
        $user = $this->worker();
        $user->workSchedule->update(['days' => [['day' => 2, 'active' => true, 'start' => '08:00', 'end' => '12:00']]]);
        $log = $this->jornada($user, '08:00');

        $this->artisan('worklogs:pausa-automatica');

        $this->assertSame('pending', $log->refresh()->break_status);
    }

    public function test_respecta_la_pausa_desactivada_per_a_la_persona(): void
    {
        $log = $this->jornada($this->worker(['break_override' => false]), '08:00');

        $this->artisan('worklogs:pausa-automatica');

        $this->assertSame('pending', $log->refresh()->break_status);
    }

    public function test_hora_fixa_de_la_persona(): void
    {
        $log = $this->jornada($this->worker(['break_override_time' => '13:45:00']), '08:00');

        $this->artisan('worklogs:pausa-automatica');

        $this->assertSame('active', $log->refresh()->break_status);
    }

    public function test_tanca_la_pausa_a_inici_mes_durada_exacte(): void
    {
        $log = $this->jornada($this->worker(), '08:00', [
            'break_status' => 'active', 'break_start_time' => '2026-10-06 13:00:00',
        ]);

        $this->artisan('worklogs:pausa-automatica');

        $log->refresh();
        $this->assertSame('completed', $log->break_status);
        $this->assertSame('2026-10-06 13:20:00', $log->getRawOriginal('break_end_time'));
    }

    public function test_no_tanca_una_pausa_encara_en_curs(): void
    {
        $log = $this->jornada($this->worker(), '08:00', [
            'break_status' => 'active', 'break_start_time' => '2026-10-06 13:50:00',
        ]);

        $this->artisan('worklogs:pausa-automatica');

        $this->assertSame('active', $log->refresh()->break_status);
    }

    public function test_pausa_en_curs_en_fitxar_la_sortida_acaba_a_l_hora_de_sortida(): void
    {
        $log = $this->jornada($this->worker(), '08:00', [
            'break_status' => 'active', 'break_start_time' => '2026-10-06 13:55:00',
            'end_time' => '2026-10-06 13:58:00',
        ]);

        $this->artisan('worklogs:pausa-automatica');

        $log->refresh();
        $this->assertSame('completed', $log->break_status);
        $this->assertSame('2026-10-06 13:58:00', $log->getRawOriginal('break_end_time'));
    }

    public function test_no_torna_a_obrir_una_pausa_ja_feta(): void
    {
        $log = $this->jornada($this->worker(), '08:00', [
            'break_status' => 'completed',
            'break_start_time' => '2026-10-06 13:00:00', 'break_end_time' => '2026-10-06 13:20:00',
        ]);

        $this->artisan('worklogs:pausa-automatica');

        $this->assertSame('2026-10-06 13:00:00', $log->refresh()->getRawOriginal('break_start_time'));
    }

    public function test_la_pantalla_reoberta_tard_no_allarga_la_pausa(): void
    {
        $user = $this->worker();
        $log = $this->jornada($user, '08:00', [
            'break_status' => 'active', 'break_start_time' => '2026-10-06 13:00:00',
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/work-logs/{$log->id}/complete-break")->assertOk();

        $this->assertSame('2026-10-06 13:20:00', $log->refresh()->getRawOriginal('break_end_time'));
    }

    public function test_la_pantalla_no_reescriu_una_pausa_que_ja_ha_tancat_el_servidor(): void
    {
        $user = $this->worker();
        $log = $this->jornada($user, '08:00', [
            'break_status' => 'completed',
            'break_start_time' => '2026-10-06 13:00:00', 'break_end_time' => '2026-10-06 13:20:00',
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/work-logs/{$log->id}/complete-break")
            ->assertOk()->assertJsonPath('break_status', 'completed');

        $this->assertSame('2026-10-06 13:20:00', $log->refresh()->getRawOriginal('break_end_time'));
        $this->assertDatabaseCount('work_log_modifications', 0);
    }
}
