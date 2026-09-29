<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BreakSetting extends Model
{
    protected $table = 'break_settings';

    protected $fillable = [
        'enabled', 'threshold_hours', 'break_duration_minutes',
        'auto_start', 'grace_period_minutes',
        'break_start_mode', 'break_start_offset_minutes', 'break_start_fixed_time',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_start' => 'boolean',
        'break_start_offset_minutes' => 'integer',
        'break_start_fixed_time' => 'datetime:H:i:s',
    ];

    /**
     * Get the singleton break settings record.
     */
    public static function getSettings(): self
    {
        return self::first() ?? self::create([
            'enabled' => true,
            'threshold_hours' => 5,
            'break_duration_minutes' => 20,
            'auto_start' => false,
            'grace_period_minutes' => 0,
            'break_start_mode' => 'offset',
            'break_start_offset_minutes' => 300,
            'break_start_fixed_time' => '12:00:00',
        ]);
    }
}
