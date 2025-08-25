# Parking API (Laravel 10, PHP 8.2)

A small, production-quality REST API for an airport parking service. It exposes per‑day **availability**, **pricing**, and a **booking lifecycle** (create → amend → cancel) with correct money handling, idempotency, and concurrency safety.

---

## Overview

- **Availability**: For any `from_date` (drop-off date) to `to_datetime` (pick-up datetime), the API returns per‑day capacity, booked count and remaining spaces. The checkout day is **excluded** (interval is **[from, to)**).
- **Pricing**: Price is computed per occupied day using a fixed matrix of **season** (summer/winter) × **day type** (weekday/weekend). Totals are returned as **integers in minor units** and as a `Brick\Money\Money` string.
- **Bookings**: Create validates capacity & price atomically; Amend re-checks capacity & re-prices; Cancel frees capacity and clears the idempotency fingerprint.
- **Safety**: Idempotent create via a fingerprint; pessimistic row locks to prevent race conditions; ULIDs for IDs; immutable dates throughout.

---

## Money & Currency

- Uses **brick/money** to avoid floating-point errors.
- Arithmetic is done in **minor units** (e.g., pence). `total_minor` is stored as an integer.
- API exposes both:
  - `total_minor` *(int)*
  - `total` *(string)*, e.g. `"GBP 35.00"`
- Currency is a 3-letter code (defaults to `GBP` via config/env).

---

## Seasons, Rates & Default Season

- **Rates** (minor units):
  - Summer: `weekday=1500`, `weekend=2000`
  - Winter: `weekday=1200`, `weekend=1600`
- **Month mapping**:
  - `summer_months = [6, 7, 8]`
  - `winter_months = [12, 1, 2]`
- **Default season** for other months (Mar–May, Sep–Nov) is **winter** (explicit; extendable).

---

## Capacity & Availability

- Default capacity per day is `parking.capacity` (env `PARKING_CAPACITY`, default **10**).
- A `capacities` row (if present) overrides the default for that specific calendar day.
- `AvailabilityService::calendar(range)` returns:
  ```json
  {
    "date": "YYYY-MM-DD",
    "capacity": <int>,
    "booked": <int>,
    "available": <int>
  }
  ```
- Occupancy counts **only active** bookings.
- The **checkout day is excluded**: `[from_date, to_datetime)`.

---

## Booking lifecycle

### Create
- Validates request (dates, max stay, etc.).
- Builds a `DateRange` (immutable) and computes:
  - **Idempotency fingerprint** from `email + normalized vehicle reg + from + to`.  
    If a booking with the same fingerprint exists **and is active** → **409**.
  - **Duplicate/overlap rule (vehicle-only)**: if the **same vehicle** already has an **active** booking that **overlaps** `[from, to)`, reject with **409**.
- In a **transaction**:
  - `AvailabilityService::assertRangeHasSpace(range)` with row locks.
  - `PricingService::quote(range)` for totals.
  - Create `Booking` (mutators normalize email/reg; `reference` and `status` auto-set).
  - Create `booking_days` rows for each occupied day.

### Amend
- Rebuilds a `DateRange`, **re-checks capacity** (ignoring the current booking’s days), blocks **overlap** with any *other* active booking of the **same vehicle**, **re-prices**, **increments `version`**, and re-syncs `booking_days`.
- Controllers/services return a **fresh** model so responses reflect updated values.

### Cancel
- If already cancelled → **422**.
- Otherwise sets `status=cancelled` and **clears `request_fingerprint`** so the exact same payload can be reused later.

---

## Booking rules & restrictions (what users **cannot** do)

- **Double-book the same vehicle on overlapping dates** → **409**  
  (Overlap is true when `existing.from_date < new.to_datetime` **and** `existing.to_datetime > new.from_date`.)
- **Submit the exact same booking payload twice while it’s still active** → **409**  
  (Idempotency fingerprint blocks duplicate create. After **cancel**, the same payload is allowed again because the fingerprint is cleared.)
- **Amend to overlapping dates for the same vehicle** → **409**.
- **Amend a cancelled booking** → **422** (“Cannot amend a cancelled booking.”).
- **Cancel a booking twice** → **422** (“This booking is already cancelled.”).
- **Book past dates / inverted ranges / exceed max-stay** → **422** via validation.
- **Bypass capacity** → **409** (“No spaces available on YYYY-MM-DD”).

> **Note:** The **overlap rule is vehicle-only** (email is ignored). Email is normalized for storage and used in the **idempotency** fingerprint.

---

## Idempotency, Concurrency & Locking

- **Idempotency**: unique `request_fingerprint` guards retries/double-clicks (→ **409** when active).
- **Pessimistic locking**: `assertRangeHasSpace` locks the capacity row and the relevant `booking_days` while counting.
- **Lock order**: days are sorted to minimize deadlocks.

---

## Validation Rules

- Via `FormRequest`s:
  - `QuoteAvailabilityRequest` for availability & pricing:
    - `from_date` required, date, **today or future**
    - `to_datetime` required, date, **after from_date**, within next year
  - `BookingStoreRequest` / `BookingUpdateRequest` for create/amend (same date rules) + **MaxStayDays**.
- Custom **`MaxStayDays`** rule:
  - Nights are the number of days in `[from, to)`.
  - Fails if `nights > parking.max_stay_days` (env `PARKING_MAX_STAY_DAYS`, default **10**).

---

## Configuration

- **`config/pricing.php`**
  - `currency` (env `PRICING_CURRENCY`, default `GBP`)
  - summer/winter weekday/weekend `rates` (minor units)
  - `summer_months`, `winter_months`
- **`config/parking.php`**
  - `capacity` (env `PARKING_CAPACITY`, default `10`)
  - `max_stay_days` (env `PARKING_MAX_STAY_DAYS`, default `10`)

---

## Service architecture

- **Contracts** define stable shapes:
  - `PricingServiceInterface::quote(DateRange): array`
  - `AvailabilityServiceInterface::calendar(DateRange): Collection`, `assertRangeHasSpace(DateRange, ?ignoreId)`
  - `BookingServiceInterface` → `create`, `amend`, `cancel`
- **Concrete services**:
  - `PricingService`: pure function from `DateRange` → items + total.
  - `AvailabilityService`: capacity overrides, booked counts, locking.
  - `BookingService`: orchestrates lifecycle, idempotency, overlap rules.
- **Value object**: `DateRange (final readonly)` with generator `eachOccupiedDay()` over `[from, to)`.
- **Resources**: `AvailabilityResource`, `PriceQuoteResource`, `BookingResource`.

---

## HTTP API

All routes are prefixed with `/api/v1`.

### GET `/availability`
Query: `from_date=YYYY-MM-DD`, `to_datetime=YYYY-MM-DDTHH:MM:SS`  
Returns per-day capacity & counts.

### GET `/price`
Query: `from_date`, `to_datetime`  
Returns total and per-day breakdown.

### POST `/bookings`
Body:
```json
{
    "id": "01k3h521rdn26z8ha0vc2g2gk2",
    "reference": "BK-01K3H521RDN26Z8HA0VC2G2GK3",
    "status": "active",
    "customer_name": "Alex Dow",
    "customer_email": "alex+1756033996242@example.com",
    "vehicle_reg": "QA6242 ABC",
    "from_date": "2025-08-27",
    "to_datetime": "2025-08-30T09:00:00+00:00",
    "total_minor": 4500,
    "total": "GBP 45.00",
    "currency": "GBP",
    "created_at": "2025-08-25T17:40:17+00:00",
    "updated_at": "2025-08-25T17:40:17+00:00"
}
```
Responses:
- **201** created
- **409** duplicate/overlap or same-payload idempotency
- **409** capacity full on at least one day
- **422** validation errors

### GET `/bookings/{id}`  
Returns a booking resource.

### PUT `/bookings/{id}`  
Amend (re-check capacity, re-price, **bumps `version`**).  
**422** if the booking is cancelled. **409** if the change would overlap another active booking of the same vehicle or capacity is full.

### DELETE `/bookings/{id}`  
Cancel. **422** if already cancelled. Clears fingerprint so the same payload can be re-booked later.

---

## Testing

- PHPUnit covers availability, pricing, lifecycle (create/amend/cancel), idempotency, max-stay, and DST sanity.
- Tests freeze time to `2025-08-21T09:00:00 Europe/London` for deterministic “today”/offsets.

---

## Getting started

```bash
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
```
