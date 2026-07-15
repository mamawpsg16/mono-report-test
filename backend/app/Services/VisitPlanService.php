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
        // Weekly planning is a field-sales action -- admin holds visits.*
        // like every permission, so the route gate alone lets admin create
        // plans of their own, which then pollute the team coverage report
        // with data that was never a real field visit. This is the same
        // "is this literally a field rep" domain check already used by
        // app/Rules/SalesRepresentative.php and DashboardController's
        // $activeReps -- a deliberate, precedented exception to "app code
        // checks permissions, never roles" (PLAN.md's R1 golden rule),
        // because no permission was ever meant to express this fact.
        if (! $representative->hasRole('sales_representative')) {
            abort(403, 'Only sales representatives have a weekly visit plan.');
        }

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
        // Frozen the moment its day arrives -- today included, no same-day
        // edits (decided 2026-07-14; see ADR/backlog). planned_date is always
        // midnight (date-only), so isFuture() against now() correctly treats
        // today as not-future without separate date math. Applies to every
        // role, admins included: this protects report integrity, not row
        // ownership, so there's no bypass.
        if (! $plannedDate->isFuture()) {
            throw ValidationException::withMessages([
                'planned_date' => ['You can\'t plan for a day that has already started. Plan ahead instead.'],
            ]);
        }

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
