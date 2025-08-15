<?php

namespace App\ViewModels;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\PasswordResetMail;

class AuthViewModel
{
    /**
     * Register new user
     */
    public function register(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    /**
     * Login user
     */
    public function login(array $data)
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return [
            'message' => 'Logged out successfully'
        ];
    }

    /**
     * Get current user
     */
    public function getUser(Request $request)
    {
        return $request->user();
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        // Get only the fields that are present in the request
        $updateData = [];
        
        if ($request->has('name')) {
            $updateData['name'] = $request->input('name');
        }
        
        if ($request->has('email')) {
            $updateData['email'] = $request->input('email');
        }
        
        if ($request->has('phone')) {
            $updateData['phone'] = $request->input('phone');
        }
        
        if ($request->has('address')) {
            $updateData['address'] = $request->input('address');
        }
        
        if ($request->has('avatar')) {
            $updateData['avatar'] = $request->input('avatar');
        }

        // Update user with the provided data
        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // Return fresh user data
        return $user->fresh();
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw new \Exception('Current password is incorrect');
        }

        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        return [
            'message' => 'Password changed successfully'
        ];
    }

    /**
     * Get all users (Admin only)
     */
    public function getUsers(Request $request)
    {
        $query = User::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filter by role if provided
        if ($request->filled('role') && in_array($request->get('role'), ['admin', 'user'])) {
            $query->where('role', $request->get('role'));
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
        $perPage = $request->get('per_page', 10);
        $users = $query->paginate($perPage);

        return [
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ];
    }
    
    /**
     * Update user role (Admin only)
     */
    public function updateUserRole($id, $role)
    {
        $user = User::findOrFail($id);
        
        // تحديث دور المستخدم
        $user->update([
            'role' => $role
        ]);
        
        return [
            'message' => 'تم تحديث صلاحية المستخدم بنجاح',
            'user' => $user
        ];
    }

    /**
     * Update user (Admin only)
     */
    public function updateUser($id, $request)
    {
        $user = User::findOrFail($id);
        
        // Handle image upload with enhanced security
        $imagePath = $user->avatar; // Keep existing avatar if no new image
        if ($request->hasFile('avatar')) {
            $image = $request->file('avatar');
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
                
                // Delete old avatar if exists
                if ($user->avatar && file_exists(public_path($user->avatar))) {
                    unlink(public_path($user->avatar));
                }
                
                // Generate secure filename with hash to prevent path traversal
                $extension = $image->getClientOriginalExtension();
                $filename = hash('sha256', $image->getClientOriginalName() . time() . $user->id) . '.' . $extension;
                
                // Upload new avatar
                $image->move(public_path('storage/users'), $filename);
                $imagePath = '/storage/users/' . $filename;
                
                // Log avatar upload for security audit
                \Log::info('User avatar uploaded', [
                    'user_id' => $user->id,
                    'filename' => $filename,
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type' => $realMimeType,
                    'size' => $image->getSize()
                ]);
            }
        }
        
        // تحديث بيانات المستخدم
        $updateData = [];
        if ($request->has('name')) {
            $updateData['name'] = $request->get('name');
        }
        if ($request->has('email')) {
            $updateData['email'] = $request->get('email');
        }
        if ($request->has('role')) {
            $updateData['role'] = $request->get('role');
        }
        if ($imagePath) {
            $updateData['avatar'] = $imagePath;
        }
        
        $user->update($updateData);
        
        return $user;
    }

    /**
     * Delete user (Admin only)
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // تأكد من عدم حذف الأدمن الحالي
        if (Auth::id() == $id) {
            throw new \Exception('لا يمكن حذف حسابك الشخصي');
        }
        
        $user->delete();
        
        return [
            'message' => 'تم حذف المستخدم بنجاح'
        ];
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email)
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Generate reset token
        $token = Str::random(64);
        
        // Store token in password_resets table
        DB::table('password_resets')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => Carbon::now()
            ]
        );

        // Send email
        Mail::to($email)->send(new PasswordResetMail($user, $token));

        return [
            'message' => 'Password reset email sent successfully'
        ];
    }

    /**
     * Reset password with token
     */
    public function resetPassword($token, $email, $password)
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Check if token exists and is valid
        $passwordReset = DB::table('password_resets')
            ->where('email', $email)
            ->first();

        if (!$passwordReset) {
            throw new \Exception('Invalid reset token');
        }

        // Check if token matches
        if (!Hash::check($token, $passwordReset->token)) {
            throw new \Exception('Invalid reset token');
        }

        // Check if token is not expired (24 hours)
        if (Carbon::parse($passwordReset->created_at)->addHours(24)->isPast()) {
            throw new \Exception('Reset token has expired');
        }

        // Update user password
        $user->update([
            'password' => Hash::make($password)
        ]);

        // Delete the reset token
        DB::table('password_resets')->where('email', $email)->delete();

        return [
            'message' => 'Password reset successfully'
        ];
    }
}