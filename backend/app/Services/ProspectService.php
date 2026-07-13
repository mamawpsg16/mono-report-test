<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\User;

class ProspectService
{
    public function listPaginated(User $user, int $perPage = 20, ?string $search = null)
    {
        return Prospect::with('creator')
            ->visibleTo($user)
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('notes', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data, User $creator): Prospect
    {
        // created_by isn't mass-assignable (it's ownership, not user input), so
        // set it explicitly rather than trusting the request payload.
        $prospect = new Prospect($data);
        $prospect->created_by = $creator->id;
        $prospect->save();

        return $prospect->load('creator');
    }

    public function update(Prospect $prospect, array $data): Prospect
    {
        $prospect->update($data);

        return $prospect->load('creator');
    }

    public function delete(Prospect $prospect): void
    {
        $prospect->delete();
    }
}
