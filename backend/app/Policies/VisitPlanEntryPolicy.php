<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitPlanEntry;

/**
 * Only delete is defined: it's the sole action taken on an EXISTING entry
 * (adding an entry creates a new row -- no prior owner to check; reading the
 * plan is gated by permission:visits.view + the service resolving only the
 * signed-in rep's own week).
 */
class VisitPlanEntryPolicy
{
    public function delete(User $user, VisitPlanEntry $entry): bool
    {
        // planned_date <= today is frozen -- no bypass, admins included (see
        // VisitPlanService::addEntry's matching guard on the add side). This
        // protects report integrity, not row ownership, so it belongs here
        // alongside the ownership check rather than as a separate rule.
        return $user->can('visits.delete')
            && $this->accessible($user, $entry)
            && $entry->planned_date->isFuture();
    }

    /**
     * Single-row version of the ownership rule. Reuses the exact query from
     * VisitPlanEntry::scopeVisibleTo so "who can touch this row" is defined
     * once.
     */
    private function accessible(User $user, VisitPlanEntry $entry): bool
    {
        return VisitPlanEntry::whereKey($entry->getKey())->visibleTo($user)->exists();
    }
}
