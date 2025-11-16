# Changelog

All notable changes to FleetManager Pro will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Frontend dashboard with Inertia.js + Vue.js 3
- Interactive GPS map with real-time tracking
- Mobile driver application
- Advanced reporting and exports (PDF, Excel)
- Bulk data import functionality
- AI-powered predictive maintenance
- Multi-language support

---

## [0.1.0] - 2024-01-16

### Added - Foundation Phase Complete 🎉

#### Core Infrastructure
- Laravel 12.x backend API with PostgreSQL 16
- Multi-tenant architecture with strict data isolation
- Redis integration for cache, queues, and sessions
- Laravel Sanctum API authentication
- Spatie Laravel Permission for role-based access control
- 7 predefined roles with 70+ granular permissions

#### API Endpoints (80+)
- **Authentication** (7 endpoints): login, register, logout, logout-all, me, profile
- **Dashboard** (2 endpoints): comprehensive KPIs, live fleet tracking
- **Organizations** (5 endpoints): CRUD + statistics + dashboard
- **Vehicles** (8 endpoints): CRUD + statistics + driver assignment + mileage update
- **Drivers** (4 endpoints): CRUD operations
- **Maintenances** (6 endpoints): CRUD + upcoming + overdue + complete
- **Fuel Transactions** (6 endpoints): CRUD + statistics + anomaly detection + validate
- **Costs** (5 endpoints): CRUD + statistics + validate
- **Contracts** (5 endpoints): CRUD + expiring contracts
- **GPS Positions** (8 endpoints): CRUD + live tracking + geofence alerts + vehicle tracking
- **Sites** (4 endpoints): CRUD operations
- **Workshops** (5 endpoints): CRUD + rating update

#### API Resources (6)
- VehicleResource: conditional fields (TCO, maintenance status), lazy-loaded relationships
- DriverResource: calculated fields (full_name, license_expiring)
- MaintenanceResource: overdue detection, days until due
- FuelTransactionResource: consumption and price variance calculations
- CostResource: VAT calculations, validation status
- ContractResource: expiry detection, duration calculations

#### Form Requests (8)
- LoginRequest: authentication validation
- StoreVehicleRequest: auto-injection, uniqueness validation, authorization
- UpdateVehicleRequest: optional field validation, policy authorization
- StoreMaintenanceRequest: auto-calculation of total_cost
- StoreDriverRequest: license validation, default values
- StoreCostRequest: auto-calculation of VAT (20% default)
- StoreFuelTransactionRequest: auto-calculation, tolerance validation
- StoreContractRequest: auto-status based on dates, unique contract numbers

#### Business Logic
- FleetConstants: 100+ organized constants (statuses, types, categories, thresholds)
- FleetHelper: 20+ utility functions (date calculations, formatters, validators)
- VehiclePolicy: fine-grained authorization with organization isolation
- CostPolicy: validation rules, immutability for validated costs

#### Notifications & Jobs
- **Jobs** (3): SendMaintenanceReminders, SendContractExpiryAlerts, SendLicenseExpiryAlerts
- **Notifications** (3): MaintenanceDue, ContractExpiring, LicenseExpiring
- Multi-channel delivery (Email + Database)
- Queue-based asynchronous processing
- Laravel Scheduler integration (daily alerts at 8:00 AM Europe/Paris)
- Role-based notification targeting

#### Dashboard & Analytics
- Real-time KPIs:
  - Fleet statistics (total, active, by fuel type, average age, total mileage)
  - Cost analysis with trend comparison vs previous period
  - Maintenance tracking (total, pending, overdue, upcoming, average cost)
  - Driver monitoring (total, licenses expiring, eco-scores, infractions)
  - Fuel analytics (volume, cost, price/L, anomalies by fuel type)
  - Real-time alerts (maintenance overdue, licenses expiring, contracts expiring, pending validations)
- Live fleet tracking with latest GPS positions

#### Testing (56+ tests)
- **Unit Tests** (37):
  - VehiclePolicyTest (10): authorization, organization isolation
  - SendMaintenanceRemindersJobTest (7): 7-day window, role targeting
  - SendContractExpiryAlertsJobTest (6): 30-day window, intervals
  - MaintenanceDueNotificationTest (7): channels, structure, queueing
  - SendLicenseExpiryAlertsJobTest (9): 60-day window, urgency levels
- **Feature Tests** (27):
  - DashboardApiTest (12): KPI calculations, date filtering, organization isolation
  - AuthApiTest (15): login, registration, logout, profile management
- Complete coverage of critical paths

#### Documentation
- Interactive API documentation with Scribe
  - 80+ endpoints documented with examples
  - Code examples in 4 languages (Bash, JavaScript, PHP, Python)
  - "Try It Out" functionality
  - Postman collection export
  - OpenAPI/Swagger specification
- Complete README.md with quick start guide
- FLEET_MANAGER_SETUP.md comprehensive user guide
- Session and final summaries
- Inline code documentation (PHPDoc)

#### Database
- 12 tables with complete Eloquent models:
  - organizations, sites, workshops
  - vehicles, drivers, contracts
  - maintenances, fuel_transactions, costs, gps_positions
  - users, roles, permissions
- Soft deletes on all entities
- Comprehensive relationships
- 10 seeders for demo data
- Database indexes for performance

#### Commands
- `php artisan alerts:send-daily`: Send daily notifications manually
- `php artisan user:create`: Interactive user creation with role assignment

#### Configuration
- Comprehensive .env.example with 80+ variables
- FleetManager Pro specific settings
- Feature flags
- Third-party integration placeholders
- Development tools configuration

### Security
- CSRF protection
- XSS sanitization
- Strict input validation with FormRequests
- API rate limiting (60 req/min public, 120 req/min authenticated)
- Multi-tenant data isolation
- Password hashing with bcrypt
- API token-based authentication (Sanctum)
- Role-based access control (7 roles, 70+ permissions)
- Policy-based authorization at object level

### Performance
- Redis caching
- Database query optimization
- Queue workers for background processing
- Eager loading to prevent N+1 queries
- API Resource transformation for consistent responses

---

## Version History

### [0.1.0] - 2024-01-16
- Initial release - Foundation Phase Complete
- Production-ready backend API
- 80+ endpoints, 56+ tests, complete documentation

---

## Upgrade Guide

### From scratch to 0.1.0

1. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

2. **Environment setup:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database setup:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. **Queue worker (required for notifications):**
   ```bash
   php artisan queue:work
   ```

5. **Access the application:**
   - API: http://localhost:8000
   - Documentation: http://localhost:8000/docs
   - Admin: admin@fleetmanager.fr / password

---

## Migration Notes

### 0.1.0
- First release - no migrations needed
- PostgreSQL 16+ recommended (MySQL 8.0+ compatible)
- Redis 7+ required for queue, cache, sessions
- PHP 8.3+ required

---

## Contributors

- Claude AI Assistant - Initial development
- Based on DigiParc specifications

---

## Links

- [Documentation](http://localhost:8000/docs)
- [GitHub Repository](#)
- [Issue Tracker](#)

---

## Notes

### Deprecations
- None in this version

### Breaking Changes
- None in this version

### Known Issues
- None reported

### Future Breaking Changes
- None planned

---

**Legend:**
- `Added` for new features
- `Changed` for changes in existing functionality
- `Deprecated` for soon-to-be removed features
- `Removed` for now removed features
- `Fixed` for any bug fixes
- `Security` for vulnerability fixes
