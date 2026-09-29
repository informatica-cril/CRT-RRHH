<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Excedencia extends Model
{
    protected $fillable = [
        'user_id', 'excedencia_type_id', 'start_date', 'end_date',
        'reincorporation_date', 'status', 'reason', 'notes', 'approved_by',
        'approved_at', 'denial_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reincorporation_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function excedenciaType()
    {
        return $this->belongsTo(ExcedenciaType::class);
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
