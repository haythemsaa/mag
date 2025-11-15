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

- ✓ Architecture multi-tenant complète
- ✓ Gestion des véhicules (CRUD complet)
- ✓ Gestion des conducteurs
- ✓ Gestion des contrats (leasing, assurance)
- ✓ Suivi de la maintenance (préventive/curative)
- ✓ Transactions carburant avec détection d'anomalies
- ✓ Positions GPS et télématique
- ✓ Gestion des coûts et TCO
- ✓ Gestion des ateliers/workshops

### 🚧 En Développement

- 🔄 Authentification multi-tenant avec rôles (Sanctum + Spatie Permission)
- 🔄 API RESTful complète
- 🔄 Interface web (Inertia.js + Vue.js 3)
- 🔄 Tableaux de bord analytics
- 🔄 Système d'alertes et notifications

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

- Protection CSRF native Laravel
- Validation des données entrantes
- Sanitization XSS
- Politique de mots de passe forte (à venir)
- 2FA optionnelle (à venir)
- Logs d'audit complets (à venir)

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

```bash
# Exécuter les tests
php artisan test

# Avec coverage
php artisan test --coverage
```

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
