# 📦 Guide d'Installation & Déploiement — SafarCar Maroc
> **PFE Challenge EMC 2026** — Education Media Company (Technopark Souss-Massa, Agadir)  
> **Candidat :** Moaad Daoudi (State Digital Transformation & Artificial Intelligence Engineer)  
> **Projet :** Sujet 3 — Plateforme Régionale de Covoiturage avec Découpage par Tronçons (Niveau 1 à 5)

---

## 📋 Prérequis
- **Docker Engine** (version 24.0+ recommandée)
- **Docker Compose** (version 2.20+ avec plugin compose v2)
- **Git**
- Navigateur web moderne (Chrome, Firefox, Safari, Edge)

---

## ⚡ Démarrage Clé en Main (1 Seule Commande — Niveau 5)

L'architecture du conteneur intègre un script d'initialisation automatique (`docker/php/entrypoint.sh`). Aucune commande manuelle de migration ou de chargement de données n'est requise après le démarrage :

### 1. Cloner le dépôt Git
```bash
git clone https://github.com/Moaad-Daoudi/emc-pfe-covoiturage.git
cd emc-pfe-covoiturage
```

### 2. Démarrer les conteneurs Docker
```bash
docker compose up -d
```

> **Ce qui se produit automatiquement en arrière-plan :**
> 1. `covoit_mysql` (MySQL 8.0) démarre et effectue son healthcheck.
> 2. `covoit_php` (PHP 8.3-FPM) attend la disponibilité effective de la base de données via PDO.
> 3. Les migrations Doctrine sont appliquées automatiquement (`doctrine:migrations:migrate`).
> 4. Si la base est vierge, les fixtures marocaines (villes, axes autoroutiers A3/A7, utilisateurs certifiés CNI, véhicules homologués, trajets multi-tronçons) sont automatiquement chargées (`doctrine:fixtures:load`).
> 5. `covoit_web` (Nginx) expose l'application immédiatement sur le port **8000**.

---

## 🌐 Points d'Accès de la Plateforme

| Service | URL | Identifiants / Notes |
| :--- | :--- | :--- |
| **Plateforme SafarCar** | [http://localhost:8000](http://localhost:8000) | Application complète, 11 écrans interactifs |
| **Administration Back-Office** | [http://localhost:8000/admin](http://localhost:8000/admin) | EasyAdmin 4 (réservé au rôle `ROLE_ADMIN`) |
| **phpMyAdmin** | [http://localhost:8081](http://localhost:8081) | Utilisateur : `root` \| Mot de passe : `rootsecret` |
| **Serveur MySQL** | `localhost:3306` | Base : `covoiturage` \| User : `covoit` / `secret` |

---

## 👤 Comptes de Démonstration (Pré-configurés dans les Fixtures)

La page de connexion `/login` propose un module **Accès Démo Jury (1-clic)** permettant de s'authentifier instantanément sans saisir d'identifiants :

| Profil | Email | Mot de passe | Rôle & Scénario d'Évaluation |
| :--- | :--- | :--- | :--- |
| **Administrateur** | `admin@covoit.ma` | `secret123` | `ROLE_ADMIN` — Supervision complète du parc de véhicules, trajets, réservations et modération des avis sur `/admin`. |
| **Amina Benali (Dual)** | `amina@covoit.ma` | `secret123` | `ROLE_USER` — Profil complet : conductrice avec véhicule homologué (Peugeot 208), trajets actifs publiés ET réservations passager en cours. |
| **Karim Idrissi (Conducteur)** | `karim@covoit.ma` | `secret123` | `ROLE_USER` — Conducteur avec trajet terminé (idéal pour tester le dépôt d'avis vérifié sur `/trips/43/review`). |
| **Youssef Amrani (Passager)** | `youssef@covoit.ma` | `secret123` | `ROLE_USER` — Passager sans véhicule (réservation confirmée sur Agadir → Essaouira, politique d'annulation, simulation CMI). |

---

## 🗺️ Tour d'Horizon des 11 Écrans Métier

- **S01 — Accueil & Recherche Intelligente :** `/` (recherche avec inversion, pills populaires Souss-Massa, badges CO₂ et économies).
- **S02 — Détail & Réservation Multi-Tronçons (Hero Screen) :** `/trips/43` (carte Leaflet avec tracé OSRM réel, sélecteur réactif départ/arrivée, calcul dynamique du tarif et des places goulot d'étranglement).
- **S03 — Paiement Sécurisé CMI / Espèces :** `/trips/43/checkout?from=43&to=45&seats=1` (garantie séquestre, récapitulatif ADM et assurance AXA).
- **S04 — Dashboard Passager :** `/my/bookings` (billets confirmés, QR code de contrôle, politique d'annulation CNDP).
- **S05 — Publier un Trajet Multi-Étapes (Hero Screen) :** `/driver/trips/new` (ajout dynamique d'escales, aperçu OSRM en direct, barème tarifaire automatique équitable).
- **S06 — Dashboard Conducteur :** `/driver/trips` (4 KPIs réels calculés depuis la BDD, jauge d'occupation, bouton de validation finale).
- **S07 — Feuille de Route & Manifeste Passagers :** `/driver/trips/43` (timeline d'arrêts, validation d'embarquement nominative, diffusion SMS groupée).
- **S08 — Profil Public & Fiche de Confiance :** `/users/22` (score d'avis authentifiés, conformité CNI/Permis CNDP, véhicules déclarés).
- **S09 — Système d'Avis & Notations Vérifiées :** `/trips/43/review` (attribution d'étoiles, tags d'appréciation, contrôle strict par `ReviewVoter`).
- **S10 — Mon Compte & Parc Véhicules :** `/account` (gestion du profil, préférences de bord non-fumeur/musique, déclaration de cartes grises).
- **S11 — Authentification Souveraine :** `/login` (connexion sécurisée + raccourcis 1-clic d'évaluation jury).

---

## 🧪 Exécution des Tests Automatisés

Le projet comprend une suite complète de tests unitaires et fonctionnels couvrant les règles métier critiques (concurrence, algorithme de tarification, OSRM, géocodage Nominatim) :

```bash
docker compose exec php bin/phpunit tests/Unit
```

**Résultat attendu :**
```text
OK (31 tests, 136 assertions) — 100% passing
```

---

## 🔄 Réinitialisation Complète de la Base de Données (Optionnel)

Si vous souhaitez recharger les données de démonstration initiales à tout moment :

```bash
docker compose exec php bin/console doctrine:fixtures:load --no-interaction
```

---

## 🛠️ Dépannage & Commandes Utiles

- **Inspecter les logs du serveur d'application :**
  ```bash
  docker compose logs -f php
  ```
- **Inspecter les logs du serveur web Nginx :**
  ```bash
  docker compose logs -f web
  ```
- **Conflit de ports (ex. port 8000 ou 3306 déjà occupé) :**
  Modifiez les mappages de ports dans `docker-compose.yml` (ex: `"8080:80"` ou `"3307:3306"`).
- **Arrêt de l'environnement :**
  ```bash
  docker compose down
  ```
- **Arrêt et suppression des volumes de données :**
  ```bash
  docker compose down -v
  ```
