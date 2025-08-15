<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\YachtController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ReviewController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes with rate limiting for security
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// Hotels public routes
Route::get('/hotels', [HotelController::class, 'index']);
Route::get('/hotels/popular', [HotelController::class, 'popular']);
Route::get('/hotels/city/{city}', [HotelController::class, 'byCity']);
Route::get('/hotels/{id}', [HotelController::class, 'show']);
Route::get('/hotels/{id}/availability', [HotelController::class, 'availability']);

// Yachts public routes
Route::get('/yachts', [YachtController::class, 'index']);
Route::get('/yachts/popular', [YachtController::class, 'popular']);
Route::get('/yachts/location/{location}', [YachtController::class, 'byLocation']);
Route::get('/yachts/size/{size}', [YachtController::class, 'bySize']);
Route::get('/yachts/{id}', [YachtController::class, 'show']);
Route::get('/yachts/{id}/availability', [YachtController::class, 'availability']);

// Reviews public routes
Route::get('/reviews', [ReviewController::class, 'index']);
Route::get('/reviews/{id}', [ReviewController::class, 'show']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::get('/auth/bookings', [AuthController::class, 'getUserBookings']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    // Booking routes with rate limiting
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::post('/bookings', [BookingController::class, 'store']); // Limit booking creation
    });
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::put('/bookings/{id}', [BookingController::class, 'update']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    Route::get('/bookings/stats/summary', [BookingController::class, 'stats']);
    Route::get('/bookings/calendar/{year}/{month}', [BookingController::class, 'calendar']);

    // Review routes
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    // Admin routes (require admin role)
    Route::middleware('admin')->group(function () {
        
        // Hotel management
        Route::post('/hotels', [HotelController::class, 'store']);
        Route::put('/hotels/{id}', [HotelController::class, 'update']);
        Route::delete('/hotels/{id}', [HotelController::class, 'destroy']);

        // Yacht management
        Route::post('/yachts', [YachtController::class, 'store']);
        Route::put('/yachts/{id}', [YachtController::class, 'update']);
        Route::delete('/yachts/{id}', [YachtController::class, 'destroy']);

        // Booking management
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
        Route::put('/admin/bookings/{id}/confirm', [BookingController::class, 'confirm']);
        Route::put('/admin/bookings/{id}/cancel', [BookingController::class, 'adminCancel']);

        // Statistics
        Route::get('/admin/stats/overview', [BookingController::class, 'adminStats']);
        Route::get('/admin/stats/hotels', [HotelController::class, 'stats']);
        Route::get('/admin/stats/yachts', [YachtController::class, 'stats']);

        // User management
        Route::get('/admin/users', [AuthController::class, 'allUsers']);
        Route::put('/users/{id}/role', [AuthController::class, 'updateUserRole']);
        Route::put('/users/{id}', [AuthController::class, 'updateUser']);
        Route::delete('/users/{id}', [AuthController::class, 'deleteUser']);
    });
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});