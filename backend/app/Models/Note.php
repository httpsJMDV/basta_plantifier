<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Note extends Model
{
    protected $fillable = ['user_id', 'plant_record_id', 'conversation_id', 'map_pin_id', 'revision', 'title', 'body'];

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'note_media')->orderBy('media.id');
    }

    public function plant()
    {
        return $this->belongsTo(PlantRecord::class, 'plant_record_id');
    }
}
