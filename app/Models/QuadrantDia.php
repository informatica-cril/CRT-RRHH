<?php

namespace App\Models;

use App\Services\HorariDia;
use Illuminate\Database\Eloquent\Model;

/** Un dia del quadrant d'una persona: mana sobre la plantilla setmanal (veure App\Services\HorariDia). */
class QuadrantDia extends Model
{
    protected $table = 'quadrant_dies';

    protected $fillable = ['user_id', 'dia', 'trams', 'lliure', 'work_schedule_id', 'nota', 'created_by'];

    protected $casts = [
        'trams' => 'array',
        'lliure' => 'boolean',
    ];

    protected static function booted(): void
    {
        // La memòria de HorariDia és per petició: qualsevol canvi la buida.
        static::saved(fn () => HorariDia::oblida());
        static::deleted(fn () => HorariDia::oblida());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workSchedule()
    {
        return $this->belongsTo(WorkSchedule::class);
    }
}
