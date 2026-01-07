<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException as LaravelValidationException;

/**
 * Auth Service
 */
class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->toArray(),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();
        if (!$user || !Hash::check($password, $user->password)) {
            throw new ValidationException(
                'The provided credentials are incorrect.',
                ['email' => ['The provided credentials are incorrect.']]
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->toArray(),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
   
    public function getUser(User $user): array
    {
        return [
            'user' => $user->toArray(),
        ];
    }

    public function updateProfile(User $user, array $data): array
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return [
            'user' => $user->fresh()->toArray(),
        ];
    }
  
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}

