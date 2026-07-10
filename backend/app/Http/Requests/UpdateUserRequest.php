<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // exclude the user being edited so re-saving their own email
            // doesn't trip the unique rule against their existing row
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')->id),
            ],
        ];
    }
}
