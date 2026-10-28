# Prompt log

This directory records the AI-assisted development of this project: the prompts
that drove it, the decisions taken along the way, and how to capture evidence of
them.

---

## Honest status of this directory

**No screenshots have been captured.** This file explains how to capture them;
it does not claim they exist. If you add any, put them in `prompts/screenshots/`
and list them in the index below.

The record further down is a factual summary of the development session that
produced this codebase, written from the actual work performed.

---

## How to maintain a prompt log

### 1. Keep one file per working session

```
prompts/
├── README.md                    this file
├── 01-initial-build.md          the session that created the project
├── 02-<topic>.md                later sessions
└── screenshots/                 captured images, if any
```

### 2. Record each significant prompt with its outcome

A useful entry has four parts:

```markdown
## Prompt N - <short title>

**Date:** YYYY-MM-DD

**Prompt:**
> The instruction given, quoted verbatim.

**Outcome:**
- Files created or changed
- Commands run and their result

**Decisions:**
- Anything chosen between alternatives, and why
```

Record the *decision* and its *reason*, not just the instruction. Six months on,
"why is tax rounded per line?" is the question that matters.

### 3. Note corrections

Where the first attempt was wrong, record the failure and the fix. The errors
caught during this build are listed under "Problems found and fixed" below and
are more instructive than the parts that worked immediately.

---

## How to capture screenshots

Screenshots are not generated automatically. To capture them:

### Of the prompts themselves

1. Open the AI tool with the conversation visible.
2. Capture the window:
   - **Windows:** `Win + Shift + S`, or Snipping Tool
   - **macOS:** `Cmd + Shift + 4`, then space to capture a window
   - **Linux:** `gnome-screenshot -w`, Spectacle, or Flameshot
3. Save as `prompts/screenshots/NN-description.png`.
4. **Redact any API keys, tokens, passwords or personal data before saving.**
5. Add a row to the index below.

### Of the application

```bash
php artisan serve
```

Then capture each screen at a few widths:

| Screen | URL | Suggested widths |
|---|---|---|
| Dashboard | `/` | 1440, 768, 390 |
| Products | `/products` | 1440, 390 |
| Customers | `/customers` | 1440 |
| Customer detail | `/customers/1` | 1440 |
| New order | `/orders/create` | 1440, 390 |
| Order confirmation | `/orders/1` | 1440 |
| Order history | `/orders` | 1440, 390 |

The browser DevTools device toolbar (`F12`, then `Ctrl/Cmd + Shift + M`) gives
exact widths. Chrome can capture a full-page image from the command menu
(`Ctrl/Cmd + Shift + P`, then "Capture full size screenshot").

### Screenshot index

| File | Shows | Captured |
|---|---|---|
| _(none yet)_ | | |

---

## Development record

The project was built in ordered stages, each verified before the next began.

### Stage 0 - Inspection

The working directory was empty. The environment probe found PHP, Composer and
MySQL were **not** on the Windows PATH but existed inside a WSL Ubuntu-24.04
distro, and that the `Z:` drive is that distro filesystem. All subsequent
commands were dispatched into WSL.

Found: PHP 8.4.26, Composer 2.9.5, MySQL 8.4.11, Node 22, with `pcntl` and
`pcov` available.

### Stage 1 - Scaffold

`composer create-project laravel/laravel` produced Laravel 13.38.0. Inspecting
the skeleton corrected two planning assumptions:

- It ships **PHPUnit 12.5, not Pest** - so tests were written for PHPUnit.
- The frontend is **Tailwind 4 + Vite 8** with no starter kit and no Alpine.

`php artisan install:api` added Sanctum and `routes/api.php`.

### Stage 2 - Database layer

Four migrations with foreign keys, unique constraints and indexes. Money as
`DECIMAL(12,2)`, tax as `DECIMAL(5,2)`, stock as `UNSIGNED INT`. Models with
explicit `$fillable`. Factories and seeders, with the order seeder driving the
real `OrderService` so seeded data cannot contradict the calculation rules.

### Stage 3 - Money and the order domain

`App\Support\Money` (integer cents) and `OrderCalculator` (pure arithmetic). The
money class was verified with a standalone script covering the float traps
before anything depended on it - all 13 checks passed.

### Stage 4 - API

Three endpoints with Form Requests, API Resources and consistent JSON. Sanctum
token auth with rate limiting, generic credential errors and a timing-attack
mitigation.

### Stage 5 - Concurrency

`OrderService` with `lockForUpdate()`, sorted lock acquisition, a post-lock
stock re-check, and a conditional decrement.

### Stage 6 - Queue job

`SendOrderConfirmation` with `afterCommit()` and an ID-only payload.

### Stage 7 - UI

Blade + Tailwind 4 + Alpine. A design system of `@utility` classes, reusable
components, and the order builder with live preview maths mirroring the server.

### Stage 8 - Tests

110 tests. The concurrency tests fork real OS processes rather than running
sequentially.

### Stage 9 - Deliverables

Postman collection generated programmatically from PHP arrays (guaranteeing
valid JSON), README, SECURITY.md, this log.

---

## Key decisions and their reasons

| Decision | Reason |
|---|---|
| Money as integer cents | `(int)(19.99 * 100)` is `1998` in PHP; float drift becomes real money |
| Money as JSON strings | JSON numbers are IEEE doubles; `19.99` can arrive as `19.989999999999998` |
| Tax rounded per line, then summed | The order total must equal the sum of printed line totals |
| Duplicate products rejected, not merged | Merging silently changes what was asked for |
| 409 for stock, 422 for bad input | A valid request for unavailable stock is a state conflict |
| Pessimistic locking | Stock contention is a frequent, expected collision |
| Locks in sorted ID order | Removes the lock-ordering cycle that causes deadlocks |
| DTO without a price field | Structural guarantee against price tampering |
| Shared service for API and web | The two entry points cannot drift apart |
| MySQL for the test suite | SQLite cannot demonstrate row-level locking |
| Fork real processes in tests | A sequential test proves nothing about concurrency |

### Decisions taken by the project owner

Three choices were put to the owner before implementation:

1. **Authentication** - Sanctum tokens on all API routes (over a public API or a
   hybrid).
2. **Duplicate products in one order** - reject with 422 (over merging
   quantities or keeping separate lines).
3. **Test database** - MySQL for the whole suite (over SQLite, or a split), so
   the concurrency guarantee could be genuinely verified.

---

## Problems found and fixed

Recorded because the failures are more instructive than the successes.

| # | Problem | Cause | Fix |
|---|---|---|---|
| 1 | `migrate:fresh` fatal - incompatible `$afterCommit` | The `Queueable` trait already declares it untyped; redeclaring it as `bool` is a conflict | Call the trait `afterCommit()` method instead |
| 2 | Two models failed to parse | A backslash escaping problem produced a three-character literal in which the backslash escaped the closing quote | Used `chr(92)` so no literal backslash appears in the source |
| 3 | Low-stock `threshold` missing from the response | `additional(['meta' => ...])` is overwritten by the `meta` block that the paginator generates | Moved it to a top-level `filters` key |
| 4 | Vite build failed - cannot apply unknown utility class `btn` | Tailwind 4 cannot `@apply` a sibling class defined in `@layer components` | Redeclared the component classes with `@utility` |
| 5 | Newman run - 46 of 54 assertions failed | Revoke token sat in folder `00`, so a top-to-bottom run killed the token before folders 01-04 ran | Moved revoke into its own folder `05 - Clean up (run last)` |
| 6 | Token-revocation test failed while the real behaviour was correct | The auth guard caches the resolved user within one test process | Verified against a live server first (correctly 401), then added `forgetGuards()` so the test reflects a real request |

Problems 4 and 5 were found only by *running* the build and the collection -
static inspection would have missed both. Problem 6 is a reminder to check
whether a red test means broken code or a broken test.

---

## Verification performed

Every claim in the README was checked rather than assumed:

| Check | Method | Result |
|---|---|---|
| Money arithmetic | Standalone script, 13 cases | All passed |
| Migrations and seeders | `migrate:fresh --seed` | 30 products, 15 customers, 29 orders |
| Order totals reconcile | SQL comparing orders against their lines | No discrepancies |
| Negative stock | SQL `WHERE stock_quantity < 0` | Zero rows |
| All API endpoints | `curl` against a live server | Correct status codes and bodies |
| Price tampering | Posted a fake `grand_total` of `0.01` | Server returned the true `29.49` |
| Rollback on failure | Mixed satisfiable and impossible lines | No order, no customer, stock unchanged |
| Customer reuse | Posted a mixed-case email | Matched the existing row, name preserved |
| Queue pipeline | `queue:work --stop-when-empty` | 3 jobs processed, 0 failed, 3 log lines |
| No secrets in logs | grep for passwords and tokens | Clean |
| All 11 UI screens | HTTP status plus error-page grep | All 200, no exceptions |
| UI and server total parity | Compared the JS preview with the API result | Identical to the cent |
| CSRF | POST without a token | 419 as expected |
| Concurrency | Forked processes racing for stock | 1-of-2 and 4-of-10, no deadlock |
| Postman collection | `newman run` | 22 requests, 54 assertions, 0 failures |
| Route coverage | Cross-checked collection URLs against `route:list` | All 22 matched |

---

## Reproducing this build

The prompt that produced this project asked, in a single brief, for: project
inspection and a plan; a normalised schema with migrations, models, factories
and seeders; three REST endpoints; a security review; concurrency protection
with row locking; a queued confirmation job; a professional responsive UI;
automated tests; a Postman collection; and full documentation - with the
explicit instruction to implement and verify rather than describe, and not to
claim tests passed unless they were actually executed.

Two constraints shaped the result more than anything else:

- *Do not claim concurrency is fully verified by a sequential test.* This is why
  the concurrency tests fork real processes.
- *Never claim an endpoint is protected unless the protection is implemented and
  tested.* This is why SECURITY.md lists limitations L1 to L10 instead of
  implying everything is covered.
