<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertifiedMail extends Model
{
    protected $fillable = [
        'sender_id', 'tipus', 'expedient_ref', 'assumpte', 'cos', 'acceptacio',
        'programat_at', 'adjunts_json', 'estat_global', 'error_txt',
    ];

    protected $casts = [
        'acceptacio' => 'boolean',
        'programat_at' => 'datetime',
        'adjunts_json' => 'array',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CertifiedMailRecipient::class);
    }
}
