<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' Hotel',
            'description' => $this->faker->paragraph(3),
            'city' => $this->faker->city,
            'address' => $this->faker->address,
            'price_per_night' => $this->faker->numberBetween(500, 3000),
            'rating' => $this->faker->randomFloat(1, 3, 5),
            'stars' => $this->faker->numberBetween(3, 5),
            'images' => [
                $this->faker->imageUrl(800, 600, 'hotel', true),
                $this->faker->imageUrl(800, 600, 'hotel', true),
            ],
            'amenities' => $this->faker->randomElements([
                'WiFi', 'Pool', 'Spa', 'Restaurant', 'Gym', 'Room Service', 'Parking', 'Bar', 'Garden', 'Shuttle Service'
            ], 4),
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->companyEmail,
            'website' => $this->faker->url,
            'is_active' => true,
        ];
    }
}