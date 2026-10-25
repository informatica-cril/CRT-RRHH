<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Configuració del saldo de minuts (una sola fila, com AprovacioAutomaticaSetting). Desactivat per defecte. */
class SaldoMinutsSetting extends Model
{
    protected $table = 'saldo_minuts_settings';

    protected $fillable = ['enabled', 'finestra_minuts', 'tope_minuts', 'actiu_des'];

    protected $casts = [
        'enabled' => 'boolean',
        'finestra_minuts' => 'integer',
        'tope_minuts' => 'integer',
        'actiu_des' => 'date:Y-m-d',
    ];

    private static ?self $cache = null;

    public static function getSettings(): self
    {
        return self::$cache ??= (self::first() ?? self::create(['enabled' => false, 'finestra_minuts' => 5, 'tope_minuts' => 15]));
    }

    /** Si la taula encara no existeix (servidor sense migrar), el saldo simplement no existeix. */
    public static function disponible(): bool
    {
        return Schema::hasTable('saldo_minuts_settings') && Schema::hasTable('saldo_minuts_apunts');
    }

    public static function oblida(): void
    {
        self::$cache = null;
    }
}
