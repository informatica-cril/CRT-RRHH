<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLogModification extends Model
{
    protected $table = 'work_log_modifications';

    protected $fillable = [
        'work_log_id', 'user_id', 'action',
        'old_values', 'new_values', 'comment',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function workLog()
    {
        return $this->belongsTo(WorkLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
