<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** El recordatori de la jornada sense tancar surt dins de l'app (ja no per correu). */
class JornadaObertaNotificacioTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function treballador(): User
    {
        $s = WorkSchedule::create(['name' => 'Matí', 'total_hours_weekly' => 25,
            'days' => array_map(fn ($d) => ['day' => $d, 'active' => true, 'start' => '09:00', 'end' => '14:00'], [1, 2, 3, 4, 5])]);

        return User::create(['name' => 'T', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'active' => true, 'work_schedule_id' => $s->id]);
    }

    private function obre(User $u, string $dia): int
    {
        return DB::table('work_logs')->insertGetId(['user_id' => $u->id, 'date' => $dia, 'start_time' => "$dia 09:00:00",
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function ids(): array
    {
        return collect($this->getJson('/api/v1/notificacions')->assertOk()->json('items'))->pluck('id')->all();
    }

    public function test_avisa_quan_l_horari_ha_acabat_i_la_jornada_segueix_oberta(): void
    {
        $u = $this->treballador();
        $id = $this->obre($u, '2026-11-17');   // dimarts
        Sanctum::actingAs($u);

        Carbon::setTestNow(Carbon::parse('2026-11-17 13:00', 'Europe/Madrid'));   // abans del final: res
        $this->assertNotContains("jornada-oberta-$id", $this->ids());

        Carbon::setTestNow(Carbon::parse('2026-11-17 14:10', 'Europe/Madrid'));   // ja ha acabat
        $this->assertContains("jornada-oberta-$id", $this->ids());

        DB::table('work_logs')->where('id', $id)->update(['end_time' => '2026-11-17 14:05:00']);   // tancada: desapareix
        $this->assertNotContains("jornada-oberta-$id", $this->ids());
    }

    public function test_no_envia_cap_correu(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $u = $this->treballador();
        $this->obre($u, '2026-11-17');
        Carbon::setTestNow(Carbon::parse('2026-11-17 14:10', 'Europe/Madrid'));
        $this->artisan('schedule:run')->assertExitCode(0);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }
}
