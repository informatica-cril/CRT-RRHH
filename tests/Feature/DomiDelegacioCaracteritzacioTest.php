<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CARACTERITZACIÓ del pont domi → RRHH: DomiIntegrationController DELEGA en
 * WorkLogController (store/update) amb sub-requests construïdes a mà. Aquest
 * camí no passa per la injecció del framework, així que un canvi de signatura
 * al controlador (p. ex. FormRequests) el pot trencar SENSE que cap altre test
 * ho detecti. Aquests tests el fixen.
 */
class DomiDelegacioCaracteritzacioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 500)]); // worldtimeapi → rellotge local
    }

    private function servei(): User
    {
        return User::create([
            'name' => 'Servei domi', 'email' => 'servei' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'service', 'active' => true,
        ]);
    }

    private function worker(): User
    {
        return User::create([
            'name' => 'Fisio Domi', 'email' => 'fisio' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'relacio' => 'laboral',
            'dni' => 'DNI' . strtoupper(uniqid()), 'active' => true,
        ]);
    }

    public function test_clock_in_via_domi_obre_jornada(): void
    {
        Sanctum::actingAs($this->servei(), \App\Support\DomiScopes::permisos());
        $w = $this->worker();

        $r = $this->postJson('/api/v1/domi/jornada/start', ['dni' => $w->dni]);
        $r->assertStatus(201);

        $log = WorkLog::where('user_id', $w->id)->whereNull('end_time')->first();
        $this->assertNotNull($log, 'clockIn ha de deixar una jornada oberta');
        $startLocal = Carbon::parse($log->getRawOriginal('start_time'), 'Europe/Madrid');
        $this->assertTrue($startLocal->diffInMinutes(Carbon::now('Europe/Madrid')) < 2);
    }

    public function test_clock_out_via_domi_tanca_i_calcula_hores(): void
    {
        Sanctum::actingAs($this->servei(), \App\Support\DomiScopes::permisos());
        $w = $this->worker();
        $start = Carbon::now('Europe/Madrid')->subHours(3);
        WorkLog::create([
            'user_id' => $w->id,
            'date' => $start->copy()->setTimezone('Europe/Madrid')->toDateString(),
            'start_time' => $start->toDateTimeString(),
            'status' => 'pending',
        ]);

        $r = $this->postJson('/api/v1/domi/jornada/stop', ['dni' => $w->dni]);
        $r->assertStatus(200);

        $log = WorkLog::where('user_id', $w->id)->firstOrFail();
        $this->assertNotNull($log->getRawOriginal('end_time'));
        $this->assertEqualsWithDelta(3.0, (float) $log->total_hours_worked, 0.05,
            'les hores les calcula el servidor en la delegació igual que en la crida directa');
    }

    public function test_primer_hito_arribada_obre_jornada_en_mode_hitos(): void
    {
        Sanctum::actingAs($this->servei(), \App\Support\DomiScopes::permisos());
        $w = $this->worker();

        $r = $this->postJson('/api/v1/domi/hito', [
            'dni' => $w->dni, 'tipus' => 'arribada',
            'moment' => now()->toIso8601String(), 'radi_ok' => true,
        ]);
        $this->assertContains($r->getStatusCode(), [200, 201],
            'el primer hito del dia ha d\'obrir jornada, no fallar');
        $this->assertNotNull(
            WorkLog::where('user_id', $w->id)->whereNull('end_time')->first(),
            'el hito d\'arribada sense jornada prèvia l\'obre automàticament'
        );
    }
}
