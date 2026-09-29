<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkLogRequest extends FormRequest
{
    /** Autenticació al middleware; el bloqueig de dispositiu corporatiu queda al controlador. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_time' => 'nullable|date',
            'end_location_lat' => 'nullable|numeric',
            'end_location_lng' => 'nullable|numeric',
            'total_hours_worked' => 'nullable|numeric|min:0',
            'authorized_extra_code' => 'nullable|string',
            'extra_hours_authorized' => 'nullable|numeric|min:0',
            'extra_hours_unauthorized' => 'nullable|numeric|min:0',
            'status' => 'in:pending,approved,rejected',
            'rejection_reason' => 'nullable|string',
            'end_location_match' => 'nullable|boolean',
            'end_location_distance' => 'nullable|numeric',
            'location_match' => 'nullable|boolean',
            'hour_status' => 'nullable|in:in_progress,ok,extra,out_of_area',
            'hours_worked' => 'nullable|numeric|min:0',
            'hours_out_of_area' => 'nullable|numeric|min:0',
            'justificacio' => 'nullable|string|min:15|max:500',
            'disp' => 'sometimes|nullable|string',
            'gps_error' => 'sometimes|nullable|in:denied,unavailable',
        ];
    }
}
