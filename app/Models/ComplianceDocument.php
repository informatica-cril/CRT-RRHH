<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Document de governança/compliment (política de gestió algorítmica, informació art. 90, RoPA,
 * EIPD…). Versionat i publicable. Un cop 'publicat' no s'edita: una nova versió és un
 * document nou. La publicació i l'acusament són la CONSTÀNCIA que habilita el motor disciplinari.
 */
class ComplianceDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipus', 'titol', 'versio', 'contingut', 'base_legal',
        'estat', 'requereix_acus', 'published_at', 'published_by',
    ];

    protected $casts = [
        'requereix_acus' => 'boolean',
        'published_at'   => 'datetime',
    ];

    public function acknowledgements()
    {
        return $this->hasMany(ComplianceAcknowledgement::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
