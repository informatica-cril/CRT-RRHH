<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Regles que depenen de la FILA (qui és el titular), no només del rol.
        \App\Models\Payroll::class => \App\Policies\PayrollPolicy::class,
        \App\Models\DisciplinaryDocument::class => \App\Policies\DisciplinaryDocumentPolicy::class,
        \App\Models\DisciplinaryCase::class => \App\Policies\DisciplinaryCasePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
