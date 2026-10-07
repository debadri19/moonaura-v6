# MoonAura Crystals — Project State

> **Read this file first in any new session.** It reflects the verified
> current workspace, not a plan. If this file conflicts with the code,
> the code wins.

**Documented product version: v0.6.7**

**Repository context (this workspace):**

- Git branch: `master`
- HEAD: `4e037bb` — merge of GitHub `debadri19/moonaura-v6` `main` (`fbb47f5`) into this workspace
- Unpublished storefront UI refinements exist in the working tree (CSS + `index.php` + `support.php`). Treat them as current source. Do not pull, reset, restore, or clean over them.
- Admin code lives in `dashboard/` (not `admin/`). Older docs that say `admin/*.php` mean `dashboard/*.php`.

Last documentation refresh: 7 October 2026. This refresh does not bump the product version.

---

## 1. Stack

| Layer | Implementation |
|---|---|
| Language | PHP 8+ |
| Database | MySQL / MariaDB, PDO, prepared statements |
| Config | `config/config.php` + gitignored `.env` / `config.local.php` |
| Storefront | Server-rendered PHP + HTML + CSS + Vanilla JS |
| Admin | PHP under `dashboard/` |
| CSS | Global `assets/css/style.css` + per-page CSS files |
| JS | Vanilla JS in `assets/js/` |
| Payments | Razorpay (only registered online gateway), COD, Manual UPI QR |
| Email | Brevo SMTP + PHPMailer (`includes/mailer.php`) |
| Newsletter | Brevo Contacts API (`includes/newsletter-functions.php`) |
| Invoices | On-demand PDF (`includes/lib/SimplePdfWriter.php`) |
| Theme | Light / Dark / System via `html[data-theme]` |

**Not in this project:** React, Vue, Angular, Next.js, Laravel, WordPress, Shopify, Bootstrap, Tailwind, SPA routing.

**Not in this codebase:** `CashfreeGateway`, `PhonePeGateway`, `webhook-cashfree.php`. Historical Cashfree/PhonePe settings rows are inert.

---

## 2. Architecture

```
config/config.php          env, SITE_URL, ADMIN_URL, secrets mapping
includes/                  shared PHP (auth, cart, orders, tax, mail, payments)
includes/header.php        storefront header
includes/footer.php        storefront footer, theme JS, GA4, Meta Pixel
dashboard/                 admin panel
account/                   customer account pages
assets/css/                storefront CSS
assets/js/                 storefront JS
database/                  schema.sql, seed.sql, incremental migrations
```

Page pattern: PHP bootstrap → `theme_boot()` in `<head>` → page CSS → `includes/header.php` → content → `includes/footer.php`.

Public asset URLs: `asset_url()` always prefixes `SITE_URL`. Admin-local files (`dashboard/assets/css/admin.css`) stay on `ADMIN_URL`.

Details: `docs/FRONTEND_ARCHITECTURE.md`, `docs/BACKEND_INTEGRATION.md`.

---

## 3. Major Modules

### Storefront

| Page | File |
|---|---|
| Home | `index.php` |
| Shop | `shop.php` |
| Product | `product.php` |
| Cart | `cart.php` |
| Checkout | `checkout.php` |
| Buy Now | `buy-now.php` |
| Wishlist | `wishlist.php` |
| About | `about.php` |
| Support / Help | `support.php` |
| Policy | `policy.php` |
| Concerns | `concerns.php`, `concern.php` |
| Order success | `order-success.php` |
| Payment | `payment.php`, `payment-verify.php`, `payment-failure.php`, `payment-retry.php` |
| Manual UPI | `manual-upi-payment.php` |
| Newsletter | `newsletter-subscribe.php`, `newsletter-confirmed.php` |
| Sitemap | `sitemap.php` (`/sitemap.xml` via `router.php` / server rewrite) |
| Guest invoice | `guest-invoice.php` |

### Customer account (`account/`)

Login, register, logout, dashboard, orders, order-detail, invoice, addresses, profile, change-password, forgot/reset password, theme-save.

### Admin (`dashboard/`)

Login, 2FA setup/verify, dashboard, products, categories, customers, orders, order-create, order-detail, invoice, invoice-designer, settings, recovery-download, forgot/reset password.

### Shared CSS

`style.css`, `header.css`, `footer.css`, `home.css`, `shop.css`, `product.css`, `cart.css`, `checkout.css`, `account.css`, `about-us.css`, `policy.css`, `support.css`, `wishlist.css`.

Admin CSS: `dashboard/assets/css/admin.css` (no storefront Dark Mode tokens).

---

## 4. Important Integrations

| Integration | Status in this workspace | Notes |
|---|---|---|
| MySQL + PDO | Implemented | `includes/db.php` |
| Razorpay | Implemented | `includes/payments/RazorpayGateway.php`, `webhook-razorpay.php` |
| COD | Implemented | Checkout + admin payment-status |
| Manual UPI QR | Implemented | Customer submit + admin verify/reject |
| Cashfree / PhonePe | **Not in use** | No gateway class |
| Brevo SMTP | Implemented | Password reset + order confirmation/shipped/delivered |
| Brevo Contacts newsletter | Implemented (storefront only) | Pending list 7, confirmed list 6 |
| Admin newsletter campaigns | **Not implemented** | No campaign composer/sender in `dashboard/` |
| GST / tax snapshots | Implemented | `includes/tax-functions.php` |
| Invoice PDF + designer | Implemented | `includes/invoice-functions.php`, `dashboard/invoice-designer.php` |
| Admin TOTP 2FA | Implemented | `includes/two-factor.php` |
| GA4 | Implemented, env-gated | `includes/analytics-functions.php`, `assets/js/ga4.js` |
| Meta Pixel + CAPI | Implemented, env-gated | `includes/meta-pixel-functions.php`, `includes/meta-capi-functions.php` |
| Sitemap / robots | Implemented | `sitemap.php`, `robots.txt` |

Credentials are env-only. Do not document real keys, tokens, or `.env` values.

---

## 5. Current Implementation State

The PHP/MySQL ecommerce conversion is **functionally implemented** for catalog, cart, checkout, accounts, admin, payments, GST invoices, stock, and transactional email.

Storefront Light / Dark / System theme is implemented (`assets/js/theme.js`, CSS tokens in `style.css`). Logged-in customers persist preference via `account/theme-save.php` and `customers.theme_preference`.

**Admin Dark Mode is not implemented.**

**Walk-in / POS (Phase 5H) is not implemented.** Support Address card states: "Online Store Only • No Walk-in Store".

Coupons are not implemented (`checkout.php` keeps discount at 0).

### Newsletter (accurate)

**Implemented**

- Homepage form POSTs to `newsletter-subscribe.php`
- CSRF + server-side email validation
- Brevo Contacts API via `BREVO_API_KEY`
- New / unconfirmed contacts go to pending list (default id 7)
- Confirmation mail and move to confirmed list (default id 6) are handled by a **Brevo Automation**, not by this app
- `newsletter-confirmed.php` is a landing page only
- No local subscriber table

**Not implemented**

- Admin newsletter / email campaign management
- In-app campaign composer, audience picker, or blast sender

### Payments (accurate)

Checkout offers gateways that are **both enabled and configured**. `PaymentManager` registers **only** `razorpay`. COD and Manual UPI are separate checkout options.

### Recent storefront UI (working tree, implementation complete)

These exist in current CSS/PHP. **Visual QA is pending** unless a human has signed off in browser.

| Item | Evidence |
|---|---|
| Compact `.product-btn` / matched `.cart-btn` (40px, radius 16px) | `assets/css/home.css` |
| Circular policy / About / mobile-nav icon wells | `policy.css`, `about-us.css`, `header.css` |
| Desktop active nav: short gold bar under item | `header.css` `.navbar > ul > li > a.is-active::after` |
| Mobile active nav: left gold inset bar | `header.css` `.mobile-nav>a.is-active` |
| Home Hero padding / height tokens | `home.css` `.hero` |
| Shop toolbar vertical padding equalized | `shop.css` `.shop-toolbar` |
| Policy mobile shortcuts 2-column grid | `policy.css` `@media (max-width:768px) .policy-nav` |
| Lucide-style zodiac stroke SVGs | `index.php` `HOMEPAGE_ZODIAC_SIGNS` |
| Dark Mode scrollbar thumbs | `style.css` `html[data-theme="dark"]` |
| Dark Mode heading/link tokens + Account/Home remaps | `style.css`, `account.css`, `home.css` |
| Dark Mode `.btn-outline` via `--color-outline` | `style.css` |
| Policy Hero compact heading + nowrap eyebrow | `policy.css` |
| About mobile Our Promise 2×3 grid | `about-us.css` |
| Support "Online Store Only • No Walk-in Store" | `support.php` `.info-availability` |

**Not claimed complete:** newsletter input vs Subscribe button size/alignment (input height 54px, button uses `--btn-height` 42px). Full-site visual QA. Production verification.

---

## 6. Completed Roadmap (condensed)

Earlier `PROJECT_STATE.md` rows that still match source:

| Phase | Scope | Code status |
|---|---|---|
| 0–3D | Foundation, catalog, cart, checkout, accounts, invoices, admin orders | Implemented |
| 4A | Session wishlist | Implemented |
| 4B | Customer UI audit series | Implemented (historical) |
| 4C | Multi-gateway flags + admin settings | Implemented; only Razorpay class remains |
| 4D | Manual UPI QR + admin verify/reject | Implemented |
| 5A | Search + order tracking/timeline | Implemented |
| 5B | Customer forgot/reset password | Implemented |
| 5C | Admin manual order create | Implemented |
| 5D | Email infra + order confirmation/shipped/delivered | Implemented |
| 5E | Admin TOTP 2FA + session hardening | Implemented |
| 5F | Inventory automation | Implemented |
| 5F GST | GST-inclusive rates + snapshots | Implemented |
| 5F.1 | Admin 2FA recovery codes | Implemented |
| 5G Login | Login lockout | Implemented |
| 5G Invoice | GST invoice rendering + branding | Implemented |
| 6 | Invoice Designer | Implemented |
| Homepage UX | Featured, best sellers, concerns, zodiac | Implemented |
| v0.6.1–v0.6.7 | Asset URLs, certificate flag, Razorpay guard, newsletter, nav cleanup, sitemap, GA4 env | Implemented |

Historical Cashfree “confirmed on a real server” notes are **legacy**. Current code has no Cashfree gateway.

---

## 7. Pending

See `PROJECT_STATUS.md` for the live list. High level:

- Phase 5H POS / walk-in sales — not started (and storefront now states online-only)
- Admin Dark Mode — not started
- Admin Newsletter & Email Campaign Management — not started
- Newsletter input + Subscribe button size/alignment
- Full Site Visual QA
- Meta Pixel Events Manager QA / later Pixel phases
- Live admin deployment finalization
- Final production audit
- Coupons / shipping-rules table
- Account-linked (DB) wishlist

Do not re-open UI items already implemented in the working tree (product CTA size, Home Hero padding, Policy 2-col shortcuts, zodiac SVGs, Dark scrollbar, Account/Home heading contrast, Policy Hero type, About mobile Promise grid, Support online-only note).

---

## 8. Known Limitations (still true in source)

- Wishlist is session-based, not account-linked
- No coupon table; checkout discount stays 0
- PDF owner-password / `/Encrypt` is not implemented (`SimplePdfWriter` has no encryption)
- Checkout state is free text, not a dropdown
- Admin buttons are not the storefront pill system
- Guest invoice download requires `INVOICE_TOKEN_SECRET` to be set; empty secret fails closed
- Analytics/Pixel emit nothing unless env IDs are valid
- Losing `ADMIN_2FA_ENCRYPTION_KEY` makes 2FA secrets unrecoverable

---

## 9. Documentation Context

| File | Role |
|---|---|
| `README.md` | Overview and map |
| `PROJECT_STATUS.md` | Completion / QA / pending |
| `CHANGELOG.md` | Chronological verified changes |
| `SETUP.md` | How to run and configure |
| `DEPLOYMENT_CHECKLIST.md` | Migrations and launch checks |
| `docs/DESIGN_SYSTEM.md` | Tokens and visual system |
| `docs/UI_RULES.md` | Binding UI constraints |
| `docs/FRONTEND_ARCHITECTURE.md` | PHP/CSS/JS structure |
| `docs/BACKEND_INTEGRATION.md` | Integrations and endpoints |
| `AI_INSTRUCTIONS.md` | Agent safety rules |
| `RECOVERY_PROMPT.md` | Safe Git recovery |

Stale claims removed from this refresh:

- README “remaining: cart, admin, checkout, auth” — those exist
- PROJECT_STATE §1 “5C/5D/5E not started” — they exist
- “Buy Now is a dead button” — `buy-now.php` exists
- “No email notifications” — mailer + order emails exist
- “GST hardcoded to 0” — tax snapshots exist
- `admin/*.php` paths — actual path is `dashboard/`
- Cashfree as the active gateway — Razorpay is the only registered online gateway
