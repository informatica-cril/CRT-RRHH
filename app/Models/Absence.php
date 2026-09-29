<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    protected $fillable = [
        'user_id', 'absence_type_id', 'start_date', 'end_date',
        'reason', 'approved', 'approved_by', 'approved_at', 'denial_reason', 'with_travel',
        'justificant_path', 'justificant_name', 'justificant_uploaded_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved' => 'boolean',
        'approved_at' => 'datetime',
        'justificant_uploaded_at' => 'datetime',
        'with_travel' => 'boolean',
    ];

    // La ruta física del justificant (part mèdic) no surt mai per l'API: el fitxer
    // es baixa per l'endpoint amb guarda, no pel camí del disc.
    protected $hidden = ['justificant_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function absenceType()
    {
        return $this->belongsTo(AbsenceType::class);
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Calculate total days of this absence (dies naturals, inici→fi inclòs).
     */
    public function getTotalDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Dies NATURALS d'un període vacacional.
     *
     * Es compta des de l'inici fins al final del període, TOT el que passa al mig
     * (caps de setmana i festius inclosos). A més, el període s'estén més enllà del
     * final pels dies NO laborables consecutius (cap de setmana i festius) fins al
     * següent dia laborable — perquè es parla d'inici i fi de període vacacional
     * (fins a la tornada a la feina), no de dies laborables solts.
     *
     * @param  string|\Carbon\Carbon  $start
     * @param  string|\Carbon\Carbon  $end
     * @param  array|null  $holidays  Llista de dates 'Y-m-d' festives (si null, es carreguen).
     */
    public static function vacationDays($start, $end, ?array $holidays = null): int
    {
        if ($holidays === null) {
            $holidays = \App\Models\Holiday::pluck('date')
                ->map(fn ($d) => \Carbon\Carbon::parse($d)->toDateString())->all();
        }
        $holidaySet = array_flip($holidays);

        $s = $start instanceof \Carbon\Carbon ? $start->copy() : \Carbon\Carbon::parse($start);
        $e = $end instanceof \Carbon\Carbon ? $end->copy() : \Carbon\Carbon::parse($end);

        // Estendre el final a través de dies no laborables (cap de setmana o festiu)
        // fins al següent dia laborable. Límit de seguretat per evitar bucles.
        $next = $e->copy()->addDay();
        $guard = 0;
        while (($next->isWeekend() || isset($holidaySet[$next->toDateString()])) && $guard < 31) {
            $e = $next->copy();
            $next->addDay();
            $guard++;
        }

        return $s->diffInDays($e) + 1;
    }
}
