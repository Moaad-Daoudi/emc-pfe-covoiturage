# 📐 Data Domain Model (Modèle de Données) — Subject 3: Regional Carpooling

This document provides the complete technical and architectural specification of the relational data domain model for the **SafarCar** regional carpooling platform, fulfilling all requirements of **Level 1 (Method: 10 points)**.

---

## 1. UML Class Diagram (PlantUML)

This diagram is authored using **PlantUML** (source: [`docs/diagrams/class_diagram.puml`](diagrams/class_diagram.puml)) and compiled via the official `plantuml.jar` engine:

![Diagramme de Classes UML (PlantUML)](images/class_diagram.png)

*(A vector SVG version is also available at [`docs/images/class_diagram.svg`](images/class_diagram.svg) for lossless rendering in reports and print documents).*

---

## 2. Relational Entity Relationship Diagram (Mermaid)

```mermaid
erDiagram
    USER ||--o{ VEHICLE : "owns (1:N)"
    USER ||--o{ TRIP : "drives (1:N)"
    USER ||--o{ BOOKING : "reserves as passenger (1:N)"
    USER ||--o{ REVIEW : "authors (1:N)"
    USER ||--o{ REVIEW : "receives as target (1:N)"
    
    TRIP ||--|{ TRIP_STOP : "contains ordered stops (1:N CASCADE)"
    TRIP ||--|{ TRIP_SEGMENT : "decomposes into atomic segments (1:N CASCADE)"
    TRIP ||--o{ BOOKING : "receives bookings (1:N)"
    TRIP ||--o{ REVIEW : "reviewed in (1:N)"
    
    CITY ||--o{ TRIP : "departure city (1:N)"
    CITY ||--o{ TRIP : "arrival city (1:N)"
    CITY ||--o{ TRIP_STOP : "stop location (1:N)"
    
    VEHICLE ||--o{ TRIP : "used for (1:N)"
    
    TRIP_STOP ||--o{ TRIP_SEGMENT : "departure of segment (from_stop)"
    TRIP_STOP ||--o{ TRIP_SEGMENT : "arrival of segment (to_stop)"
    TRIP_STOP ||--o{ BOOKING : "boarding stop"
    TRIP_STOP ||--o{ BOOKING : "alighting stop"
    
    BOOKING ||--|{ BOOKING_SEGMENT : "locks seats across (1:N CASCADE)"
    TRIP_SEGMENT ||--o{ BOOKING_SEGMENT : "reserved by (1:N)"
    
    USER {
        int id PK
        string email UK "VARCHAR(180) UNIQUE"
        json roles "JSON"
        string password "VARCHAR(255) Argon2id/Sodium"
        string firstname "VARCHAR(80)"
        string last_name "VARCHAR(80)"
        string phone "VARCHAR(25) nullable"
        text bio "TEXT nullable"
        string avatar "VARCHAR(255) nullable"
        datetime created_at "DATETIME_IMMUTABLE"
    }
    
    VEHICLE {
        int id PK
        int owner_id FK "REFERENCES user(id)"
        string brand "VARCHAR(60)"
        string model "VARCHAR(60)"
        string color "VARCHAR(40)"
        string license_plate "VARCHAR(30)"
        int seats "INT"
        boolean air_conditioned "TINYINT(1)"
    }
    
    CITY {
        int id PK
        string name UK "VARCHAR(100) UNIQUE"
        string region "VARCHAR(100) nullable"
        float latitude "DOUBLE"
        float longitude "DOUBLE"
    }
    
    TRIP {
        int id PK
        int driver_id FK "REFERENCES user(id)"
        int vehicle_id FK "REFERENCES vehicle(id)"
        int departure_city_id FK "REFERENCES city(id)"
        int arrival_city_id FK "REFERENCES city(id)"
        datetime departure_at "DATETIME_IMMUTABLE"
        int total_seats "INT"
        int price_total "INT (MAD)"
        boolean luggage_allowed "TINYINT(1)"
        string status "VARCHAR(20) ENUM"
        text route_geometry "TEXT nullable (OSRM Polyline/GeoJSON)"
        float distance_km "DOUBLE nullable"
        datetime created_at "DATETIME_IMMUTABLE"
    }
    
    TRIP_STOP {
        int id PK
        int trip_id FK "REFERENCES trip(id) ON DELETE CASCADE"
        int city_id FK "REFERENCES city(id)"
        int stop_order "INT"
        datetime estimated_arrival_at "DATETIME_IMMUTABLE"
        string meeting_point "VARCHAR(150) nullable"
    }
    
    TRIP_SEGMENT {
        int id PK
        int trip_id FK "REFERENCES trip(id) ON DELETE CASCADE"
        int from_stop_id FK "REFERENCES trip_stop(id)"
        int to_stop_id FK "REFERENCES trip_stop(id)"
        int segment_order "INT"
        int available_seats "INT"
        int price "INT (MAD)"
        float distance_km "DOUBLE nullable"
    }
    
    BOOKING {
        int id PK
        int passenger_id FK "REFERENCES user(id)"
        int trip_id FK "REFERENCES trip(id)"
        int boarding_stop_id FK "REFERENCES trip_stop(id)"
        int alighting_stop_id FK "REFERENCES trip_stop(id)"
        int seats_booked "INT"
        int total_price "INT (MAD)"
        int refund_amount "INT nullable (MAD)"
        string status "VARCHAR(30) ENUM"
        datetime created_at "DATETIME_IMMUTABLE"
        datetime cancelled_at "DATETIME_IMMUTABLE nullable"
    }
    
    BOOKING_SEGMENT {
        int id PK
        int booking_id FK "REFERENCES booking(id) ON DELETE CASCADE"
        int segment_id FK "REFERENCES trip_segment(id)"
        int seats_count "INT"
    }
    
    REVIEW {
        int id PK
        int author_id FK "REFERENCES user(id)"
        int target_id FK "REFERENCES user(id)"
        int trip_id FK "REFERENCES trip(id)"
        smallint rating "SMALLINT (1-5)"
        text comment "TEXT nullable"
        datetime created_at "DATETIME_IMMUTABLE"
    }
```

---

## 2. Architectural Deep-Dive: Atomic Segment Inventory Pattern

### 2.1 The Multi-Stop Sub-Segment Problem
In Moroccan inter-city carpooling (e.g., **Agadir $\rightarrow$ Essaouira $\rightarrow$ Safi $\rightarrow$ Casablanca**), trips rarely operate point-to-point. 

A naive data model that stores available seats as a single integer on the `Trip` entity (`trip.available_seats`) fails immediately:
- If a passenger books **Agadir $\rightarrow$ Essaouira**, decrementing `trip.available_seats` would falsely block another passenger from booking **Safi $\rightarrow$ Casablanca**, even though the car will be completely empty between Safi and Casablanca.
- Conversely, storing independent bookings without segment breakdown leads to severe overbooking or under-utilization of the vehicle fleet.

### 2.2 Mathematical Formalization of the Atomic Segment Solution
Let Trip $T$ have an ordered sequence of $N$ intermediate stops:
$$\mathcal{S} = (s_0, s_1, s_2, \dots, s_{N-1})$$

The trip is automatically decomposed into $N - 1$ **consecutive atomic segments**:
$$\Sigma = (\sigma_0, \sigma_1, \dots, \sigma_{N-2})$$
where each segment $\sigma_k$ connects stop $s_k$ to stop $s_{k+1}$.

#### Example: Agadir $\rightarrow$ Essaouira $\rightarrow$ Safi $\rightarrow$ Casablanca (4 stops, 3 segments)
| Stop Index | City | Atomic Segment | Segment Order | From $\rightarrow$ To |
|:---|:---|:---|:---:|:---|
| $s_0$ | Agadir | $\sigma_0$ | 0 | Agadir $\rightarrow$ Essaouira |
| $s_1$ | Essaouira | $\sigma_1$ | 1 | Essaouira $\rightarrow$ Safi |
| $s_2$ | Safi | $\sigma_2$ | 2 | Safi $\rightarrow$ Casablanca |
| $s_3$ | Casablanca | — | — | *Final Destination* |

#### Reservation Projection Across Segments
When a passenger requests a booking $B$ from boarding stop $s_i$ to alighting stop $s_j$ with $0 \le i < j \le N-1$:
1. The booking spans the subset of contiguous segments:
   $$\Sigma(B) = \{\sigma_k \mid i \le k < j\}$$
2. **Availability Check (Precondition):**
   The booking is valid if and only if every segment in $\Sigma(B)$ has sufficient capacity:
   $$\min_{\sigma \in \Sigma(B)} (\text{availableSeats}(\sigma)) \ge \text{seatsBooked}(B)$$
3. **Capacity Decrement:**
   Upon confirmation of booking $B$:
   $$\forall \sigma \in \Sigma(B): \quad \text{availableSeats}(\sigma) \leftarrow \text{availableSeats}(\sigma) - \text{seatsBooked}(B)$$
4. **Independent Re-use of Empty Segments:**
   A passenger traveling $s_0 \rightarrow s_1$ and another traveling $s_2 \rightarrow s_3$ occupy disjoint sets of segments ($\{\sigma_0\} \cap \{\sigma_2\} = \emptyset$), enabling 100% vehicle capacity reuse without conflict!

---

## 3. Concurrency Control & Database Locking Strategy

### 3.1 Eliminating Race Conditions
When two users concurrently attempt to book the last remaining seat on overlapping segments (e.g., User A books Agadir $\rightarrow$ Casablanca while User B books Essaouira $\rightarrow$ Safi), a classic race condition occurs if read and write operations are not synchronized.

### 3.2 Pessimistic Write Lock (`SELECT ... FOR UPDATE`)
The platform enforces ACID guarantees using Doctrine's pessimistic write locking:
```php
$entityManager->wrapInTransaction(function() use ($trip, $fromStop, $toStop, $seats) {
    // 1. Fetch traversed segments ordered by segmentOrder ASC
    $segments = $tripSegmentRepository->findSegmentsForPath($trip, $fromStop, $toStop);

    // 2. Lock each segment row in MySQL (SELECT ... FOR UPDATE)
    foreach ($segments as $segment) {
        $entityManager->lock($segment, LockMode::PESSIMISTIC_WRITE);
        
        // 3. Verify real-time invariant inside lock
        if ($segment->getAvailableSeats() < $seats) {
            throw new InsufficientSeatsException("Seat no longer available on " . $segment);
        }
    }

    // 4. Safely decrement seats and persist Booking + BookingSegment
    foreach ($segments as $segment) {
        $segment->setAvailableSeats($segment->getAvailableSeats() - $seats);
    }
});
```

### 3.3 Deadlock Prevention Guarantee
To mathematically eliminate deadlocks between concurrent multi-segment transactions, **all locks are strictly acquired in ascending order of `segment_order`** (`ORDER BY segment_order ASC`). This satisfies Dijkstra’s resource hierarchy condition: no circular wait can ever form.

---

## 4. Domain Enums & State Transitions

### 4.1 Trip Status Lifecycle (`TripStatus`)
```mermaid
stateDiagram-v2
    [*] --> SCHEDULED : Driver publishes trip
    SCHEDULED --> ACTIVE : Departure time reached
    ACTIVE --> COMPLETED : Driver marks trip as finished
    SCHEDULED --> CANCELLED : Driver cancels trip
    ACTIVE --> CANCELLED : Force cancellation (Admin)
    COMPLETED --> [*] : Reviews unlocked
    CANCELLED --> [*] : All bookings refunded
```

- `SCHEDULED`: Trip is visible in search results, available for booking.
- `ACTIVE`: Journey is currently in progress.
- `COMPLETED`: Journey concluded. Post-trip reviews are now unlocked.
- `CANCELLED`: Trip was cancelled by driver or administrator. All associated bookings transition to `CANCELLED_DRIVER`.

### 4.2 Booking Status Lifecycle (`BookingStatus`)
```mermaid
stateDiagram-v2
    [*] --> CONFIRMED : Instant booking checkout
    CONFIRMED --> CANCELLED_PASSENGER : Passenger cancels
    CONFIRMED --> CANCELLED_DRIVER : Driver cancels trip
    CONFIRMED --> [*] : Trip marked COMPLETED
    CANCELLED_PASSENGER --> [*] : Seats released immediately
    CANCELLED_DRIVER --> [*] : 100% refund processed
```

- `CONFIRMED`: Seats reserved and decremented on all spanned atomic segments.
- `CANCELLED_PASSENGER`: Passenger cancelled. Seats immediately released (`availableSeats += seatsBooked`). Refund percentage applied based on departure window (>24h: 100%, <24h: 50%).
- `CANCELLED_DRIVER`: Driver cancelled the trip. 100% refund guaranteed to passenger.

---

## 5. Unique Constraints & Database Indexes

| Table | Constraint / Index Name | Columns | Purpose |
|:---|:---|:---|:---|
| `user` | `UNIQ_IDENTIFIER_EMAIL` | `email` | Guarantees uniqueness of user logins. |
| `city` | `UNIQ_CITY_NAME` | `name` | Prevents duplicate Moroccan city records. |
| `trip_stop` | `uniq_trip_stop_order` | `trip_id, stop_order` | Guarantees sequential order of stops within a trip. |
| `review` | `uniq_review_author_target_trip` | `author_id, target_id, trip_id` | **Anti-Spam Invariant:** Guarantees a user can review a participant at most once per trip. |
| `trip` | `idx_trip_departure_status` | `departure_city_id, status, departure_at` | High-performance index for homepage trip search. |
| `trip_segment` | `idx_segment_trip_order` | `trip_id, segment_order` | High-performance index for segment path traversal and locking. |

---

## 6. Business Invariants Summary

1. **Self-Booking Prohibition:** A driver can never book a seat on their own published trip (enforced by `TripVoter` & `BookingService`).
2. **Atomic Inventory Integrity:** At all times, for any segment $\sigma$:
   $$0 \le \text{availableSeats}(\sigma) \le \text{totalSeats}(T)$$
3. **Review Eligibility:** A review can only be submitted if:
   - Trip status is `COMPLETED`.
   - The reviewer was a confirmed participant (`CONFIRMED`).
   - Reviewer has not already submitted a review for that target user on that trip.
4. **Immediate Seat Reclamation:** Whenever a booking transitions to `CANCELLED_PASSENGER`, seats are re-credited to `TripSegment.availableSeats` in the same atomic transaction.
