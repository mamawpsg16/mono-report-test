<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    /**
     * Public: consume an invitation token and set the user's password. Reuses
     * the 'invitations' password broker — it validates the token + expiry, runs
     * the callback, then deletes the token (single-use). On success the account
     * is activated (must_change_password cleared) and the user can sign in.
     */
    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('invitations')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password, // hashed by the model's 'hashed' cast
                    'must_change_password' => false,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // invalid/expired token or unknown email
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password set. You can now sign in.']);
    }
}
