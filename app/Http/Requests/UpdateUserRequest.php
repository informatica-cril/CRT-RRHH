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
            'email' => 'email|unique:users,email,' . $this->route('user')->id,
            'password' => 'nullable|string|min:6',
            'role' => 'in:admin,coordinator,worker,hr',
            'work_type' => 'nullable|string|in:DOMICILIARIA,DOMICILIARIA_VALLES,AMBULATORIA',
            'job_profile' => 'nullable|string',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'active' => 'boolean',
            'privacy_consent' => 'boolean',
            'seniority_date' => 'nullable|date',
            'onboarding_profile_id' => 'nullable|integer',
            'onboarding_completed' => 'boolean',
            'must_change_password' => 'boolean',
            'dni' => 'nullable|string',
            'device_phone' => 'nullable|string|max:20',
            'pacte_complementaries' => 'boolean',
            'relacio' => 'nullable|in:laboral,autonom',
            'disponibilitat_setmanal' => 'nullable|numeric|min:0|max:99',
            'specialties' => 'sometimes|array',
            'specialties.*' => 'string|exists:specialties,code',
        ];
    }
}
