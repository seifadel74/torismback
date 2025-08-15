<?php

namespace App\Http\Controllers;

use App\Models\Yacht;
use App\ViewModels\YachtViewModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class YachtController extends Controller
{
    protected $yachtViewModel;

    public function __construct(YachtViewModel $yachtViewModel)
    {
        $this->yachtViewModel = $yachtViewModel;
    }
    /**
     * Get all yachts
     */
    public function index(Request $request)
    {
        $query = Yacht::with(['reviews' => function ($query) {
            $query->latest()->limit(5);
        }]);

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by location
        if ($request->has('location')) {
            $query->byLocation($request->location);
        }

        // Filter by price range
        if ($request->has('min_price') && $request->has('max_price')) {
            $query->byPriceRange($request->min_price, $request->max_price);
        }

        // Filter by rating
        if ($request->has('rating')) {
            $query->byRating($request->rating);
        }

        // Filter by capacity
        if ($request->has('capacity')) {
            $query->byCapacity($request->capacity);
        }

        // Sort results
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');

        switch ($sortBy) {
            case 'price':
                $query->orderBy('price_per_day', $sortOrder);
                break;
            case 'rating':
                $query->orderBy('rating', $sortOrder);
                break;
            case 'popularity':
                $query->withCount('bookings')->orderBy('bookings_count', $sortOrder);
                break;
            case 'capacity':
                $query->orderBy('capacity', $sortOrder);
                break;
            default:
                $query->orderBy('name', $sortOrder);
        }

        $yachts = $query->get();

        return response()->json([
            'success' => true,
            'data' => $yachts
        ]);
    }

    /**
     * Get specific yacht
     */
    public function show($id)
    {
        try {
            $yacht = Yacht::with(['reviews.user', 'reviews' => function ($query) {
                $query->latest();
            }])->findOrFail($id);

            $reviews = $yacht->reviews()->with('user')->paginate(10);

            return response()->json([
                'success' => true,
                'data' => [
                    'yacht' => $yacht,
                    'reviews' => $reviews,
                    'stats' => [
                        'total_reviews' => $yacht->reviews()->count(),
                        'average_rating' => $yacht->average_rating,
                        'size_category' => $yacht->size_category,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Yacht not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create new yacht (Admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:100',
            'price_per_day' => 'required|numeric|min:0',
            'rating' => 'nullable|numeric|min:0|max:5',
            'images' => 'nullable|array',
            'amenities' => 'nullable|array',
            'capacity' => 'required|integer|min:1',
            'length' => 'nullable|numeric|min:0',
            'year_built' => 'nullable|integer|min:1900',
            'crew_size' => 'nullable|integer|min:0',
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
            $result = $this->yachtViewModel->createYacht($request);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['yacht']
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create yacht',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update yacht (Admin only)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'location' => 'sometimes|string|max:100',
            'price_per_day' => 'sometimes|numeric|min:0',
            'rating' => 'nullable|numeric|min:0|max:5',
            'images' => 'nullable|array',
            'amenities' => 'nullable|array',
            'capacity' => 'sometimes|integer|min:1',
            'length' => 'nullable|numeric|min:0',
            'year_built' => 'nullable|integer|min:1900',
            'crew_size' => 'nullable|integer|min:0',
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
            $result = $this->yachtViewModel->updateYacht($id, $request);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['yacht']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update yacht',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete yacht (Admin only)
     */
    public function destroy($id)
    {
        try {
            $yacht = Yacht::findOrFail($id);
            $yacht->delete();

            return response()->json([
                'success' => true,
                'message' => 'Yacht deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete yacht',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check yacht availability
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
            $yacht = Yacht::findOrFail($id);
            
            $result = [
                'yacht' => $yacht,
                'available' => $yacht->isAvailable($request->check_in_date, $request->check_out_date),
                'check_in' => $request->check_in_date,
                'check_out' => $request->check_out_date,
                'duration' => \Carbon\Carbon::parse($request->check_in_date)->diffInDays($request->check_out_date),
                'total_price' => $yacht->price_per_day * \Carbon\Carbon::parse($request->check_in_date)->diffInDays($request->check_out_date),
            ];

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
     * Get popular yachts
     */
    public function popular(Request $request)
    {
        try {
            $limit = $request->get('limit', 6);
            $yachts = Yacht::withCount('bookings')
                ->orderBy('bookings_count', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $yachts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular yachts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get yachts by location
     */
    public function byLocation(Request $request, $location)
    {
        try {
            $limit = $request->get('limit', 10);
            $yachts = Yacht::byLocation($location)
                ->with('reviews')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $yachts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch yachts by location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get yachts by size
     */
    public function bySize(Request $request, $size)
    {
        try {
            $limit = $request->get('limit', 10);
            $query = Yacht::query();

            switch (strtolower($size)) {
                case 'small':
                    $query->where('length', '<', 30);
                    break;
                case 'medium':
                    $query->whereBetween('length', [30, 50]);
                    break;
                case 'large':
                    $query->where('length', '>', 50);
                    break;
            }

            $yachts = $query->with('reviews')->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $yachts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch yachts by size',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get yacht statistics
     */
    public function stats()
    {
        try {
            $stats = [
                'total_yachts' => Yacht::count(),
                'average_price' => Yacht::avg('price_per_day'),
                'average_rating' => Yacht::avg('rating'),
                'size_distribution' => [
                    'small' => Yacht::where('length', '<', 30)->count(),
                    'medium' => Yacht::whereBetween('length', [30, 50])->count(),
                    'large' => Yacht::where('length', '>', 50)->count(),
                ],
                'top_locations' => Yacht::select('location', \DB::raw('count(*) as count'))
                    ->groupBy('location')
                    ->orderBy('count', 'desc')
                    ->limit(5)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch yacht statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 