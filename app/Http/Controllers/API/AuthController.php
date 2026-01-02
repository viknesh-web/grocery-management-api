<?php

namespace App\Http\Controllers\API;

use App\Exceptions\ValidationException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authentication Controller
 * 
 * Handles HTTP requests for authentication operations.
 * 
 * Responsibilities:
 * - HTTP request/response handling
 * - Input validation (via FormRequest classes)
 * - Service method calls
 * - Response formatting (via ApiResponse helper)
 * - Exception handling
 * 
 * Does NOT contain:
 * - Business logic
 * - Direct User model queries
 * - Password hashing
 * - Token generation
 */
class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Register a new admin user.
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->register($request->validated());

            return ApiResponse::success($data, 'User registered successfully', 201);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Registration failed. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Login user and return token.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->login(
                $request->input('email'),
                $request->input('password')
            );

            return ApiResponse::success($data, 'Login successful');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Login failed. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Get the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $data = $this->authService->getUser($request->user());
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Unable to fetch user information. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->updateProfile($request->user(), $request->validated());
            return ApiResponse::success($data, 'Profile updated successfully');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Profile update failed. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Logout the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());
            return ApiResponse::success(null, 'Logged out successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Logout failed. Please try again later.',
                null,
                500
            );
        }
    }
}
