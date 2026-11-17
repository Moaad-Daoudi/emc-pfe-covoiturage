# 📐 Technical Implementation Plan & Sprint Tracker

## Sprint 0: DevOps & Initial Skeleton
- [x] Create `Dockerfile` (PHP 8.3-FPM + Composer + required extensions).
- [x] Configure `docker-compose.yml` (Services: php, web Nginx, db MySQL 8.0).
- [x] Initialize Symfony 7.4 project via Composer within the container.
- [x] Verify database connection and push the initial Git commit.

## Sprint 1: Level 1 — Domain Model & Back-Office
- [x] Entities: `User`, `Vehicle`, `City`, `Trip`, `TripStop`, `TripSegment`, `Booking`, `BookingSegment`, `Review`.
- [x] Doctrine Migrations & MySQL 8.0 schema validation.
- [x] `AppFixtures` with FakerPHP: Moroccan cities, 4 demo accounts, and multi-stop journeys.
- [x] Install EasyAdmin 4 (`/admin`) for domain object administration.
- [x] Mermaid ER Diagram in `docs/MODELE.md`.

## Sprint 2: Level 2 — Concurrency, Business Rules & Tests
- [x] `BookingService` domain service with multi-segment inventory validation (T-201).
- [x] Pessimistic locking (`PESSIMISTIC_WRITE`) transaction engine (T-202).
- [x] Distinct cancellation policies for Driver and Passenger (T-203).
- [x] Post-trip review eligibility rules (T-204).
- [x] Automated test suite with PHPUnit (`tests/`) (T-205).

## Sprint 3: Level 3 — Product & 11 UI Screens
- [x] T-301: Base Layout & Design System (`templates/base.html.twig`, Tailwind emerald/forest palette, Plus Jakarta Sans, navbar, footer).
- [x] T-302: Screen S01 — Home & Search Experience (`/`) matching Stitch AI prototype (Hero, trust metrics bar, filter sidebar, sub-segment search cards, value props, testimonials).
- [ ] T-303: Screen S02 — Trip Details & Segment Booking (`/trips/{id}`).
- [ ] T-304: Screen S03 — Simulated Checkout (`/bookings/{id}/checkout`).
- [ ] T-305: Screen S04 — Passenger Dashboard (`/my/bookings`).
- [ ] T-306: Screen S05 — Publish a Trip (`/driver/trips/new`).
- [ ] T-307: Screens S06 & S07 — Driver Space & Manifest (`/driver/trips`).
- [ ] T-308: Screens S08 & S09 — Public Profiles & Review System (`/users/{id}`, `/trips/{id}/review`).
- [ ] T-309: Screen S10 — Account & Vehicle Management (`/account`).
- [ ] T-310: Screen S11 — Admin Moderation Dashboard.
- [ ] T-311: Demo Switcher Component (1-click login as Amina, Karim, Youssef, Admin).

## Sprint 4: Level 4 — OSRM & Nominatim External APIs
- [ ] `NominatimService` with caching.
- [ ] `OsrmService` for real road distance and travel duration.
- [ ] Suggested pricing calculation engine.
- [ ] Interactive Leaflet.js map integration.

## Sprint 5: Level 5 — Turnkey Delivery & Project Wrap-Up
- [ ] Single startup command: `docker compose up -d`.
- [ ] Installation guide: `INSTALL.md`.
- [ ] Architectural decisions log: `docs/DECISIONS.md`.
- [ ] 1-page Conceptual Note in PDF (`note_conceptuelle.pdf`).
- [ ] Finalize and compress `traces-ia.zip`.
