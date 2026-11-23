<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogItem extends Model
{
    protected $fillable = [
        'kind',
        'name',
        'parent_id',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(CatalogItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(CatalogItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function tutors()
    {
        return $this->belongsToMany(TutorProfile::class, 'tutor_catalog', 'catalog_id', 'tutor_id');
    }

    public function nearbyAreas()
    {
        return $this->hasMany(AreaAdjacency::class, 'area_id');
    }
}
