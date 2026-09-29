<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /** L'autenticació i el rol es controlen al middleware de la ruta. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'in:admin,coordinator,worker,hr',
            'work_type' => 'nullable|string|in:DOMICILIARIA,DOMICILIARIA_VALLES,AMBULATORIA',
            'job_profile' => 'nullable|string',
            'dni' => 'nullable|string',
            'device_phone' => 'nullable|string|max:20',
            'pacte_complementaries' => 'boolean',
            // Tipus de relació. OBJECTIU: required a l'alta ("la pantalla d'alta sí que
            // l'exigeix", PROPOSTA-COLABORADOR-AUTONOM §2) — però el formulari actual
            // encara no envia el camp i posar-lo required trencaria TOTES les altes.
            // nullable fins que el frontend l'incorpori; NULL es tracta com a laboral.
            'relacio' => 'nullable|in:laboral,autonom',
            'disponibilitat_setmanal' => 'nullable|numeric|min:0|max:99',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'active' => 'boolean',
            'seniority_date' => 'nullable|date',
            'onboarding_profile_id' => 'nullable|integer',
        ];
    }
}
