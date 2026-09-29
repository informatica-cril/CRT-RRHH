<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Specialty;

class SpecialtyController extends Controller
{
    /** Catàleg d'especialitats actives (per als selectors de la fitxa del treballador). */
    public function index()
    {
        return response()->json(
            Specialty::where('active', true)->orderBy('sort')->get(['id', 'code', 'name'])
        );
    }
}
