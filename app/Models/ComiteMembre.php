<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComiteMembre extends Model
{
    protected $table = 'comite_membres';

    protected $fillable = [
        'user_id', 'tipus_representacio', 'carrec', 'sindicat',
        'data_alta', 'data_baixa', 'credit_hores_mensual', 'notes', 'creat_per',
    ];

    protected $casts = [
        'data_alta'            => 'date:Y-m-d',
        'data_baixa'           => 'date:Y-m-d',
        'credit_hores_mensual' => 'decimal:2',
    ];

    public const TIPUS = [
        'comite'           => "Comitè d'empresa",
        'delegat_personal' => 'Delegat/da de personal',
        'delegat_sindical' => 'Delegat/da sindical',
    ];

    public const CARRECS = [
        'president' => 'President/a',
        'secretari' => 'Secretari/ària',
        'vocal'     => 'Vocal',
        'delegat'   => 'Delegat/da',
    ];

    /**
     * Escala de l'art. 68.e ET: hores al mes segons la plantilla del centre. És el mínim legal;
     * el conveni la pot millorar, per això al membre es desa el valor i això només el proposa.
     */
    public static function creditLegal(int $plantilla): int
    {
        return match (true) {
            $plantilla <= 100 => 15,
            $plantilla <= 250 => 20,
            $plantilla <= 500 => 30,
            $plantilla <= 750 => 35,
            default           => 40,
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hores()
    {
        return $this->hasMany(ComiteHora::class, 'membre_id');
    }

    /** Mandat que cobreix la data donada. */
    public function scopeVigentEl($q, string $data)
    {
        return $q->where('data_alta', '<=', $data)
            ->where(fn ($w) => $w->whereNull('data_baixa')->orWhere('data_baixa', '>=', $data));
    }
}
