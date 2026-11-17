# 🚗 Regional Carpooling Platform (Plateforme de Covoiturage Régional)
> **PFE Challenge EMC 2026** — Education Media Company (Technopark Souss Massa, Agadir)  
> **Candidate:** Moaad Daoudi (State Digital Transformation & AI Engineer Student, ENSA Al Hoceima)  
> **Subject:** Subject 3 — Regional Carpooling Platform (Sujet 3 — Plateforme de covoiturage régional)  
> **Stack & Tools:** Symfony 7.4 (PHP 8.3), MySQL 8.0, Docker, Doctrine ORM, Twig, Tailwind CSS, Stitch AI (UI Design), Stimulus, OSRM & Nominatim APIs

---

## 📌 Project Overview

This project is a complete, production-ready regional carpooling web application built for the Moroccan inter-city transport network (Agadir, Marrakech, Essaouira, Casablanca...). 

The platform supports:
- **Multi-leg / Stopover journeys**: Drivers publish routes with intermediate stops, and passengers can book any subset portion (sub-segment).
- **Sub-segment seat availability & atomic locking**: Eliminates overbooking using database-level pessimistic locking (`LockMode::PESSIMISTIC_WRITE`) within ACID transactions.
- **Dual-role user accounts**: A single user account can seamlessly operate both as a Passenger and as a Driver.
- **External Geo-routing & Smart Pricing**: Powered by **Nominatim** (OpenStreetMap geocoding) and **OSRM** (Open Source Routing Machine) to compute real road distances and suggest fair trip prices.
- **Turnkey Dockerized delivery**: Fully automated local orchestration via Docker Compose.

---

## 🏆 Challenge Levels Progress

| Level | Topic | Status |
| :--- | :--- | :---: |
| **Level 1** | **Domain Modeling & Back-Office** (Entities, MySQL schema, migrations, Moroccan seed data, EasyAdmin) | ⏳ In Progress |
| **Level 2** | **Business Rules & Concurrency** (Sub-segment inventory, pessimistic locking, cancellation rules, PHPUnit tests) | ⏳ In Progress |
| **Level 3** | **Product Experience & UI** (11 user-facing screens, Symfony Voters, dual-persona UX, verified reviews) | ⏳ In Progress |
| **Level 4** | **External APIs & Dynamic Pricing** (Nominatim geocoding, OSRM real road distance, interactive Leaflet map) | ⏳ In Progress |
| **Level 5** | **Delivery & Packaging** (One-command Docker startup `docker compose up -d`, `INSTALL.md`) | ⏳ In Progress |
| **Bonus** | **AI Engineering Traces** (+15 pts bonus: `traces-ia/` logs, prompt history & `LISEZMOI.md`) | ✅ Documented |

---

## 🚀 Quick Start (Development)

Prerequisites: **Docker Desktop** & **Git**.

```bash
# 1. Clone the repository
git clone https://github.com/<your-username>/emc-pfe-covoiturage.git
cd emc-pfe-covoiturage

# 2. Start the multi-container environment
docker compose up -d

# 3. Access the web application
# Public app:  http://localhost:8000
# Back-Office: http://localhost:8000/admin
```

For detailed setup, demo credentials, and automated testing, see [INSTALL.md](INSTALL.md) (coming in Level 5).

---

## 📂 Documentation

- 🗺️ **Sprint Roadmap & Tickets:** [SPRINTS.md](SPRINTS.md)
- 🛠️ **Tools & Environment Guide:** [tools.txt](tools.txt)
- 📐 **Domain Model & ER Diagram:** `docs/MODELE.md` *(Sprint 1)*
- ⚖️ **Architectural Decisions Log:** `docs/DECISIONS.md` *(Sprint 1 & 2)*
- 🤖 **AI Engineering Traces (+15 pts):** [traces-ia/LISEZMOI.md](traces-ia/LISEZMOI.md)
