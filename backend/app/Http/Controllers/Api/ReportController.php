<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowCoverageReportRequest;
use App\Services\CoverageReportService;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(
        private readonly CoverageReportService $coverageReportService
    ) {}

    // The signed-in rep's own planned-vs-actual. Identity always comes from
    // $request->user(), never from client input -- see the security note on
    // ShowCoverageReportRequest.
    public function myWeek(ShowCoverageReportRequest $request)
    {
        return response()->json(
            $this->coverageReportService->forRepresentative(
                $request->user(),
                $this->resolveWeekStart($request),
            ),
        );
    }

    // Every rep's planned-vs-actual. Admin-only by route gate (roles.manage);
    // no representative_id input exists on this endpoint at all.
    public function team(ShowCoverageReportRequest $request)
    {
        return response()->json(
            $this->coverageReportService->forTeam($this->resolveWeekStart($request)),
        );
    }

    private function resolveWeekStart(ShowCoverageReportRequest $request): Carbon
    {
        $weekStart = $request->validated('week_start')
            ? Carbon::parse($request->validated('week_start'))
            : now();

        return $weekStart->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
