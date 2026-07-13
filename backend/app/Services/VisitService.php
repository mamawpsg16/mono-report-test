<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
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
        $visit->save();

        return $visit->load(['customer', 'representative']);
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
