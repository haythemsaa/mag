<?php

namespace Database\Seeders;

use App\Models\Cost;
use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Database\Seeder;

class CostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = Vehicle::with('organization')->get();
        $users = User::all();

        $costTemplates = [
            'fuel' => [
                'suppliers' => ['Total', 'Shell', 'BP', 'Esso', 'Intermarché Carburant'],
                'amount_range' => [40, 120],
                'subcategories' => ['Diesel', 'Essence', 'GPL', 'Électricité'],
                'vat_deductible' => true,
                'account_code' => '6061',
            ],
            'maintenance' => [
                'suppliers' => ['Garage Dupont', 'Auto Services', 'Midas', 'Norauto', 'Speedy'],
                'amount_range' => [80, 800],
                'subcategories' => ['Révision', 'Vidange', 'Freins', 'Pneus', 'Climatisation', 'Diagnostic'],
                'vat_deductible' => true,
                'account_code' => '6155',
            ],
            'insurance' => [
                'suppliers' => ['AXA', 'Allianz', 'Groupama', 'Generali', 'MAIF'],
                'amount_range' => [80, 250],
                'subcategories' => ['Prime mensuelle', 'Prime annuelle', 'Franchise sinistre'],
                'vat_deductible' => false,
                'account_code' => '6161',
            ],
            'tax' => [
                'suppliers' => ['Direction Générale des Finances Publiques', 'Trésor Public'],
                'amount_range' => [120, 600],
                'subcategories' => ['Carte grise', 'Taxe annuelle', 'Malus écologique'],
                'vat_deductible' => false,
                'account_code' => '6351',
            ],
            'parking' => [
                'suppliers' => ['Parking Gare', 'Indigo', 'Q-Park', 'Effia', 'Zenpark'],
                'amount_range' => [2, 45],
                'subcategories' => ['Parking horaire', 'Abonnement mensuel', 'Stationnement résidentiel'],
                'vat_deductible' => true,
                'account_code' => '6251',
            ],
            'toll' => [
                'suppliers' => ['Vinci Autoroutes', 'APRR', 'Sanef', 'ASF'],
                'amount_range' => [5, 80],
                'subcategories' => ['Péage autoroutier', 'Badge télépéage'],
                'vat_deductible' => true,
                'account_code' => '6251',
            ],
            'fine' => [
                'suppliers' => ['Trésor Public', 'ANTAI', 'Préfecture de Police'],
                'amount_range' => [35, 135],
                'subcategories' => ['Excès de vitesse', 'Stationnement interdit', 'Feu rouge', 'Téléphone au volant'],
                'vat_deductible' => false,
                'account_code' => '6712',
            ],
            'other' => [
                'suppliers' => ['Divers fournisseurs', 'Station lavage', 'Auto Équipement'],
                'amount_range' => [10, 200],
                'subcategories' => ['Lavage', 'Accessoires', 'Péage parking', 'Réparation mineure'],
                'vat_deductible' => true,
                'account_code' => '6068',
            ],
        ];

        $this->command->info('Seeding costs...');

        foreach ($vehicles as $vehicle) {
            // Each vehicle gets 3-8 costs
            $numCosts = rand(3, 8);

            for ($i = 0; $i < $numCosts; $i++) {
                // Random category
                $categories = array_keys($costTemplates);
                $category = $categories[array_rand($categories)];
                $template = $costTemplates[$category];

                // Random supplier and subcategory
                $supplier = $template['suppliers'][array_rand($template['suppliers'])];
                $subcategory = $template['subcategories'][array_rand($template['subcategories'])];

                // Random date in the past year
                $date = now()->subDays(rand(1, 365));

                // Random amount
                $amount = rand($template['amount_range'][0] * 10, $template['amount_range'][1] * 10) / 10;

                // Calculate VAT if deductible
                $vatAmount = null;
                if ($template['vat_deductible']) {
                    $vatAmount = round($amount * 0.20, 2); // 20% VAT
                }

                // 75% of costs are validated
                $validated = rand(0, 99) < 75;
                $validatedBy = null;
                $validatedAt = null;

                if ($validated && $users->count() > 0) {
                    $validatedBy = $users->random()->id;
                    $validatedAt = (clone $date)->addDays(rand(1, 7));
                }

                // Generate reference and invoice number
                $reference = strtoupper(substr($category, 0, 3)) . '-' . $vehicle->id . '-' . rand(1000, 9999);
                $invoiceNumber = 'INV-' . $date->format('Y') . '-' . rand(10000, 99999);

                // Generate description
                $description = $this->generateDescription($category, $subcategory, $vehicle);

                // Get current mileage for some categories
                $mileage = null;
                if (in_array($category, ['fuel', 'maintenance'])) {
                    // Estimate mileage at time of cost
                    $daysAgo = now()->diffInDays($date);
                    $estimatedKmPerDay = 50; // Average 50km per day
                    $mileage = max(0, $vehicle->mileage - ($daysAgo * $estimatedKmPerDay));
                }

                Cost::create([
                    'organization_id' => $vehicle->organization_id,
                    'vehicle_id' => $vehicle->id,
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'date' => $date,
                    'amount' => $amount,
                    'currency' => 'EUR',
                    'supplier_name' => $supplier,
                    'invoice_number' => $invoiceNumber,
                    'reference' => $reference,
                    'description' => $description,
                    'mileage' => $mileage,
                    'validated' => $validated,
                    'validated_by' => $validatedBy,
                    'validated_at' => $validatedAt,
                    'account_code' => $template['account_code'],
                    'vat_deductible' => $template['vat_deductible'],
                    'vat_amount' => $vatAmount,
                ]);
            }
        }

        $totalCosts = Cost::count();
        $this->command->info("Created {$totalCosts} costs");
        $this->command->info('  - Validated: ' . Cost::where('validated', true)->count());
        $this->command->info('  - Pending validation: ' . Cost::where('validated', false)->count());
        $this->command->info('  - Total amount: ' . number_format(Cost::sum('amount'), 2) . ' EUR');
        $this->command->info('  - VAT deductible: ' . number_format(Cost::where('vat_deductible', true)->sum('vat_amount'), 2) . ' EUR');

        foreach (array_keys($costTemplates) as $category) {
            $count = Cost::where('category', $category)->count();
            $total = Cost::where('category', $category)->sum('amount');
            $this->command->info("  - {$category}: {$count} costs, " . number_format($total, 2) . " EUR");
        }
    }

    /**
     * Generate description based on category and subcategory
     */
    private function generateDescription(string $category, string $subcategory, $vehicle): string
    {
        $descriptions = [
            'fuel' => "Plein de carburant - {$subcategory} - {$vehicle->registration_number}",
            'maintenance' => "{$subcategory} réalisée - {$vehicle->make} {$vehicle->model}",
            'insurance' => "Assurance véhicule - {$subcategory} - Contrat n°" . rand(100000, 999999),
            'tax' => "{$subcategory} - Véhicule {$vehicle->registration_number}",
            'parking' => "{$subcategory} - Zone urbaine",
            'toll' => "{$subcategory} - Trajet professionnel",
            'fine' => "{$subcategory} - PV n°" . rand(1000000, 9999999),
            'other' => "{$subcategory} - Frais divers véhicule",
        ];

        return $descriptions[$category] ?? "Frais de {$category} - {$subcategory}";
    }
}
