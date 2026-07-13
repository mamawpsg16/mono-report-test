<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware gates the permission; VisitController checks
        // per-row ownership via VisitPolicy before this ever gets here.
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
