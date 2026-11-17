<?php

namespace App\Console\Commands;

use App\Mail\WorkLogReminderMail;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendWorkLogReminders extends Command
{
    protected $signature = 'worklogs:send-reminders';
    protected $description = 'Send email reminders to workers who have not closed their work log';

    public function handle(): void
    {
        $now   = now('Europe/Madrid');
        $today = $now->toDateString();

        $openLogs = WorkLog::whereNull('end_time')
            ->whereDate('date', $today)
            ->with('user.workSchedule')
            ->get();

        foreach ($openLogs as $log) {
            if (!$log->user || !$log->user->email) {
                continue;
            }

            $scheduledEnd = $this->scheduledEndTimeFor($log->user, Carbon::parse($log->getRawOriginal('start_time'), 'Europe/Madrid'));

            // Un sol recordatori (RRHH, 11-2026: arribaven massa correus, a totes hores i alguns equivocats):
            // quan la jornada ha acabat segons l'horari i continua oberta. Els avisos «falten 10 min» i «a les
            // 20:00» ja no s'envien; la jornada oberta es veu i es resol a la Safata.
            if ($scheduledEnd && is_null($log->reminder1_sent_at)) {
                $window_start = $scheduledEnd->copy();
                $window_end   = $scheduledEnd->copy()->addHours(1);

                if ($now->between($window_start, $window_end)) {
                    $this->sendReminder($log, 1, 'reminder1_sent_at', $now);
                }
            }
        }
    }

    /**
     * Atomically mark the flag and send the email.
     * Uses DB::table WHERE NULL to prevent duplicate sends if cron overlaps.
     */
    private function sendReminder(WorkLog $log, int $type, string $flag, Carbon $now): void
    {
        $affected = DB::table('work_logs')
            ->where('id', $log->id)
            ->whereNull($flag)
            ->update([$flag => $now->toDateTimeString()]);

        if ($affected === 0) {
            return; // already sent by a concurrent cron run
        }

        try {
            Mail::to($log->user->email)->send(new WorkLogReminderMail($log, $type));
            $this->info("Reminder {$type} → {$log->user->email} (log #{$log->id})");
        } catch (\Exception $e) {
            // Roll back flag so the next cron retries the email
            DB::table('work_logs')->where('id', $log->id)->update([$flag => null]);
            $this->error("Mail failed for log #{$log->id}: {$e->getMessage()}");
        }
    }

    private function scheduledEndTimeFor(?User $user, Carbon $date): ?Carbon
    {
        // Quadrant o plantilla d'aquell dia (veure HorariDia).
        $trams = \App\Services\HorariDia::trams($user, $date->toDateString());
        // L'ÚLTIM tram: amb jornada partida, el final del primer tram (p. ex. 13:00) feia saltar l'avís a mig dia.
        $day = $trams ? end($trams) : null;

        if (!$day || empty($day['end'])) {
            return null;
        }

        [$eh, $em] = array_map('intval', explode(':', $day['end']));
        return Carbon::parse($date->toDateString(), 'Europe/Madrid')->setTime($eh, $em, 0);
    }
}
