<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una línia del llibre de minuts d'una persona: un fitxatge o un ajust de gestió. */
class SaldoMinutsApunt extends Model
{
    protected $table = 'saldo_minuts_apunts';

    protected $fillable = [
        'user_id', 'work_log_id', 'tipus', 'date', 'entrada_delta', 'sortida_delta', 'delta_aplicat',
        'excedent', 'saldo_despres', 'extra_abans', 'extra_despres', 'nota', 'decidit_per',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'extra_abans' => 'float',
        'extra_despres' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workLog()
    {
        return $this->belongsTo(WorkLog::class);
    }
}
