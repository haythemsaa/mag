# FleetManager Pro - Guide de Configuration

## 📋 Vue d'ensemble

FleetManager Pro est un système SaaS complet de gestion de flotte automobile multi-tenant développé avec Laravel 12, conforme aux spécifications DigiParc.

### Fonctionnalités principales

- ✅ Gestion multi-tenant avec isolation des données par organisation
- ✅ 10 API controllers RESTful (70+ endpoints)
- ✅ Système de permissions granulaire (7 rôles, 70+ permissions)
- ✅ Notifications automatiques (maintenances, contrats, permis)
- ✅ Jobs asynchrones avec queue
- ✅ Suivi GPS en temps réel
- ✅ Gestion TCO (Total Cost of Ownership)
- ✅ Détection d'anomalies carburant
- ✅ Policies d'autorisation fine
- ✅ 10 seeders de données de test

## 🚀 Installation

### Prérequis

- PHP 8.3+
- Composer
- PostgreSQL 16+ (ou MySQL 8.0+)
- Redis 7+ (pour cache et queues)
- Node.js 20+ & NPM (pour frontend)

### Étapes d'installation

```bash
# 1. Cloner le repository
git clone <repository-url>
cd mag

# 2. Installer les dépendances
composer install
npm install

# 3. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 4. Configurer la base de données dans .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fleetmanager
DB_USERNAME=postgres
DB_PASSWORD=your_password

# 5. Configurer Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
QUEUE_CONNECTION=redis

# 6. Exécuter les migrations
php artisan migrate

# 7. Seeder la base de données
php artisan db:seed

# 8. Compiler les assets
npm run build

# 9. Démarrer le serveur
php artisan serve
```

## 👥 Système de Rôles et Permissions

### Rôles disponibles

#### 1. **super-admin**
- Accès total au système
- Peut gérer toutes les organisations
- Bypass de toutes les restrictions

#### 2. **organization-admin**
- Gestion complète de son organisation
- Tous les modules (véhicules, conducteurs, maintenances, coûts, contrats)
- Validation des coûts et transactions

#### 3. **fleet-manager**
- Gestion véhicules et conducteurs
- Maintenances et transactions carburant
- Suivi GPS et statistiques
- **Pas d'accès** : validation financière

#### 4. **accountant**
- Focus finances et comptabilité
- Gestion contrats et coûts
- Validation transactions et coûts
- Accès en lecture véhicules/conducteurs
- **Pas d'accès** : modification véhicules/conducteurs

#### 5. **maintenance-manager**
- Focus ateliers et maintenances
- Gestion complète maintenances
- Gestion ateliers
- Création coûts maintenance
- **Pas d'accès** : finances, validation

#### 6. **driver**
- Vue limitée véhicule assigné
- Création transactions carburant
- Consultation maintenances
- **Pas d'accès** : modification, autres véhicules

#### 7. **viewer**
- Lecture seule sur tous les modules
- **Pas d'accès** : création, modification, suppression

### Commande de création d'utilisateur

```bash
# Interactive
php artisan user:create

# Avec options
php artisan user:create \
  --name="John Doe" \
  --email="john@example.com" \
  --password="secret" \
  --organization=1 \
  --role="fleet-manager"
```

## 🔔 Notifications Automatiques

### Types de notifications

#### 1. Maintenances à venir (7 jours)
- Envoyée à : super-admin, organization-admin, fleet-manager, maintenance-manager
- Fréquence : Quotidienne (8h00)
- Canaux : Email + Database

#### 2. Contrats expirants (30 jours)
- Envoyée à : super-admin, organization-admin, accountant
- Intervalles : 30, 14, 7, 3, 1, 0 jours avant expiration
- Canaux : Email + Database
- Types : Location, Assurance, Maintenance, Location courte durée

#### 3. Permis de conduire expirants (60 jours)
- Envoyée à : super-admin, organization-admin, fleet-manager
- Intervalles : 60, 30, 14, 7, 3, 1, 0 jours avant expiration
- Canaux : Email + Database
- **Urgence haute** si ≤ 7 jours

### Commande manuelle

```bash
# Envoyer toutes les alertes
php artisan alerts:send-daily

# Alertes spécifiques
php artisan alerts:send-daily --type=maintenance
php artisan alerts:send-daily --type=contract
php artisan alerts:send-daily --type=license

# Pour une organisation spécifique
php artisan alerts:send-daily --organization=1
```

### Configuration du scheduler

Ajouter à votre crontab :

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Les alertes seront envoyées automatiquement à 8h00 chaque jour (timezone Europe/Paris).

## 🔐 Sécurité Multi-tenant

### Middleware OrganizationScope

Automatiquement appliqué sur les routes protégées :

```php
Route::middleware(['auth:sanctum', 'organization.scope'])->group(function () {
    Route::apiResource('vehicles', VehicleController::class);
});
```

**Fonctionnement** :
- Auto-injection de `organization_id` dans les requêtes GET/POST
- Blocage de l'accès aux données d'autres organisations (403)
- Bypass pour super-admins
- Validation que l'utilisateur appartient à une organisation

### Policies d'autorisation

```php
// Exemple : Vérifier si l'utilisateur peut mettre à jour un véhicule
$user->can('update', $vehicle);

// Vérifier une permission spécifique
$user->can('vehicles.assign-driver');

// Dans les controllers
$this->authorize('update', $vehicle);
```

## 📊 API Endpoints

### 📖 Documentation API Interactive

FleetManager Pro utilise **Scribe** pour générer automatiquement une documentation API interactive complète.

**Accès :**
- Documentation : `http://localhost:8000/docs`
- Collection Postman : `http://localhost:8000/docs.postman`
- Spécification OpenAPI : `http://localhost:8000/docs.openapi`

**Fonctionnalités :**
- Exemples en Bash, JavaScript, PHP, Python
- Bouton "Try It Out" pour tester directement
- Documentation de tous les endpoints avec paramètres et réponses
- Téléchargement de la collection Postman

**Régénérer la documentation :**
```bash
php artisan scribe:generate
```

### Authentification

```http
POST /api/login              # Connexion et obtention token
POST /api/register           # Créer un nouveau compte
POST /api/logout             # Déconnexion (révoque token actuel)
POST /api/logout-all         # Déconnexion tous appareils
GET  /api/me                 # Profil utilisateur avec rôles/permissions
PUT  /api/profile            # Mettre à jour profil
GET  /api/user               # (deprecated - utiliser /me)
```

**Exemple Login :**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@fleetmanager.fr",
    "password": "password",
    "device_name": "iPhone 13"
  }'

# Response:
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@fleetmanager.fr",
    "organization_id": 1,
    "roles": ["super-admin"],
    "permissions": ["organizations.view", "vehicles.create", ...]
  }
}
```

**Authentification des requêtes :**
```bash
curl -X GET http://localhost:8000/api/vehicles \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Dashboard

```http
GET /api/dashboard                    # KPIs complets (flotte, coûts, maintenance, drivers, fuel, alerts)
GET /api/dashboard/live-fleet         # Suivi flotte temps réel avec GPS
```

### Organizations

```http
GET    /api/organizations
POST   /api/organizations
GET    /api/organizations/{id}
PUT    /api/organizations/{id}
DELETE /api/organizations/{id}
GET    /api/organizations/{id}/statistics
GET    /api/organizations/{id}/dashboard
```

### Vehicles

```http
GET    /api/vehicles
POST   /api/vehicles
GET    /api/vehicles/{id}
PUT    /api/vehicles/{id}
DELETE /api/vehicles/{id}
GET    /api/vehicles/{id}/statistics
POST   /api/vehicles/{id}/assign-driver
DELETE /api/vehicles/{id}/unassign-driver
PATCH  /api/vehicles/{id}/mileage
```

### GPS Tracking

```http
GET  /api/gps-positions
POST /api/gps-positions
GET  /api/gps-positions/{id}
PUT  /api/gps-positions/{id}
DELETE /api/gps-positions/{id}
GET  /api/gps-positions/live-tracking?organization_id=1
GET  /api/gps-positions/geofence-alerts
GET  /api/gps-positions/vehicle/{vehicleId}/latest
GET  /api/gps-positions/vehicle/{vehicleId}/track?start_date=2024-01-01&end_date=2024-01-31
```

### Costs & Statistics

```http
GET  /api/costs
POST /api/costs
GET  /api/costs/{id}
PUT  /api/costs/{id}
DELETE /api/costs/{id}
POST /api/costs/{id}/validate
GET  /api/costs/statistics?organization_id=1&start_date=2024-01-01&end_date=2024-12-31
```

## 🧪 Données de Test

### Après seeding (`php artisan db:seed`)

- **5 organisations** avec structure hiérarchique
- **15-40 véhicules** par organisation (diesel, électrique, hybride)
- **10-25 conducteurs** par organisation
- **2-3 sites** par organisation
- **2-4 ateliers** par organisation
- **2-5 maintenances** par véhicule (80% complétées)
- **5-15 transactions carburant** par véhicule
- **1-3 contrats** par véhicule (location, assurance, maintenance)
- **3-8 coûts** par véhicule (toutes catégories)
- **10-30 positions GPS** par véhicule (7 derniers jours)

### Utilisateur par défaut

```
Email: admin@fleetmanager.fr
Password: password
Rôle: super-admin
Organisation: Transport Express SARL (première organisation créée)
```

## 🛠️ Queue Workers

Pour traiter les jobs asynchrones :

```bash
# Démarrer le queue worker
php artisan queue:work

# Avec supervisord (production)
sudo supervisorctl start laravel-worker:*
```

Configuration supervisord (`/etc/supervisor/conf.d/laravel-worker.conf`) :

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path-to-your-project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path-to-your-project/storage/logs/worker.log
stopwaitsecs=3600
```

## ✅ Validation avec FormRequests

FleetManager Pro utilise des **FormRequests** pour une validation robuste et réutilisable.

### FormRequests disponibles

#### LoginRequest
Validation de la connexion avec gestion des messages personnalisés.
```php
use App\Http\Requests\LoginRequest;

public function login(LoginRequest $request) {
    // Données déjà validées
    $validated = $request->validated();
}
```

#### StoreVehicleRequest
Validation création véhicule avec :
- Auto-injection de `organization_id` depuis l'utilisateur authentifié
- Vérification unicité du numéro d'immatriculation et VIN
- Validation des dates (assurance non expirée, etc.)
- Statut par défaut "active" si non fourni
- **Autorisation** : vérifie que l'utilisateur a la permission `vehicles.create`

```php
use App\Http\Requests\StoreVehicleRequest;

public function store(StoreVehicleRequest $request) {
    $vehicle = Vehicle::create($request->validated());
    return response()->json($vehicle, 201);
}
```

#### UpdateVehicleRequest
Validation mise à jour véhicule avec :
- Règles "sometimes" (optionnelles)
- Exclusion ID actuel pour unicité
- **Autorisation** : utilise VehiclePolicy->update()

#### StoreMaintenanceRequest
Validation création maintenance avec :
- Auto-injection de `organization_id`
- Calcul automatique de `total_cost` (labor_cost + parts_cost)
- Statut par défaut "pending"
- **Autorisation** : vérifie permission `maintenances.create`

### Créer votre propre FormRequest

```bash
php artisan make:request StoreDriverRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('drivers.create');
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'license_number' => 'required|string|unique:drivers',
            // ...
        ];
    }

    public function messages(): array
    {
        return [
            'license_number.unique' => 'Ce numéro de permis est déjà utilisé',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Auto-injection organization_id
        if (!$this->has('organization_id') && $this->user()) {
            $this->merge([
                'organization_id' => $this->user()->organization_id,
            ]);
        }
    }
}
```

## 📝 Commandes Artisan Utiles

```bash
# Envoyer les alertes quotidiennes
php artisan alerts:send-daily

# Créer un utilisateur
php artisan user:create

# Vider le cache des permissions
php artisan permission:cache-reset

# Lister les routes API
php artisan route:list --path=api

# Nettoyer les queues failed
php artisan queue:flush

# Relancer les failed jobs
php artisan queue:retry all
```

## 🔧 Configuration Queue

Dans `.env` :

```env
QUEUE_CONNECTION=redis

# Ou database
QUEUE_CONNECTION=database

# Ou sync (développement uniquement)
QUEUE_CONNECTION=sync
```

## 📈 Monitoring et Logs

```bash
# Voir les logs en temps réel
tail -f storage/logs/laravel.log

# Logs des jobs
tail -f storage/logs/worker.log

# Nettoyer les vieux logs
php artisan log:clear
```

## 🌐 Variables d'environnement importantes

```env
APP_NAME="FleetManager Pro"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Europe/Paris
APP_LOCALE=fr

# Queue
QUEUE_CONNECTION=redis

# Mail (pour notifications)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@fleetmanager.fr
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,fleetmanager.local
SESSION_DOMAIN=.fleetmanager.local
```

## 🚨 Troubleshooting

### Erreur : "Role not found"
```bash
php artisan db:seed --class=RolePermissionSeeder
```

### Erreur : "Queue connection redis not found"
```bash
# Installer predis
composer require predis/predis

# Ou utiliser database queue
QUEUE_CONNECTION=database
php artisan queue:table
php artisan migrate
```

### Erreur : "Notifications not sent"
```bash
# Créer la table notifications
php artisan notifications:table
php artisan migrate

# Vérifier le queue worker
php artisan queue:work
```

## 📚 Documentation API

Pour générer la documentation OpenAPI/Swagger :

```bash
# Installer scribe
composer require --dev knuckleswtf/scribe

# Générer la doc
php artisan scribe:generate
```

## 🎯 Prochaines étapes

- [ ] Implémenter le frontend (Inertia.js + Vue 3)
- [ ] Tests automatisés (Feature + Unit)
- [ ] Export PDF/Excel des rapports
- [ ] Tableau de bord analytics
- [ ] API publique pour intégrations tierces
- [ ] Application mobile (Flutter)

## 📧 Support

Pour toute question ou problème, contactez l'équipe de développement.
