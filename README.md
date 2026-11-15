# Gate-E Checkout: BAB ALBAHRAIN technical assessment

A small shop where a customer picks a product, fills in their details and pays by card through Gate-E without leaving the checkout page. It is plain PHP 8.1 and MySQL (PDO) with Bootstrap 5, jQuery and AJAX, as the brief asked. Besides the seven tasks I added a few things I wanted in a real payment flow: status is always checked against the gateway, the callback can be refreshed safely, and there is an API log and an Arabic interface.

![Checkout](docs/screenshots/01-checkout.png)

## Requirements coverage

| # | Task from the brief | Where it lives | Screenshot |
|---|---|---|---|
| 1 | Gate-E integration with PHP and MySQL | `src/Gateway/GateEClient.php`, `src/Services/PaymentSyncService.php`, `database/schema.sql` | `09-api-logs.png` |
| 2 | Purchase form (name, email, mobile) stored in the DB | `public/index.php`, `src/Core/Validator.php`, `CustomerRepository` | `01-checkout.png`, `02-validation-errors.png`, `db-1-customers.png` |
| 3 | AJAX create-payment and an "initiated" DB record | `public/assets/js/checkout.js`, `public/api/create-payment.php`, `CheckoutService::start()` | `db-2-payments.png` |
| 4 | Payment page inside the same page (iframe, no redirect) | `#panel-pay` in `public/index.php`, `openGateway()` in `checkout.js` | `03-payment-iframe.png` |
| 5 | Redirect to a callback page; payment and transaction details updated | `public/callback.php`, `PaymentSyncService::syncFromGateway()` | `db-3-transactions.png` |
| 6 | Payment and transaction details on the callback page | `public/callback.php` (receipt card) | `04-callback-success.png`, `05-callback-failed.png` |
| 7 | Payments listing; "View" modal with DB and Gate-E data via jQuery AJAX | `public/payments.php`, `payments.js`, `api/payments-list.php`, `api/payment-details.php` | `06-payments-list.png`, `07-payment-modal.png`, `08-modal-comparison.png` |

## Extras

- The callback page, the webhook and the "Refresh status" button all use one sync class (`PaymentSyncService`).
- Statuses: `initiated`, `pending`, `paid`, `failed`, `cancelled`. Invalid changes such as `paid` to `failed` are rejected.
- Refreshing the receipt or a duplicate webhook does not create extra rows.
- The View modal compares the stored record with live Gate-E data and shows a match or mismatch badge.
- API log page with request, response, HTTP code and duration. The API key is masked.
- Dashboard cards on the payments page (total, paid, failed, success rate, revenue).
- Arabic and English with RTL layout, print stylesheet for the receipt, works down to 375px.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ (no framework), PDO, cURL, PSR-4 autoloading with Composer, `vlucas/phpdotenv` |
| Database | MySQL 8 (InnoDB, foreign keys, unique indexes, JSON columns for raw gateway responses) |
| Frontend | HTML5, CSS3, Bootstrap 5.3 (with the RTL build), JavaScript, jQuery 3, AJAX (JSON) |
| Payments | Gate-E REST API: payment create, get and gateway list, plus a webhook |
| Mobile (bonus) | Flutter 3 / Dart, `webview_flutter` |
| Tooling | Cloudflare Tunnel (public webhook URL), Mermaid (diagrams in this README) |

## Features

- Product purchase form with live totals, VAT and client plus server validation
- Gate-E payment page loaded in an iframe inside the same page, with a fallback link
- Callback receipt page and a background webhook, both verified against the gateway
- Payments listing with search, status and date filters, pagination and dashboard cards
- "View" modal comparing the stored record with live Gate-E data
- API log viewer with the API key masked
- Arabic and English with RTL, responsive layout, print stylesheet
- Flutter app that runs the same checkout in a WebView

## Project structure

```
public/                 Web root (php -S serves this)
  index.php             Checkout page
  callback.php          Redirect target / receipt
  payments.php          Payments listing + View modal
  logs.php              API log viewer
  api/                  AJAX endpoints (JSON)
  webhook/gate-e.php    Gate-E server-to-server notification
  assets/               CSS, JS (jQuery), images
src/
  Core/                 Env, Database (PDO), Csrf, Validator, Logger, RateLimiter, Lang, Response
  Gateway/              GateEClient (cURL), GatewayApi interface, StatusMapper
  Repositories/         Customer, Payment, Transaction, Product, ApiLog
  Services/             CheckoutService, PaymentSyncService, PaymentDetailsService
config/ lang/ views/    Settings, EN/AR strings, shared templates
database/               schema.sql, seed.sql
mobile/                 Flutter WebView app
docs/                   Architecture notes, API samples, screenshots
```

## Setup (about 5 minutes)

Requirements: PHP 8.1+ with `pdo_mysql`, `curl`, `mbstring`; MySQL 8; Composer.

```bash
composer install
cp .env.example .env          # then fill in the values below
mysql -uroot < database/schema.sql
mysql -uroot < database/seed.sql
php -S localhost:8000 -t public
```

`.env`:

```
APP_URL=http://localhost:8000          # the public URL once a tunnel is running
DB_HOST=127.0.0.1
DB_NAME=gatee_shop
DB_USER=root
DB_PASS=
GATEE_API_URL=https://api01.gate-e.net/
GATEE_API_USER=...
GATEE_API_KEY=...
GATEE_GATEWAY_ID=10117701907931638     # from gateway/list
```

**Tunnel for the webhook.** Gate-E cannot reach `localhost`, so the server-to-server notification needs a public URL:

```bash
cloudflared tunnel --url http://localhost:8000     # or: ngrok http 8000
```

Put the printed `https://...` address in `APP_URL`, restart the PHP server, and browse the app through that address. The customer redirect works on localhost too; only the webhook needs the tunnel.

Test card: `1111 2222 3333 4444`, expiry `12/30` (CVV results are listed under *How to use it*).

Pages: `/` checkout, `/payments.php` listing, `/logs.php` API logs. Add `?lang=ar` for Arabic.

## How to use it

1. Open the checkout (`/`), choose a product and quantity, then enter name, email and a Bahraini mobile number (8 digits starting 1, 3 or 6).
2. Press **Pay now**. The payment is created through AJAX and the Gate-E card form opens inside the page.
3. Pay with the test card. The browser returns to the receipt page, which shows the result, amount, reference, transaction id and masked card.
4. Open **Payments** to see every attempt. Press **View** for the database record next to the live Gate-E data, and **Refresh status** to re-sync.
5. Open **API logs** to inspect each request, response and webhook.

| Test CVV | Result |
|---|---|
| `123` | Payment succeeds |
| `999` | Payment fails |
| any other | Gate-E lets the customer re-enter the card; nothing is recorded |

## Mobile app (bonus)

`mobile/` holds a small Flutter app that opens the same checkout in a WebView, with a progress bar, an offline retry screen and a URL allow-list. See [`mobile/README.md`](mobile/README.md) for how to run it on an emulator, a phone or Chrome.

## Screenshots

| | |
|---|---|
| ![Validation](docs/screenshots/02-validation-errors.png) | ![Payment iframe](docs/screenshots/03-payment-iframe.png) |
| ![Success](docs/screenshots/04-callback-success.png) | ![Failed](docs/screenshots/05-callback-failed.png) |
| ![List](docs/screenshots/06-payments-list.png) | ![Modal](docs/screenshots/08-modal-comparison.png) |
| ![API logs](docs/screenshots/09-api-logs.png) | ![Arabic](docs/screenshots/10-checkout-arabic.png) |

Database tables: [customers](docs/screenshots/db-1-customers.png), [payments](docs/screenshots/db-2-payments.png), [transactions](docs/screenshots/db-3-transactions.png), [api_logs](docs/screenshots/db-4-api-logs.png).

## Payment flow

```mermaid
sequenceDiagram
    autonumber
    participant B as Browser
    participant P as My PHP
    participant D as MySQL
    participant G as Gate-E

    B->>P: POST /api/create-payment.php (AJAX, CSRF token)
    P->>P: validate, rate limit, price from DB
    P->>D: insert customer + payment (initiated)
    P->>G: payment/create
    G-->>P: payment id + link
    P->>D: attach gateway id, status pending
    P-->>B: JSON { payment_url }
    B->>G: iframe loads payment_url
    Note over B,G: customer pays inside the iframe
    G-->>P: POST /webhook/gate-e.php (code_id)
    P->>G: payment/get (verify)
    P->>D: lock row, upsert transaction, update status
    P-->>G: 200 + code_id
    G-->>B: redirect to /callback.php?payment_id&payment_code
    B->>P: GET /callback.php (breaks out of the iframe)
    P->>G: payment/get (verify again)
    P->>D: sync is a no-op if the webhook already ran
    P-->>B: receipt page
```

## Key engineering decisions

- **Never trust the browser for money.** The price is read from `products` by id and the VAT is computed on the server. A tampered `price` field in the request is ignored; I tested it.
- **Verify, don't believe.** The redirect query string and the webhook body only tell me which payment to look at. The status always comes from `payment/get`. A payment is marked paid only if the gateway amount equals the order amount.
- **Idempotency by construction.** Each sync fetches from Gate-E first, then opens a DB transaction and locks the payment row with `SELECT ... FOR UPDATE`. Transactions have a unique `(payment_id, fingerprint)` key, so replaying the same data upserts the same row.
- **One sync service.** There is exactly one code path that turns a gateway response into database state, so the callback, webhook and manual refresh cannot disagree.
- **State machine, with one deliberate widening.** `failed` and `cancelled` may still move to `paid`. The gateway page lets a customer retry on the same payment, and a payment cancelled on my side could still be completed at Gate-E, where the verified gateway status is the truth. `paid` is final.
- **Thin endpoints.** Each endpoint validates, calls a service and returns `{ success, data, message, errors }`.
- **Network versus business errors.** cURL has connect and read timeouts and retries once, only on network failures. A business error from Gate-E is never retried. Users see a clean message and the log gets the detail.

## Security measures

- CSRF token on every state-changing request (the webhook is server-to-server and is verified through `payment/get` instead).
- Prepared statements only; `htmlspecialchars` on every output; JSON written into the page is escaped with `JSON_HEX_*`.
- Server-side validation: email, Bahraini mobile (8 digits starting 1, 3 or 6), quantity range, active product.
- Session rate limit on create-payment (10 attempts per 5 minutes).
- Headers: `Content-Security-Policy` (scripts and styles limited to self plus the CDN, `frame-src` allows `*.gate-e.net`), `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`; session cookie is `HttpOnly` and `SameSite=Lax`.
- Secrets only in `.env`; the API key is masked in `api_logs` and in the application log.
- Cancel is limited to payments created in the same browser session; the retry link is HMAC-signed.

## Known limitations and what I would do next in production

- **The real webhook differs from the brief.** The delivery has no top-level `code_id`; the payment is under `data.payment` (`id` and `code`), alongside a `callback_key`. The endpoint reads the payment from there, still accepts `code_id`, and echoes the identifier it received. I could not confirm what Gate-E expects in the reply beyond HTTP 200, so that part is the one thing I would check with their support. The body is never trusted anyway: the status comes from `payment/get`.
- **Gate-E response fields.** `StatusMapper` and the transaction mapping were checked against real paid and failed test-card responses (`docs/api-samples/`). Only the test gateway was available, so other card brands and 3-D Secure fields are untested.
- **Notification settings.** The brief mentions turning notification sending off, but the field name is not in the sample I had, so I did not guess it.
- **No authentication on `/payments.php` and `/logs.php`.** Acceptable for an assessment; in production they sit behind an admin login with roles.
- **Webhooks are processed inline.** In production I would acknowledge immediately, push the job to a queue and process it with retries.
- **Reconciliation.** A cron job that re-syncs payments stuck in `pending` after N minutes, plus alerting on mismatches between my database and Gate-E.
- **Monitoring.** Structured logs shipped to a central place, alerts on gateway error rate and latency.
- **Tests.** The sync service already depends on a `GatewayApi` interface so it can be tested with a fake gateway; I would add a proper PHPUnit suite and a CI job.
