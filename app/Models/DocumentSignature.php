<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSignature extends Model
{
    protected $fillable = [
        'document_id', 'user_id', 'viewed_at', 'signed_at',
        'document_hash', 'signature_hash',
        'timestamp_source', 'timestamp_token',
        'signing_payload', 'legal_basis',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function isViewed(): bool
    {
        return $this->viewed_at !== null;
    }
}
