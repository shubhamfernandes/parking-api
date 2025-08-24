# Parking API (Laravel 10, PHP 8.2)

A small, production-quality REST API for an airport parking service. It exposes per‑day **availability**, **pricing**, and a **booking lifecycle** (create → amend → cancel) with correct money handling, idempotency, and concurrency safety.

---


## Overview

- **Availability**: For any `from_date` (drop-off date) to `to_datetime` (pick-up datetime), the API returns per‑day capacity, booked count and remaining spaces. The checkout day is **excluded** (interval is **[from, to)**).
- **Pricing**: Price is computed per occupied day using a fixed matrix of **season** (summer/winter) × **day type** (weekday/weekend). Totals are returned as **integers in minor units** and as a `Brick\Money\Money` string.
- **Bookings**: Create validates capacity & price atomically; Amend re-checks capacity & re-prices; Cancel frees capacity and clears the idempotency fingerprint.
- **Safety**: Idempotent create via a fingerprint; pessimistic row locks to prevent race conditions when days fill up; ULIDs for IDs; immutable dates throughout.

---


## Money & Currency

- Uses **[brick/money]** to avoid floating-point errors.
- All arithmetic is done in **minor units** (e.g., pence). `total_minor` is stored as integer.
- API exposes both:
  - `total_minor` *(int)*
  - `total` *(string)*, e.g. `"GBP 35.00"`
- Currency is a 3-letter code (defaults to `GBP` via config/env).

---

## Seasons, Rates & Default Season

- **Rates** are defined per season & day type:
  - Summer: `weekday=1500`, `weekend=2000` (minor units)
  - Winter: `weekday=1200`, `weekend=1600`
- **Month mapping** (1..12):
  - `summer_months = [6, 7, 8]`
  - `winter_months = [12, 1, 2]`
- **Default season** for months not listed (Mar–May, Sep–Nov) is **winter** by design.
  This is documented and can be extended later (e.g., adding a “standard” band).
  (config.pricing,config.parking)
---

## Capacity & Availability

- Default capacity per day is `parking.capacity` (env `PARKING_CAPACITY`, default 10).
- A `capacities` row (if present) overrides the default for that specific calendar day.
- `AvailabilityService::calendar(range)` returns a collection of:
  ```json
  {
    "date": "YYYY-MM-DD",
    "capacity": <int>,
    "booked": <int>,
    "available": <int>
  }
  ```
- Occupancy counts **only active** bookings.
- The **checkout day is excluded**: the interval is `[from_date, to_datetime)`.

---

## Booking Lifecycle

### Create
- Validates request (dates, max stay, etc.).
- Builds a `DateRange` (immutable) and computes:
  - **Idempotency fingerprint** from `email + normalized vehicle reg + from + to`. If a booking with the same fingerprint exists, returns **409**.
  - **Duplicate-active rule**: same user + same car cannot have another **active** booking.
- In a **transaction**:
  - `AvailabilityService::assertRangeHasSpace(range)` with row locks.
  - `PricingService::quote(range)` for totals.
  - Create `Booking` (mutators normalize email/reg; `reference` and `status` auto-set).
  - Create `booking_days` rows for each occupied day.

### Amend
- Builds new `DateRange`, re-checks capacity (ignoring the current booking’s own days), re‑prices, bumps `version`, and re-syncs `booking_days`.


### Cancel
- If already cancelled → **422**.
- Otherwise: set `status=cancelled`, **clear `request_fingerprint`** so the exact same payload can be used to re-create later. Cascade deletes do **not** remove the booking; it remains as a cancelled record.

---

## Idempotency, Concurrency & Locking

- **Idempotency**: unique `request_fingerprint` column ensures duplicate payloads are rejected (**409**), even under race conditions.
- **Pessimistic locking**: `assertRangeHasSpace` creates/locks the `capacities` row for each day and locks `booking_days` rows during counting to serialize writers safely.
- **Lock order**: days are sorted to reduce deadlocks when multiple days are touched.

---

## Validation Rules

- Request validation via `FormRequest`s:
  - `QuoteAvailabilityRequest`: shared by **availability** and **pricing** endpoints.
    - `from_date` required, date, **today or future**
    - `to_datetime` required, date, **after from_date**, within the next year
  - `BookingUpdateRequest` (for amend) uses the same date rules and the custom rule below.
- Custom rule: **`MaxStayDays`**
  - Computes nights as the number of days in `[from, to)`.
  - Fails if `nights > parking.max_stay_days` (env `PARKING_MAX_STAY_DAYS`, default 10).

---

## Configuration

- **`config/pricing.php`**
  - `currency` (env `PRICING_CURRENCY`, default `GBP`)
  - `rates` matrix for summer/winter × weekday/weekend (minor units)
  - `summer_months`, `winter_months`
- **`config/parking.php`**
  - `capacity` (env `PARKING_CAPACITY`, default `10`)
  - `max_stay_days` (env `PARKING_MAX_STAY_DAYS`, default `10`)


---

## Service Architecture (Interfaces & Implementations)

- **Contracts (interfaces)** define stable shapes and make services swappable in tests:
  - `PricingServiceInterface::quote(DateRange): array`
  - `AvailabilityServiceInterface::calendar(DateRange): Collection` and `assertRangeHasSpace(DateRange, ?ignoreId)`
  - `BookingServiceInterface` (`create`, `amend`, `cancel`)

- **Concrete services**
  - `PricingService`: pure function from `DateRange` → items + total (uses rates + season map).
  - `AvailabilityService`: reads capacity overrides & booked counts; enforces space with row locks.
  - `BookingService`: orchestrates the lifecycle inside transactions.

- **Bindings (`AppServiceProvider`)**
  - Binds interfaces to singletons; normalizes config; injects dependencies.

### Value Objects

- `DateRange (final readonly)`
  - `fromDate: CarbonImmutable (00:00)` and `toDateTime: CarbonImmutable`
  - Generator `eachOccupiedDay()` yields **date strings** for every day in `[from, to)`.

### Resources (API serializers)

- `AvailabilityResource` → `range`, `all_days_have_space`, `per_day[]`
- `PriceQuoteResource` → `currency`, `total_minor`, `total`, `breakdown[]`
- `BookingResource` → booking fields, totals, and optional `days[]` if relation loaded

---

## HTTP API

All routes are prefixed with `/api/v1`.

### GET `/availability`
**Query:** `from_date=YYYY-MM-DD`, `to_datetime=YYYY-MM-DDTHH:MM:SS`  
**Response:**
```json
{
  "range": { "from_date": "...", "to_datetime": "..." },
  "all_days_have_space": true,
  "per_day": [
    { "date": "2025-08-22", "capacity": 10, "booked": 2, "available": 8 }
  ]
}
```

### GET `/price`
**Query:** `from_date`, `to_datetime`  
**Response:**
```json
{
  "currency": "GBP",
  "total_minor": 3500,
  "total": "GBP 35.00",
  "breakdown": [
    { "date": "2025-08-22", "season": "summer", "day_type": "weekday", "amount_minor": 1500 }
  ]
}
```

### POST `/bookings`
**Body:**
```json
{
  "customer_name": "Alex",
  "customer_email": "alex@example.com",
  "vehicle_reg": "AB12 CDE",
  "from_date": "2025-08-22",
  "to_datetime": "2025-08-23T09:00:00"
}
```
**Responses:**
- **201**: booking resource
- **409**: duplicate active booking for same user+car, or idempotent re‑submit. cancel a canceled booking
- **422**: validation error,amend a cancelled booking

### GET `/bookings/{id}` → booking resource

### PUT `/bookings/{id}` → amend (re-check capacity, re-price, bump version)

### DELETE `/bookings/{id}` → cancel (non‑idempotent; 422 if already cancelled)

---

## Testing & Postman

- **PHPUnit**: feature & unit tests cover availability math, pricing, lifecycle, idempotency, max-stay, and DST sanity.




## Design Decisions & Assumptions

- **Interval** is `[from, to)`: checkout day excluded everywhere (availability & pricing) for consistency.
- **Default season for shoulder months** is **winter** (explicit, documented).
- **Idempotency** guards client retries and double-clicks.
- **ULIDs** for compact, sortable IDs.
- **Immutable time** (`CarbonImmutable`) to avoid accidental mutation bugs.
- **Validation**: max stay applies to create, amend, and quote (recommended).
- **Normalization**: emails lowercased/trimmed; vehicle regs uppercased with spaces collapsed; a normalized field is used for fast lookups.

---


# Clone or unpack the repository
git clone <repo-url> parking-api
cd parking-api

# 1. Copy environment
cp .env.example .env

# 2. Start services
docker compose up -d --build

# 3. Install dependencies FIRST
docker compose run --rm app composer install --no-interaction

# 4. Generate key
docker compose run --rm app php artisan key:generate

# 5. Database setup
docker compose run --rm app php artisan migrate 


# Enter the container
docker compose exec app bash

