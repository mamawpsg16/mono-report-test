<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        // The module gate; row scoping is applied to the query (scopeVisibleTo).
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $this->accessible($user, $customer);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update') && $this->accessible($user, $customer);
    }

    /**
     * Single-row version of the ownership/coverage rule. Reuses the exact query
     * from Customer::scopeVisibleTo so "who can see this row" is defined once.
     */
    private function accessible(User $user, Customer $customer): bool
    {
        return Customer::whereKey($customer->getKey())->visibleTo($user)->exists();
    }
}
