<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException as LaravelValidationException;

/**
 * Auth Service
 * 
 * Handles all business logic for authentication operations.
 * 
 * Responsibilities:
 * - User registration
 * - User authentication
 * - Token management
 * - Profile updates
 * 
 * Does NOT contain:
 * - Direct User model queries (uses User model directly for now, could use UserRepository if created)
 * - HTTP response handling
 */
class AuthService
{
    /**
     * Register a new user.
     * 
     * Handles:
     * - User creation
     * - Password hashing
     * - Token generation
     *
     * @param array $data User data (name, email, password, password_confirmation)
     * @return array User data with token
     * @throws ValidationException If validation fails
     */
    public function register(array $data): array
    {
        // Create user (business logic - user creation)
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Generate token (business logic - token generation)
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->toArray(),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Authenticate user and generate token.
     * 
     * Handles:
     * - User lookup
     * - Password verification
     * - Token generation
     *
     * @param string $email
     * @param string $password
     * @return array User data with token
     * @throws ValidationException If credentials are invalid
     */
    public function login(string $email, string $password): array
    {
        // Find user (business logic - user lookup)
        $user = User::where('email', $email)->first();

        // Verify password (business logic - authentication)
        if (!$user || !Hash::check($password, $user->password)) {
            throw new ValidationException(
                'The provided credentials are incorrect.',
                ['email' => ['The provided credentials are incorrect.']]
            );
        }

        // Generate token (business logic - token generation)
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->toArray(),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Get authenticated user.
     * 
     * Business logic: Returns current authenticated user.
     *
     * @param User $user
     * @return array User data
     */
    public function getUser(User $user): array
    {
        return [
            'user' => $user->toArray(),
        ];
    }

    /**
     * Update user profile.
     * 
     * Handles:
     * - Profile data validation (email uniqueness)
     * - User update
     *
     * @param User $user
     * @param array $data Profile data (name, email)
     * @return array Updated user data
     */
    public function updateProfile(User $user, array $data): array
    {
        // Update user (business logic - profile update)
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return [
            'user' => $user->fresh()->toArray(),
        ];
    }

    /**
     * Logout user.
     * 
     * Business logic: Revokes current access token.
     *
     * @param User $user
     * @return void
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}

