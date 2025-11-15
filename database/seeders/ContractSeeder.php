<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = Vehicle::with('organization')->get();

        $contractTemplates = [
            'lease' => [
                'suppliers' => ['LeasePlan France', 'ALD Automotive', 'Arval', 'Alphabet France'],
                'monthly_cost_range' => [300, 800],
                'duration_range' => [24, 48],
            ],
            'insurance' => [
                'suppliers' => ['AXA Pro', 'Allianz France', 'Groupama', 'Generali'],
                'monthly_cost_range' => [80, 250],
                'duration_range' => [12, 12], // Annual renewal
            ],
            'maintenance' => [
                'suppliers' => ['Total Maintenance', 'Entretien Pro', 'Fleet Services', 'Auto Care Plus'],
                'monthly_cost_range' => [150, 400],
                'duration_range' => [12, 36],
            ],
            'rental' => [
                'suppliers' => ['Europcar Business', 'Sixt Corporate', 'Enterprise Fleet', 'Hertz Pro'],
                'monthly_cost_range' => [400, 1200],
                'duration_range' => [6, 24],
            ],
        ];

        $this->command->info('Seeding contracts...');

        foreach ($vehicles as $vehicle) {
            // Each vehicle gets 1-3 contracts
            $numContracts = rand(1, 3);
            $contractTypes = array_keys($contractTemplates);
            shuffle($contractTypes);

            for ($i = 0; $i < $numContracts; $i++) {
                $type = $contractTypes[$i];
                $template = $contractTemplates[$type];

                // Random supplier from template
                $supplier = $template['suppliers'][array_rand($template['suppliers'])];

                // Generate contract dates
                $startDate = now()->subMonths(rand(1, 36));
                $durationMonths = rand($template['duration_range'][0], $template['duration_range'][1]);
                $endDate = (clone $startDate)->addMonths($durationMonths);

                // Calculate costs
                $monthlyCost = rand($template['monthly_cost_range'][0] * 10, $template['monthly_cost_range'][1] * 10) / 10;
                $totalCost = $monthlyCost * $durationMonths;

                // Determine status
                $status = 'active';
                if ($endDate < now()) {
                    $status = rand(0, 9) < 8 ? 'expired' : 'cancelled'; // 80% expired, 20% cancelled
                } elseif ($endDate->diffInDays(now()) <= 30 && rand(0, 10) < 3) {
                    // Some contracts expiring soon might be cancelled
                    $status = 'cancelled';
                }

                // Auto-renewal more likely for insurance
                $autoRenewal = $type === 'insurance' ? (rand(0, 10) > 3) : (rand(0, 10) > 7);

                $contractNumber = strtoupper(substr($type, 0, 3)) . '-' .
                                  str_pad($vehicle->id, 4, '0', STR_PAD_LEFT) . '-' .
                                  rand(1000, 9999);

                Contract::create([
                    'organization_id' => $vehicle->organization_id,
                    'vehicle_id' => $vehicle->id,
                    'contract_number' => $contractNumber,
                    'type' => $type,
                    'supplier_name' => $supplier,
                    'supplier_contact' => 'Service Client',
                    'supplier_email' => strtolower(str_replace(' ', '', $supplier)) . '@contact.fr',
                    'supplier_phone' => '01' . rand(10, 99) . rand(10, 99) . rand(10, 99) . rand(10, 99),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'duration_months' => $durationMonths,
                    'monthly_cost' => $monthlyCost,
                    'total_cost' => $totalCost,
                    'mileage_limit_annual' => $type === 'lease' ? rand(15000, 30000) : null,
                    'excess_mileage_cost' => $type === 'lease' ? rand(5, 15) / 100 : null,
                    'terms' => $this->generateTerms($type),
                    'status' => $status,
                    'auto_renewal' => $autoRenewal,
                    'notes' => $status === 'cancelled' ? 'Contrat résilié par le client' : null,
                ]);
            }
        }

        $totalContracts = Contract::count();
        $this->command->info("Created {$totalContracts} contracts");
        $this->command->info('  - Active: ' . Contract::where('status', 'active')->count());
        $this->command->info('  - Expired: ' . Contract::where('status', 'expired')->count());
        $this->command->info('  - Cancelled: ' . Contract::where('status', 'cancelled')->count());
        $this->command->info('  - Expiring soon (30 days): ' .
            Contract::where('status', 'active')
                ->where('end_date', '>=', now())
                ->where('end_date', '<=', now()->addDays(30))
                ->count()
        );
    }

    /**
     * Generate contract terms based on type
     */
    private function generateTerms(string $type): string
    {
        $terms = [
            'lease' => "Location longue durée avec entretien inclus.\nFranchise kilométrique annuelle avec surplus facturé.\nVéhicule de remplacement en cas de panne.\nAssistance 24/7 incluse.",
            'insurance' => "Assurance tous risques.\nFranchise: 500€\nProtection juridique incluse.\nVéhicule de remplacement: 30 jours maximum.",
            'maintenance' => "Entretien préventif selon préconisations constructeur.\nPièces d'usure incluses.\nMain d'oeuvre incluse.\nVéhicule de courtoisie sur demande.",
            'rental' => "Location courte durée.\nKilométrage illimité.\nAssurance au tiers incluse.\nRestitution flexible sous conditions.",
        ];

        return $terms[$type];
    }
}
