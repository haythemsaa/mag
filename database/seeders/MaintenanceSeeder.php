<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Database\Seeder;

class MaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = Vehicle::all();

        $maintenanceTypes = [
            ['type' => 'preventive', 'category' => 'oil_change', 'description' => 'Vidange et remplacement filtre à huile'],
            ['type' => 'preventive', 'category' => 'tire_change', 'description' => 'Changement pneumatiques'],
            ['type' => 'preventive', 'category' => 'brake_service', 'description' => 'Révision freins'],
            ['type' => 'curative', 'category' => 'engine_repair', 'description' => 'Réparation moteur'],
            ['type' => 'curative', 'category' => 'transmission', 'description' => 'Réparation transmission'],
            ['type' => 'recall', 'category' => 'manufacturer_recall', 'description' => 'Rappel constructeur'],
        ];

        foreach ($vehicles as $vehicle) {
            $workshops = Workshop::where('organization_id', $vehicle->organization_id)->get();
            if ($workshops->isEmpty()) continue;

            // Create 2-5 maintenances per vehicle
            $maintenanceCount = rand(2, 5);

            for ($i = 0; $i < $maintenanceCount; $i++) {
                $template = $maintenanceTypes[array_rand($maintenanceTypes)];
                $workshop = $workshops->random();

                $scheduledDate = now()->subMonths(rand(1, 12));
                $isCompleted = rand(0, 10) > 2; // 80% completed
                $completedDate = $isCompleted ? $scheduledDate->copy()->addDays(rand(1, 3)) : null;
                $status = $isCompleted ? 'completed' : (['scheduled', 'in_progress'][array_rand(['scheduled', 'in_progress'])]);

                $laborCost = rand(80, 400);
                $partsCost = rand(50, 600);
                $totalCost = $laborCost + $partsCost;

                Maintenance::create([
                    'organization_id' => $vehicle->organization_id,
                    'vehicle_id' => $vehicle->id,
                    'workshop_id' => $workshop->id,
                    'driver_id' => $vehicle->current_driver_id,
                    'type' => $template['type'],
                    'category' => $template['category'],
                    'reference_number' => 'MAINT-' . date('Ymd', strtotime($scheduledDate)) . '-' . rand(1000, 9999),
                    'scheduled_date' => $scheduledDate,
                    'completed_date' => $completedDate,
                    'start_time' => $isCompleted ? '08:00' : null,
                    'end_time' => $isCompleted ? '16:00' : null,
                    'mileage_at_service' => $vehicle->current_mileage - rand(1000, 10000),
                    'next_service_mileage' => $isCompleted ? $vehicle->current_mileage + rand(10000, 20000) : null,
                    'next_service_date' => $isCompleted ? now()->addMonths(rand(6, 12)) : null,
                    'labor_cost' => $isCompleted ? $laborCost : 0,
                    'parts_cost' => $isCompleted ? $partsCost : 0,
                    'total_cost' => $isCompleted ? $totalCost : 0,
                    'description' => $template['description'],
                    'work_done' => $isCompleted ? 'Travaux effectués selon planning. Aucun problème détecté.' : null,
                    'parts_replaced' => $isCompleted ? ($template['category'] === 'oil_change' ? 'Filtre à huile, huile moteur' : 'Pièces diverses') : null,
                    'recommendations' => $isCompleted && rand(0, 1) ? 'Prochaine révision dans 6 mois' : null,
                    'invoice_number' => $isCompleted ? 'INV-' . rand(100000, 999999) : null,
                    'status' => $status,
                    'downtime_hours' => $isCompleted ? rand(4, 24) : 0,
                ]);
            }
        }
    }
}
