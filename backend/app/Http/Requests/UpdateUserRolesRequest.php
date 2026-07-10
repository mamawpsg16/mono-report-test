<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
