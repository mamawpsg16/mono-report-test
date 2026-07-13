<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visit data isolation (CRM pivot P3) + the one-open-visit-per-rep invariant.
 * The invariant matters twice: the service gives a friendly 422 on the normal
 * path, but the partial unique index is what actually holds under a race --
 * this test proves the DB constraint itself, not just the app-level check.
 */
class VisitAccessTest extends TestCase
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

    private function visitFor(User $representative, Customer $customer, bool $open = true): Visit
    {
        $visit = new Visit();
        $visit->customer_id = $customer->id;
        $visit->representative_id = $representative->id;
        $visit->started_at = now();
        if (! $open) {
            $visit->ended_at = now();
        }
        $visit->save();

        return $visit;
    }

    public function test_admin_sees_every_visit(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $repA = $this->user('a@t.test', 'sales_representative');
        $customer = $this->customerFor($repA, 'A1');

        $this->visitFor($repA, $customer);

        $this->assertCount(1, Visit::visibleTo($admin)->get());
    }

    public function test_rep_sees_only_their_own_visits(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $customer = $this->customerFor(null, 'C1');

        $this->visitFor($repA, $customer);
        $this->visitFor($repB, $customer);

        $this->assertCount(1, Visit::visibleTo($repA)->get());
        $this->assertCount(1, Visit::visibleTo($repB)->get());
    }

    public function test_rep_can_start_a_visit_to_their_own_customer(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');

        $response = $this->actingAs($rep)
            ->postJson('/api/visits', ['customer_id' => $customer->id]);

        $response->assertCreated();
        $this->assertDatabaseHas('visits', [
            'customer_id' => $customer->id,
            'representative_id' => $rep->id,
            'ended_at' => null,
        ]);
    }

    public function test_rep_cannot_start_a_visit_to_a_customer_they_cannot_see(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $other = $this->user('other@t.test', 'sales_representative');
        $notMine = $this->customerFor($other, 'C1');

        $response = $this->actingAs($rep)
            ->postJson('/api/visits', ['customer_id' => $notMine->id]);

        // Customer::visibleTo scopes the lookup, so an inaccessible customer
        // 404s (findOrFail) rather than leaking a 403/422 that confirms it exists.
        $response->assertNotFound();
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_starting_a_second_visit_while_one_is_open_is_rejected(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');
        $this->visitFor($rep, $customer); // already has one open

        $response = $this->actingAs($rep)
            ->postJson('/api/visits', ['customer_id' => $customer->id]);

        $response->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_the_one_open_visit_partial_index_rejects_a_second_open_row_at_the_db_level(): void
    {
        // Proves the actual guarantee, not just the service's pre-check: even
        // bypassing VisitService entirely, the DB itself refuses a second open
        // visit for the same rep. This is what protects against two concurrent
        // "Start Visit" requests racing past the app-level check.
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');
        $this->visitFor($rep, $customer);

        $this->expectException(QueryException::class);
        $this->visitFor($rep, $customer);
    }

    public function test_finishing_a_visit_closes_it(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');
        $visit = $this->visitFor($rep, $customer);

        $response = $this->actingAs($rep)
            ->patchJson("/api/visits/{$visit->uuid}/finish", ['notes' => 'All good']);

        $response->assertOk();
        $this->assertNotNull($visit->fresh()->ended_at);
        $this->assertSame('All good', $visit->fresh()->notes);
    }

    public function test_rep_cannot_finish_another_reps_visit(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $customer = $this->customerFor($repB, 'C1');
        $visit = $this->visitFor($repB, $customer);

        $this->actingAs($repA)
            ->patchJson("/api/visits/{$visit->uuid}/finish", [])
            ->assertForbidden();

        $this->assertTrue($visit->fresh()->isOpen());
    }
}
