<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rep assignment (CRM pivot P1): only admins may set/clear a customer's
 * owning sales rep, the target must actually BE a sales rep, and an
 * assignment must immediately change what the rep can see (scopeVisibleTo).
 */
class CustomerRepAssignmentTest extends TestCase
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
            'email' => "{$code}@shop.test", // customers.email is NOT NULL
            'assigned_representative_id' => $owner?->id,
        ]);
    }

    public function test_admin_can_assign_a_representative_to_a_customer(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor(null, 'C1');

        $response = $this->actingAs($admin)
            ->patchJson("/api/customers/{$customer->uuid}/representative", [
                'assigned_representative_id' => $rep->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('assigned_representative.id', $rep->id);

        $this->assertSame($rep->id, $customer->fresh()->assigned_representative_id);
        // the actor is stamped, so "who reassigned this" stays auditable
        $this->assertSame($admin->id, $customer->fresh()->updated_by);
    }

    public function test_admin_can_unassign_a_customer_by_setting_null(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor($rep, 'C1');

        $response = $this->actingAs($admin)
            ->patchJson("/api/customers/{$customer->uuid}/representative", [
                'assigned_representative_id' => null,
            ]);

        $response->assertOk();
        $this->assertNull($customer->fresh()->assigned_representative_id);
    }

    public function test_non_admin_cannot_assign_a_representative(): void
    {
        // The meaningful attacker: a sales rep. They already hold
        // customers.update (for the CSV confirm flow), so a naive
        // permission-only gate would wrongly let them re-own customers.
        $rep = $this->user('rep@t.test', 'sales_representative');
        $other = $this->user('other@t.test', 'sales_representative');
        $customer = $this->customerFor($other, 'C1');

        $response = $this->actingAs($rep)
            ->patchJson("/api/customers/{$customer->uuid}/representative", [
                'assigned_representative_id' => $rep->id,
            ]);

        $response->assertForbidden();
        $this->assertSame($other->id, $customer->fresh()->assigned_representative_id);
    }

    public function test_assigning_a_non_representative_user_is_rejected(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $notARep = $this->user('norole@t.test'); // no role at all
        $customer = $this->customerFor(null, 'C1');

        $response = $this->actingAs($admin)
            ->patchJson("/api/customers/{$customer->uuid}/representative", [
                'assigned_representative_id' => $notARep->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_representative_id');
        $this->assertNull($customer->fresh()->assigned_representative_id);
    }

    public function test_reassignment_is_reflected_in_the_reassigned_reps_visible_customers(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $customer = $this->customerFor($repA, 'C1');

        $this->actingAs($admin)
            ->patchJson("/api/customers/{$customer->uuid}/representative", [
                'assigned_representative_id' => $repB->id,
            ])
            ->assertOk();

        // the endpoint and scopeVisibleTo must agree, immediately
        $this->assertTrue(Customer::visibleTo($repB)->whereKey($customer->id)->exists());
        $this->assertFalse(Customer::visibleTo($repA)->whereKey($customer->id)->exists());
    }

    public function test_admin_can_bulk_assign_selected_customers_to_a_rep(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $rep = $this->user('rep@t.test', 'sales_representative');
        $c1 = $this->customerFor(null, 'C1');
        $c2 = $this->customerFor(null, 'C2');
        $c3 = $this->customerFor(null, 'LEFT-ALONE');

        $response = $this->actingAs($admin)
            ->patchJson('/api/customers/assign-representative', [
                'customer_uuids' => [$c1->uuid, $c2->uuid],
                'assigned_representative_id' => $rep->id,
            ]);

        $response->assertOk()->assertJson(['updated' => 2]);
        $this->assertSame($rep->id, $c1->fresh()->assigned_representative_id);
        $this->assertSame($rep->id, $c2->fresh()->assigned_representative_id);
        $this->assertNull($c3->fresh()->assigned_representative_id);
    }

    public function test_bulk_assignment_is_rejected_for_non_admins(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');
        $customer = $this->customerFor(null, 'C1');

        $this->actingAs($rep)
            ->patchJson('/api/customers/assign-representative', [
                'customer_uuids' => [$customer->uuid],
                'assigned_representative_id' => $rep->id,
            ])
            ->assertForbidden();

        $this->assertNull($customer->fresh()->assigned_representative_id);
    }
}
