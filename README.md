# Inventory & Orders

A Laravel application for managing a product catalogue, customers and orders,
with a secure JSON API, a responsive back-office UI, and verified protection
against overselling stock under concurrent load.

Built to demonstrate production-grade practice in the areas that matter most for
an order system: **exact money arithmetic**, **server-authoritative pricing**,
**atomic stock deduction**, and **real concurrency testing**.

---

## Contents

- [Main features](#main-features)
- [Technology stack](#technology-stack)
- [System requirements](#system-requirements)
- [Installation](#installation)
- [Running the application](#running-the-application)
- [API reference](#api-reference)
- [Order and tax calculation rules](#order-and-tax-calculation-rules)
- [Stock deduction and concurrency strategy](#stock-deduction-and-concurrency-strategy)
- [Changing the currency](#changing-the-currency)
- [Queue configuration](#queue-configuration)
- [Automated testing](#automated-testing)
- [Postman collection](#postman-collection)
- [Web UI](#web-ui)
- [Security](#security)
- [Design decisions](#design-decisions)
- [Assumptions](#assumptions)
- [Known limitations](#known-limitations)
- [Project structure](#project-structure)
- [Troubleshooting](#troubleshooting)

---

## Main features

- **Three REST endpoints** - create an order, read a customer order history,
  list low-stock products. All token-authenticated.
- **Server-authoritative money.** Prices and tax rates always come from the
  product catalogue. Client-submitted prices and totals are ignored entirely.
- **Exact decimal arithmetic.** A dedicated `Money` value object works in integer
  cents, so no monetary value ever passes through a PHP float.
- **Overselling is impossible.** Row-level locking inside a transaction, proven
  by tests that fork real OS processes to contend for the last unit.
- **Historic price snapshots.** Each order line stores the unit price and tax
  rate that applied when the order was placed, so repricing a product never
  rewrites history.
- **Queued order confirmations** dispatched only after the transaction commits.
- **Responsive back-office UI** - dashboard, products, customers, order builder
  with live total previews, and order history.
- **110 automated tests** covering money, validation, stock, concurrency,
  queueing and security.

---

## Technology stack

| Component | Version | Notes |
|---|---|---|
| PHP | 8.4.26 | Requires `>= 8.3` |
| Laravel | 13.38.0 | |
| MySQL | 8.4.11 | InnoDB required for row locking |
| Laravel Sanctum | 4.x | API token authentication |
| PHPUnit | 12.5 | Laravel 13 ships PHPUnit, not Pest |
| Tailwind CSS | 4.x | Via the Vite plugin |
| Alpine.js | 3.x | Only on the order builder screen |
| Vite | 8.x | Asset bundling |
| Node | 22.x | Build-time only |

---

## System requirements

- PHP 8.3 or newer with extensions: `pdo_mysql`, `mbstring`, `bcmath`, `intl`,
  `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`
- `pcntl` (optional) - required only for the concurrency test group
- Composer 2.x
- MySQL 8.x or MariaDB 10.6+ (InnoDB)
- Node.js 20+ and npm (only to build frontend assets)

> **This project targets MySQL.** It runs on SQLite, but SQLite locks the whole
> database file rather than individual rows, so the concurrency guarantees
> cannot be demonstrated there. The concurrency tests detect this and skip with
> an explanatory message rather than passing silently.

---

## Installation

### 1. Install PHP dependencies

```bash
composer install
```

### 2. Create the environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Create the databases

Two databases are needed: one for the application, one for the test suite.

```bash
mysql -u root -p -e "CREATE DATABASE inventory_orders CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE DATABASE inventory_orders_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Optionally create a dedicated user instead of using root:

```bash
mysql -u root -p -e "CREATE USER 'inventory_app'@'localhost' IDENTIFIED BY 'choose-a-password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON inventory_orders.* TO 'inventory_app'@'localhost';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON inventory_orders_test.* TO 'inventory_app'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

### 4. Configure the database connection

Edit `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory_orders
DB_USERNAME=inventory_app
DB_PASSWORD=choose-a-password
DB_TEST_DATABASE=inventory_orders_test
```

### 5. Run migrations and seed sample data

```bash
php artisan migrate --seed
```

This creates 30 products, 15 customers and roughly 29 orders, plus a local
development API user.

| Seeder | Creates |
|---|---|
| `DatabaseSeeder` | API user `admin@example.test` / `password` |
| `ProductSeeder` | 18 hand-written products spanning every interesting case (zero stock, stock exactly at the threshold, zero tax, multiple tax bands) plus 12 random ones |
| `CustomerSeeder` | 7 named customers (one deliberately with no orders) plus 8 random ones |
| `OrderSeeder` | Order history generated by driving the real `OrderService`, so totals and stock are always internally consistent |

> The seeded `admin@example.test` / `password` credential is for **local
> development only**. It grants access to a throwaway database. Never reuse it.

### 6. Build the frontend assets

```bash
npm install
npm run build
```

### Environment variables

Beyond the standard Laravel keys, this application adds:

| Variable | Default | Purpose |
|---|---|---|
| `INVENTORY_LOW_STOCK_THRESHOLD` | `10` | Default threshold for the low-stock endpoint |
| `INVENTORY_LOW_STOCK_MAX_THRESHOLD` | `1000` | Largest threshold a client may request |
| `INVENTORY_MAX_ORDER_ITEMS` | `50` | Maximum distinct line items per order |
| `INVENTORY_MAX_LINE_QUANTITY` | `1000` | Maximum quantity on a single line |
| `INVENTORY_CURRENCY_SYMBOL` | `$` | Display symbol in the UI |
| `DB_TEST_DATABASE` | `inventory_orders_test` | Database used by the test suite |

---

## Running the application

```bash
# Start the web server
php artisan serve

# In a second terminal, start the queue worker (for order confirmations)
php artisan queue:work

# In a third terminal, if you are editing frontend assets
npm run dev
```

The UI is then at <http://127.0.0.1:8000> and the API under
<http://127.0.0.1:8000/api>.

---

## API reference

All endpoints are prefixed with `/api` and return JSON. Send both headers:

```
Accept: application/json
Content-Type: application/json
```

### Authentication

Every endpoint **except** `POST /api/auth/token` requires a Sanctum bearer
token.

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/api/auth/token` | Public | Exchange credentials for a token |
| DELETE | `/api/auth/token` | Bearer | Revoke the current token |
| GET | `/api/user` | Bearer | The authenticated user |
| POST | `/api/orders` | Bearer | Create an order |
| GET | `/api/customers/orders` | Bearer | Customer order history |
| GET | `/api/products/low-stock` | Bearer | Low-stock products |

---

### POST /api/auth/token

Issue a bearer token.

**Request**

```json
{
  "email": "admin@example.test",
  "password": "password",
  "device_name": "postman"
}
```

| Field | Rules |
|---|---|
| `email` | required, string, valid email, max 255 |
| `password` | required, string, max 255 |
| `device_name` | optional, string, max 255 (defaults to `api-client`) |

**Response `201 Created`**

```json
{
  "message": "Token issued.",
  "data": {
    "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "API Admin", "email": "admin@example.test" }
  }
}
```

The plain-text token is shown **once only**; the database stores a hash.

**Errors**

| Status | Cause |
|---|---|
| `422` | Missing/malformed fields, or invalid credentials (deliberately the same generic message either way) |
| `429` | More than 6 requests/min from one IP, or 5 failed attempts/min for one email+IP |

```bash
curl -X POST http://127.0.0.1:8000/api/auth/token \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@example.test","password":"password"}'
```

---

### POST /api/orders

Create an order. **All monetary values are computed server-side.**

**Request**

```json
{
  "customer": {
    "name": "Test Customer",
    "email": "customer@example.test"
  },
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 13, "quantity": 1 }
  ]
}
```

**Validation rules**

| Field | Rules |
|---|---|
| `customer` | required, array |
| `customer.name` | required, string, min 2, max 255 |
| `customer.email` | required, string, valid email, max 255 |
| `items` | required, array, min 1, max 50 |
| `items.*.product_id` | required, integer, min 1, must exist in `products` |
| `items.*.quantity` | required, integer, min 1, max 1000 |

Additional rule: **the same `product_id` may not appear twice.** Combine
duplicate lines into one item with the total quantity.

> **Any price, tax or total in the request body is ignored.** The server reads
> pricing from the `products` table. Sending `"grand_total": "0.01"` has no
> effect whatsoever - this is covered by a dedicated test.

**Response `201 Created`**

```json
{
  "data": {
    "id": 30,
    "reference": "ORD-20261026-VYNQ5Y6F",
    "customer": {
      "id": 16,
      "name": "Test Customer",
      "email": "customer@example.test",
      "created_at": "2026-10-26T16:43:18+00:00"
    },
    "items": [
      {
        "id": 59,
        "product_id": 1,
        "product": {
          "id": 1,
          "name": "Wireless Optical Mouse",
          "code": "SKU-MOU-1001",
          "price": "2399.04",
          "tax_rate": "18.00",
          "stock_quantity": 133,
          "is_low_stock": false
        },
        "quantity": 2,
        "unit_price": "2399.04",
        "tax_rate": "18.00",
        "line_subtotal": "4798.08",
        "line_tax": "863.65",
        "line_total": "5661.73"
      }
    ],
    "subtotal": "4798.08",
    "tax_total": "863.65",
    "grand_total": "5661.73",
    "created_at": "2026-10-26T16:43:18+00:00",
    "updated_at": "2026-10-26T16:43:18+00:00"
  }
}
```

Money is returned as **strings**, not numbers, because JSON numbers are IEEE
doubles and `19.99` can arrive in a JavaScript client as `19.989999999999998`.

**Errors**

| Status | Cause | Example body |
|---|---|---|
| `401` | No or invalid token | `{"message":"Unauthenticated. Provide a valid Bearer token."}` |
| `422` | Validation failed | `{"message":"Quantity must be at least 1.","errors":{"items.0.quantity":["Quantity must be at least 1."]}}` |
| `409` | Insufficient stock | see below |
| `429` | Rate limit exceeded | `{"message":"Too many requests."}` |

A `409 Conflict` names each product that fell short:

```json
{
  "message": "One or more items do not have enough stock available.",
  "errors": {
    "stock": [
      {
        "product_id": 18,
        "product_name": "Fire Extinguisher 2kg",
        "requested": 5,
        "available": 2
      }
    ]
  }
}
```

`409` rather than `422` because the payload is well formed and the products are
real - the current state of the world simply cannot satisfy the request.

**Behaviour notes**

- If no customer has the given email, one is created.
- If a customer already exists, it is reused. Matching is **case-insensitive**,
  so `Bob@Example.com` and `bob@example.com` are the same customer.
- An existing customer is **never renamed** by an order request.
- On any failure, the entire transaction rolls back: no order, no line items, no
  stock change, and no customer row.

```bash
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"customer":{"name":"Test Customer","email":"customer@example.test"},"items":[{"product_id":1,"quantity":2}]}'
```

---

### GET /api/customers/orders

Retrieve a customer order history by email address.

**Query parameters**

| Parameter | Rules | Default |
|---|---|---|
| `email` | **required**, string, valid email, max 255 | - |
| `per_page` | optional, integer, 1-100 | `15` |
| `page` | optional, integer, min 1 | `1` |

**Response `200 OK`**

```json
{
  "data": [
    {
      "id": 30,
      "reference": "ORD-20261026-VYNQ5Y6F",
      "items": [
        {
          "id": 59,
          "product_id": 1,
          "product": { "id": 1, "name": "Wireless Optical Mouse", "code": "SKU-MOU-1001" },
          "quantity": 2,
          "unit_price": "2399.04",
          "tax_rate": "18.00",
          "line_subtotal": "4798.08",
          "line_tax": "863.65",
          "line_total": "5661.73"
        }
      ],
      "items_count": 1,
      "subtotal": "4798.08",
      "tax_total": "863.65",
      "grand_total": "5661.73",
      "created_at": "2026-10-26T16:43:18+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 15, "to": 1, "total": 1 },
  "customer": { "id": 16, "name": "Test Customer", "email": "customer@example.test" }
}
```

**Responses**

| Status | Meaning |
|---|---|
| `200` + populated `data` | Customer exists and has orders |
| `200` + empty `data` | Customer exists but has never ordered |
| `404` | No customer with that email (`{"message":"No customer found for the supplied email address.","data":null}`) |
| `422` | `email` missing or malformed |
| `401` | No or invalid token |

The distinction between an empty list and a 404 is deliberate: "this customer
has not ordered yet" and "this customer does not exist" are different answers.

**N+1 prevention.** Orders are loaded with `with(['items.product'])`, so
rendering any number of orders costs a constant number of queries. Covered by
`CustomerOrderHistoryTest::it_does_not_trigger_n_plus_one_queries`.

```bash
curl "http://127.0.0.1:8000/api/customers/orders?email=customer@example.test&per_page=10" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

---

### GET /api/products/low-stock

List products at or below a stock threshold.

**Query parameters**

| Parameter | Rules | Default |
|---|---|---|
| `threshold` | optional, integer, 0-1000 | `10` (from `INVENTORY_LOW_STOCK_THRESHOLD`) |
| `per_page` | optional, integer, 1-100 | `15` |
| `page` | optional, integer, min 1 | `1` |

The comparison is **`stock_quantity <= threshold`**, so a product sitting
*exactly* on the threshold **is** included. Results are ordered lowest stock
first, then by name.

**Response `200 OK`**

```json
{
  "data": [
    {
      "id": 6,
      "name": "1080p Webcam",
      "code": "SKU-CAM-1006",
      "price": "5664.00",
      "tax_rate": "18.00",
      "stock_quantity": 0,
      "is_low_stock": true,
      "created_at": "2026-10-26T16:42:00+00:00",
      "updated_at": "2026-10-26T16:42:00+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 15, "total": 6, "last_page": 1 },
  "filters": { "threshold": 10, "is_default_threshold": true }
}
```

The `filters` block echoes the applied threshold so the response is
self-describing: a caller can see whether their value or the default was used.

> `filters` is a top-level key rather than part of `meta` because Laravel builds
> its own `meta` block for paginated resource collections and would overwrite
> anything merged into it.

**Errors**

| Status | Cause |
|---|---|
| `422` | `threshold` negative, fractional, non-numeric, or above 1000 |
| `401` | No or invalid token |

The upper bound exists so nobody can pass `threshold=99999999` and use this
endpoint to dump the entire catalogue in one request.

```bash
# Default threshold
curl "http://127.0.0.1:8000/api/products/low-stock" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"

# Custom threshold
curl "http://127.0.0.1:8000/api/products/low-stock?threshold=5" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

---

## Order and tax calculation rules

Every figure is computed server-side from the `products` table.

```
line_subtotal = unit_price * quantity                   (exact integer maths)
line_tax      = round(line_subtotal * tax_rate / 100)    (half-up, per line)
line_total    = line_subtotal + line_tax

subtotal      = sum(line_subtotal)
tax_total     = sum(line_tax)
grand_total   = subtotal + tax_total
```

### Rounding rule

**Half-up, applied once per line item.** A result of exactly half a cent rounds
away from zero (`0.005` becomes `0.01`).

Tax is rounded per line and the **rounded** values are then summed. Summing
rounded lines (rather than rounding the summed tax) guarantees the order total
always equals the sum of its printed line totals - which is what anyone reading
an invoice expects. This invariant is asserted by
`OrderCalculatorTest::the_grand_total_always_equals_the_sum_of_the_line_totals`.

### Worked example

| Product | Unit price | Tax | Qty | Line subtotal | Line tax | Line total |
|---|---|---|---|---|---|---|
| Wireless Optical Mouse | 2399.04 | 18% | 2 | 4798.08 | 863.65 | 5661.73 |
| Printer Paper A4 | 719.04 | 5% | 3 | 2157.12 | 107.86 | 2264.98 |
| Printed Safety Handbook | 1440.00 | 0% | 1 | 1440.00 | 0.00 | 1440.00 |
| **Order** | | | | **8395.20** | **971.51** | **9366.71** |

Line 2 shows the rounding: 5% of 2157.12 is 107.856, which rounds to 107.86.

### Why no floating point

PHP cannot represent `19.99` exactly in binary:

```php
(int) (19.99 * 100);     // 1998, not 1999
0.1 + 0.2 === 0.3;       // false
```

Over many order lines that drift becomes real money. `App\Support\Money`
therefore holds every amount as a whole number of cents, so addition and
multiplication are exact integer operations, and rounding happens exactly once -
at the single point where a fractional result is unavoidable (tax).

Database columns are `DECIMAL(12,2)` for money and `DECIMAL(5,2)` for tax rates.

### Historic price snapshots

Each `order_items` row stores the `unit_price` and `tax_rate` that applied at the
moment of ordering. Repricing a product tomorrow does not rewrite yesterday
orders. Covered by
`CreateOrderTest::it_stores_the_price_and_tax_rate_that_applied_at_order_time`.

---

## Stock deduction and concurrency strategy

### The problem

Two customers try to buy the last unit at the same moment. A naive
implementation reads stock (`1`), both see enough, both decrement, and stock
becomes `-1`. One unit has been sold twice.

### The solution

`App\Services\OrderService::create()` wraps everything in one transaction and
takes a pessimistic row lock before reading stock:

```php
DB::transaction(function () {
    $customer = $this->resolveCustomer(...);

    // 1. Lock the product rows. A second transaction touching the same rows
    //    now BLOCKS here until this one commits or rolls back.
    $products = Product::query()
        ->whereIn('id', $sortedProductIds)   // sorted: see deadlock note
        ->orderBy('id')
        ->lockForUpdate()                    // SELECT ... FOR UPDATE
        ->get()
        ->keyBy('id');

    // 2. Re-check stock AFTER the lock is held. This is the critical step:
    //    the pre-lock read may already be stale.
    $this->assertStockIsSufficient($lines, $products);

    // 3. Compute money from the locked product rows.
    $calculated = $this->calculator->calculate($lines, $products);

    // 4. Write the order and its lines.
    // 5. Decrement stock with a conditional UPDATE.
});

// 6. Only after a successful COMMIT is the confirmation queued.
SendOrderConfirmation::dispatch($order->id);
```

### Deadlock avoidance

Product IDs are **sorted ascending before locking**. If order A locks products
`[5, 9]` and order B locks `[9, 5]` in that sequence, each can hold the lock the
other needs - a classic deadlock. Sorting means every transaction in the system
acquires locks in the same order, so that cycle cannot form.

Verified by
`OrderConcurrencyTest::concurrent_orders_for_multiple_products_do_not_deadlock`,
which deliberately submits two orders referencing the same products in opposite
sequence.

### Defence in depth

Four independent layers prevent negative stock:

1. **Validation** rejects quantities below 1.
2. **Row lock + re-check** inside the transaction.
3. **Conditional UPDATE** - the decrement runs
   `where('stock_quantity', '>=', $quantity)`, so if the row somehow changed, zero
   rows are affected and an exception rolls the order back.
4. **Unsigned column** - `unsignedInteger` means the database itself rejects a
   negative value.

### Verification

Concurrency is proven, not assumed. The tests fork **real OS processes** with
`pcntl_fork`, giving each child its own database connection so they genuinely
contend for the same row.

| Scenario | Expected | Actual |
|---|---|---|
| 2 processes, 1 unit in stock | 1 success, 1 clean 409 | **Passed** |
| 10 processes, 4 units in stock | 4 successes, 6 rejections, stock = 0 | **Passed** |
| 2 processes, products in opposite order | both succeed, no deadlock | **Passed** |

```bash
php artisan test --group=concurrency
```

> **A sequential test cannot prove this.** Two requests issued one after another
> in a single PHP process never contend for a lock. That is why these tests fork.

> **Database requirement.** This depends on InnoDB row-level locking. On SQLite
> the tests **skip with an explanatory message** rather than reporting a false
> pass, because SQLite locks the entire database file.

---

## Queue configuration

After an order is created, `App\Jobs\SendOrderConfirmation` is dispatched to
simulate sending a confirmation email. No SMTP server is required - the job
writes a safe line to the application log.

### Configuration

```dotenv
QUEUE_CONNECTION=database   # jobs stored in the `jobs` table (default)
# QUEUE_CONNECTION=sync     # run inline, no worker needed (handy for debugging)
MAIL_MAILER=log             # "sent" mail goes to storage/logs/laravel.log
```

### Running a worker

```bash
# Process jobs continuously
php artisan queue:work

# Process the backlog then exit (useful in scripts and CI)
php artisan queue:work --stop-when-empty

# Inspect and retry failures
php artisan queue:failed
php artisan queue:retry all
```

### Two deliberate design points

1. **The job carries an order ID, not an Order model.** Serialising the whole
   model would freeze a snapshot into the queue payload and write customer data
   into the `jobs` table. An ID keeps the payload minimal and forces a fresh read
   at execution time.

2. **`afterCommit()` is enabled.** The job is only released to a worker once the
   surrounding transaction commits. Without it, a worker could pick the job up
   while the order row was still uncommitted - or already rolled back - and find
   nothing there. `OrderService` also dispatches *after* the transaction closure
   returns, so a failed order can never produce a confirmation.

### What gets logged

```
[2026-10-26 16:44:08] local.INFO: Order confirmation email queued for delivery.
{"order_id":32,"order_reference":"ORD-20261026-IEJGC6OM","customer_name":"Brand New Buyer",
"customer_email":"brand.new@example.test","item_count":1,"grand_total":"1074.24"}
```

Deliberately minimal and non-sensitive: no payment details, no tokens, no
passwords. Log files are often shipped to third-party aggregators.

### Tests

| Test | Asserts |
|---|---|
| `CreateOrderTest::it_dispatches_a_confirmation_job_after_a_successful_order` | Job is queued with the correct order ID |
| `OrderStockTest::it_does_not_dispatch_a_confirmation_job_when_stock_is_insufficient` | No job after a 409 |
| `CreateOrderValidationTest::it_does_not_dispatch_a_confirmation_job_when_validation_fails` | No job after a 422 |

---

## Automated testing

The suite uses **PHPUnit 12.5** (Laravel 13 ships PHPUnit, not Pest) and runs
against **MySQL**, not SQLite - see
[the concurrency section](#stock-deduction-and-concurrency-strategy) for why.

### Commands

```bash
# Main suite (107 tests)
php artisan test --exclude-group=concurrency

# Concurrency suite (3 tests, forks real processes)
php artisan test --group=concurrency

# A single file
php artisan test tests/Feature/Api/CreateOrderTest.php

# With coverage (requires pcov or xdebug)
php artisan test --coverage
```

> **Run the two groups separately.** The concurrency tests deliberately avoid
> `RefreshDatabase`, because its per-test transaction is invisible to other
> connections and a forked child would not see the test data. Mixing them into
> one run causes interference.

### Results as of the last run

| Suite | Tests | Assertions | Result |
|---|---|---|---|
| Unit | 22 | 37 | **Passed** |
| Feature (excluding concurrency) | 85 | 360 | **Passed** |
| Concurrency | 3 | 16 | **Passed** |
| **Total** | **110** | **413** | **All passing** |

### Coverage by area

| File | Covers |
|---|---|
| `tests/Unit/MoneyTest.php` | Decimal parsing, float traps, half-up rounding, injection-style input |
| `tests/Unit/OrderCalculatorTest.php` | Line and order totals, mixed tax rates, the total-equals-lines invariant |
| `tests/Feature/Api/CreateOrderTest.php` | Successful creation, new vs existing customer, case-insensitive matching, multiple products, stock deduction, historic snapshots, **client price tampering**, job dispatch |
| `tests/Feature/Api/CreateOrderValidationTest.php` | Empty/missing items, bad product IDs, 7 invalid quantity cases, 7 invalid customer cases, duplicate products, order-size cap |
| `tests/Feature/Api/OrderStockTest.php` | Insufficient stock, exact-stock orders, out-of-stock, **no partial writes after failure**, sequential exhaustion |
| `tests/Feature/Api/OrderConcurrencyTest.php` | Real forked-process races for the last unit, heavy contention, deadlock avoidance |
| `tests/Feature/Api/CustomerOrderHistoryTest.php` | Known/unknown/empty customers, invalid email, case-insensitive lookup, isolation between customers, pagination, **N+1 prevention** |
| `tests/Feature/Api/LowStockProductTest.php` | Below/at/above threshold, zero stock, default threshold, 6 invalid threshold cases, ordering, pagination |
| `tests/Feature/Api/AuthTokenTest.php` | Token issue/revoke, hashed storage, generic error messages, enumeration resistance, throttling, unauthenticated rejection |
| `tests/Feature/Api/SecurityTest.php` | Unique constraints, foreign-key integrity, cascade/restrict behaviour, mass assignment, SQL injection, error-message leakage, negative stock at DB level |

### Notable tests

**Client price tampering** - posts a body claiming a `grand_total` of `0.01` for
a ₹48,000 product, and asserts the stored total is `56640.00`.

**No partial writes** - submits an order with one satisfiable line and one
impossible line, then asserts zero orders, zero line items, **zero customers**,
and unchanged stock on the satisfiable product.

**Real concurrency** - forks 10 processes competing for 4 units and asserts
exactly 4 succeed.

---

## Postman collection

```
postman/Inventory-Orders.postman_collection.json
postman/Local-Environment.postman_environment.json
```

### Importing

1. Postman > **Import** > select both files.
2. Top-right environment selector > choose **Inventory & Orders - Local**.
3. Confirm `base_url` matches your server (default `http://127.0.0.1:8000`).

### Before running

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Then check that `product_id_1`, `product_id_2`, `product_id_3` and
`low_stock_product_id` in the environment point at real rows:

```bash
php artisan tinker --execute="\App\Models\Product::whereIn('code', ['SKU-MOU-1001','SKU-PAP-1013','SKU-BOK-1016','SKU-FIR-1018'])->get(['id','code','stock_quantity'])->each(fn(\$p) => print(\$p->id.' '.\$p->code.' stock='.\$p->stock_quantity.PHP_EOL));"
```

### Recommended run order

| Folder | Contents |
|---|---|
| `00 - Authentication` | **Issue token (run this first)**, plus two deliberate failures |
| `01 - Create order` | New customer, existing customer, multiple products, price-tampering check |
| `02 - Create order (expected failures)` | Insufficient stock, invalid customer, invalid product, invalid quantity, empty items, duplicate product |
| `03 - Customer order history` | Known customer, customer with no orders, unknown email, malformed email |
| `04 - Low stock products` | Default threshold, custom threshold, negative threshold, threshold over maximum |
| `05 - Clean up (run last)` | Revoke the token |

**Run "Issue token" first.** Its test script saves the token into the
`auth_token` environment variable that every other request reads. The revoke
request is isolated in folder `05` so a straight top-to-bottom run reaches it
last.

### Requests expected to fail

Anything named **`FAILS:`** is *meant* to return 4xx. Its tests assert that
failure, so a green tick means the API correctly rejected bad input.

### Variables saved automatically

| Variable | Set by |
|---|---|
| `auth_token` | Issue token |
| `order_id`, `order_reference` | Create order - new customer |
| `existing_customer_email` | Create order - new customer |

### Running from the command line

```bash
npm install -g newman

newman run postman/Inventory-Orders.postman_collection.json \
  -e postman/Local-Environment.postman_environment.json
```

Last verified run: **22 requests, 54 assertions, 0 failures.**

### Security note

The collection contains no real credentials. The environment values are local
development placeholders matching the seeder, and `auth_token` ships empty.

---

## Web UI

Server-rendered Blade with Tailwind CSS 4, plus Alpine.js on the one screen that
genuinely needs interactivity.

| Page | Route | Contents |
|---|---|---|
| **Dashboard** | `/` | Summary cards (products, customers, orders, low stock), low-stock table, recent orders |
| **Products** | `/products` | Name, code, unit price, tax rate, stock with low-stock badges; search, stock-level filter, sortable columns, pagination |
| **Customers** | `/customers` | Name, email, order count, total spend; search and pagination |
| **Customer detail** | `/customers/{customer}` | Per-customer stats and full order history with expandable line items |
| **New order** | `/orders/create` | Customer fields, dynamic product rows, add/remove controls, live stock indicators, live subtotal/tax/total preview, validation errors, loading state |
| **Order confirmation** | `/orders/{order}` | Full breakdown, totals, customer panel, order metadata |
| **Order history** | `/orders` | All orders with reference, customer, date, items, subtotal, tax, total; search and date-range filters, pagination |

### The order builder

The most interesting screen. Alpine manages the line rows and previews totals as
you type, mirroring the server rounding rules exactly (integer cents, half-up per
line).

**The preview is a convenience only.** The form submits nothing but
`product_id` and `quantity` per line; the server recalculates every figure from
the product catalogue. Verified during development: for the same order the
browser predicted `698.00 / 125.64 / 823.64` and the server produced identical
figures.

Other behaviour:

- Only products with stock above zero are offered.
- A product already on one line is disabled in the other selects, matching the
  server rule that rejects duplicates.
- The quantity input caps at the available stock and warns when exceeded.
- The submit button disables while the request is in flight, preventing
  double submission.
- On a stock failure the form is redisplayed with the input intact and a message
  naming each short product.

### Design system

Reusable classes defined once in `resources/css/app.css` using Tailwind 4
`@utility` declarations: `btn-primary`, `card`, `input`, `badge-success`,
`alert-error`, and so on. Restyling every button is a one-line change.

> Tailwind 4 note: these are declared with `@utility`, not inside
> `@layer components`. Tailwind 4 resolves `@utility` before `@apply` runs, which
> is what lets `btn-primary` build on `btn`. The same code inside
> `@layer components` fails with an unknown-utility-class error.

Reusable Blade components live in `resources/views/components/`: `stat-card`,
`stock-badge`, `empty-state`, `money`, `input-error`, `nav-link`.

### Responsiveness

- **Mobile** - sidebar collapses into a slide-over drawer; tables scroll
  horizontally; form fields stack.
- **Tablet** - two-column card grids.
- **Desktop** - fixed sidebar, four-column cards, sticky order summary.

### Accessibility

- A skip link as the first focusable element.
- Every input has an associated label; errors use `role="alert"` and
  `aria-describedby`.
- `aria-invalid` drives the error styling, so the visual state and the state
  announced to a screen reader cannot disagree.
- Tables have screen-reader captions and `scope` attributes.
- Visible focus rings via `:focus-visible`.
- `prefers-reduced-motion` is respected.
- Stock status is conveyed by icon and text, not colour alone.

### States

Every list has an explicit **empty state** explaining what is going on and what
to do next. Forms show inline validation errors, a loading state on submit, and
a success message after redirect.

---

## Security

Full detail is in **[SECURITY.md](SECURITY.md)**. Summary:

| Control | Status |
|---|---|
| API authentication | Sanctum bearer tokens on all `/api` routes except token issue |
| Token storage | Hashed; plain text shown once |
| Account enumeration | Prevented - identical message for wrong password and unknown email |
| Rate limiting | 6/min on token issue, 5 failed logins/min, 60/min elsewhere |
| Input validation | Form Requests on every endpoint, allow-list based |
| SQL injection | Eloquent/prepared statements throughout; `ORDER BY` restricted to an allow list |
| Mass assignment | Explicit `$fillable` on every model |
| Price tampering | Impossible - the DTO has no price field |
| Negative stock | Four independent layers |
| Partial writes | Prevented by a single transaction |
| Error leakage | Generic messages; no paths, SQL or env values |
| CSRF (web UI) | Laravel `web` middleware; verified returning 419 |
| XSS | Blade escaping; `Js::from()` for JS data |
| Secrets | `.env` git-ignored; `.env.example` holds placeholders only |

**The web UI has no authentication** and is intended for localhost use only.
This is documented as limitation L2 in SECURITY.md rather than implied to be
secure.

---

## Design decisions

**Money as integer cents.** PHP floats cannot represent `19.99` exactly, so
`(int)(19.99 * 100)` is `1998`. `App\Support\Money` keeps amounts as whole cents
and rounds exactly once, where tax makes a fraction unavoidable.

**Money as JSON strings.** JSON numbers are IEEE doubles, so a numeric `19.99`
can reach a JavaScript client as `19.989999999999998`. Strings are exact.

**Tax rounded per line, then summed.** Keeps an invoice internally consistent:
the order total always equals the sum of the printed line totals.

**Historic price snapshots on order lines.** An order must always show what the
customer was actually charged, so repricing cannot rewrite history.

**Duplicate products rejected, not merged.** Merging would mean the server
quietly changed what was asked for. A client sending the same product twice has
probably made a mistake worth surfacing. Enforced in validation *and* by a unique
constraint.

**409 for insufficient stock, 422 for bad input.** A well-formed request for
unavailable stock is a state conflict, not a malformed payload. Different causes
deserve different codes.

**Pessimistic locking over optimistic.** Stock contention on popular products is
a frequent, expected collision. `lockForUpdate()` makes the second request wait
briefly rather than fail and retry.

**Locks acquired in sorted ID order.** Removes the lock-ordering cycle that
causes deadlocks.

**Job receives an ID, not a model.** Smaller payload, no customer data in the
`jobs` table, and a guaranteed-fresh read at execution time.

**Service layer, not fat controllers.** `OrderService` owns the transaction and
locking so the API controller and the web controller share one code path - the
browser form and the JSON API cannot drift apart.

**DTO with no price field.** The strongest guarantee against price tampering is
structural: `OrderLineInput` carries only `productId` and `quantity`, so a
client-supplied price physically cannot reach the calculator.

**Blade and Alpine over a SPA.** One screen needs interactivity. A full SPA would
add a second application, an API versioning burden and a build pipeline for no
benefit here.

**PHPUnit, not Pest.** Laravel 13 ships PHPUnit by default; following the
installed setup rather than converting it.

**Seeder drives the real `OrderService`.** Seeded orders therefore have correct
totals, correct snapshots and correctly decremented stock. Seed data cannot
drift from the calculation rules.

**Random order references.** `ORD-20261026-A1B2C3D4` does not disclose how many
orders the system has taken, unlike a sequential number.

---

## Changing the currency

The display symbol comes from config, so switching it is a one-line change:

```env
INVENTORY_CURRENCY=INR
INVENTORY_CURRENCY_SYMBOL=₹
```

That changes how amounts are *rendered*, not what they are worth. To
re-denominate the values already stored, use:

```bash
# Preview - writes inside a transaction, then rolls it back
php artisan inventory:convert-currency 96.00 --dry-run

# Apply, showing each row as it changes
php artisan inventory:convert-currency 96.00 -v
```

The rate is units of the new currency per unit of the old, with at most two
decimal places (a finer rate is rejected rather than silently rounded).

**Why this is a command and not an `UPDATE ... SET price = price * 96`.** An
order stores `subtotal`, `tax_total` and `grand_total`, and each line stores
`line_subtotal`, `line_tax` and `line_total`. Those figures are bound together
by the rounding rules above: tax is rounded half-up once per line, and the
rounded lines are then summed. Multiplying every column independently rounds
each one separately, and the invoice stops reconciling - a grand total that
disagrees with the sum of its own printed lines by a few paise.

So the command converts only the two *input* values, the catalogue `price` and
the historic `unit_price` snapshot on each order line, and then recomputes every
derived figure through the same `OrderCalculator` rules used when an order is
placed. Tax rates are percentages and are deliberately left untouched. The whole
run is wrapped in one transaction, so a failure cannot leave the catalogue
converted and the order history not.

After the conversion that produced the current seed data, every order still
reconciles exactly: 0 orders and 0 lines disagree with their components.

---

## Assumptions

Where the requirements were open to interpretation:

1. **Single currency.** The application is denominated in Indian Rupees (INR,
   `₹`). There is no multi-currency support and no live FX lookup: amounts are
   stored as plain decimals in one currency, and the symbol is configurable via
   `INVENTORY_CURRENCY_SYMBOL`. See
   [Changing the currency](#changing-the-currency) to re-denominate.
2. **Tax is a simple percentage per product.** No jurisdictions, exemptions or
   compound tax.
3. **A customer is not a login.** Customers never authenticate; `User` is the
   separate back-office/API identity.
4. **Orders are immutable once placed.** No editing, cancellation or refunds, so
   stock is never restored.
5. **No payment processing.** Orders are recorded, not charged.
6. **Stock is a single pooled number.** No warehouses, reservations or
   backorders.
7. **A product exactly at the threshold counts as low stock** (the comparison is
   `<=`).
8. **An order request never renames an existing customer.** Knowing an email
   address must not let you change the stored name of that customer.
9. **Confirmation emails are simulated** by a log entry, as the brief permits.
10. **The web UI is a trusted internal tool** on localhost, hence no auth on it.
11. **`example.test` is used for all sample emails** - a reserved TLD that cannot
    accidentally reach a real inbox.
12. **The API is unversioned.** Routes are `/api/orders`, not `/api/v1/orders`,
    matching the brief exactly.

---

## Known limitations

Honest gaps, with the fix for each. Security-specific items are L1-L10 in
[SECURITY.md](SECURITY.md).

| # | Limitation | Fix |
|---|---|---|
| 1 | No authentication on the web UI | Add `laravel/breeze` and an `auth` middleware group |
| 2 | No authorisation granularity - any token reads any customer history | Sanctum token abilities plus a policy |
| 3 | No order cancellation, so stock is never restored | Add a cancellation flow that re-increments inside a transaction |
| 4 | No product/customer CRUD in the UI (read-only lists) | Add resource controllers and forms |
| 5 | Sanctum tokens never expire | Set `expiration` in `config/sanctum.php`, schedule `sanctum:prune-expired` |
| 6 | No HTTPS enforcement or security headers | `URL::forceScheme('https')` plus header middleware |
| 7 | No audit trail of who created an order | Add `created_by` and an audit log |
| 8 | Rate limiting is per-node with a local cache | Use a shared Redis cache store |
| 9 | Concurrency tests need `pcntl` (POSIX only) | They skip with a clear message on Windows without WSL |
| 10 | No API versioning | Introduce `/api/v1` before any breaking change |
| 11 | No CI pipeline | Add a GitHub Actions workflow running both test groups against MySQL |
| 12 | No screenshots in this repository | See `prompts/README.md` for how to capture them |

---

## Project structure

Files written for this project (the standard Laravel skeleton is omitted):

```
app/
├── Data/                              Immutable DTOs
│   ├── CalculatedLine.php             One priced order line
│   ├── CalculatedOrder.php            Totals for a whole order
│   └── OrderLineInput.php             Validated input - NO price field, by design
├── Exceptions/
│   ├── InsufficientStockException.php  Carries per-product shortage detail
│   └── ProductUnavailableException.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthTokenController.php            Issue/revoke Sanctum tokens
│   │   │   ├── CustomerOrderHistoryController.php API 2
│   │   │   ├── LowStockProductController.php      API 3
│   │   │   └── OrderController.php                API 1
│   │   └── Web/
│   │       ├── CustomerController.php
│   │       ├── DashboardController.php
│   │       ├── OrderController.php
│   │       └── ProductController.php
│   ├── Requests/
│   │   ├── Api/  (StoreOrder, CustomerOrderHistory, LowStock, IssueToken)
│   │   └── Web/  (StoreOrderWebRequest)
│   └── Resources/  (Product, Customer, Order, OrderItem)
├── Jobs/
│   └── SendOrderConfirmation.php      Queued, afterCommit, ID-only payload
├── Models/
│   ├── Customer.php                   + email normalisation
│   ├── Order.php                      + reference generation
│   ├── OrderItem.php                  Historic price snapshot
│   ├── Product.php                    + lowStock and search scopes
│   └── User.php                       API identity (HasApiTokens)
├── Services/
│   ├── OrderCalculator.php            Pure arithmetic, no I/O
│   └── OrderService.php               Transaction + row locking  <-- core file
└── Support/
    └── Money.php                      Integer-cent value object  <-- core file

config/inventory.php                   Thresholds, order limits, money settings

database/
├── factories/   (Product, Customer, Order, OrderItem)
├── migrations/  (products, customers, orders, order_items)
└── seeders/     (Database, Product, Customer, Order)

resources/
├── css/app.css                        Design tokens + @utility components
├── js/
│   ├── app.js                         Alpine bootstrap
│   └── order-builder.js               Order form logic + preview maths
└── views/
    ├── components/   (stat-card, stock-badge, empty-state, money, input-error, nav-link)
    ├── customers/    (index, show)
    ├── dashboard/    (index)
    ├── layouts/app.blade.php          Responsive shell with sidebar
    ├── orders/       (index, create, show)
    ├── partials/flash.blade.php
    └── products/index.blade.php

routes/
├── api.php                            Sanctum-protected API
└── web.php                            Browser UI

tests/
├── Feature/Api/  (CreateOrder, CreateOrderValidation, OrderStock,
│                  OrderConcurrency, CustomerOrderHistory, LowStockProduct,
│                  AuthToken, Security)
├── Unit/         (Money, OrderCalculator)
└── TestCase.php                       Sanctum helpers

postman/
├── Inventory-Orders.postman_collection.json
└── Local-Environment.postman_environment.json

prompts/README.md                      Prompt log and screenshot guidance
SECURITY.md                            Controls, assumptions, limitations
```

### The three files worth reading first

1. **`app/Support/Money.php`** - why no monetary value touches a float.
2. **`app/Services/OrderService.php`** - the transaction, the row locking, the
   post-commit dispatch.
3. **`tests/Feature/Api/OrderConcurrencyTest.php`** - how the overselling
   guarantee is actually proven.

### Database schema

```
products                      customers
--------                      ---------
id                            id
name                          name
code            UNIQUE        email           UNIQUE
price           DECIMAL(12,2) created_at
tax_rate        DECIMAL(5,2)  updated_at
stock_quantity  UNSIGNED INT
created_at         INDEX on stock_quantity, name
updated_at

orders                        order_items
------                        -----------
id                            id
reference       UNIQUE        order_id    FK -> orders     (cascade on delete)
customer_id     FK -> customers (restrict) product_id  FK -> products   (restrict)
subtotal        DECIMAL(12,2) quantity        UNSIGNED INT
tax_total       DECIMAL(12,2) unit_price      DECIMAL(12,2)  <- snapshot
grand_total     DECIMAL(12,2) tax_rate        DECIMAL(5,2)   <- snapshot
created_at                    line_subtotal   DECIMAL(12,2)
updated_at                    line_tax        DECIMAL(12,2)
                              line_total      DECIMAL(12,2)
   INDEX (customer_id, created_at)   UNIQUE (order_id, product_id)
```

---

## Troubleshooting

### `SQLSTATE[HY000] [1049] Unknown database 'inventory_orders'`

The database has not been created yet:

```bash
mysql -u root -p -e "CREATE DATABASE inventory_orders CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### `SQLSTATE[HY000] [1045] Access denied for user`

`DB_USERNAME` / `DB_PASSWORD` in `.env` do not match a real MySQL user. Verify:

```bash
mysql -u your_db_user -p -e "SELECT 1;"
```

Then clear the cached config:

```bash
php artisan config:clear
```

### Tests fail with `Unknown database 'inventory_orders_test'`

The test database is separate and must exist:

```bash
mysql -u root -p -e "CREATE DATABASE inventory_orders_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Concurrency tests are skipped

Expected on some setups. The skip message names the cause:

- *"require MySQL/MariaDB"* - the test connection is SQLite. Check the
  `DB_CONNECTION` value in `phpunit.xml`.
- *"require ext-pcntl"* - `pcntl` is unavailable (normal on native Windows).
  Run under WSL, Linux or macOS. `php -m | grep pcntl` to confirm.

They skip rather than pass, so a skipped run is never mistaken for a verified one.

### `Vite manifest not found`

Frontend assets have not been built:

```bash
npm install
npm run build
```

### `Error: Cannot apply unknown utility class 'btn'`

A Tailwind 4 ordering issue. Custom classes that other classes `@apply` must be
declared with `@utility`, not inside `@layer components`. See
`resources/css/app.css`.

### The UI loads but is completely unstyled

The build output is missing or stale. Rebuild with `npm run build`, then
hard-reload the browser. Confirm `public/build/manifest.json` exists.

### Order confirmations never appear in the log

No queue worker is running. Either start one:

```bash
php artisan queue:work
```

or set `QUEUE_CONNECTION=sync` in `.env` to run jobs inline. Check for stuck
jobs with `php artisan queue:failed`.

### `419 Page Expired` when submitting the order form

A CSRF token problem - usually a stale page after a server restart, or a session
driver that cannot write. Reload the page. Confirm `SESSION_DRIVER=database` and
that the `sessions` table exists (`php artisan migrate`).

### `Target class [...] does not exist`

Autoloader is stale:

```bash
composer dump-autoload
php artisan optimize:clear
```

### Changes to `.env` seem to be ignored

Cached configuration is taking precedence:

```bash
php artisan config:clear
```

### Postman requests return 401

The token is missing or expired. Run **00 - Authentication > Issue token**
again; it refreshes the `auth_token` environment variable. Also confirm the
correct environment is selected in the top-right selector.

### Postman requests return 422 for a valid-looking product

`product_id_1/2/3` in the environment point at IDs that do not exist - common
after `migrate:fresh` reassigns IDs. Re-check them with the tinker command in
the [Postman section](#postman-collection).

### A fresh start

```bash
php artisan migrate:fresh --seed
php artisan optimize:clear
npm run build
```

---

## Quick reference

```bash
# Setup
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install && npm run build

# Run
php artisan serve                              # http://127.0.0.1:8000
php artisan queue:work                         # order confirmations

# Test
php artisan test --exclude-group=concurrency   # main suite
php artisan test --group=concurrency           # real concurrency tests

# Inspect
php artisan route:list --except-vendor
./vendor/bin/pint --test                       # code style

# Postman
newman run postman/Inventory-Orders.postman_collection.json \
  -e postman/Local-Environment.postman_environment.json
```

---

## Licence

MIT. Built as a technical demonstration.
