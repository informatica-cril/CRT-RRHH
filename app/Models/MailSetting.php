<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class MailSetting extends Model
{
    protected $table = 'mail_settings';

    protected $fillable = [
        'mailer', 'host', 'port', 'encryption', 'username', 'password',
        'from_address', 'from_name', 'last_test_at', 'last_test_status',
    ];

    protected $casts = [
        'password' => 'encrypted', // xifrada a la BD
        'last_test_at' => 'datetime',
    ];

    /** Registre únic de configuració de correu. */
    public static function getSettings(): self
    {
        // Valors inicials des de config/mail.php: env() fora de config/ torna null amb config:cache.
        return self::first() ?? self::create([
            'mailer' => config('mail.default', 'smtp'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => (int) config('mail.mailers.smtp.port', 587),
            'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
            'username' => config('mail.mailers.smtp.username'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name') ?: 'CRT RRHH',
        ]);
    }

    /**
     * Aplica aquesta configuració al mailer en temps d'execució (sobreescriu config()).
     */
    public function applyToRuntime(): void
    {
        $mailer = $this->mailer ?: config('mail.default');
        Config::set('mail.default', $mailer);

        // Només sobreescriure els valors que tenen contingut; els buits mantenen la
        // config del .env (evita, p. ex., trencar l'enviament si encara no s'ha
        // desat la contrasenya des de la UI).
        if ($mailer === 'smtp') {
            if (! empty($this->host)) {
                Config::set('mail.mailers.smtp.host', $this->host);
            }
            if (! empty($this->port)) {
                Config::set('mail.mailers.smtp.port', (int) $this->port);
            }
            if (! empty($this->encryption)) {
                Config::set('mail.mailers.smtp.encryption', $this->encryption);
            }
            if (! empty($this->username)) {
                Config::set('mail.mailers.smtp.username', $this->username);
            }
            if (! empty($this->password)) {
                Config::set('mail.mailers.smtp.password', $this->password);
            }
        }

        if (! empty($this->from_address)) {
            Config::set('mail.from.address', $this->from_address);
            Config::set('mail.from.name', $this->from_name ?: config('mail.from.name'));
        }
    }
}
