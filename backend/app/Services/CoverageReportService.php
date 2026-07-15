<?php

namespace App\Services;

use App\Models\User;
use App\Models\VisitPlanEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CoverageReportService
{
    /**
     * One rep's planned-vs-actual for the week containing $weekStart.
     * Read-only: unlike VisitPlanService::getOrCreateWeek, viewing a report
     * must never have the side effect of creating a plan that doesn't exist.
     */
    public function forRepresentative(User $representative, Carbon $weekStart): array
    {
        $entries = $this->entriesForWeek($weekStart)
            ->whereHas('visitPlan', fn ($query) => $query->where('representative_id', $representative->id))
            ->get();

        return $this->summarize($entries);
    }

    /**
     * Every rep's planned-vs-actual for the week, grouped by rep. Admin-only
     * by route gate (roles.manage) -- there is no per-row ownership check
     * inside this method because the route itself is the boundary.
     *
     * Scoped to the sales_representative role, not "anyone with a
     * VisitPlan" -- an admin holds visits.* permissions too (same as every
     * role) and can use the planning screen themselves, which would
     * otherwise leak into a "team coverage" report as a phantom rep. Same
     * idiom as DashboardController::metrics's $activeReps.
     */
    public function forTeam(Carbon $weekStart): array
    {
        $entries = $this->entriesForWeek($weekStart)
            ->whereHas('visitPlan.representative', fn ($query) => $query->role('sales_representative'))
            ->get();

        return $entries
            ->groupBy(fn (VisitPlanEntry $entry) => $entry->visitPlan->representative_id)
            ->map(function ($repEntries) {
                return [
                    'representative' => [
                        'name' => $repEntries->first()->visitPlan->representative->name,
                    ],
                    ...$this->summarize($repEntries),
                ];
            })
            ->values()
            ->all();
    }

    private function entriesForWeek(Carbon $weekStart)
    {
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        return VisitPlanEntry::with(['customer', 'visit', 'visitPlan.representative'])
            ->whereBetween('planned_date', [$weekStart->toDateString(), $weekEnd->toDateString()]);
    }

    private function summarize(Collection $entries): array
    {
        $withStatus = $entries->map(fn (VisitPlanEntry $entry) => [
            'customer_name' => $entry->customer->name,
            'planned_date' => $entry->planned_date->toDateString(),
            'status' => $this->statusFor($entry),
        ]);

        return [
            'entries' => $withStatus->values()->all(),
            'planned_count' => $withStatus->count(),
            'visited_count' => $withStatus->where('status', 'visited')->count(),
            'missed_count' => $withStatus->where('status', 'missed')->count(),
        ];
    }

    /**
     * visited: a Visit claimed this entry (VisitService::start's auto-link).
     * missed: the day is fully in the past and nothing claimed it.
     * pending: today or a future day, not yet visited -- today counts as
     * pending, not missed, because the day isn't over (this is a stricter
     * boundary than the entry freeze, which treats today as immutable for
     * *editing* but not yet a failure for *reporting*).
     */
    private function statusFor(VisitPlanEntry $entry): string
    {
        if ($entry->visit) {
            return 'visited';
        }

        // planned_date is midnight; isPast() would wrongly call *today*
        // missed as soon as the clock passes 00:00. Compare dates, not
        // instants: only a day strictly before today's date is missed.
        return $entry->planned_date->lt(Carbon::today()) ? 'missed' : 'pending';
    }
}
