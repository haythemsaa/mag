<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Site;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        $firstNames = ['Jean', 'Pierre', 'Michel', 'André', 'Philippe', 'Marie', 'Nathalie', 'Sophie', 'Isabelle', 'Catherine'];
        $lastNames = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau'];

        foreach ($organizations as $organization) {
            $sites = $organization->sites;
            $driverCount = rand(10, 25);

            for ($i = 0; $i < $driverCount; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $site = $sites->count() > 0 ? $sites->random() : null;

                Driver::create([
                    'organization_id' => $organization->id,
                    'site_id' => $site?->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => strtolower($firstName . '.' . $lastName . $i) . '@' . str_replace(' ', '', strtolower($organization->name)) . '.fr',
                    'employee_id' => 'EMP-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                    'license_number' => strtoupper(substr($lastName, 0, 3)) . rand(100000, 999999),
                    'license_type' => ['B', 'C', 'D'][array_rand(['B', 'C', 'D'])],
                    'license_expiry_date' => now()->addYears(rand(1, 5)),
                    'license_points' => rand(8, 12),
                    'phone' => '+336' . rand(10000000, 99999999),
                    'phone_mobile' => '+336' . rand(10000000, 99999999),
                    'birth_date' => now()->subYears(rand(25, 60)),
                    'hiring_date' => now()->subMonths(rand(6, 60)),
                    'address' => rand(1, 200) . ' Rue de la Paix',
                    'postal_code' => '750' . str_pad(rand(1, 20), 2, '0', STR_PAD_LEFT),
                    'city' => $organization->city,
                    'country' => 'FR',
                    'status' => ['active', 'active', 'active', 'suspended'][array_rand(['active', 'active', 'active', 'suspended'])],
                    'is_active' => true,
                    'eco_driving_score' => rand(60, 100),
                    'total_infractions' => rand(0, 5),
                    'next_medical_check' => now()->addMonths(rand(3, 24)),
                    'notes' => $i % 5 == 0 ? 'Conducteur exemplaire, aucun incident' : null,
                ]);
            }
        }
    }
}
