# FleetManager Pro - Product Roadmap 2025-2026

Basé sur l'analyse concurrentielle et les tendances du marché africain et européen.

---

## Vision Produit

**Mission:** Devenir la solution de gestion de flotte la plus complète et accessible pour les PME/ETI en Afrique francophone et Europe.

**Positionnement:**
- Prix compétitif (30-50% moins cher que Cartrack/MiX Telematics)
- API-first pour intégrations faciles
- Focus marchés africains francophones
- Interface simple vs complexité Geotab/Verizon

---

## Q1 2025 (Jan-Mar) - Sécurité & Conformité

### ✅ COMPLÉTÉ
- [x] API REST 80+ endpoints
- [x] Dashboard KPIs temps réel
- [x] Multi-tenant architecture
- [x] RBAC avec 7 rôles, 70+ permissions
- [x] Notifications automatiques
- [x] Tests 56+
- [x] Documentation API (Scribe)
- [x] Docker & CI/CD

### 🚀 EN COURS (Jan 2025)

#### 1. Module Infractions [15-20j]
**Objectif:** Gestion complète des amendes et points de permis

- [ ] Migration database `infractions`
- [ ] Model + Controller + FormRequests
- [ ] API CRUD infractions
- [ ] Lien véhicule + conducteur + date/heure
- [ ] Statuts workflow (reçue → payée)
- [ ] Alertes permis en danger (< 3 points)
- [ ] Dashboard infractions
- [ ] Tests (10+)
- [ ] Documentation API

**Livrables:**
- Endpoints: POST/GET/PUT/DELETE /api/infractions
- Dashboard: /dashboard/infractions
- Notifications: permis en danger, amende impayée

#### 2. Module Accidents [15-20j]
**Objectif:** Workflow complet de gestion des sinistres

- [ ] Migration database `accidents` + `accident_photos`
- [ ] Model + Controller + FormRequests
- [ ] API CRUD accidents
- [ ] Upload photos accident (multiple)
- [ ] Workflow statuts (déclaré → expertisé → réparé → clos)
- [ ] Lien avec module Costs (réparations)
- [ ] Statistiques accidentologie
- [ ] Dashboard accidents
- [ ] Tests (10+)
- [ ] Documentation API

**Livrables:**
- Endpoints: POST/GET/PUT/DELETE /api/accidents
- Endpoints: POST /api/accidents/{id}/photos
- Dashboard: /dashboard/accidents
- Rapports: accidents par véhicule/conducteur

#### 3. Géofences & Zones [10-15j]
**Objectif:** Zones géographiques avec alertes

- [ ] Migration database `geofences`
- [ ] Model + Controller
- [ ] API CRUD géofences
- [ ] Types zones: autorisées, interdites, clients, dépôts
- [ ] Détection entrée/sortie zone (via GPS positions)
- [ ] Alertes temps réel
- [ ] Historique événements géofences
- [ ] Tests (8+)
- [ ] Documentation API

**Livrables:**
- Endpoints: POST/GET/PUT/DELETE /api/geofences
- Endpoints: GET /api/geofences/events
- Job: ProcessGeofenceAlerts
- Notifications: entrée/sortie zone

**KPIs Q1:**
- 3 nouveaux modules majeurs
- 30+ nouveaux endpoints
- 28+ nouveaux tests
- Documentation complète

---

## Q2 2025 (Apr-Jun) - Green Fleet & Mobilité

### 4. Transition Énergétique [15-20j]
**Objectif:** Support complet véhicules électriques

**4.1 Véhicules Électriques**
- [ ] Migration: champs VE dans `vehicles`
- [ ] Migration: table `charging_sessions`
- [ ] Migration: table `charging_stations`
- [ ] API gestion bornes recharge
- [ ] API sessions recharge
- [ ] Calcul coût électricité
- [ ] Suivi autonomie batterie
- [ ] Alertes batterie faible

**4.2 Émissions & CO2**
- [ ] Ajout CO2 dans `vehicles` et `fuel_transactions`
- [ ] Calcul émissions par trajet
- [ ] Calcul émissions totales flotte
- [ ] Comparaison VE vs thermique
- [ ] Dashboard Green Fleet
- [ ] KPIs développement durable

**4.3 Rapports Environnementaux**
- [ ] Export rapport CO2
- [ ] Statistiques transition énergétique
- [ ] Recommandations conversion VE

**Livrables:**
- Endpoints: POST/GET/PUT/DELETE /api/charging-stations
- Endpoints: POST/GET /api/charging-sessions
- Dashboard: /dashboard/green-fleet
- Rapports: émissions CO2, TCO VE vs thermique

### 5. Application Mobile - Phase 1: API [10-15j]
**Objectif:** API mobile complète pour application conducteur

- [ ] Routes API mobile (/api/mobile/*)
- [ ] Authentification mobile (Sanctum)
- [ ] Endpoints profil conducteur
- [ ] Endpoints véhicule assigné
- [ ] Endpoints déclaration incidents
- [ ] Endpoints upload photos
- [ ] Endpoints historique trajets
- [ ] Endpoints notifications
- [ ] Documentation API mobile
- [ ] Tests (15+)

**Livrables:**
- API mobile complète
- Documentation Postman
- Exemples d'intégration

### 6. Application Mobile - Phase 2: App [30-40j]
**Objectif:** Application mobile React Native

**6.1 Setup & Authentification**
- [ ] Init projet React Native
- [ ] Setup navigation
- [ ] Écrans authentification
- [ ] Intégration API auth

**6.2 Fonctionnalités Conducteur**
- [ ] Écran profil
- [ ] Écran véhicule assigné
- [ ] Déclaration incidents (formulaire)
- [ ] Capture photos
- [ ] Upload photos
- [ ] Historique trajets
- [ ] Notifications push (Firebase)

**6.3 Inspection Pré-Trajet**
- [ ] Checklist digitale
- [ ] Upload photos inspection
- [ ] Signature électronique

**6.4 Déploiement**
- [ ] Build Android
- [ ] Build iOS
- [ ] Publication Google Play
- [ ] Publication App Store

**Livrables:**
- App iOS + Android
- Publication stores
- Guide utilisateur conducteur

**KPIs Q2:**
- Support complet véhicules électriques
- Application mobile opérationnelle
- 20+ nouveaux endpoints
- 100+ téléchargements app (target)

---

## Q3 2025 (Jul-Sep) - Sécurité & Optimisation

### 7. Sécurité Anti-Vol [5-10j]
**Objectif:** Alertes et détection vol

- [ ] Migration: table `theft_alerts`
- [ ] Détection mouvement anormal (hors heures)
- [ ] Alertes sortie géofence parking
- [ ] Détection perte signal GPS (brouillage)
- [ ] Alertes vol en temps réel
- [ ] Dashboard sécurité
- [ ] Intégration notifications urgentes (SMS si disponible)

**Livrables:**
- Job: DetectTheftActivity
- Notifications: alerte vol, brouillage GPS
- Dashboard: /dashboard/security

### 8. Optimisation de Routes [20-30j]
**Objectif:** Calcul routes optimales et multi-arrêts

**8.1 Intégration Cartographie**
- [ ] Intégration Google Maps API / OSRM
- [ ] Calcul distance entre 2 points
- [ ] Calcul temps trajet
- [ ] Calcul ETA dynamique

**8.2 Optimisation Multi-Arrêts**
- [ ] Migration: tables `routes` et `route_stops`
- [ ] Algorithme optimisation (TSP simplifié)
- [ ] API planification routes
- [ ] Affectation véhicules
- [ ] Suivi progression route

**8.3 Analyse Performance Routes**
- [ ] Comparaison route planifiée vs réelle
- [ ] Écarts distance/temps
- [ ] Coût par route
- [ ] Rapports optimisation

**Livrables:**
- Endpoints: POST/GET /api/routes
- Endpoints: POST /api/routes/optimize
- Dashboard: /dashboard/routes
- Rapports: économies réalisées

### 9. Exports Comptables [10-15j]
**Objectif:** Exports pour comptabilité et ERP

- [ ] Migration: table `accounting_exports`
- [ ] Configuration mapping comptes comptables
- [ ] Export CSV personnalisable
- [ ] Export Excel avec formules
- [ ] Export XML (format standard)
- [ ] Exports automatisés (scheduler)
- [ ] Journaux comptables
- [ ] Tests exports

**Livrables:**
- Endpoints: GET /api/exports/accounting
- Configuration: mapping comptes
- Formats: CSV, Excel, XML
- Command: php artisan exports:accounting

**KPIs Q3:**
- Sécurité renforcée (alertes vol)
- Optimisation routes opérationnelle
- Exports comptables multiples formats

---

## Q4 2025 (Oct-Dec) - Analytics & Intégrations

### 10. Dashcams & Vidéo [15-20j]
**Objectif:** Intégration dashcams tiers

- [ ] Migration: table `dashcam_events`
- [ ] API webhooks pour fournisseurs dashcams
- [ ] Réception événements (freinage brusque, collision)
- [ ] Corrélation GPS + événement vidéo
- [ ] Stockage métadonnées vidéo (pas vidéo complète)
- [ ] Liens vers vidéos hébergées (fournisseur)
- [ ] Dashboard événements vidéo

**Partenaires potentiels:**
- Mobileye
- Lytx
- Surfsight
- SmartWitness

**Livrables:**
- Endpoints: POST /api/webhooks/dashcam (public)
- Dashboard: /dashboard/dashcam-events
- Documentation intégration partenaires

### 11. Analytics Avancée [20-30j]
**Objectif:** Scoring, prédictions, dashboards custom

**11.1 Scoring Conducteurs**
- [ ] Algorithme scoring (règles métier)
- [ ] Pondération: vitesse, freinage, accélérations, accidents, infractions
- [ ] Score 0-100 par conducteur
- [ ] Évolution score dans le temps
- [ ] Classement conducteurs

**11.2 Prédictions**
- [ ] Prédiction prochaine maintenance (historique)
- [ ] Alertes proactives pannes
- [ ] Prédiction coûts mensuels
- [ ] Recommandations actions

**11.3 Dashboards Personnalisables**
- [ ] Migration: table `custom_dashboards`
- [ ] Système widgets
- [ ] Drag & drop widgets
- [ ] Sauvegarde layouts utilisateur
- [ ] Partage dashboards

**Livrables:**
- Endpoints: GET /api/analytics/driver-scores
- Endpoints: GET /api/analytics/predictions
- Endpoints: POST/GET /api/custom-dashboards
- Dashboard builder UI

### 12. Intégration ANTAI (France) [15-20j]
**Objectif:** Import automatique infractions France

- [ ] Étude API ANTAI
- [ ] Authentification ANTAI
- [ ] Import infractions automatique
- [ ] Matching véhicule (plaque)
- [ ] Affectation conducteur automatique
- [ ] Synchronisation statuts
- [ ] Tests intégration

**Note:** Nécessite partenariat/agrément ANTAI

**Livrables:**
- Command: php artisan infractions:sync-antai
- Job: SyncAntaiInfractions (daily)
- Documentation configuration

**KPIs Q4:**
- Intégration vidéo opérationnelle
- Analytics avancée disponible
- Première intégration gouvernementale (ANTAI)

---

## 2026 - Expansion & Innovation

### Q1 2026 - Gestion Pneus & Tachygraphe

#### 13. Module Pneus
- [ ] CRUD pneus par position
- [ ] Historique changements
- [ ] Alertes usure
- [ ] Coûts par pneu
- [ ] Planning rotations

#### 14. Tachygraphe Digital (Poids Lourds)
- [ ] Intégration tachygraphes
- [ ] Import données conducteur
- [ ] Respect temps conduite
- [ ] Alertes réglementaires

### Q2 2026 - IA & Machine Learning

#### 15. Maintenance Prédictive IA
- [ ] Collecte données historiques
- [ ] Modèle ML prédiction pannes
- [ ] Recommandations maintenance
- [ ] Optimisation TCO

#### 16. Analyse Comportement IA
- [ ] Détection patterns conduite
- [ ] Clustering conducteurs
- [ ] Recommandations personnalisées

### Q3 2026 - Expansion Géographique

#### 17. Multi-Pays Afrique
- [ ] Support multi-devises
- [ ] Multi-langues (FR, EN, AR)
- [ ] Intégrations locales (Kenya, Nigeria, etc.)
- [ ] Partenariats récupération véhicules

#### 18. Compliance Européenne
- [ ] RGPD renforcé
- [ ] Zones LEZ (Low Emission Zones)
- [ ] Normes Euro 6/7
- [ ] Intégrations SIV (immatriculations)

### Q4 2026 - Plateforme Marketplace

#### 19. Marketplace Intégrations
- [ ] Store intégrations tierces
- [ ] SDK développeurs
- [ ] Webhooks généralisés
- [ ] Revenue sharing partenaires

#### 20. White Label Option
- [ ] Branding personnalisable
- [ ] Multi-instance
- [ ] Licence revendeurs

---

## Metrics & KPIs

### KPIs Produit

**2025:**
- Endpoints API: 80 → 150+
- Tests: 56 → 120+
- Modules: 12 → 25+
- Couverture fonctionnelle concurrents: 60% → 90%

**2026:**
- Endpoints API: 150 → 200+
- Intégrations tierces: 0 → 10+
- Pays supportés: 1 → 5+
- Utilisateurs actifs: TBD

### KPIs Business

**2025:**
- Clients pilotes: 5-10
- ARR: TBD
- Churn: < 10%
- NPS: > 50

**2026:**
- Clients: 50-100
- ARR: TBD
- Expansion internationale
- Partenariats stratégiques: 5+

---

## Ressources Estimées

### Équipe Recommandée 2025

**Backend (Laravel/PHP):**
- 2 développeurs senior
- 1 développeur junior

**Frontend (Vue.js/React):**
- 1 développeur senior
- 1 développeur junior

**Mobile (React Native):**
- 1 développeur senior (Q2-Q3)
- 1 développeur junior (Q2-Q3)

**DevOps:**
- 1 DevOps engineer (temps partiel)

**Product & QA:**
- 1 Product Owner
- 1 QA Engineer

**Total:** 8-9 personnes

### Budget Estimé 2025

**Développement:** ~150-250 j/h
**Infrastructure:** AWS/DO (~$500-1000/mois)
**Outils tiers:** Scribe, Google Maps API, etc. (~$200-500/mois)
**Marketing:** TBD

---

## Risques & Mitigation

### Risques Techniques

| Risque | Impact | Probabilité | Mitigation |
|--------|--------|-------------|------------|
| Complexité intégration ANTAI | Élevé | Moyenne | POC préalable, partenariat |
| Performance GPS/Geofences | Moyen | Moyenne | Indexation DB, cache Redis |
| Stockage vidéos coûteux | Élevé | Haute | Stockage chez partenaires |
| Scalabilité IA/ML | Moyen | Faible | Démarrer règles métier |

### Risques Business

| Risque | Impact | Probabilité | Mitigation |
|--------|--------|-------------|------------|
| Concurrence agressive | Élevé | Haute | Différenciation prix/marchés |
| Adoption lente mobile | Moyen | Moyenne | Incentives conducteurs |
| Réglementation change | Moyen | Moyenne | Veille réglementaire |

---

## Décisions Stratégiques à Prendre

### Court Terme (Q1 2025)
1. Confirmer roadmap Q1-Q2 avec clients pilotes
2. Recruter développeurs mobile (Q2)
3. Choisir fournisseur dashcams partenaire

### Moyen Terme (Q2-Q3 2025)
4. Stratégie pricing (freemium vs tiered)
5. Focus géographique (Afrique francophone prioritaire?)
6. Partenariats stratégiques (assureurs, loueurs)

### Long Terme (2026)
7. Levée de fonds nécessaire?
8. White label B2B2C?
9. Expansion Europe vs Afrique?

---

## Prochaines Actions Immédiates

### Semaine 1-2 (Jan 2025)
1. ✅ Analyse concurrentielle complétée
2. ✅ Roadmap produit validée
3. [ ] Validation roadmap avec stakeholders
4. [ ] Priorités Q1 confirmées
5. [ ] Démarrage Module Infractions

### Semaine 3-4 (Jan 2025)
6. [ ] Migration database infractions
7. [ ] API CRUD infractions
8. [ ] Tests infractions
9. [ ] Documentation API

### Mois 2 (Fév 2025)
10. [ ] Module Accidents
11. [ ] Module Géofences
12. [ ] Release Q1 v0.2.0

---

## Conclusion

Cette roadmap positionne FleetManager Pro pour:

✅ **Parité fonctionnelle** avec concurrents africains (Q1-Q2 2025)
✅ **Différenciation Green Fleet** (Q2 2025)
✅ **Innovation mobile** (Q2-Q3 2025)
✅ **Leadership prix/simplicité** (continu)
✅ **Expansion internationale** (2026)

**Prochaine étape:** Validation roadmap et démarrage Sprint 1 - Module Infractions
