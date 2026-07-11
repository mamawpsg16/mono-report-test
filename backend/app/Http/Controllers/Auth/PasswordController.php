<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    /**
     * Set the authenticated user's password and clear the must-change flag.
     * Used by the forced first-login reset.
     */
    public function update(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'), // hashed by the cast
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'Password updated']);
    }
}
