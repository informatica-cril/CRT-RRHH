<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Alta del primer administrador en una instal·lació nova (producció).
 * No es pot fer amb el DatabaseSeeder: crea usuaris de prova amb contrasenya 123456.
 * La contrasenya es demana per terminal (no queda a l'historial ni a cap fitxer) i ha
 * de complir la mateixa política que la resta de l'app.
 */
class CreaAdmin extends Command
{
    protected $signature = 'crt:crea-admin {email : Correu de l\'administrador} {nom : Nom complet entre cometes}
        {--rol=admin : admin (administració) o hr (Recursos Humans)}';

    protected $description = 'Crea (o reactiva) un usuari d\'administració o de RRHH demanant la contrasenya per terminal';

    public function handle(): int
    {
        $rol = $this->option('rol');
        if (! in_array($rol, ['admin', 'hr'], true)) {
            $this->error('--rol ha de ser admin o hr.');
            return self::FAILURE;
        }
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Correu no vàlid: {$email}");
            return self::FAILURE;
        }

        $password = $this->secret('Contrasenya (mín. ' . PasswordPolicy::MIN . ' caràcters, majúscula, minúscula, xifra i símbol)');
        if (! $password || ! PasswordPolicy::compleix($password)) {
            $this->error('La contrasenya no compleix la política.');
            return self::FAILURE;
        }
        if ($password !== $this->secret('Repeteix la contrasenya')) {
            $this->error('Les contrasenyes no coincideixen.');
            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], [
            'name' => $this->argument('nom'),
            'password' => Hash::make($password),
            'role' => $rol,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->info(($user->wasRecentlyCreated ? 'Creat' : 'Actualitzat') . ' ' . ($rol === 'hr' ? "l'usuari de RRHH" : "l'administrador")
            . " {$user->name} <{$user->email}> (id {$user->id}).");

        return self::SUCCESS;
    }
}
