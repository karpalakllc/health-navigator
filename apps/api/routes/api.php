<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\ForumController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PharmacyController;
use App\Http\Controllers\Api\V1\PlatformController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SpecialtyController;
use App\Http\Controllers\Api\V1\TriageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/specialties', [SpecialtyController::class, 'index']);
    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get('/doctors/{slug}/reviews', [ReviewController::class, 'indexForDoctor']);
    Route::get('/doctors/{slug}', [DoctorController::class, 'show']);
    Route::get('/facilities', [FacilityController::class, 'index']);
    Route::get('/facilities/{slug}/reviews', [ReviewController::class, 'indexForFacility']);
    Route::get('/facilities/{slug}', [FacilityController::class, 'show']);
    Route::get('/pharmacies', [PharmacyController::class, 'index']);
    Route::get('/pharmacies/{slug}/products', [PharmacyController::class, 'products']);
    Route::get('/pharmacies/{slug}', [PharmacyController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    Route::get('/forum/categories', [ForumController::class, 'indexCategories']);
    Route::get('/forum/categories/{category}/topics', [ForumController::class, 'indexTopics']);
    Route::get('/forum/categories/{category}/topics/{topic}', [ForumController::class, 'showTopic']);

    Route::prefix('triage')->group(function (): void {
        Route::get('/flow', [TriageController::class, 'showFlow']);
        Route::post('/sessions', [TriageController::class, 'startSession'])
            ->middleware('throttle:api-triage-sessions');
        Route::put('/sessions/{id}/answers', [TriageController::class, 'storeAnswers'])
            ->middleware('throttle:api-triage-sessions');
        Route::post('/sessions/{id}/emergency', [TriageController::class, 'emergency'])
            ->middleware('throttle:api-triage-sessions');
        Route::post('/sessions/{id}/complete', [TriageController::class, 'complete'])
            ->middleware('throttle:api-triage-complete');
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:api-login');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [MeController::class, 'show']);
        Route::get('/me/reviews', [ReviewController::class, 'myReviews']);
        Route::get('/me/forum/topics', [ForumController::class, 'myTopics']);
        Route::get('/me/forum/posts', [ForumController::class, 'myPosts']);
        Route::post('/forum/categories/{category}/topics', [ForumController::class, 'storeTopic'])
            ->middleware(['role:member', 'throttle:api-forum-topics']);
        Route::post('/forum/categories/{category}/topics/{topic}/posts', [ForumController::class, 'storePost'])
            ->middleware(['role:member', 'throttle:api-forum-posts']);
        Route::post('/doctors/{slug}/reviews', [ReviewController::class, 'storeForDoctor'])
            ->middleware(['role:member', 'throttle:api-reviews']);
        Route::post('/facilities/{slug}/reviews', [ReviewController::class, 'storeForFacility'])
            ->middleware(['role:member', 'throttle:api-reviews']);
        Route::get('/platform/staff', [PlatformController::class, 'staff'])
            ->middleware('role:admin,moderator');
        Route::get('/platform/admin', [PlatformController::class, 'admin'])
            ->middleware('role:admin');
    });
});
