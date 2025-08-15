<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotels = [
            [
                'name' => 'Nile Palace Hotel',
                'description' => 'Luxury hotel overlooking the Nile River with stunning views and world-class amenities.',
                'city' => 'Cairo',
                'address' => 'Corniche El Nil, Cairo, Egypt',
                'price_per_night' => 250.00,
                'rating' => 4.5,
                'stars' => 5,
                'images' => [
                    'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800',
                    'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800',
                ],
                'amenities' => ['WiFi', 'Pool', 'Spa', 'Restaurant', 'Gym', 'Room Service'],
                'phone' => '+201234567890',
                'email' => 'info@nilepalace.com',
                'website' => 'https://nilepalace.com',
            ],
            [
                'name' => 'Pyramid View Resort',
                'description' => 'Unique resort with direct views of the Great Pyramids of Giza.',
                'city' => 'Giza',
                'address' => 'Pyramid Road, Giza, Egypt',
                'price_per_night' => 180.00,
                'rating' => 4.2,
                'stars' => 4,
                'images' => [
                    'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800',
                    'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800',
                ],
                'amenities' => ['WiFi', 'Pool', 'Restaurant', 'Garden', 'Shuttle Service'],
                'phone' => '+201234567891',
                'email' => 'info@pyramidview.com',
                'website' => 'https://pyramidview.com',
            ],
            [
                'name' => 'Mediterranean Beach Hotel',
                'description' => 'Beachfront hotel in Alexandria with private beach access and Mediterranean views.',
                'city' => 'Alexandria',
                'address' => 'Corniche Road, Alexandria, Egypt',
                'price_per_night' => 150.00,
                'rating' => 4.0,
                'stars' => 4,
                'images' => [
                    'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800',
                    'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800',
                ],
                'amenities' => ['WiFi', 'Private Beach', 'Pool', 'Restaurant', 'Water Sports'],
                'phone' => '+201234567892',
                'email' => 'info@medbeach.com',
                'website' => 'https://medbeach.com',
            ],
            [
                'name' => 'Luxor Temple Hotel',
                'description' => 'Historic hotel near the Luxor Temple with traditional Egyptian architecture.',
                'city' => 'Luxor',
                'address' => 'Temple Street, Luxor, Egypt',
                'price_per_night' => 120.00,
                'rating' => 4.3,
                'stars' => 3,
                'images' => [
                    'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800',
                    'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800',
                ],
                'amenities' => ['WiFi', 'Restaurant', 'Garden', 'Tour Guide Service'],
                'phone' => '+201234567893',
                'email' => 'info@luxortemple.com',
                'website' => 'https://luxortemple.com',
            ],
            [
                'name' => 'Red Sea Paradise Resort',
                'description' => 'All-inclusive resort on the Red Sea with diving and water activities.',
                'city' => 'Hurghada',
                'address' => 'Red Sea Coast, Hurghada, Egypt',
                'price_per_night' => 200.00,
                'rating' => 4.6,
                'stars' => 5,
                'images' => [
                    'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800',
                    'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800',
                ],
                'amenities' => ['WiFi', 'Private Beach', 'Pool', 'Spa', 'Diving Center', 'All Inclusive'],
                'phone' => '+201234567894',
                'email' => 'info@redseaparadise.com',
                'website' => 'https://redseaparadise.com',
            ],
        ];

        foreach ($hotels as $hotel) {
            Hotel::create($hotel);
        }

        // Create more hotels using factory
        Hotel::factory(15)->create();
    }
}