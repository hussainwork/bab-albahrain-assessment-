# Gate-E Payment Integration — Bab Albahrain Assessment

**Deadline:** 16/11/2026 — 09:00 AM (submit by the evening of 15/11 to be safe)
**Stack (required by them):** PHP 8 · MySQL · HTML · CSS · Bootstrap 5 · JavaScript · jQuery · AJAX
**Rule:** Local git only. Do **not** push to GitHub.

---

## Phase 0 — Setup & API discovery
- [x] Install PHP 8.1+, MySQL 8, Composer (optional), and a local server (XAMPP / Laragon / `php -S`)
- [x] Create the project folder and run `git init` (local commits only, no remote)
- [x] Create `.env` with `GATEE_API_URL`, `GATEE_API_USER`, `GATEE_API_KEY`, `APP_URL`, DB credentials
- [x] Add `.env` to `.gitignore` and create `.env.example` with blank values
- [x] Import the Postman collection and run **Gateway → list** to get a valid `gateway.id`
- [x] Run **Payment → create** once in Postman and save the raw JSON response to `docs/api-samples/`
- [x] Run **Payment → get** with the returned id and save that response too
- [ ] Write down the exact field names for: payment id, payment code, payment page URL, status, and transaction details
- [ ] Install ngrok (or Cloudflare Tunnel) so the gateway can reach your callback URL

## Phase 1 — Database design
- [ ] Create `database/schema.sql`
- [ ] `products` table (id, name, description, price, currency, image, is_active)
- [ ] `customers` table (id, name, email, mobile_code, mobile, created_at)
- [ ] `payments` table (id, customer_id, product_id, order_reference, amount, currency, gateway_payment_id, gateway_payment_code, payment_url, status, created_at, updated_at)
- [ ] `transactions` table (id, payment_id, gateway_transaction_id, status, card_masked, auth_code, response_message, raw_response JSON, created_at)
- [ ] `api_logs` table (id, direction, endpoint/action, request JSON with key masked, response JSON, http_code, duration_ms, created_at)
- [ ] Indexes and foreign keys; a unique index on `order_reference`
- [ ] `database/seed.sql` with 2–3 demo products

## Phase 2 — Core backend
- [ ] `config/` loads `.env` and exposes settings
- [ ] `src/Database.php`: a PDO singleton with prepared statements only
- [ ] `src/GateEClient.php`: a cURL wrapper with `createPayment()`, `getPayment()`, `listGateways()`, timeouts, error handling, and logging to `api_logs`
- [ ] Repository classes: `PaymentRepository`, `CustomerRepository`, `TransactionRepository`
- [ ] A status-mapping helper (gateway status → `initiated / pending / paid / failed / cancelled`)
- [ ] CSRF token helper and input validation helper
- [ ] JSON response helper for AJAX endpoints

## Phase 3 — Checkout page (tasks 2, 3, 4)
- [ ] `public/index.php`: product card plus a purchase form (name, email, mobile with +973 code)
- [ ] Client-side validation with jQuery and Bootstrap validation styles
- [ ] Server-side validation (never trust the client), with the price taken from the DB and not from the form
- [ ] `api/create-payment.php` (AJAX POST): save the customer, insert a payment with status `initiated`, call Gate-E, and update the row with the gateway id, code, and URL
- [ ] Return the payment URL as JSON and load it in an **iframe** inside the same page (Bootstrap modal or inline panel)
- [ ] Loading spinner, disabled submit button (no double submit), and clear error alerts
- [ ] Handle the case where the gateway blocks framing (X-Frame-Options) by showing a fallback "Open payment page" button

## Phase 4 — Callback & webhook (tasks 5, 6)
- [ ] `public/callback.php`: the redirect target, which receives `payment_id` and `payment_code`
- [ ] Callback page calls `getPayment()` server-side to **verify** status (never trust query params alone)
- [ ] Update the `payments` row and insert/update the `transactions` row inside a DB transaction
- [ ] Make it idempotent: refreshing the callback must not create duplicate transactions
- [ ] Iframe breakout: if callback loads inside the iframe, redirect `window.top` to the full callback page
- [ ] Show a clear result page: success/failed badge, amount, reference, transaction id, card (masked), date, and a "Back to shop" link
- [ ] `api/webhook.php`: receive the background POST, log it, verify via `getPayment()`, update the DB, return HTTP 200 and echo `code_id`
- [ ] Test CVV `123` (success), `999` (failed), and another CVV (retry, nothing recorded)

## Phase 5 — Payments listing (task 7)
- [ ] `public/payments.php`: a Bootstrap table of all payments (ref, customer, amount, status badge, date, View button)
- [ ] Search, a status filter, and pagination (server-side, via AJAX)
- [ ] `api/payment-details.php`: return DB record + transactions + live `getPayment()` result as one JSON
- [ ] "View" opens a modal with two sections: **Stored in database** and **Live from Gate-E**, plus a "match / mismatch" indicator
- [ ] "Refresh status" button in the modal that re-syncs the DB from the gateway

## Phase 6 — Senior-level extras (what makes you stand out)
- [ ] Small dashboard cards on the listing page: total payments, paid, failed, success rate, total revenue
- [ ] `api_logs` viewer page (request/response audit trail with the API key masked)
- [ ] Security pass: CSRF on all POSTs, `htmlspecialchars` on output, prepared statements, rate-limit create-payment per session, and no secrets in the JS
- [ ] Consistent error handling and a user-friendly 500 page; errors logged to `storage/logs/app.log`
- [ ] Arabic/English toggle on the checkout page (optional, but strong for a Bahraini company)
- [ ] Responsive check on mobile width
- [ ] Optional bonus: a small Flutter app that opens the same checkout in a WebView (shows the mobile skill they asked for)

## Phase 7 — Testing
- [ ] Full happy path with CVV 123 and DB rows verified
- [ ] Failure path with CVV 999 and DB rows verified
- [ ] Retry path with another CVV, confirming no failed record is written
- [ ] Invalid form input (empty, bad email, bad mobile)
- [ ] Gateway down / wrong API key: the app shows a clean error and logs it
- [ ] Refresh the callback page repeatedly and confirm there are no duplicates
- [ ] View modal shows matching DB and API data

## Phase 8 — Documentation & screenshots
- [ ] `README.md`: overview, architecture diagram, setup steps, env vars, flow explanation, security decisions, known limitations
- [ ] Payment flow sequence diagram (Mermaid in README)
- [ ] Screenshots in `docs/screenshots/`: checkout form, validation errors, iframe payment page, success callback, failed callback, payments list, View modal, dashboard, API logs
- [ ] Screenshots of DB tables (phpMyAdmin / TablePlus): `customers`, `payments`, `transactions`, `api_logs`
- [ ] Optional: a 2–3 minute screen-recorded demo video (strong signal)

## Phase 9 — Submission
- [ ] Zip the project (exclude `.env`, include `.env.example` + `schema.sql`)
- [ ] Put the screenshots into one PDF report (cover page, flow, screens, DB tables)
- [ ] Write the reply email: short summary, what you built beyond the brief, how to run it, attachments
- [ ] Add portfolio projects (hussainwork.com, SooQna, Vault, WashGo) with your role clearly stated
- [ ] Re-read their email once more and check every one of the 7 tasks is covered
- [ ] Send before 16/11/2026 09:00 AM