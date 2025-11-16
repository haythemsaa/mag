# Guide d'Implémentation - Module Infractions

Module prioritaire identifié dans l'analyse concurrentielle.

**Effort estimé:** 15-20 jours
**Priorité:** 🔴 HAUTE
**Valeur ajoutée:** Demande forte marché, fonctionnalité critique manquante

---

## 1. User Stories

### US1: En tant que Gestionnaire de Flotte
- Je veux enregistrer une infraction reçue
- Afin de suivre les amendes et points de permis

### US2: En tant que Gestionnaire de Flotte
- Je veux affecter une infraction au conducteur responsable
- Afin de tenir un historique par conducteur

### US3: En tant que Gestionnaire de Flotte
- Je veux être alerté quand un conducteur risque de perdre son permis
- Afin de prendre des mesures préventives

### US4: En tant que Gestionnaire de Flotte
- Je veux voir les statistiques d'infractions par véhicule/conducteur
- Afin d'identifier les problèmes récurrents

### US5: En tant que Conducteur
- Je veux consulter mes infractions
- Afin de connaître ma situation

---

## 2. Schéma Base de Données

### Migration: create_infractions_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();

            // Identification
            $table->string('infraction_number')->unique();
            $table->string('reference_number')->nullable(); // Numéro officiel (ANTAI, etc.)

            // Détails infraction
            $table->enum('type', [
                'speeding',          // Excès de vitesse
                'red_light',         // Feu rouge
                'parking',           // Stationnement interdit
                'phone',             // Téléphone au volant
                'seatbelt',          // Ceinture
                'alcohol',           // Alcoolémie
                'dangerous_driving', // Conduite dangereuse
                'stop_sign',         // Stop
                'wrong_way',         // Sens interdit
                'other'              // Autre
            ]);

            // Date et lieu
            $table->date('infraction_date');
            $table->time('infraction_time')->nullable();
            $table->string('location');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Montants et paiement
            $table->decimal('amount', 10, 2); // Montant amende
            $table->decimal('reduced_amount', 10, 2)->nullable(); // Montant minoré (paiement rapide)
            $table->decimal('increased_amount', 10, 2)->nullable(); // Montant majoré (retard)
            $table->integer('points_deducted')->default(0); // Points retirés

            // Workflow
            $table->enum('status', [
                'received',   // Reçue
                'pending',    // En attente affectation
                'assigned',   // Affectée au conducteur
                'contested',  // Contestée
                'paid',       // Payée
                'cancelled'   // Annulée
            ])->default('received');

            // Dates importantes
            $table->date('due_date')->nullable();           // Date limite paiement
            $table->date('reduced_due_date')->nullable();   // Date limite tarif minoré
            $table->date('paid_date')->nullable();          // Date paiement
            $table->date('contested_date')->nullable();     // Date contestation

            // Informations complémentaires
            $table->decimal('recorded_speed', 5, 2)->nullable(); // Vitesse constatée (km/h)
            $table->decimal('speed_limit', 5, 2)->nullable();    // Vitesse autorisée (km/h)
            $table->string('payment_method')->nullable();         // Mode paiement
            $table->string('payment_reference')->nullable();      // Référence paiement
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index pour performance
            $table->index('infraction_number');
            $table->index('reference_number');
            $table->index('type');
            $table->index('status');
            $table->index('infraction_date');
            $table->index('due_date');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'type']);
            $table->index(['vehicle_id', 'infraction_date']);
            $table->index(['driver_id', 'infraction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infractions');
    }
};
```

### Table pour pièces jointes

```php
Schema::create('infraction_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('infraction_id')->constrained()->cascadeOnDelete();
    $table->string('file_name');
    $table->string('file_path');
    $table->string('file_type'); // pdf, jpg, png
    $table->integer('file_size'); // bytes
    $table->string('type')->default('document'); // document, photo, notice
    $table->text('description')->nullable();
    $table->timestamps();
});
```

---

## 3. Model Eloquent

### app/Models/Infraction.php

```php
<?php

namespace App\Models;

use App\Constants\FleetConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Infraction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'driver_id',
        'infraction_number',
        'reference_number',
        'type',
        'infraction_date',
        'infraction_time',
        'location',
        'latitude',
        'longitude',
        'amount',
        'reduced_amount',
        'increased_amount',
        'points_deducted',
        'status',
        'due_date',
        'reduced_due_date',
        'paid_date',
        'contested_date',
        'recorded_speed',
        'speed_limit',
        'payment_method',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'infraction_date' => 'date',
        'infraction_time' => 'datetime:H:i',
        'due_date' => 'date',
        'reduced_due_date' => 'date',
        'paid_date' => 'date',
        'contested_date' => 'date',
        'amount' => 'decimal:2',
        'reduced_amount' => 'decimal:2',
        'increased_amount' => 'decimal:2',
        'points_deducted' => 'integer',
        'recorded_speed' => 'decimal:2',
        'speed_limit' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($infraction) {
            if (!$infraction->infraction_number) {
                $infraction->infraction_number = self::generateInfractionNumber();
            }

            if (!$infraction->status) {
                $infraction->status = 'received';
            }
        });
    }

    /**
     * Generate unique infraction number
     */
    public static function generateInfractionNumber(): string
    {
        $year = now()->year;
        $lastInfraction = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastInfraction ? ((int) substr($lastInfraction->infraction_number, -6)) + 1 : 1;

        return 'INF-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InfractionAttachment::class);
    }

    /**
     * Scopes
     */
    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['received', 'pending', 'assigned']);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['received', 'pending', 'assigned'])
            ->whereNull('paid_date');
    }

    public function scopeOverdue($query)
    {
        return $query->unpaid()
            ->where('due_date', '<', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeByVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Accessors & Mutators
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'paid'
            && $this->status !== 'cancelled'
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date || $this->status === 'paid') {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    public function getAmountToPayAttribute(): float
    {
        // Si en retard, montant majoré
        if ($this->is_overdue && $this->increased_amount) {
            return (float) $this->increased_amount;
        }

        // Si dans les délais, montant minoré possible
        if ($this->reduced_due_date
            && now()->lte($this->reduced_due_date)
            && $this->reduced_amount
        ) {
            return (float) $this->reduced_amount;
        }

        // Sinon montant normal
        return (float) $this->amount;
    }

    public function getSpeedExcessAttribute(): ?float
    {
        if (!$this->recorded_speed || !$this->speed_limit) {
            return null;
        }

        return $this->recorded_speed - $this->speed_limit;
    }

    /**
     * Methods
     */
    public function markAsPaid(string $paymentMethod, ?string $paymentReference = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_date' => now(),
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
        ]);
    }

    public function contest(?string $notes = null): void
    {
        $this->update([
            'status' => 'contested',
            'contested_date' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function assignToDriver(int $driverId): void
    {
        $this->update([
            'driver_id' => $driverId,
            'status' => 'assigned',
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $reason ?? $this->notes,
        ]);
    }
}
```

---

## 4. FormRequests

### app/Http/Requests/StoreInfractionRequest.php

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInfractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('infractions.create');
    }

    protected function prepareForValidation(): void
    {
        // Auto-inject organization_id
        if (!$this->has('organization_id') && $this->user()) {
            $this->merge([
                'organization_id' => $this->user()->organization_id,
            ]);
        }

        // Set default status
        if (!$this->has('status')) {
            $this->merge(['status' => 'received']);
        }
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|exists:organizations,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'reference_number' => 'nullable|string|max:255',
            'type' => 'required|in:speeding,red_light,parking,phone,seatbelt,alcohol,dangerous_driving,stop_sign,wrong_way,other',
            'infraction_date' => 'required|date|before_or_equal:today',
            'infraction_time' => 'nullable|date_format:H:i',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'amount' => 'required|numeric|min:0',
            'reduced_amount' => 'nullable|numeric|min:0|lt:amount',
            'increased_amount' => 'nullable|numeric|min:0|gt:amount',
            'points_deducted' => 'nullable|integer|min:0|max:12',
            'due_date' => 'nullable|date|after:infraction_date',
            'reduced_due_date' => 'nullable|date|after:infraction_date|before:due_date',
            'recorded_speed' => 'nullable|numeric|min:0',
            'speed_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Le véhicule est obligatoire',
            'vehicle_id.exists' => 'Le véhicule sélectionné n\'existe pas',
            'type.required' => 'Le type d\'infraction est obligatoire',
            'infraction_date.required' => 'La date d\'infraction est obligatoire',
            'infraction_date.before_or_equal' => 'La date d\'infraction ne peut pas être future',
            'amount.required' => 'Le montant est obligatoire',
            'amount.min' => 'Le montant doit être positif',
        ];
    }
}
```

### app/Http/Requests/UpdateInfractionRequest.php

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInfractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $infraction = $this->route('infraction');
        return $this->user()->can('infractions.update')
            && $this->user()->organization_id === $infraction->organization_id;
    }

    public function rules(): array
    {
        return [
            'driver_id' => 'nullable|exists:drivers,id',
            'reference_number' => 'nullable|string|max:255',
            'type' => 'sometimes|in:speeding,red_light,parking,phone,seatbelt,alcohol,dangerous_driving,stop_sign,wrong_way,other',
            'infraction_date' => 'sometimes|date|before_or_equal:today',
            'infraction_time' => 'nullable|date_format:H:i',
            'location' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:received,pending,assigned,contested,paid,cancelled',
            'paid_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
            'payment_reference' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
```

---

## 5. API Controller

### app/Http/Controllers/Api/InfractionController.php

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInfractionRequest;
use App\Http\Requests\UpdateInfractionRequest;
use App\Http\Resources\InfractionResource;
use App\Models\Infraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Infractions
 *
 * Gestion des infractions et amendes de la flotte
 */
class InfractionController extends Controller
{
    /**
     * List Infractions
     *
     * Récupère la liste des infractions de l'organisation.
     *
     * @queryParam organization_id integer required ID de l'organisation. Example: 1
     * @queryParam status string Filtrer par statut. Example: unpaid
     * @queryParam vehicle_id integer Filtrer par véhicule. Example: 5
     * @queryParam driver_id integer Filtrer par conducteur. Example: 3
     * @queryParam type string Filtrer par type. Example: speeding
     * @queryParam per_page integer Items par page. Example: 15
     *
     * @response 200 {
     *   "data": [{
     *     "id": 1,
     *     "infraction_number": "INF-2025-000001",
     *     "vehicle": {"id": 5, "registration_number": "AB-123-CD"},
     *     "driver": {"id": 3, "full_name": "Jean Dupont"},
     *     "type": "speeding",
     *     "infraction_date": "2025-01-15",
     *     "location": "A6 Lyon",
     *     "amount": 135.00,
     *     "amount_to_pay": 90.00,
     *     "points_deducted": 1,
     *     "status": "received",
     *     "is_overdue": false,
     *     "days_until_due": 12
     *   }],
     *   "meta": {"current_page": 1, "total": 25}
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Infraction::class);

        $query = Infraction::with(['vehicle', 'driver'])
            ->forOrganization($request->input('organization_id'));

        // Filters
        if ($request->has('status')) {
            if ($request->status === 'unpaid') {
                $query->unpaid();
            } elseif ($request->status === 'overdue') {
                $query->overdue();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('vehicle_id')) {
            $query->byVehicle($request->vehicle_id);
        }

        if ($request->has('driver_id')) {
            $query->byDriver($request->driver_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $infractions = $query->latest('infraction_date')
            ->paginate($request->input('per_page', 15));

        return InfractionResource::collection($infractions);
    }

    /**
     * Create Infraction
     *
     * @bodyParam vehicle_id integer required ID du véhicule. Example: 5
     * @bodyParam driver_id integer ID du conducteur. Example: 3
     * @bodyParam type string required Type d'infraction. Example: speeding
     * @bodyParam infraction_date date required Date de l'infraction. Example: 2025-01-15
     * @bodyParam location string required Lieu. Example: A6 Lyon
     * @bodyParam amount number required Montant. Example: 135.00
     * @bodyParam points_deducted integer Points retirés. Example: 1
     *
     * @response 201 {"data": {"id": 1, "infraction_number": "INF-2025-000001"}}
     */
    public function store(StoreInfractionRequest $request): JsonResponse
    {
        $infraction = Infraction::create($request->validated());

        return (new InfractionResource($infraction))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show Infraction
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     *
     * @response 200 {"data": {"id": 1, "infraction_number": "INF-2025-000001"}}
     */
    public function show(Infraction $infraction): InfractionResource
    {
        $this->authorize('view', $infraction);

        $infraction->load(['vehicle', 'driver', 'attachments']);

        return new InfractionResource($infraction);
    }

    /**
     * Update Infraction
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     *
     * @response 200 {"data": {"id": 1, "status": "paid"}}
     */
    public function update(UpdateInfractionRequest $request, Infraction $infraction): InfractionResource
    {
        $infraction->update($request->validated());

        return new InfractionResource($infraction->fresh());
    }

    /**
     * Delete Infraction
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     *
     * @response 204
     */
    public function destroy(Infraction $infraction): JsonResponse
    {
        $this->authorize('delete', $infraction);

        $infraction->delete();

        return response()->json(null, 204);
    }

    /**
     * Mark as Paid
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     * @bodyParam payment_method string required Mode de paiement. Example: bank_transfer
     * @bodyParam payment_reference string Référence. Example: PAY-12345
     *
     * @response 200 {"data": {"id": 1, "status": "paid"}}
     */
    public function markAsPaid(Request $request, Infraction $infraction): InfractionResource
    {
        $this->authorize('update', $infraction);

        $request->validate([
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
        ]);

        $infraction->markAsPaid(
            $request->payment_method,
            $request->payment_reference
        );

        return new InfractionResource($infraction->fresh());
    }

    /**
     * Contest Infraction
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     * @bodyParam notes string Notes de contestation. Example: Erreur de plaque
     *
     * @response 200 {"data": {"id": 1, "status": "contested"}}
     */
    public function contest(Request $request, Infraction $infraction): InfractionResource
    {
        $this->authorize('update', $infraction);

        $infraction->contest($request->input('notes'));

        return new InfractionResource($infraction->fresh());
    }

    /**
     * Infraction Statistics
     *
     * @queryParam organization_id integer required ID organisation. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "total_infractions": 25,
     *     "total_amount": 3250.50,
     *     "unpaid_count": 12,
     *     "unpaid_amount": 1500.00,
     *     "by_type": {"speeding": 15, "parking": 8, "red_light": 2},
     *     "by_status": {"paid": 13, "unpaid": 12},
     *     "top_vehicles": [{"vehicle_id": 5, "count": 8}],
     *     "top_drivers": [{"driver_id": 3, "count": 6, "points_lost": 4}]
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id');

        $infractions = Infraction::forOrganization($organizationId);

        $stats = [
            'total_infractions' => $infractions->count(),
            'total_amount' => $infractions->sum('amount'),
            'unpaid_count' => $infractions->unpaid()->count(),
            'unpaid_amount' => $infractions->unpaid()->sum('amount'),
            'overdue_count' => $infractions->overdue()->count(),
            'total_points_lost' => $infractions->sum('points_deducted'),

            'by_type' => $infractions->select('type')
                ->selectRaw('count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),

            'by_status' => $infractions->select('status')
                ->selectRaw('count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),

            'top_vehicles' => $infractions->select('vehicle_id')
                ->selectRaw('count(*) as count, sum(amount) as total_amount')
                ->groupBy('vehicle_id')
                ->orderByDesc('count')
                ->limit(5)
                ->with('vehicle:id,registration_number')
                ->get(),

            'top_drivers' => $infractions->select('driver_id')
                ->whereNotNull('driver_id')
                ->selectRaw('count(*) as count, sum(amount) as total_amount, sum(points_deducted) as points_lost')
                ->groupBy('driver_id')
                ->orderByDesc('count')
                ->limit(5)
                ->with('driver:id,first_name,last_name')
                ->get(),
        ];

        return response()->json(['data' => $stats]);
    }
}
```

---

## 6. Permissions

Ajouter dans `database/seeders/RolePermissionSeeder.php`:

```php
// Infractions permissions
Permission::create(['name' => 'infractions.view']);
Permission::create(['name' => 'infractions.create']);
Permission::create(['name' => 'infractions.update']);
Permission::create(['name' => 'infractions.delete']);
Permission::create(['name' => 'infractions.statistics']);

// Assign to roles
$superAdmin->givePermissionTo([
    'infractions.view', 'infractions.create',
    'infractions.update', 'infractions.delete',
    'infractions.statistics'
]);

$admin->givePermissionTo([
    'infractions.view', 'infractions.create',
    'infractions.update', 'infractions.statistics'
]);

$fleetManager->givePermissionTo([
    'infractions.view', 'infractions.create',
    'infractions.update', 'infractions.statistics'
]);

$driver->givePermissionTo(['infractions.view']); // Own only
```

---

## 7. Tests

### tests/Feature/InfractionApiTest.php

```php
<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Infraction;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfractionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Vehicle $vehicle;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->user->givePermissionTo([
            'infractions.view', 'infractions.create',
            'infractions.update', 'infractions.delete'
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->driver = Driver::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_user_can_create_infraction(): void
    {
        $data = [
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
            'type' => 'speeding',
            'infraction_date' => '2025-01-15',
            'infraction_time' => '14:30',
            'location' => 'A6 Lyon',
            'amount' => 135.00,
            'reduced_amount' => 90.00,
            'points_deducted' => 1,
            'due_date' => '2025-02-15',
            'recorded_speed' => 145,
            'speed_limit' => 130,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/infractions', $data);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'infraction_number', 'type', 'status']
        ]);

        $this->assertDatabaseHas('infractions', [
            'vehicle_id' => $this->vehicle->id,
            'type' => 'speeding',
            'amount' => 135.00,
        ]);
    }

    public function test_infraction_number_is_auto_generated(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertNotNull($infraction->infraction_number);
        $this->assertStringStartsWith('INF-', $infraction->infraction_number);
    }

    public function test_user_can_mark_infraction_as_paid(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'received',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/infractions/{$infraction->id}/pay", [
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'PAY-12345',
            ]);

        $response->assertStatus(200);

        $infraction->refresh();
        $this->assertEquals('paid', $infraction->status);
        $this->assertNotNull($infraction->paid_date);
    }

    public function test_user_can_contest_infraction(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/infractions/{$infraction->id}/contest", [
                'notes' => 'Erreur de plaque d\'immatriculation',
            ]);

        $response->assertStatus(200);

        $infraction->refresh();
        $this->assertEquals('contested', $infraction->status);
        $this->assertNotNull($infraction->contested_date);
    }

    public function test_can_get_infraction_statistics(): void
    {
        Infraction::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'type' => 'speeding',
            'status' => 'received',
        ]);

        Infraction::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'type' => 'parking',
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/infractions/statistics?organization_id={$this->organization->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_infractions',
                'total_amount',
                'unpaid_count',
                'by_type',
                'by_status',
            ]
        ]);

        $this->assertEquals(8, $response->json('data.total_infractions'));
    }

    public function test_overdue_infractions_are_detected(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'received',
            'due_date' => now()->subDays(5),
        ]);

        $this->assertTrue($infraction->is_overdue);
    }

    public function test_amount_to_pay_returns_reduced_amount_when_applicable(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'amount' => 135.00,
            'reduced_amount' => 90.00,
            'reduced_due_date' => now()->addDays(10),
        ]);

        $this->assertEquals(90.00, $infraction->amount_to_pay);
    }

    public function test_amount_to_pay_returns_increased_amount_when_overdue(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'amount' => 135.00,
            'increased_amount' => 375.00,
            'due_date' => now()->subDays(10),
            'status' => 'received',
        ]);

        $this->assertEquals(375.00, $infraction->amount_to_pay);
    }
}
```

---

## 8. Notifications

### app/Notifications/InfractionOverdue.php

```php
<?php

namespace App\Notifications;

use App\Models\Infraction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InfractionOverdue extends Notification
{
    use Queueable;

    public function __construct(public Infraction $infraction)
    {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Infraction en retard - ' . $this->infraction->infraction_number)
            ->line("L'infraction {$this->infraction->infraction_number} est en retard de paiement.")
            ->line("Véhicule: {$this->infraction->vehicle->registration_number}")
            ->line("Montant à payer: {$this->infraction->amount_to_pay}€")
            ->action('Voir l\'infraction', url("/infractions/{$this->infraction->id}"))
            ->line('Merci de régulariser la situation rapidement.');
    }

    public function toArray($notifiable): array
    {
        return [
            'infraction_id' => $this->infraction->id,
            'infraction_number' => $this->infraction->infraction_number,
            'vehicle_id' => $this->infraction->vehicle_id,
            'amount_to_pay' => $this->infraction->amount_to_pay,
            'days_overdue' => abs($this->infraction->days_until_due),
        ];
    }
}
```

---

## 9. Routes API

Ajouter dans `routes/api.php`:

```php
use App\Http\Controllers\Api\InfractionController;

Route::middleware('auth:sanctum')->group(function () {
    // Infractions
    Route::prefix('infractions')->group(function () {
        Route::get('/', [InfractionController::class, 'index']);
        Route::post('/', [InfractionController::class, 'store']);
        Route::get('/statistics', [InfractionController::class, 'statistics']);
        Route::get('/{infraction}', [InfractionController::class, 'show']);
        Route::put('/{infraction}', [InfractionController::class, 'update']);
        Route::delete('/{infraction}', [InfractionController::class, 'delete']);

        // Actions
        Route::post('/{infraction}/pay', [InfractionController::class, 'markAsPaid']);
        Route::post('/{infraction}/contest', [InfractionController::class, 'contest']);
    });
});
```

---

## 10. Commandes Laravel

### Vérifier infractions en retard

```php
php artisan make:command CheckOverdueInfractions
```

```php
<?php

namespace App\Console\Commands;

use App\Models\Infraction;
use App\Notifications\InfractionOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckOverdueInfractions extends Command
{
    protected $signature = 'infractions:check-overdue';
    protected $description = 'Check for overdue infractions and send notifications';

    public function handle(): int
    {
        $overdueInfractions = Infraction::overdue()->get();

        $this->info("Found {$overdueInfractions->count()} overdue infractions");

        foreach ($overdueInfractions as $infraction) {
            // Notify organization admins
            $admins = $infraction->organization->users()
                ->role(['super-admin', 'admin', 'fleet-manager'])
                ->get();

            Notification::send($admins, new InfractionOverdue($infraction));

            $this->line("Notified for: {$infraction->infraction_number}");
        }

        return Command::SUCCESS;
    }
}
```

Ajouter dans `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('infractions:check-overdue')
        ->dailyAt('09:00')
        ->timezone(config('app.timezone'));
}
```

---

## 11. Documentation Scribe

Générer la documentation:

```bash
php artisan scribe:generate
```

Vérifier: `http://localhost:8000/docs`

---

## 12. Checklist de Livraison

### Database
- [ ] Migration `create_infractions_table.php` créée
- [ ] Migration `create_infraction_attachments_table.php` créée
- [ ] Migrations exécutées sans erreur
- [ ] Indexes ajoutés pour performance

### Models
- [ ] Model `Infraction.php` créé
- [ ] Model `InfractionAttachment.php` créé
- [ ] Relationships configurées
- [ ] Scopes ajoutés
- [ ] Accessors/Mutators fonctionnels
- [ ] Factory créé pour tests

### API
- [ ] `InfractionController.php` créé
- [ ] FormRequests créés (`Store`, `Update`)
- [ ] `InfractionResource.php` créé
- [ ] Routes API ajoutées
- [ ] Permissions créées et assignées
- [ ] Policy créé

### Tests
- [ ] `InfractionApiTest.php` créé
- [ ] 10+ tests écrits
- [ ] Tous les tests passent
- [ ] Coverage > 80%

### Notifications
- [ ] `InfractionOverdue.php` créé
- [ ] Notification testée (mail + database)

### Commands
- [ ] `CheckOverdueInfractions` créé
- [ ] Scheduler configuré
- [ ] Command testé manuellement

### Documentation
- [ ] Annotations Scribe ajoutées
- [ ] Documentation générée
- [ ] Exemples de requêtes testés
- [ ] CHANGELOG.md mis à jour

---

## 13. Prochaines Étapes

Après livraison Module Infractions:

1. **Intégration ANTAI** (si France)
2. **Dashboard Infractions** (Vue.js frontend)
3. **Export Excel** infractions
4. **Module Accidents** (next priority)

---

**Durée estimée:** 15-20 jours
**Status:** 🚀 Prêt à démarrer
