<?php

use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PlatformCredentialController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SocialAccountController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    // Dashboard
    Route::get('dashboard', DashboardController::class);

    // Posts
    Route::apiResource('posts', PostController::class);
    Route::post('posts/{post}/schedule',              [PostController::class, 'schedule']);
    Route::post('posts/{post}/publish-now',           [PostController::class, 'publishNow']);
    Route::post('posts/{post}/cancel',                [PostController::class, 'cancel']);
    Route::post('posts/{post}/duplicate',             [PostController::class, 'duplicate']);
    Route::post('posts/{post}/submit-for-approval',   [PostController::class, 'submitForApproval']);
    Route::post('posts/{post}/approve',               [PostController::class, 'approve']);
    Route::post('posts/{post}/reject',                [PostController::class, 'reject']);

    // Media
    Route::apiResource('media', MediaController::class)->only(['index', 'store', 'destroy']);

    // Social Accounts
    Route::get('social-accounts',                [SocialAccountController::class, 'index']);
    Route::post('social-accounts/connect',       [SocialAccountController::class, 'connect']);
    Route::delete('social-accounts/{socialAccount}', [SocialAccountController::class, 'destroy']);
    Route::post('social-accounts/{socialAccount}/verify', [SocialAccountController::class, 'verify']);

    // Calendar
    Route::get('calendar', CalendarController::class);

    // Team
    Route::get('team',              [TeamController::class, 'index']);
    Route::post('team/invite',      [TeamController::class, 'invite']);
    Route::put('team/{user}',       [TeamController::class, 'update']);
    Route::delete('team/{user}',    [TeamController::class, 'destroy']);

    // Settings
    Route::get('settings',                          [SettingsController::class, 'show']);
    Route::put('settings/profile',                  [SettingsController::class, 'updateProfile']);
    Route::put('settings/organization',             [SettingsController::class, 'updateOrganization']);
    Route::put('settings/feature-flags',            [SettingsController::class, 'updateFeatureFlags']);

    // Platform credentials (per-org API keys)
    Route::get('platform-credentials',                      [PlatformCredentialController::class, 'index']);
    Route::put('platform-credentials/{platform}',           [PlatformCredentialController::class, 'upsert']);
    Route::delete('platform-credentials/{platform}',        [PlatformCredentialController::class, 'destroy']);

    // Platform rules (public to authenticated users)
    Route::get('platform-rules', fn() => response()->json(config('platform_rules')));
});
