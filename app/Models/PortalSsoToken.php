<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalSsoToken extends Model
{
    protected $fillable = ['user_id', 'app', 'token_hash', 'expires_at', 'used_at', 'created_ip'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
