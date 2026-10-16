<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * L'autenticació va al middleware. El filtre de camps privilegiats
     * (role, active, relacio, ...) per a no-admins es fa al controlador:
     * no és un rebuig (403), és una IGNORADA silenciosa camp a camp.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'email' => 'email|max:255|unique:users,email,' . $this->route('user')->id,
            'password' => 'nullable|string|min:6',
            'role' => 'in:admin,coordinator,worker,hr',
            'work_type' => 'nullable|string|in:DOMICILIARIA,DOMICILIARIA_VALLES,AMBULATORIA',
            'job_profile' => 'nullable|string|max:255',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'active' => 'boolean',
            'privacy_consent' => 'boolean',
            'seniority_date' => 'nullable|date',
            'onboarding_profile_id' => 'nullable|integer',
            'onboarding_completed' => 'boolean',
            'must_change_password' => 'boolean',
            // La columna és varchar(20): sense límit, MySQL tallava el desat amb un 500.
            'dni' => 'nullable|string|max:20',
            // CP principal de la persona (domiciliària). No hi era i es descartava sempre en desar.
            'postal_code_assigned' => 'nullable|string|max:255',
            'device_phone' => 'nullable|string|max:20',
            'pacte_complementaries' => 'boolean',
            'relacio' => 'nullable|in:laboral,autonom',
            'entitat' => 'nullable|in:CRIL,CRT,CRIL_CRT',
            'disponibilitat_setmanal' => 'nullable|numeric|min:0|max:99',
            // % de retribució: només informatiu (la jornada va per l'historial de /users/{id}/jornada).
            'retribucio_pct' => 'nullable|numeric|min:0|max:100',
            'specialties' => 'sometimes|array',
            'specialties.*' => 'string|exists:specialties,code',
        ];
    }

    /** Els mateixos missatges en català que l'alta. */
    public function messages(): array
    {
        return StoreUserRequest::missatges();
    }
}
