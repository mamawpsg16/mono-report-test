<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Real gating is the route's customers.create/update middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            // Round-trips from the preview response; python-service re-resolves
            // and re-validates the path server-side before touching it.
            'stored_path' => ['required', 'string'],
            'original_filename' => ['required', 'string'],
        ];
    }
}
