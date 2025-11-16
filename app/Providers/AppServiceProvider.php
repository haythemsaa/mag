<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Cost;
use App\Models\Driver;
use App\Models\FuelTransaction;
use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Observers\ContractObserver;
use App\Observers\CostObserver;
use App\Observers\DriverObserver;
use App\Observers\FuelTransactionObserver;
use App\Observers\MaintenanceObserver;
use App\Observers\VehicleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers for audit logging
        Vehicle::observe(VehicleObserver::class);
        Driver::observe(DriverObserver::class);
        Maintenance::observe(MaintenanceObserver::class);
        FuelTransaction::observe(FuelTransactionObserver::class);
        Cost::observe(CostObserver::class);
        Contract::observe(ContractObserver::class);
    }
}
