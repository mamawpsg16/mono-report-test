<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The security spine: a sales rep may only ever see customers they own or are
 * actively covering. Admins see everything. If any of these break, the whole
 * CRM's data isolation is broken.
 */
class CustomerScopeTest extends TestCase
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
            'email' => "{$code}@shop.test", // customers.email is NOT NULL
            'assigned_representative_id' => $owner?->id,
        ]);
    }

    private function visibleCodes(User $user): array
    {
        return Customer::visibleTo($user)->pluck('customer_code')->sort()->values()->all();
    }

    public function test_admin_sees_every_customer(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $repA = $this->user('a@t.test', 'sales_representative');

        $this->customerFor($repA, 'A1');
        $this->customerFor(null, 'UNASSIGNED');

        $this->assertSame(['A1', 'UNASSIGNED'], $this->visibleCodes($admin));
    }

    public function test_rep_sees_only_their_own_customers(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');

        $this->customerFor($repA, 'A1');
        $this->customerFor($repA, 'A2');
        $this->customerFor($repB, 'B1');
        $this->customerFor(null, 'UNASSIGNED');

        $this->assertSame(['A1', 'A2'], $this->visibleCodes($repA));
        $this->assertSame(['B1'], $this->visibleCodes($repB));
    }

    public function test_whole_book_coverage_exposes_the_absent_reps_customers_only_within_the_window(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');

        $this->customerFor($repA, 'A1');
        $this->customerFor($repA, 'A2');
        $this->customerFor($repB, 'B1');

        // active whole-book grant: B covers all of A
        $active = Coverage::create([
            'representative_id' => $repA->id,
            'covering_representative_id' => $repB->id,
            'customer_id' => null,
            'starts_on' => today()->subDay(),
            'ends_on' => today()->addDay(),
        ]);

        $this->assertSame(['A1', 'A2', 'B1'], $this->visibleCodes($repB));

        // move the window into the past → B loses A's customers again
        $active->update(['starts_on' => today()->subDays(5), 'ends_on' => today()->subDays(2)]);

        $this->assertSame(['B1'], $this->visibleCodes($repB));
    }

    public function test_single_customer_coverage_exposes_only_that_one_customer(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');

        $a1 = $this->customerFor($repA, 'A1');
        $this->customerFor($repA, 'A2'); // NOT covered

        Coverage::create([
            'representative_id' => $repA->id,
            'covering_representative_id' => $repB->id,
            'customer_id' => $a1->id, // only this one
            'starts_on' => today(),
            'ends_on' => today(),
        ]);

        // B sees only A1, never A2
        $this->assertSame(['A1'], $this->visibleCodes($repB));
    }
}
