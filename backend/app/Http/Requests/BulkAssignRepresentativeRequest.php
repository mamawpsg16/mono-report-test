<?php

namespace App\Http\Requests;

use App\Rules\SalesRepresentative;
use Illuminate\Foundation\Http\FormRequest;

class BulkAssignRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Real gating is the route's permission:roles.manage middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuids' => ['required', 'array', 'min:1'],
            'customer_uuids.*' => ['uuid', 'exists:customers,uuid'],
            'assigned_representative_id' => [
                'nullable', 'integer', 'exists:users,id', new SalesRepresentative,
            ],
        ];
    }

    public function customerUuids(): array
    {
        return $this->validated('customer_uuids');
    }

    public function representativeId(): ?int
    {
        return $this->validated('assigned_representative_id');
    }
}
