<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = ['user_id', 'plant_record_id', 'title', 'body'];

    public function plant()
    {
        return $this->belongsTo(PlantRecord::class, 'plant_record_id');
    }
}
