<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be the id of a user who actually holds the
 * sales_representative role — a customer's owner is always a rep, never an
 * admin or an unroled account. Pair with nullable/exists in the request;
 * this rule only runs when a non-null id was provided.
 */
class SalesRepresentative implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! User::find($value)?->hasRole('sales_representative')) {
            $fail('The selected user is not a sales representative.');
        }
    }
}
