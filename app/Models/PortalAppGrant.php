<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalAppGrant extends Model
{
    protected $fillable = ['user_id', 'app', 'granted_by'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
