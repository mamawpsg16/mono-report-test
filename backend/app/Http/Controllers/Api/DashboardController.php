<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Org-wide admin metrics for the dashboard. Route-gated by roles.manage,
     * so this is always the admin (global) view -- no per-row scoping needed.
     * The rep's own-book dashboard is a separate, later endpoint.
     */
    public function metrics(): JsonResponse
    {
        // Base query for active sales reps, reused for the count and the
        // per-rep coverage list below.
        $activeReps = User::role('sales_representative')->where('is_active', true);

        return response()->json([
            'total_customers' => Customer::count(),
            'unassigned_customers' => Customer::whereNull('assigned_representative_id')->count(),
            'active_reps' => (clone $activeReps)->count(),
            // must_change_password = invited-but-hasn't-set-a-password-yet
            // (the invitation flow leaves an unusable password until they do).
            'pending_invitations' => User::where('must_change_password', true)->count(),
            // withCount avoids an N+1: one query loads every rep with their
            // assigned-customer tally, busiest first.
            'rep_coverage' => (clone $activeReps)
                ->withCount('assignedCustomers')
                ->orderByDesc('assigned_customers_count')
                ->get()
                ->map(fn (User $rep) => [
                    'name' => $rep->name,
                    'customers_count' => $rep->assigned_customers_count,
                ]),
        ]);
    }

    /**
     * The signed-in rep's own book. Goes through Customer::scopeVisibleTo (the
     * same scope the Customers list uses) so this count can never disagree with
     * what they actually see on the Customers page.
     */
    public function myBook(Request $request): JsonResponse
    {
        $user = $request->user();

        // At most one open visit per rep (DB-enforced, see the visits
        // migration), so a single row -- if any -- is always the whole answer.
        $openVisit = Visit::open()->visibleTo($user)->with('customer')->first();

        return response()->json([
            'my_customers_count' => Customer::visibleTo($user)->count(),
            'my_customers' => Customer::visibleTo($user)
                ->orderBy('name')
                ->limit(8)
                ->get(['name', 'city']),
            'open_visit' => $openVisit ? [
                'customer_name' => $openVisit->customer->name,
                'started_at' => $openVisit->started_at,
            ] : null,
        ]);
    }
}
