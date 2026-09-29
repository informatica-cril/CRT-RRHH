<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatAlert extends Model
{
    use HasFactory;

    protected $fillable = ['message_id', 'keyword', 'reviewed', 'content_excerpt'];

    protected $casts = [
        'reviewed' => 'boolean',
    ];

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
