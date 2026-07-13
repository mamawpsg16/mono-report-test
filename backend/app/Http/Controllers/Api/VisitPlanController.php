<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddVisitPlanEntryRequest;
use App\Http\Requests\ShowVisitPlanRequest;
use App\Models\Customer;
use App\Models\VisitPlanEntry;
use App\Services\VisitPlanService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VisitPlanController extends Controller
{
    public function __construct(
        private readonly VisitPlanService $visitPlanService
    ) {}

    // The signed-in rep's plan for a given week (get-or-create). Real
    // create/manage UI lives on the web for this one -- see PLAN.md's P4
    // note on why planning breaks the P2/P3 view-only pattern on purpose.
    // No week_start = next week: reps open this to plan ahead, not to stare
    // at a week that's already half over; "this week" is one tab away.
    public function show(ShowVisitPlanRequest $request)
    {
        $weekStart = $request->validated('week_start')
            ? Carbon::parse($request->validated('week_start'))
            : now()->addWeek();

        return response()->json(
            $this->visitPlanService->getOrCreateWeek($request->user(), $weekStart),
        );
    }

    public function storeEntry(AddVisitPlanEntryRequest $request)
    {
        $customer = Customer::visibleTo($request->user())
            ->findOrFail($request->validated('customer_id'));

        return response()->json(
            $this->visitPlanService->addEntry(
                $request->user(),
                $customer,
                Carbon::parse($request->validated('planned_date')),
            ),
            201,
        );
    }

    public function destroyEntry(Request $request, VisitPlanEntry $entry)
    {
        $this->authorize('delete', $entry);

        $this->visitPlanService->removeEntry($entry, $request->user());

        return response()->json(['deleted' => true]);
    }
}
