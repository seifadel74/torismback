<?php

namespace App\ViewModels;

use App\Models\Hotel;
use Illuminate\Http\Request;

class HotelViewModel
{
    /**
     * Get all hotels with filtering and pagination
     */
    public function getHotels(Request $request)
    {
        $query = Hotel::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('city')) {
            $query->where('city', $request->get('city'));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->get('min_rating'));
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_night', '<=', $request->get('max_price'));
        }

        if ($request->filled('stars')) {
            $query->where('stars', $request->get('stars'));
        }

        // Apply sorting
        if ($request->filled('sort_by')) {
            $sortBy = $request->get('sort_by');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Get paginated results
        $perPage = $request->get('per_page', 12);
        $hotels = $query->paginate($perPage);

        return [
            'hotels' => $hotels->items(),
            'pagination' => [
                'current_page' => $hotels->currentPage(),
                'last_page' => $hotels->lastPage(),
                'per_page' => $hotels->perPage(),
                'total' => $hotels->total(),
            ]
        ];
    }

    /**
     * Get specific hotel
     */
    public function getHotel($id)
    {
        $hotel = Hotel::findOrFail($id);
        return $hotel;
    }

    /**
     * Create new hotel
     */
    public function createHotel($request)
    {
        // Handle image uploads with enhanced security
        $imagePaths = [];
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            
            // Limit number of images to prevent abuse
            if (count($images) > 5) {
                throw new \Exception('Maximum 5 images allowed');
            }
            
            foreach ($images as $index => $image) {
                if ($image && $image->isValid()) {
                    // Enhanced security checks
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                    $realMimeType = $image->getMimeType();
                    
                    if (!in_array($realMimeType, $allowedMimes)) {
                        throw new \Exception('Invalid file type. Only JPEG, PNG, JPG, and GIF are allowed.');
                    }
                    
                    // Check file size (max 2MB)
                    if ($image->getSize() > 2048 * 1024) {
                        throw new \Exception('File size too large. Maximum 2MB allowed.');
                    }
                    
                    // Generate secure filename with hash to prevent path traversal
                    $extension = $image->getClientOriginalExtension();
                    $filename = hash('sha256', $image->getClientOriginalName() . time() . $index) . '.' . $extension;
                    
                    // Move file to secure location
                    $image->move(public_path('storage/hotels'), $filename);
                    $imagePaths[] = '/storage/hotels/' . $filename;
                    
                    // Log file upload for security audit
                    \Log::info('Hotel image uploaded', [
                        'filename' => $filename,
                        'original_name' => $image->getClientOriginalName(),
                        'mime_type' => $realMimeType,
                        'size' => $image->getSize()
                    ]);
                }
            }
        }
        
        // Process amenities
        $amenities = [];
        if ($request->has('amenities')) {
            $amenitiesString = $request->get('amenities');
            if (is_string($amenitiesString)) {
                $amenities = json_decode($amenitiesString, true) ?: [];
            } elseif (is_array($amenitiesString)) {
                $amenities = $amenitiesString;
            }
        }
        
        $hotelData = [
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'city' => $request->get('city'),
            'location' => $request->get('location') ?: $request->get('city'),
            'price_per_night' => (float) $request->get('price'),
            'rating' => (float) $request->get('rating'),
            'images' => json_encode($imagePaths),
            'amenities' => json_encode($amenities),
        ];
        
        $hotel = Hotel::create($hotelData);
        
        // Return hotel with decoded images and amenities for frontend
        $hotel->images = $imagePaths;
        $hotel->amenities = $amenities;

        return [
            'message' => 'Hotel created successfully',
            'hotel' => $hotel
        ];
    }

    /**
     * Update hotel
     */
    public function updateHotel($id, $request)
    {
        $hotel = Hotel::findOrFail($id);
        
        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            // Delete old images if new ones are uploaded
            $oldImages = json_decode($hotel->images, true) ?: [];
            foreach ($oldImages as $oldImage) {
                $oldImagePath = public_path($oldImage);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            // Upload new images
            foreach ($request->file('images') as $index => $image) {
                if ($image && $image->isValid()) {
                    $filename = time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('storage/hotels'), $filename);
                    $imagePaths[] = '/storage/hotels/' . $filename;
                }
            }
        } else {
            // Keep existing images if no new ones uploaded
            $imagePaths = json_decode($hotel->images, true) ?: [];
        }
        
        // Process amenities
        $amenities = [];
        if ($request->has('amenities')) {
            $amenitiesString = $request->get('amenities');
            if (is_string($amenitiesString)) {
                $amenities = json_decode($amenitiesString, true) ?: [];
            } elseif (is_array($amenitiesString)) {
                $amenities = $amenitiesString;
            }
        } else {
            // Keep existing amenities if not provided
            $amenities = json_decode($hotel->amenities, true) ?: [];
        }
        
        $hotelData = [
            'name' => $request->get('name') ?: $hotel->name,
            'description' => $request->get('description') ?: $hotel->description,
            'city' => $request->get('city') ?: $hotel->city,
            'location' => $request->get('location') ?: $hotel->location,
            'price_per_night' => $request->has('price') ? (float) $request->get('price') : $hotel->price_per_night,
            'rating' => $request->has('rating') ? (float) $request->get('rating') : $hotel->rating,
            'images' => json_encode($imagePaths),
            'amenities' => json_encode($amenities),
        ];
        
        $hotel->update($hotelData);
        
        // Return hotel with decoded images and amenities for frontend
        $hotel->images = $imagePaths;
        $hotel->amenities = $amenities;

        return [
            'message' => 'Hotel updated successfully',
            'hotel' => $hotel
        ];
    }

    /**
     * Delete hotel
     */
    public function deleteHotel($id)
    {
        $hotel = Hotel::findOrFail($id);
        $hotel->delete();

        return [
            'message' => 'Hotel deleted successfully'
        ];
    }

    /**
     * Get hotel availability
     */
    public function getHotelAvailability($id, Request $request)
    {
        $hotel = Hotel::findOrFail($id);
        
        $checkIn = $request->get('check_in');
        $checkOut = $request->get('check_out');
        
        // Simple availability check
        $isAvailable = true; // This would be more complex in a real app
        
        return [
            'hotel' => $hotel,
            'is_available' => $isAvailable,
            'check_in' => $checkIn,
            'check_out' => $checkOut
        ];
    }

    /**
     * Get popular hotels
     */
    public function getPopularHotels(Request $request)
    {
        $limit = $request->get('limit', 6);
        
        $hotels = Hotel::where('is_active', true)
                      ->orderBy('rating', 'desc')
                      ->orderBy('bookings_count', 'desc')
                      ->limit($limit)
                      ->get();

        return [
            'hotels' => $hotels
        ];
    }

    /**
     * Get hotels by city
     */
    public function getHotelsByCity($city, Request $request)
    {
        $query = Hotel::where('city', $city)
                     ->where('is_active', true);

        // Apply additional filters
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->get('min_rating'));
        }

        if ($request->has('max_price')) {
            $query->where('price_per_night', '<=', $request->get('max_price'));
        }

        $hotels = $query->get();

        return [
            'city' => $city,
            'hotels' => $hotels,
            'count' => $hotels->count()
        ];
    }

    /**
     * Get hotel statistics
     */
    public function getHotelStats()
    {
        $totalHotels = Hotel::count();
        $activeHotels = Hotel::where('is_active', true)->count();
        $averageRating = Hotel::avg('rating');
        $averagePrice = Hotel::avg('price_per_night');

        return [
            'total_hotels' => $totalHotels,
            'active_hotels' => $activeHotels,
            'average_rating' => round($averageRating, 2),
            'average_price' => round($averagePrice, 2)
        ];
    }
} 