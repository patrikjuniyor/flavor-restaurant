# Flavor Direct UI — Design System & Customization Architecture (Phase 1)

## 1. Overview & Core Philosophy

**Flavor Direct UI** is a complete, enterprise-grade, mobile-first and RTL-native UI/UX system engineered exclusively for high-converting commercial restaurant websites.

### Core UX Principle
> **"Install → Choose a design preset → Enter restaurant info → Add menu items → Publish"**

Restaurant owners can launch a modern, responsive, accessible website without writing a single line of code.

---

## 2. Design Tokens System (CSS Custom Properties)

All visual elements in Flavor derive from centralized CSS custom properties declared in `:root`:

### 2.1 Colors & Brand Palette
| Token | Description | Fallback Example |
|---|---|---|
| `--flavor-primary` | Primary brand accent color | `#8a5a2b` |
| `--flavor-secondary` | Secondary brand tone | `#5c6b3a` |
| `--flavor-accent` | Highlights, badges & promos | `#c47d39` |
| `--flavor-bg` | Site canvas background | `#f8f4ee` |
| `--flavor-surface` | Primary card & panel surface | `#ffffff` |
| `--flavor-surface-alt`| Alternate section background | `#f1ebe1` |
| `--flavor-ink` | Main body typography color | `#241b14` |
| `--flavor-muted` | Secondary description & metadata | `#6e5f53` |
| `--flavor-line` | Card borders & subtle dividers | `#e5dcce` |

### 2.2 System Feedback Tokens
| Token | Purpose | Value |
|---|---|---|
| `--flavor-success` / `--flavor-success-bg` | Order ready / success toast | `#16a34a` / `#dcfce7` |
| `--flavor-warning` / `--flavor-warning-bg` | Low capacity / pending alert | `#d97706` / `#fef3c7` |
| `--flavor-danger` / `--flavor-danger-bg` | Unavailable (86) / Error | `#dc2626` / `#fee2e2` |
| `--flavor-info` / `--flavor-info-bg` | OTP Sent / notifications | `#0284c7` / `#e0f2fe` |

### 2.3 4px Spacing Grid
`--flavor-space-1` (4px), `--flavor-space-2` (8px), `--flavor-space-3` (12px), `--flavor-space-4` (16px), `--flavor-space-5` (20px), `--flavor-space-6` (24px), `--flavor-space-8` (32px), `--flavor-space-12` (48px), `--flavor-space-16` (64px), `--flavor-space-24` (96px).

### 2.4 Border Radius & Shadows
- **Card Radius**: `--flavor-radius` (Customizer selectable: `0px`, `8px`, `16px`, `24px`).
- **Button Radius**: `--flavor-btn-radius` (Customizer selectable: `6px`, `12px`, `9999px`).
- **Elevation Shadows**: `--flavor-shadow-sm`, `--flavor-shadow-md`, `--flavor-shadow-lg`, `--flavor-shadow-xl`.

---

## 3. The 11 Ready-Made Restaurant Presets

Flavor ships with 11 distinct aesthetic presets configured in `Design::skins()`:

1. **Modern Restaurant (`modern-restaurant`)**: Warm brown and beige with organic green highlights for contemporary bistros.
2. **Luxury Dining (`luxury-dining`)**: Ultra-dark royal aesthetic with champagne gold and high contrast typography.
3. **Persian Traditional (`persian-traditional`)**: Crimson, amber gold, and turquoise blue reminiscent of authentic Persian heritage.
4. **Cafe & Bistro (`cafe-bistro`)**: Coffee brown, olive, and warm cream for specialty cafes and brunch spots.
5. **Fast Food & Burger Bar (`fast-food`)**: Vibrant energetic red and cheddar yellow for burger joints and pizzerias.
6. **Pizza & Italian (`pizza-italian`)**: Tomato red, basil green, and fresh mozzarella white.
7. **Bakery & French Pastry (`bakery-pastry`)**: Delicate pastel rose and mint tones for patisseries.
8. **Juice Bar & Healthy Living (`juice-bar`)**: Fresh organic green and citrus orange.
9. **Dark Luxe (`dark-luxe`)**: Minimalist deep charcoal with amber illumination.
10. **Minimal Clean (`minimal-clean`)**: Monochromatic pure white and crisp black typography.
11. **Cloud Kitchen (`cloud-kitchen`)**: Energetic delivery orange optimized for takeaway hubs.

---

## 4. The 15 Reusable Homepage Sections

Each section is modularly located under `flavor/template-parts/marketing/`:

| # | Section File | Component Name | Description |
|---|---|---|---|
| 1 | `hero.php` | Hero Banner | Fullscreen, Split 2-Column, or Minimal with dynamic CTA buttons and SLA badges. |
| 2 | `intro.php` | Highlights | 4-card feature showcase (Organic ingredients, live cooking, insulated delivery, quiet ambiance). |
| 3 | `featured.php` | Chef's Specials | Query-driven grid of top-rated signature dishes with instant ordering CTA. |
| 4 | `categories.php` | Category Showcase | Image cards with dish count and smooth category filter links. |
| 5 | `special-offers.php`| Promo Banner | High-converting coupon callout with single-tap copy code button. |
| 6 | `reservation-cta.php`| Table Booking CTA| Visual table reservation invite with key booking perks. |
| 7 | `about.php` | Story & Heritage | 2-column story layout with years-of-experience badge and trust statistics. |
| 8 | `gallery.php` | Atmosphere Gallery | Responsive photo grid showcasing interior, kitchen and food presentation. |
| 9 | `testimonials.php` | Guest Reviews | Customer feedback cards with 5 gold stars and verified guest tags. |
| 10 | `hours.php` | Working Hours & Map | Live "Open Now / Closed" real-time indicator and weekly schedule. |
| 11 | `location.php` | Directions & Branch | Branch details, address, and Google Maps directions link. |
| 12 | `contact.php` | Direct Hotline | Click-to-call, WhatsApp, Telegram, and Instagram links. |
| 13 | `social-links.php` | Social Links | Clean inline SVG icons. |
| 14 | `full-menu.php` | Embedded Menu | Interactive category tabs and live menu items directly on the front page. |
| 15 | `footer.php` | 4-Column Footer | Rich footer with bio, links, working hours, trust badges, and copyright bar. |

---

## 5. 10-Step Setup & Onboarding Wizard

Accessible in WordPress admin under **Appearance → 🚀 راه‌اندازی سریع Flavor** (`admin.php?page=flavor-setup`):

- **Step 1: Restaurant Name & Tagline**: Updates `blogname` and `blogdescription`.
- **Step 2: Logo Upload**: Interactive WordPress media uploader with live thumbnail preview.
- **Step 3: Cover Image**: Uploads hero background.
- **Step 4: Colors**: Primary & Accent color picker.
- **Step 5: Choose Preset**: Visual grid of all 11 restaurant presets.
- **Step 6: Opening Hours & Service Modes**: Sets working hours and toggles Dine-in / Takeaway / Delivery.
- **Step 7: Address & Delivery Area**: City and branch street address.
- **Step 8: Contact & Socials**: Phone hotline, Instagram, Telegram.
- **Step 9: First Menu Item**: Instantly creates the first WooCommerce simple food item with category and price.
- **Step 10: Publish & Launch**: Automatically generates core pages (`خانه`, `منو`, `رزرو`, `شعبه‌ها`), binds templates, and redirects to the live storefront!

---

## 6. Accessibility & Performance Benchmarks

1. **RTL & Typography**:
   - Bundled Vazirmatn font with `font-display: swap` and critical WOFF2 preloading in `<head>`.
   - Persian number conversion via `toPersianDigits()` and CSS `font-feature-settings: "ss01", "ss02"`.
2. **WCAG 2.1 AA Compliance**:
   - Strict contrast ratio >= 4.5:1.
   - Visible `:focus-visible` ring across all interactive elements.
   - Screen reader skip link (`.flavor-skip`) with focus trap on open modals.
3. **Core Web Vitals**:
   - Zero Cumulative Layout Shift (CLS = 0.00) with aspect-ratio preserved image containers.
   - Hero banner uses `fetchpriority="high"` and eager decoding for LCP < 1.2s.
