<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Mail\BookingConfirmedMail;
use App\Mail\NewBookingNotificationMail;
use App\Mail\BookingConfirmation;
use Illuminate\Support\Facades\Mail;

class BookingController extends Controller
{
    /**
     * Get user bookings
     */
    public function index(Request $request)
    {
        $query = $request->user()->bookings()->with(['bookable', 'reviews']);

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        // Sort results
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $bookings = $query->orderBy($sortBy, $sortOrder)
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => $bookings,
                'stats' => [
                    'total' => $request->user()->bookings()->count(),
                    'pending' => $request->user()->bookings()->pending()->count(),
                    'confirmed' => $request->user()->bookings()->confirmed()->count(),
                    'cancelled' => $request->user()->bookings()->cancelled()->count(),
                ]
            ]
        ]);
    }

    /**
     * Create new booking
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bookable_type' => 'required|in:hotel,yacht',
            'bookable_id' => 'required|integer',
            'check_in_date' => 'required|date|after:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'guests_count' => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
            'payment_method' => 'required|in:credit_card,paypal,bank_transfer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Determine bookable type
            $bookableType = $request->bookable_type === 'hotel' ? Hotel::class : Yacht::class;
            
            // Use database transaction with locking to prevent double booking
            $booking = DB::transaction(function () use ($request, $bookableType) {
                // Lock the bookable resource to prevent race conditions
                $bookable = $bookableType::lockForUpdate()->findOrFail($request->bookable_id);

                // Re-check availability within the transaction (critical for race condition prevention)
                if (!$bookable->isAvailable($request->check_in_date, $request->check_out_date)) {
                    throw new \Exception('Selected dates are not available');
                }

                // Calculate total price
                $duration = \Carbon\Carbon::parse($request->check_in_date)
                    ->diffInDays($request->check_out_date);
                
                $priceField = $bookableType === Hotel::class ? 'price_per_night' : 'price_per_day';
                $totalPrice = $bookable->$priceField * $duration;

                // Create booking within the same transaction
                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'bookable_type' => $bookableType,
                    'bookable_id' => $bookable->id,
                    'check_in_date' => $request->check_in_date,
                    'check_out_date' => $request->check_out_date,
                    'total_price' => $totalPrice,
                    'status' => 'pending',
                    'guests_count' => $request->guests_count,
                    'special_requests' => $request->special_requests,
                    'payment_method' => $request->payment_method,
                    'payment_status' => 'pending',
                ]);

                // Log the booking creation for security audit
                \Log::info('Booking created', [
                    'user_id' => $request->user()->id,
                    'booking_id' => $booking->id,
                    'bookable_type' => $bookableType,
                    'bookable_id' => $bookable->id,
                    'dates' => $request->check_in_date . ' to ' . $request->check_out_date
                ]);

                return $booking;
            });

            // Send booking confirmation email
            try {
                Mail::to($request->user()->email)->send(new BookingConfirmation($booking));
                \Log::info('Booking confirmation email sent', [
                    'booking_id' => $booking->id,
                    'user_email' => $request->user()->email
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send booking confirmation email', [
                    'booking_id' => $booking->id,
                    'user_email' => $request->user()->email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the booking if email fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully and confirmation email sent',
                'data' => $booking->load('bookable')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific booking
     */
    public function show(Request $request, $id)
    {
        try {
            $booking = $request->user()->bookings()
                ->with(['bookable', 'reviews'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'booking' => $booking,
                    'can_cancel' => $booking->canBeCancelled(),
                    'can_review' => $booking->status === 'confirmed' && 
                                   $booking->check_out_date->isPast() &&
                                   !$booking->reviews()->exists(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update booking
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'check_in_date' => 'sometimes|date|after:today',
            'check_out_date' => 'sometimes|date|after:check_in_date',
            'guests_count' => 'sometimes|integer|min:1',
            'special_requests' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $booking = $request->user()->bookings()->findOrFail($id);

            // Only allow updates for pending bookings
            if ($booking->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update confirmed or cancelled booking'
                ], 400);
            }

            $booking->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Booking updated successfully',
                'data' => $booking->fresh()->load('bookable')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel booking
     */
    public function destroy(Request $request, $id)
    {
        try {
            $booking = $request->user()->bookings()->findOrFail($id);

            if (!$booking->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking cannot be cancelled'
                ], 400);
            }

            $booking->cancel();

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => $booking->fresh()->load('bookable')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get booking statistics
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            $bookings = $user->bookings();

            $stats = [
                'total_bookings' => $bookings->count(),
                'total_spent' => $bookings->sum('total_price'),
                'average_booking_value' => $bookings->avg('total_price'),
                'favorite_destinations' => $this->getFavoriteDestinations($user),
                'booking_trends' => $this->getBookingTrends($user),
                'upcoming_bookings' => $user->bookings()
                    ->confirmed()
                    ->where('check_in_date', '>', now())
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch booking statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get booking calendar
     */
    public function calendar(Request $request, $year, $month)
    {
        try {
            $startDate = \Carbon\Carbon::create($year, $month, 1);
            $endDate = $startDate->copy()->endOfMonth();

            $bookings = $request->user()->bookings()
                ->whereBetween('check_in_date', [$startDate, $endDate])
                ->orWhereBetween('check_out_date', [$startDate, $endDate])
                ->get();

            $calendar = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $dayBookings = $bookings->filter(function ($booking) use ($currentDate) {
                    return $currentDate->between($booking->check_in_date, $booking->check_out_date);
                });

                $calendar[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day' => $currentDate->day,
                    'bookings' => $dayBookings->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'name' => $booking->bookable->name,
                            'type' => class_basename($booking->bookable_type),
                            'status' => $booking->status,
                        ];
                    }),
                ];

                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => $calendar
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch booking calendar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Get all bookings
     */
    public function adminIndex(Request $request)
    {
        $query = Booking::with(['user', 'bookable']);

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $bookings = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $bookings
        ]);
    }

    /**
     * Admin: Confirm booking
     */
    public function confirm(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($id);
            $booking->confirm();

            // إرسال إيميل للمستخدم
            Mail::to($booking->user->email)->send(new BookingConfirmedMail($booking));
            // إرسال إيميل للأدمن
            Mail::to(config('mail.admin_email', 'admin@tourism.com'))->send(new NewBookingNotificationMail($booking));

            return response()->json([
                'success' => true,
                'message' => 'Booking confirmed successfully',
                'data' => $booking->fresh()->load(['user', 'bookable'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Cancel booking
     */
    public function adminCancel(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($id);
            $booking->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => $booking->fresh()->load(['user', 'bookable'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Get booking statistics
     */
    public function adminStats()
    {
        try {
            $stats = [
                'total_bookings' => Booking::count(),
                'total_revenue' => Booking::sum('total_price'),
                'average_booking_value' => Booking::avg('total_price'),
                'status_distribution' => [
                    'pending' => Booking::pending()->count(),
                    'confirmed' => Booking::confirmed()->count(),
                    'cancelled' => Booking::cancelled()->count(),
                ],
                'monthly_trends' => Booking::select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(total_price) as revenue')
                )
                    ->where('created_at', '>=', now()->subMonths(12))
                    ->groupBy('month', 'year')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch admin statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get favorite destinations
     */
    private function getFavoriteDestinations($user)
    {
        return $user->bookings()
            ->with('bookable')
            ->select('bookable_type', 'bookable_id', DB::raw('count(*) as count'))
            ->groupBy('bookable_type', 'bookable_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                return [
                    'name' => $booking->bookable->name,
                    'type' => class_basename($booking->bookable_type),
                    'count' => $booking->count,
                ];
            });
    }

    /**
     * Get booking trends
     */
    private function getBookingTrends($user)
    {
        return $user->bookings()
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('YEAR(created_at) as year'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month', 'year')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($booking) {
                return [
                    'month' => $booking->month,
                    'year' => $booking->year,
                    'count' => $booking->count,
                ];
            });
    }
} 