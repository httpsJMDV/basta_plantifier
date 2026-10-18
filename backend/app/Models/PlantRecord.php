<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantRecord extends Model
{
    protected $fillable = ['common_name', 'scientific_name', 'family', 'description', 'care', 'safety', 'relationship', 'favorite', 'archived_at', 'media_id', 'chat_message_id', 'provenance'];

    protected function casts(): array
    {
        return ['favorite' => 'boolean', 'archived_at' => 'datetime', 'provenance' => 'array'];
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function observations()
    {
        return $this->hasMany(Observation::class)->orderByDesc('observed_on')->orderByDesc('id');
    }

    public function notes()
    {
        return $this->hasMany(Note::class)->latest();
    }

    public function pins()
    {
        return $this->hasMany(MapPin::class);
    }
}
