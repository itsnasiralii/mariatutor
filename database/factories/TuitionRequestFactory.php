<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\TuitionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TuitionRequest>
 */
class TuitionRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_ref' => 'REQ-'.strtoupper(Str::random(6)),
            'parent_user_id' => User::factory()->parentRole(),
            'area_id' => CatalogItem::factory()->area(),
            'subject_id' => CatalogItem::factory()->subject(),
            'class_id' => CatalogItem::factory()->schoolClass(),
            'mode_id' => CatalogItem::factory()->mode(),
            'subarea_text' => 'Block B',
            'budget_min' => 10000,
            'budget_max' => 16000,
            'preferred_time' => '5:00 PM - 6:30 PM',
            'requirements' => 'Board exam focus',
            'tutor_gender_preference' => 'Any',
            'status' => 'New request',
        ];
    }
}
