<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLog extends Model
{
    protected $fillable = [
        'user_id', 'date', 'start_time', 'end_time',
        'start_location_lat', 'start_location_lng',
        'end_location_lat', 'end_location_lng',
        'total_hours_worked', 'authorized_extra_code',
        'extra_hours_authorized', 'extra_hours_unauthorized',
        'status', 'rejection_reason',
        'location_match',
        'start_location_match',
        'break_start_location_match', 'break_end_location_match',
        'start_location_distance',
        'end_location_match',
        'end_location_distance',
        'hour_status',
        'hours_worked',
        'hours_out_of_area',
        'effective_hours',
        'auto_closed',
        'reminder_pre_sent_at',
        'reminder1_sent_at',
        'reminder2_sent_at',
        // Campos nuevos (aditivos)
        'complementary_minutes',
        'break_start_time',
        'break_end_time',
        'break_required',
        'break_status',
        'segmented',
        // Privacy by Design: mode de verificació, verificació de domicili, quadrant, purga
        'verification_mode',
        'home_verification',
        'out_of_schedule',
        'coords_purged_at',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        // Coordenades crues xifrades AES en repòs (es desxifren de forma transparent al llegir).
        // El geovallat i el mapa segueixen funcionant: els booleans i distàncies estan en clar.
        'start_location_lat' => 'encrypted',
        'start_location_lng' => 'encrypted',
        'end_location_lat' => 'encrypted',
        'end_location_lng' => 'encrypted',
        'total_hours_worked' => 'decimal:2',
        'extra_hours_authorized' => 'decimal:2',
        'extra_hours_unauthorized' => 'decimal:2',
        'location_match' => 'boolean',
        'start_location_match' => 'boolean',
        'end_location_match' => 'boolean',
        'start_location_distance' => 'decimal:2',
        'end_location_distance' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'hours_out_of_area' => 'decimal:2',
        'effective_hours' => 'decimal:2',
        'auto_closed' => 'boolean',
        // Casts nuevos (aditivos)
        'break_start_time' => 'datetime',
        'break_end_time' => 'datetime',
        'break_required' => 'boolean',
        'segmented' => 'boolean',
    ];

    /**
     * Prepare a date for array / JSON serialization.
     *
     * Totes les dates del fichatge (start_time, end_time, break_*, created_at...)
     * es guarden en hora de Madrid directa, MAI UTC real (veure WorkLogController).
     * Sense la 'Z' final: el navegador interpreta el valor tal qual, en hora local,
     * que ja és el correcte. Afegir-hi una 'Z' (com feia abans aquest mètode) li fa
     * creure que és UTC real i li suma 1-2h de més en mostrar-la.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d\TH:i:s.v');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Relaciones nuevas (aditivas) ──

    public function segments()
    {
        return $this->hasMany(WorkLogSegment::class, 'work_log_id');
    }

    public function modifications()
    {
        return $this->hasMany(WorkLogModification::class);
    }

    public function alerts()
    {
        return $this->hasMany(WorkLogAlert::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Hores brutes de la jornada (entrada → sortida), en hora de Madrid sense zona.
     * Null si la jornada no té sortida.
     */
    public function horesBrutes(): ?float
    {
        $ini = $this->getRawOriginal('start_time');
        $fi = $this->getRawOriginal('end_time');
        if (! $ini || ! $fi) {
            return null;
        }

        return round(max(0, \Carbon\Carbon::parse($ini, 'Europe/Madrid')
            ->diffInSeconds(\Carbon\Carbon::parse($fi, 'Europe/Madrid'), false)) / 3600, 4);
    }

    /**
     * Canvis per aprovar un fitxatge amb hores fora de zona donant-les per bones.
     *
     * total_hours_worked ja són les hores brutes (el servidor les recalcula en fitxar la
     * sortida), així que «restaurar» és tornar a les brutes, no sumar-hi les de fora de zona
     * (abans es feia i les duplicava: 4h20 fora de zona → 8h40).
     */
    public function canvisRestauraForaZona(): array
    {
        $brutes = $this->horesBrutes() ?? (float) $this->total_hours_worked;
        $brutes = round($brutes, 2);

        return [
            'total_hours_worked' => $brutes,
            'hours_worked' => $brutes,
            'hours_out_of_area' => 0,
            'hour_status' => (float) $this->extra_hours_unauthorized > 0 ? 'extra' : 'ok',
        ];
    }

    /** Hi ha temps fora de zona encara per resoldre segons els trams? */
    public function teTramsForaZonaPendents(): bool
    {
        return $this->segments()->where('in_zone', false)->where('status', 'pending')->exists();
    }

    /**
     * Recalcula el tiempo de trabajo EFECTIVO.
     *
     * - Si el fichaje está segmentado: solo cuentan los tramos con status='approved'
     *   (los pending y rejected NO suman como tiempo efectivo).
     * - Si no está segmentado: el efectivo es el mismo que hours_worked ya calculado.
     *
     * Persiste el resultado en effective_hours y lo devuelve.
     */
    public function recalcularEfectivo(): ?float
    {
        if ($this->segmented) {
            $mins = (int) $this->segments()->where('status', 'approved')->sum('duration_minutes');
            $eff = round($mins / 60, 2);
        } else {
            $eff = $this->hours_worked !== null ? round((float) $this->hours_worked, 2) : null;
        }

        $this->effective_hours = $eff;
        $this->save();

        return $eff;
    }
}
