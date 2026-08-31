<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition()
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'name' => $name,
            'brand' => $this->faker->optional()->company(),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'description' => $this->faker->optional()->sentence(),
            'category_id' => Category::factory(),
            'image_url' => $this->faker->optional()->imageUrl(),
        ];
    }
}
