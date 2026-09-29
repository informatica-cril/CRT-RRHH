<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLogSegment extends Model
{
    protected $table = 'work_log_segments';

    protected $fillable = [
        'work_log_id', 'segment_number', 'kind', 'ref', 'start_time', 'end_time',
        'start_lat', 'start_lng', 'end_lat', 'end_lng',
        'in_zone', 'in_schedule', 'home_verification', 'home_distance_m', 'home_radius_m',
        'audiencia_requested_at', 'audiencia_deadline', 'allegation', 'allegation_at', 'duration_minutes',
        'status', 'rejection_reason', 'authorization_code_id',
        'modified_by', 'modified_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'in_zone' => 'boolean',
        'in_schedule' => 'boolean',
        'modified_at' => 'datetime',
        'audiencia_requested_at' => 'datetime',
        'audiencia_deadline' => 'date',
        'allegation_at' => 'datetime',
        // Coords xifrades AES en repòs (coherent amb work_logs/location_tracking)
        'start_lat' => 'encrypted',
        'start_lng' => 'encrypted',
        'end_lat' => 'encrypted',
        'end_lng' => 'encrypted',
    ];

    /**
     * Invariants de l'audiència prèvia — capa d'aplicació (missatge clar abans de la BD).
     * La garantia DURA és el CHECK constraint a la taula; això dona l'error llegible.
     */
    protected static function booted(): void
    {
        static::saving(function (self $seg) {
            if ($seg->audiencia_deadline && ! $seg->audiencia_requested_at) {
                throw new \InvalidArgumentException("Audiència: hi ha un termini d'al·legacions sense obertura registrada.");
            }
            if ($seg->allegation && ! $seg->audiencia_requested_at) {
                throw new \InvalidArgumentException("Audiència: hi ha una al·legació sense audiència oberta.");
            }
            if ($seg->audiencia_deadline && $seg->audiencia_requested_at
                && $seg->audiencia_deadline->lt($seg->audiencia_requested_at->copy()->startOfDay())) {
                throw new \InvalidArgumentException(
                    "Audiència incoherent: el termini d'al·legacions (" . $seg->audiencia_deadline->toDateString()
                    . ") no pot ser anterior a l'obertura (" . $seg->audiencia_requested_at->toDateString() . ")."
                );
            }
        });
    }

    public function workLog()
    {
        return $this->belongsTo(WorkLog::class);
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
