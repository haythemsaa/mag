<?php

namespace App\Helpers;

use App\Constants\FleetConstants;
use Carbon\Carbon;

class FleetHelper
{
    /**
     * Calculate days until a given date
     */
    public static function daysUntil(?Carbon $date): ?int
    {
        if (!$date) {
            return null;
        }

        return now()->diffInDays($date, false);
    }

    /**
     * Check if a date is expiring soon (within given days)
     */
    public static function isExpiringSoon(?Carbon $date, int $daysThreshold = 30): bool
    {
        if (!$date) {
            return false;
        }

        $daysUntil = self::daysUntil($date);

        return $daysUntil !== null && $daysUntil >= 0 && $daysUntil <= $daysThreshold;
    }

    /**
     * Check if a date is expired
     */
    public static function isExpired(?Carbon $date): bool
    {
        if (!$date) {
            return false;
        }

        return $date->isPast();
    }

    /**
     * Get urgency level based on days until expiry
     */
    public static function getUrgencyLevel(int $daysUntil): string
    {
        if ($daysUntil < 0) {
            return FleetConstants::URGENCY_CRITICAL; // Expired
        } elseif ($daysUntil <= 7) {
            return FleetConstants::URGENCY_HIGH;
        } elseif ($daysUntil <= 30) {
            return FleetConstants::URGENCY_MEDIUM;
        }

        return FleetConstants::URGENCY_LOW;
    }

    /**
     * Format currency (EUR by default)
     */
    public static function formatCurrency(float $amount, string $currency = 'EUR'): string
    {
        return number_format($amount, 2, ',', ' ') . ' ' . $currency;
    }

    /**
     * Calculate percentage change between two values
     */
    public static function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100.0 : 0.0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    /**
     * Calculate fuel consumption (L/100km)
     */
    public static function calculateFuelConsumption(float $liters, int $distance): ?float
    {
        if ($distance <= 0) {
            return null;
        }

        return round(($liters / $distance) * 100, 2);
    }

    /**
     * Calculate cost per kilometer
     */
    public static function calculateCostPerKm(float $totalCost, int $distance): ?float
    {
        if ($distance <= 0) {
            return null;
        }

        return round($totalCost / $distance, 3);
    }

    /**
     * Detect fuel consumption anomaly
     */
    public static function isFuelAnomalyConsumption(float $currentConsumption, float $averageConsumption): bool
    {
        if ($averageConsumption <= 0) {
            return false;
        }

        $variance = abs($currentConsumption - $averageConsumption) / $averageConsumption;

        return $variance > FleetConstants::FUEL_ANOMALY_CONSUMPTION_VARIANCE;
    }

    /**
     * Detect fuel price anomaly
     */
    public static function isFuelAnomalyPrice(float $currentPrice, float $averagePrice): bool
    {
        if ($averagePrice <= 0) {
            return false;
        }

        $variance = abs($currentPrice - $averagePrice) / $averagePrice;

        return $variance > FleetConstants::FUEL_ANOMALY_PRICE_VARIANCE;
    }

    /**
     * Calculate vehicle age in years
     */
    public static function calculateVehicleAge(?int $year): ?float
    {
        if (!$year) {
            return null;
        }

        return round(now()->year - $year + (now()->month / 12), 1);
    }

    /**
     * Get eco-driving rating from score
     */
    public static function getEcoDrivingRating(float $score): string
    {
        if ($score >= FleetConstants::ECO_SCORE_GOOD) {
            return 'Excellent';
        } elseif ($score >= FleetConstants::ECO_SCORE_AVERAGE) {
            return 'Good';
        } elseif ($score >= FleetConstants::ECO_SCORE_POOR) {
            return 'Average';
        }

        return 'Poor';
    }

    /**
     * Format mileage (add thousands separator)
     */
    public static function formatMileage(int $mileage): string
    {
        return number_format($mileage, 0, ',', ' ') . ' km';
    }

    /**
     * Check if mileage needs attention
     */
    public static function needsMileageAttention(int $mileage): string
    {
        if ($mileage >= FleetConstants::MILEAGE_CRITICAL_THRESHOLD) {
            return 'critical';
        } elseif ($mileage >= FleetConstants::MILEAGE_WARNING_THRESHOLD) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Calculate depreciation
     */
    public static function calculateDepreciation(
        float $purchasePrice,
        ?Carbon $purchaseDate,
        float $depreciationRate = 0.20
    ): ?float {
        if (!$purchaseDate) {
            return null;
        }

        $yearsOwned = now()->diffInYears($purchaseDate);

        if ($yearsOwned === 0) {
            return 0;
        }

        return round($purchasePrice * pow(1 - $depreciationRate, $yearsOwned), 2);
    }

    /**
     * Generate registration number placeholder (for testing)
     */
    public static function generateRegistrationNumber(): string
    {
        $letters = chr(rand(65, 90)) . chr(rand(65, 90));
        $numbers = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $suffix = chr(rand(65, 90)) . chr(rand(65, 90));

        return "{$letters}-{$numbers}-{$suffix}";
    }

    /**
     * Validate VIN (Vehicle Identification Number)
     */
    public static function isValidVIN(string $vin): bool
    {
        // VIN must be exactly 17 characters
        if (strlen($vin) !== 17) {
            return false;
        }

        // VIN cannot contain I, O, or Q
        if (preg_match('/[IOQ]/', $vin)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate next maintenance date based on interval
     */
    public static function calculateNextMaintenanceDate(
        ?Carbon $lastMaintenanceDate,
        int $intervalDays = 180
    ): ?Carbon {
        if (!$lastMaintenanceDate) {
            return now()->addDays($intervalDays);
        }

        return $lastMaintenanceDate->copy()->addDays($intervalDays);
    }

    /**
     * Calculate next maintenance mileage based on interval
     */
    public static function calculateNextMaintenanceMileage(
        int $currentMileage,
        int $intervalKm = 15000
    ): int {
        return $currentMileage + $intervalKm;
    }

    /**
     * Format duration in human-readable format
     */
    public static function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' minutes';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }

        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    /**
     * Get season from date
     */
    public static function getSeason(?Carbon $date = null): string
    {
        $date = $date ?? now();
        $month = $date->month;

        if (in_array($month, [12, 1, 2])) {
            return 'winter';
        } elseif (in_array($month, [3, 4, 5])) {
            return 'spring';
        } elseif (in_array($month, [6, 7, 8])) {
            return 'summer';
        }

        return 'autumn';
    }
}
