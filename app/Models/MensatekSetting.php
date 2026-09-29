<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MensatekSetting extends Model
{
    protected $table = 'mensatek_settings';

    protected $fillable = ['usuari_api', 'api_token', 'remitent', 'last_test_at', 'last_test_status'];

    protected $casts = [
        'api_token' => 'encrypted',
        'last_test_at' => 'datetime',
    ];

    /** Registre únic de configuració del canal certificat. */
    public static function getSettings(): self
    {
        return self::first() ?? self::create([]);
    }

    public function configurat(): bool
    {
        return ! empty($this->usuari_api) && ! empty($this->api_token) && ! empty($this->remitent);
    }
}
