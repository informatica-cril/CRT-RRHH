<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un canvi del % de jornada del contracte, vigent des de `valid_from` fins al següent. */
class JornadaContracte extends Model
{
    protected $fillable = ['user_id', 'pct', 'valid_from', 'motiu', 'created_by'];

    protected $casts = [
        'pct' => 'float',
        'valid_from' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
