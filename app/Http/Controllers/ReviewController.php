<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Hotel;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Get all reviews
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'reviewable']);

        // Filter by rating
        if ($request->has('rating')) {
            $query->byRating($request->rating);
        }

        // Filter by minimum rating
        if ($request->has('min_rating')) {
            $query->byMinRating($request->min_rating);
        }

        // Filter by reviewable type
        if ($request->has('type')) {
            $type = $request->type === 'hotel' ? Hotel::class : Yacht::class;
            $query->where('reviewable_type', $type);
        }

        // Filter by reviewable ID
        if ($request->has('reviewable_id')) {
            $query->where('reviewable_id', $request->reviewable_id);
        }

        // Sort results
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $reviews = $query->orderBy($sortBy, $sortOrder)
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Get specific review
     */
    public function show($id)
    {
        try {
            $review = Review::with(['user', 'reviewable'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $review
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create new review
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reviewable_type' => 'required|in:hotel,yacht',
            'reviewable_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Determine reviewable type
            $reviewableType = $request->reviewable_type === 'hotel' ? Hotel::class : Yacht::class;
            $reviewable = $reviewableType::findOrFail($request->reviewable_id);

            // Check if user already reviewed this item
            $existingReview = Review::where('user_id', $request->user()->id)
                ->where('reviewable_type', $reviewableType)
                ->where('reviewable_id', $reviewable->id)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this item'
                ], 400);
            }

            // Check if user has booking for this item (for verified reviews)
            $hasBooking = false;
            if ($request->booking_id) {
                $booking = $request->user()->bookings()
                    ->where('id', $request->booking_id)
                    ->where('bookable_type', $reviewableType)
                    ->where('bookable_id', $reviewable->id)
                    ->where('status', 'confirmed')
                    ->first();
                
                $hasBooking = $booking !== null;
            }

            $review = Review::create([
                'user_id' => $request->user()->id,
                'reviewable_type' => $reviewableType,
                'reviewable_id' => $reviewable->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'booking_id' => $request->booking_id,
                'is_verified' => $hasBooking,
            ]);

            // Update average rating for the reviewable item
            $this->updateAverageRating($reviewable);

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review->load(['user', 'reviewable'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update review
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $review = Review::where('user_id', $request->user()->id)
                ->findOrFail($id);

            $review->update($request->all());

            // Update average rating for the reviewable item
            $this->updateAverageRating($review->reviewable);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review->fresh()->load(['user', 'reviewable'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete review
     */
    public function destroy(Request $request, $id)
    {
        try {
            $review = Review::where('user_id', $request->user()->id)
                ->findOrFail($id);

            $reviewable = $review->reviewable;
            $review->delete();

            // Update average rating for the reviewable item
            $this->updateAverageRating($reviewable);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews for specific hotel
     */
    public function hotelReviews(Request $request, $hotelId)
    {
        try {
            $hotel = Hotel::findOrFail($hotelId);
            $reviews = $hotel->reviews()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => [
                    'hotel' => $hotel,
                    'reviews' => $reviews,
                    'stats' => [
                        'total_reviews' => $hotel->reviews()->count(),
                        'average_rating' => $hotel->average_rating,
                        'rating_distribution' => $this->getRatingDistribution($hotel),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch hotel reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews for specific yacht
     */
    public function yachtReviews(Request $request, $yachtId)
    {
        try {
            $yacht = Yacht::findOrFail($yachtId);
            $reviews = $yacht->reviews()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => [
                    'yacht' => $yacht,
                    'reviews' => $reviews,
                    'stats' => [
                        'total_reviews' => $yacht->reviews()->count(),
                        'average_rating' => $yacht->average_rating,
                        'rating_distribution' => $this->getRatingDistribution($yacht),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch yacht reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user reviews
     */
    public function userReviews(Request $request)
    {
        try {
            $reviews = $request->user()->reviews()
                ->with('reviewable')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update average rating for reviewable item
     */
    private function updateAverageRating($reviewable)
    {
        $averageRating = $reviewable->reviews()->avg('rating');
        $reviewable->update(['rating' => $averageRating ?? 0]);
    }

    /**
     * Get rating distribution
     */
    private function getRatingDistribution($reviewable)
    {
        return $reviewable->reviews()
            ->select('rating', \DB::raw('count(*) as count'))
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get()
            ->pluck('count', 'rating')
            ->toArray();
    }
} 