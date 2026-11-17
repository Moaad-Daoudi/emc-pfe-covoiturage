# 📝 Engineering Session Logs & Technical Prompts (Antigravity)

## Session 01: Strategic Scoping, Architecture & Sprint Planning
**Date:** November 16-17, 2026  
**Tool:** Antigravity AI Agent  
**Subject:** Subject 3 — Regional Carpooling Platform  

### 1. Profile Evaluation & Subject Selection
- **User Prompt:**
  > "i have this project : enonce-pfe-challenge, can you tell me what is the best for me as ai engineer you can see my portfolio and my cvs"
- **Agent Analysis:**
  - Evaluated the candidate's CV and existing projects (*PFE Matcher*, *Job Intelligent*, *AtlasLegalAI*, *MediConsult*).
  - Identified core competencies in optimization algorithms, constraint programming, and data engineering.
  - Strongly recommended Subject 3 (Regional Carpooling) due to the algorithmic challenge of partial sub-segment seat management and graph-based OSRM routing.

### 2. Role Architecture & UX Design
- **User Prompt:**
  > "how much roles should be on it and i want to build it like senior engineer"
- **Senior Decision:**
  - Rejected rigid separation of driver vs passenger accounts.
  - Implemented 2 technical security roles (`ROLE_USER`, `ROLE_ADMIN`) combined with contextual **Symfony Voters** (`TripVoter`, `BookingVoter`, `ReviewVoter`) to satisfy the challenge requirement: *"one person can be both"*.
  - Mapped a crisp 11-screen SaaS-grade UX blueprint + 1 unified EasyAdmin back-office.

### 3. Sprint Planning
- Authored the comprehensive roadmap `SPRINTS.md` organizing the 7-day effort into 5 cumulative sprints aligned with the 5 challenge levels.

## Session 02: Sprint 0 Execution — Docker Orchestration & Symfony 7.4 Setup
**Date:** November 17, 2026  
**Tool:** Antigravity AI Agent  
**Accomplishments:**
- Configured multi-container Docker architecture: `php` (PHP 8.3 FPM, Composer 2, pdo_mysql, intl, opcache, zip, apcu), `web` (Nginx), and `database` (MySQL 8.0).
- Successfully bootstrapped Symfony 7.4.23 skeleton and installed full webapp/orm packs.
- Tested and verified database connectivity to MySQL 8.0: database `covoiturage` created and verified.
- Handled Flex automated recipes cleanly (resolved PostgreSQL conflicts injected by Doctrine Flex recipe).


---

## Session 04 — Back-Office Administration & Authentication Setup (T-104 & S11/S12)

- **Back-Office Implementation (`/admin`):**
  - Integrated `easycorp/easyadmin-bundle` with a dedicated `DashboardController`.
  - Built 6 complete CRUD controllers:
    - `CityCrudController`: Moroccan cities with GPS latitude & longitude formatting.
    - `UserCrudController`: User management, email, names, phone numbers, and roles.
    - `VehicleCrudController`: Fleet management with owner relation, seats, air conditioning.
    - `TripCrudController`: Trajets with departure/arrival cities, departure dates, seat counts, price, and status enum.
    - `BookingCrudController`: Reservations with boarding/alighting stops, seat counts, total price, and status enum.
    - `ReviewCrudController`: User ratings (1 to 5) and feedback moderation.
  - Enforced strict access control in `security.yaml` with `ROLE_ADMIN` protection on `^/admin`.
- **Authentication & Testing Ergonomics (S11):**
  - Scaffolded `SecurityController` with `/login` and `/logout`.
  - Created a custom styled Tailwind login view (`templates/security/login.html.twig`) with a **1-Click Demo Login** component pre-filling credentials for all 4 jury test accounts (`admin@covoit.ma`, `amina@covoit.ma`, `karim@covoit.ma`, `youssef@covoit.ma` with password `Demo1234!`).
  - Added dynamic authentication header in `templates/base.html.twig` showing current user and direct access to `/admin` for administrators.


---

## Session 05 — Booking Engine & Multi-Segment Inventory Domain Service (T-201)

- **Domain Exceptions (`src/Domain/Exception/`):**
  - `InsufficientSeatsException`: Thrown with explicit segment details when capacity is exceeded.
  - `SelfBookingException`: Prevents drivers from reserving seats on their own trips.
  - `TripNotBookableException`: Blocks reservations on non-scheduled or already departed trips.
  - `InvalidStopSequenceException`: Rejects invalid or inverted stop combinations.
- **Repository Optimization (`TripSegmentRepository`):**
  - Implemented `findSegmentsForPath(Trip $trip, int $fromOrder, int $toOrder)` strictly ordered by `segmentOrder ASC` to enforce deterministic lock ordering.
- **Booking Engine (`BookingService`):**
  - Built `getSegmentsForPath()` to extract traversed atomic segments.
  - Built `getAvailableSeatsForPath()` computing the bottleneck capacity: $\min(\text{availableSeats})$.
  - Built `calculatePriceForPath()` totaling intermediate segment prices.
  - Built `bookTrip()` orchestrating invariant checks, atomic seat decrementation, and persistence of `Booking` and `BookingSegment` records.
