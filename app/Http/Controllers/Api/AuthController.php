<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 *
 * APIs for managing authentication
 */
class AuthController extends Controller
{
    /**
     * Login
     *
     * Authenticate a user and return an API token.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The user's email address. Example: admin@fleetmanager.fr
     * @bodyParam password string required The user's password. Example: password
     * @bodyParam device_name string The name of the device (for token identification). Example: iPhone 13
     *
     * @response 200 {
     *   "token": "1|abc123...",
     *   "user": {
     *     "id": 1,
     *     "name": "Admin User",
     *     "email": "admin@fleetmanager.fr",
     *     "organization_id": 1,
     *     "is_active": true
     *   }
     * }
     *
     * @response 422 {
     *   "message": "The provided credentials are incorrect."
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact your administrator.'],
            ]);
        }

        // Revoke all existing tokens (optional - comment out for multiple sessions)
        // $user->tokens()->delete();

        $token = $user->createToken(
            $request->device_name ?? 'api-token'
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization_id' => $user->organization_id,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Register
     *
     * Register a new user account (if registration is enabled).
     *
     * @unauthenticated
     *
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password (min 8 characters). Example: secret123
     * @bodyParam password_confirmation string required Password confirmation. Example: secret123
     * @bodyParam organization_id integer required The organization ID. Example: 1
     *
     * @response 201 {
     *   "message": "User registered successfully",
     *   "token": "2|xyz789...",
     *   "user": {
     *     "id": 2,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "organization_id": 1
     *   }
     * }
     *
     * @response 422 {
     *   "message": "The email has already been taken.",
     *   "errors": {
     *     "email": ["The email has already been taken."]
     *   }
     * }
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_id' => 'required|exists:organizations,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organization_id' => $validated['organization_id'],
            'is_active' => true,
        ]);

        // Assign default 'viewer' role to new users
        $user->assignRole('viewer');

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization_id' => $user->organization_id,
                'roles' => ['viewer'],
            ],
        ], 201);
    }

    /**
     * Logout
     *
     * Revoke the current access token.
     *
     * @response 200 {
     *   "message": "Logged out successfully"
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout All Devices
     *
     * Revoke all access tokens for the current user.
     *
     * @response 200 {
     *   "message": "Logged out from all devices successfully"
     * }
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices successfully',
        ]);
    }

    /**
     * Current User
     *
     * Get the authenticated user's profile information.
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Admin User",
     *     "email": "admin@fleetmanager.fr",
     *     "organization_id": 1,
     *     "is_active": true,
     *     "roles": ["super-admin"],
     *     "permissions": ["organizations.view", "organizations.create", "..."],
     *     "created_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('organization', 'roles', 'permissions');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization_id' => $user->organization_id,
                'is_active' => $user->is_active,
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                    'subscription_plan' => $user->organization->subscription_plan,
                ] : null,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update Profile
     *
     * Update the authenticated user's profile information.
     *
     * @bodyParam name string The user's name. Example: John Smith
     * @bodyParam email string The user's email address. Example: john.smith@example.com
     * @bodyParam current_password string required Current password (required when changing password). Example: oldpassword
     * @bodyParam password string New password (min 8 characters). Example: newpassword123
     * @bodyParam password_confirmation string New password confirmation. Example: newpassword123
     *
     * @response 200 {
     *   "message": "Profile updated successfully",
     *   "user": {
     *     "id": 1,
     *     "name": "John Smith",
     *     "email": "john.smith@example.com"
     *   }
     * }
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'current_password' => 'required_with:password',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        // Verify current password if changing password
        if (isset($validated['password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The provided password is incorrect.'],
                ]);
            }
            $validated['password'] = Hash::make($validated['password']);
        }

        // Remove current_password from update data
        unset($validated['current_password']);
        unset($validated['password_confirmation']);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
