<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['user_id', 'path', 'mime', 'width', 'height'];

    protected $hidden = ['path'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return '/media/'.$this->id;
    }
}
