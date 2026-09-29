<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationCode extends Model
{
    protected $fillable = [
        'code', 'concept', 'authorized_hours',
        'time_slot_start', 'time_slot_end',
        'generated_by', 'user_id',
        'valid_from', 'valid_to',
        'used', 'used_at', 'revoked', 'revoked_at',
        'notification_sent',
        'type', 'factor', 'domi_origen',
    ];

    protected $casts = [
        'authorized_hours' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'used' => 'boolean',
        'used_at' => 'datetime',
        'revoked' => 'boolean',
        'revoked_at' => 'datetime',
        'notification_sent' => 'boolean',
    ];

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Validate if this code can be used right now.
     */
    public function validate(): array
    {
        if ($this->revoked) {
            return ['valid' => false, 'reason' => 'Codi revocat'];
        }
        if ($this->used) {
            return ['valid' => false, 'reason' => 'code_used'];
        }
        $now = now();
        if ($now->lt($this->valid_from) || $now->gt($this->valid_to)) {
            return ['valid' => false, 'reason' => 'code_expired'];
        }
        return ['valid' => true, 'code' => $this];
    }

    public function markUsed(): void
    {
        $this->update(['used' => true, 'used_at' => now()]);
    }

    public function revoke(): void
    {
        $this->update(['revoked' => true, 'revoked_at' => now()]);
    }
}
