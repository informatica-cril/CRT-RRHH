<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Mail\SenseSortidaMail;
use App\Mail\WelcomeMail;
use App\Mail\WorkLogReminderMail;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El contacte de RRHH dels correus és rrhh@lirc.es (també des de CRT): al peu i a «Respondre a». */
class CorreuContacteRrhhTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_respostes_van_a_rrhh_de_cril(): void
    {
        $this->assertSame('rrhh@lirc.es', config('mail.reply_to.address'));
    }

    public function test_tots_els_correus_porten_el_contacte_de_rrhh_al_peu(): void
    {
        $u = User::create(['name' => 'Persona', 'email' => 'p@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
        $log = WorkLog::create(['user_id' => $u->id, 'date' => '2026-10-13', 'start_time' => '2026-10-13 08:00:00', 'status' => 'pending']);

        foreach ([
            new PasswordResetMail($u, 'Temporal-123'),
            new WelcomeMail($u, 'Temporal-123'),
            new WorkLogReminderMail($log, 1),
            new SenseSortidaMail($u, collect([$log])),
        ] as $correu) {
            $this->assertStringContainsString('rrhh@lirc.es', $correu->render(), get_class($correu));
        }
    }
}
