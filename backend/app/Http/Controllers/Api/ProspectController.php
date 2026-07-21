<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\UpdateProspectRequest;
use App\Models\Prospect;
use App\Services\ProspectService;
use Illuminate\Http\Request;

class ProspectController extends Controller
{
    public function __construct(
        private readonly ProspectService $prospectService
    ) {}

    public function index(Request $request)
    {
        return response()->json($this->prospectService->listPaginated(
            $request->user(),
            (int) $request->query('per_page', 20),
            $request->query('search'),
        ));
    }

    public function store(StoreProspectRequest $request)
    {
        return response()->json(
            $this->prospectService->create($request->validated(), $request->user()),
            201,
        );
    }

    public function update(UpdateProspectRequest $request, Prospect $prospect)
    {
        // Permission is gated by the route; this enforces per-row ownership
        // (a rep can hold prospects.update yet not own THIS prospect).
        $this->authorize('update', $prospect);

        return response()->json(
            $this->prospectService->update($prospect, $request->validated()),
        );
    }

    public function destroy(Request $request, Prospect $prospect)
    {
        $this->authorize('delete', $prospect);

        $this->prospectService->delete($prospect, $request->user());

        return response()->json(['deleted' => true]);
    }
}
