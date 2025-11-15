<?php

namespace Database\Seeders;

use App\Models\FuelTransaction;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class FuelTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = Vehicle::all();

        $stations = [
            'TotalEnergies',
            'BP',
            'Esso',
            'Shell',
            'Leclerc Carburant',
            'Intermarché',
            'Auchan Drive',
        ];

        $fuelPrices = [
            'diesel' => 1.75,
            'petrol' => 1.85,
            'electric' => 0.25, // per kWh
            'hybrid' => 1.80,
        ];

        foreach ($vehicles as $vehicle) {
            // Create 5-15 fuel transactions per vehicle
            $transactionCount = rand(5, 15);

            for ($i = 0; $i < $transactionCount; $i++) {
                $transactionDate = now()->subDays(rand(1, 180));
                $station = $stations[array_rand($stations)];

                $basePrice = $fuelPrices[$vehicle->fuel_type] ?? 1.75;

                // Create some anomalies (15% chance)
                $hasAnomaly = rand(1, 100) <= 15;
                $unitPrice = $hasAnomaly ? $basePrice * (rand(0, 1) ? 1.25 : 0.75) : $basePrice + rand(-10, 10) / 100;

                $quantity = $vehicle->fuel_type === 'electric' ? rand(20, 50) : rand(30, 70);
                $totalCost = $quantity * $unitPrice;

                $validated = rand(1, 100) <= 80; // 80% validated

                $mileage = $vehicle->initial_mileage + rand(1000, $vehicle->current_mileage - $vehicle->initial_mileage);

                FuelTransaction::create([
                    'organization_id' => $vehicle->organization_id,
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $vehicle->current_driver_id,
                    'transaction_date' => $transactionDate,
                    'station_name' => $station . ' ' . ['Paris', 'Lyon', 'Marseille'][array_rand(['Paris', 'Lyon', 'Marseille'])],
                    'station_address' => rand(1, 200) . ' Avenue de la Station',
                    'latitude' => 48.8566 + (rand(-300, 300) / 1000),
                    'longitude' => 2.3522 + (rand(-300, 300) / 1000),
                    'fuel_type' => $vehicle->fuel_type,
                    'quantity' => $quantity,
                    'unit_price' => round($unitPrice, 3),
                    'total_cost' => round($totalCost, 2),
                    'currency' => 'EUR',
                    'mileage' => $mileage,
                    'odometer_reading' => $mileage,
                    'payment_method' => ['card', 'fleet_card', 'cash'][array_rand(['card', 'fleet_card', 'cash'])],
                    'card_number' => '**** **** **** ' . rand(1000, 9999),
                    'invoice_number' => 'FUEL-' . date('Ymd', strtotime($transactionDate)) . '-' . rand(1000, 9999),
                    'validated' => $validated,
                    'anomaly_detected' => $hasAnomaly,
                    'anomaly_reason' => $hasAnomaly ? 'Prix déviant de la moyenne de 30 jours: ' . (abs($unitPrice - $basePrice) / $basePrice * 100) . '%' : null,
                    'notes' => $i % 10 == 0 ? 'Plein effectué en route' : null,
                ]);
            }
        }
    }
}
