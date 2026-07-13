<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware gates the visits.create permission; the customer
        // itself must be one this rep can see, checked in the controller via
        // Customer::visibleTo (same rule the Customers list uses).
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ];
    }
}
