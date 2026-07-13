<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

/**
 * Only finish is defined: it's the sole action taken on an EXISTING visit
 * (start creates a new row -- there's no prior owner to check; list is
 * gated by permission:visits.view + scopeVisibleTo filtering the query).
 */
class VisitPolicy
{
    public function finish(User $user, Visit $visit): bool
    {
        return $user->can('visits.update') && $this->accessible($user, $visit);
    }

    /**
     * Single-row version of the ownership rule. Reuses the exact query from
     * Visit::scopeVisibleTo so "who can touch this row" is defined once.
     */
    private function accessible(User $user, Visit $visit): bool
    {
        return Visit::whereKey($visit->getKey())->visibleTo($user)->exists();
    }
}
