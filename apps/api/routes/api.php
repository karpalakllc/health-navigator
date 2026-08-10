<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\ForumController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeAvatarController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PharmacyController;
use App\Http\Controllers\Api\V1\PlatformController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SpecialtyController;
use App\Http\Controllers\Api\V1\TriageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/settings/public', [SettingsController::class, 'publicSettings']);
    Route::get('/search', SearchController::class);
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/specialties', [SpecialtyController::class, 'index']);
    Route::get('/specialties/{slug}', [SpecialtyController::class, 'show']);
    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get('/doctors/{slug}/reviews', [ReviewController::class, 'indexForDoctor']);
    Route::get('/doctors/{slug}', [DoctorController::class, 'show']);
    Route::get('/facilities', [FacilityController::class, 'index']);
    Route::get('/facilities/{slug}/reviews', [ReviewController::class, 'indexForFacility']);
    Route::get('/facilities/{slug}', [FacilityController::class, 'show']);
    Route::middleware('module:pharmacies')->group(function (): void {
        Route::get('/pharmacies', [PharmacyController::class, 'index']);
        Route::get('/pharmacies/{slug}/reviews', [ReviewController::class, 'indexForPharmacy']);
        Route::get('/pharmacies/{slug}/products', [PharmacyController::class, 'products']);
        Route::get('/pharmacies/{slug}', [PharmacyController::class, 'show']);
    });

    Route::middleware('module:products')->group(function (): void {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{slug}', [ProductController::class, 'show']);
    });

    Route::middleware('module:forum')->group(function (): void {
        Route::get('/forum/categories', [ForumController::class, 'indexCategories']);
        Route::get('/forum/topics/recent', [ForumController::class, 'recentTopics']);
        Route::get('/forum/topics', [ForumController::class, 'searchTopics']);
        Route::get('/forum/categories/{category}/topics', [ForumController::class, 'indexTopics']);
        Route::get('/forum/categories/{category}/topics/{topic}', [ForumController::class, 'showTopic'])
            ->middleware('auth.sanctum.optional');
    });

    Route::prefix('triage')->middleware('module:guidance')->group(function (): void {
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
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware(['registrations', 'throttle:api-login']);
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:api-login'])
            ->name('verification.verify');
        Route::post('/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware(['registrations', 'throttle:api-login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:api-login');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:api-login');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [MeController::class, 'show']);
        Route::post('/me/avatar', [MeAvatarController::class, 'update'])->middleware('verified');
        Route::get('/me/reviews', [ReviewController::class, 'myReviews']);
        Route::get('/me/forum/topics', [ForumController::class, 'myTopics']);
        Route::get('/me/forum/posts', [ForumController::class, 'myPosts']);
        Route::post('/forum/categories/{category}/topics', [ForumController::class, 'storeTopic'])
            ->middleware(['module:forum', 'role:member', 'verified', 'throttle:api-forum-topics']);
        Route::post('/forum/categories/{category}/topics/{topic}/posts', [ForumController::class, 'storePost'])
            ->middleware(['module:forum', 'role:member', 'verified', 'throttle:api-forum-posts']);
        // Authorization is ForumTopicPolicy::update, which understands both staff
        // permissions and category-scoped community moderation. A `role:member`
        // gate here would 403 staff moderators while the UI still offered them
        // the toolbar, because the toolbar is driven by the policy.
        Route::patch('/forum/categories/{category}/topics/{topic}/moderation', [ForumController::class, 'updateTopicModeration'])
            ->middleware(['module:forum']);
        Route::post('/doctors/{slug}/reviews', [ReviewController::class, 'storeForDoctor'])
            ->middleware(['role:member', 'verified', 'throttle:api-reviews']);
        Route::post('/facilities/{slug}/reviews', [ReviewController::class, 'storeForFacility'])
            ->middleware(['role:member', 'verified', 'throttle:api-reviews']);
        Route::post('/pharmacies/{slug}/reviews', [ReviewController::class, 'storeForPharmacy'])
            ->middleware(['role:member', 'verified', 'throttle:api-reviews']);
        // Intentional: these two stubs are the only coverage of the `role`
        // middleware's allow/deny matrix (PlatformRoutesTest), and that middleware
        // guards real endpoints. Do not delete them without first moving those
        // assertions onto another role-gated route.
        Route::get('/platform/staff', [PlatformController::class, 'staff'])
            ->middleware('role:admin,moderator');
        Route::get('/platform/admin', [PlatformController::class, 'admin'])
            ->middleware('role:admin');
    });
});
