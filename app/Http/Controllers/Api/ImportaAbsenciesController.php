<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImportaAbsenciesRrhh;
use App\Support\ExcelRrhhAbsencies;
use Illuminate\Http\Request;

/**
 * Importar les vacances, permisos, baixes i hores sindicals de l'Excel de RRHH.
 * Primer es previsualitza (no s'escriu res) i després s'aplica el que RRHH ha revisat.
 */
class ImportaAbsenciesController extends Controller
{
    /** POST /api/v1/absences/importa-excel/previsualitza (Excel a 'file') */
    public function previsualitza(Request $request, ImportaAbsenciesRrhh $importa)
    {
        $request->validate(['file' => 'required|file|max:51200'], [
            'file.required' => "Cal triar l'Excel de RRHH.",
            'file.max' => "L'Excel passa de 50 MB. Deseu-lo sense les files buides de la fulla «hores sindicals» i torneu-ho a provar.",
        ]);
        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        abort_unless(in_array($ext, ['xlsx', 'xlsm'], true), 422, "Ha de ser un Excel .xlsx (el format antic .xls no es pot llegir: deseu-lo com a .xlsx).");

        try {
            $files = ExcelRrhhAbsencies::llegeix($request->file('file')->getRealPath());
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
        abort_if(! $files, 422, "No s'ha trobat cap absència. Comproveu que és l'Excel de RRHH (fulles permisos, vacances, incapacitats…).");

        return response()->json($importa->previsualitza($files));
    }

    /** POST /api/v1/absences/importa-excel/aplica — els registres de la previsualització, ja revisats. */
    public function aplica(Request $request, ImportaAbsenciesRrhh $importa)
    {
        $data = $request->validate([
            'registres' => 'required|array|max:5000',
            'registres.*.clau' => 'required|string|size:40',
            'registres.*.nom' => 'required|string|max:255',
            'registres.*.absence_type_id' => 'required|integer',
            'registres.*.ini' => 'required|date_format:Y-m-d',
            'registres.*.fi' => 'required|date_format:Y-m-d|after_or_equal:registres.*.ini',
            'registres.*.estat' => 'nullable|string|max:50',
            'registres.*.hora_inici' => 'nullable|date_format:H:i',
            'registres.*.hora_fi' => 'nullable|date_format:H:i',
            'registres.*.minuts' => 'nullable|integer|min:0|max:1440',
            'registres.*.motiu' => 'nullable|string|max:255',
            'registres.*.obs' => 'nullable|string|max:2000',
            'registres.*.es_baixa' => 'boolean',
            'registres.*.user_id' => 'nullable|integer',
            'assignacions' => 'array',
            'amb_motiu_baixes' => 'boolean',
        ]);

        $fet = $importa->aplica($data['registres'], $data['assignacions'] ?? [], (bool) ($data['amb_motiu_baixes'] ?? true), $request->user());

        return response()->json($fet);
    }
}
