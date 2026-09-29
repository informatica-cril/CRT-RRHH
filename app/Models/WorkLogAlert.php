<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLogAlert extends Model
{
    protected $table = 'work_log_alerts';

    protected $fillable = [
        'work_log_id', 'user_id', 'type', 'message',
        'scheduled_at', 'sent_at', 'dismissed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function workLog()
    {
        return $this->belongsTo(WorkLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->whereNull('dismissed_at');
    }

    public function scopeUnsent($query)
    {
        return $query->whereNull('sent_at');
    }
}
