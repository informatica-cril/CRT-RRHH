<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingProfile extends Model
{
    protected $fillable = ['name', 'description', 'document_ids', 'doc_modes', 'is_default'];

    protected $casts = [
        'document_ids' => 'array', // Ordered JSON array of document IDs
        'doc_modes' => 'array',    // document_id => 'signature' | 'view_only'
        'is_default' => 'boolean',
    ];

    /** Per defecte un document de l'alta es signa: la lectura simple s'ha de declarar. */
    public function modeFor(int $documentId): string
    {
        return ($this->doc_modes[(string) $documentId] ?? $this->doc_modes[$documentId] ?? null) === 'view_only'
            ? 'view_only' : 'signature';
    }

    public function statuses()
    {
        return $this->hasMany(OnboardingStatus::class, 'profile_id');
    }

    public function documents()
    {
        // Manual relationship since document_ids is a JSON array
        return Document::whereIn('id', $this->document_ids ?? [])->get();
    }
}
