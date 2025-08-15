<?php

namespace App\Http\Controllers;

use App\ViewModels\AuthViewModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected $authViewModel;

    public function __construct(AuthViewModel $authViewModel)
    {
        $this->authViewModel = $authViewModel;
    }

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authViewModel->register($request->all());

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $result['user'],
                    'token' => $result['token']
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authViewModel->login($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $result['user'],
                    'token' => $result['token']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            $result = $this->authViewModel->logout($request);

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user
     */
    public function user(Request $request)
    {
        try {
            $user = $this->authViewModel->getUser($request);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            // Clean and prepare data before validation
            $data = $request->all();
            
            // Clean up any array values that should be strings
            foreach (['name', 'email', 'phone', 'address', 'avatar'] as $field) {
                if (isset($data[$field]) && is_array($data[$field])) {
                    $data[$field] = is_array($data[$field]) ? (string) $data[$field][0] : $data[$field];
                }
                // Clean up strings - trim spaces and special characters
                if (isset($data[$field]) && is_string($data[$field])) {
                    $data[$field] = trim($data[$field]);
                    // For phone numbers, remove common formatting characters
                    if ($field === 'phone') {
                        $data[$field] = preg_replace('/[^0-9+\-\s()]/', '', $data[$field]);
                        $data[$field] = trim($data[$field]);
                    }
                }
                // Remove empty strings
                if (isset($data[$field]) && $data[$field] === '') {
                    unset($data[$field]);
                }
            }
            
            // Simple validation - very relaxed rules
            $rules = [
                'name' => 'sometimes|nullable|string|max:255',
                'email' => 'sometimes|nullable|email|max:255',
                'phone' => 'sometimes|nullable|string|max:100', // Increased for very long international numbers
                'address' => 'sometimes|nullable|string|max:1000',
                'avatar' => 'sometimes|nullable|string|max:500000',
            ];

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                \Log::error('Profile validation failed: ' . json_encode($validator->errors()));
                \Log::error('Request data: ' . json_encode($request->all()));
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            // Use cleaned data from validation
            $updateData = [];
            
            // Only update fields that are provided and different
            foreach ($data as $key => $value) {
                if (in_array($key, ['name', 'email', 'phone', 'address', 'avatar']) && !empty($value)) {
                    // Check if value is actually different to avoid unnecessary encryption
                    $currentValue = $user->$key;
                    if ($currentValue !== $value) {
                        $updateData[$key] = $value;
                    }
                }
            }
            
            // Check email uniqueness if email is being updated
            if (isset($updateData['email'])) {
                $existingUser = \App\Models\User::where('email', $updateData['email'])
                    ->where('id', '!=', $user->id)
                    ->first();
                    
                if ($existingUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email already exists',
                    ], 422);
                }
            }
            
            // Update only if there are changes
            if (!empty($updateData)) {
                $user->update($updateData);
            }
            
            // Get fresh data with proper decryption
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? '',
                        'address' => $user->address ?? '',
                        'avatar' => $user->avatar ?? '',
                        'role' => $user->role,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Profile update error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث الملف الشخصي',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authViewModel->changePassword($request);

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get all users (Admin only)
     */
    public function allUsers(Request $request)
    {
        try {
            $users = $this->authViewModel->getUsers($request);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update user role (Admin only)
     */
    public function updateUserRole(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:admin,user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authViewModel->updateUserRole($id, $request->role);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['user']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user (Admin only)
     */
    public function updateUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'role' => 'sometimes|required|string|in:admin,user',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authViewModel->updateUser($id, $request);
            return response()->json(['success' => true, 'message' => 'User updated successfully', 'data' => $result]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user (Admin only)
     */
    public function deleteUser($id)
    {
        try {
            $result = $this->authViewModel->deleteUser($id);

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send password reset email
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authViewModel->sendPasswordResetEmail($request->email);

            return response()->json([
                'success' => true,
                'message' => 'Password reset email sent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authViewModel->resetPassword(
                $request->token,
                $request->email,
                $request->password
            );

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get user bookings
     */
    public function getUserBookings(Request $request)
    {
        try {
            $user = $request->user();
            $bookings = $user->bookings()->with(['bookable'])->orderBy('created_at', 'desc')->get();

            // Transform bookings data for frontend
            $transformedBookings = $bookings->map(function ($booking) {
                $bookableData = $booking->bookable;
                $bookingData = [
                    'id' => $booking->id,
                    'check_in' => $booking->check_in,
                    'check_out' => $booking->check_out,
                    'guests' => $booking->guests,
                    'total_price' => $booking->total_price,
                    'status' => $booking->status,
                    'special_requests' => $booking->special_requests,
                    'payment_method' => $booking->payment_method,
                    'created_at' => $booking->created_at,
                    'bookable_type' => $booking->bookable_type,
                ];

                if ($bookableData) {
                    if ($booking->bookable_type === 'App\\Models\\Hotel') {
                        $bookingData['hotel_name'] = $bookableData->name;
                        $bookingData['hotel_city'] = $bookableData->city;
                        $bookingData['type'] = 'hotel';
                    } elseif ($booking->bookable_type === 'App\\Models\\Yacht') {
                        $bookingData['yacht_name'] = $bookableData->name;
                        $bookingData['yacht_location'] = $bookableData->location;
                        $bookingData['type'] = 'yacht';
                    }
                }

                return $bookingData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'bookings' => $transformedBookings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}