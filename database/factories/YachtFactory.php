<?php

namespace Database\Factories;

use App\Models\Yacht;
use Illuminate\Database\Eloquent\Factories\Factory;

class YachtFactory extends Factory
{
    protected $model = Yacht::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' Yacht',
            'description' => $this->faker->paragraph(2),
            'location' => $this->faker->city,
            'price_per_day' => $this->faker->numberBetween(1000, 5000),
            'rating' => $this->faker->randomFloat(1, 3, 5),
            'images' => [
                $this->faker->imageUrl(800, 600, 'yacht', true),
                $this->faker->imageUrl(800, 600, 'yacht', true),
            ],
            'amenities' => $this->faker->randomElements([
                'WiFi', 'Kitchen', 'Bedrooms', 'Bathroom', 'Deck', 'Captain', 'Diving Equipment', 'Fishing Equipment', 'Jacuzzi', 'Chef'
            ], 4),
            'capacity' => $this->faker->numberBetween(4, 20),
            'length' => $this->faker->randomFloat(2, 20, 60),
            'year_built' => $this->faker->numberBetween(2000, 2023),
            'crew_size' => $this->faker->numberBetween(1, 5),
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->companyEmail,
            'website' => $this->faker->url,
            'is_active' => true,
        ];
    }
}