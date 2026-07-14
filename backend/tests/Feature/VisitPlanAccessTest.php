<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\VisitPlan;
use App\Models\VisitPlanEntry;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VisitPlan/VisitPlanEntry (CRM P4): get-or-create current week, add/remove
 * entries, ownership on delete, and the soft-delete-vs-unique-index fix --
 * re-adding a customer after removing them must not be blocked by the old
 * (soft-deleted) row, which is the whole reason the plain UNIQUE was swapped
 * for a partial index in the soft-delete migration.
 */
class VisitPlanAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function user(string $email, ?string $role = null): User
    {
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function customerFor(?User $owner, string $code): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'name' => "Shop {$code}",
            'email' => "{$code}@shop.test",
            'assigned_representative_id' => $owner?->id,
        ]);
    }

    // Bypasses VisitPlanService::addEntry's freeze -- needed for the
    // cannot-delete-a-frozen-entry tests, which need an entry planned for
    // today/the past to exist in the first place (the API can't create one).
    private function entryFor(User $representative, Customer $customer, string $plannedDate): VisitPlanEntry
    {
        $plan = VisitPlan::firstOrCreate([
            'representative_id' => $representative->id,
            'week_start_date' => Carbon::parse($plannedDate)->startOfWeek(Carbon::MONDAY)->toDateString(),
        ]);

        $entry = new VisitPlanEntry();
        $entry->visit_plan_id = $plan->id;
        $entry->customer_id = $customer->id;
        $entry->planned_date = $plannedDate;
        $entry->save();

        return $entry;
    }

    public function test_get_or_create_current_week_creates_a_plan_for_a_new_rep(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');

        $response = $this->actingAs($rep)->getJson('/api/visit-plans?week_start=' . now()->toDateString());

        $response->assertOk();
        $this->assertDatabaseHas('visit_plans', ['representative_id' => $rep->id]);
    }

    public function test_calling_current_twice_does_not_create_a_duplicate_plan(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $weekStart = now()->toDateString();

        $this->actingAs($rep)->getJson("/api/visit-plans?week_start={$weekStart}")->assertOk();
        $this->actingAs($rep)->getJson("/api/visit-plans?week_start={$weekStart}")->assertOk();

        $this->assertSame(1, VisitPlan::where('representative_id', $rep->id)->count());
    }

    public function test_no_week_start_param_defaults_to_next_week(): void
    {
        // Reps open this screen to plan ahead, not to stare at a week
        // that's already half over -- see the controller's `show()` note.
        $rep = $this->user('rep@t.test', 'sales_representative');

        $response = $this->actingAs($rep)->getJson('/api/visit-plans');

        // Compare on the date only, not the raw JSON shape -- week_start_date
        // is cast 'date' on the model, so it serializes as a full ISO
        // datetime, an implementation detail unrelated to what this asserts.
        $expectedWeekStart = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
        $response->assertOk();
        $this->assertSame(
            $expectedWeekStart,
            Carbon::parse($response->json('week_start_date'))->toDateString(),
        );
    }

    public function test_rep_can_add_a_customer_to_their_week(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');

        $response = $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('visit_plan_entries', ['customer_id' => $customer->id]);
    }

    public function test_rep_cannot_add_a_customer_they_cannot_see(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $other = $this->user('other@t.test', 'sales_representative');
        $notMine = $this->customerFor($other, 'C1');

        $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $notMine->id,
            'planned_date' => now()->toDateString(),
        ])->assertNotFound();
    }

    public function test_adding_the_same_customer_twice_on_the_same_day_is_rejected(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');
        $date = now()->addDay()->toDateString();

        $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => $date,
        ])->assertCreated();

        $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => $date,
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }

    public function test_rep_cannot_delete_another_reps_entry(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $customer = $this->customerFor($repB, 'C1');

        $this->actingAs($repB)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => now()->addDay()->toDateString(),
        ])->assertCreated();
        $entry = VisitPlanEntry::first();

        $this->actingAs($repA)
            ->deleteJson("/api/visit-plan-entries/{$entry->uuid}")
            ->assertForbidden();

        $this->assertDatabaseHas('visit_plan_entries', ['id' => $entry->id, 'deleted_at' => null]);
    }

    public function test_deleting_an_entry_soft_deletes_and_stamps_deleted_by(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');

        $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => now()->addDay()->toDateString(),
        ])->assertCreated();
        $entry = VisitPlanEntry::first();

        $this->actingAs($rep)
            ->deleteJson("/api/visit-plan-entries/{$entry->uuid}")
            ->assertOk();

        // Row still exists (soft delete), just marked -- not gone from the DB.
        $trashed = VisitPlanEntry::withTrashed()->find($entry->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertSame($rep->id, $trashed->deleted_by);

        // But excluded from normal queries (the plan's live entry list).
        $this->assertSame(0, VisitPlanEntry::where('id', $entry->id)->count());
    }

    public function test_re_adding_a_customer_after_soft_deleting_the_entry_succeeds(): void
    {
        // Proves the partial-unique-index fix: a plain UNIQUE would still
        // block this re-add against the old, soft-deleted row.
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');
        $date = now()->addDay()->toDateString();

        $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => $date,
        ])->assertCreated();
        $first = VisitPlanEntry::first();

        $this->actingAs($rep)
            ->deleteJson("/api/visit-plan-entries/{$first->uuid}")
            ->assertOk();

        $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => $date,
        ])->assertCreated();

        $this->assertSame(1, VisitPlanEntry::where('customer_id', $customer->id)->count());
        $this->assertSame(2, VisitPlanEntry::withTrashed()->where('customer_id', $customer->id)->count());
    }

    public function test_cannot_add_an_entry_for_today(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');

        $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('planned_date');

        $this->assertDatabaseCount('visit_plan_entries', 0);
    }

    public function test_cannot_add_an_entry_for_a_past_day(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');

        $this->actingAs($rep)->postJson('/api/visit-plan-entries', [
            'customer_id' => $customer->id,
            'planned_date' => now()->subDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('planned_date');

        $this->assertDatabaseCount('visit_plan_entries', 0);
    }

    public function test_cannot_delete_an_entry_planned_for_today(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');
        $entry = $this->entryFor($rep, $customer, now()->toDateString());

        $this->actingAs($rep)
            ->deleteJson("/api/visit-plan-entries/{$entry->uuid}")
            ->assertForbidden();

        $this->assertDatabaseHas('visit_plan_entries', ['id' => $entry->id, 'deleted_at' => null]);
    }

    public function test_admin_also_cannot_delete_an_entry_planned_for_today(): void
    {
        // The freeze protects report integrity, not row ownership -- no
        // admin bypass (decided 2026-07-14).
        $admin = $this->user('admin@t.test', 'admin');
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');
        $entry = $this->entryFor($rep, $customer, now()->toDateString());

        $this->actingAs($admin)
            ->deleteJson("/api/visit-plan-entries/{$entry->uuid}")
            ->assertForbidden();

        $this->assertDatabaseHas('visit_plan_entries', ['id' => $entry->id, 'deleted_at' => null]);
    }
}
