<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Seeder;

class VisitSeeder extends Seeder
{
    /**
     * A couple of demo visits (one finished, one still open) owned by the
     * seeded rep, so the (view-only) web Visits screen has content before the
     * mobile app -- where reps actually run the visit workflow -- exists.
     */
    public function run(): void
    {
        $rep = User::where('email', 'rep@dataforge.test')->first();
        $customer = $rep?->assignedCustomers()->first();

        if (! $rep || ! $customer) {
            return;
        }

        if (! Visit::where('representative_id', $rep->id)->exists()) {
            $finished = new Visit();
            $finished->customer_id = $customer->id;
            $finished->representative_id = $rep->id;
            $finished->started_at = now()->subDays(2);
            $finished->ended_at = now()->subDays(2)->addMinutes(25);
            $finished->notes = 'Discussed the July order; restock next month.';
            $finished->save();

            $open = new Visit();
            $open->customer_id = $customer->id;
            $open->representative_id = $rep->id;
            $open->started_at = now()->subMinutes(10);
            $open->save();
        }
    }
}
