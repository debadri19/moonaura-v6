# MoonAura Crystals — Current Project Checkpoint Report

**Date:** 12 August 2026
**Version:** v0.4.8
**Snapshot:** `/tmp/opencode/moonaura-v0.4.8-full-checkpoint-20260812.zip` (verified, see below)
**Status:** Phases 5A (UI/UX & Order Tracking), 5F (Inventory Automation) and 5G (Login Security) implemented and locally verified. Nothing committed, nothing pushed. (Earlier internal labels "Phase 5"/"Phase 6" are normalized to the 5A/5F/5G roadmap scheme; migration filenames are unchanged by design.)

---

## 1. Completed Phases

| Phase | Scope | Status |
|---|---|---|
| 0 | PHP/MySQL foundation - config, DB connection, header/footer to includes, admin login/dashboard shell | Approved |
| 1A | Admin Categories + Products CRUD, image upload/management | Approved |
| 1B | Dynamic Homepage Featured Collection, dynamic Shop grid, category filter/sort/pagination | Approved |
| 1C | Dynamic Product Detail page (gallery, SEO/JSON-LD, related products, 404 handling) | Approved |
| 2A | Commerce foundation - orders/order_items/order_addresses schema | Approved |
| 2B | Session-based cart (+ quantity clamping + product-availability validation) | Approved |
| 2C | Checkout - guest checkout, server-side validation/totals, transaction-safe order creation, order-success page | Approved |
| 2D | Customer accounts (register/login/logout/dashboard/orders/addresses/profile/change-password), guest-order linking, admin Forgot Password | Approved |
| 3 Pre-fixes | Session/auth hardening, order+invoice numbering rewrite (daily reset), dead code removal, security audit | Approved |
| 3A | Payment integration - Cashfree (active) + Razorpay (inactive) via `PaymentManager` abstraction, gateway-agnostic settings, Cash on Delivery | Confirmed working |
| 3B | Invoice System - lazy GST invoice number/date generation, on-demand PDF (no stored files), customer Download/Print | Implemented |
| 3C | Invoice UI polish + Admin Orders module (list + read-only detail view) | Implemented |
| 3D | Customer & Admin Experience review (account/order-detail, dashboards, admin orders filter, admin invoice) | Implemented |
| 4A | Wishlist - session-based, wishlist.php, dead heart-icon wired up; product-card action buttons CSS fix | Implemented |
| 4B | UI/UX Refinement - 10-batch customer-facing audit series (footer white-strip, empty states, typography, shop rework, cards, responsive, buttons, tables, design-system audit) | Audit complete |
| 4C | Multi-Gateway Payment Checkout - per-gateway `*_enabled` flags, `default_payment_gateway` pre-selection, gateway resolved from order's stored method, `admin/settings.php` built | Complete |
| 4D | Manual UPI QR Payment - QR/UPI config in settings, customer UTR+screenshot submission, admin Verify/Reject workflow | Complete |
| 5A | UI/UX & Order Tracking - product search (header search icon → `shop.php?q=`, search on name/category/description/purpose/zodiac), order tracking & timeline (`order_status_history`, admin shipping/tracking fields, admin order-status/payment-status/shipping updates, customer timeline + tracking display) | Implemented & verified |
| 5F | Inventory Automation - stock deduction at each method's confirmation point, `orders.stock_deducted_at` idempotency marker, restore on admin cancel, no-negative-stock guarantee, auto out-of-stock flip | Implemented & verified |
| 5G | Login Security - `login_attempts` lockout (5 fails / 15-min) for admin + customer login, no-enumeration messages | Implemented & verified |

---

## 2. Implemented Features

**Storefront**
- Dynamic homepage (featured collection), shop grid (category filter, sort, pagination, **search** on name/category/description/purpose/zodiac), product detail (gallery, related, 404), wishlist, cart, guest checkout, order-success
- Product search entry point fixed: header search icon now points at `shop.php?q=` (Phase 5A)

**Commerce**
- Orders/order_items/order_addresses, daily-reset order numbers, GST invoice PDF on demand, customer account order history/detail, guest-order linking on login

**Payments**
- Cashfree (active) + Razorpay (inactive) via `PaymentManager`; per-gateway enable toggles; Cash on Delivery; Manual UPI QR (customer UTR/screenshot, admin verify/reject); webhook + client-callback verification paths

**Inventory Automation (Phase 5F)**
- Deduction at each method's confirmation point: COD at placement, Manual UPI on admin verify only, gateways only after payment confirmation (via `PaymentManager`)
- `orders.stock_deducted_at` idempotency marker prevents double-deduction/restoration and payment-retry mismatch
- Restore on admin cancel (`update_order_status()`); guarded `UPDATE ... AND stock_quantity >= ?` guarantees no negative stock; auto `out_of_stock`/`in_stock` flip; cart pre-flight shortage messages
- Pre-existing orders not retroactively deducted (backward compatible)

**Login Security (Phase 5G)**
- `login_attempts` table + 5 failed attempts / 15-minute lock for BOTH admin and customer login
- No enumeration (identical generic error), lazy expiry cleanup, success clears the record

**Admin Panel**
- Categories/Products CRUD, image gallery, Orders list + detail (status/payment/shipping updates, timeline, Manual UPI verify/reject), Settings (payment toggles, Manual UPI), invoice viewer, Forgot/Reset password, login page

---

## 3. Database Migrations Applied

All migrations live in `database/`; `schema.sql` is kept identical to the cumulative structure for fresh installs. Each migration is non-destructive and idempotent (guarded `ALTER` + `CREATE TABLE IF NOT EXISTS`).

| Migration | Adds | Applied to |
|---|---|---|
| `migration_add_orders.sql` | orders/order_items/order_addresses | live |
| `migration_add_phase2d_accounts.sql` (+ repair) | customers, customer addresses, admin reset flows | live |
| `migration_add_order_number_sequences_and_landmark.sql` (+ repair) | `daily_sequences`, `order_addresses.landmark` | live |
| `migration_add_alt_text.sql` | product image alt text | live |
| `migration_phase3_daily_sequences.sql` (+ repair) | `daily_sequences` phase-3 numbering | live |
| `migration_phase3a_payments.sql`, `migration_phase3a_switch_to_cashfree.sql` | `payment_transactions`, gateway settings | live |
| `migration_phase4c_multi_gateway_checkout.sql` | per-gateway enable flags, default gateway | live |
| `migration_phase4d_manual_upi_qr.sql` | Manual UPI settings columns | live |
| `migration_phase5_order_tracking.sql` (→ Phase 5A; filename unchanged) | `orders.courier_partner`/`awb_number`/`tracking_url`, `order_status_history` table | applied & re-run (idempotent) on `moonaura`, `mu_upgrade_test`, `mu_fresh_test` |
| `migration_phase6_stock_login.sql` (→ Phases 5F/5G; filename unchanged) | `orders.stock_deducted_at`, `login_attempts` table | applied & re-run (idempotent) on `moonaura`, `mu_upgrade_test`, `mu_fresh_test` |

**Deployment note:** run `database/migration_phase5_order_tracking.sql` then `database/migration_phase6_stock_login.sql` against the live DB before deploying the code. Both are safe to re-run; existing orders keep `stock_deducted_at = NULL` (never retroactively deducted).

---

## 4. Known Limitations (intentional business rules)

Documented in `PROJECT_STATE.md` §5; the following three are confirmed intentional, not defects:

1. **Back To Top button is index-page-only** — `#backToTop` exists only on `index.php`; the WhatsApp float is harmless on pages without it. No action required.
2. **Inventory restoration is tied exclusively to Order Status = Cancelled** — setting Payment Status to `refunded` does NOT release inventory. A refund reverses payment but is not a stock event; cancel the order to return goods to stock.
3. **Payment Status = Failed does not auto-release inventory** — a failed payment leaves the order active (customer may resubmit/retry) and any reserved stock in place; inventory is only restored on cancellation.

Other notable limitations (full list in `PROJECT_STATE.md` §5):
- Wishlist is session-based (not account-linked)
- GST rate and shipping charge hardcoded to 0.00 in `create_order()`
- No email/SMS notifications anywhere (admin password-reset link shown on-screen as placeholder)
- Customer-facing "Forgot Password" does not exist (admin-only today)
- COD has no "mark paid on delivery" admin action; no fulfillment tracking integration
- Cashfree webhook field names unverified against a live payload; webhooks can't reach `localhost` without a tunnel (client-callback path works)
- No refunds, saved cards, subscriptions, UPI intent apps, EMI, partial/international payments, or live gateway mode

---

## 5. Pending Features

Not started, by explicit instruction this checkpoint:

- **Customer Forgot Password** (admin Forgot Password exists; customer side does not)
- **Manual Order Create** (admin creating orders on behalf of customers)
- **Customer Management** (admin Customers module)
- **Email Notifications** (order confirmation, admin reset delivery, etc.)
- **POS** (point of sale)

Also on the radar (previously noted candidates, not started):
- Real `PhonePeGateway` implementation (needs PhonePe onboarding credentials)
- Shipping/fulfillment integration, live gateway mode
- Account-linked (DB) wishlist, unify the two product-card markups
- Refunds/saved cards/subscriptions/UPI intent/EMI

---

## 6. Recommended Next Phase

**Phase 5B: Customer Forgot Password** — recommended first (with 5E
Email Notifications as the enabling companion), because:

1. **Email delivery is a hard prerequisite for the highest-value pending items.** Customer Forgot Password needs a real reset-link email; order confirmation, payment-confirmed, and shipped notifications all need the same subsystem. Building it once unlocks most of the pending list.
2. **It closes a real security/UX gap today.** The admin already has Forgot/Reset Password; customers do not — the only account-recovery path is contacting support.
3. **Scoped cleanly without new third-party risk:** a simple SMTP mailer (PHPMailer or a thin `mail()` wrapper consistent with the project's dependency-free style) + a `password_reset_tokens` table mirroring the existing `admin_password_resets`, reusing the lockout helpers from Phase 5G.

Suggested scope: email settings in `admin/settings.php` (SMTP host/user/pass/from), a shared `includes/email-functions.php`, customer Forgot/Reset Password pages (mirroring `admin/forgot-password.php`/`reset-password.php`), and order lifecycle notification emails (placed / payment confirmed / shipped / cancelled) wired into the existing `update_order_payment_status()` / `update_order_status()` transition points.

---

## Appendix A — Deployment ZIP Verification Results

| Item | Value |
|---|---|
| **1. ZIP filename** | `moonaura-v0.4.8-full-checkpoint-20260812.zip` |
| **2. ZIP size** | 7,426,748 bytes (~7.08 MiB) |
| **3. Total files / directories** | 186 files / 37 directories (223 total entries) |
| **4. Verification results** | Integrity: `unzip -t` — "No errors detected in compressed data" ✅ |
| | Content parity: ZIP file list diffed against working project (`find . -type f` minus `.git`) — **IDENTICAL, no missing/extra files** ✅ |
| | Exclusions: no `.git/`, `node_modules/`, `vendor/`, `.log`, `error_log`, or cache/temp files present ✅ |
| | Key new files confirmed inside: `includes/stock-functions.php`, `includes/login-security.php`, `database/migration_phase5_order_tracking.sql`, `database/migration_phase6_stock_login.sql` ✅ |
| | SHA-256: `4fbe83aa8d57adfca7e5ad3272fd07bd42cb3873bc9c55afbf7ca902d989cc14` |
| **5. Full path** | `/tmp/opencode/moonaura-v0.4.8-full-checkpoint-20260812.zip` |

*Note: this report file was created after the ZIP snapshot was taken, so the ZIP's contents match the working code tree exactly at snapshot time.*
