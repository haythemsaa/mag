<?php

namespace Database\Seeders;

use App\Models\Workshop;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class WorkshopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        $workshopTemplates = [
            [
                'name' => 'AutoService Pro',
                'type' => 'multi_brand',
                'services' => ['maintenance', 'repair', 'bodywork', 'tire_service'],
                'brands' => ['Renault', 'Peugeot', 'Citroën', 'Volkswagen', 'Mercedes'],
            ],
            [
                'name' => 'Garage Mécanique Plus',
                'type' => 'independent',
                'services' => ['maintenance', 'repair', 'diagnostics'],
                'brands' => ['Renault', 'Peugeot', 'Citroën'],
            ],
            [
                'name' => 'TotalEnergies Garage',
                'type' => 'network',
                'services' => ['maintenance', 'tire_service', 'quick_service'],
                'brands' => ['all'],
            ],
        ];

        foreach ($organizations as $organization) {
            $workshopCount = rand(2, 4);

            for ($i = 0; $i < $workshopCount; $i++) {
                $template = $workshopTemplates[array_rand($workshopTemplates)];

                Workshop::create([
                    'organization_id' => $organization->id,
                    'name' => $template['name'] . ' ' . $organization->city,
                    'type' => $template['type'],
                    'address' => rand(1, 200) . ' Boulevard des Garages',
                    'postal_code' => '750' . str_pad(rand(1, 20), 2, '0', STR_PAD_LEFT),
                    'city' => $organization->city,
                    'country' => $organization->country,
                    'latitude' => 48.8566 + (rand(-200, 200) / 1000),
                    'longitude' => 2.3522 + (rand(-200, 200) / 1000),
                    'phone' => '+3314' . rand(10000000, 99999999),
                    'email' => strtolower(str_replace(' ', '', $template['name'])) . '@example.fr',
                    'website' => 'https://www.' . strtolower(str_replace(' ', '', $template['name'])) . '.fr',
                    'contact_name' => ['Michel Dubois', 'Laurent Petit', 'Thomas Roux'][array_rand(['Michel Dubois', 'Laurent Petit', 'Thomas Roux'])],
                    'services_offered' => $template['services'],
                    'brands_serviced' => $template['brands'],
                    'rating' => rand(30, 50) / 10, // 3.0 to 5.0
                    'total_interventions' => rand(50, 500),
                    'average_cost' => rand(150, 800),
                    'average_delay_days' => rand(1, 5),
                    'is_preferred' => (bool)rand(0, 1),
                    'is_active' => true,
                    'notes' => $i % 2 == 0 ? 'Excellent service, délais respectés' : null,
                ]);
            }
        }
    }
}
