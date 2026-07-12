<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        // Credentials are correct but the account is switched off. We log them
        // straight back out and tell them plainly it's deactivated. (Note: a
        // distinct message here does let someone probing the form tell a real-
        // but-inactive email from a non-existent one — an accepted tradeoff for
        // a back-office panel where a clear message matters more.)
        if (! Auth::user()->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Please contact an administrator.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($request->user()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}
