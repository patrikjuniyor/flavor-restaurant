# مستند جامع و مشخصات فنی REST API پلتفرم Flavor (نسخه v1)
# Flavor Restaurant Platform — Comprehensive REST API Specification

این مستند، مرجع کامل معماری و نقاط پایانی (Endpoints) سامانهٔ یکپارچه **Flavor REST API** است که به عنوان **Single Source of Truth**، کلیهٔ کلاینت‌های وب، اپلیکیشن‌های موبایل (Android / iOS / Flutter / React Native)، کیوسک‌های سفارش‌گیر و پنل‌های آشپزخانه (KDS) را تغذیه می‌کند.

---

## ۱. اصول معماری و استانداردهای ارتباطی

### ۱.۱. آدرس پایه (Base URL)
```http
https://your-restaurant-domain.com/wp-json/flavor/v1/
```

### ۱.۲. ساختار استاندارد پاسخ‌ها (Standard Envelope Format)
تمامی خروجی‌های این API از ساختار یکنواخت و قابل پیش‌بینی زیر پیروی می‌کنند:

#### الف) پاسخ موفق (Success Response - HTTP 200/201)
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "timestamp": 1726646400,
    "server_time": "2026-09-18 11:30:00",
    "pagination": {
      "total": 45,
      "count": 20,
      "per_page": 20,
      "current_page": 1,
      "total_pages": 3,
      "has_next": true,
      "has_prev": false
    }
  },
  "errors": []
}
```

#### ب) پاسخ خطا (Error Response - HTTP 400/401/403/404/429/500)
```json
{
  "success": false,
  "data": null,
  "meta": {
    "timestamp": 1726646400
  },
  "errors": [
    {
      "code": "flavor_invalid_otp",
      "message": "کد تأیید پیامک شده نادرست است یا منقضی شده است.",
      "details": {
        "field": "code",
        "attempts_remaining": 3
      }
    }
  ]
}
```

---

## ۲. احراز هویت دومنظوره (Dual-Mode Authentication)

سیستم به صورت خودکار هر دو مدل احراز هویت را شناسایی و پردازش می‌کند:

### ۲.۱. توکن Bearer (مخصوص اپلیکیشن‌های موبایل و کلاینت‌های مستقل)
در هدر هر درخواست ارسال می‌شود:
```http
Authorization: Bearer <access_token>
```
- **Access Token:** مدت اعتبار ۷ روز (قابل تمدید)، رمزنگاری‌شده بر پایه Secret Key امن وردپرس.
- **Refresh Token:** مدت اعتبار ۳۰ روز با قابلیت چرخش امن خودکار (Single-use Token Rotation).
- **جدول پایگاه‌داده:** `wp_flavor_auth_tokens`

### ۲.۲. نشست کوکی و نانس وردپرس (مخصوص مرورگر و وب‌سایت)
در درخواست‌های AJAX فرانت‌اند از طریق نانس استاندارد وردپرس:
```http
X-WP-Nonce: <wp_rest_nonce>
```

---

## ۳. مدیریت نشست سبد خرید مهمان (Guest Cart Session)
کلاینت‌هایی که کاربر هنوز وارد حساب نشده است، یک شناسهٔ یکتا در هدر ارسال می‌کنند:
```http
X-Cart-Token: cart_sec_9f83a21b40...
```
هنگام ورود کاربر، سبد خرید متناظر به حساب کاربری متصل و همگام‌سازی می‌شود.

---

## ۴. فهرست کامل نقاط پایانی (API Endpoints Matrix)

### ۴.۱. بوت‌استرپ و پیکربندی اولیه (Bootstrap & Settings)

| متد | مسیر | دسترسی | توضیحات |
|:---|:---|:---|:---|
| `GET` | `/settings/app-bootstrap` | عمومی | تمام تنظیمات اولیه اپلیکیشن، رنگ‌ها، فونت، واحد پول، شعبه‌ها و تاریخ خورشیدی در یک درخواست |

#### نمونه خروجی `/settings/app-bootstrap`:
```json
{
  "success": true,
  "data": {
    "app": {
      "name": "رستوران بین‌المللی طعم",
      "description": "تجربه لذت‌بخش غذای اصیل و باکیفیت",
      "logo": "https://domain.com/wp-content/uploads/logo.png",
      "phone": "021-88889999",
      "instagram": "flavor_restaurant",
      "maintenance_mode": false,
      "min_app_version": "1.0.0",
      "current_version": "1.1.0"
    },
    "design": {
      "preset": "luxury",
      "primary_color": "#d4af37",
      "accent_color": "#8b1e0f",
      "font_family": "vazirmatn",
      "border_radius": "0.75rem"
    },
    "currency": {
      "storage_unit": "IRR",
      "display_unit": "IRT",
      "label": "تومان",
      "symbol": "تومان",
      "decimals": 0
    },
    "ordering": {
      "modes": [
        { "id": "dine_in", "label": "سفارش سر میز (سالن)", "icon": "utensils" },
        { "id": "takeaway", "label": "بیرون‌بر / تحویل حضوری", "icon": "shopping-bag" },
        { "id": "delivery", "label": "ارسال با پیک اختصاصی", "icon": "motorcycle" }
      ],
      "guest_checkout": true
    },
    "auth": {
      "otp_length": 5,
      "otp_resend_sec": 120
    },
    "branches": [
      {
        "id": 101,
        "name": "شعبه مرکزی (زعفرانیه)",
        "phone": "021-22446688",
        "address": "تهران، زعفرانیه، خیابان مقدس اردبیلی",
        "lat": "35.8052",
        "lng": "51.4183",
        "order_modes": ["dine_in", "takeaway", "delivery"],
        "is_default": true
      }
    ],
    "calendar": {
      "gregorian_today": "2026-09-18",
      "jalali_today": { "y": 1405, "m": 6, "d": 27 },
      "jalali_label": "جمعه ۲۷ شهریور ۱۴۰۵"
    }
  }
}
```

---

### ۴.۲. احراز هویت و مدیریت حساب کاربری (Authentication & Users)

| متد | مسیر | دسترسی | توضیحات |
|:---|:---|:---|:---|
| `POST` | `/auth/otp/request` | عمومی (Rate-limit) | درخواست پیامک کد ورود / ثبت‌نام |
| `POST` | `/auth/otp/verify` | عمومی (Rate-limit) | اعتبارسنجی کد و صدور جفت توکن `access_token` و `refresh_token` |
| `POST` | `/auth/token/refresh` | عمومی | تمدید Access Token با استفاده از Refresh Token |
| `POST` | `/auth/token/revoke` | احرازشده | ابطال توکن فعلی یا خروج از همهٔ دستگاه‌ها |
| `GET` | `/auth/me` | عمومی/احرازشده | دریافت اطلاعات پروفایل کاربر، آدرس‌ها، سطح و امتیاز باشگاه |
| `PUT` | `/auth/me` | احرازشده | ویرایش نام، ایمیل، ترجیحات غذایی و آدرس‌های منتخب |
| `POST` | `/auth/device` | عمومی/احرازشده | ثبت توکن اعلان‌های لحظه‌ای (FCM / APNs) |
| `DELETE` | `/auth/device` | عمومی/احرازشده | حذف توکن پوش‌نوتیفیکیشن هنگام خروج |

#### نمونه بدنه درخواست `POST /auth/otp/verify`:
```json
{
  "mobile": "09123456789",
  "code": "78492",
  "name": "رضا صادقی",
  "device_name": "iPhone 15 Pro"
}
```

#### نمونه پاسخ `POST /auth/otp/verify`:
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 42,
      "display_name": "رضا صادقی",
      "mobile": "09123456789",
      "avatar_url": "https://secure.gravatar.com/avatar/...",
      "loyalty": {
        "balance": 350,
        "balance_formatted": "۳۵۰ امتیاز",
        "tier": "طلایی",
        "discount_equivalent": 350000
      },
      "dietary_preferences": ["spicy"],
      "saved_addresses": [
        {
          "id": "addr_1",
          "title": "منزل",
          "address": "خیابان شریعتی، بالاتر از پل رومی",
          "lat": 35.792,
          "lng": 51.432,
          "unit": "۴"
        }
      ]
    },
    "tokens": {
      "access_token": "flv_acc_78a192b8d0...",
      "refresh_token": "flv_ref_c491e0a293...",
      "token_type": "Bearer",
      "expires_in": 604800
    },
    "is_new_user": false
  }
}
```

---

### ۴.۳. شعب و میزها (Branches & Dining Tables)

| متد | مسیر | دسترسی | توضیحات |
|:---|:---|:---|:---|
| `GET` | `/branches` | عمومی | فهرست شعب فعال به همراه موقعیت جغرافیایی و حالت‌های سفارش |
| `GET` | `/branches/{id}` | عمومی | جزئیات کامل یک شعبه، ساعات کاری، وضعیت باز/بسته بودن |
| `GET` | `/branches/{id}/tables` | عمومی | فهرست میزهای فعال سالن به همراه QR Code و ظرفیت |
| `GET` | `/branches/{id}/zones` | عمومی | مناطق تحت پوشش ارسال پیک و کرایه ارسال |
| `GET` | `/branches/{id}/schedule` | عمومی | شیفت‌ها و ساعات سرو وعده‌ها (صبحانه، ناهار، شام) |

---

### ۴.۴. منو، دسته‌بندی‌ها و غذاها (Menu & Dishes)

| متد | مسیر | پارامترهای اختیاری | توضیحات |
|:---|:---|:---|:---|
| `GET` | `/categories` | — | فهرست دسته‌بندی‌های منو همراه با آیکون، تصویر و تعداد غذاها |
| `GET` | `/menu` | `branch_id`, `category`, `shift`, `search`, `page`, `per_page` | منوی کامل شعبه با کنترل زمان‌بندی شیفت و موجودی زنده |
| `GET` | `/dishes/{id}` | `branch_id` | جزئیات غذای مشخص، گروه مدیفایرها، کالری، زمان آماده‌سازی، آلرژن‌ها |
| `GET` | `/dishes/featured` | `branch_id`, `limit` | غذاهای برگزیده برای اسلایدرها و بنرهای اپلیکیشن |
| `GET` | `/dishes/special-offers` | `branch_id`, `limit` | غذاهای دارای تخفیف ویژه و پیشنهاد روز |

#### ساختار کامل مدیفایرهای غذا در `/dishes/{id}`:
```json
{
  "id": 204,
  "name": "چلوکباب شیشلیک مخصوص",
  "slug": "shishlik-kebab",
  "price": 6500000,
  "price_html": "۶۵۰,۰۰۰ تومان",
  "prep_time": 25,
  "calories": 780,
  "dietary": [],
  "available": true,
  "modifier_groups": [
    {
      "type": "size",
      "title": "اندازه پرس",
      "required": true,
      "multi": false,
      "options": [
        { "id": "size-normal", "name": "یک سیخ شیشلیک (معمولی)", "price": 0, "price_html": "رایگان", "is_default": true },
        { "id": "size-double", "name": "دو سیخ شیشلیک (دوبل)", "price": 4500000, "price_html": "+۴۵۰,۰۰۰ تومان", "is_default": false }
      ]
    },
    {
      "type": "topping",
      "title": "مخلفات اضافه",
      "required": false,
      "multi": true,
      "options": [
        { "id": "topping-zeytoon", "name": "زیتون پرورده اعلا", "price": 450000, "price_html": "+۴۵,۰۰۰ تومان" },
        { "id": "topping-mast", "name": "ماست چکیده موسیردار", "price": 350000, "price_html": "+۳۵,۰۰۰ تومان" }
      ]
    }
  ]
}
```

---

### ۴.۵. سبد خرید هوشمند (Smart Cart API)

| متد | مسیر | پارامترها / بدنه | توضیحات |
|:---|:---|:---|:---|
| `GET` | `/cart` | — | دریافت محتوای سبد خرید، تخفیف، هزینه ارسال، جمع کل و امتیاز دریافتی |
| `POST` | `/cart/items` | `product_id`, `quantity`, `modifier_ids`, `instructions` | افزودن غذا با مدیفایرها و توضیحات پخت به سبد |
| `PUT` | `/cart/items/{key}` | `quantity` | تغییر تعداد یا ویرایش یک ردیف سبد خرید |
| `DELETE` | `/cart/items/{key}` | — | حذف یک قلم از سبد خرید |
| `DELETE` | `/cart` | — | خالی کردن کامل سبد خرید |
| `POST` | `/cart/coupon` | `code` | اعتبارسنجی و اعمال کد تخفیف |
| `DELETE` | `/cart/coupon` | — | حذف کوپن تخفیف فعال |
| `POST` | `/cart/loyalty` | `points` | تبدیل امتیازات باشگاه مشتریان به تخفیف سفارش |

---

### ۴.۶. سفارش‌گیری و رهگیری زنده (Orders & Real-Time Tracking)

| متد | مسیر | دسترسی | توضیحات |
|:---|:---|:---|:---|
| `POST` | `/orders` | عمومی/احرازشده | ثبت نهایی سفارش (سالن / بیرون‌بر / ارسال با پیک) |
| `GET` | `/orders` | احرازشده | تاریخچه سفارش‌های کاربر به همراه جزئیات و صورتحساب |
| `GET` | `/orders/{id}` | مالک/مدیر | جزئیات یک سفارش، اقلام، نحوه پرداخت و آدرس |
| `GET` | `/orders/{id}/track` | عمومی/مالک | رهگیری وضعیت زنده در ۴ مرحله (ثبت شده -> در حال پخت -> آماده/ارسال -> تحویل) |
| `POST` | `/orders/{id}/cancel` | مالک/مدیر | لغو سفارش در صورتی که هنوز وارد مرحله پخت نشده باشد |
| `POST` | `/orders/{id}/reorder` | عمومی/احرازشده | افزودن سریع کلیه اقلام سفارش قبلی به سبد خرید فعلی |

#### مراحل رهگیری لحظه‌ای در `/orders/{id}/track`:
```json
{
  "success": true,
  "data": {
    "order_id": 8520,
    "order_number": "#8520",
    "current_status": "preparing",
    "order_mode": "delivery",
    "steps": [
      { "step": "received", "title": "دریافت سفارش", "completed": true, "time": "12:15" },
      { "step": "preparing", "title": "در حال آماده‌سازی در آشپزخانه", "completed": true, "time": "12:18" },
      { "step": "ready", "title": "تحویل به پیک", "completed": false, "time": "" },
      { "step": "completed", "title": "تحویل داده شد", "completed": false, "time": "" }
    ],
    "estimated_time": "30 - 45 دقیقه",
    "branch_id": 101
  }
}
```

---

### ۴.۷. رزرو آنلاین میز و تقویم جلالی (Reservations & Jalali Calendar)

| متد | مسیر | توضیحات |
|:---|:---|:---|
| `GET` | `/reservations/calendar` | ماتریس روزهای ماه شمسی و وضعیت ظرفیت باز/بسته |
| `GET` | `/reservations/slots` | استعلام اسلات‌های زمانی آزاد برای شعبه، تاریخ، تعداد نفرات و بخش (سالن، تراس، VIP) |
| `POST` | `/reservations` | ثبت نهایی رزرو میز با ارسال خودکار پیامک تأیید |
| `GET` | `/reservations/my` | فهرست رزروهای گذشته و پیش‌روی کاربر جاری |
| `POST` | `/reservations/{id}/cancel` | لغو رزرو ثبت‌شده توسط کاربر |

---

### ۴.۸. دیدگاه‌ها و امتیازدهی (Reviews & Ratings)

| متد | مسیر | توضیحات |
|:---|:---|:---|
| `GET` | `/dishes/{id}/reviews` | دریافت نظرات تأیید شده برای یک غذا به همراه میانگین امتیاز |
| `POST` | `/dishes/{id}/reviews` | ثبت امتیاز (۱ تا ۵) و نظر جدید با نشان خریدار تأییدشده |
| `GET` | `/reviews/recent` | نظرات برتر اخیر مشتریان برای نمایش در صفحه نخست یا اپلیکیشن |

---

### ۴.۹. جستجوی هوشمند منو (Smart Search)

| متد | مسیر | توضیحات |
|:---|:---|:---|
| `GET` | `/search` | جستجوی وزن‌دار در عنوان، محتویات، برچسب‌ها با فیلتر قیمت و رژیم غذایی |
| `GET` | `/search/suggest` | تکمیل خودکار سریع عبارت جستجو (Autocomplete) |
| `GET` | `/search/popular` | محبوب‌ترین عبارات جستجو شده توسط مشتریان |

---

### ۴.۱۰. پنل مانیتورینگ آشپزخانه (KDS / Kitchen Operations)

| متد | مسیر | دسترسی | توضیحات |
|:---|:---|:---|:---|
| `GET` | `/kitchen/tickets` | پرسنل آشپزخانه | دریافت بلیت‌های فعال سفارش به ترتیب زمان ثبت و ضریب فوریت رنگی |
| `POST` | `/kitchen/tickets/{id}/status` | پرسنل آشپزخانه | تغییر وضعیت بلیت (`new` -> `preparing` -> `ready` -> `completed`) |
| `POST` | `/kitchen/tickets/{id}/item` | پرسنل آشپزخانه | تیک زدن آماده‌شدن یک قلم خاص از فاکتور |
| `POST` | `/kitchen/availability` | پرسنل آشپزخانه | ناموجود کردن موقت یک غذا یا اتمام موجودی برای شعبه |

---

## ۵. نمونه کدهای اتصال کلاینت‌ها (Client Integration SDKs)

### ۵.۱. نمونه پیاده‌سازی در Swift / iOS
```swift
import Foundation

class FlavorApiClient {
    static let shared = FlavorApiClient()
    private let baseURL = URL(string: "https://your-domain.com/wp-json/flavor/v1")!
    private var accessToken: String?

    func fetchMenu(branchId: Int, completion: @escaping (Result<[Dish], Error>) -> Void) {
        var urlComponents = URLComponents(url: baseURL.appendingPathComponent("menu"), resolvingAgainstBaseURL: false)!
        urlComponents.queryItems = [URLQueryItem(name: "branch_id", value: "\(branchId)")]
        
        var request = URLRequest(url: urlComponents.url!)
        request.httpMethod = "GET"
        if let token = accessToken {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            // Parse Standard Envelope (data.data)
        }.resume()
    }
}
```

### ۵.۲. نمونه پیاده‌سازی در Kotlin / Android
```kotlin
package com.flavor.restaurant.api

import retrofit2.Response
import retrofit2.http.*

interface FlavorApiService {
    @GET("settings/app-bootstrap")
    suspend fun getBootstrap(): Response<ApiResponse<BootstrapData>>

    @POST("auth/otp/verify")
    suspend fun verifyOtp(@Body body: OtpVerifyRequest): Response<ApiResponse<AuthResult>>

    @GET("menu")
    suspend fun getMenu(
        @Query("branch_id") branchId: Int,
        @Query("category") categoryId: Int?
    ): Response<ApiResponse<List<Dish>>>

    @POST("cart/items")
    suspend fun addToCart(
        @Header("Authorization") token: String?,
        @Header("X-Cart-Token") cartToken: String?,
        @Body body: AddCartItemRequest
    ): Response<ApiResponse<CartPayload>>
}
```

---

## ۶. جدول کدهای وضعیت و مدیریت خطاها (HTTP Error Codes)

| کد وضعیت | عنوان خطا | علت و راهکار |
|:---|:---|:---|
| **200 OK** | موفق | درخواست با موفقیت پردازش شد. |
| **201 Created** | منبع ایجاد شد | ثبت سفارش، رزرو یا ارسال دیدگاه موفق بود. |
| **400 Bad Request** | ورودی نامعتبر | فیلدهای اجباری ارسال نشده یا فرمت موبایل/تاریخ نادرست است. |
| **401 Unauthorized** | عدم احراز هویت | توکن Bearer ارسال نشده، منقضی شده یا نامعتبر است. |
| **403 Forbidden** | عدم دسترسی | کاربر مجوز لازم برای این شعبه یا عملیات مدیریتی را ندارد. |
| **404 Not Found** | پیدا نشد | شناسه غذا، شعبه یا سفارش وجود ندارد. |
| **409 Conflict** | تداخل داده | ساعت رزرو پر شده است یا وضعیت سفارش اجازه لغو نمی‌دهد. |
| **429 Too Many Requests** | محدودیت ترافیک | تعداد درخواست‌های مکرر (مثل درخواست پیامک یا جستجو) بیش از سقف مجاز است. |
| **500 Server Error** | خطای سرور | خطای داخلی پایگاه‌داده یا وب‌سرویس. |

---
**پایان مستندات REST API نسخه ۱.۱.۰**
