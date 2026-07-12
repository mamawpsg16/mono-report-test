<?php

namespace App\Http\Requests;

use App\Rules\SalesRepresentative;
use Illuminate\Foundation\Http\FormRequest;

class AssignRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Real gating is the route's permission:roles.manage middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            // null clears the assignment back to "Unassigned".
            'assigned_representative_id' => [
                'nullable', 'integer', 'exists:users,id', new SalesRepresentative,
            ],
        ];
    }

    public function representativeId(): ?int
    {
        return $this->validated('assigned_representative_id');
    }
}
