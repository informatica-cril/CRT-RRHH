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
