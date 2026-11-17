# 🤖 Rapport d'Utilisation de l'Intelligence Artificielle — SafarCar Maroc
> **PFE Challenge EMC 2026** — Education Media Company (Technopark Souss-Massa, Agadir)  
> **Candidat :** Moaad Daoudi (Ingénieur d'État en Transformation Digitale & IA · ENSA Al Hoceima)  
> **Sujet 3 :** Plateforme Régionale de Covoiturage avec Découpage par Tronçons (Bonus Traces IA +15 pts)  
> **Archive d'évaluation :** [`traces-ia.zip`](../traces-ia.zip)

---

## 1. Outils d'IA Mobilisés & Répartition des Tâches

| Outil IA | Modèle / Version | Périmètre & Responsabilités |
| :--- | :--- | :--- |
| **Google AI Studio** | Gemini 2.5 / Hydrogen (Reasoning) | Cadrage stratégique du domaine, modélisation relationnelle de l'entité multi-tronçons (`TripStop` + `TripSegment`), arbitrage de tarification dégressive. |
| **Stitch AI** | UI & Front-end Layout Engine | Prototypage rapide des 11 écrans, maquettage haute-fidélité en Tailwind CSS et ergonomie mobile-first. |
| **Antigravity (Google DeepMind)** | Agent d'Ingénierie Autonome | Architecture multi-conteneurs Docker, orchestration Symfony 7.4 (PHP 8.3), algorithme de verrouillage pessimiste ACID, suite PHPUnit, OSRM/Nominatim et modernisation Lucide. |

---

## 2. Structure du Dossier d'Archive (`traces-ia/`)

```text
traces-ia/
├── LISEZMOI.md                             # Rapport d'analyse critique et retours d'expérience
├── ai-studio/
│   ├── Projet Covoiturage Régional.json   # Export JSON brut officiel de la session Google AI Studio
│   └── architecture-chat.md                # Synthèse textuelle des prompts et réponses d'architecture
└── antigravity/
    ├── GEMINI.md                           # Instructions de gouvernance et règles d'ingénierie
    ├── implementation_plan.md              # Plan de suivi itératif des 5 Sprints
    ├── walkthrough.md                      # Guide de validation et scénarios d'évaluation
    └── session-transcript.md               # Historique des commandes, décisions techniques et vérifications
```

---

## 3. Les 3 Enseignements Clés & Rigueur d'Ingénierie (Critères d'Évaluation)

### A. Où l'IA a fait gagner un temps précieux (Gains de Productivité ~65%)
1. **Échafaudage Relationnel & DTOs :** Génération rapide du modèle de données (11 entités Doctrine, relations bidirectionnelles, DTOs `RouteResult`, `PricingBreakdown`, `GeocodingResult`).
2. **Design System & Ergonomie UI :** Accélération spectaculaire de l'intégration front-end en Tailwind CSS pour les 11 écrans (recherche, timeline d'arrêts, manifeste conducteur, paiement CMI, tableau de bord passager).
3. **Fixtures Régionales Marocaines Cohérentes :** Génération instantanée d'un jeu de données réaliste ancré dans le Royaume (coordonnées GPS précises des gares routières d'Agadir Al Massira, Bab Doukkala Marrakech, Casa Port, péages ADM, profils conducteurs vérifiés CNI).

### B. Où l'IA a fait des erreurs ou halluciné (Failles Identifiées)
1. **Concurrence & Surréservation (Overbooking) :** L'IA proposait systématiquement une vérification applicative naïve (`if ($availableSeats >= $requested)`), puis un verrouillage optimiste avec `@Version`. Lors de rushs de réservation (pics de départs Aïd ou week-end), le verrou optimiste rejette l'utilisateur avec une exception HTTP 409, dégradant l'expérience client.
2. **Dépendance Bloquante aux APIs Externes :** Les premiers scripts générés par l'IA effectuaient des requêtes HTTP synchrones vers OSRM et Nominatim sans politique de retry, sans cache et sans gestion de secours. En cas de coupure réseau ou de quota dépassé (erreur 429), toute l'application plantait.
3. **Usage d'Emojis et Termes Inappropriés :** L'IA a initialement injecté des emojis informels et des qualificatifs commerciaux déplacés (ex. *« haut de gamme »*), contraires au statut solidaire et non commercial du covoiturage.

### C. Comment l'Ingénieur a Détecté et Corrigé les Erreurs (Arbitrages d'Ingénierie)
1. **Implémentation du Verrouillage Pessimiste (`PESSIMISTIC_WRITE`) :**
   - Mise en place d'une transaction stricte (`$em->wrapInTransaction()`) avec `SELECT ... FOR UPDATE` sur les entités `TripSegment`.
   - **Protection contre le cache Doctrine :** Injection de `Query::HINT_REFRESH => true` pour forcer Doctrine à écraser son Identity Map en mémoire avec les données fraîches de MySQL.
   - **Élimination des Deadlocks :** Tri systématique des verrous par ordre spatial croissant (`ORDER BY segmentOrder ASC`), supprimant mathématiquement le risque d'interblocage circulaire (conditions de Coffman).
2. **Architecture Résiliente à Double Niveau (Cache PSR-6 + Matrice Hors-Ligne) :**
   - Encapsulation des requêtes OSRM et Nominatim derrière une couche de cache Symfony (TTL 30 jours).
   - Intégration d'une **matrice de secours locale** couvrant les distances et tracés exacts des axes autoroutiers marocains (A3 Agadir-Marrakech, A7 Marrakech-Casablanca), garantissant un fonctionnement à 100% même conteneur totalement déconnecté d'Internet.
3. **Design System Professionnel :** Remplacement systématique des emojis par la suite vectorielle **`symfony/ux-icons` (Lucide)** et alignement du lexique sur la réglementation marocaine des transports.

---

## 4. Recommandation pour les Évaluateurs
Pour explorer les échanges complets avec les modèles de fondation :
- Le fichier [`Projet Covoiturage Régional.json`](ai-studio/Projet%20Covoiturage%20Régional.json) peut être directement réimporté dans [Google AI Studio](https://aistudio.google.com/) pour visualiser l'arborescence des invites de raisonnement.
