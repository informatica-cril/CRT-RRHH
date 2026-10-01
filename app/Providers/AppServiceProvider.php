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
        // Totes les dates de l'app (start_time, end_time, break_*, created_at,
        // updated_at...) es guarden en hora de Madrid directa, MAI UTC real (veure
        // WorkLogController). La serialització per defecte de Carbon hi afegeix una
        // 'Z' com si fossin UTC real sense convertir-les, i el navegador se la creu
        // i suma 1-2h de més en mostrar-les. Traient la 'Z' (sense marca de zona), el
        // navegador interpreta el valor tal qual, en hora local, que ja és el correcte.
        // ÚNICA excepció: qualsevol lloc que faci servir ->toISOString()/->toJSON()
        // explícitament sobre un Carbon ja convertit a UTC real (p.ex. 'server_time' a
        // WorkLogController) NO passa per aquí — segueix sortint en UTC real, correcte.
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
