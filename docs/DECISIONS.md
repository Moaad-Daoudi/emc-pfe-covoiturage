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

### ADR-002: Pessimistic Database Locking for Seat Concurrency (PESSIMISTIC_WRITE)
- **Decision:** Enforce `LockMode::PESSIMISTIC_WRITE` (`SELECT ... FOR UPDATE`) with `Query::HINT_REFRESH => true` on `Trip` and `TripSegment` rows inside an ACID transaction via `$entityManager->wrapInTransaction()`.
- **Comparison vs. Optimistic Locking (`@Version`):**
  - *Optimistic Locking:* In peaks (e.g. holiday departures or last-minute trips), competing users get an `OptimisticLockException` and a failed screen, forcing manual page reloads and retries.
  - *Pessimistic Locking:* InnoDB queues concurrent requests row-by-row. The first request takes the seat and commits; the second request waits cleanly, reads the updated `availableSeats = 0`, and gracefully receives a clear business message (`SeatsNotAvailableException`) without data corruption.
- **Doctrine Identity Map Stale Data Guard (`Query::HINT_REFRESH`):**
  - If a `TripSegment` was already loaded into Doctrine's memory earlier in the request lifecycle (e.g. in a controller), executing `SELECT ... FOR UPDATE` without `HINT_REFRESH` would lock the row in MySQL but retain stale entity state in PHP memory. Passing `Query::HINT_REFRESH => true` forces Doctrine to immediately overwrite in-memory state with freshly locked DB values.
- **Deadlock Elimination (Dijkstra Resource Ordering):**
  - Segments are always queried and locked with `ORDER BY s.segmentOrder ASC`. Since every concurrent transaction requests locks in the exact same spatial order, circular wait (Coffman condition) is mathematically eliminated.
- **Driver Cancellation Race Condition Guard:**
  - The `$trip` aggregate root itself is locked (`PESSIMISTIC_WRITE`) and refreshed (`$em->refresh($trip)`) inside the transaction to prevent a passenger booking while the driver cancels the trip simultaneously.

### ADR-003: Multi-Segment Data Modeling (Timeline vs Naive Point-to-Point)
- **Decision:** Split trips into an ordered sequence of stops (`TripStop` with `stopOrder`, `meetingPoint`, `estimatedArrivalAt`) and an adjacent chain of atomic segments (`TripSegment` with `segmentOrder`, `fromStop`, `toStop`, `distanceKm`, `price`, `availableSeats`).
- **Rationale:** 
  - A naive origin-destination model fails to handle passengers embarking and disembarking at intermediate regional hubs (e.g., Agadir $\rightarrow$ Essaouira $\rightarrow$ Marrakech $\rightarrow$ Casablanca).
  - The atomic segment pattern allows passengers to book sub-trips of arbitrary span $[i, j]$.
  - Bottleneck capacity computation: For any requested sub-trip spanning segments from $i$ to $j$, the available capacity is strictly:
    $$\text{Capacity}(i, j) = \min_{k=i}^{j-1} (\text{availableSeats}_k)$$
  - When booked, seats are decremented only on the traversed segments $k \in [i, j-1]$, leaving preceding and succeeding segments fully available for other passengers.

### ADR-004: OSRM Routing & Nominatim Geocoding Resilient Hybrid Architecture
- **Decision:** Implement dedicated Symfony services (`OsrmService`, `NominatimService`) backed by Symfony `HttpClientInterface`, 30-day PSR-6 cache (`CacheInterface`), and an offline fallback dictionary/matrix.
- **Rationale:**
  - **OpenStreetMap Nominatim Policy Compliance:** Strict custom `User-Agent: SafarCar-Morocco-PFE/1.0` header with 1 request/sec rate throttling and persistent caching to prevent IP bans.
  - **Zero-Downtime Guarantee:** External routing APIs may experience network timeouts or rate limits during jury evaluations. A built-in offline road network matrix covering major Moroccan motorways (A3, A7, N1) paired with Haversine geometric calculations guarantees uninterrupted service even without internet access.
  - **High Performance:** Repeat queries (e.g. popular Agadir $\rightarrow$ Marrakech route) resolve in $< 1\text{ ms}$ directly from memory cache.

### ADR-005: Regulatory Moroccan Fair Pricing Algorithm (Non-Commercial Compliance)
- **Decision:** Automated pricing engine (`PricingService`) strictly conforming to Moroccan non-commercial ride-sharing legal principles and ADM (Autoroutes du Maroc) toll rates.
- **Formula:**
  $$\text{Price} = \text{round\_to\_5}\left(\left(\text{BaseCost} + (\text{Distance}_{\text{km}} \times \text{Rate}_{\text{km}})\right) \times \text{DegressiveFactor} \times \text{ComfortMultiplier}\right)$$
- **Regulatory Ceiling:** Enforces an unbreachable ceiling of $0.65\text{ MAD/km}$ (`isPriceCompliant()`) to strictly prevent taxi-clandestin (commercial transport without license) classification under Moroccan transport regulations.
- **Multi-Segment Fair Proportions:** Intermediate hop prices are proportional to segment distance with a degressive factor for longer distances, ensuring transparent pricing with 0 DH hidden fees.
