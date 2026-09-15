## IMPORTANT FOR AI

If you are unsure about any implementation detail, ASK before changing the existing frontend.

Preserving the current design and user experience is more important than introducing new UI or architectural changes.

# MoonAura Crystals – E-commerce Website

> **Latest project checkpoint (v0.6.7):** GA4 Measurement ID, Property ID, and credentials path are set in gitignored `.env`. Reporting API credentials stay outside the web root and are not committed. Admin Visitors card still uses realtime `activeUsers` and calendar-month `totalUsers` when the credentials file is readable. No database migration required.


## Project Overview

This project is a custom-built ecommerce website for **MoonAura Crystals**, an online store selling natural crystal bracelets, rings, pendants, crystal trees, pyramids, and related spiritual products.

The website frontend has already been designed and is currently built using **HTML, CSS, and Vanilla JavaScript**.

The primary objective is to convert this existing static website into a **fully functional production-ready ecommerce platform** without changing the existing design.

---

# IMPORTANT

## The frontend design is already finalized.

Do NOT redesign the website.

Do NOT change:

- Layout
- UI
- Colors
- Typography
- Spacing
- Components
- Animations
- Icons
- Overall visual appearance

The existing frontend should remain visually identical.

Only implement missing functionality and backend features.

---

# Current Tech Stack

Current:

- HTML5
- CSS3
- Vanilla JavaScript

Target Stack:

- PHP 8+
- MySQL
- HTML
- CSS
- Vanilla JavaScript

Do NOT use:

- React
- Vue
- Angular
- Next.js
- Laravel
- WordPress
- Shopify
- Bootstrap
- Tailwind CSS

---

# Current Project Status

Completed:

- Homepage (Partially)
- Header
- Footer
- About Page
- Support Page
- Policy Page
- Responsive Layout
- Global Styling
- Product Database Planning

Remaining:

- Dynamic Product System
- Admin Panel
- Shopping Cart
- Checkout
- Authentication
- Orders
- Inventory
- Customer Management
- Payment Integration
- Shipping Integration
- SEO
- Security
- Performance Optimization

---

# Product Database

The product information is maintained separately in a Google Sheets master database.

The database includes:

- SKU
- Product Name
- Slug
- Category
- Crystal Type
- Purpose
- Zodiac
- Chakra
- Variant
- Image Folder
- Image Count
- Display Order
- MRP
- Retail Sell Price
- Short Description
- Full Description
- Care Instructions
- Meta Title
- Meta Description
- Primary Benefits
- Weight
- Featured
- Stock Quantity
- Stock Status
- Created Date
- Updated Date
- Crystal Origin

Images, Categories, and Attributes are maintained in separate sheets.

---

# Product Images

Do NOT generate product images.

I will manually add product images later.

Please write the code assuming images will exist inside:

assets/images/products/

Example:

assets/images/products/

    bracelet/
        tiger-eye-bracelet/
            1.webp
            2.webp
            3.webp

    ring/
        red-carnelian-ring/

    pendant/

    crystal-tree/

Image paths should be generated dynamically.

---

# Folder Structure

Keep the existing project structure whenever possible.

Only improve it if absolutely necessary.

Avoid unnecessary file renaming.

Use reusable includes where appropriate.

---

# Backend Requirements

Build a complete ecommerce backend including:

- MySQL Database
- PHP Backend
- Admin Dashboard
- Product CRUD
- Category CRUD
- Customer Management
- Orders
- Inventory
- Coupons
- Website Settings

---

# Product Features

Implement:

- Dynamic Products
- Category Pages
- Product Details
- Product Gallery
- Related Products
- Search
- Filters
- Sorting
- Pagination
- Featured Products
- New Arrivals
- Best Sellers

---

# Shopping Features

Implement:

- Shopping Cart
- Mini Cart
- Wishlist (optional)
- Coupon System
- GST Calculation
- Shipping Calculation
- Checkout
- Order Confirmation

---

# Security

Implement production-ready security:

- Prepared Statements
- SQL Injection Protection
- XSS Protection
- CSRF Protection
- Secure Sessions
- Input Validation

---

# SEO

Support:

- Clean URLs
- Slugs
- Meta Title
- Meta Description
- Canonical URLs
- Open Graph
- robots.txt
- sitemap.xml
- Structured Data (Schema.org)

---

# Performance

Optimize:

- Lazy Loading
- Responsive Images
- Optimized Queries
- Efficient File Structure
- Clean Reusable Code

---

# Coding Style

- Write clean, modular code.
- Reuse existing frontend components.
- Avoid duplicate code.
- Add comments only where necessary.
- Follow industry best practices.

---

# Workflow

Before making any changes:

1. Analyze the entire project.
2. Understand the current frontend implementation.
3. Identify missing functionality.
4. Create an implementation plan.
5. Implement features incrementally.
6. Preserve the existing design throughout the project.

---

# Goal

The final outcome should be a complete, secure, scalable, production-ready ecommerce website suitable for real-world deployment while maintaining the existing MoonAura Crystals frontend design.