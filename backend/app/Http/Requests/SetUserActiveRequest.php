<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetUserActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Real gating is the route's permission:roles.manage middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }

    public function active(): bool
    {
        return $this->validated('active');
    }
}
