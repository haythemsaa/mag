<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Main organization
        $mainOrg = Organization::create([
            'name' => 'FleetCorp France',
            'legal_name' => 'FleetCorp France SAS',
            'siret' => '12345678901234',
            'vat_number' => 'FR12345678901',
            'address' => '123 Avenue des Champs-Élysées',
            'postal_code' => '75008',
            'city' => 'Paris',
            'country' => 'FR',
            'phone' => '+33142563478',
            'email' => 'contact@fleetcorp.fr',
            'subscription_plan' => 'enterprise',
            'max_vehicles' => 500,
            'is_active' => true,
        ]);

        // Child organizations
        Organization::create([
            'name' => 'FleetCorp Île-de-France',
            'legal_name' => 'FleetCorp Île-de-France SARL',
            'siret' => '23456789012345',
            'vat_number' => 'FR23456789012',
            'address' => '45 Rue de la République',
            'postal_code' => '92100',
            'city' => 'Boulogne-Billancourt',
            'country' => 'FR',
            'phone' => '+33147896523',
            'email' => 'idf@fleetcorp.fr',
            'parent_id' => $mainOrg->id,
            'subscription_plan' => 'professional',
            'max_vehicles' => 200,
            'is_active' => true,
        ]);

        Organization::create([
            'name' => 'FleetCorp Sud',
            'legal_name' => 'FleetCorp Sud SAS',
            'siret' => '34567890123456',
            'vat_number' => 'FR34567890123',
            'address' => '78 La Canebière',
            'postal_code' => '13001',
            'city' => 'Marseille',
            'country' => 'FR',
            'phone' => '+33491234567',
            'email' => 'sud@fleetcorp.fr',
            'parent_id' => $mainOrg->id,
            'subscription_plan' => 'professional',
            'max_vehicles' => 150,
            'is_active' => true,
        ]);

        // Independent organization
        Organization::create([
            'name' => 'Transport Express Lyon',
            'legal_name' => 'Transport Express Lyon EURL',
            'siret' => '45678901234567',
            'vat_number' => 'FR45678901234',
            'address' => '156 Cours Lafayette',
            'postal_code' => '69003',
            'city' => 'Lyon',
            'country' => 'FR',
            'phone' => '+33478965412',
            'email' => 'contact@transport-express-lyon.fr',
            'subscription_plan' => 'professional',
            'max_vehicles' => 100,
            'is_active' => true,
        ]);

        // Small organization
        Organization::create([
            'name' => 'Logistique Bordeaux',
            'legal_name' => 'Logistique Bordeaux SAS',
            'siret' => '56789012345678',
            'vat_number' => 'FR56789012345',
            'address' => '23 Cours de l\'Intendance',
            'postal_code' => '33000',
            'city' => 'Bordeaux',
            'country' => 'FR',
            'phone' => '+33556874523',
            'email' => 'info@logistique-bordeaux.fr',
            'subscription_plan' => 'starter',
            'max_vehicles' => 20,
            'is_active' => true,
        ]);
    }
}
