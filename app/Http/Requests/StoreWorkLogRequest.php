<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkLogRequest extends FormRequest
{
    /** Autenticació al middleware; el bloqueig de dispositiu corporatiu queda al controlador. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date',
            'start_location_lat' => 'nullable|numeric',
            'start_location_lng' => 'nullable|numeric',
            'end_location_lat' => 'nullable|numeric',
            'end_location_lng' => 'nullable|numeric',
            'total_hours_worked' => 'numeric|min:0',
            'authorized_extra_code' => 'nullable|string',
            'extra_hours_authorized' => 'numeric|min:0',
            'extra_hours_unauthorized' => 'numeric|min:0',
            'status' => 'in:pending,approved,rejected',
            'location_match' => 'nullable|boolean',
            'start_location_match' => 'nullable|boolean',
            'start_location_distance' => 'nullable|numeric',
            'hour_status' => 'nullable|in:in_progress,ok,extra,out_of_area',
            'hours_worked' => 'nullable|numeric|min:0',
            'hours_out_of_area' => 'nullable|numeric|min:0',
            // JUSTIFICACIÓ OBLIGATÒRIA (mín. 15 caràcters) quan es fitxa SENSE GPS.
            // via_domi: la petició ve del pont de Domiciliària, que ja ha fet complir
            // la regla coneixent l'estat real del GPS (C2: aquí no arriben coordenades).
            'via_domi' => 'sometimes|boolean',
            'disp' => 'sometimes|nullable|string',
            'gps_error' => 'sometimes|nullable|in:denied,unavailable',
            'justificacio' => 'required_without_all:start_location_lat,via_domi|string|min:15|max:500',
        ];
    }
}
