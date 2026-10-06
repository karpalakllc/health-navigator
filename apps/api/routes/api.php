<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentReportController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\ForumController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HomeHighlightsController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\MeAvatarController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PharmacyController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\ReviewHelpfulController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SpecialtyController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\TriageController;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/settings/public', [SettingsController::class, 'publicSettings']);
    Route::get('/search', SearchController::class);
    // Anonymous, identical-for-everyone taxonomies: shared caches may keep them
    // for five minutes and revalidate with If-None-Match (TaxonomyCache server side).
    Route::middleware('cache.public')->group(function (): void {
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::get('/languages', [LanguageController::class, 'index']);
        Route::get('/specialties', [SpecialtyController::class, 'index']);
        Route::get('/specialties/{slug}', [SpecialtyController::class, 'show']);
        // Top specialties and cities plus the latest approved reviews; reviews
        // of pharmacies only while that module is on (keyed into the cache).
        Route::get('/home/highlights', HomeHighlightsController::class);
    });
    // Anonymous directory and product reads are public for 60 s with an
    // ETag (cache.public:60); the review lists, which carry viewer_review, are not.
    Route::get('/doctors', [DoctorController::class, 'index'])->middleware('cache.public:60');
    // Optional auth so meta.viewer_review resolves: without it a signed-in user who
    // has already reviewed a profile is shown the submission form, then told they
    // have already reviewed it.
    Route::get('/doctors/{slug}/reviews', [ReviewController::class, 'indexForDoctor'])
        ->middleware('auth.sanctum.optional');
    Route::get('/doctors/{slug}', [DoctorController::class, 'show'])->middleware('cache.public:60');
    Route::get('/facilities', [FacilityController::class, 'index'])->middleware('cache.public:60');
    Route::get('/facilities/{slug}/reviews', [ReviewController::class, 'indexForFacility'])
        ->middleware('auth.sanctum.optional');
    Route::get('/facilities/{slug}', [FacilityController::class, 'show'])->middleware('cache.public:60');
    Route::middleware('module:pharmacies')->group(function (): void {
        Route::get('/pharmacies', [PharmacyController::class, 'index'])->middleware('cache.public:60');
        Route::get('/pharmacies/{slug}/reviews', [ReviewController::class, 'indexForPharmacy'])
            ->middleware('auth.sanctum.optional');
        Route::get('/pharmacies/{slug}/products', [PharmacyController::class, 'products'])->middleware('cache.public:60');
        Route::get('/pharmacies/{slug}', [PharmacyController::class, 'show'])->middleware('cache.public:60');
    });

    Route::middleware(['module:products', 'cache.public:60'])->group(function (): void {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{slug}', [ProductController::class, 'show']);
    });

    Route::middleware('module:forum')->group(function (): void {
        Route::get('/forum/categories', [ForumController::class, 'indexCategories'])
            ->middleware('cache.public');
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
        // Deliberately NOT behind `registrations`: this is a recovery action for an
        // account that already exists. Gating it means that turning signups off
        // strands anyone mid-verification — they can neither log in nor get a new link.
        Route::post('/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:api-verification-resend');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:api-login');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:api-login');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [MeController::class, 'show']);
        Route::patch('/me/profile', [MeController::class, 'updateProfile'])->middleware(['verified', 'throttle:api-profile']);
        Route::post('/me/avatar', [MeAvatarController::class, 'update'])->middleware('verified');
        Route::get('/me/reviews', [ReviewController::class, 'myReviews']);
        Route::get('/me/forum/topics', [ForumController::class, 'myTopics']);
        Route::get('/me/forum/posts', [ForumController::class, 'myPosts']);
        Route::post('/forum/categories/{category}/topics', [ForumController::class, 'storeTopic'])
            ->middleware(['module:forum', 'can:create,'.ForumTopic::class, 'verified', 'throttle:api-forum-topics']);
        Route::post('/forum/categories/{category}/topics/{topic}/posts', [ForumController::class, 'storePost'])
            ->middleware(['module:forum', 'can:create,'.ForumPost::class, 'verified', 'throttle:api-forum-posts']);
        // Authorization is ForumTopicPolicy::update, which understands both staff
        // permissions and category-scoped community moderation. A `can:create`
        // (member) gate here would 403 staff moderators while the UI still offered them
        // the toolbar, because the toolbar is driven by the policy.
        Route::patch('/forum/categories/{category}/topics/{topic}/moderation', [ForumController::class, 'updateTopicModeration'])
            ->middleware(['module:forum']);
        Route::post('/doctors/{slug}/reviews', [ReviewController::class, 'storeForDoctor'])
            ->middleware(['can:create,'.Review::class, 'verified', 'throttle:api-reviews']);
        Route::post('/facilities/{slug}/reviews', [ReviewController::class, 'storeForFacility'])
            ->middleware(['can:create,'.Review::class, 'verified', 'throttle:api-reviews']);
        // Same module gate as the pharmacy GETs: with the module off, the listing
        // and profile 503 but a direct POST would otherwise still accept reviews.
        Route::post('/pharmacies/{slug}/reviews', [ReviewController::class, 'storeForPharmacy'])
            ->middleware(['module:pharmacies', 'can:create,'.Review::class, 'verified', 'throttle:api-reviews']);
    });

    // Member reports of published content (docs/notice-and-action.md). Two
    // windows, each with its own key prefix so they count separately: a burst
    // limit and a daily ceiling per account. One report per item is enforced
    // by the table, so the limits only bound how many items one account flags.
    Route::middleware(['auth:sanctum', 'verified', 'throttle:10,10,api-reports-burst', 'throttle:40,1440,api-reports-daily'])
        ->group(function (): void {
            Route::post('/reviews/{review}/reports', [ContentReportController::class, 'storeForReview'])
                ->whereNumber('review');
            Route::middleware('module:forum')->group(function (): void {
                Route::post('/forum/categories/{category}/topics/{topic}/reports', [ContentReportController::class, 'storeForForumTopic']);
                Route::post('/forum/posts/{post}/reports', [ContentReportController::class, 'storeForForumPost'])
                    ->whereNumber('post');
            });
        });

    // „Корисно“ on a published review: members only (the Member role's
    // reviews.create, as for writing one), one vote each, toggled.
    Route::middleware(['auth:sanctum', 'verified', 'can:create,'.Review::class, 'throttle:60,10,api-review-helpful'])
        ->group(function (): void {
            Route::put('/reviews/{review}/helpful', [ReviewHelpfulController::class, 'store'])
                ->whereNumber('review');
            Route::delete('/reviews/{review}/helpful', [ReviewHelpfulController::class, 'destroy'])
                ->whereNumber('review');
        });

    // Account data rights and devices (D5, D6).
    Route::middleware('auth:sanctum')->prefix('me')->group(function (): void {
        Route::get('/export', [AccountController::class, 'export'])->middleware('throttle:api-account-export');
        Route::delete('/', [AccountController::class, 'destroy'])->middleware('throttle:api-account-delete');
        Route::get('/tokens', [TokenController::class, 'index']);
        Route::delete('/tokens', [TokenController::class, 'destroyOthers']);
        Route::delete('/tokens/{token}', [TokenController::class, 'destroy'])->whereNumber('token');
    });
});
