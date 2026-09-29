<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'month', 'year', 'amount',
        'viewed_at', 'signed_at', 'signature_hash', 'expires_at',
        'payroll_base64', 'file_name'
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected $appends = ['pdf_data'];

    public function getPdfDataAttribute()
    {
        return $this->payroll_base64;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
