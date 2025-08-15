<?php

namespace App\Http\Controllers;

use App\ViewModels\HotelViewModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HotelController extends Controller
{
    protected $hotelViewModel;

    public function __construct(HotelViewModel $hotelViewModel)
    {
        $this->hotelViewModel = $hotelViewModel;
    }

    /**
     * Get all hotels
     */
    public function index(Request $request)
    {
        try {
            $result = $this->hotelViewModel->getHotels($request);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch hotels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific hotel
     */
    public function show($id)
    {
        try {
            $result = $this->hotelViewModel->getHotel($id);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hotel not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create new hotel (Admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'city' => 'required|string|max:100',
            'location' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'rating' => 'required|numeric|min:0|max:5',
            'amenities' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->hotelViewModel->createHotel($request);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['hotel']
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create hotel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update hotel (Admin only)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'city' => 'sometimes|string|max:100',
            'address' => 'sometimes|string|max:500',
            'price_per_night' => 'sometimes|numeric|min:0',
            'rating' => 'nullable|numeric|min:0|max:5',
            'stars' => 'nullable|integer|min:1|max:5',
            'images' => 'nullable|array',
            'amenities' => 'nullable|array',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->hotelViewModel->updateHotel($id, $request);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['hotel']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update hotel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete hotel (Admin only)
     */
    public function destroy($id)
    {
        try {
            $result = $this->hotelViewModel->deleteHotel($id);

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete hotel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check hotel availability
     */
    public function availability(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'check_in_date' => 'required|date|after:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->hotelViewModel->getAvailability(
                $id,
                $request->check_in_date,
                $request->check_out_date
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular hotels
     */
    public function popular(Request $request)
    {
        try {
            $limit = $request->get('limit', 6);
            $hotels = $this->hotelViewModel->getPopularHotels($limit);

            return response()->json([
                'success' => true,
                'data' => $hotels
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular hotels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get hotels by city
     */
    public function byCity(Request $request, $city)
    {
        try {
            $limit = $request->get('limit', 10);
            $hotels = $this->hotelViewModel->getHotelsByCity($city, $limit);

            return response()->json([
                'success' => true,
                'data' => $hotels
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch hotels by city',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}