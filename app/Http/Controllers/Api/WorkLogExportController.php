<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * GET /api/v1/work-logs/export?mes=AAAA-MM[&user_id=][&vista=]  → registre_jornada_AAAA-MM.xlsx
 *
 * El mateix que es veu a la pantalla de registres (mes, persona i vista), generat al servidor: no
 * depèn de quants fitxatges té carregats el navegador. MINIMITZACIÓ (EIPD §6.3): com el llistat, mai
 * hi surten coordenades; de la ubicació, només si encaixava amb la zona i a quina distància.
 * Exportar dades personals queda a l'auditoria.
 */
class WorkLogExportController extends Controller
{
    /** Les mateixes vistes que la pantalla (WorkLogsView): si se'n canvia una, cal canviar-la als dos llocs. */
    private const VISTES = [
        'tots' => 'Tots', 'pendents' => "Pendents d'aprovar", 'forazona' => 'Fora de zona',
        'extra' => 'Hores extra no autoritzades', 'sensesortida' => 'Sense sortida',
        'llargs' => 'Més de 14 h', 'aprovats' => 'Aprovats', 'rebutjats' => 'Rebutjats',
    ];

    private const ESTATS = ['pending' => 'Pendent', 'approved' => 'Aprovat', 'rejected' => 'Rebutjat', 'modified' => 'Modificat'];
    private const INCIDENCIES = ['out_of_area' => 'Fora de zona', 'extra' => 'Hores extra', 'in_progress' => 'En curs / sense sortida'];

    public function export(Request $request)
    {
        $data = $request->validate([
            'mes'     => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'user_id' => 'nullable|integer',
            'vista'   => 'nullable|in:' . implode(',', array_keys(self::VISTES)),
        ]);
        $vista = $data['vista'] ?? 'tots';
        $ini = Carbon::createFromFormat('Y-m-d', $data['mes'] . '-01');

        $q = WorkLog::with('user:id,name,dni')
            ->whereBetween('date', [$ini->toDateString(), $ini->copy()->endOfMonth()->toDateString()])
            ->when($data['user_id'] ?? null, fn ($q, $uid) => $q->where('user_id', $uid));

        match ($vista) {
            'pendents'     => $q->where('status', 'pending'),
            'forazona'     => $q->where(fn ($w) => $w->where('hour_status', 'out_of_area')
                ->orWhere('location_match', false)->orWhere('start_location_match', false)
                ->orWhere('end_location_match', false)->orWhere('hours_out_of_area', '>', 0)),
            'extra'        => $q->where('extra_hours_unauthorized', '>', 0),
            'sensesortida' => $q->whereNull('end_time'),
            'llargs'       => $q->whereNotNull('end_time')->whereRaw('TIMESTAMPDIFF(MINUTE, start_time, end_time) > 840'),
            'aprovats'     => $q->where('status', 'approved'),
            'rebutjats'    => $q->where('status', 'rejected'),
            default        => null,
        };

        $logs = $q->orderBy('date')->orderBy('start_time')->get();

        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Registre ' . $data['mes']);

        $persona = ($data['user_id'] ?? null) ? ($logs->first()?->user?->name ?? 'persona seleccionada') : 'Tot el personal';
        $sh->setCellValue('A1', 'Registre de jornada · ' . mb_convert_case($ini->locale('ca')->translatedFormat('F Y'), MB_CASE_TITLE));
        $sh->setCellValue('A2', "{$persona} · Vista: " . self::VISTES[$vista] . ' · Generat el ' . now()->format('d/m/Y H:i')
            . ' · RDL 8/2019, art. 34.9 ET');
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        $cap = ['Treballador/a', 'DNI', 'Data', 'Entrada', 'Sortida', 'Hores treballades',
            'Extra autoritzades', 'Extra no autoritzades', 'Hores fora de zona', 'Estat', 'Incidència',
            'Dins de zona', 'Distància a la zona (m)', 'Motiu de rebuig'];
        $sh->fromArray($cap, null, 'A4');
        $sh->getStyle('A4:N4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle('A4:N4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A5C');

        $fila = 5;
        foreach ($logs as $l) {
            $ent = $l->getRawOriginal('start_time');
            $sor = $l->getRawOriginal('end_time');
            $dist = max((float) $l->start_location_distance, (float) $l->end_location_distance);
            $sh->fromArray([
                $l->user?->name ?? '—',
                $l->user?->dni ?? '',
                Carbon::parse($l->getRawOriginal('date'))->format('d/m/Y'),
                $ent ? substr($ent, 11, 5) : '',
                // Sortida en un altre dia: es diu la data perquè no sembli una jornada normal.
                $sor ? (substr($sor, 0, 10) !== substr((string) $ent, 0, 10) ? Carbon::parse($sor)->format('d/m H:i') : substr($sor, 11, 5)) : '',
                $sor ? (float) ($l->effective_hours ?? $l->total_hours_worked ?? 0) : null,
                (float) $l->extra_hours_authorized ?: null,
                (float) $l->extra_hours_unauthorized ?: null,
                (float) $l->hours_out_of_area ?: null,
                self::ESTATS[$l->status] ?? $l->status,
                self::INCIDENCIES[$l->hour_status] ?? '',
                $l->location_match === null ? '' : ($l->location_match ? 'Sí' : 'No'),
                $dist > 0 ? round($dist) : null,
                $l->rejection_reason ?? '',
            ], null, "A{$fila}");
            $fila++;
        }

        $ultima = max(5, $fila - 1);
        // Totals amb fórmula: si qui rep l'Excel filtra o corregeix files, es recalculen.
        $sh->setCellValue("A{$fila}", 'TOTAL (' . $logs->count() . ' registres)');
        foreach (['F', 'G', 'H', 'I'] as $c) {
            $sh->setCellValue("{$c}{$fila}", "=SUBTOTAL(9,{$c}5:{$c}{$ultima})");
        }
        $sh->getStyle("A{$fila}:N{$fila}")->getFont()->setBold(true);
        $sh->getStyle("A{$fila}:N{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $sh->getStyle("F5:I{$fila}")->getNumberFormat()->setFormatCode('0.00');

        $sh->setAutoFilter("A4:N{$ultima}");
        $sh->freezePane('A5');
        foreach (range('A', 'N') as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }

        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'EXPORT_WORKLOGS', 'entity_type' => 'work_log',
                'description' => "Exportació a Excel del registre de jornada {$data['mes']} ({$persona}, vista "
                    . self::VISTES[$vista] . ', ' . $logs->count() . ' registres)',
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, "registre_jornada_{$data['mes']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
