<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitPlanEntry;
use Illuminate\Validation\ValidationException;

class VisitService
{
    public function listPaginated(User $user, int $perPage = 20, ?string $search = null)
    {
        return Visit::with(['customer', 'representative'])
            ->visibleTo($user)
            ->when($search, function ($query, $search) {
                $query->whereHas('customer', function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%");
                });
            })
            ->latest('started_at')
            ->paginate($perPage);
    }

    /**
     * Start a visit to a customer. The friendly, app-level half of the
     * one-open-visit rule -- checked here so the rep gets a clear 422 instead
     * of a raw DB error. The partial unique index (visits_one_open_per_
     * representative) is the actual guarantee if two starts ever race.
     */
    public function start(Customer $customer, User $representative): Visit
    {
        if (Visit::open()->visibleTo($representative)->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => ['You already have an open visit. Finish it before starting another.'],
            ]);
        }

        $visit = new Visit();
        $visit->customer_id = $customer->id;
        $visit->representative_id = $representative->id;
        $visit->started_at = now();
        $visit->visit_plan_entry_id = $this->matchPlanEntry($customer, $representative)?->id;
        $visit->save();

        return $visit->load(['customer', 'representative']);
    }

    /**
     * The planned entry this visit fulfils, if any -- automatic, no manual
     * "tick" UI on web or mobile (see PLAN.md's P4 note). Matches on this
     * rep's plan, this customer, and today's date; excludes an entry another
     * visit already claimed so two visits can never point at the same plan
     * entry (would corrupt a future planned-vs-actual report).
     */
    private function matchPlanEntry(Customer $customer, User $representative): ?VisitPlanEntry
    {
        return VisitPlanEntry::whereHas('visitPlan', function ($query) use ($representative) {
            $query->where('representative_id', $representative->id);
        })
            ->where('customer_id', $customer->id)
            ->whereDate('planned_date', now()->toDateString())
            ->whereDoesntHave('visit')
            ->first();
    }

    public function finish(Visit $visit, ?string $notes = null): Visit
    {
        if (! $visit->isOpen()) {
            throw ValidationException::withMessages([
                'visit' => ['This visit is already finished.'],
            ]);
        }

        // Direct property assignment, not update([...]): ended_at isn't
        // fillable (must never be settable from raw client input), so a mass
        // update() would silently drop it.
        $visit->ended_at = now();
        $visit->notes = $notes;
        $visit->save();

        return $visit->load(['customer', 'representative']);
    }
}
