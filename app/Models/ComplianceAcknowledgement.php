<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Acusament individual d'un document de compliment per part d'un treballador.
 * Guarda la versió acusada (snapshot), la data i la IP: és la prova que la informació va ser
 * PRÈVIA i acreditable (LOPDGDD art. 90). Únic per (document, usuari).
 */
class ComplianceAcknowledgement extends Model
{
    use HasFactory;

    protected $fillable = [
        'compliance_document_id', 'user_id', 'document_versio', 'acknowledged_at', 'ip',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(ComplianceDocument::class, 'compliance_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
