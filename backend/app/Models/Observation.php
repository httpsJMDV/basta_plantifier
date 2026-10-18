<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observation extends Model
{
    protected $fillable = ['body', 'observed_on', 'height_cm', 'media_id'];

    public function plant()
    {
        return $this->belongsTo(PlantRecord::class, 'plant_record_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }
}
