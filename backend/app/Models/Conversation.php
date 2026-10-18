<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['title'];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
