<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting FleetManager Pro database seeding...');
        $this->command->newLine();

        // 1. Seed roles and permissions FIRST
        $this->command->info('  📋 Seeding roles and permissions...');
        $this->call(RolePermissionSeeder::class);

        // 2. Seed organizations
        $this->command->info('  🏢 Seeding organizations...');
        $this->call(OrganizationSeeder::class);

        // 3. Create admin user and assign to first organization
        $this->command->info('  👤 Creating admin user...');
        $firstOrg = Organization::first();

        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@fleetmanager.fr',
            'password' => Hash::make('password'),
            'organization_id' => $firstOrg?->id,
            'is_active' => true,
        ]);
        $adminUser->assignRole('super-admin');

        $this->command->info("     ✓ Created admin user: {$adminUser->email} (password: password)");
        $this->command->info("     ✓ Assigned role: super-admin");
        $this->command->info("     ✓ Organization: {$firstOrg?->name}");
        $this->command->newLine();

        // 4. Seed fleet management data in correct order
        $this->command->info('  🚗 Seeding fleet management data...');
        $this->call([
            SiteSeeder::class,
            WorkshopSeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            MaintenanceSeeder::class,
            FuelTransactionSeeder::class,
            ContractSeeder::class,
            CostSeeder::class,
            GpsPositionSeeder::class,
            GeofenceSeeder::class,
            InfractionSeeder::class,
            AccidentSeeder::class,
            ChargingStationSeeder::class,
            ChargingSessionSeeder::class,
            TheftAlertSeeder::class,
            RouteSeeder::class,
            AccountingExportSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('✅ Database seeded successfully with fleet management data!');
        $this->command->newLine();
        $this->command->info('📝 Quick Start:');
        $this->command->info("   Email: {$adminUser->email}");
        $this->command->info('   Password: password');
        $this->command->info("   Role: super-admin");
        $this->command->info("   Organization: {$firstOrg?->name}");
    }
}
