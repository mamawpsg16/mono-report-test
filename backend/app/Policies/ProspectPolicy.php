<?php

namespace App\Policies;

use App\Models\Prospect;
use App\Models\User;

/**
 * Only update/delete are defined here because those are the only actions the
 * controller enforces per-row (via $this->authorize). index is gated by the
 * permission:prospects.view route middleware + scopeVisibleTo query filtering,
 * and there's no single-prospect show endpoint -- so viewAny/view would be dead
 * code. Add them if a show endpoint or authorizeResource() ever needs them.
 */
class ProspectPolicy
{
    public function update(User $user, Prospect $prospect): bool
    {
        return $user->can('prospects.update') && $this->accessible($user, $prospect);
    }

    public function delete(User $user, Prospect $prospect): bool
    {
        return $user->can('prospects.delete') && $this->accessible($user, $prospect);
    }

    /**
     * Single-row version of the ownership rule. Reuses the exact query from
     * Prospect::scopeVisibleTo so "who can touch this row" is defined once.
     */
    private function accessible(User $user, Prospect $prospect): bool
    {
        return Prospect::whereKey($prospect->getKey())->visibleTo($user)->exists();
    }
}
