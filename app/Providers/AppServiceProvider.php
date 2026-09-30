<?php

namespace App\Providers;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Les dates es guarden i es llegeixen en hora de Madrid (config('app.timezone')),
        // però la serialització per defecte de Carbon hi afegeix una 'Z' com si fossin UTC
        // real, SENSE convertir-les. El navegador es creu la 'Z' i suma 2h (horari d'estiu)
        // en mostrar-les, desquadrant totes les hores de tota l'app. Traient la 'Z' (sense
        // marca de zona), el navegador interpreta el valor tal qual, en hora local, que és
        // exactament el que ja és.
        \Illuminate\Support\Carbon::serializeUsing(fn ($date) => $date->format('Y-m-d\TH:i:s.u'));

        // Aplicar la configuració de correu desada a la BD (si existeix) sobre la config runtime,
        // de manera que tots els enviaments (notificacions, proves) la facin servir.
        try {
            if (Schema::hasTable('mail_settings')) {
                $settings = MailSetting::first();
                if ($settings) {
                    $settings->applyToRuntime();
                }
            }
        } catch (\Throwable $e) {
            // BD no disponible (p. ex. durant migracions/artisan): ignorar silenciosament.
        }
    }
}
