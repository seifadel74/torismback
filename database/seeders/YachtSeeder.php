<?php

namespace Database\Seeders;

use App\Models\Yacht;
use Illuminate\Database\Seeder;

class YachtSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $yachts = [
            [
                'name' => 'Nile Princess',
                'description' => 'Luxury yacht for Nile River cruises with panoramic views and elegant interiors.',
                'location' => 'Cairo',
                'price_per_day' => 500.00,
                'rating' => 4.7,
                'images' => [
                    'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800',
                    'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800',
                ],
                'amenities' => ['WiFi', 'Kitchen', 'Bedrooms', 'Bathroom', 'Deck', 'Captain'],
                'capacity' => 8,
                'length' => 45.0,
                'year_built' => 2020,
                'crew_size' => 2,
                'phone' => '+201234567890',
                'email' => 'info@nileprincess.com',
                'website' => 'https://nileprincess.com',
            ],
            [
                'name' => 'Red Sea Explorer',
                'description' => 'Adventure yacht for diving and snorkeling in the Red Sea.',
                'location' => 'Hurghada',
                'price_per_day' => 800.00,
                'rating' => 4.8,
                'images' => [
                    'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800',
                    'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800',
                ],
                'amenities' => ['WiFi', 'Diving Equipment', 'Kitchen', 'Bedrooms', 'Diving Platform'],
                'capacity' => 12,
                'length' => 60.0,
                'year_built' => 2019,
                'crew_size' => 3,
                'phone' => '+201234567891',
                'email' => 'info@redseaexplorer.com',
                'website' => 'https://redseaexplorer.com',
            ],
            [
                'name' => 'Mediterranean Dream',
                'description' => 'Elegant yacht for Mediterranean coastal cruises.',
                'location' => 'Alexandria',
                'price_per_day' => 600.00,
                'rating' => 4.5,
                'images' => [
                    'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800',
                    'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800',
                ],
                'amenities' => ['WiFi', 'Kitchen', 'Bedrooms', 'Bathroom', 'Sun Deck', 'Fishing Equipment'],
                'capacity' => 6,
                'length' => 35.0,
                'year_built' => 2021,
                'crew_size' => 1,
                'phone' => '+201234567892',
                'email' => 'info@meddream.com',
                'website' => 'https://meddream.com',
            ],
            [
                'name' => 'Luxor Heritage',
                'description' => 'Traditional dahabiya for authentic Nile River experience.',
                'location' => 'Luxor',
                'price_per_day' => 400.00,
                'rating' => 4.3,
                'images' => [
                    'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800',
                    'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800',
                ],
                'amenities' => ['Traditional Decor', 'Kitchen', 'Bedrooms', 'Bathroom', 'Deck', 'Guide'],
                'capacity' => 10,
                'length' => 40.0,
                'year_built' => 2018,
                'crew_size' => 2,
                'phone' => '+201234567893',
                'email' => 'info@luxorheritage.com',
                'website' => 'https://luxorheritage.com',
            ],
            [
                'name' => 'Sharm El Sheikh Luxury',
                'description' => 'Premium yacht for luxury Red Sea experiences.',
                'location' => 'Sharm El Sheikh',
                'price_per_day' => 1200.00,
                'rating' => 4.9,
                'images' => [
                    'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800',
                    'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800',
                ],
                'amenities' => ['WiFi', 'Jacuzzi', 'Kitchen', 'Master Suite', 'Deck', 'Chef', 'Butler'],
                'capacity' => 6,
                'length' => 50.0,
                'year_built' => 2022,
                'crew_size' => 4,
                'phone' => '+201234567894',
                'email' => 'info@sharmluxury.com',
                'website' => 'https://sharmluxury.com',
            ],
        ];

        foreach ($yachts as $yacht) {
            Yacht::create($yacht);
        }

        // Create more yachts using factory
        Yacht::factory(10)->create();
    }
}