<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitPlan;
use App\Models\VisitPlanEntry;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Planned-vs-actual coverage report (CRM P4 payoff): status derivation
 * (visited/missed/pending) and, just as important, that a rep can never see
 * another rep's week -- the IDOR risk this endpoint was designed against.
 */
class CoverageReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function user(string $email, string $role): User
    {
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

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

    // Bypasses VisitPlanService::addEntry's freeze -- the missed/visited
    // cases below need entries planned for today/the past, which the normal
    // add-entry API refuses to create at all.
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

    // Simulates what VisitService::start()'s auto-link would have done --
    // that matching logic has its own tests elsewhere; here we only need a
    // Visit that already claims an entry, to check the report reads it right.
    private function linkVisitTo(VisitPlanEntry $entry): void
    {
        $visit = new Visit();
        $visit->customer_id = $entry->customer_id;
        $visit->representative_id = $entry->visitPlan->representative_id;
        $visit->started_at = now();
        $visit->ended_at = now();
        $visit->visit_plan_entry_id = $entry->id;
        $visit->save();
    }

    public function test_my_week_reports_visited_missed_and_pending_correctly(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $visitedCustomer = $this->customerFor($rep, 'C1');
        $missedCustomer = $this->customerFor($rep, 'C2');
        $pendingCustomer = $this->customerFor($rep, 'C3');

        $visitedEntry = $this->entryFor($rep, $visitedCustomer, now()->subDay()->toDateString());
        $this->linkVisitTo($visitedEntry);
        $this->entryFor($rep, $missedCustomer, now()->subDay()->toDateString());
        $this->entryFor($rep, $pendingCustomer, now()->addDay()->toDateString());

        $response = $this->actingAs($rep)->getJson('/api/reports/coverage/my-week');

        $response->assertOk();
        $statuses = collect($response->json('entries'))
            ->pluck('status', 'customer_name');

        $this->assertSame('visited', $statuses['Shop C1']);
        $this->assertSame('missed', $statuses['Shop C2']);
        $this->assertSame('pending', $statuses['Shop C3']);
        $this->assertSame(3, $response->json('planned_count'));
        $this->assertSame(1, $response->json('visited_count'));
        $this->assertSame(1, $response->json('missed_count'));
    }

    public function test_an_entry_planned_for_today_with_no_visit_is_pending_not_missed(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');
        $this->entryFor($rep, $customer, now()->toDateString());

        $response = $this->actingAs($rep)->getJson('/api/reports/coverage/my-week');

        $this->assertSame('pending', $response->json('entries.0.status'));
    }

    public function test_rep_only_sees_their_own_entries_on_my_week(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $customerA = $this->customerFor($repA, 'A1');
        $customerB = $this->customerFor($repB, 'B1');
        $this->entryFor($repA, $customerA, now()->addDay()->toDateString());
        $this->entryFor($repB, $customerB, now()->addDay()->toDateString());

        $response = $this->actingAs($repA)->getJson('/api/reports/coverage/my-week');

        $response->assertOk();
        $this->assertSame(['Shop A1'], collect($response->json('entries'))->pluck('customer_name')->all());
    }

    public function test_a_representative_id_query_param_is_ignored_not_honored(): void
    {
        // The IDOR case this endpoint was built to close off: identity comes
        // from the session, never from client input. A crafted param must
        // have zero effect, not a 403 or a validation error -- it should
        // simply not be a recognized input at all.
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $customerB = $this->customerFor($repB, 'B1');
        $this->entryFor($repB, $customerB, now()->addDay()->toDateString());

        $response = $this->actingAs($repA)
            ->getJson('/api/reports/coverage/my-week?representative_id=' . $repB->id);

        $response->assertOk();
        $this->assertSame([], $response->json('entries'));
    }

    public function test_rep_cannot_reach_the_team_report(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');

        $this->actingAs($rep)->getJson('/api/reports/coverage/team')->assertForbidden();
    }

    public function test_admin_team_report_groups_entries_by_representative(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $customerA = $this->customerFor($repA, 'A1');
        $customerB = $this->customerFor($repB, 'B1');
        $this->entryFor($repA, $customerA, now()->addDay()->toDateString());
        $this->entryFor($repB, $customerB, now()->addDay()->toDateString());

        $response = $this->actingAs($admin)->getJson('/api/reports/coverage/team');

        $response->assertOk();
        $names = collect($response->json())->pluck('representative.name')->sort()->values()->all();
        $this->assertSame(['a@t.test', 'b@t.test'], $names);
    }
}
