# 📧 Modèle d'Email de Soumission Officielle — PFE Challenge EMC

**À :** `pfe@educationmedia.ma`  
**Objet :** Soumission PFE Challenge 2026 — Sujet 3 (Covoiturage Régional) — Moaad Daoudi  

---

Madame, Monsieur les membres du Jury de recrutement,  
Chère équipe technique d'Education Media Company (EMC),

J'ai le plaisir de vous soumettre ma réalisation complète pour le **PFE Challenge 2026** sur le **Sujet 3 — Plateforme de Covoiturage Régional avec Découpage par Tronçons**.

Le projet est entièrement opérationnel, architecturé selon les meilleures pratiques d'ingénierie logicielle (Symfony 7.4, PHP 8.3, MySQL 8.0, Docker multi-conteneurs, Doctrine ORM, Tailwind CSS, Stimulus, Leaflet et OSRM), et couvre **l'intégralité des 5 Niveaux du challenge ainsi que le Bonus Traces IA (+15 pts)**.

---

### 🔗 Liens & Ressources du Projet

- **Dépôt GitHub :** [https://github.com/Moaad-Daoudi/emc-pfe-covoiturage](https://github.com/Moaad-Daoudi/emc-pfe-covoiturage)
- **Tag de Release Officielle :** `v1.0.0`
- **Note Conceptuelle (1 Page PDF) :** Disponible directement à la racine du dépôt sous [`note_conceptuelle.pdf`](https://github.com/Moaad-Daoudi/emc-pfe-covoiturage/blob/main/note_conceptuelle.pdf)
- **Archive des Traces IA (+15 pts Bonus) :** Disponible sous [`traces-ia.zip`](https://github.com/Moaad-Daoudi/emc-pfe-covoiturage/raw/main/traces-ia.zip) accompagnée du rapport d'analyse critique [`traces-ia/LISEZMOI.md`](https://github.com/Moaad-Daoudi/emc-pfe-covoiturage/blob/main/traces-ia/LISEZMOI.md)
- **Export JSON brut Google AI Studio :** Inclus sous [`traces-ia/ai-studio/Projet Covoiturage Régional.json`](https://github.com/Moaad-Daoudi/emc-pfe-covoiturage/blob/main/traces-ia/ai-studio/Projet%20Covoiturage%20R%C3%A9gional.json)

---

### ⚡ Démarrage Clé en Main (Niveau 5)

Une seule commande suffit pour démarrer l'ensemble des conteneurs, exécuter les migrations et injecter les fixtures de démonstration :

```bash
git clone https://github.com/Moaad-Daoudi/emc-pfe-covoiturage.git
cd emc-pfe-covoiturage
docker compose up -d
```

- **Application Web :** [http://localhost:8000](http://localhost:8000)
- **Back-Office d'Administration :** [http://localhost:8000/admin](http://localhost:8000/admin)
- **phpMyAdmin :** [http://localhost:8081](http://localhost:8081)

Un module **« Accès Démo Jury (1-clic) »** sur la page `/login` permet de tester instantanément tous les profils (Administrateur, Conductrice Duale avec véhicule homologué, Passager sans véhicule, Conducteur évaluable).

---

### 🧪 Tests Automatisés

La suite de 31 tests unitaires et 136 assertions valide l'intégrité de l'algorithme tarifaire, de l'isolation transactionnelle ACID (verrouillage pessimiste sans overbooking), d'OSRM et de Nominatim :

```bash
docker compose exec php bin/phpunit tests/Unit
```
*Résultat : 31/31 tests réussis (100% vert).*

---

### 👤 Coordonnées du Candidat

- **Nom & Prénom :** Moaad Daoudi
- **Formation :** Élève Ingénieur d'État en Génie de la Transformation Digitale & Intelligence Artificielle (ENSA Al Hoceima)
- **GitHub :** [https://github.com/Moaad-Daoudi](https://github.com/Moaad-Daoudi)

Je reste à votre entière disposition pour l'entretien technique et la soutenance de ce travail.

Je vous prie d'agréer, Madame, Monsieur, l'expression de mes salutations distinguées.

**Moaad Daoudi**
