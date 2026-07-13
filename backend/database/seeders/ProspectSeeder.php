<?php

namespace Database\Seeders;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProspectSeeder extends Seeder
{
    /**
     * A few demo prospects owned by the seeded rep so the (view-only) web
     * Prospects screen has content before the mobile app -- which is where reps
     * actually capture prospects -- exists. Idempotent: keyed on name + owner.
     */
    public function run(): void
    {
        $rep = User::where('email', 'rep@dataforge.test')->first();

        if (! $rep) {
            return;
        }

        $samples = [
            ['name' => 'Osu Wholesale', 'phone' => '024 000 1111', 'notes' => 'Met at the trade fair; interested in bulk orders.'],
            ['name' => 'Labadi Provisions', 'phone' => '020 222 3333', 'notes' => 'Follow up next week re: pricing.'],
            ['name' => 'Spintex Mart', 'phone' => null, 'notes' => 'Walk-in enquiry.'],
        ];

        foreach ($samples as $sample) {
            $exists = Prospect::where('name', $sample['name'])
                ->where('created_by', $rep->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $prospect = new Prospect($sample);
            $prospect->created_by = $rep->id;
            $prospect->save();
        }
    }
}
