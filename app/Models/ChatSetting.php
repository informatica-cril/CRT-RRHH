<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSetting extends Model
{
    use HasFactory;

    protected $fillable = ['forensic_keywords', 'chat_policy_text', 'require_policy_acceptance'];

    protected $casts = [
        'forensic_keywords' => 'array',
        'require_policy_acceptance' => 'boolean',
    ];
}
