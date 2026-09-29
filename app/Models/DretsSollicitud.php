<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DretsSollicitud extends Model
{
    protected $table = 'drets_sollicituds';

    protected $fillable = ['user_id', 'dret', 'detall', 'presentada_ts', 'venciment', 'ip'];

    protected $casts = [
        'presentada_ts' => 'datetime',
        'resposta_ts'   => 'datetime',
        'venciment'     => 'date',
    ];

    /** Els sis drets de la política. Aquesta llista mana sobre la pantalla i sobre la validació. */
    public const DRETS = [
        'acces'        => 'Accés',
        'rectificacio' => 'Rectificació',
        'supressio'    => 'Supressió',
        'limitacio'    => 'Limitació del tractament',
        'portabilitat' => 'Portabilitat',
        'oposicio'     => 'Oposició',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'respost_per');
    }
}
