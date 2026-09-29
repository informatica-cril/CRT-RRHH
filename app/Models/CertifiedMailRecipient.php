<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertifiedMailRecipient extends Model
{
    protected $fillable = [
        'certified_mail_id', 'user_id', 'email', 'nom',
        'id_mensaje', 'estat', 'estat_txt', 'estat_at',
    ];

    protected $casts = [
        'estat_at' => 'datetime',
    ];

    public function certifiedMail(): BelongsTo
    {
        return $this->belongsTo(CertifiedMail::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
