<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BibliotecaCarpeta;
use App\Models\BibliotecaFitxer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documentació: tothom veu i descarrega totes les carpetes; RRHH i Superior les gestionen.
 * Es pot pujar fitxer a fitxer o importar un ZIP (cada carpeta del ZIP = una carpeta de l'app).
 */
class BibliotecaController extends Controller
{
    /** Extensions acceptades: PDF, Office (i equivalents d'OpenOffice) i imatges. */
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'jpg', 'jpeg', 'png'];
    private const MAX_FITXER_KB = 30720;          // 30 MB per fitxer
    private const MAX_ZIP_DESCOMPRIMIT = 500_000_000;   // 500 MB en total, contra ZIPs inflats

    private const MIMES = [
        'pdf' => 'application/pdf', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    ];

    public function index()
    {
        return response()->json(BibliotecaCarpeta::with('fitxers')->orderBy('ordre')->orderBy('nom')->get());
    }

    public function descarrega(BibliotecaFitxer $fitxer)
    {
        abort_unless(Storage::disk('local')->exists($fitxer->path), 404, 'El fitxer no es troba.');

        return Storage::disk('local')->response($fitxer->path, $fitxer->nom, ['Content-Type' => $fitxer->mime ?: 'application/octet-stream']);
    }

    public function creaCarpeta(Request $request)
    {
        $data = $request->validate(['nom' => 'required|string|max:150']);

        return response()->json(BibliotecaCarpeta::create(['nom' => trim($data['nom']),
            'ordre' => (int) BibliotecaCarpeta::max('ordre') + 1])->load('fitxers'), 201);
    }

    public function reanomenaCarpeta(Request $request, BibliotecaCarpeta $carpeta)
    {
        $data = $request->validate(['nom' => 'required|string|max:150']);
        $carpeta->update(['nom' => trim($data['nom'])]);

        return response()->json($carpeta->load('fitxers'));
    }

    public function esborraCarpeta(BibliotecaCarpeta $carpeta)
    {
        foreach ($carpeta->fitxers as $f) Storage::disk('local')->delete($f->path);
        $carpeta->delete();

        return response()->json(['ok' => true]);
    }

    public function pujaFitxers(Request $request, BibliotecaCarpeta $carpeta)
    {
        $request->validate([
            'fitxers' => 'required|array|min:1|max:50',
            'fitxers.*' => 'file|max:' . self::MAX_FITXER_KB . '|mimes:' . implode(',', self::EXTENSIONS),
        ]);
        foreach ($request->file('fitxers') as $f) {
            $this->desa($carpeta, $f->getClientOriginalName(), fn ($path) => Storage::disk('local')->putFileAs(dirname($path), $f, basename($path)),
                $f->getSize(), $request->user()->id);
        }

        return response()->json($carpeta->load('fitxers'));
    }

    public function esborraFitxer(BibliotecaFitxer $fitxer)
    {
        Storage::disk('local')->delete($fitxer->path);
        $fitxer->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Importa un ZIP: cada fitxer va a la carpeta que el conté directament (si no n'hi ha cap, a «General»).
     * Les carpetes que ja existeixen (pel nom) es reaprofiten i un fitxer amb el mateix nom no es duplica.
     * Es llegeix entrada a entrada sense descomprimir al disc (cap ruta del ZIP arriba al sistema de fitxers).
     */
    public function importaZip(Request $request)
    {
        $request->validate(['zip' => 'required|file|mimes:zip|max:204800']);   // 200 MB
        abort_unless(class_exists(\ZipArchive::class), 500, "El servidor no té l'extensió ZIP de PHP: puja els fitxers carpeta a carpeta.");

        $zip = new \ZipArchive();
        abort_unless($zip->open($request->file('zip')->getRealPath()) === true, 422, "No s'ha pogut obrir el ZIP.");

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) $total += (int) ($zip->statIndex($i)['size'] ?? 0);
        if ($total > self::MAX_ZIP_DESCOMPRIMIT) {
            $zip->close();
            abort(422, 'El ZIP descomprimit fa més de 500 MB. Divideix-lo en diversos ZIP.');
        }

        $creats = 0;
        $omesos = [];
        $carpetes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = $this->nomUtf8((string) $zip->getNameIndex($i, \ZipArchive::FL_ENC_RAW));
            $nom = str_replace('\\', '/', $nom);
            if (str_ends_with($nom, '/') || str_contains($nom, '__MACOSX/')) continue;
            $parts = array_values(array_filter(explode('/', $nom), fn ($p) => $p !== '' && $p !== '.' && $p !== '..'));
            $fitxer = array_pop($parts);
            if ($fitxer === null || str_starts_with($fitxer, '.') || str_starts_with($fitxer, '~$')) continue;

            $ext = strtolower(pathinfo($fitxer, PATHINFO_EXTENSION));
            $mida = (int) ($zip->statIndex($i)['size'] ?? 0);
            if (! in_array($ext, self::EXTENSIONS, true)) { $omesos[] = "{$fitxer} (tipus no acceptat)"; continue; }
            if ($mida > self::MAX_FITXER_KB * 1024) { $omesos[] = "{$fitxer} (més de 30 MB)"; continue; }

            $nomCarpeta = trim((string) end($parts)) ?: 'General';
            $carpeta = $carpetes[$nomCarpeta] ??= BibliotecaCarpeta::firstOrCreate(['nom' => $nomCarpeta],
                ['ordre' => (int) BibliotecaCarpeta::max('ordre') + 1]);
            if ($carpeta->fitxers()->where('nom', $fitxer)->exists()) { $omesos[] = "{$fitxer} (ja hi era)"; continue; }

            $contingut = $zip->getFromIndex($i);
            if ($contingut === false) { $omesos[] = "{$fitxer} (no s'ha pogut llegir)"; continue; }
            $this->desa($carpeta, $fitxer, fn ($path) => Storage::disk('local')->put($path, $contingut), strlen($contingut), $request->user()->id);
            $creats++;
        }
        $zip->close();

        return response()->json([
            'fitxers' => $creats, 'carpetes' => count($carpetes), 'omesos' => $omesos,
            'biblioteca' => BibliotecaCarpeta::with('fitxers')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }

    /** Desa el fitxer amb un nom intern aleatori (el nom original només és per mostrar-lo). */
    private function desa(BibliotecaCarpeta $carpeta, string $nom, callable $escriu, int $mida, ?int $autor): void
    {
        $nom = trim(Str::limit(basename(str_replace('\\', '/', $nom)), 250, ''));
        $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
        $path = 'biblioteca/' . $carpeta->id . '/' . Str::uuid() . ($ext ? ".{$ext}" : '');
        $escriu($path);
        BibliotecaFitxer::create(['carpeta_id' => $carpeta->id, 'nom' => $nom, 'path' => $path,
            'mime' => self::MIMES[$ext] ?? 'application/octet-stream', 'mida' => $mida, 'pujat_per' => $autor]);
    }

    /** Els ZIP fets amb Windows sovint guarden els noms en CP850 (les vocals amb accent sortien trencades). */
    private function nomUtf8(string $nom): string
    {
        return mb_check_encoding($nom, 'UTF-8') ? $nom : mb_convert_encoding($nom, 'UTF-8', 'CP850');
    }
}
