<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Política de segon factor. UNA sola fila: és una decisió d'empresa, no un ajust per usuari.
 * Vegeu la migració per al perquè de cada mode.
 */
class SecondFactorSetting extends Model
{
    protected $fillable = ['mode', 'roles', 'changed_by', 'changed_at'];
    protected $casts = ['roles' => 'array', 'changed_at' => 'datetime'];

    public const OFF = 'off';
    public const ROLES = 'roles';
    public const ALL = 'all';

    /** La fila viva, o null si la taula encara no existeix (desplegament a mig fer). */
    public static function actual(): ?self
    {
        try { return static::query()->orderBy('id')->first(); }
        catch (\Throwable $e) { return null; }
    }
}
