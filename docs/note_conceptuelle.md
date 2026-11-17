# 📄 Note Conceptuelle d'Ingénierie — SafarCar Maroc
> **PFE Challenge EMC 2026** — Education Media Company (Technopark Souss-Massa, Agadir)  
> **Candidat :** Moaad Daoudi (Ingénieur d'État en Transformation Digitale & IA · ENSA Al Hoceima)  
> **Sujet 3 :** Plateforme Régionale de Covoiturage avec Découpage par Tronçons (Niveau 1 à 5)  
> **Document PDF compilé :** [`note_conceptuelle.pdf`](../note_conceptuelle.pdf) (1 page A4)

---

## 1. Choix du Sujet & Compréhension du Besoin Métier
Le **Sujet 3 (Covoiturage régional interurbain)** répond à un défi logistique et économique majeur au Maroc : fluidifier et rentabiliser les mobilités transversales (axe Atlantique et Haut-Atlas reliant Agadir, Essaouira, Marrakech et Casablanca). Les offres classiques échouent à traiter efficacement les haltes intermédiaires.

**Besoin central résolu :** Permettre à un conducteur de publier un itinéraire multi-escales et autoriser les passagers à réserver n'importe quel sous-tronçon. Dès qu'un passager libère son siège à une escale (ex. Essaouira), ce siège est **automatiquement remis en vente** pour l'étape suivante (Essaouira $\rightarrow$ Marrakech), maximisant le taux de remplissage du véhicule dans le respect strict du barème légal non commercial (plafond de 0,65 DH/km).

---

## 2. Décomposition du Problème & Arbitrages d'Architecture

### • Modélisation Atomique par Tronçons (ADR-003)
Décomposition d'un trajet en une chaîne ordonnée d'arrêts (`TripStop`) et de sous-segments adjacents (`TripSegment`). La disponibilité pour un trajet entre l'arrêt $i$ et l'arrêt $j$ correspond au goulot d'étranglement exact :
$$\text{Capacité}(i, j) = \min_{k=i}^{j-1} (\text{availableSeats}_k)$$
L'incrémentation/décrémentation des places est strictement ciblée sur les segments traversés.

### • Gestion de la Concurrence Sévère (ADR-002)
Choix délibéré d'un **verrouillage pessimiste** (`LockMode::PESSIMISTIC_WRITE` via `SELECT ... FOR UPDATE`) ordonné selon Dijkstra (`segmentOrder ASC`) combiné à `Query::HINT_REFRESH`. Contrairement au verrouillage optimiste qui rejetterait l'utilisateur avec une erreur 409 lors des pics d'achat, le verrou pessimiste sérialise proprement les réservations et élimine tout risque de surréservation (*overbooking*) ou d'interblocage (*deadlock*).

### • Architecture de Sécurité Senior
Deux rôles techniques socles (`ROLE_USER`, `ROLE_ADMIN`) combinés à 3 Symfony Voters contextuels (`TripVoter`, `BookingVoter`, `ReviewVoter`). Cela satisfait avec élégance la règle métier *« un utilisateur peut être conducteur sur un trajet et passager sur un autre »* sans duplication de tables ni commutation de session.

### • Résilience SIG OSRM / Nominatim (ADR-004)
Cache persistant PSR-6 (30 jours) associé à une matrice routière marocaine de secours hors-ligne (A3, A7, N1) garantissant un fonctionnement 100% autonome et sans panne même en cas de coupure réseau ou de limitation de débit d'API externe.

---

## 3. Ordre de Réalisation & Gestion du Temps (Méthode Agile 7 Jours)
- **Sprint 0-1 (J1-J2) :** Socle Docker multi-conteneurs, modélisation relationnelle Doctrine, migrations et Back-Office EasyAdmin 4.
- **Sprint 2 (J3) :** Moteur de réservation transactionnel, isolation ACID, politique d'annulation et suite de tests unitaires (PHPUnit).
- **Sprint 3 (J4-J5) :** Parcours complet des 11 écrans UX/UI en Tailwind CSS/Stimulus, intégration des icônes vectorielles Lucide et dynamisation 100% BDD.
- **Sprint 4-5 (J6-J7) :** Intégration cartographique Leaflet/OSRM, algorithme tarifaire équitable, conteneurisation clé en main (Niveau 5) et traces IA.

---

## 4. Utilisation de l'IA (Succès, Limites & Arbitrages de l'Ingénieur)
- **Ce qui a fonctionné :** La synergie entre **Google AI Studio (Gemini 2.5 / Hydrogen)** pour le cadrage architectural initial et **Antigravity** pour l'implémentation itérative a permis un gain de productivité d'environ **65%** sur l'échafaudage du boilerplate (entités, contrôleurs, DTOs, fixtures).
- **Hallucinations & Arbitrages de l'Ingénieur :**
  1. *Concurrence :* L'IA préconisait un verrouillage optimiste `@Version`. Face aux exigences d'expérience utilisateur lors de rushs de réservation, j'ai imposé le modèle pessimiste avec réévaluation immédiate du cache Doctrine (`HINT_REFRESH`).
  2. *Dépendance externe :* L'IA générait des appels OSRM/Nominatim synchrones sans tolérance de panne. J'ai conçu la couche de cache et la matrice de contingence locale marocaine pour garantir une haute disponibilité permanente lors de l'évaluation du jury.
  3. *Qualité UI :* Refus des suggestions d'emojis informels ou de termes commerciaux inappropriés (ex. « haut de gamme ») au profit d'un design system épuré sous Symfony UX Icons (Lucide) et d'un lexique conforme au transport solidaire.

---

## 5. Perspectives d'Évolution avec 1 Semaine Supplémentaire
1. **Passerelle Monétique Marocaine :** Intégration du SDK CMI / PayZone avec authentification 3D-Secure et séquestre bancaire automatisé.
2. **Dynamic Route Matching :** Algorithme de calcul de détour (max 5 km / 10 min) pour proposer des points de ramassage optimisés sur autoroute.
3. **Application Mobile Compagnon :** Client Flutter iOS/Android pour géolocalisation live du véhicule et notifications push Firebase.
