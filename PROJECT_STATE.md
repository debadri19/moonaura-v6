# MoonAura Crystals — Project State

> **Latest checkpoint — v0.6.7:** GA4 Measurement ID, Property ID, and credentials path are set in gitignored `.env`. Service-account JSON stays outside the web root and is gitignored. Frozen storefront navigation, Account nav chevron, Razorpay mapping guard, configured-gateway checkout, Shop card actions, asset URLs, Certificate Included, and homepage newsletter remain. Public `/sitemap.xml` is generated dynamically from the live catalog (`sitemap.php`); `robots.txt` points crawlers at it.


> **Read this file first in any new session.** It reflects the actual
> current state of the codebase, not a plan or a wishlist. If something
> here conflicts with what you find in the code, the code wins - flag
> the mismatch and update this file.

**Current version: v0.6.7** - Phase 5A (UI/UX & Order Tracking),
Phase 5B (Customer Forgot Password), Phase 5C (Admin Manual Order
Create), Phase 5D Step 1 (Email Infrastructure + Forgot-Password
Emails), Phase 5E (Admin Security Hardening + TOTP 2FA), Phase 5F
(Inventory Automation) and Phase 5G (Login Security) are implemented
and locally verified; the remaining Phase 5D steps
(order/shipping/delivery/marketing emails - out of scope for Step 1)
and Phase 5H are pending (see §2/§3/§4). Historical status
narrative from earlier phases follows unchanged:

NOTE ON PHASE LABELS: the roadmap has reused "5G" twice. Phase 5G
(Login Security) shipped earlier - see §2/§4 below. This session's
work is the GST-invoice Phase 5G (production-ready GST-compliant
invoice system + invoice branding layer) - the invoice lineage that
Phases 3B/3C/3D started. Both rows are kept in the table in §2 with
their scope spelled out.

Last updated (historical): Phase 4C (Multi-Gateway Payment Checkout)
and Phase 4D (Manual UPI QR Payment) - both now complete, including
the `admin/settings.php` page and the Manual UPI verify/reject
workflow on `admin/order-detail.php` that Phase 4C/4D's initial
implementation had left unbuilt (see the audit note below and
§2/§4/§5 for full detail).
Phase 4B (UI/UX Refinement) - customer-facing audit series **complete**,
10 batches: Footer/Cart/Wishlist/Orders/Account Typography, Shop Page +
Product Cards, About Us/Home overflow fix + Home-About-Shop "Why
Choose" consolidation, Authentication + Shared Forms audit,
Customer-Facing Button-System audit, Tables audit, Notifications
audit, Loading States audit, documentation sync, and the Final Design
System Audit (incl. a dedicated Product Page Audit). Full
batch-by-batch detail in §2's Phase 4B row and `CHANGELOG.md`. Two
items this file previously claimed as already complete were found to
be incomplete when actually re-checked against the code that session:
the About Us grid fix (only the desktop 4-column track had the
`minmax(0,1fr)` fix - the 992px 2-column breakpoint still had the
bare-`1fr` overflow bug, plus a separate `white-space:nowrap` bug on
both About and Home's card titles) and the "8-file" button-system
rollout (that count only ever covered the Customer Account + Auth
pages - a further 13 instances of the identical missing-`.btn`-base-
class bug were found and fixed across Cart, Checkout, Payment, Payment
Failure, Order Success, and Product Details). Both were corrected in
the code and in this file. The Final Design System Audit also caught
two more real bugs - one accessibility issue and one self-introduced
icon-syntax inconsistency, both fixed.

**Phase 4C/4D audit note:** an initial implementation of the
multi-gateway checkout model and the Manual UPI QR feature was built
directly against the codebase (not through the usual session-by-
session process reflected elsewhere in this file), and this file/
`CHANGELOG.md` were never updated to describe it - the first thing
done once that was discovered was an audit against the approved
specification (see §4/§5) before writing anything further. That audit
found the checkout-facing half of both features correctly built and
working, but two genuinely critical pieces missing entirely:
`admin/settings.php` (referenced by name in three places in the
existing code/docs, but the file didn't exist) and the Manual UPI
"Verify Payment"/"Reject Payment" admin action (also referenced by
name, also missing - and more specifically, `admin/order-detail.php`
had no order/payment status update mechanism of any kind, for any
payment method). Both are now built - see §2's Phase 4C/4D row.

---

## 1. Current Project Status

**v0.5.0 status:** Phase 5A (UI/UX & Order Tracking), 5B (Customer
Forgot Password), 5F (Inventory Automation) and 5G (Login Security)
are complete and verified - product search (header icon →
`shop.php?q=`), order tracking & timeline, the customer forgot/reset
password flow (mirroring the admin version), the stock
deduction/restore lifecycle, and admin + customer login lockout are
all live. Remaining Phase 5 roadmap, all **not started**: 5C Admin
Manual Order Create, 5D Customer Management, 5E Email Notifications,
5H POS / Walk-in Sales. The earlier-phase narrative below is
preserved as history.

Static HTML/CSS/JS site has been converted into a PHP 8 + MySQL
ecommerce platform in phases, each explicitly scoped and approved
before implementation. Guest checkout, customer accounts, admin
panel, full commerce foundation, and payment integration (Cashfree
active, Razorpay available, Cash on Delivery) are all implemented and
you've confirmed Cashfree working end-to-end on a real server. Phase
3B (Invoice System) is implemented. Phase 3C polished the invoice's
item-table header and made the admin Orders menu functional (list +
read-only detail view). This session (Phase 3D) reviewed every
Customer Account page and the Admin Panel end to end, fixed the real
gaps found, and confirmed everything else was already complete -
see the summary right below and §2/§4 for detail.

**Phase 3D review findings (this session):**

Customer Account - reviewed Dashboard, Orders, Order Details, Saved
Addresses, Profile, and the Success page. Four of six were already
complete with nothing worth changing (Orders list, Saved Addresses,
Profile, Success page - all fully functional, correctly styled,
nothing placeholder). Two had real, fixable gaps:
- **Order Details** was silently dropping the `discount` line from
  the total breakdown shown to the customer (the column exists on
  `orders` and is used elsewhere, just never rendered here), didn't
  show Payment Method or per-item unit price, and had no way back to
  the order list except the browser back button. Fixed - see §2.
- **Dashboard** was three static summary-count cards and nothing
  else - functional, but so bare it barely qualified as a dashboard.
  Added a Recent Orders preview (reusing the exact table/badge
  markup `orders.php` already uses). See §2.

Admin Panel - reviewed the newly-enabled Orders module plus the
dashboard:
- **`admin/dashboard.php` was a genuine, misleading placeholder** -
  its Products and Orders cards still said "Coming in the next/future
  phase" despite both modules being live (Products since Phase 1A,
  Orders since Phase 3C). Nobody had gone back to update this page
  after either module shipped. Fixed - both cards now show live
  counts and link to their real pages; Customers stays an honest
  placeholder, since that module genuinely doesn't exist. See §2.
- **`admin/orders.php`** had no status filter and no result count -
  functional but bare for a list that could grow long. Added a status
  filter dropdown (combines with the existing search) and a count
  line. See §2.
- **`admin/order-detail.php`** had no way to actually see an
  invoice - `orders.invoice_number` was displayed as text but there
  was no admin-facing way to view/generate the PDF (only the
  customer-facing, login-gated `account/invoice.php` existed). Added
  a small, purely read-only `admin/invoice.php` wrapper that reuses
  the exact same invoice generation functions - no new invoice logic,
  no order edits. See §2.
- Confirmed no order editing/status-change/refund/shipping UI exists
  anywhere in admin at that time, as instructed - that was
  deliberately unimplemented pending the "Order Timeline"/"Shipping
  Integration" phase. **That phase has since shipped as Phase 5A**:
  admin now has order-status, payment-status (COD/Manual UPI), and
  shipping/tracking update forms on `admin/order-detail.php` (§2).

**Correction (this session):** a prior session's handoff note here
claimed guest invoice download "is not wired up" because
`account/invoice.php` never calls `verify_guest_invoice_token()`.
That was **wrong** - it looked only at `account/invoice.php` and
missed that the guest flow is a deliberately separate file,
`guest-invoice.php` (project root, alongside `order-success.php`),
not a branch inside `account/invoice.php`. Re-traced the complete
chain end to end this session and confirmed it's fully implemented
and correct:
`order-success.php` calls `generate_guest_invoice_token($order['order_number'])`
→ builds Download/Print links to `guest-invoice.php?order=...&exp=...&sig=...`
→ `guest-invoice.php` calls `verify_guest_invoice_token()` (HMAC-SHA256
over `orderNumber|expiresAt`, time-limited, `hash_equals()` comparison)
→ on success, calls the exact same `get_or_create_invoice_number()` +
`build_invoice_pdf()` that `account/invoice.php` uses, so invoice
numbering/PDF rendering is not duplicated anywhere. `account/invoice.php`
itself is untouched and still login-only, by design - it's not the
guest entry point and was never supposed to be.
**One real, separate finding**: this is currently **inactive on this
specific `.env`** - `INVOICE_TOKEN_SECRET` is present as a key but its
value is empty, and both token functions fail closed on that (by
design, same pattern as `CASHFREE_SECRET_KEY`) - so
`generate_guest_invoice_token()` returns `null` and the Download/Print
buttons on `order-success.php` don't render at all right now, not
because of a code bug. `.env` already has a comment with the exact
command to generate a real value
(`php -r "echo bin2hex(random_bytes(32));"`) - this just hasn't been
run yet on this environment. Once that's set, the feature is live with
zero code changes.
`account/invoice.php`'s own doc comment still says "guest-order
invoice download is not wired up yet" - that line is now stale (it
predates `guest-invoice.php`) but wasn't corrected this session, since
this session's task was verification + updating this file and
CHANGELOG.md specifically, not further code edits.

---

## 2. Completed Phases (approved)

| Phase | Scope | Status |
|---|---|---|
| 0 | PHP/MySQL foundation - config, DB connection, header/footer→includes, admin login/dashboard shell | ✅ Approved |
| 1A | Admin Categories + Products CRUD, image upload/management | ✅ Approved |
| 1B | Dynamic Homepage Featured Collection, dynamic Shop grid, category filter/sort/pagination | ✅ Approved |
| 1C | Dynamic Product Detail page (gallery, SEO/JSON-LD, related products, 404 handling) | ✅ Approved |
| 1 (finishing task) | Wired homepage/shop product cards to product.php | ✅ Approved |
| 2A | Commerce foundation - orders/order_items/order_addresses schema | ✅ Approved |
| 2B | Session-based cart (add/update/remove/count/page) + quantity clamping + product-availability validation | ✅ Approved |
| 2C | Checkout - guest checkout, server-side validation/totals, transaction-safe order creation, order-success page | ✅ Approved |
| 2D | Customer accounts (register/login/logout/dashboard/orders/addresses/profile/change-password), guest-order linking, admin Forgot Password | ✅ Approved |
| 3 Pre-fixes | Session/auth hardening, order+invoice numbering rewrite (daily reset), dead code removal, full security audit | ✅ Approved |
| Bugfix | Shop page MySQL 8 `DISTINCT`+`ORDER BY` incompatibility in `get_shop_categories()` | ✅ Fixed (static-verified) |
| Bugfix | Checkout schema drift - missing `daily_sequences` table and `order_addresses.landmark` column on a database with incompletely-applied migrations | ✅ Fixed and confirmed - debug line removed from `checkout.php` |
| 3A | Payment integration - Cashfree (active) + Razorpay (kept, inactive) via `PaymentManager` abstraction, gateway-agnostic `settings` table, Cash on Delivery | ✅ Confirmed working end-to-end (per your report) |
| 3B | Invoice System - lazy invoice number/date generation, on-demand GST invoice PDF (no stored files), customer-account Download/Print | 🔨 Implemented, **awaiting your real-server verification** |
| 3C (partial) | Invoice UI polish (purple item-table header) + Admin Orders module (list + read-only detail view) | 🔨 Implemented, **awaiting your real-server verification** - see §4 |
| 3D | Customer & Admin Experience review - fixed real gaps in account/order-detail.php (discount/payment method/back link), account/dashboard.php (Recent Orders), admin/dashboard.php (live Products/Orders cards), admin/orders.php (status filter), admin/invoice.php (new, read-only) | 🔨 Implemented, **awaiting your real-server verification** - see §4 |
| 4A | Wishlist - session-based (mirrors the cart), new `wishlist.php` page, wired up the pre-existing-but-dead heart icon on shop.php/product.php, header icon+count badge. Also fixed the product-card action buttons having no CSS anywhere (found while reusing that component) | 🔨 Implemented this session, **awaiting your real-server verification** - see §4 |
| 4B | UI/UX Refinement - full customer-facing audit series, **complete** (10 batches): Footer white-strip fix, Cart/Wishlist Empty States, Orders Page + Account Typography, Shop Page rework (Hero/Filters/Collection/Why-Choose-reuse/Need-Help-reuse), Product Card fixed-height layout, About Us grid overflow fix (corrected - see note below), Home page same fix + Home/About/Shop "Why Choose" component consolidation, Authentication + Shared Forms audit, Customer-Facing Button-System audit (13 more missing-`.btn` instances found beyond the earlier 8-file pass), Tables audit (account-table mobile scroll wrapper, cart mobile grid-area bug), Notifications audit (no changes needed - already consistent), Loading States audit (no changes needed - none exist anywhere, consistently), Final Design System Audit incl. Product Page Audit (found + fixed one accessibility bug and one icon-syntax inconsistency; reviewed Cards/Typography/Colors/Shadows/Hover/Focus/Icons/Border-radius/Responsive/Accessibility with no further genuine bugs found) | ✅ Audit series complete, **all shipped work awaiting your real-server verification** - see §4 |
| 4C | Multi-Gateway Payment Checkout - replaced the single `active_payment_gateway` setting with one `*_enabled` flag per gateway (`cashfree_enabled`, `razorpay_enabled`, `phonepe_enabled`) plus `default_payment_gateway` (pre-selection only, never which options show); `PaymentManager::createPayment()` now resolves the gateway from the order's own stored `payment_method` rather than a global "active" setting; checkout shows one labeled radio per enabled gateway. PhonePe investigated (PG API v2, OAuth client-credentials, non-self-serve support-ticket onboarding, distinct webhook verification) and deliberately **not** implemented blindly - `phonepe_enabled` exists as a real, inert flag. `admin/settings.php` (Payment Methods toggles + Default Gateway dropdown, both validated server-side) built this session - the checkout-facing half already existed when audited, this page did not | ✅ Complete, **awaiting your real-server verification** - see §4 |
| 4D | Manual UPI QR Payment - a fifth checkout option (QR code / UPI ID / account name, configured via `admin/settings.php`); customer submits a UTR + optional screenshot on `manual-upi-payment.php`, which logs a `payment_transactions` row (`status = 'submitted'`) without marking the order paid. `admin/order-detail.php`'s Verify Payment (`payment_status = 'paid'`, `order_status = 'processing'`) / Reject Payment (`payment_status = 'failed'`) actions built this session - the customer-facing half already existed when audited, this admin workflow did not (the whole feature was unusable in production without it - see §5) | ✅ Complete, **awaiting your real-server verification** - see §4 |
| 5A | UI/UX & Order Tracking - product search (header search icon wired to `shop.php?q=`, search across product name/category/description/purpose/zodiac with escaped-LIKE + prepared params), order tracking & timeline (`order_status_history` table, `log_order_status_event()`/`update_order_status()`/`update_order_payment_status()` recording real milestones, admin shipping/tracking fields, admin order-status/payment-status/shipping update forms, customer timeline + tracking display on shipped/delivered orders) | ✅ Implemented & verified locally - see §4 |
| 5F | Inventory Automation - stock deduction at each payment method's confirmation point (COD at placement, Manual UPI only on admin verify, gateways only after payment confirmation via `PaymentManager`), `orders.stock_deducted_at` idempotency marker (no double-deduction/restore, no payment-retry mismatch), restore on admin cancel, guarded `UPDATE ... AND stock_quantity >= ?` no-negative-stock guarantee, auto `out_of_stock`/`in_stock` flip, cart stock preflight messages. Pre-existing orders never retroactively deducted (backward compatible). See `includes/stock-functions.php` | ✅ Implemented & verified locally - see §4 |
| 5G | Login Security - `login_attempts` table + 5-failed-attempts / 15-minute lock for BOTH admin and customer login (`includes/login-security.php`), no-account-enumeration messages, lazy expiry cleanup, success clears the record. Phase 6 task was originally labelled "Phase 6" internally but is normalized to Phase 5G in this roadmap | ✅ Implemented & verified locally - see §4 |
| 5G (invoice) | Production GST-compliant invoice system + invoice branding layer - per-line GST Rate column in the items table, Taxable Value / CGST / SGST / IGST breakdown (from the Phase 5F snapshot, never recalculated, rate labels only on single-rate orders), PDF /Info metadata (Title/Author/Subject/Keywords/Creator/Producer/CreationDate/ModDate, deterministic from stored data), "A Brand by DS Lifestyle" letterhead tagline + optional business_website line, Business / GST admin settings section (`admin/settings.php`: business_name / trade_name / gstin / address / phone / email / website with GSTIN-format + URL validation), admin invoice `?mode=download` + Download link on order-detail. Deliberately NOT adding PDF owner-password/read-only encryption (unsupported by the hand-rolled writer - documented, not faked). Same label as Login-Security 5G above - see the note in §1 | ✅ Implemented & verified locally - see §4 (`/tmp/opencode/t_phase5g_invoice.php`, 42/42) |
| 5B | Customer Forgot Password - customer-side password reset flow mirroring the existing admin Forgot/Reset Password (`account/forgot-password.php` + `account/reset-password.php`, `customer_password_resets` table with hashed 60-min single-use tokens, Phase 5G lockout reuse, "Forgot your password?" link on the customer login page; link shown on-page as a placeholder until Phase 5E email). See `database/migration_phase5b_customer_password_reset.sql` | ✅ Implemented & verified locally - see §4 |

## 3. Pending Phases

| Phase | Scope | Status |
|---|---|---|
| 5C | Admin Manual Order Create - admin places orders on behalf of customers | ✅ Implemented & verified locally - see §4 (`/tmp/opencode/p5c_test.sh`, 62/62) |
| 5D | Email Notifications - SMTP email subsystem + order/admin notification emails. Step 1 (mailer + Forgot-Password emails, both customer & admin) done; order/shipping/delivery/marketing emails remain | 🔨 Step 1 implemented & verified locally - see §4 (`/tmp/opencode/p5d_test.sh`, 30/30) |
| 5E | Admin Security Hardening + TOTP Two-Factor Authentication - session hardening (idle timeout, UA binding, POST+CSRF logout, security headers), RFC 6238 TOTP 2FA for admin logins with encrypted secrets, 2FA setup page, fail-closed OTP verify | ✅ Implemented & verified locally - see §4 (`/tmp/opencode/p5e_test.sh` 44/44 + `/tmp/opencode/p5e_failclosed.sh` 11/11) |
| 5H | POS / Walk-in Sales - point of sale for walk-in customers | Not started |
| (later) | Not yet scoped candidates: real `PhonePeGateway` implementation (once real client_id/client_secret/client_version are obtained via PhonePe's support-ticket onboarding - see §5), Shipping Integration, refunds, saved cards, subscriptions, UPI intent, EMI, live gateway mode, account-linked (DB) wishlist, unifying the two different product-card markups (see §5) | Not started |

---

## 4. Runtime Verification Status

**Phases 5A/5F/5G (v0.4.8, this session) - verified live against a
running PHP 8 + MySQL dev server (`moonaura` DB):**
- **5A Search:** header icon routes to `shop.php?q=`; matches on name
  (`amethyst`), purpose (`love`, `protection`) and zodiac (`pisces`);
  `q`+category combination works; no-match empty state; literal
  `%`/`_` return 200; search value persists in the input
- **5F Inventory:** 60 automated checks green (26 unit + 16 HTTP
  stock + 18 login). COD deducts at placement, Manual UPI only on
  admin verify (reject doesn't), gateways only after payment
  confirmation; double-callback/retry idempotency; admin cancel
  restores + clears marker; reactivation re-deducts (paid/COD only);
  no negative stock via checkout; friendly shortage preflight; fresh
  install (`schema.sql` + `seed.sql`) loads clean; migrations
  idempotent across `moonaura`/`mu_upgrade_test`/`mu_fresh_test`;
  pre-existing orders keep `stock_deducted_at = NULL`
- **5G Login Security:** 5 failed attempts → 15-minute lock for both
  admin and customer login; locked email rejected before password
  check; "Too many failed login attempts" message; no counter growth
  while locked; lazy expiry → fresh start; success clears record;
  unknown email returns generic "Incorrect email or password."
  (no enumeration)
- **5G (invoice production, this session):** 42 automated checks green
  (`/tmp/opencode/t_phase5g_invoice.php`), all prior regressions re-run
  green (5F GST 60/60, 5D2 email 22/22, 5E/5F.1 24/24, failure-path
  harness), every generated PDF validates with pypdf. Verified: per-line
  GST Rate column (single/mixed/zero-rated orders), Taxable Value +
  CGST/SGST/IGST breakdown with rate labels only on single-rate orders,
  byte-identical regeneration (determinism incl. metadata), invoice
  numbering format/uniqueness/idempotency, business settings drive the
  letterhead (set → verify → restore), metadata /Info dict + trailer
  reference, metadata-free output byte-identical to the old format,
  40-line-item invoice paginates with the table header redrawn on page
  2. HTTP: admin settings POST saves GSTIN (uppercased) + website
  (https:// normalized) and rejects an invalid GSTIN / empty name;
  admin/invoice.php `?mode=download` returns `Content-Disposition:
  attachment` (inline by default), unauthenticated access redirects to
  admin login; guest-invoice with a valid token serves a valid PDF and
  rejects a forged signature; account/invoice.php stays login-gated;
  public pages return 200.
- **5B Customer Forgot Password (v0.5.0, this session):** 22 automated
  HTTP checks green. Existing customer email → reset link generated;
  non-existing email → identical generic message, no link, no token
  row; invalid / expired / already-used token → rejected with one
  generic message; password mismatch → rejected, token not consumed,
  hash unchanged; successful reset → 302 to `account/login.php` with
  the exact flash message "Password updated successfully. Please
  login."; login with new password works, old password fails; CSRF 403
  on both forms without token; forgot-password blocked while the email
  is login-locked (no token generated); stored `token_hash` ==
  SHA-256(raw token); `customer_password_resets` FK + indexes verified
  live; migration idempotent (re-run safe)
- **Regression:** public pages 200; payment pages handle unconfigured
  gateway gracefully (no 500); admin order detail CRUD verified
  end-to-end (status/payment/shipping updates, Manual UPI verify/
  reject, timeline logging); `php -l` sweep clean

**Phase 5E (v0.5.2, this session) - Admin Security Hardening + TOTP
2FA, verified live against the running PHP dev server + `moonaura` DB
(`/tmp/opencode/p5e_test.sh` = 44/44, `/tmp/opencode/p5e_failclosed.sh`
= 11/11 when the server runs without the encryption key):**
- **Setup flow:** password login → Security page renders the QR
  container + 32-char Base32 secret; the secret stays stable across
  reloads (stored encrypted, blob ≠ raw secret, 2FA still off); wrong
  first code rejected (2FA stays off); correct current code flips
  `two_factor_enabled = 1` + `two_factor_enabled_at` and shows the
  "On" state
- **Login with 2FA:** password-only login → 302 to `2fa-verify.php`;
  every protected page (dashboard/products/2fa-setup) bounces a pending
  session back to the OTP step; GET logout is inert, POST+CSRF logout
  works; wrong OTP rejected with error and the pending state persists;
  correct OTP → session granted → dashboard 200
- **Disable flow:** wrong disable code rejected (2FA stays on); correct
  current OTP disables and wipes the secret + timestamp; password login
  then skips 2FA; setup page shows a fresh QR
- **CSRF / tamper / lockout:** setup + logout POSTs without CSRF → 403;
  a bogus PHPSESSID gets no access; 5 wrong OTPs trigger the shared
  15-minute `login_attempts` lock (message shown, further attempts
  blocked); the session attempt cap forces a fresh password login
  instead of grinding codes; after 5 wrong OTPs the pending step is
  discarded so protected pages are unreachable
- **Fail-closed (server without `ADMIN_2FA_ENCRYPTION_KEY`):** the
  setup page warns and renders no QR, stores no secret, and refuses to
  enable; a DB row claiming 2FA-on with an unreadable/tampered secret
  denies every pending attempt (GET and POST, pending state discarded,
  protected pages blocked) - no OTP is ever checked against a bad
  secret
- **Regression:** Phase 5C 62/62, Phase 5D 30/30, lockout suite 18/18,
  `php -l` sweep clean; admin DB baseline restored to 2FA-off

**Verified working on a real server (per project handover / your reports):**
- Customer registration, auto-login after registration, login, logout, dashboard
- Duplicate email validation, duplicate phone validation
- Password hashing (bcrypt via `password_hash`/`password_verify`)
- Checkout end-to-end, including both schema-drift repair migrations
  (`daily_sequences`, `order_addresses.landmark`)
- **Phase 3A (Cashfree payment integration) - confirmed fully working
  and verified, per your report.** (No further detail was needed here
  since you confirmed this directly rather than this being inferred
  from static analysis.)

**Verified via static analysis only (no PHP/MySQL runtime available in
this chat's sandbox - every phase's "regression test" in this project
has been brace/tag balance checks, function-definition/call
cross-referencing, and SQL placeholder-count verification, not live
execution):**
- Everything from Phase 0 through Phase 3 pre-fixes not explicitly
  listed above as runtime-verified
- The `get_shop_categories()` MySQL 8 fix

**Phase 3C (this session) - verified by parity-prototype rendering /
static cross-reference only, same sandbox limitation as always (no
PHP/MySQL runtime here):**
- **Purple invoice header**: added `SimplePdfWriter::filledRoundedRect()`
  (new, additive method - nothing existing changed), used it plus
  white text in `build_invoice_pdf()`'s table-header closure only.
  Verified via a Python line-for-line port of the exact same drawing
  calls: rendered with `poppler`, pixel-sampled the output and
  confirmed the brand purple (`RGB 91,46,145` / `#5B2E91`) fill with
  white text on top, anti-aliased rounded corners present (not a
  plain rectangle). Re-ran the full realistic multi-page invoice
  parity test (long names, 12 items, forced page break) through the
  change - layout/pagination/wrapping all still correct, only the
  header row's colors changed, data rows unaffected.
- **Admin Orders module**: `admin/orders.php` (list) and
  `admin/order-detail.php` (detail) are new files, following
  `admin/products.php`'s existing list-page pattern exactly (same
  auth check, same search-form/table-card structure, no new
  pagination pattern introduced since products.php doesn't have one
  either). Cross-checked every column referenced
  (`orders`/`order_items`/`order_addresses`/`payment_transactions`)
  against `database/schema.sql` - all match. Cross-checked every
  function called (`require_admin_login()`, `current_admin()`,
  `format_price()`, `h()`, `redirect()`) against their actual
  definitions in `includes/auth.php`/`includes/functions.php`. Brace/
  paren/bracket balance checked on both new PHP files and the CSS
  changes. **Not verified**: an actual admin login + click-through in
  a browser - needs, at minimum: logging in as admin, confirming the
  Orders link in the sidebar is no longer grayed out and opens the
  list, confirming search works, confirming the detail page opens for
  a real order (including one with no shipping address, one paid via
  COD vs. online, and one where an invoice has/hasn't been generated
  yet, to exercise every conditional branch in
  `admin/order-detail.php`).
- Read-only by design - no order status change, refund, or shipping
  action was added; see the doc comment at the top of
  `admin/orders.php` for why (that's the separate, not-yet-scoped
  "Order Timeline"/"Shipping Integration" work in §3).

**Phase 3D (this session) - same static/parity-check-only verification,
same sandbox limitation:**
- **`account/order-detail.php`**: added a conditional Discount row
  (only when `> 0`, matching the same pattern already used in
  `admin/order-detail.php`), a Payment Method row, unit price on each
  item line, and a "Back to Orders" link with the order date. Diffed
  every field referenced against the `orders`/`order_items` columns
  already being fetched by this page (no new query added) - all
  exist. Balance-checked.
- **`account/dashboard.php`**: added a `LIMIT 3` recent-orders query
  and a preview table reusing `orders.php`'s exact `.account-table`/
  `.account-badge` markup (copy-pasted structure, not new CSS).
  Balance-checked.
- **`admin/dashboard.php`**: added two `SELECT COUNT(*)` queries
  (`products`, `orders` - both table names confirmed against
  `database/schema.sql`) and wired the existing `.admin-placeholder-card`
  markup into real links - no new CSS class, just wrapped the existing
  cards in `<a>` tags the same inline-style way `account/dashboard.php`
  already wraps its own summary cards. Balance-checked.
- **`admin/orders.php`**: added a `status` GET parameter, allow-listed
  against the 5 real `order_status` enum values from `schema.sql`
  before it ever reaches the query (anything else is silently treated
  as "no filter"), combined with the existing search via `AND` inside
  explicit parentheses around the search's own `OR` group (checked
  this by hand - without the parens, operator precedence would have
  let a status filter silently override part of the search instead of
  combining with it). Balance-checked.
- **`admin/invoice.php`** (new): traced its function calls
  (`get_or_create_invoice_number()`, `build_invoice_pdf()`,
  `require_admin_login()`, `flash_set()`, `redirect()`, `h()`) against
  their actual definitions - all match, all identical to how
  `account/invoice.php`/`guest-invoice.php` already call them, so no
  new invoice-generation code path was introduced, only a new
  auth/lookup wrapper around the existing one. Confirmed
  `.admin-alert`/`.admin-alert-error` (used for its error redirect
  back to `order-detail.php`) already exist in `admin.css` (used by
  `admin/categories.php`). Balance-checked.
- **Not verified**: an actual browser click-through of any of the
  above - needs, at minimum: viewing an order with/without a discount
  on the customer side, viewing the customer dashboard with 0/1/4+
  orders (to see the "View all" link only appears past 3), the admin
  status filter actually narrowing results, and clicking "Generate &
  View"/"View Invoice" on an order with no invoice yet vs. one that
  already has one.

**Phase 3B (Invoice System) - verified by parity-prototype rendering,
NOT by executing the actual PHP (still no PHP interpreter in this
sandbox):**

Because a hand-written PDF binary format is easy to get subtly wrong
(byte offsets, stream lengths, xref tables) and this sandbox has no
PHP to run the real code, `includes/lib/SimplePdfWriter.php` and
`includes/invoice-functions.php`'s logic were each first built and
proven in an exact line-for-line Python port of the same algorithm,
then transcribed to PHP. Specifically checked, with realistic/
adversarial test data (very long customer name, very long email, long
multi-line address, 12 products with long names, one deliberately
routed to force a page break):
- The output is a structurally valid PDF - opens and extracts correct
  text via `pypdf` and renders correctly via `poppler`
  (`pdftoppm`/`pdftocairo`, both available in this sandbox) with no
  warnings or errors
- **Determinism**: the exact same generation run twice, byte-for-byte
  identical (`cmp` confirms no diff) - see the "DETERMINISM" note at
  the top of `includes/invoice-functions.php` for exactly what does
  and doesn't affect this
- **Long-text wrapping**: long customer name/email, long shipping
  address (including a long landmark), and long product names all
  wrap onto multiple lines within their column rather than
  overrunning it; item-row height adjusts per-row based on how many
  lines the product name actually wrapped to
- **Multi-page pagination**: forcing a page break, the letterhead
  (business name/logo, "TAX INVOICE", invoice number, gold rule) and
  the item table's column header both automatically redraw on the new
  page - confirmed the redrawn letterhead is pixel-identical to page
  1's (mean pixel difference ~0.6 on a 0-255 scale across the whole
  region - i.e. only antialiasing noise, not a real difference)
- **Logo + transparency**: registered a JPEG color image plus a
  second grayscale JPEG as its `/SMask`, drawn in the header - `pypdf`
  recognizes the composited result as an `RGBA` image at the correct
  dimensions, and the rendered PNG shows the logo's actual ink color
  (sampled brand-purple pixels) correctly composited against the
  white page rather than a black box or a missing image

**What this validates vs. doesn't:** this proves the PDF-generation
*algorithm* is correct - object numbering, byte offsets, content
stream syntax, wrapping math, and pagination logic all behave as
intended. It does NOT prove the actual PHP file has no typo/syntax
error PHP's own parser would catch (`php -l` was not runnable here -
see the brace/paren/bracket balance check that was run instead, which
passed) or exercise PHP-specific runtime behavior this sandbox can't
reach (actual `PDO` fetch results, actual `GD` extension calls in
`register_invoice_logo()`, actual header/`Content-Disposition`
delivery to a browser). Needs, at minimum, on a real server:
1. Download Invoice on an order that has no `invoice_number` yet -
   confirm a number and date get generated and stored, and a valid
   PDF downloads immediately
2. Download Invoice again on that same order - confirm the same
   number/date are reused (not regenerated) and the PDF is
   byte-identical to the first download
3. Print Invoice - confirm it opens inline in a new tab rather than
   downloading
4. An order with a long product name/customer name/address, to see
   the wrapping behavior on an actual PDF viewer (Adobe Reader/Chrome/
   Preview), not just `poppler`'s renderer
5. An order with enough items to force a second page, to confirm the
   repeating header in a real viewer
6. Whether `register_invoice_logo()` actually decodes anything on your
   PHP build - this project's own logo files are WebP saved with a
   `.png` extension (see that function's doc comment), so this
   depends on your `php-gd` build including WebP read support; if it
   doesn't, invoices simply render without a logo (confirmed
   graceful - no error), not a broken invoice

**Phase 4A (Wishlist, this session) - static cross-reference only,
same sandbox limitation as always:**
- Cross-checked every function call (`wishlist_get()`, `wishlist_add()`,
  `wishlist_remove()`, `wishlist_toggle()`, `is_in_wishlist()`,
  `wishlist_count()`, `get_wishlist_items_with_details()`) against
  their own definitions in the new `includes/wishlist-functions.php` -
  all internally consistent, deliberately mirroring
  `includes/cart-functions.php` function-for-function
- Cross-checked `wishlist-toggle.php`'s redirect-safety regex - copied
  verbatim from `cart-add.php`'s already-proven version, not
  reimplemented
- Confirmed `.wishlist-btn`/`.product-buttons`/`.btn-cart`/`.btn-buy`
  had genuinely zero CSS anywhere in the project before this session
  (`grep` across every `.css` file came back empty) - not an
  assumption
- Balance-checked every new/edited PHP file and every edited CSS file
- **Not verified**: an actual browser click-through - needs, at
  minimum: clicking a heart icon on the shop page and on the product
  page and confirming it fills in/redirects back correctly, confirming
  the header badge count updates, opening `wishlist.php` with 0/1/4+
  items, removing an item from the wishlist page itself, and adding a
  wishlist item to the cart from the wishlist page

**Phase 4A UI regression pass (this session) - upgraded verification:
actual rendering, not just static analysis.** This sandbox has no PHP,
but it does have `wkhtmltoimage` (a WebKit-based renderer) and no
outbound network - used to actually render the real CSS (not a
description of it) against reconstructed HTML:
- Rendered an isolated test card using the real
  `assets/css/style.css`/`assets/css/home.css` with the corrected
  `.product-actions`/`.cart-btn`/`.product-btn`/`.wishlist-btn`
  markup - confirmed visually: the icon cart button and "Buy Now"
  render at matching height, sit vertically centered next to each
  other, and the wishlist heart still renders correctly in the card's
  top-right corner in both states (outline = not saved, filled gold =
  saved)
- Balance-checked every touched file again after the revert
  (`shop.php`, `wishlist.php`, `assets/css/home.css`) - confirmed
  every trace of `.btn-cart`/`.btn-buy`/`.product-buttons` is gone
  from both the markup and the CSS, and confirmed `.wishlist-btn`/
  `.wishlist-form` (the legitimate part of the original Phase 4A work)
  were untouched by the fix
- **Footer white-strip issue - investigated, could NOT reproduce or
  find a root cause with what's available here:**
  - Checked `html`/`body` base styles, `.footer`'s own box model
    (padding/background/box-shadow/overflow), for any `::before`/
    `::after` pseudo-elements on `.footer`, for `100vh`/`100vw` usage
    anywhere in the CSS (found one `100vh`, on the mobile menu overlay
    - `position: fixed`, can't affect document flow/footer spacing),
    and for unclosed/mismatched wrapper tags in `header.php` (tag
    count balanced) - none showed an obvious cause
  - Confirmed neither `footer.php` nor `footer.css` was touched by
    either Phase 4A session at all
  - Reconstructed an approximate static version of a real page (actual
    `header.php`/`footer.php` markup, PHP tags stripped) and rendered
    it with the real CSS at both a desktop (1400px) and mobile (375px)
    width - **no white strip appeared at the bottom of the footer in
    either render**; a low-confidence pixel scan initially flagged
    something near the bottom of the mobile render, but a visual crop
    showed it was payment-icon placeholder boxes (broken images in
    this synthetic test, not a real gap)
  - This does NOT prove the issue doesn't exist on your end - `wkhtmltoimage`
    is a different, older rendering engine than Chrome/Safari/Firefox,
    and the PHP-tag-stripping used to build the test page is
    approximate, not exact. Genuinely unresolved - needs a screenshot
    or the specific page/browser/viewport width where you're seeing
    it to actually pin down and fix rather than guess at

- **Footer white-strip - further investigation this session, still
  unresolved:** two more specific hypotheses checked and ruled out:
  - `index.php`'s `<main id="page-content">` wrapper (present only on
    the home page, no other page has it) has zero associated CSS
    anywhere in the project, and nothing in `style.css`/`home.css`/
    `footer.css` selects based on DOM nesting depth (`:last-child`,
    `body >`, etc. - none exist) - so the extra wrapper level can't
    be silently breaking a cascade-dependent rule
  - No `100vw`/`50vw` usage anywhere in the CSS (`grep` came back
    empty) - rules out the classic scrollbar-width full-bleed-section
    gap bug
  - Genuinely still unresolved - same ask as before: a screenshot or
    the exact page/browser/viewport width would let this actually get
    pinned down instead of continuing to guess at hypotheses

- **About Us "Why Choose MoonAura" card overflow - root cause found
  and fixed this session:** `.about-why-grid` (`assets/css/
  about-us.css`) used `grid-template-columns: repeat(4,1fr)`. Bare
  `1fr` tracks have an implicit `min-width: auto` (the content's
  min-content size) - so at viewports just above the 992px breakpoint
  (where the layout drops to 2 columns), the four padded cards
  refused to shrink below their own content width and pushed past the
  container. Matches "overflow before the mobile breakpoint" exactly.
  Fixed by changing to `repeat(4,minmax(0,1fr))` - cards now shrink to
  fit (with normal internal text wrap) instead of overflowing;
  behavior is identical everywhere the grid already had enough room.
  Nothing else about `.about-why-card` or the 768px/576px breakpoints
  was touched. Could not visually verify with a render:
  `wkhtmltoimage` here is v0.12.6 (QtWebKit, pre-dates CSS Grid
  support) and rendered the grid as stacked blocks regardless of the
  CSS - confidence in this fix is from CSS spec reasoning (a
  well-documented, standard fix for this exact failure mode), not a
  visual render. Worth a real-browser check on your end.

- **Missing global button variants - added this session:**
  `.btn-secondary`, `.btn-text`, `.btn-danger` added to `style.css`,
  built on the existing shared `.btn` base exactly like
  `.btn-primary`/`.btn-outline` already are - no new colors invented:
  `.btn-secondary` uses the existing `--gold` variable, `.btn-danger`
  reuses `#b3261e` (already the project's danger red in
  `admin.css`'s `.admin-icon-btn-danger` and the invoice's
  payment-status-failed/refunded badges). `.btn-text` deliberately
  overrides the shared base's `min-width`/`height`/`padding` (a real
  text-style button can't use the same 56px pill sizing as the filled
  variants) but keeps the same color tokens and transition timing.
  Nothing existing renamed or restructured.

- Guest Order Linking (`link_guest_orders_to_customer()`) has not been
  runtime-tested end-to-end - no guest order existed to test against
  during the last verification pass
- Admin Forgot Password flow (token generation/expiry/reset) - built,
  static-verified, not yet runtime-tested

**Phase 4B (this session) - 9-batch customer-facing UI/UX audit
series, same static-analysis-only limitation as always (no PHP/MySQL
runtime here):**

Every change this phase was CSS/HTML markup only - no new PHP logic,
no schema changes, no new queries. Verification method throughout:
brace/tag balance checks on every touched file, full-project diffing
against the original baseline after every batch to confirm no
out-of-scope file was touched, and targeted visual rendering via
`wkhtmltoimage` where useful - with two known, explicitly-flagged
limitations: it predates CSS Grid support (confirmed again this
session; multi-column grid layouts render as stacked blocks
regardless of actual CSS) and cannot reach external CDNs (Font
Awesome icons render blank), so grid-column-width and icon-rendering
claims in this phase relied on CSS-spec reasoning and flexbox-based
simulations of the real column widths instead of a direct render.
- **Footer white-strip**: root cause confirmed - `index.php`'s
  `#backToTop` button had zero CSS/JS anywhere in the project, so it
  rendered as a bare unstyled `<button>` in normal document flow
  right after the footer. Given a real fixed-position implementation
  (`style.css`) plus scroll-show/hide and click-to-top behavior
  (`main.js`), guarded with `if (!backToTop) return;` since the
  element only exists on `index.php`.
- **Cart/Wishlist Empty States**: both used `class="btn-primary"`
  without the shared `.btn` base class (same root cause pattern as
  the earlier button-system work), so the "Continue Shopping" button
  rendered completely unstyled. Consolidated both pages' near-
  duplicate empty-state markup into one shared `.empty-state`
  component in `style.css`. Note: `payment-failure.php` also uses the
  old `.cart-empty` class from `cart.css` - kept that rule in place
  (unused by `cart.php` now) rather than deleting it, since removing
  it would have broken an out-of-scope page.
- **Orders Page + `account/dashboard.php`**: removed the separate
  "View Details" column, made the Order # itself the clickable link
  (`.account-order-id-link`, new). Initially scoped to `orders.php`
  only; the Tables Audit batch later found `dashboard.php`'s Recent
  Orders preview had the same stale pattern and applied the identical
  fix there too.
- **Account Typography**: every account page's `<h1>` reads "My
  Account" except `order-detail.php`, which had `<h1>Order
  MOAOD...</h1>` - standardized to match, moved the order number into
  the existing meta line instead of dropping it.
- **Shop Page rework**: Hero now reuses the exact `.hero`/
  `.hero-container`/`.hero-content`/`.hero-image` component `index.php`
  already had (home.css) instead of a separate, uncontrolled
  `.about-hero`/`.hero-tag` implementation that was never even linked
  to a stylesheet defining it. Filters/toolbar/pagination/results text
  had zero CSS anywhere - new `assets/css/shop.css` added (page-scoped,
  same pattern as `cart.css`/`checkout.css`). "Why Choose MoonAura"
  and "Need Help Choosing" sections now reuse About Us's existing
  `.about-why*`/`.need-help`/`.help-box` components instead of
  maintaining separate `.shop-features`/`.shop-cta` implementations
  (later renamed - see the "Why Choose" consolidation entry below).
- **Product Card fixed-height layout**: `.product-category`, the card
  title, `.product-price`, and `.current-price` (the `shop.php`/
  `wishlist.php` card variant) had literally no CSS anywhere - added
  to `home.css` next to the sibling `.product-title`/`.price` rules
  for the *other* card variant (`index.php`/`product.php`'s related
  products). **Regression caught and fixed during this same batch**:
  the first version of the new `.product-content h3` selector had no
  class qualifier, so it also matched-and-overrode `index.php`/
  `product.php`'s `<h3 class="product-title">` (same `.product-content`
  wrapper, higher specificity than `.product-title` alone) - rescoped
  to `.product-content h3:not(.product-title)` before shipping.
- **About Us / Home "Why Choose" overflow - corrected, not just
  re-confirmed**: this file previously claimed the About Us grid
  fix was complete. Re-checked this session and found the base
  desktop rule did have `minmax(0,1fr)`, but the `@media
  (max-width:992px)` 2-column breakpoint still had the original bare
  `repeat(2,1fr)` bug, and a separate `white-space:nowrap` on
  `.about-why-title` (and Home's identical `.why-title`) caused
  "Certificate Included" to overflow its own card at real mobile
  widths regardless of the grid fix - confirmed both empirically (a
  flexbox simulation at the real computed column width showed the
  overflow, then showed it resolved) and via CSS spec reasoning.
  Fixed on both pages.
- **Home/About/Shop "Why Choose" consolidation**: found the two
  implementations (`home.css`'s `.why-*`, `about-us.css`'s
  `.about-why-*`) were byte-for-byte identical in every CSS property
  except one real difference - Home's markup was missing the gold
  divider element between title/description that About's had. Strong
  evidence this was unintentional: `home.css` contained an orphaned
  `.about-why-card:hover .about-why-divider` rule (About's class
  names, sitting unused in the wrong file) plus a fully-built,
  never-referenced `.why-divider` rule with identical values. Added
  the missing divider to `index.php`'s markup, moved the shared CSS
  into `style.css` under a neutral `.why-choose*` name (not
  page-specific anymore), deleted both old duplicate blocks, and
  updated `about.php`/`shop.php` to the new class names. One real gap
  caught mid-fix: the initial pass deleted the old CSS from
  `about-us.css` and `style.css`'s new block correctly, but the
  matching deletion from `home.css` was missed in that turn - caught
  and completed in a follow-up verification pass before this was
  considered done.
- **Authentication + Shared Forms audit**: `login.php`/`register.php`
  already correct. Found and fixed one instance of the same
  missing-`.btn`-base-class bug on `checkout.php`'s "Place Order"
  button. Confirmed no customer-facing Forgot/Reset Password flow
  exists (only Admin has one) - same finding this file already had on
  record, re-verified.
- **Customer-Facing Button-System audit**: verified every finding
  from the Auth audit and found 13 more identical missing-`.btn`
  instances across `cart.php` (Update/Proceed to Checkout/Continue
  Shopping), `payment.php` (Back to Checkout/Pay Now),
  `payment-failure.php` (Retry Payment/Back to Cart), `order-success.php`
  (Continue Shopping/Download Invoice/Print Invoice), and `product.php`
  (Back to Shop/Add to Cart/Buy Now). Confirmed every scoped CSS
  override that assumes `.btn` is present (`.cart-summary .btn-primary`
  etc.) was already correctly authored and waiting for the class to be
  added - these were bugs, not missing styles.
- **Tables audit**: `.account-table` had zero responsive handling
  anywhere (no scroll wrapper, no breakpoint rules) - added
  `.account-table-wrap` (`overflow-x:auto`) plus a `min-width` on the
  table itself so it scrolls instead of illegibly compressing. Cart's
  mobile (≤800px) item layout had two real bugs: the remove-button's
  `grid-area: remove` targeted the `<button>`, but the actual grid
  child is its unclassed wrapping `<form>` - `grid-area` has no effect
  on non-grid-item elements (unambiguous per spec), so the button had
  no defined position in the mobile layout; fixed by classing the form
  itself. Separately, the "Update" button overflowed past the card
  edge because the inherited `.btn` `min-width:180px` was never
  overridden for this compact context - fixed by adding `min-width:0`
  scoped inside the existing 800px media query only (confirmed via
  flexbox render that this is what actually overflowed; the
  grid-area bug was confirmed via spec reasoning since `wkhtmltoimage`
  can't render CSS Grid).
- **Notifications audit**: reviewed all three alert component families
  (`.account-alert*`, `.checkout-alert*`, `.cart-alert*`) across every
  page that uses them. Confirmed byte-for-byte identical in every
  property (padding, radius, colors, spacing, the `<ul><li>` error-list
  pattern, complete absence of icons everywhere). No genuine
  inconsistency found - no files changed.
- **Loading States audit**: confirmed no spinner/skeleton/disabled-
  button-state implementation exists anywhere in the customer-facing
  codebase, including the payment gateway flow - consistently absent
  everywhere, not a partial implementation. No files changed.
- **Not verified**: an actual browser click-through of any of the
  above on every viewport size this phase specifically targeted
  (particularly the Cart mobile breakpoint fixes and the Shop page's
  filter/pagination styling, both of which relied on CSS-spec
  reasoning or a flexbox-simulated render rather than a true CSS
  Grid-capable renderer).
- **Documentation sync batch**: no code changes, `PROJECT_STATE.md`/
  `CHANGELOG.md` only.
- **Final Design System Audit batch**: reviewed Cards, Tables,
  Typography, Colors, Shadows, Hover states, Focus states, Icons,
  Border-radius, Responsive behavior, and Accessibility across the
  whole customer-facing site, plus a dedicated Product Page Audit
  (gallery, breadcrumb, info block, specs, description, related
  products, 404/not-found state, responsive behavior). Two genuine
  bugs found and fixed:
  - **Accessibility**: `.newsletter-form input` (Home page) had
    `outline:none` with no `:focus` replacement anywhere - confirmed
    via a full programmatic sweep of every `outline:none` rule in the
    codebase that this was the only such instance. Every other
    outline-suppressed input on the site pairs it with a
    `border-color` swap on focus; this input has `border:none` too, so
    a `box-shadow` ring was added instead, using the same
    `rgba(91,46,145,...)` primary-color convention already used
    elsewhere in the codebase.
  - **Icon syntax**: the `#backToTop` button (built this session, in
    the Footer fix batch) used legacy `class="fas fa-chevron-up"`
    instead of the `fa-solid` prefix used in the other 162 icon
    instances site-wide. Confirmed FA6's bundled `all.min.css` likely
    still renders the legacy prefix correctly, but fixed for authoring
    consistency regardless.
  Also reviewed and explicitly did **not** change (see §5 for the
  reportable ones): the wide box-shadow value spread (intentional
  per-component art direction, not a bug), two hardcoded hex colors
  that exactly duplicate `var(--primary)`/`var(--gold)` (zero visual
  difference), the `Courier New` monospace on the payment reference
  value (intentional), and the "hover-lift only on truly clickable
  cards" pattern (`.product-card`/`.why-choose-card`/
  `.testimonial-card` have it, `.account-card`/`.cart-item`/
  `.account-address-card` correctly don't, since the latter are
  containers for separately-interactive children, not whole-card
  links).

**Phase 4C/4D (Multi-Gateway Checkout + Manual UPI QR + Admin
Settings) - same static-analysis-only limitation as always (no
PHP/MySQL runtime here):**

An initial implementation of the checkout-facing half of both features
was already present in the codebase when this phase's work began (see
the top-of-file note) - it was audited against the approved
specification first, confirmed correctly built, and left untouched.
This session's actual code changes were narrower:
- **`PaymentManager.php`**: added one new public, read-only method
  (`getRegisteredGatewayNames()`) so `admin/settings.php` has a single
  source of truth for which gateways have a real class, instead of
  hardcoding `['cashfree', 'razorpay']` itself. Does not touch
  `createPayment()`/`verifyPayment()`/`handleWebhook()`/either gateway
  class - confirmed via diff that `RazorpayGateway.php`,
  `CashfreeGateway.php`, `payment.php`, `payment-verify.php`,
  `webhook-razorpay.php`, and `webhook-cashfree.php` are all
  byte-for-byte unchanged this session.
- **`admin/settings.php`** (new): Payment Methods toggles (Cashfree,
  Razorpay, PhonePe shown but disabled/inert, Manual UPI QR, COD),
  Default Payment Gateway dropdown, Manual UPI Settings (UPI ID,
  Account Name, QR image upload/replace via the existing
  `save_uploaded_image()` helper). Server-side validation for both
  required rules (can't disable every payment method; can't set a
  disabled gateway as default) - a small inline script keeps the
  dropdown's disabled options in sync with the checkboxes client-side,
  but that's a UX nicety, not the actual enforcement.
- **`admin/order-detail.php`**: added a "Manual UPI Payment" card
  (UTR number, submission timestamp, screenshot link) with Verify
  Payment / Reject Payment actions, gated to `manual_upi` orders still
  at `payment_status = 'pending'`. Both write through the existing
  `update_order_payment_status()`/`update_payment_transaction()`
  functions (the same ones `PaymentManager` itself uses) rather than
  new hand-written SQL - confirmed this is the *only* order/payment
  status update mechanism added; every other payment method's status
  still only ever changes via `PaymentManager`
  (`verifyPayment()`/`handleWebhook()`) or `checkout.php`'s direct COD
  call, unchanged.
- **`admin/includes/admin-sidebar.php`**: Settings link enabled
  (was a disabled placeholder).
- **`admin/assets/css/admin.css`**: added `.admin-btn-danger` (reusing
  the existing `#b3261e` danger red already used by
  `.admin-icon-btn-danger` and the invoice payment-status badges - not
  a new color) and `.admin-current-qr` (a small image-preview style).
- **Not verified**: an actual browser walkthrough of
  `admin/settings.php` or the Verify/Reject buttons - no PHP/MySQL
  runtime available here, same limitation this file has always had.
  Particularly worth checking on a real server: the file upload path
  (`assets/uploads/qr-codes/` gets created automatically by
  `save_uploaded_image()` if missing, but confirm the web server user
  actually has write permission there), and that disabling a gateway
  an in-progress order was already using doesn't strand that order
  (by design it shouldn't - `createPayment()` deliberately doesn't
  re-check `_enabled` status, see that function's own docblock - but
  this hasn't been exercised against a real database).

---

## 5. Known Limitations (intentional, by design so far)

- **Wishlist is session-based, like the cart, not tied to a customer
  account** - it doesn't survive clearing cookies/switching devices,
  and a guest's wishlist isn't merged into their account on login
  (there's nothing to merge - it was never account-linked to begin
  with). This mirrors the cart's own existing architecture exactly
  rather than introducing a different pattern for a very similar
  feature; a DB-backed, account-linked wishlist (surviving across
  devices) would be a reasonable future enhancement but is a genuinely
  different, bigger feature (new table, login-merge logic) - not
  attempted here
- **The heart/wishlist icon only appears on the two product-card
  variants that already had `.product-buttons`/similar structure**:
  `shop.php`'s grid, `product.php`'s main Add to Cart row, and the new
  `wishlist.php`. It was deliberately NOT added to `index.php`'s
  featured-products section or `product.php`'s own "Related Products"
  section - both of those use an older, simpler card markup
  (`.product-title`/`.price`/`.sale-price` instead of
  `.product-content h3`/`.product-price`/`.current-price`) that never
  had a wishlist placeholder to begin with, and retrofitting one would
  mean adapting to a different structure rather than reusing what's
  there - a bigger, separate decision than "wire up the existing
  button." Flagged in §3 as a future "unify the two product-card
  markups" candidate, not fixed here
- **The product-card action row (`.btn-cart`/`.btn-buy`/`.product-buttons`)
  went through two iterations - the first was wrong, corrected in a
  follow-up "UI regression" session.** What actually happened: those
  three classes genuinely had zero CSS anywhere in the project
  (confirmed by grep). The first Phase 4A pass **misdiagnosed** this -
  it assumed `.btn-cart`/`.btn-buy` were the intended, current design
  that had simply never been styled, and wrote new CSS for them as
  text buttons. That was wrong: the project already had a complete,
  working, responsive set of classes for exactly this
  (`.product-actions`/`.cart-btn`/`.product-btn` - an icon-only cart
  button paired with a solid "Buy Now" button, already used correctly
  on `index.php` and `product.php`'s Related Products section, full
  responsive breakpoints included) that the first pass didn't
  recognize as the real target and left orphaned instead of using. The
  follow-up session removed the invented `.btn-cart`/`.btn-buy`/
  `.product-buttons` CSS entirely and switched `shop.php`/`wishlist.php`
  to the pre-existing `.product-actions`/`.cart-btn`/`.product-btn`
  classes instead - restoring the icon cart button, matching height/
  alignment with Buy Now (both already designed to pair together), and
  picking up the responsive breakpoints for free. The wishlist heart
  button itself (`.wishlist-btn`/`.wishlist-form`) was untouched by
  either the mistake or the fix - it was correct from the start
- **"Buy Now" is still a dead button everywhere it appears** (shop.php,
  product.php, and now wishlist.php's "View" link takes its place
  instead - see `CHANGELOG.md`) - pre-existing across the whole site,
  not a wishlist regression, and not touched here since it's unrelated
  to the wishlist feature

- **GST rate and shipping charge are hardcoded to 0.00** in
  `create_order()` - no tax-rate or shipping-rules table exists yet;
  explicitly deferred to a future phase, not an oversight
- **No email/SMS notifications anywhere** (order confirmation, admin
  password reset link, customer reset link, etc.) - explicitly out of
  scope project-wide; both the admin and customer (Phase 5B) password
  reset links are displayed on-screen as clear placeholders for future
  real email delivery. Scheduled as **Phase 5E (Email Notifications)** -
  not started
- **Customer-facing "Forgot Password" now exists** - Phase 5B shipped
  `account/forgot-password.php` + `account/reset-password.php`
  (mirroring the Phase 2D admin flow): hashed 60-min single-use tokens
  in `customer_password_resets`, no-account-enumeration messaging,
  CSRF, Phase 5G lockout reuse. The reset link is shown on-page as a
  placeholder until Phase 5E wires up real email delivery
- **Order number format**: `MOAOD<YYYYMMDD><4-digit sequence>`,
  resets daily. Orders created before the Phase 3 pre-fixes keep
  their original `MOA-YYYY-NNNNNN` format - not rewritten, by design
  (never alter historical data)
- **Cart is cleared on customer logout** - `destroy_session()` fully
  destroys the session per the Phase 3 security audit; this was a
  deliberate security-first tradeoff, flagged to the user as a
  possible UX tradeoff worth revisiting later if desired
- **Shop/Product page CSS is newly authored** (`product.css`,
  `cart.css`, `checkout.css`, `account.css`) since no original design
  existed for these pages - all reuse `style.css`/`home.css` variables
  and components wherever a fit already existed (e.g. Related
  Products reuses the homepage's exact `.product-card`)
- **Admin Settings UI now exists** (`admin/settings.php`, Phase 4C/4D)
  for payment settings - gateway enable/disable, default gateway,
  Manual UPI configuration all go through it now rather than a direct
  `UPDATE settings ...` SQL statement. It does not cover the invoice
  business-detail settings (`business_gstin` etc. - see above) or
  anything outside payments - those remain SQL-only
- **Neither gateway's webhook can reach `localhost` directly** - local
  testing of `webhook-cashfree.php`/`webhook-razorpay.php` needs a
  tunnel (ngrok or similar); the client-callback path
  (`payment-verify.php`) still works without one, since that's a
  normal browser request
- **`CashfreeGateway`'s webhook payload field names are unverified
  against a live payload** - implemented from Cashfree's published
  documentation (schema has changed across their API versions
  before); confirm against a real webhook delivery before production
  use (see `SETUP.md` §6)
- **Cash on Delivery has no dedicated "mark paid on delivery"
  action** - an admin can set a COD order's payment status to `paid`
  via the Phase 5A payment-status dropdown on
  `admin/order-detail.php`, but there is no one-click
  "Mark COD Paid on Delivery" action for the fulfillment flow yet;
  payment-on-delivery is otherwise tracked outside this system for
  now
- **Back To Top button is index-page-only** - the `#backToTop`
  floating button exists solely on `index.php` (`index.php:1146`);
  account/shop/product/etc. pages intentionally don't render it. The
  WhatsApp floating button's CSS (`assets/css/footer.css`) and the
  footer markup comment (`includes/footer.php`) describe it as stacked
  above `#backToTop`, which is accurate where both exist (index.php)
  and harmless elsewhere - the WhatsApp button simply floats a little
  higher on pages without it. No action required; this is a deliberate
  design decision, not a defect
- **Inventory restoration is tied exclusively to Order Status =
  Cancelled** - an order's reserved stock (`orders.stock_deducted_at`)
  is released only when the admin sets the order status to
  `cancelled` (via `update_order_status()`), never automatically.
  Setting Payment Status to `refunded` does NOT release inventory -
  a refund marks the payment as reversed but is not treated as a
  stock event. To return goods to inventory, the admin cancels the
  order; refunded-but-not-cancelled orders keep their stock reserved.
  This is an intentional business rule, not a defect
- **Payment Status = Failed does not auto-release inventory** - a
  failed payment (admin reject of a Manual UPI reference, or the
  gateway reporting a failed attempt) leaves the order active and
  leaves any already-reserved stock in place. Inventory is only
  restored when the order itself is cancelled, because a failed
  payment does not mean the order is dead (the customer may resubmit
  a correct UTR/reference, retry, or be contacted directly). This is
  an intentional business rule, not a defect
- **No refunds, saved cards, subscriptions, UPI intent apps, EMI,
  partial/international payments, or live mode** - all explicitly out
  of scope for Phase 3A

**Invoice PDF engine (`includes/lib/SimplePdfWriter.php`) - a
hand-rolled, dependency-free PDF writer, not a general-purpose PDF
library. Compared to a standard library (e.g. TCPDF, mPDF, Dompdf),
these are its real, known limitations:**

- **Text width is estimated, not measured.** Real Helvetica AFM
  metrics (per-character widths) aren't embedded; right-alignment and
  word-wrapping both use a fixed average-width-per-character
  heuristic. Good enough that the parity-prototype testing (§4) never
  showed text overrunning a column, but a string of unusually
  wide characters (e.g. all-caps "WWWWWW") could wrap slightly
  earlier/later than a metrics-accurate engine would, and
  right-aligned figures may sit a point or two off from
  pixel-perfect. Not noticeable in normal invoice content
  (names/addresses/prices), but worth knowing about.
- **No hyphenation or text justification** - word-wrap only breaks
  between whole words.
- **Only the standard 14 PDF fonts (Helvetica/Helvetica-Bold)** - no
  embedded fonts, so no italics, no non-Latin scripts (Hindi/Bengali
  etc.), no custom typography. Adding a real embedded font (e.g. for
  regional-language invoices) would be a genuine architecture change,
  unlike the logo image support below.
- **Image embedding is JPEG-only at the PDF level.** PNG/WebP/GIF/BMP
  sources work via `register_invoice_logo()` decoding them through
  PHP's GD extension and re-encoding as JPEG (+ a second JPEG for
  transparency, via `/SMask`) before handing them to
  `SimplePdfWriter` - see that function's doc comment. This means:
  - **Requires the `gd` PHP extension** to be compiled in (very
    common, but not guaranteed on every host) - without it, or if
    GD's build lacks WebP read support specifically, a logo is simply
    not drawn; invoice generation itself never fails because of this
    (confirmed via the parity prototype's error-path design, though
    the actual PHP `imagecreatefromstring()` call itself couldn't be
    executed in this sandbox - see §4)
  - **CMYK JPEG source images are not supported** by
    `registerImageJpeg()` directly (would render with inverted
    colors) - not a concern for the GD auto-decode path, which always
    produces RGB/Grayscale JPEGs itself, only for a caller passing
    raw JPEG bytes straight through
  - Re-encoding through JPEG means a very sharp-edged logo (e.g. thin
    text) could show minor JPEG compression artifacting at high zoom;
    not visible at normal invoice viewing/printing size in testing
  - **This project's own image assets are WebP files saved with a
    `.png` extension** (verified project-wide, not just the one logo
    file used here) - a real discovery from this work, not a
    hypothetical: if `register_invoice_logo()` returns null on your
    server, this WebP-via-GD path is the most likely reason. Exporting
    a true JPEG or PNG version of the logo is the reliable fix
    regardless of GD's WebP support
- **All fonts and images share one `/Resources` dictionary across
  every page** rather than a per-page resource set - simpler, and
  correct for an invoice, but not how a library handling
  wildly-different page layouts within one document might do it
- **PDF metadata exists but is render-determined** - since Phase 5G,
  `build_invoice_pdf()` sets `/Title`, `/Author`, `/Subject`,
  `/Keywords`, `/Creator`, `/Producer`, `/CreationDate` and `/ModDate`
  via `SimplePdfWriter::setMetadata()`. The earlier "no metadata"
  limitation was removed by making the metadata deterministic: the
  dates come from the stored `invoice_generated_at`, never from
  `date()`/`time()` at render time, so "byte-identical on every
  download" still holds (verified by the 5G harness). A metadata-free
  `SimplePdfWriter` document (no `setMetadata()` call) stays
  byte-identical to the pre-metadata format - no `/Info` object is
  emitted at all
- **Page numbers exist**: "Page X of Y" is drawn in the bottom margin
  by `SimplePdfWriter` (`drawPageFooter()`) on every page; the earlier
  "no page numbers" note was already obsolete
- **No PDF/A, no digital signatures, no encryption/password-protection** -
  the hand-rolled writer does not implement the PDF `/Encrypt` dict,
  so owner-password "read-only" invoices are NOT supported. Phase 5G
  deliberately chose to document this rather than fake it: a
  generated invoice is a normal, fully-editable PDF. If read-only
  protection is ever required: (a) post-process the generated bytes
  with a real tool (qpdf --encrypt / Ghostscript / LibreOffice), or
  (b) swap `SimplePdfWriter` for a maintained library (TCPDF / FPDF /
  dompdf all support RC4/AES encryption). See
  `includes/invoice-functions.php`'s header comment
- **GD-based logo decoding is a per-pixel PHP loop when the source has
  transparency** (`imagecolorat()` has no bulk/array API) - roughly
  350,000 iterations for the actual site logo's dimensions. Fine for
  an on-demand single invoice; keep any future logo file reasonably
  sized (a few hundred px wide) rather than a multi-megapixel original

**Invoice business details are configurable, and since Phase 5G they
are editable from the admin UI:**
`business_name`, `business_trade_name`, `business_gstin`,
`business_address`, `business_phone`, `business_email`,
`business_website` are all read via `get_setting()` (see
`get_invoice_business_details()` in `includes/invoice-functions.php`)
rather than hardcoded.
`business_name`/`business_address`/`business_phone`/`business_email`
fall back to real values already published on this site's own Support
page if unset; `business_trade_name` and `business_gstin` have no
default (no real value for either exists anywhere in the codebase) and
print as absent/"Not configured" on the invoice until set. **The
Business / GST section on `admin/settings.php` now covers all of
them** (Phase 5G), alongside the Phase 4C/4D payment settings and the
Phase 5F `business_state` - validation: `business_name` required,
`business_gstin` must match the 15-char GSTIN format (uppercased on
save), `business_email` must be a valid address, `business_website`
must be a valid URL (`https://` added automatically), and length caps
on every field. They are stored with `set_setting()`, so there is no
longer any need for a direct
`INSERT INTO settings (setting_key, setting_value) VALUES (...)`.
Until `business_gstin` is set, invoices are not legally complete GST
documents - flagged deliberately, not glossed over.

**Findings from the Phase 4B audit series, reported and intentionally
left unfixed (each is either an explicit design decision needed, or
genuinely outside a "consistency audit" scope - not an oversight):**
- **Button width behavior differs across three otherwise-identical
  contexts**: `.account-auth-box .btn-primary` is explicitly
  full-width, `.account-card .btn-primary` (address/profile/
  change-password) is explicitly left-aligned/natural-width, and
  `.checkout-form-card .btn-primary` has neither override so it
  happens to stretch full-width by default (flex `align-items:stretch`
  is the default). Not broken, just three separate unstated decisions
  - picking one would be a design call, not a bug fix
- **Form-input and alert CSS is triplicated near-identically** across
  `account.css` (`.account-auth-box`/`.account-card`), `checkout.css`
  (`.checkout-form-card`), and `cart.css` (`.cart-alert*`) - same
  padding/border/radius/focus values, same alert colors, maintained
  in three places. Architecture-level duplication, deliberately not
  consolidated (this engagement's scope explicitly excluded
  architecture refactors)
- **`account/dashboard.php`'s Recent Orders preview shows 4 columns**
  (Order #, Date, Total, Status) **vs. `account/orders.php`'s 5**
  (adds Payment) - a reasonable "summary widget shows less than the
  full page" pattern, not necessarily a bug, flagged in case it
  should match exactly
- **No Warning or Information notification variant exists anywhere**
  in the customer-facing CSS - only Success/Error. Nothing to make
  consistent since there's no existing component to compare against;
  noted only because the Notifications audit's scope explicitly named
  both
- **No loading-state UI anywhere** (no spinner, no disabled-button
  state, no "Processing..." feedback) on any customer-facing form or
  the payment gateway flow specifically - the Pay Now button has no
  protection against a rapid double-click before the gateway modal
  opens. Consistently absent site-wide (not a partial implementation,
  so nothing to reconcile), but worth a product decision on whether to
  add one
- **Two structurally different product-card markups still coexist**
  site-wide: `index.php`/`product.php`'s related-products
  (`.product-title`/`.price`/`.sale-price`) vs. `shop.php`/
  `wishlist.php`'s (`.product-category`/`.product-content h3`/
  `.product-price`/`.current-price`) - this was already a known,
  documented gap above before this session; the missing CSS for the
  second variant was added this session (see §2/§4's Phase 4B entry),
  but the two markups themselves were not unified, since that's a
  refactor, not a consistency fix
- **`.product-not-found` (the 404/out-of-stock product state) is a
  third, visually distinct "empty state" pattern** - no icon, no card
  background/border, just centered text + button with 100px padding,
  unlike Cart/Wishlist's shared `.empty-state` component (icon + card
  + consistent spacing). Not broken on its own, just stylistically
  different - flagged as a design-inconsistency candidate rather than
  fixed, since converting it would mean changing an existing,
  currently-working page's design, not just correcting a bug
- **Two hardcoded hex colors duplicate CSS variables exactly**:
  `.rating i` uses `color:#D4AF37` instead of `var(--gold)`
  (`home.css`), `.policy-list li::before` uses `color:#5B2E91` instead
  of `var(--primary)` (`policy.css`). Zero visual difference either
  way - purely a maintainability note (if the brand color ever
  changes, these two spots wouldn't update automatically) - not fixed
  since there's no visual bug to correct

---

## 6. Next Implementation Target

**Next up: Phase 5C (Admin Manual Order Create).** Phases 5A/5B/5F/5G
are complete (v0.5.0). Phase 5C will let an admin place orders on
behalf of customers (see §3). Not yet started - do not begin until the
current checkpoint is closed.

**Historical (Phase 5B closure - Customer Forgot Password):** delivered
in v0.5.0, mirroring the existing admin Forgot/Reset Password flow for
customers (`account/forgot-password.php`, `account/reset-password.php`,
`customer_password_resets` table, `migration_phase5b_*.sql`), reusing
the Phase 5G `login_attempts` lockout helpers, CSRF/flash infrastructure,
and the existing storefront auth-card components - no redesign, no new
CSS, no email sending (on-page link placeholder until Phase 5E). Fully
verified locally (22 HTTP checks) - see §4.

**Historical (Phase 4C/4D closure - Admin Settings + Manual UPI
verification workflow):** Found an already-built checkout-facing
implementation of both features when this session started (see the
top-of-file audit note and §2/§4's Phase 4C/4D entries) - audited it
against the approved specification first, then built the two pieces
the audit found genuinely missing: `admin/settings.php` (payment
method toggles, default gateway, Manual UPI configuration) and the
Verify Payment/Reject Payment actions on `admin/order-detail.php`.
Confirmed via diff that Cashfree, Razorpay, the webhook endpoints,
invoices, and existing payment verification logic are all untouched
this session - see §4's Phase 4C/4D entry for exactly what did
change. **Phase 4C and 4D are now both considered code-complete.**

**This session (Phase 4B, documentation sync + Final Design System
Audit — Phase 4B now closed):** Synchronized this file and
`CHANGELOG.md` against the actual codebase after a 10-batch audit
series (see §1/§2). Three items this file previously listed as open
are now resolved and confirmed:
- Footer white-strip: root cause found and fixed - `index.php`'s
  `#backToTop` button had no CSS/JS anywhere, so it rendered as a
  bare unstyled `<button>` in normal document flow directly below the
  footer. Given a proper fixed-position implementation.
- About Us grid overflow: the earlier fix was incomplete (desktop-only
  `minmax(0,1fr)`, missing at the 992px 2-column breakpoint, plus an
  unrelated `white-space:nowrap` bug on both About and Home's card
  titles causing text to spill past the card edge). Both fixed on
  both pages.
- Shop page rework: completed as its own batch (Hero, Filters,
  Collection section, Why-Choose-MoonAura component reuse, Need-Help
  CTA reuse, Product Card fixed-height layout).

Then ran the Final Design System Audit (Cards, Tables, Typography,
Colors, Shadows, Hover states, Focus states, Icons, Border radius,
Responsive behavior, Accessibility) plus a dedicated Product Page
Audit - see §4's Phase 4B entry for the full detail. Found and fixed
one accessibility bug (an unstyled focus state) and one icon-syntax
inconsistency; reviewed everything else in that list and found no
further genuine bugs - see §5 for what was found and correctly left
alone (design decisions/architecture items, not bugs).

**Phase 4B is now considered code-complete.** Nothing further is
planned for it unless your real-server review turns something up.

**Known open items, reported and intentionally left unfixed/not
auto-fixed - see §5 for detail):**
- No customer-facing Forgot/Reset Password flow exists (confirmed
  again this session - only Admin has one)
- Button width behavior differs across three otherwise-identical
  contexts (auth box / account card / checkout form) - needs a design
  decision, not a bug fix
- Form-input and alert CSS is triplicated near-identically across
  `account.css`/`checkout.css`/`cart.css` - architecture-level,
  deliberately not consolidated per this engagement's "no
  architecture refactors" constraint
- `account/dashboard.php`'s Recent Orders preview shows 4 columns
  vs. `account/orders.php`'s 5 (no Payment column) - minor, flagged
  only
- No Warning/Information notification variant exists anywhere (only
  Success/Error) - nothing to make consistent since there's no
  existing component to compare against
- No loading-state UI (spinners/disabled-button states) exists
  anywhere on any customer-facing form or the payment flow -
  consistently absent site-wide, not a partial implementation
- The two different product-card markups noted below still coexist
  (unifying them is a refactor, out of any audit's scope so far)
- `business_gstin`/`business_trade_name` and the rest of the invoice
  business details remain SQL-only - `admin/settings.php` only covers
  payment settings (see §5)
- PhonePe remains an inert `phonepe_enabled` flag with no registered
  gateway class - see §2's Phase 4C entry and §5 for why

**Next, in order:**
1. **Your real-server review of everything shipped this Phase 4C/4D
   session** - `admin/settings.php` (all four payment-method toggles,
   the default-gateway validation, the QR image upload) and the
   Verify/Reject Payment buttons on `admin/order-detail.php`
   specifically need a real walkthrough; see §4's "Not verified" note
   for the two things most worth checking first (upload folder write
   permission, an in-progress order surviving a gateway being
   disabled mid-checkout)
2. **Your real-server review of everything shipped the Phase 4B
   session** - almost entirely CSS/markup changes, low functional
   risk, but not yet seen in a real browser (see §4)
3. **Your real-server review of Phase 4A** (the Wishlist feature
   itself, plus the Phase 4A action-row fix) - see §4's checklists
4. **Your real-server review of Phase 3D**, if that hasn't happened
   yet (customer account/admin panel fixes) - see §4
5. **Your real-server review of the Phase 3C changes**, if that
   hasn't happened yet (invoice header styling, admin Orders module
   itself) - see §4
6. **Set `INVOICE_TOKEN_SECRET` in `.env`** so guest invoice download
   actually appears on `order-success.php` (code is confirmed
   complete - this is the one remaining config step)
7. Then: your real-server review of Phase 3B itself, if that hasn't
   happened yet
8. After that: pick the next Phase 5 roadmap item from §3 - 5C
   Admin Manual Order Create, 5D Customer Management, 5E Email
   Notifications, 5H POS / Walk-in Sales - plus the not-yet-scoped
   candidates (real `PhonePeGateway`
   once real credentials are obtained - see §5, Shipping
   Integration, account-linked wishlist upgrade, unifying the two
   product-card markups), or whatever you'd like prioritized next
