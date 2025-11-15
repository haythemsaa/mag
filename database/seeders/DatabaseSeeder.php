<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default user for testing
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@fleetmanager.fr',
        ]);

        // Seed fleet management data in correct order
        $this->call([
            OrganizationSeeder::class,
            SiteSeeder::class,
            WorkshopSeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            MaintenanceSeeder::class,
            FuelTransactionSeeder::class,
        ]);

        $this->command->info('Database seeded successfully with fleet management data!');
    }
}
