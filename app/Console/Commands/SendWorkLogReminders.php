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
        $today = now()->toDateString();
        $now   = now();

        $openLogs = WorkLog::whereNull('end_time')
            ->whereDate('date', $today)
            ->with('user.workSchedule')
            ->get();

        foreach ($openLogs as $log) {
            if (!$log->user || !$log->user->email) {
                continue;
            }

            $scheduledEnd = $this->scheduledEndTimeFor($log->user, Carbon::parse($log->getRawOriginal('start_time'), 'Europe/Madrid'));

            // ── Reminder 1: 10 min before scheduled end ───────────────────────
            if ($scheduledEnd && is_null($log->reminder_pre_sent_at)) {
                $window_start = $scheduledEnd->copy()->subMinutes(10);
                $window_end   = $scheduledEnd->copy()->addMinutes(5);

                if ($now->between($window_start, $window_end)) {
                    $this->sendReminder($log, 0, 'reminder_pre_sent_at', $now);
                }
            }

            // ── Reminder 2: workday has ended (scheduledEnd passed) ────────────
            if ($scheduledEnd && is_null($log->reminder1_sent_at)) {
                $window_start = $scheduledEnd->copy();
                $window_end   = $scheduledEnd->copy()->addHours(1);

                if ($now->between($window_start, $window_end)) {
                    $this->sendReminder($log, 1, 'reminder1_sent_at', $now);
                }
            }

            // ── Reminder 3: session still open 1h+ after scheduled end ────────
            if ($scheduledEnd && is_null($log->reminder2_sent_at)) {
                $afterEnd = $scheduledEnd->copy()->addHours(1);
                $at20h    = Carbon::today()->setHour(20)->setMinute(0)->setSecond(0);
                $fireAt   = $afterEnd->greaterThan($at20h) ? $afterEnd : $at20h;

                if ($now->greaterThanOrEqualTo($fireAt)) {
                    $this->sendReminder($log, 2, 'reminder2_sent_at', $now);
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
        $schedule = $user?->workSchedule;
        if (!$schedule || !$schedule->days) {
            return null;
        }

        $dayOfWeek = $date->dayOfWeek;
        $day = collect($schedule->days)->first(fn ($d) =>
            (int) ($d['day'] ?? -1) === $dayOfWeek && ($d['active'] ?? false)
        );

        if (!$day || empty($day['end'])) {
            return null;
        }

        [$eh, $em] = array_map('intval', explode(':', $day['end']));
        return Carbon::today()->setHour($eh)->setMinute($em)->setSecond(0);
    }
}
