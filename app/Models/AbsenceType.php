<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceType extends Model
{
    protected $fillable = [
        'name', 'recoverable', 'remunerated', 'max_days',
        'max_per_year', 'max_lifetime', 'requires_justification',
        'advance_notice_hours', 'extends_with_travel',
        'extra_days_travel', 'category',
        // Com a la fulla CONCEPTOS de l'Excel de RRHH: motiu de l'Excel i quantitat per unitat i període.
        'codi_rrhh', 'quantitat', 'unitat', 'periode', 'notes',
    ];

    protected $casts = [
        'recoverable' => 'boolean',
        'remunerated' => 'boolean',
        'requires_justification' => 'boolean',
        'extends_with_travel' => 'boolean',
        'quantitat' => 'float',
    ];

    public function absences()
    {
        return $this->hasMany(Absence::class);
    }
}
