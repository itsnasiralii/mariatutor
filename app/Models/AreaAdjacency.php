<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaAdjacency extends Model
{
    protected $fillable = [
        'area_id',
        'nearby_area_id',
        'priority',
    ];

    public function area()
    {
        return $this->belongsTo(CatalogItem::class, 'area_id');
    }

    public function nearbyArea()
    {
        return $this->belongsTo(CatalogItem::class, 'nearby_area_id');
    }
}
