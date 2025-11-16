<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\VehiclePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehiclePolicyTest extends TestCase
{
    use RefreshDatabase;

    private VehiclePolicy $policy;
    private Organization $organization1;
    private Organization $organization2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new VehiclePolicy();

        // Create organizations
        $this->organization1 = Organization::factory()->create(['name' => 'Organization 1']);
        $this->organization2 = Organization::factory()->create(['name' => 'Organization 2']);

        // Create roles
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'fleet-manager']);
        Role::create(['name' => 'viewer']);
    }

    public function test_super_admin_can_do_anything(): void
    {
        $superAdmin = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $superAdmin->assignRole('super-admin');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization2->id, // Different organization
        ]);

        // Super admin should bypass all checks
        $this->assertTrue($this->policy->before($superAdmin, 'view'));
        $this->assertTrue($this->policy->view($superAdmin, $vehicle));
        $this->assertTrue($this->policy->update($superAdmin, $vehicle));
        $this->assertTrue($this->policy->delete($superAdmin, $vehicle));
    }

    public function test_user_can_view_vehicle_in_same_organization(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $user->assignRole('fleet-manager');
        $user->givePermissionTo('vehicles.view');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);

        $this->assertTrue($this->policy->view($user, $vehicle));
    }

    public function test_user_cannot_view_vehicle_in_different_organization(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $user->assignRole('fleet-manager');
        $user->givePermissionTo('vehicles.view');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization2->id, // Different organization
        ]);

        $this->assertFalse($this->policy->view($user, $vehicle));
    }

    public function test_user_can_update_vehicle_with_permission_and_same_organization(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $user->assignRole('fleet-manager');
        $user->givePermissionTo('vehicles.update');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);

        $this->assertTrue($this->policy->update($user, $vehicle));
    }

    public function test_user_cannot_update_vehicle_without_permission(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $user->assignRole('viewer'); // No update permission

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);

        $this->assertFalse($this->policy->update($user, $vehicle));
    }

    public function test_user_can_assign_driver_with_permission(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $user->assignRole('fleet-manager');
        $user->givePermissionTo('vehicles.assign-driver');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);

        $this->assertTrue($this->policy->assignDriver($user, $vehicle));
    }

    public function test_user_cannot_assign_driver_to_vehicle_in_different_organization(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $user->assignRole('fleet-manager');
        $user->givePermissionTo('vehicles.assign-driver');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization2->id, // Different organization
        ]);

        $this->assertFalse($this->policy->assignDriver($user, $vehicle));
    }

    public function test_user_can_view_statistics_with_permission(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $user->assignRole('fleet-manager');
        $user->givePermissionTo('vehicles.statistics');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);

        $this->assertTrue($this->policy->viewStatistics($user, $vehicle));
    }

    public function test_user_can_delete_vehicle_with_permission(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $user->assignRole('fleet-manager');
        $user->givePermissionTo('vehicles.delete');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);

        $this->assertTrue($this->policy->delete($user, $vehicle));
    }

    public function test_only_super_admin_can_force_delete(): void
    {
        $fleetManager = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $fleetManager->assignRole('fleet-manager');
        $fleetManager->givePermissionTo('vehicles.delete');

        $superAdmin = User::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);
        $superAdmin->assignRole('super-admin');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization1->id,
        ]);

        $this->assertFalse($this->policy->forceDelete($fleetManager, $vehicle));
        $this->assertTrue($this->policy->forceDelete($superAdmin, $vehicle));
    }
}
