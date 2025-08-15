<?php

namespace App\ViewModels;

use App\Models\Yacht;
use Illuminate\Http\Request;

class YachtViewModel
{
    /**
     * Get all yachts with filtering and pagination
     */
    public function getYachts(Request $request)
    {
        $query = Yacht::query();

        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->has('location')) {
            $query->where('location', $request->get('location'));
        }

        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->get('min_rating'));
        }

        if ($request->has('max_price')) {
            $query->where('price_per_day', '<=', $request->get('max_price'));
        }

        if ($request->has('capacity')) {
            $query->where('capacity', '>=', $request->get('capacity'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated results
        $perPage = $request->get('per_page', 12);
        $yachts = $query->paginate($perPage);

        return [
            'yachts' => $yachts->items(),
            'pagination' => [
                'current_page' => $yachts->currentPage(),
                'last_page' => $yachts->lastPage(),
                'per_page' => $yachts->perPage(),
                'total' => $yachts->total(),
            ]
        ];
    }

    /**
     * Get specific yacht
     */
    public function getYacht($id)
    {
        $yacht = Yacht::findOrFail($id);
        return $yacht;
    }

    /**
     * Create new yacht
     */
    public function createYacht($request)
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
                    $image->move(public_path('storage/yachts'), $filename);
                    $imagePaths[] = '/storage/yachts/' . $filename;
                    
                    // Log file upload for security audit
                    \Log::info('Yacht image uploaded', [
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
        
        $yachtData = [
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'location' => $request->get('location'),
            'price_per_day' => (float) $request->get('price'),
            'capacity' => (int) $request->get('capacity'),
            'rating' => (float) $request->get('rating'),
            'images' => json_encode($imagePaths),
            'amenities' => json_encode($amenities),
        ];
        
        $yacht = Yacht::create($yachtData);
        
        // Return yacht with decoded images and amenities for frontend
        $yacht->images = $imagePaths;
        $yacht->amenities = $amenities;

        return [
            'message' => 'Yacht created successfully',
            'yacht' => $yacht
        ];
    }

    /**
     * Update yacht
     */
    public function updateYacht($id, $request)
    {
        $yacht = Yacht::findOrFail($id);
        
        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            // Delete old images if new ones are uploaded
            $oldImages = json_decode($yacht->images, true) ?: [];
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
                    $image->move(public_path('storage/yachts'), $filename);
                    $imagePaths[] = '/storage/yachts/' . $filename;
                }
            }
        } else {
            // Keep existing images if no new ones uploaded
            $imagePaths = json_decode($yacht->images, true) ?: [];
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
            $amenities = json_decode($yacht->amenities, true) ?: [];
        }
        
        $yachtData = [
            'name' => $request->get('name') ?: $yacht->name,
            'description' => $request->get('description') ?: $yacht->description,
            'location' => $request->get('location') ?: $yacht->location,
            'price_per_day' => $request->has('price') ? (float) $request->get('price') : $yacht->price_per_day,
            'capacity' => $request->has('capacity') ? (int) $request->get('capacity') : $yacht->capacity,
            'rating' => $request->has('rating') ? (float) $request->get('rating') : $yacht->rating,
            'images' => json_encode($imagePaths),
            'amenities' => json_encode($amenities),
        ];
        
        $yacht->update($yachtData);
        
        // Return yacht with decoded images and amenities for frontend
        $yacht->images = $imagePaths;
        $yacht->amenities = $amenities;

        return [
            'message' => 'Yacht updated successfully',
            'yacht' => $yacht
        ];
    }

    /**
     * Delete yacht
     */
    public function deleteYacht($id)
    {
        $yacht = Yacht::findOrFail($id);
        $yacht->delete();

        return [
            'message' => 'Yacht deleted successfully'
        ];
    }

    /**
     * Get yacht availability
     */
    public function getYachtAvailability($id, Request $request)
    {
        $yacht = Yacht::findOrFail($id);
        
        $checkIn = $request->get('check_in');
        $checkOut = $request->get('check_out');
        
        // Simple availability check
        $isAvailable = true; // This would be more complex in a real app
        
        return [
            'yacht' => $yacht,
            'is_available' => $isAvailable,
            'check_in' => $checkIn,
            'check_out' => $checkOut
        ];
    }

    /**
     * Get popular yachts
     */
    public function getPopularYachts(Request $request)
    {
        $limit = $request->get('limit', 6);
        
        $yachts = Yacht::where('is_active', true)
                      ->orderBy('rating', 'desc')
                      ->orderBy('bookings_count', 'desc')
                      ->limit($limit)
                      ->get();

        return [
            'yachts' => $yachts
        ];
    }

    /**
     * Get yachts by location
     */
    public function getYachtsByLocation($location, Request $request)
    {
        $query = Yacht::where('location', $location)
                     ->where('is_active', true);

        // Apply additional filters
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->get('min_rating'));
        }

        if ($request->has('max_price')) {
            $query->where('price_per_day', '<=', $request->get('max_price'));
        }

        $yachts = $query->get();

        return [
            'location' => $location,
            'yachts' => $yachts,
            'count' => $yachts->count()
        ];
    }

    /**
     * Get yachts by size
     */
    public function getYachtsBySize($size, Request $request)
    {
        $query = Yacht::where('is_active', true);

        switch ($size) {
            case 'small':
                $query->where('capacity', '<=', 8);
                break;
            case 'medium':
                $query->whereBetween('capacity', [9, 15]);
                break;
            case 'large':
                $query->where('capacity', '>=', 16);
                break;
        }

        $yachts = $query->get();

        return [
            'size' => $size,
            'yachts' => $yachts,
            'count' => $yachts->count()
        ];
    }

    /**
     * Get yacht statistics
     */
    public function getYachtStats()
    {
        $totalYachts = Yacht::count();
        $activeYachts = Yacht::where('is_active', true)->count();
        $averageRating = Yacht::avg('rating');
        $averagePrice = Yacht::avg('price_per_day');

        return [
            'total_yachts' => $totalYachts,
            'active_yachts' => $activeYachts,
            'average_rating' => round($averageRating, 2),
            'average_price' => round($averagePrice, 2)
        ];
    }
} 