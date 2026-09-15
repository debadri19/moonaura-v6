# MoonAura Crystals — Project Status Report

> Handoff checkpoint: v0.6.7. Phase 5D Step 1+2 (email) + Phase 5E
> (admin TOTP 2FA) + Phase 5F.1 (recovery codes) + Phase 5F (GST & Tax
> architecture) + Phase 5G (GST-compliant invoice system & invoice
> branding) + database migration/ordering audit (canonical order
> finalized, live-verified) + Homepage/Frontend UX pass (mobile nav
> cleanup, About Us wording, Featured/Best Selling/Shop By Concern/
> Shop By Zodiac homepage sections, new Concern Category
> classification + admin field, global favicon audit) + Concern UI
> Cleanup (semantic per-concern icons, legacy homepage section
> removed) + UI & Responsive Fixes (Part 1) + Checkout/Buy Now/Saved
> Address/GST Display (Part 2) + Order Confirmation & My Account UX
> (Part 3) + **Phase 6 Advanced Invoice Designer System — COMPLETE**.
> Local verification green across all suites.
> Nothing from Phases 5D-5G / the DB audit / the UX pass / the
> cleanup checkpoint / Parts 1-3 / Phase 6 is committed/pushed yet.

---

## Latest v0.6.7 Update

GA4 server-side environment configuration, on the frozen navigation/payment/shop/newsletter base:

- Gitignored `.env` now sets `GA4_MEASUREMENT_ID`, `GA4_PROPERTY_ID`, and `GA4_CREDENTIALS_PATH`. Existing `config.php` env mapping is reused. Credentials are not hardcoded.
- Service-account JSON remains outside the web root. `moonaura-ga4.json` is gitignored.
- Admin Dashboard Visitors card still reads Current = realtime `activeUsers` and Total = current calendar-month `totalUsers` when the credentials file is readable. No visitor values are faked if the file is missing.

Razorpay mapping guard, configured-gateway checkout, Shop card actions, asset URLs, Certificate Included, homepage newsletter, and storefront navigation remain.

## Completed

| Phase | Scope | Status |
|---|---|---|
| 1-3 | Catalog, cart, checkout, payments (Cashfree/Razorpay/COD), invoices, admin modules | Implemented (earlier phases) |
| 4A | Wishlist | Implemented |
| 4B | UI/UX refinement (10-batch customer audit) | Implemented |
| 4C | Multi-gateway payment checkout + admin settings | Implemented & verified |
| 4D | Manual UPI QR payment + admin verify/reject workflow | Implemented & verified |
| 5A | UI/UX & order tracking (search, tracking, timeline, admin shipping fields) | Implemented & verified locally |
| 5B | Customer forgot/reset password | Implemented & verified locally |
| 5C | Admin manual order creation | Implemented & verified locally (62/62) |
| 5D Step 1 | Email infrastructure (Brevo SMTP + PHPMailer) + forgot-password emails (admin + customer) | Implemented & verified locally (30/30) |
| 5D Step 2 | Order confirmation / shipped / delivered emails (dedup via `order_email_log`, fail-closed SMTP, Reply-To support) | Implemented & verified locally (22/22 + SMTP-failure/retry drill + regression) |
| 5E | Admin security hardening + TOTP 2FA (RFC 6238, AES-256-GCM secrets, session hardening, POST logout) | Implemented & verified locally (44/44 + 11/11 fail-closed) |
| 5F | Inventory automation (stock deduction, no-negative-stock, restore on cancel) | Implemented & verified locally |
| 5F (GST) | GST & Tax architecture (product-wise rates, GST-inclusive pricing, intra/inter tax-type resolution, order + item tax snapshots, admin rate/seller-state management) | Implemented & verified locally (60/60) |
| 5F.1 | Admin 2FA recovery codes (one-time codes, hashed storage, download, security log) | Implemented & verified locally (43/43) |
| 5G (Login Security) | Login security (lockout, no enumeration) | Implemented & verified locally (18/18) |
| 5G (Invoice) | GST-compliant invoice system & invoice branding (per-line GST Rate column, Taxable Value + CGST/SGST/IGST breakdown, PDF `/Info` metadata, "A Brand by DS Lifestyle" letterhead + optional website line, admin Business/GST settings incl. GSTIN, invoice `?mode=download` attachment flow) | **COMPLETE** — Implemented & verified locally (42/42) |
| — | Homepage/Frontend UX pass: mobile nav duplicate-link cleanup, About Us "OUR PROMISE" wording, Featured Products + Best Selling Products (real-sales-ranked) + Shop By Concern + Shop By Zodiac Sign homepage sections, new Concern Category classification (13 presets, `concern_categories`/`product_concerns`, `concerns.php`/`concern.php`, admin multi-select field), global favicon audit across all customer + admin pages | **COMPLETE** — Implemented & verified locally |
| — | Concern UI Cleanup: semantic per-concern Font Awesome icons on `/concerns.php` and the individual concern page (13/13 distinct, no generic fallback among seeded concerns; homepage teaser icons unchanged), legacy "Curated Collection / Career Success Collection" section removed from the homepage (dedicated concern page and Shop By Concern unaffected) | **COMPLETE** — Implemented & verified locally |
| — | Part 1 UI & Responsive Fixes: shop search bar (full-width, pill-shaped) + filter alignment, mobile header fit on narrow phones, product-page Add to Cart/Buy Now/Wishlist mobile layout, cart-summary button centering fix | **COMPLETE** — Implemented & verified locally |
| — | Part 2 Checkout/Buy Now/Saved Address/GST Display: Buy Now (skips cart, guest + logged-in, all 4 payment methods), Saved Address selector + auto-fill + auto-save-with-dedup, real GST derivation on cart/checkout summaries (was hardcoded ₹0 on the preview only - real orders were always taxed correctly) | **COMPLETE** — Implemented & verified locally |
| — | Part 3 Order Confirmation & My Account UX: order-success.php heading/message/Estimated Delivery/Trust Info sections, dashboard Recent Orders mobile-card labels, saved-address Edit/Delete button alignment, order-detail "Payment Details" heading | **COMPLETE** — Implemented & verified locally |
| 6 | Advanced Invoice Designer System: admin-configurable invoice layout (logo/watermark upload+placement, branding, header, order/payment/address info, product table, tax summary, footer) via `invoice_designer_settings` + `admin/invoice-designer.php`, live A4 preview, reset-to-defaults. Refactored `build_invoice_pdf()` verified **byte-for-byte identical** output at default settings against a pre-refactor baseline | **COMPLETE** — Implemented & verified locally |

## In Progress

- None. All scoped work is complete; Phase 5H is queued (see Pending).
  Roadmap-naming note: the roadmap's "Phase 5F" line item is inventory
  automation (above); this checkpoint's GST & Tax work is the **Phase 5F
  (GST)** row (migration `migration_phase5f_gst_tax.sql`). Similarly,
  "Phase 5G" has been used informally for two different things in this
  project's history: the earlier login-security hardening (row
  **5G (Login Security)** above) and the roadmapped **Phase 5G** invoice
  enhancement (row **5G (Invoice)** above, shipped as v0.5.6). The
  invoice row is the one the roadmap and `DEPLOYMENT_CHECKLIST.md` mean
  by "Phase 5G".

## Pending

- **Phase 5H** — POS / walk-in sales.
- **PDF permission restriction (Invoice Designer, Phase 6)** —
  researched, not implemented: `SimplePdfWriter` has no `/Encrypt`
  support, and implementing it correctly means hand-rolling the PDF
  Standard Security Handler's own encryption algorithm - judged too
  risky to attempt safely. Documented as a known limitation rather
  than worked around; no customer-facing password was added either
  way (that was never in scope).
- **State dropdown / billing vs shipping separation** — checkout state
  is free text today; a dropdown (future DS Lifestyle billing/shipping
  split) would make intra/inter-state resolution exact instead of
  best-effort normalized matching.
- **Admin button style consistency** — align admin buttons with the
  customer "Shop Now" pill style.
- **Admin UI design audit**.
- **Production readiness review** — real-server verification of Phases
  4B-5G plus this checkpoint's Homepage/Frontend UX pass (only some
  phases have been verified on a real server).
- **`shop.php`'s pre-existing "concern" dropdown filter** (LIKE-matches
  `products.purpose` against ~5 generic values - Love/Career/Money/
  Health/Protection) and the new 13-preset Concern Category system
  (`concern_categories`/`product_concerns`) are intentionally two
  separate things today, per this checkpoint's brief (Purpose must not
  be repurposed). A future pass could fold the old filter into the new
  taxonomy if that's ever wanted.

## Known Bugs

- None known in the verified scopes. Test artifacts from the local
  verification runs remain in the dev DB (`admin_recovery_codes`,
  `admin_security_log`, `login_attempts`, the Phase 5D Step 2 test
  orders and `order_email_log` rows, plus the Phase 5F GST test
  products `GST-P01..P05` and their test orders) - these are dev-only
  and harmless; the dev admin is left at the 2FA-off baseline.

## Deployment Notes

- Dev server runs PHP 8 + MySQL (`moonaura` DB), port 8090.
- Phases 5D-5F.1, Phase 5F (GST), Phase 5G (invoice system), the
  v0.5.7 database migration/ordering audit, the v0.5.8
  Homepage/Frontend UX pass (Concern Category architecture, homepage
  sections, favicon audit), the v0.5.9 Concern UI Cleanup (semantic
  concern icons, legacy homepage section removal), Parts 1-3 (UI &
  Responsive Fixes, Checkout/Buy Now/Saved Address/GST Display, Order
  Confirmation & My Account UX), and Phase 6 (Advanced Invoice
  Designer System) are all **uncommitted** (local working tree) -
  commit/push was intentionally deferred by request throughout.
- `test-pdf.php` (a dev-only diagnostic script that reads a hardcoded
  local Windows temp path — not part of the application) is excluded
  from the packaged handoff ZIP as of v0.5.7; it remains in the local
  working tree only.
- Local email testing uses a dev SMTP sink (Brevo credentials point at
  `127.0.0.1:2525`); production must use real Brevo SMTP settings.
- Order emails send From `noreply@moonauracrystals.in` (default
  `MAIL_FROM_ADDRESS`) and Reply-To `support@moonauracrystals.in`
  (default `MAIL_REPLY_TO_ADDRESS`) unless overridden in the env.
- The admin TOTP secret + recovery codes are encrypted/hashed with
  `ADMIN_2FA_ENCRYPTION_KEY`; losing/rotating it makes 2FA secrets
  unrecoverable (fail-closed by design).
- Product prices are **GST-INCLUSIVE** (Phase 5F GST): each product
  carries its own `gst_rate` (default 0.00), and order GST is derived
  out of the inclusive price at order creation and snapshotted. The
  seller's state is stored in the `business_state` setting (Admin >
  Settings > Business/GST); while unset, orders store `tax_type = NULL`
  and split GST conservatively as CGST+SGST.

## Database Migrations Required

**Canonical order** (matches `DEPLOYMENT_CHECKLIST.md`; this list was
previously out of sync with that file - see the CHANGELOG "Migration
order documentation audit" entry). All migrations are idempotent and
safe to re-run. Live-verified (fresh install + upgrade-from-baseline,
this order and the tracking/password-reset/4C/4D-earlier order both
tested) to produce byte-identical schemas:

1. `database/migration_phase4c_multi_gateway_checkout.sql` (Phase 4C, if upgrading)
2. `database/migration_phase4d_manual_upi_qr.sql` (Phase 4D, if upgrading)
3. `database/migration_phase5_order_tracking.sql` (Phase 5A)
4. `database/migration_phase6_stock_login.sql` (Phases 5F/5G) - **hard
   dependency: must run after #3** (`orders.stock_deducted_at` is
   added `AFTER tracking_url`, a column #3 creates)
5. `database/migration_phase5b_customer_password_reset.sql` (Phase 5B)
6. `database/migration_phase5e_admin_2fa.sql` (Phase 5E)
7. `database/migration_phase5f1_recovery_codes.sql` (Phase 5F.1)
8. `database/migration_phase5d2_order_emails.sql` (Phase 5D Step 2)
9. `database/migration_phase5f_gst_tax.sql` (Phase 5F GST - product GST
   rates, order/item tax snapshot columns, `business_state` setting)
10. `database/migration_add_concern_categories.sql` (Homepage/Frontend
    UX pass, v0.5.8 - `concern_categories` + `product_concerns` tables,
    seeded with the 13 approved presets)
11. `database/migration_add_invoice_designer_settings.sql` (Phase 6 -
     `invoice_designer_settings` single-row JSON config table for the
     Invoice Designer admin panel). No ordering dependency on any other
     migration in this list.
12. `database/migration_add_certificate_included.sql` (`products.certificate_included`
     TINYINT default 1). No ordering dependency on any other migration
     in this list.

Migrations 1-2 and 5-10 have no ordering dependency on each other or on
#3/#4 (each only touches its own new table/columns or the generic
`settings` table) - #3 before #4 is the only ordering that must be
preserved.

A fresh install loads `database/schema.sql` + `database/seed.sql` which
include all of the above.

## Environment Variables Required

| Variable | Purpose |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database connection |
| `SITE_URL` | Storefront base URL (emails, links, public assets) |
| `ADMIN_URL` | Admin base URL (defaults to `SITE_URL/admin`) |
| `ADMIN_2FA_ENCRYPTION_KEY` | 32-byte base64 key for AES-256-GCM 2FA secrets (required for 2FA) |
| `BREVO_SMTP_HOST`, `BREVO_SMTP_PORT` | Brevo SMTP host/port |
| `BREVO_SMTP_USERNAME`, `BREVO_SMTP_PASSWORD` | Brevo SMTP credentials |
| `BREVO_SMTP_SECURE` | Optional: auto/tls/ssl/none |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Sender address/name |
| `MAIL_REPLY_TO_ADDRESS` | Optional Reply-To for transactional emails (default `support@moonauracrystals.in`) |
| Invoice/gateway settings | Payment gateway keys + invoice settings (see `SETUP.md`) |

See `SETUP.md` for the full environment reference.
