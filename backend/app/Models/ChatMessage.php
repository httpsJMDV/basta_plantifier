<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChatMessage extends Model
{
    protected $fillable = ['role', 'body', 'media_id', 'identification', 'status', 'response_style'];

    protected $hidden = ['plantnet_result'];

    protected function casts(): array
    {
        return ['identification' => 'array', 'plantnet_result' => 'array'];
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'chat_message_media')->orderBy('media.id');
    }
}
