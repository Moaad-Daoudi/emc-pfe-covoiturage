# ⚖️ Architectural Decision Records (Journal des Décisions) — Subject 3

**Project:** Regional Carpooling Platform (PFE Challenge EMC)  
**Candidate:** Moaad Daoudi  

---

## 1. Context & Architectural Principles
This log documents key technical trade-offs, domain rules, and answers to the challenge's "Questions to Decide" (*Questions à trancher*).

---

## 2. Answers to "Questions to Decide" (Questions à trancher)

### Q1: Can a seat released at an intermediate stop be resold for the remainder of the trip?
> **Decision: YES.**  
> **Rationale:** Thanks to the atomic `TripSegment` decomposition model, seats are tracked per hop between consecutive stops rather than globally per trip. If passenger A travels from Agadir to Marrakech and exits the vehicle, that physical seat is automatically marked available on the subsequent Marrakech $\rightarrow$ Casablanca segment. This maximizes vehicle occupancy and driver revenue.

### Q2: How is the price of a trip segment calculated?
> **Decision: Proportional degressive pricing based on real road distance (OSRM).**  
> **Rationale:** We use the real road distance returned by the OSRM routing engine. Long-distance segments apply a degressive factor so that the per-kilometer cost decreases slightly for longer distances, while shorter hops maintain a minimum viable fare. Drivers can either accept the algorithmically suggested price or adjust within defined bounds.

### Q3: What happens when a driver modifies a timetable or removes a stop after bookings have occurred?
> **Decision: Strict immutable stops once booked; notifications & cancellation options on schedule shifts.**  
> **Rationale:** A stop that has confirmed passenger bookings cannot be deleted without triggering a formal cancellation workflow (`CANCELLED_BY_DRIVER`). Minor schedule modifications (>30 mins) notify all booked passengers and allow free immediate cancellation with 100% refund.

### Q4: Can a passenger book multiple seats at once?
> **Decision: YES, up to the maximum available capacity on all target segments.**  
> **Rationale:** A single booking can request $K$ seats (e.g. for a family or group). The reservation engine atomically checks and decrements $K$ seats across every intermediate segment $[i, j]$. If any single sub-segment has fewer than $K$ seats available, the entire transaction fails gracefully.

### Q5: Who rates whom, and when?
> **Decision: Mutual post-trip reviews, strictly after trip completion.**  
> **Rationale:** 
> - A passenger can rate the driver, and a driver can rate confirmed passengers.
> - A review can ONLY be submitted once the trip status transitions to `COMPLETED`.
> - Enforced by a dedicated `ReviewVoter` checking that the author and recipient actually traveled together on a non-cancelled booking.

---

## 3. Technical Architectural Decisions

### ADR-001: 2 Security Roles + Contextual Symfony Voters
- **Decision:** Use `ROLE_USER` and `ROLE_ADMIN`, with domain capabilities enforced via `TripVoter`, `BookingVoter`, and `ReviewVoter`.
- **Rationale:** Satisfies the explicit requirement: *"one person can be both driver and passenger"*. Avoids rigid separate user tables or role switching.

### ADR-002: Pessimistic Database Locking for Seat Concurrency
- **Decision:** Enforce `LockMode::PESSIMISTIC_WRITE` on `TripSegment` rows inside `$entityManager->wrapInTransaction()`.
- **Rationale:** High-traffic popular routes can experience simultaneous booking requests for the last remaining seat. In-memory PHP checks are vulnerable to race conditions; database row locks guarantee strict ACID isolation.
