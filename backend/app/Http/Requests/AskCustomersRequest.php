<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Real gating is the route's customers.view middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            // Bounded so a client can't ship an ever-growing transcript into
            // the RAG prompt on every follow-up.
            'history' => ['array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ];
    }
}
