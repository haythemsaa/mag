<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $organization) {
            // Create 2-3 sites per organization
            $siteCount = rand(2, 3);

            for ($i = 1; $i <= $siteCount; $i++) {
                Site::create([
                    'organization_id' => $organization->id,
                    'name' => $organization->name . ' - Site ' . $i,
                    'code' => strtoupper(substr($organization->name, 0, 3)) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'address' => ($i * 10) . ' Rue Example',
                    'postal_code' => '750' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'city' => $organization->city,
                    'country' => $organization->country,
                    'latitude' => 48.8566 + (rand(-100, 100) / 1000),
                    'longitude' => 2.3522 + (rand(-100, 100) / 1000),
                    'phone' => '+3314' . rand(10000000, 99999999),
                    'manager_name' => ['Jean Dupont', 'Marie Martin', 'Pierre Durant', 'Sophie Bernard'][array_rand(['Jean Dupont', 'Marie Martin', 'Pierre Durant', 'Sophie Bernard'])],
                    'manager_email' => 'manager' . $i . '@' . str_replace(' ', '', strtolower($organization->name)) . '.fr',
                    'is_active' => true,
                ]);
            }
        }
    }
}
