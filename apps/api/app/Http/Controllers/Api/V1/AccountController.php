<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AnonymiseUser;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\AccountExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The member's data rights (D5): a downloadable copy of their own data, and
 * deleting the account by anonymisation.
 */
class AccountController extends Controller
{
    /**
     * Streams the JSON document (AccountExport) as an attachment. Rate-limited
     * per account (api-account-export): it reads every row the member owns.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $this->user($request);
        $export = new AccountExport($user);

        return response()->streamDownload(
            fn () => $export->write(),
            'zdravje360-'.now()->format('Y-m-d').'.json',
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store, private',
            ],
        );
    }

    /**
     * Deletes the caller's account after they re-enter their password: their
     * personal data is cleared and their reviews and forum posts stay, shown
     * as a deleted user (AnonymiseUser). Every session ends with it.
     *
     * Staff accounts are refused: they are managed by administrators, and the
     * panel's audit trail (moderated_by_id) points at them.
     */
    public function destroy(Request $request, AnonymiseUser $anonymise): JsonResponse
    {
        $user = $this->user($request);

        if ($user->isStaff()) {
            return ApiResponse::errorCode('account.staff_cannot_delete', 403);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('api.account.password_incorrect')],
            ]);
        }

        $anonymise->handle($user);

        return ApiResponse::success(['message' => __('api.account.deleted')]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
