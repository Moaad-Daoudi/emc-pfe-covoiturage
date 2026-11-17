# 🚗 SafarCar Maroc — Plateforme Régionale de Covoiturage
> **PFE Challenge EMC 2026** — Education Media Company (Technopark Souss-Massa, Agadir)  
> **Candidat :** Moaad Daoudi (Ingénieur d'État en Transformation Digitale & IA · ENSA Al Hoceima)  
> **Sujet Sélectionné :** Sujet 3 — Plateforme de Covoiturage Régional avec Découpage par Tronçons (Niveau 1 à 5 + Bonus IA)  
> **Stack Technologique :** Symfony 7.4 (PHP 8.3-FPM), MySQL 8.0, Docker Multi-Conteneurs, Doctrine ORM, Twig, Tailwind CSS, Stimulus, Leaflet.js, OSRM, Symfony UX Icons (Lucide).

[![Tests](https://img.shields.io/badge/PHPUnit-31%20Tests%20Passing-10b981?style=for-the-badge&logo=php)](tests/Unit)
[![Docker](https://img.shields.io/badge/Docker-Turnkey%20Level%205-0284c7?style=for-the-badge&logo=docker)](INSTALL.md)
[![AI Bonus](https://img.shields.io/badge/Traces%20IA-%2B15%20Pts%20Bonus-8b5cf6?style=for-the-badge&logo=google-gemini)](traces-ia/LISEZMOI.md)
[![License](https://img.shields.io/badge/Challenge-EMC%202026-0d9488?style=for-the-badge)](note_conceptuelle.pdf)

---

## 📌 Présentation du Projet

**SafarCar** est une plateforme web de covoiturage interurbain hautement robuste, conçue sur mesure pour les axes autoroutiers du Royaume du Maroc (Agadir, Essaouira, Marrakech, Casablanca...). 

Elle résout le problème fondamental de la gestion des escales et de la rentabilité des trajets partagés :
1. **Découpage multi-tronçons & Revente de sièges libérés :** Un conducteur publie un trajet avec escales (ex. Agadir $\rightarrow$ Essaouira $\rightarrow$ Marrakech). Un passager peut réserver une portion précise (Agadir $\rightarrow$ Essaouira). Dès que ce passager descend à Essaouira, son siège est **automatiquement remis en vente** pour l'étape suivante (Essaouira $\rightarrow$ Marrakech).
2. **Concurrence & Prévention absolue de l'overbooking :** Utilisation d'un verrouillage pessimiste au niveau base de données (`LockMode::PESSIMISTIC_WRITE` `SELECT ... FOR UPDATE` + `Query::HINT_REFRESH`) avec ordonnancement spatial Dijkstra des verrous.
3. **Architecture de Sécurité Senior :** Un compte unifié `ROLE_USER` pouvant agir simultanément comme conducteur et passager, protégé par des Symfony Voters contextuels (`TripVoter`, `BookingVoter`, `ReviewVoter`).
4. **Calcul d'Itinéraire OSRM & Tarification Réglementaire :** Intégration de l'API de routage OSRM (calcul des distances réelles d'autoroute) et géocodage Nominatim avec cache persistant (30 jours) et matrice de secours hors-ligne (zéro panne garantie).
5. **Déploiement Clé en Main (Niveau 5) :** Démarrage automatique sans intervention manuelle via une seule commande `docker compose up -d`.

---

## 🏆 Matrice de Réalisation des Niveaux du Challenge

| Niveau | Objectif Clé | Statut | Livrables Principaux |
| :--- | :--- | :---: | :--- |
| **Niveau 1** | **Modélisation & Back-Office** | ✅ **Validé** | 11 entités Doctrine, relations multi-tronçons, fixtures marocaines réelles, EasyAdmin 4 complet à `/admin`. |
| **Niveau 2** | **Règles Métier & Concurrence** | ✅ **Validé** | Moteur de réservation ACID, verrouillage pessimiste `PESSIMISTIC_WRITE`, politique d'annulation, 31 tests unitaires. |
| **Niveau 3** | **Expérience Produit & 11 Écrans UI** | ✅ **Validé** | 11 écrans complets en Tailwind CSS, modernisation intégrale en icônes Lucide (50 SVGs locaux), données 100% dynamiques BDD. |
| **Niveau 4** | **APIs Externes & Tarification** | ✅ **Validé** | Intégration OSRM & Nominatim, Leaflet.js interactif, barème tarifaire légal conforme ADM (plafond 0,65 DH/km). |
| **Niveau 5** | **Déploiement Clé en Main** | ✅ **Validé** | Entrypoint automatisé (`docker/php/entrypoint.sh`), démarrage autonome `docker compose up -d`, guide complet `INSTALL.md`. |
| **Bonus** | **Traces IA (+15 pts)** | ✅ **Validé** | Export JSON brut officiel Google AI Studio, transcripts Antigravity, archive `traces-ia.zip` et rapport `LISEZMOI.md`. |

---

## ⚡ Démarrage Rapide (1 Seule Commande)

Prérequis : **Docker Desktop** (ou Docker Engine sous Linux) & **Git**.

```bash
# 1. Cloner le projet
git clone https://github.com/Moaad-Daoudi/emc-pfe-covoiturage.git
cd emc-pfe-covoiturage

# 2. Démarrer l'ensemble des services
docker compose up -d
```

L'application est immédiatement disponible sur :
- **Application Web Publique :** [http://localhost:8000](http://localhost:8000)
- **Back-Office d'Administration :** [http://localhost:8000/admin](http://localhost:8000/admin)
- **phpMyAdmin :** [http://localhost:8081](http://localhost:8081)

Pour les détails d'installation, voir [INSTALL.md](INSTALL.md).

---

## 👤 Comptes de Démonstration (Connexion 1-Clic au `/login`)

| Compte | Identifiant | Mot de passe | Rôle & Scénario d'Évaluation |
| :--- | :--- | :--- | :--- |
| **Administrateur** | `admin@covoit.ma` | `secret123` | `ROLE_ADMIN` — Accès complet au Back-Office EasyAdmin 4 à `/admin`. |
| **Amina Benali (Dual)** | `amina@covoit.ma` | `secret123` | `ROLE_USER` — Profil complet : conductrice avec véhicule homologué, trajets publiés ET réservations en cours. |
| **Karim Idrissi (Conducteur)** | `karim@covoit.ma` | `secret123` | `ROLE_USER` — Conducteur avec trajet terminé (test du dépôt d'avis vérifié à `/trips/43/review`). |
| **Youssef Amrani (Passager)** | `youssef@covoit.ma` | `secret123` | `ROLE_USER` — Passager sans véhicule (réservation sur Agadir → Essaouira, simulation de paiement CMI). |

---

## 🗺️ Tour d'Horizon des 11 Écrans Utilisateur

1. **S01 — Accueil & Recherche :** `/` (recherche intelligente, villes populaires, métriques d'impact CO₂).
2. **S02 — Détail & Réservation Multi-Tronçons (Hero Screen) :** `/trips/43` (carte Leaflet avec tracé OSRM réel, sélecteur dynamique départ/arrivée, calcul instantané de prix et de disponibilité goulot).
3. **S03 — Paiement Sécurisé CMI :** `/trips/43/checkout?from=43&to=45&seats=1` (étapes de paiement, récapitulatif ADM et assurance AXA).
4. **S04 — Dashboard Passager :** `/my/bookings` (billets confirmés, QR code de contrôle, politique d'annulation CNDP).
5. **S05 — Publication de Trajet Multi-Étapes (Hero Screen) :** `/driver/trips/new` (ajout dynamique d'arrêts en Stimulus, aperçu OSRM live, barème légal auto).
6. **S06 — Dashboard Conducteur :** `/driver/trips` (KPIs temps réel BDD, jauge d'occupation, validation de fin de trajet).
7. **S07 — Manifeste Passagers & Feuille de Route :** `/driver/trips/43` (émargement nominatif par arrêt, encaissement CMI/espèces, diffusion SMS groupée).
8. **S08 — Profil Public & Fiche de Confiance :** `/users/22` (avis certifiés, conformité CNI/Permis CNDP, véhicules déclarés).
9. **S09 — Évaluation & Avis Vérifiés :** `/trips/43/review` (attribution d'étoiles, tags d'appréciation, contrôle strict par `ReviewVoter`).
10. **S10 — Mon Compte & Parc Véhicules :** `/account` (gestion du profil, préférences de confort, déclaration de cartes grises).
11. **S11 — Authentification :** `/login` (connexion souveraine + boutons 1-clic d'évaluation jury).

---

## 🧪 Tests Automatisés

Exécution de la suite PHPUnit complète dans le conteneur :

```bash
docker compose exec php bin/phpunit tests/Unit
```

```text
PHPUnit 12.5.44 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.3.36
Configuration: /var/www/html/phpunit.dist.xml

...............................                                   31 / 31 (100%)

Time: 00:00.659, Memory: 18.00 MB
OK (31 tests, 136 assertions)
```

---

## 📂 Documentation & Livrables Officiels

- 📄 **Note Conceptuelle d'Ingénierie (1 Page PDF) :** [`note_conceptuelle.pdf`](note_conceptuelle.pdf) ([Version Markdown](docs/note_conceptuelle.md))
- 📦 **Guide de Déploiement & Installation :** [`INSTALL.md`](INSTALL.md)
- ⚖️ **Journal des Décisions Architecturales :** [`docs/DECISIONS.md`](docs/DECISIONS.md)
- 🗺️ **Roadmap & Suivi des Sprints :** [`SPRINTS.md`](SPRINTS.md)
- 🤖 **Archive Traces IA (+15 Pts) :** [`traces-ia.zip`](traces-ia.zip) ([Rapport LISEZMOI.md](traces-ia/LISEZMOI.md))
- 📧 **Modèle d'Email de Soumission :** [`docs/SUBMISSION_EMAIL.md`](docs/SUBMISSION_EMAIL.md)
