<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplementarySlotDeclaration extends Model
{
    protected $fillable = [
        'user_id', 'month', 'slots', 'hours', 'status', 'confirmed_at', 'confirmed_ip',
        'withdrawal_reason', 'withdrawal_requested_at', 'resolved_at', 'resolved_note',
    ];

    protected $casts = [
        'month' => 'date:Y-m-d',
        'slots' => 'array',
        'confirmed_at' => 'datetime',
        'withdrawal_requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
