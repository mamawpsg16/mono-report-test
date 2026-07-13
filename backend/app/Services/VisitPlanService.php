<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Models\VisitPlan;
use App\Models\VisitPlanEntry;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class VisitPlanService
{
    /**
     * The plan for the week containing $referenceDate (default: today),
     * creating it if this rep has none yet for that week. One row per
     * rep/week (DB-enforced), so this can never create a duplicate.
     */
    public function getOrCreateWeek(User $representative, ?Carbon $referenceDate = null): VisitPlan
    {
        $weekStart = ($referenceDate ?? now())->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        $plan = VisitPlan::firstOrCreate([
            'representative_id' => $representative->id,
            'week_start_date' => $weekStart->toDateString(),
        ]);

        return $plan->load(['entries.customer']);
    }

    /**
     * Add a customer to the plan for the week $plannedDate falls in
     * (resolving/creating that week's plan for this rep -- usually the
     * current week, but not assumed to be).
     */
    public function addEntry(User $representative, Customer $customer, Carbon $plannedDate): VisitPlanEntry
    {
        $plan = $this->getOrCreateWeek($representative, $plannedDate);

        if (VisitPlanEntry::where('visit_plan_id', $plan->id)
            ->where('customer_id', $customer->id)
            ->where('planned_date', $plannedDate->toDateString())
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'customer_id' => ['This customer is already planned for that day.'],
            ]);
        }

        $entry = new VisitPlanEntry();
        $entry->visit_plan_id = $plan->id;
        $entry->customer_id = $customer->id;
        $entry->planned_date = $plannedDate->toDateString();
        $entry->save();

        return $entry->load('customer');
    }

    /**
     * Soft delete: the entry disappears from the plan but the row (and who
     * removed it) is kept for history, not erased. delete() itself becomes a
     * soft delete via VisitPlanEntry's SoftDeletes trait.
     */
    public function removeEntry(VisitPlanEntry $entry, User $actor): void
    {
        $entry->deleted_by = $actor->id;
        $entry->save();
        $entry->delete();
    }
}
