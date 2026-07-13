<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinishVisitRequest;
use App\Http\Requests\StartVisitRequest;
use App\Models\Customer;
use App\Models\Visit;
use App\Services\VisitService;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function __construct(
        private readonly VisitService $visitService
    ) {}

    // Web reads this (view/report); mobile will too, for visit history.
    public function index(Request $request)
    {
        return response()->json($this->visitService->listPaginated(
            $request->user(),
            (int) $request->query('per_page', 20),
            $request->query('search'),
        ));
    }

    // Mobile-only in practice (the field "Start Visit" action) -- the web has
    // no start button, but the endpoint isn't restricted by client, only by
    // the visits.create permission + the customer being one this rep can see.
    public function start(StartVisitRequest $request)
    {
        $customer = Customer::visibleTo($request->user())
            ->findOrFail($request->validated('customer_id'));

        return response()->json(
            $this->visitService->start($customer, $request->user()),
            201,
        );
    }

    // Mobile-only in practice (the field "Finish Visit" action).
    public function finish(FinishVisitRequest $request, Visit $visit)
    {
        $this->authorize('finish', $visit);

        return response()->json(
            $this->visitService->finish($visit, $request->validated('notes')),
        );
    }
}
