# MoonAura Crystals

Custom PHP ecommerce storefront for **MoonAura Crystals** — an online shop for natural crystal bracelets, rings, pendants, trees, pyramids, and related products.

> **Current documented product version: v0.6.7**
> **Workspace:** `master` at commit `4e037bb`, plus unpublished storefront UI refinements in the working tree.
> **Source of truth:** the current workspace. If a document conflicts with the code, the code wins.

## IMPORTANT FOR AI

- Preserve the current design and user experience.
- Do not pull, reset, restore, clean, or checkout over unpublished work.
- Do not introduce React, Vue, Angular, Next.js, Laravel, WordPress, Shopify, Bootstrap, or Tailwind.
- Ask before changing existing frontend layout, colors, typography, or components.
- Never commit or print secrets, API keys, tokens, or `.env` values.

See `AI_INSTRUCTIONS.md` and `RECOVERY_PROMPT.md`.

---

## Project Overview

The site is a **server-rendered PHP 8 + MySQL/MariaDB** storefront with a separate **admin panel** under `dashboard/`. Pages are PHP templates with shared header/footer includes, page-specific CSS, and Vanilla JavaScript. There is **no SPA** and **no frontend framework**.

The original static HTML design was converted in place. The storefront is functionally complete for catalog, cart, checkout, accounts, payments, invoices, and email. Remaining work is primarily visual QA, production verification, admin Dark Mode, and an admin newsletter campaign system.

---

## Technology Stack

| Layer | Current implementation |
|---|---|
| Runtime | PHP 8+ |
| Database | MySQL / MariaDB via PDO prepared statements |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Admin | PHP pages under `dashboard/` |
| Payments | Razorpay (online), Cash on Delivery, Manual UPI QR |
| Email | Brevo SMTP + PHPMailer (transactional) |
| Newsletter | Brevo Contacts API (storefront double opt-in only) |
| Invoices | On-demand PDF via `SimplePdfWriter` |
| Analytics | GA4 (gtag + optional Reporting API), Meta Pixel + CAPI |

**Do not use:** React, Vue, Angular, Next.js, Laravel, WordPress, Shopify, Bootstrap, Tailwind.

---

## Architecture Summary

- Each public page is a PHP file that loads `config/config.php`, helpers, then renders HTML.
- Shared chrome: `includes/header.php`, `includes/footer.php`.
- Global CSS: `assets/css/style.css` (tokens, buttons, theme). Page CSS is loaded per route (`home.css`, `shop.css`, `about-us.css`, and so on).
- Admin chrome: `dashboard/includes/admin-header.php`, `admin-sidebar.php`, `admin-footer.php`.
- Business logic lives in `includes/*.php` (cart, orders, tax, stock, mail, payments, theme, analytics).
- Public assets always load from `SITE_URL` via `asset_url()`. Admin-local assets stay on `ADMIN_URL`.

Details: `docs/FRONTEND_ARCHITECTURE.md`, `docs/BACKEND_INTEGRATION.md`.

---

## Major Implemented Functionality

- Dynamic catalog: homepage, shop (search/filter/sort/pagination), product detail, concerns, zodiac
- Session cart and wishlist, AJAX add/update/remove
- Guest and customer checkout, Buy Now, saved addresses
- Payments: Razorpay, COD, Manual UPI QR (admin verify/reject)
- Customer accounts: register/login, orders, tracking timeline, profile, addresses, invoices, theme preference
- Admin: products, categories, customers, orders (status/shipping/payment), manual order create, settings, invoice designer, TOTP 2FA
- GST-inclusive pricing with intra/inter-state snapshots and GST invoices
- Inventory deduction/restore tied to payment confirmation / cancel
- Transactional email: password reset, order confirmation / shipped / delivered
- Storefront newsletter signup (Brevo pending list → confirmation → confirmed list)
- Light / Dark / System theme on the storefront
- Dynamic `/sitemap.xml` (`sitemap.php`) and `robots.txt`
- GA4 and Meta Pixel/CAPI hooks (enabled only when env IDs are configured)

---

## Setup Requirements

You need PHP 8+, MySQL/MariaDB, and a web server (Apache, nginx, or PHP built-in). Full steps are in `SETUP.md`.

Minimum:

1. Create database `moonaura` and import `database/schema.sql` then `database/seed.sql`.
2. Configure gitignored `.env` or `config/config.local.php` (never commit credentials).
3. Create the first admin at `dashboard/setup.php`, then remove that file.
4. Set `SITE_URL`, database credentials, and (for live) Razorpay, Brevo, and 2FA keys.

See `DEPLOYMENT_CHECKLIST.md` for migrations and production checks.

---

## Development / Deployment Notes

- Branch: `master`. Last recorded merge: GitHub `debadri19/moonaura-v6` `main` into this workspace (`4e037bb`).
- Unpublished storefront UI CSS/PHP edits exist in the working tree. Treat them as current source, not discarded work.
- Admin path on disk is `dashboard/` (not `admin/`). `ADMIN_URL` defaults to `SITE_URL/dashboard`.
- Cashfree and PhonePe gateway classes are **not** present. Do not configure them.
- Storefront newsletter exists. **Admin newsletter / email campaign management does not.**
- Visual QA of recent UI work is **implementation complete, visual QA pending**. Do not treat code edits as production-verified.

---

## Documentation Map

| File | Purpose |
|---|---|
| `PROJECT_STATE.md` | Technical state, stack, modules, integrations |
| `PROJECT_STATUS.md` | Completed / in progress / pending / QA |
| `CHANGELOG.md` | Chronological verified changes |
| `SETUP.md` | Local and production setup |
| `DEPLOYMENT_CHECKLIST.md` | Migrations and launch checks |
| `AI_INSTRUCTIONS.md` | Safe rules for AI coding agents |
| `RECOVERY_PROMPT.md` | Safe Git recovery workflow |
| `SINGLE_SHOT_RECOVERY_PROMPT.md` | Copy-paste recovery prompt |
| `docs/DESIGN_SYSTEM.md` | Tokens, theme, typography, buttons, icons |
| `docs/UI_RULES.md` | Binding UI rules (do / do not) |
| `docs/FRONTEND_ARCHITECTURE.md` | PHP/CSS/JS structure |
| `docs/BACKEND_INTEGRATION.md` | Payments, email, GST, analytics, endpoints |
