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
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'in:admin,coordinator,worker,hr',
            'work_type' => 'nullable|string|in:DOMICILIARIA,DOMICILIARIA_VALLES,AMBULATORIA',
            'job_profile' => 'nullable|string|max:255',
            // La columna és varchar(20): sense límit, MySQL tallava el desat amb un 500.
            'dni' => 'nullable|string|max:20',
            'device_phone' => 'nullable|string|max:20',
            'pacte_complementaries' => 'boolean',
            // Tipus de relació. OBJECTIU: required a l'alta ("la pantalla d'alta sí que
            // l'exigeix", PROPOSTA-COLABORADOR-AUTONOM §2) — però el formulari actual
            // encara no envia el camp i posar-lo required trencaria TOTES les altes.
            // nullable fins que el frontend l'incorpori; NULL es tracta com a laboral.
            'relacio' => 'nullable|in:laboral,autonom',
            'entitat' => 'nullable|in:CRIL,CRT,CRIL_CRT',
            'disponibilitat_setmanal' => 'nullable|numeric|min:0|max:99',
            // % de retribució: només informatiu (la jornada va per l'historial de /users/{id}/jornada).
            'retribucio_pct' => 'nullable|numeric|min:0|max:100',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'active' => 'boolean',
            'seniority_date' => 'nullable|date',
            'onboarding_profile_id' => 'nullable|integer',
        ];
    }

    /**
     * Missatges en català: no hi ha fitxers de traducció i es veia el codi («validation.unique»).
     * El correu duplicat sovint és un compte de gestió (RRHH, administració), que no surt a la
     * llista de Treballadors.
     */
    public function messages(): array
    {
        return self::missatges();
    }

    public static function missatges(): array
    {
        return [
            'email.unique' => 'Ja hi ha un compte amb aquest correu. Pot ser una persona inactiva o un compte de gestió (RRHH, administració), que no surten a la llista de Treballadors. Fes servir un altre correu o parla amb administració.',
            'email.required' => 'Cal el correu electrònic.',
            'email.email' => 'El correu electrònic no és vàlid.',
            'name.required' => 'Cal el nom.',
            'password.required' => 'Cal una contrasenya.',
            'password.min' => 'La contrasenya ha de tenir com a mínim :min caràcters.',
            'role.in' => 'El rol no és vàlid.',
            'work_type.in' => 'L\'àmbit no és vàlid.',
            'entitat.in' => 'L\'entitat no és vàlida (CRIL, CRT o totes dues).',
            'work_schedule_id.exists' => 'L\'horari triat no existeix.',
            'seniority_date.date' => 'La data d\'alta no és vàlida.',
            'dni.max' => 'El DNI / NIE no pot tenir més de :max caràcters.',
            'device_phone.max' => 'El telèfon no pot tenir més de :max caràcters.',
            'name.max' => 'El nom no pot tenir més de :max caràcters.',
            'email.max' => 'El correu electrònic no pot tenir més de :max caràcters.',
            'job_profile.max' => 'El lloc de treball no pot tenir més de :max caràcters.',
            'postal_code_assigned.max' => 'El codi postal no pot tenir més de :max caràcters.',
            'retribucio_pct.numeric' => 'El percentatge de retribució ha de ser un número.',
            'retribucio_pct.min' => 'El percentatge de retribució no pot ser negatiu.',
            'retribucio_pct.max' => 'El percentatge de retribució no pot passar del 100%.',
        ];
    }
}
