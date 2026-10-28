# Security

This document records the security controls that are actually implemented in
this codebase, the assumptions behind them, and the limitations that remain.
Nothing is listed here as protected unless it is implemented and covered by a
test.

Last reviewed: 2026-10-26

---

## 1. Authentication and authorisation

### What is protected

| Endpoint | Method | Protection |
|---|---|---|
| `/api/auth/token` | POST | **Public.** Rate limited to 6 requests/min per IP, plus 5 failed attempts/min per email+IP. |
| `/api/auth/token` | DELETE | `auth:sanctum` |
| `/api/user` | GET | `auth:sanctum` |
| `/api/orders` | POST | `auth:sanctum` |
| `/api/customers/orders` | GET | `auth:sanctum` |
| `/api/products/low-stock` | GET | `auth:sanctum` |
| All `/` web UI routes | GET/POST | **No authentication** (see limitations) |

The token endpoint cannot itself require a token, so it is protected by rate
limiting instead.

### How it works

- **Laravel Sanctum** personal access tokens, sent as `Authorization: Bearer <token>`.
- Tokens are stored **hashed** in `personal_access_tokens`. The plain-text value
  is shown exactly once, at issue time. Verified by
  `AuthTokenTest::the_stored_token_is_hashed_not_plain_text`.
- Revocation is immediate: `DELETE /api/auth/token` deletes the token row, and
  the next request with it returns 401. Verified against a live server and by
  `AuthTokenTest::a_revoked_token_no_longer_grants_access`.

### Credential handling

- Passwords are hashed with bcrypt via the `hashed` cast on the User model.
- **Account enumeration is prevented.** A wrong password and an unknown email
  return the byte-identical message "These credentials do not match our
  records." Verified by
  `AuthTokenTest::an_unknown_email_and_a_wrong_password_give_the_same_message`.
- **Timing side-channel mitigated.** When no user matches, `Hash::check()` is
  still run against a dummy bcrypt hash so a missing email and a wrong password
  take comparable time.
- Passwords and tokens are added to `dontFlash` so they never reach logs,
  session flash data, or stack traces.

### Authorisation model

Any valid token holder may call any protected endpoint. There are no roles and
no per-customer ownership checks. This is a deliberate scope decision for a
single-tenant back-office tool, **not** an oversight - see limitation L1.

---

## 2. Input validation

Every request is validated by a Form Request class before any business logic
runs:

| Class | Covers |
|---|---|
| `Api\StoreOrderRequest` | Order creation (API) |
| `Api\CustomerOrderHistoryRequest` | Order history lookup |
| `Api\LowStockRequest` | Low-stock threshold |
| `Api\IssueTokenRequest` | Credentials |
| `Web\StoreOrderWebRequest` | Order creation (browser form) |

Key rules:

- **Quantities** must be integers `>= 1` and `<= 1000` per line (configurable).
  Zero, negative, fractional, string and array values are all rejected.
- **Product IDs** must be integers that exist in `products`.
- **Emails** are validated with `email:rfc` and capped at 255 characters.
- **Thresholds** are bounded `0 <= n <= 1000` (configurable). The upper bound
  exists so nobody can pass `threshold=99999999` and dump the whole catalogue.
- **Pagination** is capped at 100 items per page to limit memory use.
- **Order size** is capped at 50 line items, limiting how long a single
  transaction can hold row locks.

### Allow-list, not deny-list

`validated()` returns only keys that have validation rules. Extra keys in the
request body are never read. The service layer receives an `OrderLineInput` DTO
that has **no field for a price** - so a client-supplied price cannot physically
reach the calculation, even if a future refactor forgot to strip it.

---

## 3. Business-logic security

### Money is server-authoritative

Prices and tax rates are read from the `products` table **inside** the
transaction. Anything the client sends about `price`, `unit_price`, `tax_rate`,
`line_total`, `subtotal`, `tax_total` or `grand_total` is discarded.

Verified by `CreateOrderTest::it_ignores_client_supplied_prices_taxes_and_totals`,
which posts a body claiming `grand_total: "0.01"` for a ₹48,000 product and asserts
the stored total is `590.00`. The Postman collection contains the equivalent
check.

### No floating-point money

All monetary arithmetic runs through `App\Support\Money`, which holds values as
integer cents. Columns are `DECIMAL`, never `FLOAT`. JSON responses emit money as
**strings** so a JavaScript client cannot silently turn `19.99` into
`19.989999999999998`.

Rounding rule: **half-up, applied per line item**, documented in the README and
covered by `MoneyTest` (8 data-provider cases) and `OrderCalculatorTest`.

### Stock cannot go negative or oversell

Four independent layers:

1. **Validation** rejects non-positive quantities.
2. **Row locking** - `lockForUpdate()` on product rows, re-reading stock *after*
   the lock is acquired.
3. **Conditional UPDATE** - `where('stock_quantity', '>=', $quantity)` on the
   decrement, so a zero affected-row count raises an exception and rolls back.
4. **Unsigned column** - the database itself refuses a negative value. Verified
   by `SecurityTest::stock_quantity_cannot_be_set_negative_at_the_database_level`.

### Atomicity

Customer creation, order creation, line items and stock decrements all happen in
one `DB::transaction()`. Any failure rolls back everything, including the
customer row that was created earlier in the same transaction. Verified by
`OrderStockTest::a_failed_order_leaves_no_partial_database_changes`.

### Duplicate handling

- **Duplicate customers:** prevented by a unique index on `customers.email` plus
  case-insensitive normalisation (`Bob@Example.com` and `bob@example.com` are the
  same customer).
- **Duplicate products in one order:** rejected with 422. Also enforced by a
  `unique(order_id, product_id)` constraint on `order_items`.
- An order request never renames an existing customer. Knowing an email address
  must not let you change the stored name of that customer.

---

## 4. Database security

- **No raw SQL with user input.** Everything goes through Eloquent or the query
  builder, which use prepared statements. Verified by two injection tests that
  pass `' OR '1'='1` and `1; DROP TABLE products` and confirm the tables survive.
- **`ORDER BY` is an allow list.** The product list accepts a `sort` parameter,
  constrained by an `in:` rule to four known column names, because column names
  cannot be parameter-bound.
- **`LIKE` wildcards are escaped** so searching for `%` does not match every row.
- **Mass assignment** - every model declares an explicit `$fillable`. No model
  uses `$guarded = []`. Verified by two mass-assignment tests.
- **Foreign keys** are enforced with deliberate delete behaviour:
  - `order_items.order_id` to `orders` - cascade (a line cannot outlive its order)
  - `order_items.product_id` to `products` - restrict (protects order history)
  - `orders.customer_id` to `customers` - restrict (protects order history)

---

## 5. Error handling and information disclosure

- API errors are always JSON, never an HTML error page.
- `QueryException` is caught and replaced with a generic message, so driver
  text containing table names, column names or SQL never reaches a client.
- Any unhandled exception returns a generic message when `APP_DEBUG=false`.
- Verified by `SecurityTest::error_responses_do_not_leak_internal_details`, which
  asserts no response contains `/var/www`, `vendor/laravel`, `SQLSTATE`,
  `DB_PASSWORD`, `APP_KEY` or `.php:`.

### Logging

The order confirmation job logs only: order ID, order reference, customer name,
customer email, item count and grand total. No payment data, no tokens, no
passwords. Checked against the real log file during verification.

---

## 6. Web UI security

- **CSRF protection** on all state-changing requests via the Laravel `web`
  middleware group. Confirmed manually: a POST without a token returns **419**.
- **XSS** - Blade `{{ }}` escapes output by default. The only data passed to
  JavaScript goes through `Js::from()`, which JSON-encodes and escapes it.
- **No unescaped output** - the `{!! !!}` syntax is not used anywhere in the views.

---

## 7. Configuration and secrets

- `.env` is git-ignored; `.env.example` contains only placeholder values.
- `.env.example` ships with `DB_USERNAME=your_db_user` and
  `DB_PASSWORD=your_db_password` - no working credentials.
- The seeded `admin@example.test` / `password` user is a **local development
  credential only**, documented as such in the seeder and the README.
- `APP_DEBUG=true` is set for local development. **It must be `false` in any
  deployed environment**, or Laravel will render stack traces and environment
  variables in error responses.

---

## 8. Rate limiting

| Scope | Limit |
|---|---|
| `POST /api/auth/token` | 6 requests/minute per IP |
| Failed logins | 5 per minute per email + IP, then 429 |
| All other `/api` routes | 60 requests/minute |

The login throttle is verified by
`AuthTokenTest::it_throttles_repeated_failed_login_attempts`.

---

## 9. Concurrency - verified, not assumed

The claim "two simultaneous requests for the last unit result in exactly one
success" is proven by tests that **fork real OS processes** (`pcntl_fork`), so
two independent database connections genuinely contend for the same row.

| Test | Result |
|---|---|
| 2 processes, 1 unit of stock | exactly 1 created, 1 rejected with 409 |
| 10 processes, 4 units of stock | exactly 4 created, 6 rejected, stock ends at 0 |
| 2 processes, products in opposite order | both succeed, no deadlock |

Deadlocks are avoided by sorting product IDs before locking, so every
transaction in the system acquires locks in the same order.

Run them with:

```bash
php artisan test --group=concurrency
```

### Database requirement

These guarantees depend on **InnoDB row-level locking** (`SELECT ... FOR UPDATE`).
The test suite is configured to run against MySQL for this reason. On SQLite the
concurrency tests **skip with an explanatory message** rather than passing
silently, because SQLite locks the whole database file and cannot demonstrate
row contention.

---

## 10. Known limitations

These are real gaps. They are listed rather than hidden.

- **L1 - No authorisation granularity.** Any valid token can read the order
  history of any customer and create orders for any email address. There are no
  roles, scopes or ownership checks. Before multi-user or customer-facing use,
  add Sanctum token abilities and a policy restricting history reads to the
  owning customer.

- **L2 - The web UI has no authentication.** Every page under `/` is publicly
  reachable. It is intended for localhost development only. Before exposing it,
  add `laravel/breeze` (or equivalent) and an `auth` middleware group.

- **L3 - No HTTPS enforcement.** There is no `forceScheme('https')` or HSTS. A
  bearer token sent over plain HTTP is interceptable. Terminate TLS at the web
  server and enable `URL::forceScheme('https')` in production.

- **L4 - No security headers.** `Content-Security-Policy`,
  `X-Content-Type-Options` and `Referrer-Policy` are not set. Add them at the web
  server or via middleware.

- **L5 - No audit trail.** Orders record what was bought but not who created
  them. There is no `created_by` column and no immutable audit log.

- **L6 - Stock is never restored.** There is no order cancellation or refund
  path, so a cancelled order would leave stock permanently decremented.

- **L7 - Token lifetime is unlimited.** Sanctum tokens never expire. Set the
  `expiration` value in `config/sanctum.php` and schedule
  `sanctum:prune-expired` for production.

- **L8 - No brute-force lockout beyond rate limiting.** Repeated failures
  throttle for 60 seconds but never lock an account.

- **L9 - Single-node rate limiting.** Throttling uses the configured cache store.
  With multiple application servers and a local cache driver, limits apply per
  node. Use a shared Redis cache in production.

- **L10 - No request-size limit in the application.** Body size is left to the
  web server (`client_max_body_size` in nginx, `post_max_size` in PHP).

---

## 11. Pre-deployment checklist

Before this application is exposed beyond localhost:

- [ ] Set `APP_DEBUG=false` and `APP_ENV=production`
- [ ] Generate a fresh `APP_KEY`
- [ ] Replace the seeded development user with a real account and strong password
- [ ] Add authentication to the web UI (L2)
- [ ] Add token abilities and ownership policies (L1)
- [ ] Enforce HTTPS and add HSTS (L3)
- [ ] Add security headers (L4)
- [ ] Set a Sanctum token expiration and schedule pruning (L7)
- [ ] Point rate limiting at a shared cache store (L9)
- [ ] Run `php artisan config:cache route:cache view:cache`
- [ ] Confirm `.env` is not readable from the web root
- [ ] Run the full test suite, including the concurrency group

---

## 12. Reporting a vulnerability

This is a demonstration project with no production deployment. If you adapt it
for real use, replace this section with a genuine contact address and
disclosure policy.
