<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * „Is this username free?“ while someone types it at sign-up or on the account
 * page. Answers yes or no with the same message registration would give, and
 * nothing else: no suggestions, no hint of which list refused a name, nothing
 * about the account that holds it. That a taken name is taken is inherent —
 * usernames are public. Throttled per address (api-username-check).
 */
class UsernameAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $username = $request->query('username');

        if (! is_string($username) || mb_strlen($username) > 100) {
            $username = '';
        }

        /** @var User|null $viewer */
        $viewer = $request->user();
        $problem = UsernameValidator::problem(UsernameNormalizer::prepare($username), $viewer);

        return ApiResponse::success([
            'available' => $problem === null,
            'message' => $problem === null ? null : __("validation.custom.username.{$problem}"),
        ]);
    }
}
