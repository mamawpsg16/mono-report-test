<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // `confirmed` requires a matching `password_confirmation` field.
        // We don't ask for the current password: this endpoint's job is the
        // forced first-login reset, and the user is already authenticated with
        // the temp password. (Worth revisiting if it becomes a general
        // "change my password" screen — then re-auth is the safer default.)
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
