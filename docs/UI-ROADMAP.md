# Flavor UI/UX Roadmap — Design System & Component Specifications

## 1. Design System Overview ("Flavor Direct UI")

Flavor Direct UI is an **RTL-native, mobile-first design system** tailored specifically for the Iranian and MENA restaurant market. It bridges the aesthetic requirements of 8 distinct culinary atmospheres with high conversion rates, accessibility, and sub-second interaction feedback.

---

## 2. Design Tokens & Multi-Skin Matrix

The theme and mobile apps share a common CSS Variables / Flutter Theme token structure across all 8 pre-configured restaurant skins:

| Skin Slug | Concept | Primary Accent | Background | Surface Card | Secondary Olive | Text Ink |
|---|---|---|---|---|---|---|
| `modern-cafe` | کافه مدرن | `#8A5A2B` (Warm Brown) | `#F6F1EA` | `#FFFDF8` | `#5C6B3A` | `#2B2118` |
| `fast-food` | فست‌فود | `#D62828` (Vibrant Red) | `#FFF8E8` | `#FFFFFF` | `#FCBF49` | `#1A1A1A` |
| `traditional` | رستوران سنتی | `#881337` (Persian Crimson)| `#FAF5F0` | `#FFFDF9` | `#B45309` | `#28140E` |
| `fine-dining` | رستوران لوکس | `#C5A880` (Champagne Gold)| `#121212` | `#1E1E1E` | `#8C7355` | `#F5F5F5` |
| `pastry` | شیرینی‌فروشی | `#DB2777` (Pastel Rose) | `#FDF2F8` | `#FFFFFF` | `#F472B6` | `#371B2B` |
| `juice-bar` | آبمیوه و اسموتی | `#16A34A` (Fresh Green) | `#F0FDF4` | `#FFFFFF` | `#EA580C` | `#14281D` |
| `catering` | کترینگ سازمانی | `#1E3A8A` (Corporate Navy)| `#F8FAFC` | `#FFFFFF` | `#0284C7` | `#0F172A` |
| `cloud-kitchen` | آشپزخانه ابری | `#EA580C` (Direct Orange)| `#FAFAFA` | `#FFFFFF` | `#64748B` | `#18181B` |

---

## 3. Typography & RTL Persian Layout Rules

1. **Primary Persian Font**: **Vazirmatn** (Bundled WOFF2 with `font-display: swap` in Web; TTF/OTF in Flutter).
2. **Fallback Stack**: `Vazirmatn, Shabnam, Tahoma, system-ui, -apple-system, sans-serif`.
3. **Persian Number Formatting**:
   - Every price and numeric count must go through the centralized formatter `PersianText::format_digits()` / `toPersianDigits()`.
   - Thousands separators use standard Persian comma (٬) or localized space.
4. **BiDi & RTL Mirroring**:
   - Standard navigation chevrons, icons, drawer dismiss animations, and progress steppers are mirrored horizontally.
   - Touch targets are strictly **minimum 48×48 dp/px** for effortless single-thumb operation on mobile screens.

---

## 4. Key Component Specifications

### 4.1 Sticky Category Navigation (`CategoryNav`)
- Horizontal, scrollable pill buttons fixed beneath the sticky site header.
- Uses `IntersectionObserver` on Web and `ScrollController` in Flutter to highlight the active category in real time as the user scrolls.
- Active state: Filled accent background with white text and subtle scale bounce.

### 4.2 Interactive Food Item Card (`ItemCard`)
- **Visuals**: High-resolution 3:2 aspect ratio hero image with smooth rounded corners (16px radius) and subtle elevation shadow.
- **Badges**:
  - Out of stock / 86-ed: Semi-transparent overlay with `ناموجود (Unavailable)`.
  - Meal Schedule: `سرو فقط در صبحانه (Breakfast only)`.
  - Dietary Flags: Spicy (🌶️), Vegetarian (🌱), Gluten-Free (🌾).
- **Price Display**: Formatted in Toman with secondary original price if discounted.
- **Quick Action**: Single-tap `+` button that opens the Modifier Customizer bottom sheet.

### 4.3 Modifier Customizer (Bottom Sheet / Modal)
- **Header**: Item photo thumbnail, title, and close button (`✕`).
- **Option Groups**:
  - **Single-select (Size / Cooking Style)**: Radio list with extra price tag (`+ ۲۵٬۰۰۰ تومان`).
  - **Multi-select (Extra Toppings / Sides)**: Checkbox items with instant visual checkmark.
  - **Removals (e.g. بدون پیاز)**: Strikethrough style indicating excluded ingredients.
- **Special Instructions**: Expanding textarea for custom kitchen notes.
- **Bottom Floating Action**: Quantity stepper (`- [1] +`) and full-width CTA button displaying real-time calculated total: `افزودن به سبد خرید • ۱۸۵٬۰۰۰ تومان`.

### 4.4 Unified Cart Drawer (`CartDrawer`)
- **Order Mode Selector**: Segmented control with 3 tabs:
  - 🍽️ **سالن (Dine-in)**: Auto-locks table number if scanned via QR or allows manual table selection.
  - 🛍️ **بیرون‌بر (Takeaway)**: Pick-up preparation time estimate.
  - 🛵 **ارسال (Delivery)**: Real-time zone address check & minimum order validation.
- **Line Items**: Modifiers and custom notes shown in subtle muted tags under each food title.
- **Coupon Code Box**: In-drawer promo code verification with instant discount calculation.
- **Sticky Checkout CTA**: Full-width button leading to checkout.

### 4.5 Jalali Reservation Calendar (`ReservationPicker`)
- **Month Header**: Shows current Jalali month and year (e.g., `شهریور ۱۴۰۵`) with previous/next month navigation arrows.
- **Week Header**: Iranian week order starting Saturday (`ش`, `ی`, `د`, `س`, `چ`, `پ`, `ج`).
- **Day Cells**: Past days disabled and muted; available days interactive; today highlighted with border.
- **Time Slots Grid**: Grouped into Lunch and Dinner time chips. Full slots marked disabled with `تکمیل`.

### 4.6 Live Order Tracking Stepper (`OrderTracker`)
- Animated vertical stepper showing 5 operational states:
  1. `سفارش ثبت شد` (Order Placed)
  2. `تایید آشپزخانه و شروع پخت` (Cooking)
  3. `سفارش آماده شد` (Ready)
  4. `تحویل به پیک / آماده تحویل` (Out for Delivery)
  5. `تحویل داده شد` (Completed)
- Pulsing glow animation on the current active milestone.

---

## 5. Accessibility (WCAG 2.1 AA) & Performance Benchmarks

1. **Color Contrast**: All text-to-background contrast ratios strictly maintain >= 4.5:1.
2. **Keyboard & Screen Reader Support**:
   - Modals and drawers lock focus and dismiss on `Escape`.
   - Interactive components include ARIA attributes (`aria-expanded`, `aria-haspopup`, `aria-live="polite"`).
3. **Core Web Vitals & Rendering Performance**:
   - **LCP (Largest Contentful Paint)**: < 1.2s on standard 4G connections.
   - **CLS (Cumulative Layout Shift)**: 0.00 (aspect-ratio preserved image containers and skeleton placeholders).
   - **FID / INP**: < 50ms instant touch feedback.
