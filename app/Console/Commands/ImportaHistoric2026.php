<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Importa l'històric 2026 de l'app antiga de CRT (portalempleado_c: persones, contrasenyes, nòmines
 * i fitxatges) i posa al dia qui és actiu segons la llista de personal. Mateixa comanda que a CRIL;
 * aquí les persones noves són de CRT per defecte i es poden crear ja inactives («actiu»: false).
 *
 * El paquet (JSON.gz) es genera fora del repositori a partir dels bolcats: porta dades personals
 * i no es versiona mai. Aquí només hi ha la lògica d'import, que RESPECTA el que ja hi ha a RRHH:
 *
 *  - Persones: s'identifiquen pel DNI (o pel correu, si no en tenen). De les que ja existeixen no es
 *    toca la fitxa, només l'estat actiu/inactiu i l'entitat. Es creen les del bloc usuaris_nous, amb el
 *    hash bcrypt del portal tal qual; qui no en té rep una contrasenya aleatòria i l'ha de canviar.
 *  - Actiu/inactiu: la llista de personal actiu mana. Qui és a RRHH i no hi surt queda inactiu (no pot
 *    entrar però conserva tot el seu històric). Els comptes d'administració i de servei no es toquen.
 *  - Nòmines: s'afegeixen totes, també les duplicades (decisió de RRHH). Les que ja tenia RRHH es queden.
 *  - Fitxatges: s'afegeixen tots. Si RRHH ja té un fitxatge d'aquella persona aquell dia (d'abans de
 *    l'import), el dia es queda com està. Els que no tenen sortida o duren més de 14 h entren pendents
 *    de revisió, amb una nota al seu historial.
 *
 * created_at és el moment de l'import, no la data del fitxatge: així el llibre d'integritat els
 * segella el dia que van entrar de debò i no es toca cap segell passat. La data i les hores reals
 * van a date/start_time/end_time (hora de Madrid directa, el conveni actual).
 *
 * Per defecte és un DRY-RUN: ho fa tot dins d'una transacció i la desfà. Cal --apply per escriure.
 * Un mateix paquet no es pot aplicar dues vegades (duplicaria nòmines i fitxatges).
 */
class ImportaHistoric2026 extends Command
{
    protected $signature = 'migracio:importa-historic-2026
        {paquet : Ruta del fitxer migracio_2026.json.gz}
        {--apply : Escriu de debò (sense això, només simula i desfà)}
        {--llargs=pendent : Fitxatges de més de 14 h: pendent (amb la sortida original, per revisar) | aprovat | sense-sortida}';
    protected $description = "Importa l'històric 2026 (nòmines i fitxatges) i actualitza qui és actiu";

    private const ACCIO = 'MIGRACIO_HISTORIC_2026';
    private const MINUTS_LLARG = 14 * 60;

    private array $inf = [];

    public function handle(): int
    {
        $llargs = $this->option('llargs');
        if (! in_array($llargs, ['pendent', 'aprovat', 'sense-sortida'], true)) {
            $this->error('--llargs ha de ser pendent, aprovat o sense-sortida.');
            return self::FAILURE;
        }

        $ruta = $this->argument('paquet');
        if (! is_file($ruta)) {
            $this->error("No trobo el paquet: {$ruta}");
            return self::FAILURE;
        }
        $raw = gzdecode((string) file_get_contents($ruta));
        $paq = $raw === false ? null : json_decode($raw, true);
        if (! is_array($paq) || ($paq['versio'] ?? null) !== 1) {
            $this->error('El paquet no és vàlid (cal un JSON.gz de versió 1).');
            return self::FAILURE;
        }
        $hash = hash('sha256', $raw);

        if (AuditLog::where('action', self::ACCIO)->where('description', 'like', "%{$hash}%")->exists()) {
            $this->error('Aquest paquet ja es va aplicar. Tornar-lo a aplicar duplicaria nòmines i fitxatges.');
            return self::FAILURE;
        }

        // work_log_modifications.user_id és obligatori: les notes de la migració van a nom del primer
        // compte d'administració.
        $autor = (int) DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
        if (! $autor) {
            $this->error("No hi ha cap compte d'administració a qui atribuir les notes de la migració.");
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        if ($apply) {
            $this->error('MODE APLICAR: s\'escriuran dades reals a la BD.');
            if (! $this->confirm('Has fet un backup de la base de dades i vols continuar?', false)) {
                $this->info('Cancel·lat.');
                return self::SUCCESS;
            }
        } else {
            $this->warn("MODE SIMULACIÓ: s'executa tot i es desfà al final. Per escriure, afegeix --apply.");
        }

        $ara = now()->toDateTimeString();
        $this->inf = ['usuaris_creats' => 0, 'usuaris_omesos' => [], 'sense_contrasenya' => [], 'activats' => [],
            'desactivats' => [], 'entitats' => 0, 'nomines' => 0, 'fitxatges' => 0, 'dia_ja_a_rrhh' => 0,
            'sense_sortida' => 0, 'llargs' => 0, 'dni_sense_usuari' => []];

        DB::beginTransaction();
        try {
            $this->creaPersones($paq['usuaris_nous'] ?? [], $ara);
            $this->actualitzaEstat($paq['actius'] ?? [], $paq['entitats'] ?? [], $ara);
            $ids = $this->idsPerDni();
            $this->importaNomines($paq['nomines'] ?? [], $ids, $ara);
            $this->importaFitxatges($paq['fitxatges'] ?? [], $ids, $ara, $autor, $llargs);

            AuditLog::create([
                'user_id' => null, 'action' => self::ACCIO, 'entity_type' => 'migracio', 'entity_id' => null,
                'description' => mb_substr("Import de l'històric 2026 (paquet {$hash}): {$this->inf['usuaris_creats']} persones noves, "
                    . count($this->inf['desactivats']) . " desactivades, {$this->inf['nomines']} nòmines, "
                    . "{$this->inf['fitxatges']} fitxatges (llargs: {$llargs})", 0, 1000),
                'ip_address' => null, 'user_agent' => 'artisan',
            ]);

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage() . ' — no s\'ha escrit res.');
            return self::FAILURE;
        }

        $this->informe($llargs, $apply);

        return self::SUCCESS;
    }

    private function creaPersones(array $nous, string $ara): void
    {
        foreach ($nous as $u) {
            $dni = ! empty($u['dni']) ? strtoupper(trim($u['dni'])) : null;
            $email = strtolower(trim($u['email']));
            if ($dni && DB::table('users')->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])->exists()) {
                $this->inf['usuaris_omesos'][] = "{$u['name']}: ja existeix (es respecta la fitxa de RRHH)";
                continue;
            }
            if (DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
                $this->inf['usuaris_omesos'][] = "{$u['name']}: el correu {$email} ja és a RRHH (es respecta la fitxa)";
                continue;
            }

            // Sense contrasenya del portal: una d'aleatòria que ningú coneix i canvi obligat. RRHH li
            // n'haurà de generar una de temporal des de la fitxa perquè pugui entrar.
            $teHash = ! empty($u['password_hash']);
            if (! $teHash) {
                $this->inf['sense_contrasenya'][] = "{$u['name']} <{$email}>";
            }
            // Inserció directa: el cast 'hashed' del model tornaria a fer hash del bcrypt del portal.
            $fila = [
                'name' => $u['name'], 'email' => $email,
                'password' => $teHash ? $u['password_hash'] : Hash::make(Str::random(40)),
                'dni' => $dni, 'role' => 'worker', 'work_type' => $u['work_type'] ?? 'AMBULATORIA',
                'job_profile' => $u['job_profile'] ?? null, 'entitat' => $u['entitat'] ?? 'CRT',
                'active' => (bool) ($u['actiu'] ?? true), 'must_change_password' => ! $teHash, 'created_at' => $ara, 'updated_at' => $ara,
            ];
            // Perfil desconegut: no s'envia i la BD hi posa el seu per defecte (la columna no admet NULL).
            if ($fila['job_profile'] === null) {
                unset($fila['job_profile']);
            }
            DB::table('users')->insert($fila);
            $this->inf['usuaris_creats']++;
        }
    }

    /** La llista de personal actiu mana sobre users.active; l'entitat només es toca si el paquet la diu. */
    private function actualitzaEstat(array $actius, array $entitats, string $ara): void
    {
        $troba = function ($r, array $llista) {
            $dni = strtoupper(trim((string) $r->dni));
            $email = strtolower(trim((string) $r->email));
            foreach ($llista as $a) {
                if ((! empty($a['dni']) && $dni !== '' && strtoupper(trim($a['dni'])) === $dni)
                    || (! empty($a['email']) && strtolower(trim($a['email'])) === $email)) {
                    return $a;
                }
            }
            return null;
        };

        $persones = DB::table('users')->whereNotIn('role', ['admin', 'service'])
            ->get(['id', 'name', 'dni', 'email', 'active', 'entitat']);
        foreach ($persones as $r) {
            $actiu = $troba($r, $actius) !== null;
            if ($actiu && ! $r->active) {
                DB::table('users')->where('id', $r->id)->update(['active' => true, 'updated_at' => $ara]);
                $this->inf['activats'][] = $r->name;
            } elseif (! $actiu && $r->active) {
                DB::table('users')->where('id', $r->id)->update(['active' => false, 'updated_at' => $ara]);
                $this->inf['desactivats'][] = $r->name;
            }
            $ent = $troba($r, $entitats);
            if ($ent && $r->entitat !== $ent['entitat']) {
                DB::table('users')->where('id', $r->id)->update(['entitat' => $ent['entitat'], 'updated_at' => $ara]);
                $this->inf['entitats']++;
            }
        }
    }

    private function idsPerDni(): array
    {
        return DB::table('users')->whereNotNull('dni')->get(['id', 'dni'])
            ->mapWithKeys(fn ($r) => [strtoupper(trim($r->dni)) => $r->id])->all();
    }

    private function idDe(array $ids, string $dni): ?int
    {
        $id = $ids[strtoupper(trim($dni))] ?? null;
        if (! $id) {
            $this->inf['dni_sense_usuari'][$dni] = true;
        }
        return $id;
    }

    private function importaNomines(array $nomines, array $ids, string $ara): void
    {
        // A CRT les nòmines del portal antic ja es van importar (crt:importa-portal): no es tornen a
        // afegir. Mateixa clau que aquell import: persona + any + mes + nom del fitxer.
        $jaHi = [];
        DB::table('payrolls')->select(['user_id', 'year', 'month', 'file_name'])->orderBy('id')
            ->each(function ($p) use (&$jaHi) {
                $jaHi[$p->user_id . '|' . (int) $p->year . '|' . (int) $p->month . '|' . trim((string) $p->file_name)] = true;
            }, 2000);
        $this->inf['nomines_ja'] = 0;

        $bloc = [];
        foreach ($nomines as $n) {
            if (! $uid = $this->idDe($ids, $n['dni'])) {
                continue;
            }
            $fitxer = trim($n['file_name']) . '.pdf';
            $clau = $uid . '|' . (int) $n['any'] . '|' . (int) $n['mes'] . '|' . $fitxer;
            if (isset($jaHi[$clau])) {
                $this->inf['nomines_ja']++;
                continue;
            }
            $jaHi[$clau] = true;
            $bloc[] = [
                'user_id' => $uid, 'title' => sprintf('Nòmina %04d-%02d', $n['any'], $n['mes']),
                'month' => (string) $n['mes'], 'year' => $n['any'], 'amount' => null,
                'payroll_base64' => $n['pdf_base64'], 'file_name' => $fitxer,
                'created_at' => $ara, 'updated_at' => $ara,
            ];
            if (count($bloc) >= 50) {
                DB::table('payrolls')->insert($bloc);
                $this->inf['nomines'] += count($bloc);
                $bloc = [];
            }
        }
        if ($bloc) {
            DB::table('payrolls')->insert($bloc);
            $this->inf['nomines'] += count($bloc);
        }
    }

    private function importaFitxatges(array $fitxatges, array $ids, string $ara, int $autor, string $llargs): void
    {
        // Dies que RRHH ja tenia ABANS de l'import: només aquests bloquegen (els torns partits de
        // l'app antiga del mateix dia entren tots).
        $jaTenia = [];
        DB::table('work_logs')->whereYear('date', 2026)->select(['id', 'user_id', 'date'])->orderBy('id')
            ->each(function ($w) use (&$jaTenia) {
                $jaTenia[$w->user_id . '|' . substr((string) $w->date, 0, 10)] = true;
            }, 2000);

        $plans = [];
        foreach ($fitxatges as $f) {
            if (! $uid = $this->idDe($ids, $f['dni'])) {
                continue;
            }
            if (isset($jaTenia[$uid . '|' . $f['date']])) {
                $this->inf['dia_ja_a_rrhh']++;
                continue;
            }

            $fi = $f['end'];
            $minuts = $fi ? Carbon::parse($f['start'])->diffInMinutes(Carbon::parse($fi)) : null;
            $estat = 'approved';
            $nota = null;

            if (! $fi) {
                $estat = 'pending';
                $nota = "Importat de l'app antiga sense hora de sortida: cal completar-lo.";
                $this->inf['sense_sortida']++;
            } elseif ($minuts > self::MINUTS_LLARG) {
                $this->inf['llargs']++;
                $hores = round($minuts / 60, 1);
                if ($llargs === 'sense-sortida') {
                    $estat = 'pending';
                    $nota = "Importat de l'app antiga: la sortida original ({$fi}, {$hores} h) no és creïble i s'ha deixat buida.";
                    $fi = null;
                    $minuts = null;
                } elseif ($llargs === 'pendent') {
                    $estat = 'pending';
                    $nota = "Importat de l'app antiga amb una durada de {$hores} h: probablement no es va fitxar la sortida a temps. Hores a 0 fins que es revisi.";
                } else {
                    $nota = "Importat de l'app antiga amb una durada de {$hores} h, tal com constava (hores a 0 fins que es revisi).";
                }
            }

            // Un fitxatge de més de 14 h no són hores treballades: es conserven l'entrada i la sortida
            // originals, però les hores queden a 0 fins que RRHH el revisi (n'hi ha de mesos sencers, que
            // a més no caben a la columna). La durada real queda a la nota.
            $hores = ($minuts !== null && $minuts <= self::MINUTS_LLARG) ? round($minuts / 60, 2) : null;
            $fila = [
                'user_id' => $uid, 'date' => $f['date'], 'start_time' => $f['start'], 'end_time' => $fi,
                'hours_worked' => $hores, 'effective_hours' => $hores, 'total_hours_worked' => $hores ?? 0,
                'hour_status' => $fi ? 'ok' : 'in_progress', 'status' => $estat,
                'verification_mode' => 'B', 'home_verification' => 'no_disponible',
                'created_at' => $ara, 'updated_at' => $ara,
            ];

            if ($nota) {
                // D'un en un per tenir l'id segur: amb fitxatges entrant alhora, els ids d'un insert
                // múltiple no són necessàriament seguits. La nota va a l'historial que RRHH veu al detall.
                $id = DB::table('work_logs')->insertGetId($fila);
                DB::table('work_log_modifications')->insert([
                    'work_log_id' => $id, 'user_id' => $autor, 'action' => 'created',
                    'comment' => $nota, 'created_at' => $ara, 'updated_at' => $ara,
                ]);
                $this->inf['fitxatges']++;
                continue;
            }

            $plans[] = $fila;
            if (count($plans) >= 500) {
                DB::table('work_logs')->insert($plans);
                $this->inf['fitxatges'] += count($plans);
                $plans = [];
            }
        }
        if ($plans) {
            DB::table('work_logs')->insert($plans);
            $this->inf['fitxatges'] += count($plans);
        }
    }

    private function informe(string $llargs, bool $apply): void
    {
        $i = $this->inf;
        $this->newLine();
        $this->table(['Concepte', 'Quantitat'], [
            ['Persones noves creades', $i['usuaris_creats']],
            ['Persones reactivades (són a la llista)', count($i['activats'])],
            ['Persones desactivades (no són a la llista)', count($i['desactivats'])],
            ['Entitat actualitzada (CRIL / CRT)', $i['entitats']],
            ['Nòmines afegides', $i['nomines']],
            ['Nòmines que ja hi eren (no es tornen a afegir)', $i['nomines_ja'] ?? 0],
            ['Fitxatges afegits', $i['fitxatges']],
            ['  · sense hora de sortida (pendents)', $i['sense_sortida']],
            ["  · de més de 14 h ({$llargs})", $i['llargs']],
            ['Fitxatges no afegits: aquell dia RRHH ja en tenia', $i['dia_ja_a_rrhh']],
        ]);
        if ($i['desactivats']) {
            $this->line('Desactivades: ' . implode(', ', $i['desactivats']));
        }
        if ($i['activats']) {
            $this->line('Reactivades: ' . implode(', ', $i['activats']));
        }
        foreach ($i['sense_contrasenya'] as $o) {
            $this->warn("Sense contrasenya del portal (cal generar-n'hi una de temporal des de RRHH): {$o}");
        }
        foreach ($i['usuaris_omesos'] as $o) {
            $this->warn("Persona no creada — {$o}");
        }
        if ($i['dni_sense_usuari']) {
            $this->warn('DNI del paquet sense persona a RRHH (les seves dades no s\'han importat): ' . implode(', ', array_keys($i['dni_sense_usuari'])));
        }
        $this->info($apply ? 'Fet. Import aplicat.' : 'Simulació acabada: no s\'ha escrit res.');
    }
}
