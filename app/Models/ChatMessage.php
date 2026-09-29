<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'sender_id', 'content', 'type', 'file_data', 'file_name'];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function readBy()
    {
        return $this->belongsToMany(User::class, 'chat_message_reads', 'message_id', 'user_id')->withTimestamps();
    }
}
