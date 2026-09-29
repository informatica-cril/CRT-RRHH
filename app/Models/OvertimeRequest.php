<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    protected $fillable = [
        'user_id', 'hours', 'period_from', 'period_to', 'requested_by', 'reason',
        'domi_ref', 'status', 'authorized_by', 'authorized_at', 'authorization_code_id',
        'denial_reason',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'period_from' => 'date',
        'period_to' => 'date',
        'authorized_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
