<?php

namespace App\ViewModels;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Yacht;
use Illuminate\Http\Request;

class BookingViewModel
{
    /**
     * Get user bookings
     */
    public function getUserBookings(Request $request)
    {
        $user = $request->user();
        
        $query = $user->bookings()->with(['bookable']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('type')) {
            $query->where('bookable_type', $request->get('type'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $bookings = $query->paginate(10);

        return [
            'bookings' => $bookings->items(),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ]
        ];
    }

    /**
     * Create new booking
     */
    public function createBooking(array $data)
    {
        // Validate bookable exists
        $bookableType = $data['bookable_type'];
        $bookableId = $data['bookable_id'];

        if ($bookableType === 'hotel') {
            $bookable = Hotel::findOrFail($bookableId);
        } elseif ($bookableType === 'yacht') {
            $bookable = Yacht::findOrFail($bookableId);
        } else {
            throw new \Exception('Invalid bookable type');
        }

        // Calculate total price
        $checkIn = new \DateTime($data['check_in_date']);
        $checkOut = new \DateTime($data['check_out_date']);
        $days = $checkIn->diff($checkOut)->days;

        $pricePerDay = $bookableType === 'hotel' ? $bookable->price_per_night : $bookable->price_per_day;
        $totalPrice = $pricePerDay * $days * ($data['quantity'] ?? 1);

        $booking = Booking::create([
            'user_id' => $data['user_id'],
            'bookable_type' => $bookableType,
            'bookable_id' => $bookableId,
            'check_in_date' => $data['check_in_date'],
            'check_out_date' => $data['check_out_date'],
            'quantity' => $data['quantity'] ?? 1,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return [
            'booking' => $booking->load(['bookable']),
            'message' => 'Booking created successfully'
        ];
    }

    /**
     * Get specific booking
     */
    public function getBooking($id, Request $request)
    {
        $user = $request->user();
        $booking = $user->bookings()->with(['bookable'])->findOrFail($id);

        return $booking;
    }

    /**
     * Update booking
     */
    public function updateBooking($id, array $data, Request $request)
    {
        $user = $request->user();
        $booking = $user->bookings()->findOrFail($id);

        // Only allow updates if booking is pending
        if ($booking->status !== 'pending') {
            throw new \Exception('Cannot update confirmed or cancelled booking');
        }

        $booking->update($data);

        return [
            'booking' => $booking->load(['bookable']),
            'message' => 'Booking updated successfully'
        ];
    }

    /**
     * Cancel booking
     */
    public function cancelBooking($id, Request $request)
    {
        $user = $request->user();
        $booking = $user->bookings()->findOrFail($id);

        if (!$booking->canBeCancelled()) {
            throw new \Exception('Booking cannot be cancelled');
        }

        $booking->cancel();

        return [
            'booking' => $booking->load(['bookable']),
            'message' => 'Booking cancelled successfully'
        ];
    }

    /**
     * Get booking statistics
     */
    public function getBookingStats(Request $request)
    {
        $user = $request->user();
        
        $totalBookings = $user->bookings()->count();
        $pendingBookings = $user->bookings()->where('status', 'pending')->count();
        $confirmedBookings = $user->bookings()->where('status', 'confirmed')->count();
        $cancelledBookings = $user->bookings()->where('status', 'cancelled')->count();
        $totalSpent = $user->bookings()->where('status', 'confirmed')->sum('total_price');

        return [
            'total_bookings' => $totalBookings,
            'pending_bookings' => $pendingBookings,
            'confirmed_bookings' => $confirmedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'total_spent' => $totalSpent,
        ];
    }

    /**
     * Get booking calendar
     */
    public function getBookingCalendar($year, $month, Request $request)
    {
        $user = $request->user();
        
        $bookings = $user->bookings()
                        ->whereYear('check_in_date', $year)
                        ->whereMonth('check_in_date', $month)
                        ->get();

        $calendar = [];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dayBookings = $bookings->filter(function($booking) use ($date) {
                return $booking->check_in_date <= $date && $booking->check_out_date >= $date;
            });

            $calendar[$day] = [
                'date' => $date,
                'bookings' => $dayBookings->values(),
                'count' => $dayBookings->count()
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'calendar' => $calendar
        ];
    }

    /**
     * Admin: Get all bookings
     */
    public function getAllBookings(Request $request)
    {
        $query = Booking::with(['user', 'bookable']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('type')) {
            $query->where('bookable_type', $request->get('type'));
        }

        if ($request->has('date_from')) {
            $query->where('check_in_date', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('check_out_date', '<=', $request->get('date_to'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $bookings = $query->paginate(20);

        return [
            'bookings' => $bookings->items(),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ]
        ];
    }

    /**
     * Admin: Confirm booking
     */
    public function confirmBooking($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->confirm();

        return [
            'booking' => $booking->load(['user', 'bookable']),
            'message' => 'Booking confirmed successfully'
        ];
    }

    /**
     * Admin: Cancel booking
     */
    public function adminCancelBooking($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->cancel();

        return [
            'booking' => $booking->load(['user', 'bookable']),
            'message' => 'Booking cancelled successfully'
        ];
    }

    /**
     * Admin: Get booking statistics
     */
    public function getAdminBookingStats()
    {
        $totalBookings = Booking::count();
        $pendingBookings = Booking::where('status', 'pending')->count();
        $confirmedBookings = Booking::where('status', 'confirmed')->count();
        $cancelledBookings = Booking::where('status', 'cancelled')->count();
        $totalRevenue = Booking::where('status', 'confirmed')->sum('total_price');

        $hotelBookings = Booking::where('bookable_type', 'App\Models\Hotel')->count();
        $yachtBookings = Booking::where('bookable_type', 'App\Models\Yacht')->count();

        return [
            'total_bookings' => $totalBookings,
            'pending_bookings' => $pendingBookings,
            'confirmed_bookings' => $confirmedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'total_revenue' => $totalRevenue,
            'hotel_bookings' => $hotelBookings,
            'yacht_bookings' => $yachtBookings,
        ];
    }
} 