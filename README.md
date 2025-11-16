# FleetManager Pro

![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-316192?style=for-the-badge&logo=postgresql)
![Vue.js](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

**FleetManager Pro** est une solution SaaS complète de gestion de parc automobile développée en Laravel, destinée aux entreprises de toutes tailles souhaitant optimiser la gestion de leur flotte de véhicules.

## 🚀 Vision du Projet

Une plateforme moderne qui combine les meilleures fonctionnalités des leaders du marché (DigiParc, Geotab, Fleetio) avec une interface intuitive et une architecture scalable.

### Objectifs Principaux

- ✅ **Centralisation** : Regrouper toutes les données de la flotte dans une plateforme unique
- 📊 **Optimisation** : Réduire les coûts d'exploitation de 30% grâce à une gestion intelligente
- 🤖 **Automatisation** : Éliminer 80% des tâches administratives répétitives
- 📱 **Multi-device** : Accès web et mobile natif (iOS/Android)
- 🌍 **Multi-tenant** : Support de multiples organisations avec hiérarchie

## 📋 Fonctionnalités Principales

### ✅ Implémenté (v0.1 - Foundation)

**Core Features**
- ✓ Architecture multi-tenant complète avec isolation des données
- ✓ Gestion des véhicules (CRUD complet)
- ✓ Gestion des conducteurs
- ✓ Gestion des contrats (leasing, assurance)
- ✓ Suivi de la maintenance (préventive/curative)
- ✓ Transactions carburant avec détection d'anomalies
- ✓ Positions GPS et télématique
- ✓ Gestion des coûts et TCO
- ✓ Gestion des ateliers/workshops

**Security & Permissions**
- ✓ Authentification multi-tenant avec Laravel Sanctum
- ✓ Système de rôles et permissions granulaires (Spatie Permission)
- ✓ 7 rôles prédéfinis : super-admin, organization-admin, fleet-manager, accountant, maintenance-manager, driver, viewer
- ✓ 70+ permissions granulaires par module
- ✓ Policies Laravel pour autorisation fine-grained
- ✓ Middleware d'isolation des organisations

**API & Resources**
- ✓ API RESTful complète (70+ endpoints)
- ✓ API Resources pour formatage cohérent des réponses
- ✓ Validation robuste des requêtes
- ✓ Support filtrage, tri, pagination

**Dashboard & Analytics**
- ✓ Dashboard KPIs temps réel
- ✓ Statistiques flotte (véhicules par statut, type de carburant, âge moyen)
- ✓ Analyse des coûts avec tendances
- ✓ Suivi maintenance (en retard, à venir)
- ✓ Monitoring conducteurs (permis, scores éco-conduite)
- ✓ Analyse carburant avec détection d'anomalies
- ✓ Système d'alertes temps réel

**Notifications & Automation**
- ✓ Système de notifications multi-canal (Email + Database)
- ✓ Notifications maintenance (7 jours d'avance)
- ✓ Alertes contrats expirants (30 jours)
- ✓ Alertes permis conducteurs (60 jours)
- ✓ Jobs en arrière-plan avec queues Redis
- ✓ Scheduler Laravel pour envoi quotidien automatique
- ✓ Ciblage par rôle pour chaque type d'alerte

**Testing**
- ✓ Tests unitaires (Policies, Modèles)
- ✓ Tests features (API endpoints, Dashboard)
- ✓ Tests d'autorisation multi-tenant
- ✓ Coverage isolation des organisations

### 🚧 En Développement

- 🔄 Interface web (Inertia.js + Vue.js 3)
- 🔄 Génération automatique documentation API (Scribe)

### 📅 Roadmap

**Phase 2 - Operations (Q1 2025)**
- Géolocalisation temps réel sur carte
- Application mobile conducteur
- Gestion avancée carburant avec cartes
- Module de planification de missions

**Phase 3 - Intelligence (Q2 2025)**
- Machine Learning pour maintenance prédictive
- Analytics avancés et BI
- Optimisation de tournées (VRP)
- API GraphQL

**Phase 4 - Extensions (Q3 2025)**
- Intégrations tierces (ANTAI, constructeurs)
- Modules spécialisés (transport, logistique)
- Marketplace d'extensions
- Support multi-langues

## 🛠️ Stack Technologique

### Backend
- **Framework** : Laravel 12.x
- **PHP** : 8.3+
- **Base de données** : PostgreSQL 16
- **Cache/Queue** : Redis
- **Authentification** : Laravel Sanctum
- **Permissions** : Spatie Laravel Permission

### Frontend
- **Framework** : Vue.js 3
- **Adapter** : Inertia.js
- **UI** : Tailwind CSS 3.x
- **Routing** : Ziggy (Laravel routes en JS)

### DevOps
- **Conteneurisation** : Docker
- **CI/CD** : GitHub Actions (à venir)
- **Monitoring** : Laravel Telescope (dev)

## 📦 Installation

### Prérequis

- PHP 8.3 ou supérieur
- Composer 2.x
- Node.js 18+ et npm
- PostgreSQL 16+
- Redis 7+

### Étapes d'installation

1. **Cloner le repository**
```bash
git clone <repository-url>
cd fleetmanager-pro
```

2. **Installer les dépendances PHP**
```bash
composer install
```

3. **Configurer l'environnement**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configurer la base de données**

Éditez le fichier `.env` :
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fleetmanager
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

5. **Configurer Redis**
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

6. **Exécuter les migrations**
```bash
php artisan migrate
```

7. **Installer et compiler les assets frontend**
```bash
npm install
npm run dev
```

8. **Lancer le serveur de développement**
```bash
php artisan serve
```

L'application sera accessible sur `http://localhost:8000`

### 🚀 Quick Start avec Données de Démo

Pour démarrer rapidement avec des données de test complètes :

```bash
# 1. Seeder complet (roles, permissions, organizations, vehicles, etc.)
php artisan db:seed

# 2. Démarrer le queue worker pour les notifications
php artisan queue:work

# 3. Envoyer les alertes quotidiennes manuellement (optionnel)
php artisan alerts:send-daily
```

**Compte administrateur par défaut :**
- Email : `admin@fleetmanager.fr`
- Password : `password`
- Rôle : `super-admin`

**Créer un utilisateur personnalisé :**
```bash
# Mode interactif
php artisan user:create

# Mode CLI
php artisan user:create \
  --name="John Doe" \
  --email="john@example.com" \
  --password="secret123" \
  --organization=1 \
  --role="fleet-manager"
```

**Tester le scheduler (cron jobs) :**
```bash
# Exécuter toutes les tâches planifiées
php artisan schedule:work

# Le scheduler enverra automatiquement les alertes quotidiennes à 8h00 (Europe/Paris)
```

**Accéder au Dashboard :**
```bash
# Obtenir un token API via POST /api/login
# Puis accéder aux endpoints :
GET /api/dashboard                    # KPIs complets
GET /api/dashboard/live-fleet         # Suivi flotte temps réel
GET /api/vehicles?include_tco=1       # Véhicules avec TCO
```

**Documentation détaillée :**

Consultez `FLEET_MANAGER_SETUP.md` pour :
- Configuration complète des rôles et permissions
- Liste des 70+ endpoints API avec exemples
- Configuration des notifications et jobs
- Troubleshooting et FAQ

## 🗃️ Structure de la Base de Données

### Tables Principales

| Table | Description | Statut |
|-------|-------------|--------|
| `organizations` | Organisations multi-tenant | ✅ |
| `sites` | Sites/Agences par organisation | ✅ |
| `vehicles` | Parc de véhicules | ✅ |
| `drivers` | Conducteurs | ✅ |
| `contracts` | Contrats (leasing, assurance) | ✅ |
| `maintenances` | Interventions maintenance | ✅ |
| `fuel_transactions` | Transactions carburant | ✅ |
| `gps_positions` | Positions GPS temps réel | ✅ |
| `costs` | Tous types de coûts | ✅ |
| `workshops` | Ateliers/Garages | ✅ |

### Modèles Eloquent

Tous les modèles incluent :
- Relations Eloquent complètes
- Soft Deletes
- Casts appropriés
- Méthodes métier (ex: `calculateTCO()`, `needsMaintenance()`)

Exemple - Modèle `Vehicle` :
```php
// Relations
$vehicle->organization
$vehicle->site
$vehicle->currentDriver
$vehicle->contracts
$vehicle->maintenances
$vehicle->fuelTransactions
$vehicle->gpsPositions
$vehicle->costs

// Méthodes métier
$vehicle->isAvailable()
$vehicle->needsMaintenance()
$vehicle->calculateTCO()
$vehicle->calculateAverageConsumption()
```

## 🔐 Sécurité

**Implémenté**
- ✓ Protection CSRF native Laravel
- ✓ Validation stricte des données entrantes (Form Requests)
- ✓ Sanitization XSS automatique
- ✓ Authentification API avec Laravel Sanctum (tokens)
- ✓ Système de permissions granulaires (70+ permissions)
- ✓ Policies Laravel pour autorisation au niveau objet
- ✓ Isolation multi-tenant stricte (middleware + policies)
- ✓ Super-admin bypass avec traçabilité
- ✓ Protection des coûts validés (immuabilité métier)
- ✓ Validation des accès organisation par organisation

**À Venir**
- 🔄 Politique de mots de passe forte configurable
- 🔄 2FA optionnelle (TOTP)
- 🔄 Logs d'audit complets avec Laravel Activity Log
- 🔄 Rate limiting API par tenant
- 🔄 Détection d'intrusion et alertes sécurité

## 📊 Architecture Multi-Tenant

```
Groupe Enterprise
├── Société A
│   ├── Site Paris
│   ├── Site Lyon
│   └── Site Marseille
├── Société B
│   └── Site Lille
└── Société C
    ├── Site Bordeaux
    └── Site Nantes
```

Chaque organisation peut avoir :
- Hiérarchie illimitée (parent/enfants)
- Sites multiples
- Plans d'abonnement différents (Starter/Professional/Enterprise)
- Limites de véhicules configurables

## 🧪 Tests

Le projet inclut une suite de tests complète pour garantir la qualité et la sécurité.

### Tests Unitaires

- **VehiclePolicyTest** : Tests d'autorisation multi-tenant
  - Vérification super-admin bypass
  - Tests isolation organisation
  - Tests permissions granulaires
  - Tests actions spécifiques (assignDriver, updateMileage, etc.)

### Tests Features

- **DashboardApiTest** : Tests API Dashboard
  - Structure des réponses KPIs
  - Calculs statistiques (flotte, coûts, maintenance, etc.)
  - Filtrage par date
  - Isolation multi-tenant
  - Tests live-fleet avec GPS

### Exécution

```bash
# Exécuter tous les tests
php artisan test

# Tests avec output détaillé
php artisan test --parallel

# Tests spécifiques
php artisan test --filter=VehiclePolicyTest
php artisan test --filter=DashboardApiTest

# Avec coverage (nécessite Xdebug/PCOV)
php artisan test --coverage
php artisan test --coverage --min=80
```

### Tests Actuels

- ✓ 10 tests unitaires (VehiclePolicy)
- ✓ 12 tests features (Dashboard API)
- ✓ Coverage : Policies, Controllers, Resources
- 🔄 À venir : Tests modèles, jobs, notifications

## 📖 Documentation

### Documentation API
La documentation API complète sera générée avec Scribe (à venir)

### Spécifications Fonctionnelles
Consultez le document `SPECIFICATIONS.md` pour le cahier des charges complet

## 🤝 Contribution

Les contributions sont les bienvenues ! Veuillez consulter `CONTRIBUTING.md` pour les guidelines.

## 📄 License

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 👥 Auteurs

- Développement initial : Équipe FleetManager Pro
- Basé sur les spécifications du cahier des charges DigiParc

## 🙏 Remerciements

- Inspiration : DigiParc, Geotab, Fleetio
- Framework : Laravel & Vue.js communities
- Icons : Heroicons

## 📞 Support

Pour toute question ou problème :
- Ouvrir une issue sur GitHub
- Email : support@fleetmanager.pro (à venir)
- Documentation : docs.fleetmanager.pro (à venir)

---

**FleetManager Pro** - Optimisez votre flotte, maximisez vos profits 🚗💨
