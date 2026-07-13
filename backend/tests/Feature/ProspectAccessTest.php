<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prospect data isolation (CRM pivot P2): a rep sees and can touch only the
 * prospects they created; admins see all. The dangerous case is a rep who
 * holds prospects.update yet tries to edit ANOTHER rep's prospect -- the
 * permission passes, only the policy's ownership check stops them.
 */
class ProspectAccessTest extends TestCase
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

    private function prospectFor(User $creator, string $name): Prospect
    {
        $prospect = new Prospect(['name' => $name]);
        $prospect->created_by = $creator->id;
        $prospect->save();

        return $prospect;
    }

    /**
     * Sort in PHP, not the DB: Postgres's locale collation ignores punctuation
     * and case, so an ORDER BY here would be non-obvious. Mirrors
     * CustomerScopeTest::visibleCodes.
     */
    private function visibleNames(User $user): array
    {
        return Prospect::visibleTo($user)->pluck('name')->sort()->values()->all();
    }

    public function test_admin_sees_every_prospect(): void
    {
        $admin = $this->user('admin@t.test', 'admin');
        $repA = $this->user('a@t.test', 'sales_representative');

        $this->prospectFor($repA, 'A-lead');
        $this->prospectFor($admin, 'ADM-lead');

        $this->assertSame(['A-lead', 'ADM-lead'], $this->visibleNames($admin));
    }

    public function test_rep_sees_only_their_own_prospects(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');

        $this->prospectFor($repA, 'A1');
        $this->prospectFor($repA, 'A2');
        $this->prospectFor($repB, 'B1');

        $this->assertSame(['A1', 'A2'], $this->visibleNames($repA));
        $this->assertSame(['B1'], $this->visibleNames($repB));
    }

    public function test_rep_can_create_a_prospect_owned_by_themselves(): void
    {
        $rep = $this->user('rep@t.test', 'sales_representative');

        $response = $this->actingAs($rep)
            ->postJson('/api/prospects', ['name' => 'New Lead', 'phone' => '024']);

        $response->assertCreated()->assertJsonPath('name', 'New Lead');
        $this->assertSame($rep->id, Prospect::first()->created_by);
    }

    public function test_rep_cannot_update_another_reps_prospect(): void
    {
        // The meaningful attacker: a rep who legitimately holds prospects.update.
        // A permission-only gate would wrongly let them edit anyone's prospect;
        // the policy's ownership check is what forbids it.
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $b1 = $this->prospectFor($repB, 'B1');

        $this->actingAs($repA)
            ->putJson("/api/prospects/{$b1->uuid}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('B1', $b1->fresh()->name);
    }

    public function test_rep_cannot_delete_another_reps_prospect(): void
    {
        $repA = $this->user('a@t.test', 'sales_representative');
        $repB = $this->user('b@t.test', 'sales_representative');
        $b1 = $this->prospectFor($repB, 'B1');

        $this->actingAs($repA)
            ->deleteJson("/api/prospects/{$b1->uuid}")
            ->assertForbidden();

        $this->assertDatabaseHas('prospects', ['id' => $b1->id]);
    }

    public function test_user_without_prospects_permission_is_refused(): void
    {
        // A user with no role has none of the prospects.* permissions, so the
        // route middleware refuses them before any policy runs.
        $noRole = $this->user('norole@t.test');

        $this->actingAs($noRole)
            ->getJson('/api/prospects')
            ->assertForbidden();
    }
}
