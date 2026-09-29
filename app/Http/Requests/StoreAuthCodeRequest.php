<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuthCodeRequest extends FormRequest
{
    /**
     * Autenticació al middleware. Les validacions de NEGOCI (franja diürna
     * 06:00–22:00 i tope anual del 30% de complementàries, art. 12.5 ET)
     * queden al controlador: depenen de l'estat de la BD, no del format.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|unique:authorization_codes',
            'concept' => 'required|string',
            'authorized_hours' => 'required|numeric|min:0',
            'time_slot_start' => 'required',
            'time_slot_end' => 'required',
            'generated_by' => 'required|exists:users,id',
            'user_id' => 'nullable|exists:users,id',
            /* El TIPUS ha de poder venir del formulari: si no, tot codi neix
               complementari i a un treballador a jornada completa (que per llei
               només pot fer extraordinàries) no se li podia emetre res.
               El FACTOR no s'accepta del client: el posa el servidor segons el
               tipus, perquè no hi hagi dos multiplicadors per a la mateixa hora. */
            'type' => 'nullable|in:complementaria,extraordinaria',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
        ];
    }
}
