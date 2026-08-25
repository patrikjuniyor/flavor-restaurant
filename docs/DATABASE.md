# Database schema — flavor-core

WordPress prefix is shown as `wp_`. Engine: InnoDB. Charset: `utf8mb4` / `utf8mb4_unicode_ci`.

Money in custom tables is stored as **integer Rials** (`BIGINT UNSIGNED`) unless the admin flips the storage unit. Application code always goes through `FlavorCore\WooCommerce\Currency`.

Dates are stored **Gregorian** (`DATE` / `DATETIME`, site timezone or GMT as noted). Jalali is a display concern.

Foreign keys are not declared at the MySQL level (WordPress + shared hosting + HPOS make FK migrations fragile). Integrity is enforced in PHP. Logical relations are listed below.

Schema version option: `flavor_core_db_version` (currently `1.0.0`).

---

## 1. `wp_flavor_kitchen_tickets`

Operational source of truth for a placed order.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | Ticket id (not the WC order id) |
| `order_id` | BIGINT UNSIGNED NOT NULL | WC order id (HPOS or posts) |
| `order_number` | VARCHAR(32) NOT NULL | Snapshot of WC order number |
| `branch_id` | BIGINT UNSIGNED NOT NULL | `flavor_branch` post ID |
| `table_id` | BIGINT UNSIGNED NULL | `wp_flavor_tables.id` |
| `table_number` | VARCHAR(20) NULL | Snapshot for receipts if table row is later deleted |
| `order_mode` | VARCHAR(20) NOT NULL | `dine_in` \| `takeaway` \| `delivery` |
| `kitchen_status` | VARCHAR(20) NOT NULL | `new` \| `preparing` \| `ready` \| `completed` \| `cancelled` |
| `payment_status` | VARCHAR(32) NOT NULL | Mirrors WC (`pending`, `on-hold`, `processing`, `completed`, …) |
| `payment_method` | VARCHAR(64) NULL | WC method id or `flavor_pay_at_counter` / `flavor_cod` / `flavor_card_on_delivery` |
| `customer_id` | BIGINT UNSIGNED NULL | WP user id |
| `customer_name` | VARCHAR(190) NULL | Snapshot |
| `customer_mobile` | VARCHAR(20) NULL | Normalized `09xxxxxxxxx` |
| `delivery_address` | TEXT NULL | Snapshot JSON or plain text |
| `delivery_zone_id` | BIGINT UNSIGNED NULL | |
| `delivery_fee` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | Rials |
| `subtotal` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | Rials |
| `discount_total` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | Rials |
| `total` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | Rials |
| `special_notes` | TEXT NULL | |
| `source` | VARCHAR(20) NOT NULL DEFAULT `online` | `online` \| `phone` \| `walk_in` |
| `placed_at` | DATETIME NOT NULL | Local site time |
| `accepted_at` | DATETIME NULL | |
| `ready_at` | DATETIME NULL | |
| `completed_at` | DATETIME NULL | |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

Indexes:

- `UNIQUE uk_order (order_id)` — idempotent insert
- `idx_branch_status_placed (branch_id, kitchen_status, placed_at)`
- `idx_table_status (table_id, kitchen_status)`
- `idx_mobile (customer_mobile)`
- `idx_placed (placed_at)`

Relations: `order_id` → WC order; `branch_id` → `flavor_branch`; `table_id` → `wp_flavor_tables.id`.

---

## 2. `wp_flavor_kitchen_ticket_items`

Per-line kitchen progress (large orders can mark items ready individually).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `ticket_id` | BIGINT UNSIGNED NOT NULL | |
| `order_item_id` | BIGINT UNSIGNED NOT NULL | WC order item id |
| `product_id` | BIGINT UNSIGNED NOT NULL | |
| `item_name` | VARCHAR(190) NOT NULL | Snapshot |
| `quantity` | SMALLINT UNSIGNED NOT NULL DEFAULT 1 | |
| `modifiers_json` | LONGTEXT NULL | JSON array of selected modifiers |
| `special_instructions` | VARCHAR(200) NULL | |
| `item_status` | VARCHAR(20) NOT NULL DEFAULT `pending` | `pending` \| `ready` |
| `prep_time_minutes` | SMALLINT UNSIGNED NULL | |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

Indexes: `idx_ticket (ticket_id)`, `idx_order_item (order_item_id)`.

---

## 3. `wp_flavor_tables`

Physical tables of a branch. Not a CPT — queried by QR token and capacity.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `branch_id` | BIGINT UNSIGNED NOT NULL | |
| `table_number` | VARCHAR(20) NOT NULL | Unique per branch |
| `label` | VARCHAR(190) NULL | Optional display name |
| `capacity` | TINYINT UNSIGNED NOT NULL DEFAULT 4 | |
| `section` | VARCHAR(20) NOT NULL DEFAULT `indoor` | `indoor` \| `outdoor` \| `bar` \| `window` |
| `qr_token` | CHAR(32) NOT NULL | Unpredictable public token |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | |
| `sort_order` | INT NOT NULL DEFAULT 0 | |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

Indexes:

- `UNIQUE uk_branch_number (branch_id, table_number)`
- `UNIQUE uk_qr (qr_token)`
- `idx_branch_active (branch_id, is_active)`

---

## 4. `wp_flavor_reservations`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `branch_id` | BIGINT UNSIGNED NOT NULL | |
| `table_id` | BIGINT UNSIGNED NULL | V1 usually NULL — capacity is pooled by section |
| `section` | VARCHAR(20) NULL | Preference, not a hard lock |
| `reservation_date` | DATE NOT NULL | Gregorian |
| `reservation_time` | TIME NOT NULL | |
| `duration_minutes` | SMALLINT UNSIGNED NOT NULL DEFAULT 90 | |
| `party_size` | TINYINT UNSIGNED NOT NULL | |
| `customer_id` | BIGINT UNSIGNED NULL | |
| `customer_name` | VARCHAR(190) NOT NULL | |
| `customer_mobile` | VARCHAR(20) NOT NULL | |
| `status` | VARCHAR(20) NOT NULL DEFAULT `pending` | `pending` \| `confirmed` \| `seated` \| `completed` \| `cancelled` \| `no_show` |
| `special_requests` | TEXT NULL | |
| `source` | VARCHAR(20) NOT NULL DEFAULT `online` | `online` \| `walk_in` \| `phone` |
| `reminder_sent_at` | DATETIME NULL | |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

Indexes:

- `idx_branch_date_status (branch_id, reservation_date, status)`
- `idx_mobile (customer_mobile)`
- `idx_date_time (reservation_date, reservation_time)`

---

## 5. `wp_flavor_delivery_zones`

V1 zone types: `radius` and `neighborhoods`. `polygon` column exists for Pro.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `branch_id` | BIGINT UNSIGNED NOT NULL | |
| `name` | VARCHAR(190) NOT NULL | |
| `zone_type` | VARCHAR(20) NOT NULL DEFAULT `neighborhoods` | `radius` \| `neighborhoods` \| `polygon` |
| `center_lat` | DECIMAL(10,7) NULL | |
| `center_lng` | DECIMAL(10,7) NULL | |
| `radius_km` | DECIMAL(6,2) NULL | |
| `neighborhoods_json` | LONGTEXT NULL | JSON string array |
| `polygon_json` | LONGTEXT NULL | Reserved |
| `delivery_fee` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | Rials |
| `min_order` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | Rials |
| `estimated_minutes` | SMALLINT UNSIGNED NOT NULL DEFAULT 45 | |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | |
| `sort_order` | INT NOT NULL DEFAULT 0 | |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

Indexes: `idx_branch_active (branch_id, is_active)`.

---

## 6. `wp_flavor_availability`

Current per-branch availability. One row per pair.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `branch_id` | BIGINT UNSIGNED NOT NULL | |
| `product_id` | BIGINT UNSIGNED NOT NULL | |
| `is_available` | TINYINT(1) NOT NULL DEFAULT 1 | |
| `unavailable_until` | DATETIME NULL | NULL = until restocked manually |
| `reason` | VARCHAR(190) NULL | |
| `updated_by` | BIGINT UNSIGNED NULL | User id |
| `updated_at` | DATETIME NOT NULL | |

Indexes: `UNIQUE uk_branch_product (branch_id, product_id)`.

---

## 7. `wp_flavor_availability_log`

| Column | Type |
|---|---|
| `id` | BIGINT UNSIGNED AI PK |
| `branch_id` | BIGINT UNSIGNED NOT NULL |
| `product_id` | BIGINT UNSIGNED NOT NULL |
| `old_available` | TINYINT(1) NULL |
| `new_available` | TINYINT(1) NOT NULL |
| `unavailable_until` | DATETIME NULL |
| `changed_by` | BIGINT UNSIGNED NULL |
| `changed_at` | DATETIME NOT NULL |

Indexes: `idx_branch_product_time (branch_id, product_id, changed_at)`.

---

## 8. `wp_flavor_loyalty_ledger`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `customer_id` | BIGINT UNSIGNED NOT NULL | |
| `order_id` | BIGINT UNSIGNED NULL | |
| `points_delta` | INT NOT NULL | Negative = redeem |
| `balance_after` | INT NOT NULL | |
| `reason` | VARCHAR(40) NOT NULL | `earn` \| `redeem` \| `adjust` \| `expire` \| `stamp` |
| `note` | VARCHAR(190) NULL | |
| `created_at` | DATETIME NOT NULL | |

Indexes: `idx_customer_time (customer_id, created_at)`, `idx_order (order_id)`.

---

## 9. `wp_flavor_sms_log`

| Column | Type |
|---|---|
| `id` | BIGINT UNSIGNED AI PK |
| `provider` | VARCHAR(40) NOT NULL |
| `event` | VARCHAR(40) NOT NULL |
| `recipient` | VARCHAR(20) NOT NULL |
| `template` | VARCHAR(64) NULL |
| `body` | TEXT NULL |
| `status` | VARCHAR(20) NOT NULL |
| `provider_message_id` | VARCHAR(64) NULL |
| `related_type` | VARCHAR(40) NULL |
| `related_id` | BIGINT UNSIGNED NULL |
| `created_at` | DATETIME NOT NULL |

`status`: `queued` \| `sent` \| `failed` \| `dev`.

Indexes: `idx_recipient_time (recipient, created_at)`, `idx_related (related_type, related_id)`.

---

## 10. `wp_flavor_otp_codes`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `mobile` | VARCHAR(20) NOT NULL | |
| `code_hash` | CHAR(64) NOT NULL | HMAC-SHA256, never store raw OTP |
| `attempts` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | |
| `expires_at` | DATETIME NOT NULL | |
| `consumed_at` | DATETIME NULL | |
| `ip` | VARCHAR(45) NULL | |
| `created_at` | DATETIME NOT NULL | |

Indexes: `idx_mobile_exp (mobile, expires_at)`.

Rate limit: max 3 active/recent rows per mobile per 10 minutes (enforced in PHP).

---

## 11. `wp_flavor_customer_addresses`

| Column | Type |
|---|---|
| `id` | BIGINT UNSIGNED AI PK |
| `customer_id` | BIGINT UNSIGNED NOT NULL |
| `label` | VARCHAR(80) NULL |
| `province` | VARCHAR(80) NOT NULL |
| `city` | VARCHAR(80) NOT NULL |
| `neighborhood` | VARCHAR(80) NULL |
| `address_line` | TEXT NOT NULL |
| `postal_code` | VARCHAR(10) NULL |
| `lat` | DECIMAL(10,7) NULL |
| `lng` | DECIMAL(10,7) NULL |
| `is_default` | TINYINT(1) NOT NULL DEFAULT 0 |
| `created_at` | DATETIME NOT NULL |
| `updated_at` | DATETIME NOT NULL |

Indexes: `idx_customer_default (customer_id, is_default)`.

---

## 12. `wp_flavor_menu_schedules`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `branch_id` | BIGINT UNSIGNED NOT NULL | `0` = global default |
| `name` | VARCHAR(80) NOT NULL | Breakfast, … |
| `slug` | VARCHAR(40) NOT NULL | `breakfast` \| `lunch` \| `dinner` \| `late_night` |
| `start_time` | TIME NOT NULL | |
| `end_time` | TIME NOT NULL | May be smaller than start (overnight) |
| `days_json` | VARCHAR(60) NOT NULL | JSON `[0,1,2,3,4,5,6]` |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

Indexes: `UNIQUE uk_branch_slug (branch_id, slug)`.

---

## 13. `wp_flavor_branch_hours`

| Column | Type |
|---|---|
| `id` | BIGINT UNSIGNED AI PK |
| `branch_id` | BIGINT UNSIGNED NOT NULL |
| `day_of_week` | TINYINT UNSIGNED NOT NULL |
| `mode` | VARCHAR(20) NOT NULL DEFAULT `all` |
| `open_time` | TIME NULL |
| `close_time` | TIME NULL |
| `is_closed` | TINYINT(1) NOT NULL DEFAULT 0 |

Indexes: `UNIQUE uk_branch_day_mode (branch_id, day_of_week, mode)`.

`day_of_week`: 0 = Saturday (Iranian week start) through 6 = Friday.

---

## 14. `wp_flavor_branch_closures`

| Column | Type |
|---|---|
| `id` | BIGINT UNSIGNED AI PK |
| `branch_id` | BIGINT UNSIGNED NOT NULL |
| `closure_date` | DATE NOT NULL |
| `reason` | VARCHAR(190) NULL |

Indexes: `UNIQUE uk_branch_date (branch_id, closure_date)`.

---

## WordPress objects (not custom tables)

| Object | Why |
|---|---|
| CPT `flavor_branch` | Admin UI, REST, revisions, featured image (cover photo) |
| Product (WC `simple`) | Catalog, price, image, categories |
| WC Order + HPOS | Money, line items, payment, refunds |
| WC Coupon + meta | Promo codes (Jalali expiry and branch restriction as coupon meta) |
| Users + usermeta | Customers (mobile login), staff assignments |
| Options `flavor_core_*` | Settings, db version, menu versions |

Branch structured fields that are queried often (coords, default flag) are **both** post meta (editor UX) and, when needed, denormalized onto tickets/zones.

---

## Sample rows (testing)

```sql
-- After a branch CPT with ID 12 exists and a WC order 1045 is paid COD:
INSERT INTO wp_flavor_kitchen_tickets
  (order_id, order_number, branch_id, table_id, table_number, order_mode,
   kitchen_status, payment_status, payment_method, customer_name, customer_mobile,
   delivery_fee, subtotal, discount_total, total, source, placed_at, created_at, updated_at)
VALUES
  (1045, '1045', 12, 3, '5', 'dine_in',
   'new', 'on-hold', 'flavor_pay_at_counter', 'علی رضایی', '09121234567',
   0, 2500000, 0, 2500000, 'online', NOW(), NOW(), NOW());

INSERT INTO wp_flavor_tables
  (branch_id, table_number, label, capacity, section, qr_token, is_active, sort_order, created_at, updated_at)
VALUES
  (12, '5', 'پنج پنجره', 4, 'window', 'a1b2c3d4e5f60718293a4b5c6d7e8f90', 1, 5, NOW(), NOW());
```

Prices above are **Rials** (۲۵۰٬۰۰۰ تومان).

---

## Uninstall

`uninstall.php` drops every `wp_flavor_*` table and deletes CPT posts, product meta keys `_flavor_*`, user meta `_flavor_*`, and options `flavor_core_*` **only** if the shop manager confirmed `flavor_core_remove_data` = yes in settings. Default is to leave data (so a brief deactivation is safe).
