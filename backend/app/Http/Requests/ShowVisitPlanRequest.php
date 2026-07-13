<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowVisitPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware gates the visits.view permission; there's no
        // per-row ownership to check here -- a rep only ever resolves their
        // own plan (VisitPlanService keys off the authenticated user).
        return true;
    }

    public function rules(): array
    {
        return [
            'week_start' => ['nullable', 'date'],
        ];
    }
}
