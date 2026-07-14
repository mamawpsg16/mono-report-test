<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowCoverageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware gates the permission (visits.view / roles.manage);
        // there's no per-row ownership to check here -- who this report is
        // *for* is decided server-side (myWeek = $request->user(), team =
        // every rep), never taken from client input.
        return true;
    }

    public function rules(): array
    {
        return [
            'week_start' => ['nullable', 'date'],
        ];
    }
}
