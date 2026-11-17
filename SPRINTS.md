# 🚀 Roadmap & Sprints — Regional Carpooling Platform (Subject 3)
**Project:** PFE Challenge EMC — Technopark Souss Massa  
**Candidate:** Moaad Daoudi (State Digital Transformation | Artificial Intelligence Engineer)  
**Required Stack:** Symfony 7.4 (PHP 8.3), MySQL 8.0, Docker, Doctrine ORM, Twig / Tailwind / Stimulus  
**Timeline:** 7 Days (5 Sprints mapped to the 5 Challenge Levels)

---

## 📊 Project Alignment Matrix

```mermaid
flowchart LR
    S0["Sprint 0: Setup & Docker"] --> S1["Sprint 1: Modeling & Admin (L1)"]
    S1 --> S2["Sprint 2: Concurrency & Rules (L2)"]
    S2 --> S3["Sprint 3: Product & 11 UI Screens (L3)"]
    S3 --> S4["Sprint 4: OSRM APIs & Pricing (L4)"]
    S4 --> S5["Sprint 5: Delivery, Concept Note & AI Traces (L5)"]
```

---

## 🏗️ Role & Security Architecture (Senior Pattern)

* **2 Technical Security Roles (`security.yaml`):**
  * `ROLE_USER`: Single unified member account capable of acting as both Passenger and Driver.
  * `ROLE_ADMIN`: Exclusive access to the Back-Office (`/admin` via EasyAdmin).
* **2 Contextual Domain Roles (Enforced via Symfony Voters):**
  * `TripVoter` (`TRIP_BOOK`, `TRIP_MANAGE`, `TRIP_CANCEL`): Prevents drivers from booking their own trips and restricts trip management to the trip owner.
  * `BookingVoter` (`BOOKING_CANCEL_PASSENGER`): Validates passenger cancellation policies.
  * `ReviewVoter` (`REVIEW_CREATE`): Only permits reviews if the trip is `COMPLETED`, the user was a confirmed participant, and has not already reviewed that person for that trip.
* **4 Pre-configured Demo Accounts in DataFixtures:**
  1. `admin@covoit.ma`: System Administrator (`ROLE_ADMIN`).
  2. `amina@covoit.ma`: Dual Persona (`ROLE_USER`, owns a verified vehicle, has published trips AND has passenger bookings).
  3. `karim@covoit.ma`: Driver with past completed trips (allows immediate testing of post-trip reviews and ratings).
  4. `youssef@covoit.ma`: Passenger without a car (tests partial segment bookings, cancellation policies, and vehicle prompts).

---

## 🗺️ Screen Blueprint: 11 UI Views + 1 Unified Back-Office

| ID | Route | Screen / Component | Core Purpose & Features |
|:---|:---|:---|:---|
| **S01** | `/` | Home & Unified Trip Search | Search bar (Origin, Destination, Date, Seats) + Matching trip cards displaying exact sub-segment match, remaining seats, and segment price. |
| **S02** | `/trips/{id}` | Trip Details & Segment Booking *(Hero Screen #1)* | Interactive Leaflet/OSRM route map, stop timeline, dynamic sub-segment selector, live availability/pricing, and booking CTA. |
| **S03** | `/bookings/{id}/checkout` | Simulated Payment & Confirmation | Segment recap, cancellation policy reminder, transactional lock trigger, and seat deduction. |
| **S04** | `/my/bookings` | Passenger Dashboard | Two tabs (*Upcoming* | *Past & Cancelled*), cancellation modal, and "Leave a review" button (active if trip is `COMPLETED`). |
| **S05** | `/driver/trips/new` | Publish a Trip *(Hero Screen #2)* | Dynamic intermediate stop fields (Stimulus), Leaflet route preview, OSRM real road distance calculation, and suggested price auto-fill. |
| **S06** | `/driver/trips` | Driver Dashboard | Two tabs (*Published / Upcoming* | *History*), occupancy gauge, and a "Mark as Completed" action button for easy evaluator review testing. |
| **S07** | `/driver/trips/{id}` | Passenger Manifest & Trip Management | Detailed boarding/exiting manifest per stop, confirmed passenger contact info, and driver cancellation trigger. |
| **S08** | `/users/{id}` | Public Profile & Reviews | Bio, registered vehicles, badges, aggregate star rating, and verified review feed. |
| **S09** | `/trips/{id}/review` | Submit Review | Star rating (1 to 5) + comment form, strictly protected by `ReviewVoter`. |
| **S10** | `/account` | My Account & Vehicles | Profile management + "My Vehicles" tab (enables any `ROLE_USER` to seamlessly act as a driver). |
| **S11** | `/login` & `/register` | Authentication | Standard login form + "1-Click Demo Login" box with 4 prefilled buttons for instant jury testing. |
| **S12** | `/admin` | EasyAdmin 4 Back-Office | Full CRUD administration: Cities, Users, Vehicles, Trips, Bookings, and Review moderation. |

---

## 📅 Detailed Sprints & Actionable Tickets

### 🔹 Sprint 0: Initialization, Docker Environment & Git Baseline (Day 1 - Morning)
> **Goal:** Build the complete multi-container Docker foundation and establish the initial public GitHub repository commit.

- [ ] **T-001: Multi-Container Docker Configuration**
  - `php` container: PHP 8.3 FPM, Composer, extensions (`pdo_mysql`, `intl`, `opcache`, `zip`).
  - `web` container: Nginx with optimized Symfony vhost pointing to `public/index.php`.
  - `database` container: MySQL 8.0 with persistent storage volume and healthcheck.
- [ ] **T-002: Symfony 7.4 Skeleton Setup**
  - Install via Composer: `symfony/webapp-pack`, `symfony/orm-pack`.
  - Configure `.env.example` and test database environments.
- [ ] **T-003: Public Git Repository Setup**
  - Create the public repository: `emc-pfe-covoiturage`.
  - Push the baseline commit: `chore(init): initial symfony 7.4 skeleton with docker environment`.
  - Set up `docs/` folder and `README.md` structure.

---

### 🔹 Sprint 1: Level 1 — Domain Modeling, Fixtures & Back-Office (Day 1 Afternoon - Day 2)
> **Goal:** Design the relational domain model, generate realistic Moroccan seed data, and deliver the EasyAdmin back-office.

- [ ] **T-101: Domain Entity Modeling**
  - `User`: Implements `UserInterface`, profile info, avatar, phone.
  - `Vehicle`: License plate, make, model, color, total seats.
  - `City`: Name (Agadir, Marrakech, Essaouira, Casablanca...), latitude, longitude.
  - `Trip`: Driver, vehicle, departure city, arrival city, departure datetime, status (`SCHEDULED`, `COMPLETED`, `CANCELLED`).
  - `TripStop`: Ordered intermediate stops (`stopOrder`), estimated arrival time.
  - `TripSegment`: Atomic segment connecting 2 consecutive stops, available seats, calculated price.
  - `Booking`: Passenger, trip, boarding stop, drop-off stop, seat count, status (`CONFIRMED`, `CANCELLED_PASSENGER`, `CANCELLED_DRIVER`), total price.
  - `BookingSegment`: Junction entity mapping reserved seats across each atomic segment.
  - `Review`: Author, recipient, trip, rating (1-5), comment, creation timestamp.
- [ ] **T-102: Doctrine Migrations**
  - Generate and run the initial migration with foreign key constraints and query indexes.
- [ ] **T-103: Realistic Moroccan Fixtures (`AppFixtures`)**
  - Integrate `FakerPHP`.
  - Seed 15 Moroccan cities with accurate geographic coordinates.
  - Seed the **4 mandatory demo accounts**.
  - Generate 20 realistic trips (future trips, fully booked trips, and completed trips with reviews).
- [ ] **T-104: EasyAdmin 4 Setup (Screen S12)**
  - Install `easycorp/easyadmin-bundle`.
  - Centralized `/admin` dashboard protected by `ROLE_ADMIN`.
  - CRUD Controllers: `CityCrudController`, `UserCrudController`, `VehicleCrudController`, `TripCrudController`, `BookingCrudController`, `ReviewCrudController`.
- [ ] **T-105: Domain Documentation (`docs/MODELE.md`)**
  - Comprehensive Mermaid ER diagram.
  - Architectural justification for the atomic `TripSegment` model solving the sub-segment booking challenge.

---

### 🔹 Sprint 2: Level 2 — Business Rules, Concurrency & Automated Tests (Day 3)
> **Goal:** Enforce data integrity, eliminate race conditions via database locks, and achieve full PHPUnit test coverage.

- [ ] **T-201: Segment Inventory & Booking Engine**
  - Create the `BookingService` domain service.
  - Segment overlap check: Booking from stop $i$ to stop $j$ validates that all atomic segments $[i, i+1], ..., [j-1, j]$ satisfy `availableSeats >= requestedSeats`.
- [ ] **T-202: Concurrency Control & Pessimistic Locking**
  - Wrap the booking logic inside `$entityManager->wrapInTransaction()`.
  - Execute pessimistic write locks (`LockMode::PESSIMISTIC_WRITE` / `SELECT ... FOR UPDATE`) on target `TripSegment` entities.
  - Decrement seats atomically; trigger automatic transaction rollback if capacity is exceeded.
- [ ] **T-203: Distinct Cancellation Policies**
  - **Passenger Cancellation**: Immediate seat release across booked segments, update status to `CANCELLED_PASSENGER`. Implement threshold rules (>24h: 100% refund, <24h: 50%).
  - **Driver Cancellation**: Cascading cancellation of all related passenger bookings with status `CANCELLED_DRIVER` and notifications.
- [ ] **T-204: Review Eligibility Guardrails**
  - Enforce prerequisite conditions: Trip must be `COMPLETED`, booking must be `CONFIRMED`, and limit to 1 review per participant per trip.
- [ ] **T-205: Automated Test Suite with PHPUnit (`tests/`)**
  - `SegmentOverlapTest`: Unit test validating available seat calculations across multi-leg journeys.
  - `BookingConcurrencyTest`: Integration test simulating concurrent requests competing for the last available seat.
  - `CancellationRulesTest`: Unit tests for passenger vs driver cancellation policies.
  - `ReviewEligibilityTest`: Security test validating review restrictions on upcoming or cancelled trips.

---

### 🔹 Sprint 3: Level 3 — Product Experience & 11 UI Screens (Day 4 - Day 5)
> **Goal:** Build an intuitive, responsive user experience (Tailwind + Stimulus) covering all passenger and driver flows.

- [ ] **T-301: Base Layout & Design System (`templates/base.html.twig`)**
  - Integrate Tailwind CSS (via AssetMapper) and Lucide Icons.
  - Clean dual-role navigation bar (*Find a Ride*, *Publish a Ride*, *My Bookings*, *Driver Space*, *My Account*).
  - Business-oriented flash messages (success, capacity warnings, booking errors).
- [ ] **T-302: Screen S01 — Home & Search Experience (`/`)**
  - Instant search form: origin, destination, date, seats.
  - Search results display: matched segment badges, remaining seats, and segment price.
- [ ] **T-303: Screen S02 — Trip Details & Segment Booking (`/trips/{id}`)**
  - Visual timeline of stops and departure times.
  - Interactive boarding/drop-off stop selector with dynamic pricing and seat count updates.
  - Map container for Leaflet/OSRM integration.
- [ ] **T-304: Screen S03 — Simulated Checkout (`/bookings/{id}/checkout`)**
  - Clean summary, policy reminders, and one-click transactional booking trigger.
- [ ] **T-305: Screen S04 — Passenger Dashboard (`/my/bookings`)**
  - *Upcoming* and *Past* tabs.
  - Cancellation modal with policy reminders.
  - Dynamic "Leave a Review" button when the trip status is `COMPLETED`.
- [ ] **T-306: Screen S05 — Publish a Trip (`/driver/trips/new`)**
  - Stimulus controller for dynamically adding and removing intermediate stops without page reloads.
  - Vehicle selector and default price inputs.
- [ ] **T-307: Screens S06 & S07 — Driver Space & Manifest (`/driver/trips`)**
  - S06: Trip list with quick-action "Mark as Completed" button to streamline jury testing.
  - S07: Clear manifest showing passenger boardings and exits per stop with contact information.
- [ ] **T-308: Screens S08 & S09 — Public Profiles & Review System (`/users/{id}`, `/trips/{id}/review`)**
  - S08: Public profile with calculated average ratings, verified driver badges, and review list.
  - S09: 5-star rating form with commentary, guarded by `ReviewVoter`.
- [ ] **T-309: Screen S10 — Account & Vehicle Management (`/account`)**
  - Profile settings and vehicle management (enables any user to register a car and publish trips).
- [ ] **T-310: Screen S11 — Authentication & 1-Click Demo Login (`/login`)**
  - Standard Symfony login form.
  - **Jury Quick-Test Box**: 4 buttons that prefill credentials for `Admin`, `Amina (Dual)`, `Karim (Driver)`, and `Youssef (Passenger)`.

---

### 🔹 Sprint 4: Level 4 — External APIs (Nominatim + OSRM) & Dynamic Pricing (Day 6)
> **Goal:** Power the platform with OpenStreetMap geocoding, real OSRM road routing, and automated suggested pricing.

- [ ] **T-401: Nominatim Geocoding Service (`NominatimService`)**
  - Symfony HttpClient integration querying Nominatim API.
  - Filesystem/Redis caching layer for coordinates to strictly respect OSM usage policies.
- [ ] **T-402: OSRM Routing Service (`OsrmService`)**
  - Call OSRM API (`/route/v1/driving/`) with stop sequences.
  - Retrieve accurate road distance in kilometers, estimated duration, and GeoJSON polyline.
  - Resilient fallback handling in case of network timeouts.
- [ ] **T-403: Suggested Pricing Calculator (`PricingService`)**
  - Transparent pricing formula: $Price = BaseCost + (Distance_{km} \times Rate_{km}) \times DegressiveFactor$.
  - Automated breakdown of suggested pricing across all intermediate legs.
- [ ] **T-404: Front-End Interactive Map Integration (Leaflet.js)**
  - Stimulus controller rendering the map on trip publish (S05) and trip details (S02).
  - Draw the real OSRM route geometry and place custom markers for all stops.

---

### 🔹 Sprint 5: Level 5, Deliverables & AI Traces Bonus (+15 pts) (Day 7)
> **Goal:** Finalize the turnkey Docker deployment, compile documentation, write the 1-page concept note, and package AI traces.

- [ ] **T-501: Automated Turnkey Docker Deployment (Level 5)**
  - Automated container startup running migrations and fixtures in a single command:
    ```bash
    docker compose up -d
    ```
  - Verify complete fresh installation on a clean machine.
- [ ] **T-502: Step-by-Step Installation Guide (`INSTALL.md`)**
  - Clear prerequisites (Docker & Docker Compose).
  - Exact startup commands.
  - Table of demo accounts and passwords.
  - Automated test execution commands.
- [ ] **T-503: Architectural Decision Records (`docs/DECISIONS.md`)**
  - Document clear answers to the challenge's "Questions to Decide".
  - Architectural justification: *2 Security Roles + Contextual Voters*.
  - Concurrency design rationale: *Pessimistic Locking vs Optimistic Locking*.
- [ ] **T-504: 1-Page Conceptual Note PDF (`note_conceptuelle.pdf`)**
  - Executive 6-section summary: Subject choice, requirement decomposition, architectural decisions, execution order, AI steering methodology, and +1 week roadmap.
  - Clean 1-page PDF export.
- [ ] **T-505: AI Traces Archive (`traces-ia.zip`) & `LISEZMOI.md` (+15 pts Bonus)**
  - Export AI Studio chats (Gemini Hydrogen) and Antigravity logs.
  - Complete `LISEZMOI.md` documenting tools used, archive contents, and **3 key learnings** (time saved, AI hallucinations/errors, and engineering corrections applied).
  - Sanitize and remove all personal tokens, passwords, and API keys.
- [ ] **T-506: Final Git Repository Review & Submission Preparation**
  - Verify clean, linear commit history on `main` branch (no squashing or forced rebase).
  - Tag release `v1.0.0`.
  - Draft formal submission email to `pfe@educationmedia.ma`.

---

## 📈 Weekly Sprint Tracking Dashboard

| Day | Primary Sprint | Key Deliverable | Status |
|:---|:---|:---|:---:|
| **Day 1** | Sprint 0 & 1 | Operational Docker + Symfony 7.4 Skeleton + Domain Entities | ⏳ In Progress |
| **Day 2** | Sprint 1 | Moroccan Seed Data + Functional EasyAdmin Back-Office | ⏳ Pending |
| **Day 3** | Sprint 2 | `PESSIMISTIC_WRITE` Concurrency + Passing PHPUnit Tests | ⏳ Pending |
| **Day 4** | Sprint 3 | Passenger Flow (Search, Details, Checkout, Bookings) | ⏳ Pending |
| **Day 5** | Sprint 3 | Driver Flow + Public Profiles + Verified Reviews | ⏳ Pending |
| **Day 6** | Sprint 4 | OSRM & Nominatim Integration + Leaflet Route Map | ⏳ Pending |
| **Day 7** | Sprint 5 | `INSTALL.md`, 1-Page PDF Note, AI Traces & Final Submission | ⏳ Pending |
