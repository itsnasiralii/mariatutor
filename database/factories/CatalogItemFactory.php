<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kind' => 'area',
            'name' => fake()->unique()->words(2, true),
            'parent_id' => null,
            'active' => true,
            'sort_order' => 1,
        ];
    }

    public function area(?string $name = null): static
    {
        return $this->state(fn () => [
            'kind' => 'area',
            'name' => $name ?? 'Sector '.fake()->unique()->bothify('##-?'),
        ]);
    }

    public function subject(?string $name = null): static
    {
        return $this->state(fn () => [
            'kind' => 'subject',
            'name' => $name ?? 'Subject '.fake()->unique()->lexify('????'),
        ]);
    }

    public function schoolClass(?string $name = null): static
    {
        return $this->state(fn () => [
            'kind' => 'class',
            'name' => $name ?? 'Class '.fake()->unique()->numberBetween(1, 12),
        ]);
    }

    public function mode(?string $name = null): static
    {
        return $this->state(fn () => [
            'kind' => 'mode',
            'name' => $name ?? 'Mode '.fake()->unique()->lexify('???'),
        ]);
    }
}
