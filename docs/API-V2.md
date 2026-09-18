# Flavor Restaurant Platform — REST API V2 Specification

> **Namespace:** `/flavor/v2/`  
> **Base URL:** `https://restaurant.example.com/wp-json/flavor/v2/`  
> **Format:** JSON (UTF-8, RTL Persian text supported)  
> **Status:** Production-Ready & Fully Backwards-Compatible with `/flavor/v1/`

---

## ۱. نمای کلی و معماری V2 (Overview & Architecture)

نگارش دوم API پلتفرم Flavor به عنوان یک لایه مدرن، امن و ماژولار بر روی سرویس‌های هسته سیستم پیاده‌سازی شده است. این نسخه بدون تکرار منطق کسب‌وکار (DRY) و همگام با نسخه V1 به مشتریان موبایل (Flutter iOS/Android)، وب (React/Next.js/PWA) و پنل‌های شعب و آشپزخانه (KDS/POS) سرویس‌دهی می‌کند.

### اصول کلیدی V2:
1. **امنیت مالکیت بدون Session (Zero-Trust Guest Security):** ایمن‌سازی کامل سفارشات و رزروهای مهمان در برابر حملات جعل شناسه (IDOR) با استفاده از توکن‌های رمزنگاری‌شده ۶۴ کاراکتری (`guest_token` / `X-Guest-Token`).
2. **پشتیبانی مستقل از کوکی برای سبد خرید مهمان (`X-Cart-Token`):** حذف وابستگی اپلیکیشن‌های موبایل به کوکی‌های PHP/WooCommerce از طریق جدول پایگاه داده `flavor_guest_carts` با هش SHA-256 و مهاجرت خودکار سبد هنگام ورود کاربر.
3. **تفکیک کامل دسترسی شعب (Multi-Branch Isolation):** اعمال ماتریس دسترسی دقیق برای پرسنل (مدیر شعبه، صندوق‌دار، سرآشپز، گارسون).
4. **استانداردسازی پاسخ‌ها و پاکت واحد (Standard JSON Envelope):** ساختار یکدست داده‌ها همراه با فراداده‌های صفحه‌بندی، واحدهای پولی (تومان/ریال) و تاریخ‌های شمسی/میلادی.

---

## ۲. هدرهای احراز هویت و نشست (Headers & Authentication)

| هدر (Header) | مقدار و توضیح | الزامی در مسیرها |
| :--- | :--- | :--- |
| `Authorization` | `Bearer <access_token>` — توکن احراز هویت JWT کاربر لاگین‌شده | مسیرهای نیازمند لاگین (`/auth/me`, `/orders`, `/reservations/my`) |
| `X-Cart-Token` | `fcart_<hex_token>` — شناسه سبد خرید مهمان بدون نیاز به کوکی | تمامی مسیرهای سبد خرید (`/cart/*`) و ثبت سفارش مهمان |
| `X-Guest-Token` | `<64_char_hex_token>` — توکن مالکیت سفارش یا رزرو مهمان | پیگیری و لغو سفارشات و رزروهای مهمان (`/orders/{id}/track`, `/reservations/{id}`) |
| `Content-Type` | `application/json; charset=utf-8` | تمامی درخواست‌های POST, PUT, DELETE |
| `Accept` | `application/json` | تمامی درخواست‌ها |

---

## ۳. ساختار پاکت پاسخ و خطا (Response Envelope Schema)

### پاکت موفقیت (Success Envelope):
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "timestamp": 1726646400,
    "server_time": "2026-09-18 10:30:00"
  },
  "errors": []
}
```

### پاکت خطای استاندارد (Error Envelope):
```json
{
  "success": false,
  "data": null,
  "meta": {
    "timestamp": 1726646400
  },
  "errors": [
    {
      "code": "forbidden",
      "message": "دسترسی به این سفارش برای شما مجاز نیست.",
      "details": null
    }
  ]
}
```

---

## ۴. مرجع مسیرهای API V2 (Endpoints Reference)

### ۴.۱. تنظیمات و راه‌اندازی اولیه (Settings & App Bootstrap)
- **`GET /flavor/v2/settings/app-bootstrap`**
  - **توضیح:** بارگذاری یکباره کل کانفیگ رستوران شامل برندینگ، تم (رنگ‌ها، فونت، گردی حاشیه‌ها)، واحدهای ارزی تومان/ریال، حالت‌های مجاز سفارش (سالن، بیرون‌بر، پیک)، لیست شعب فعال و تاریخ امروز شمسی.
  - **دسترسی:** عمومی (`public, max-age=300`)

### ۴.۲. منو، دسته‌ها و غذاها (Menu & Dishes)
- **`GET /flavor/v2/categories`**
  - **توضیح:** دریافت لیست دسته‌بندی‌های غذایی همراه با آیکون و تصویر.
- **`GET /flavor/v2/menu?branch_id=1&category=5&shift=dinner&page=1&per_page=30`**
  - **توضیح:** دریافت اقلام منو فیلترشده بر اساس شعبه، شیفت کاری، دسته‌بندی و وضعیت موجودی لحظه‌ای شعبه.
- **`GET /flavor/v2/dishes/{id}?branch_id=1`**
  - **توضیح:** جزئیات کامل غذا، گالری تصاویر، گزینه‌های سفارشی‌سازی (اندازه، افزودنی‌ها، سس‌ها)، ارزش غذایی و کالری.
- **`GET /flavor/v2/dishes/featured?branch_id=1`**
  - **توضیح:** غذاهای ویژه و پیشنهادی سرآشپز.
- **`GET /flavor/v2/dishes/special-offers?branch_id=1`**
  - **توضیح:** غذاهای دارای تخفیف و پیشنهاد شگفت‌انگیز.

### ۴.۳. سبد خرید و توکن مهمان (Cart & Guest Session Bridge)
- **`GET /flavor/v2/cart`**
  - **هدر:** `X-Cart-Token: fcart_...` (اختیاری؛ در صورت ارسال نشدن خودکار تولید می‌شود).
  - **پاسخ:** اقلام سبد، محاسبات مبالغ، تخفیف‌ها و کوپن‌های اعمال‌شده.
- **`POST /flavor/v2/cart/items`**
  - **بدنه:** `{"product_id": 105, "quantity": 2, "modifier_ids": ["extra_cheese"], "instructions": "کم‌نمک"}`
- **`PUT /flavor/v2/cart/items/{key}`**
  - **بدنه:** `{"quantity": 3}`
- **`DELETE /flavor/v2/cart/items/{key}`**
  - **توضیح:** حذف یک قلم از سبد.
- **`DELETE /flavor/v2/cart`**
  - **توضیح:** خالی کردن کامل سبد خرید و ابطال کش در جدول `flavor_guest_carts`.
- **`POST /flavor/v2/cart/coupon`**
  - **بدنه:** `{"code": "TASTE1403"}`
- **`DELETE /flavor/v2/cart/coupon`**
  - **توضیح:** حذف کوپن تخفیف.
- **`POST /flavor/v2/cart/loyalty`**
  - **بدنه:** `{"points": 50}` (نیازمند لاگین).

### ۴.۴. سفارشات و پیگیری زنده (Orders & Live Tracking)
- **`POST /flavor/v2/orders`**
  - **توضیح:** ثبت نهایی سفارش (Dine-in, Takeaway, Delivery). در صورت ثبت توسط کاربر مهمان، فیلد `guest_token` در پاسخ و هدر `X-Guest-Token` بازگردانده می‌شود.
  - **بدنه نمونه:**
    ```json
    {
      "order_mode": "delivery",
      "branch_id": 1,
      "mobile": "09121234567",
      "name": "محمدرضا علوی",
      "payment_method": "flavor_pay_at_counter",
      "address": {
        "province": "تهران",
        "city": "تهران",
        "line": "خیابان ولیعصر، نرسیده به میدان ونک، پلاک ۱۲",
        "postal_code": "1994612345"
      },
      "notes": "لطفاً با قاشق و چنگال یکبارمصرف ارسال شود."
    }
    ```
- **`GET /flavor/v2/orders`**
  - **توضیح:** لیست تاریخچه سفارشات کاربر لاگین‌شده (نیازمند `Bearer`).
- **`GET /flavor/v2/orders/{id}`**
  - **امنیت IDOR:** نیازمند لاگین مالک سفارش، شماره موبایل احرازشده، یا هدر `X-Guest-Token`.
- **`GET /flavor/v2/orders/{id}/track`**
  - **توضیح:** وضعیت زنده ۴ مرحله‌ای پردازش سفارش در آشپزخانه و پیک (`received` -> `preparing` -> `ready` -> `completed`).
- **`POST /flavor/v2/orders/{id}/cancel`**
  - **توضیح:** لغو سفارش در صورتی که وارد مرحله آماده‌سازی آشپزخانه نشده باشد.
- **`POST /flavor/v2/orders/{id}/reorder`**
  - **توضیح:** افزودن مجدد تمام اقلام سفارش قبلی به سبد خرید جاری.

### ۴.۵. رزرو میز و تقویم شمسی (Reservations & Jalali Calendar)
- **`GET /flavor/v2/reservations/calendar?jy=1403&jm=7`**
  - **توضیح:** ساختار ماتریس ماهانه تقویم جلالی با مشخصات روزهای باز/بسته.
- **`GET /flavor/v2/reservations/slots?branch_id=1&date=2026-09-20&party=4&section=indoor`**
  - **توضیح:** محاسبه ظرفیت و سانس‌های زمانی در دسترس شعبه.
- **`POST /flavor/v2/reservations`**
  - **توضیح:** ثبت رزرو جدید میز. توکن `guest_token` برای پیگیری‌های بعدی صادر و بازگردانده می‌شود.
- **`GET /flavor/v2/reservations/{id}`**
  - **توضیح:** مشاهده کارت جزئیات رزرو با حفاظت IDOR و توکن مهمان.
- **`GET /flavor/v2/reservations/my`**
  - **توضیح:** رزروهای گذشته و آینده کاربر لاگین‌شده.
- **`POST /flavor/v2/reservations/{id}/cancel`**
  - **توضیح:** لغو رزرو میز توسط مشتری یا پرسنل.

### ۴.۶. احراز هویت پیامکی و پروفایل (Authentication & OTP)
- **`POST /flavor/v2/auth/otp/request`**
  - **بدنه:** `{"mobile": "09121234567"}`
- **`POST /flavor/v2/auth/otp/verify`**
  - **بدنه:** `{"mobile": "09121234567", "code": "12345", "name": "نام کاربر", "device_name": "iPhone 15"}`
  - **مهاجرت خودکار:** در صورت ارسال `X-Cart-Token`، اقلام سبد خرید مهمان بلافاصله به حساب کاربر منتقل می‌شوند.
- **`POST /flavor/v2/auth/token/refresh`**
  - **بدنه:** `{"refresh_token": "..."}`
- **`POST /flavor/v2/auth/token/revoke`**
  - **بدنه:** `{"all_devices": false}`
- **`GET /flavor/v2/auth/me`**
  - **توضیح:** اطلاعات پروفایل، امتیازات باشگاه مشتریان، آدرس‌های ذخیره‌شده و دسترسی‌های پرسنلی.
- **`PUT /flavor/v2/auth/me`**
  - **توضیح:** بروزرسانی نام، ایمیل، آدرس‌ها و ترجیحات غذایی.

### ۴.۷. شعب، مناطق ارسال و میزها (Branches & Tables)
- **`GET /flavor/v2/branches`**
- **`GET /flavor/v2/branches/{id}`**
- **`GET /flavor/v2/branches/{id}/tables`**
- **`GET /flavor/v2/branches/{id}/zones`**
- **`GET /flavor/v2/branches/{id}/schedule`**

### ۴.۸. مانیتورینگ آشپزخانه (Kitchen Display System - KDS)
- **`GET /flavor/v2/kitchen/tickets?branch_id=1&status=new`**
  - **مجوز:** `flavor_manage_kitchen` یا مدیر کل شعبه.
- **`POST /flavor/v2/kitchen/tickets/{id}/status`**
  - **بدنه:** `{"status": "preparing"}` (`preparing` | `ready` | `completed` | `cancelled`).

---

## ۵. راهنمای مهاجرت از V1 به V2 (Migration Guide)

| قابلیت | نگارش V1 | نگارش V2 |
| :--- | :--- | :--- |
| **فضای نام (Namespace)** | `/wp-json/flavor/v1/` | `/wp-json/flavor/v2/` |
| **سبد خرید مهمان** | متکی بر کوکی‌های ووکامرس | مستقل از کوکی با هدر `X-Cart-Token` و جدول پایگاه داده |
| **دسترسی به سفارش مهمان** | دسترسی آزاد بر اساس شناسه (IDOR) | قفل‌شده با `X-Guest-Token` یا تطابق شماره موبایل احرازشده |
| **استعلام رزرو تکی** | وجود نداشت | افزوده شد (`GET /reservations/{id}`) با احراز توکن مهمان |
| **همگام‌سازی ورود کاربر** | نیاز به لاگین دستی در وب | انتقال خودکار سبد مهمان به حساب کاربر با ارسال `X-Cart-Token` |
