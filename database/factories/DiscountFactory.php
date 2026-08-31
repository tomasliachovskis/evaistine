<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountFactory extends Factory
{
    public function definition()
    {
        $original = $this->faker->randomFloat(2, 5, 100);
        $discounted = round($original * $this->faker->randomFloat(2, 0.4, 0.9), 2);

        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'product_url' => $this->faker->url(),
            'original_price' => $original,
            'discounted_price' => $discounted,
            'discount_percent' => (int) round((1 - $discounted / $original) * 100),
            'condition' => null,
            'card' => false,
            'start_at' => now()->subDay(),
            'end_at' => now()->addWeek(),
        ];
    }
}
