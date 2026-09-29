<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DomiScopes;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DomiServiceAccount extends Command
{
    protected $signature = 'domi:service-account
        {--email=servei.domi@crtbcn.cat : Email del compte de servei}
        {--rotate : Emet un bitllet nou (el vell segueix viu fins que --revoca-antics)}
        {--revoca-antics : Revoca tots els bitllets del compte menys el més nou}
        {--estat : Només informa: bitllets vius, permisos i dies que els queden}';

    protected $description = 'Compte de servei de la app domi: emissió, rotació i estat del bitllet acotat.';

    /** Vida del bitllet i finestra d'avís abans de caducar. */
    public const VIDA_DIES  = 180;
    public const AVIS_DIES  = 30;

    public function handle(): int
    {
        $email = $this->option('email');

        if ($this->option('estat')) {
            return $this->estat($email);
        }

        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        if ($isNew) {
            $user->name = 'Servei Domi (API)';
            $user->password = Hash::make(Str::random(40)); // no s'usa: mai fa login amb contrasenya
            $user->privacy_consent = true;
        }
        // Rol de servei dedicat (bulk-index + /domi/*); actiu perquè auth:sanctum el deixi passar
        $user->role = 'service';
        $user->active = true;
        $user->save();

        $this->info(($isNew ? '✅ Compte de servei CREAT: ' : 'ℹ️  Compte de servei ja existent: ') . $email . " (id={$user->id}, role=service)");

        if ($this->option('revoca-antics')) {
            $viu = $user->tokens()->orderByDesc('created_at')->first();
            $n = $viu ? $user->tokens()->where('id', '!=', $viu->id)->delete() : 0;
            $this->warn("Bitllets antics revocats: $n (es conserva el #{$viu?->id}).");
            if (! $this->option('rotate')) {
                return self::SUCCESS;
            }
        }

        $existing = $user->tokens()->count();

        if ($existing > 0 && ! $this->option('rotate')) {
            $this->warn("Ja té $existing bitllet(s). No en genero un de nou (usa --rotate).");
            $this->line("Consulta l'estat amb: php artisan domi:service-account --estat");
            return self::SUCCESS;
        }

        /* SOLAPAMENT: el bitllet nou s'emet SENSE esborrar el vell. Si el revoquéssim
           aquí, el pont quedaria mort entre l'emissió i el moment que informàtica
           enganxa el bitllet nou a includes/rrhh_secret.php de domi. Un cop domi ja
           l'usa (es veu a "últim ús" de --estat), es tanca amb --revoca-antics. */
        $caduca = Carbon::now()->addDays(self::VIDA_DIES);
        $token = $user->createToken('domi-sync', DomiScopes::permisos(), $caduca)->plainTextToken;

        $this->newLine();
        $this->info('🔑 BITLLET SANCTUM (guarda\'l a la config blindada de domi — no es tornarà a mostrar):');
        $this->line("<fg=yellow>$token</>");
        $this->newLine();
        $this->line('Permisos: ' . implode(' ', DomiScopes::permisos()));
        $this->line('Caduca:   ' . $caduca->toDateTimeString() . ' (' . self::VIDA_DIES . ' dies)');
        $this->newLine();
        $this->line('Renovació SENSE tallar el pont:');
        $this->line('  1. php artisan domi:service-account --rotate      (emet el nou; el vell segueix viu)');
        $this->line('  2. informàtica enganxa RRHH_TOKEN a domi:includes/rrhh_secret.php');
        $this->line('  3. php artisan domi:service-account --estat       (comprova que el nou ja té últim ús)');
        $this->line('  4. php artisan domi:service-account --revoca-antics');

        return self::SUCCESS;
    }

    /** Estat dels bitllets vius. Retorna FAILURE si algun exigeix acció, perquè el cron ho vegi. */
    private function estat(string $email): int
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No hi ha cap compte de servei amb email $email.");
            return self::FAILURE;
        }

        $tokens = $user->tokens()->orderBy('id')->get();
        if ($tokens->isEmpty()) {
            $this->error('⛔ El compte de servei no té cap bitllet: el pont amb domi està MORT.');
            return self::FAILURE;
        }

        $cal = false;
        $files = [];
        foreach ($tokens as $t) {
            $dies = $t->expires_at ? (int) Carbon::now()->diffInDays($t->expires_at, false) : null;
            if ($dies === null) {
                $estat = '⚠️  SENSE CADUCITAT'; $cal = true;
            } elseif ($dies < 0) {
                $estat = '⛔ CADUCAT'; $cal = true;
            } elseif ($dies <= self::AVIS_DIES) {
                $estat = "⚠️  caduca en $dies dies"; $cal = true;
            } else {
                $estat = "✅ $dies dies";
            }
            $permisos = (array) $t->abilities;
            if (in_array('*', $permisos, true)) { $estat .= ' · ⚠️ PERMISOS ["*"]'; $cal = true; }

            $files[] = [$t->id, $t->name, implode(' ', $permisos),
                        $t->last_used_at?->toDateTimeString() ?? 'mai',
                        $t->expires_at?->toDateTimeString() ?? '—', $estat];
        }

        $this->table(['#', 'nom', 'permisos', 'últim ús', 'caduca', 'estat'], $files);

        if ($tokens->count() > 1) {
            $this->warn('Hi ha més d\'un bitllet viu. Si la rotació ja està tancada: --revoca-antics.');
            $cal = true;
        }
        if ($cal) {
            $this->newLine();
            $this->error('Cal acció sobre el bitllet del compte de servei de domi.');
            /* L'avís ha d'arribar a una persona, no només al codi de sortida del cron:
               queda a la traça d'auditoria, que ja té pantalla. Un cop al dia n'hi ha prou. */
            $this->avisaAuditoria($user, $files);
            return self::FAILURE;
        }

        $this->info('Bitllet del pont amb domi: correcte.');
        return self::SUCCESS;
    }

    private function avisaAuditoria(User $user, array $files): void
    {
        try {
            $ja = \App\Models\AuditLog::where('action', 'DOMI_TOKEN_AVIS')
                ->whereDate('created_at', Carbon::today())->exists();
            if ($ja) { return; }

            $resum = implode(' | ', array_map(fn ($f) => "#$f[0] $f[5]", $files));
            \App\Models\AuditLog::create([
                'user_id'     => $user->id,
                'action'      => 'DOMI_TOKEN_AVIS',
                'entity_type' => 'auth',
                'description' => 'Bitllet del compte de servei de domi: ' . mb_substr($resum, 0, 400),
            ]);
        } catch (\Throwable $e) { /* l'avís no pot tombar el cron */ }
    }
}
