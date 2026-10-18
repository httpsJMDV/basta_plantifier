<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapPin extends Model
{
    protected $fillable = ['user_id', 'plant_record_id', 'title', 'area', 'description', 'latitude', 'longitude', 'visibility', 'sensitive', 'observed_on'];

    protected $hidden = ['latitude', 'longitude'];

    protected function casts(): array
    {
        return ['sensitive' => 'boolean'];
    }

    public function plant()
    {
        return $this->belongsTo(PlantRecord::class, 'plant_record_id');
    }
}
