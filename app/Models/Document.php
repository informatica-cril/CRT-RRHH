<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'title', 'description', 'content', 'file_name',
        'file_path', 'file_mime', 'category',
        'requires_signature', 'is_urgent',
        'target_users', 'created_by',
    ];

    protected $casts = [
        'requires_signature' => 'boolean',
        'is_urgent' => 'boolean',
        'target_users' => 'json',
    ];

    protected $appends = ['pdf_data'];

    public function getPdfDataAttribute()
    {
        if ($this->file_path) {
            return asset('storage/' . $this->file_path);
        }

        return null;
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signatures()
    {
        return $this->hasMany(DocumentSignature::class);
    }

    /**
     * Check if a document targets a specific user.
     */
    public function targetsUser(int $userId): bool
    {
        $val = $this->target_users;
        
        // If it's a string, it might be the JSON literal "all" or an encoded array
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            // json_decode returns null if invalid JSON, or the decoded value
            if ($decoded !== null) {
                $val = $decoded;
            }
        }

        if ($val === 'all' || $val === null || $val === '') {
            return true;
        }
        if (is_array($val)) {
            return in_array($userId, $val);
        }
        return false;
    }

    /**
     * Scope: urgent documents requiring signature.
     */
    public function scopeUrgentRequiringSignature($query)
    {
        return $query->where('is_urgent', true)->where('requires_signature', true);
    }
}
