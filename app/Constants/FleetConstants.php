<?php

namespace App\Constants;

class FleetConstants
{
    // Vehicle statuses
    public const VEHICLE_STATUS_ACTIVE = 'active';
    public const VEHICLE_STATUS_INACTIVE = 'inactive';
    public const VEHICLE_STATUS_MAINTENANCE = 'maintenance';
    public const VEHICLE_STATUS_SOLD = 'sold';
    public const VEHICLE_STATUS_SCRAPPED = 'scrapped';

    public const VEHICLE_STATUSES = [
        self::VEHICLE_STATUS_ACTIVE,
        self::VEHICLE_STATUS_INACTIVE,
        self::VEHICLE_STATUS_MAINTENANCE,
        self::VEHICLE_STATUS_SOLD,
        self::VEHICLE_STATUS_SCRAPPED,
    ];

    // Fuel types
    public const FUEL_TYPE_GASOLINE = 'gasoline';
    public const FUEL_TYPE_DIESEL = 'diesel';
    public const FUEL_TYPE_ELECTRIC = 'electric';
    public const FUEL_TYPE_HYBRID = 'hybrid';
    public const FUEL_TYPE_LPG = 'lpg';
    public const FUEL_TYPE_CNG = 'cng';

    public const FUEL_TYPES = [
        self::FUEL_TYPE_GASOLINE,
        self::FUEL_TYPE_DIESEL,
        self::FUEL_TYPE_ELECTRIC,
        self::FUEL_TYPE_HYBRID,
        self::FUEL_TYPE_LPG,
        self::FUEL_TYPE_CNG,
    ];

    // Vehicle categories
    public const VEHICLE_CATEGORY_CAR = 'car';
    public const VEHICLE_CATEGORY_VAN = 'van';
    public const VEHICLE_CATEGORY_TRUCK = 'truck';
    public const VEHICLE_CATEGORY_BUS = 'bus';
    public const VEHICLE_CATEGORY_MOTORCYCLE = 'motorcycle';
    public const VEHICLE_CATEGORY_UTILITY = 'utility';

    public const VEHICLE_CATEGORIES = [
        self::VEHICLE_CATEGORY_CAR,
        self::VEHICLE_CATEGORY_VAN,
        self::VEHICLE_CATEGORY_TRUCK,
        self::VEHICLE_CATEGORY_BUS,
        self::VEHICLE_CATEGORY_MOTORCYCLE,
        self::VEHICLE_CATEGORY_UTILITY,
    ];

    // Ownership types
    public const OWNERSHIP_TYPE_OWNED = 'owned';
    public const OWNERSHIP_TYPE_LEASED = 'leased';
    public const OWNERSHIP_TYPE_RENTED = 'rented';

    public const OWNERSHIP_TYPES = [
        self::OWNERSHIP_TYPE_OWNED,
        self::OWNERSHIP_TYPE_LEASED,
        self::OWNERSHIP_TYPE_RENTED,
    ];

    // Maintenance types
    public const MAINTENANCE_TYPE_PREVENTIVE = 'preventive';
    public const MAINTENANCE_TYPE_CORRECTIVE = 'corrective';
    public const MAINTENANCE_TYPE_INSPECTION = 'inspection';
    public const MAINTENANCE_TYPE_TIRE_CHANGE = 'tire_change';
    public const MAINTENANCE_TYPE_OIL_CHANGE = 'oil_change';
    public const MAINTENANCE_TYPE_OTHER = 'other';

    public const MAINTENANCE_TYPES = [
        self::MAINTENANCE_TYPE_PREVENTIVE,
        self::MAINTENANCE_TYPE_CORRECTIVE,
        self::MAINTENANCE_TYPE_INSPECTION,
        self::MAINTENANCE_TYPE_TIRE_CHANGE,
        self::MAINTENANCE_TYPE_OIL_CHANGE,
        self::MAINTENANCE_TYPE_OTHER,
    ];

    // Maintenance statuses
    public const MAINTENANCE_STATUS_PENDING = 'pending';
    public const MAINTENANCE_STATUS_IN_PROGRESS = 'in_progress';
    public const MAINTENANCE_STATUS_COMPLETED = 'completed';
    public const MAINTENANCE_STATUS_CANCELLED = 'cancelled';

    public const MAINTENANCE_STATUSES = [
        self::MAINTENANCE_STATUS_PENDING,
        self::MAINTENANCE_STATUS_IN_PROGRESS,
        self::MAINTENANCE_STATUS_COMPLETED,
        self::MAINTENANCE_STATUS_CANCELLED,
    ];

    // Cost categories
    public const COST_CATEGORY_FUEL = 'fuel';
    public const COST_CATEGORY_MAINTENANCE = 'maintenance';
    public const COST_CATEGORY_INSURANCE = 'insurance';
    public const COST_CATEGORY_TAX = 'tax';
    public const COST_CATEGORY_PARKING = 'parking';
    public const COST_CATEGORY_TOLL = 'toll';
    public const COST_CATEGORY_FINE = 'fine';
    public const COST_CATEGORY_LEASE = 'lease';
    public const COST_CATEGORY_DEPRECIATION = 'depreciation';
    public const COST_CATEGORY_OTHER = 'other';

    public const COST_CATEGORIES = [
        self::COST_CATEGORY_FUEL,
        self::COST_CATEGORY_MAINTENANCE,
        self::COST_CATEGORY_INSURANCE,
        self::COST_CATEGORY_TAX,
        self::COST_CATEGORY_PARKING,
        self::COST_CATEGORY_TOLL,
        self::COST_CATEGORY_FINE,
        self::COST_CATEGORY_LEASE,
        self::COST_CATEGORY_DEPRECIATION,
        self::COST_CATEGORY_OTHER,
    ];

    // Payment methods
    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_CREDIT_CARD = 'credit_card';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_CHECK = 'check';
    public const PAYMENT_METHOD_OTHER = 'other';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_CREDIT_CARD,
        self::PAYMENT_METHOD_BANK_TRANSFER,
        self::PAYMENT_METHOD_CHECK,
        self::PAYMENT_METHOD_OTHER,
    ];

    // Contract types
    public const CONTRACT_TYPE_LEASE = 'lease';
    public const CONTRACT_TYPE_INSURANCE = 'insurance';
    public const CONTRACT_TYPE_MAINTENANCE = 'maintenance';
    public const CONTRACT_TYPE_FULL_SERVICE = 'full_service';
    public const CONTRACT_TYPE_OTHER = 'other';

    public const CONTRACT_TYPES = [
        self::CONTRACT_TYPE_LEASE,
        self::CONTRACT_TYPE_INSURANCE,
        self::CONTRACT_TYPE_MAINTENANCE,
        self::CONTRACT_TYPE_FULL_SERVICE,
        self::CONTRACT_TYPE_OTHER,
    ];

    // Contract statuses
    public const CONTRACT_STATUS_ACTIVE = 'active';
    public const CONTRACT_STATUS_EXPIRED = 'expired';
    public const CONTRACT_STATUS_CANCELLED = 'cancelled';
    public const CONTRACT_STATUS_PENDING = 'pending';

    public const CONTRACT_STATUSES = [
        self::CONTRACT_STATUS_ACTIVE,
        self::CONTRACT_STATUS_EXPIRED,
        self::CONTRACT_STATUS_CANCELLED,
        self::CONTRACT_STATUS_PENDING,
    ];

    // Driver statuses
    public const DRIVER_STATUS_ACTIVE = 'active';
    public const DRIVER_STATUS_INACTIVE = 'inactive';
    public const DRIVER_STATUS_ON_LEAVE = 'on_leave';
    public const DRIVER_STATUS_TERMINATED = 'terminated';

    public const DRIVER_STATUSES = [
        self::DRIVER_STATUS_ACTIVE,
        self::DRIVER_STATUS_INACTIVE,
        self::DRIVER_STATUS_ON_LEAVE,
        self::DRIVER_STATUS_TERMINATED,
    ];

    // Employment types
    public const EMPLOYMENT_TYPE_FULL_TIME = 'full_time';
    public const EMPLOYMENT_TYPE_PART_TIME = 'part_time';
    public const EMPLOYMENT_TYPE_CONTRACTOR = 'contractor';
    public const EMPLOYMENT_TYPE_TEMPORARY = 'temporary';

    public const EMPLOYMENT_TYPES = [
        self::EMPLOYMENT_TYPE_FULL_TIME,
        self::EMPLOYMENT_TYPE_PART_TIME,
        self::EMPLOYMENT_TYPE_CONTRACTOR,
        self::EMPLOYMENT_TYPE_TEMPORARY,
    ];

    // Subscription plans
    public const SUBSCRIPTION_PLAN_STARTER = 'starter';
    public const SUBSCRIPTION_PLAN_PROFESSIONAL = 'professional';
    public const SUBSCRIPTION_PLAN_ENTERPRISE = 'enterprise';

    public const SUBSCRIPTION_PLANS = [
        self::SUBSCRIPTION_PLAN_STARTER,
        self::SUBSCRIPTION_PLAN_PROFESSIONAL,
        self::SUBSCRIPTION_PLAN_ENTERPRISE,
    ];

    // Notification intervals (in days)
    public const NOTIFICATION_INTERVAL_MAINTENANCE = 7;
    public const NOTIFICATION_INTERVAL_CONTRACT = 30;
    public const NOTIFICATION_INTERVAL_LICENSE = 60;

    // Alert urgency levels
    public const URGENCY_LOW = 'low';
    public const URGENCY_MEDIUM = 'medium';
    public const URGENCY_HIGH = 'high';
    public const URGENCY_CRITICAL = 'critical';

    // Eco-driving score ranges
    public const ECO_SCORE_MIN = 0;
    public const ECO_SCORE_MAX = 10;
    public const ECO_SCORE_POOR = 4;
    public const ECO_SCORE_AVERAGE = 6;
    public const ECO_SCORE_GOOD = 8;

    // Default pagination
    public const DEFAULT_PAGINATION_PER_PAGE = 15;
    public const MAX_PAGINATION_PER_PAGE = 100;

    // Fuel anomaly thresholds
    public const FUEL_ANOMALY_CONSUMPTION_VARIANCE = 0.30; // 30% variance
    public const FUEL_ANOMALY_PRICE_VARIANCE = 0.25; // 25% variance
    public const FUEL_ANOMALY_MIN_QUANTITY = 5; // Minimum 5 liters

    // Mileage thresholds
    public const MILEAGE_WARNING_THRESHOLD = 250000; // 250,000 km
    public const MILEAGE_CRITICAL_THRESHOLD = 300000; // 300,000 km

    // VAT rate (default for France)
    public const DEFAULT_VAT_RATE = 0.20; // 20%

    /**
     * Get human-readable label for a constant value
     */
    public static function getLabel(string $type, string $value): string
    {
        $labels = [
            'vehicle_status' => [
                self::VEHICLE_STATUS_ACTIVE => 'Active',
                self::VEHICLE_STATUS_INACTIVE => 'Inactive',
                self::VEHICLE_STATUS_MAINTENANCE => 'In Maintenance',
                self::VEHICLE_STATUS_SOLD => 'Sold',
                self::VEHICLE_STATUS_SCRAPPED => 'Scrapped',
            ],
            'fuel_type' => [
                self::FUEL_TYPE_GASOLINE => 'Gasoline',
                self::FUEL_TYPE_DIESEL => 'Diesel',
                self::FUEL_TYPE_ELECTRIC => 'Electric',
                self::FUEL_TYPE_HYBRID => 'Hybrid',
                self::FUEL_TYPE_LPG => 'LPG',
                self::FUEL_TYPE_CNG => 'CNG',
            ],
            'contract_type' => [
                self::CONTRACT_TYPE_LEASE => 'Lease',
                self::CONTRACT_TYPE_INSURANCE => 'Insurance',
                self::CONTRACT_TYPE_MAINTENANCE => 'Maintenance',
                self::CONTRACT_TYPE_FULL_SERVICE => 'Full Service',
                self::CONTRACT_TYPE_OTHER => 'Other',
            ],
        ];

        return $labels[$type][$value] ?? ucfirst(str_replace('_', ' ', $value));
    }
}
