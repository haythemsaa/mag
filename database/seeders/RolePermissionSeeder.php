<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // Organizations
            'organizations.view',
            'organizations.create',
            'organizations.update',
            'organizations.delete',
            'organizations.statistics',

            // Vehicles
            'vehicles.view',
            'vehicles.create',
            'vehicles.update',
            'vehicles.delete',
            'vehicles.assign-driver',
            'vehicles.update-mileage',
            'vehicles.statistics',

            // Drivers
            'drivers.view',
            'drivers.create',
            'drivers.update',
            'drivers.delete',

            // Maintenances
            'maintenances.view',
            'maintenances.create',
            'maintenances.update',
            'maintenances.delete',
            'maintenances.complete',

            // Fuel Transactions
            'fuel-transactions.view',
            'fuel-transactions.create',
            'fuel-transactions.update',
            'fuel-transactions.delete',
            'fuel-transactions.validate',
            'fuel-transactions.statistics',

            // Sites
            'sites.view',
            'sites.create',
            'sites.update',
            'sites.delete',

            // Workshops
            'workshops.view',
            'workshops.create',
            'workshops.update',
            'workshops.delete',

            // Contracts
            'contracts.view',
            'contracts.create',
            'contracts.update',
            'contracts.delete',

            // Costs
            'costs.view',
            'costs.create',
            'costs.update',
            'costs.delete',
            'costs.validate',
            'costs.statistics',

            // GPS Positions
            'gps-positions.view',
            'gps-positions.create',
            'gps-positions.update',
            'gps-positions.delete',
            'gps-positions.live-tracking',
            'gps-positions.geofence',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $this->command->info('Created ' . count($permissions) . ' permissions');

        // Create roles and assign permissions

        // 1. Super Admin - All permissions
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());
        $this->command->info('Created super-admin role with all permissions');

        // 2. Organization Admin - Full access to organization data
        $orgAdmin = Role::create(['name' => 'organization-admin']);
        $orgAdmin->givePermissionTo([
            'organizations.view',
            'organizations.update',
            'organizations.statistics',

            'vehicles.view',
            'vehicles.create',
            'vehicles.update',
            'vehicles.delete',
            'vehicles.assign-driver',
            'vehicles.update-mileage',
            'vehicles.statistics',

            'drivers.view',
            'drivers.create',
            'drivers.update',
            'drivers.delete',

            'maintenances.view',
            'maintenances.create',
            'maintenances.update',
            'maintenances.delete',
            'maintenances.complete',

            'fuel-transactions.view',
            'fuel-transactions.create',
            'fuel-transactions.update',
            'fuel-transactions.delete',
            'fuel-transactions.validate',
            'fuel-transactions.statistics',

            'sites.view',
            'sites.create',
            'sites.update',
            'sites.delete',

            'workshops.view',
            'workshops.create',
            'workshops.update',
            'workshops.delete',

            'contracts.view',
            'contracts.create',
            'contracts.update',
            'contracts.delete',

            'costs.view',
            'costs.create',
            'costs.update',
            'costs.delete',
            'costs.validate',
            'costs.statistics',

            'gps-positions.view',
            'gps-positions.create',
            'gps-positions.update',
            'gps-positions.delete',
            'gps-positions.live-tracking',
            'gps-positions.geofence',
        ]);
        $this->command->info('Created organization-admin role');

        // 3. Fleet Manager - Vehicle and driver management
        $fleetManager = Role::create(['name' => 'fleet-manager']);
        $fleetManager->givePermissionTo([
            'organizations.view',

            'vehicles.view',
            'vehicles.create',
            'vehicles.update',
            'vehicles.assign-driver',
            'vehicles.update-mileage',
            'vehicles.statistics',

            'drivers.view',
            'drivers.create',
            'drivers.update',

            'maintenances.view',
            'maintenances.create',
            'maintenances.update',
            'maintenances.complete',

            'fuel-transactions.view',
            'fuel-transactions.create',
            'fuel-transactions.update',
            'fuel-transactions.statistics',

            'sites.view',
            'workshops.view',
            'contracts.view',
            'costs.view',

            'gps-positions.view',
            'gps-positions.create',
            'gps-positions.live-tracking',
            'gps-positions.geofence',
        ]);
        $this->command->info('Created fleet-manager role');

        // 4. Accountant - Financial management
        $accountant = Role::create(['name' => 'accountant']);
        $accountant->givePermissionTo([
            'organizations.view',
            'organizations.statistics',

            'vehicles.view',
            'vehicles.statistics',

            'drivers.view',

            'maintenances.view',

            'fuel-transactions.view',
            'fuel-transactions.validate',
            'fuel-transactions.statistics',

            'sites.view',
            'workshops.view',

            'contracts.view',
            'contracts.create',
            'contracts.update',
            'contracts.delete',

            'costs.view',
            'costs.create',
            'costs.update',
            'costs.validate',
            'costs.statistics',

            'gps-positions.view',
        ]);
        $this->command->info('Created accountant role');

        // 5. Maintenance Manager - Workshop and maintenance focus
        $maintenanceManager = Role::create(['name' => 'maintenance-manager']);
        $maintenanceManager->givePermissionTo([
            'organizations.view',

            'vehicles.view',
            'vehicles.update-mileage',

            'drivers.view',

            'maintenances.view',
            'maintenances.create',
            'maintenances.update',
            'maintenances.delete',
            'maintenances.complete',

            'fuel-transactions.view',

            'sites.view',

            'workshops.view',
            'workshops.create',
            'workshops.update',

            'contracts.view',
            'costs.view',
            'costs.create',

            'gps-positions.view',
        ]);
        $this->command->info('Created maintenance-manager role');

        // 6. Driver - Limited view of assigned vehicle and personal data
        $driver = Role::create(['name' => 'driver']);
        $driver->givePermissionTo([
            'vehicles.view',
            'maintenances.view',
            'fuel-transactions.view',
            'fuel-transactions.create',
            'gps-positions.view',
        ]);
        $this->command->info('Created driver role');

        // 7. Viewer - Read-only access
        $viewer = Role::create(['name' => 'viewer']);
        $viewer->givePermissionTo([
            'organizations.view',
            'vehicles.view',
            'drivers.view',
            'maintenances.view',
            'fuel-transactions.view',
            'sites.view',
            'workshops.view',
            'contracts.view',
            'costs.view',
            'gps-positions.view',
        ]);
        $this->command->info('Created viewer role');

        $this->command->info('✓ Role and permission seeder completed successfully');
        $this->command->info('  - Total permissions: ' . Permission::count());
        $this->command->info('  - Total roles: ' . Role::count());
    }
}
