# Flavor Restaurant Mobile Application (Flutter Client)
# اپلیکیشن موبایل یکپارچه رستوران Flavor (اندروید و iOS)

اپلیکیشن موبایل تجاری و یکپارچه **Flavor Mobile**، کلاینت اختصاصی و Cross-Platform پلتفرم رستورانی Flavor است که با فریم‌ورک **Flutter** و با رعایت بالاترین استانداردهای معماری Clean Architecture و Clean Code پیاده‌سازی شده است. این اپلیکیشن با اتصال مستقیم به **Flavor REST API** کلیهٔ قابلیت‌های سفارش‌گیری، رزرو میز، پرداخت و باشگاه مشتریان را در هر دو سیستم‌عامل **Android** و **iOS** ارائه می‌دهد.

---

## ۱. ویژگی‌های کلیدی اپلیکیشن (Key Features)

1. **معماری White-Label پویا**: دریافت آنی هویت بصری، رنگ سازمانی، فونت، لوگو و تنظیمات از وب‌سرویس `/settings/app-bootstrap`.
2. **پشتیبانی کامل RTL و زبان فارسی**: تایپوگرافی اصیل با فونت **وزیرمتن (Vazirmatn)** و تبدیل خودکار اعداد به ارقام فارسی.
3. **موتور اختصاصی تقویم جلالی (Jalali Date Engine)**: انتخاب تاریخ شمسی، ماتریس روزها و اسلات‌های خالی رزرو میز.
4. **ورود و احراز هویت سریع با پیامک (OTP Login)**: تایمر شمارش معکوس، ورود امن با توکن‌های JWT Bearer و چرخش توکن (Token Rotation).
5. **سفارش‌گیری چندحالته (Omnichannel Ordering)**:
   - سالن (Dine-in): انتخاب میز یا اسکن QR Code
   - بیرون‌بر (Takeaway): تحویل حضوری در شعبه
   - ارسال با پیک (Delivery): مدیریت آدرس‌ها و محاسبه کرایه ارسال
6. **سفارشی‌سازی پیشرفته غذا (Product Modifiers)**: انتخاب اندازه پرس، تاپینگ‌های چندانتخابی، درجه پخت، حذف مواد و یادداشت سرآشپز.
7. **سبد خرید هوشمند**: محاسبه آنی قیمت، اعمال کدهای تخفیف و تبدیل امتیازات باشگاه مشتریان به تخفیف فاکتور.
8. **رهگیری زنده سفارش (Real-Time Order Tracking)**: نوار پیشرفت ۴ مرحله‌ای با به‌روزرسانی خودکار وضعیت آشپزخانه.
9. **رزرو آنلاین میز**: انتخاب شعبه، تاریخ شمسی، تعداد نفرات، بخش سالن/تراس و رزرو با پیامک تأیید.
10. **باشگاه مشتریان و امتیازات (Loyalty)**: نمایش موجودی، نشان سطح (طلایی، نقره‌ای، برنزی) و تاریخچه سفارش‌ها.
11. **اعلان‌های لحظه‌ای (Push Notifications)**: اتصال به FCM و APNs برای اطلاع‌رسانی آماده‌شدن غذا و تخفیف‌ها.
12. **پشتیبانی از پیوندهای عمیق (Deep Links & App Links)**: باز شدن خودکار صفحات غذا، منو و رهگیری سفارش از طریق لینک‌های وب‌سایت.

---

## ۲. ساختار لایه‌ها و معماری (Clean Architecture)

```
Presentation (Screens & Custom Widgets)
      ↓
State Management (Providers: Auth, Config, Menu, Cart, Order, Reservation, Favorites)
      ↓
Repositories (Domain Interfaces & Local Cache)
      ↓
API Client (Dio/Http with Bearer Interceptor & Cart Token)
      ↓
Flavor REST API (/wp-json/flavor/v1/)
```

### ساختار دایرکتوری‌های پروژه:
```
mobile/
├── android/                   # پیکربندی نیتیو اندروید و Flavourها
├── ios/                       # پیکربندی نیتیو iOS و Schemas
├── assets/
│   ├── fonts/vazirmatn/       # فونت‌های وزیرمتن فارسی
│   ├── images/                # تصاویر و لوگوهای پیش‌فرض
│   └── icons/                 # آیکون‌های وکتور
├── lib/
│   ├── config/                # تم داینامیک، تنظیمات محیطی و روت‌ها
│   ├── core/
│   │   ├── api/               # کلاینت HTTP، مدیریت استثناها و توکن‌ها
│   │   ├── storage/           # ذخیره‌سازی امن توکن‌ها (SecureStorage)
│   │   ├── utils/             # تقویم جلالی، فرمت ارز، اعداد فارسی
│   │   └── notifications/     # سرویس اعلان‌های FCM و APNs
│   ├── models/                # مدل‌های داده‌ای JSON (Immutable Data Classes)
│   ├── repositories/          # لایه مخازن داده و ارتباط با API
│   ├── state/                 # ارائه‌دهندگان وضعیت (Provider State Management)
│   ├── ui/
│   │   ├── common/            # ویجت‌های دیزاین سیستم (Buttons, Cards, Shimmers)
│   │   └── screens/           # صفحه‌های ۲۰گانه اپلیکیشن
│   └── main.dart              # نقطه ورود و راه‌اندازی برنامه
├── test/                      # تست‌های واحد، مخزن و یکپارچگی End-to-End
└── pubspec.yaml               # وابستگی‌ها و متادیتا
```

---

## ۳. دستورالعمل بیلد و اجرای اپلیکیشن (Build Guide)

### ۳.۱. نصب پیش‌نیازها
- فلاتر نسخه ۳.۱۰ یا بالاتر (`Flutter SDK >= 3.10.0`)
- جاوا نسخه ۱۷ (`OpenJDK 17`)
- اندروید استودیو / Xcode برای شبیه‌سازها

### ۳.۲. نصب وابستگی‌ها
```bash
cd mobile
flutter pub get
```

### ۳.۳. اجرای محیط‌های مختلف (Flavors)

#### محیط توسعه (Development):
```bash
flutter run --flavor dev -t lib/main.dart
```

#### محیط آزمایشی (Staging):
```bash
flutter run --flavor staging -t lib/main.dart
```

#### محیط نهایی (Production):
```bash
flutter run --flavor prod -t lib/main.dart
```

---

## ۴. خروجی نهایی برای انتشار (Release & Deployment)

### ۴.۱. خروجی اندروید (Google Play / کافه‌بازار / مایکت)

#### تولید Android App Bundle (.aab):
```bash
flutter build appbundle --flavor prod -t lib/main.dart --release
```

#### تولید فایل نصبی مستقیم (Universal APK):
```bash
flutter build apk --flavor prod -t lib/main.dart --release
```

### ۴.۲. خروجی iOS (App Store / TestFlight / سیبچه)

```bash
flutter build ipa --flavor prod -t lib/main.dart --release
```

---

## ۵. اجرای تست‌های خودکار (Automated Testing)

برای اجرای تمامی تست‌های واحد، تبدیل تاریخ، محاسبه‌گر سبد خرید و تست یکپارچه سناریوی مشتری:

```bash
flutter test
```

---
**توسعه‌یافته بر پایه معماری تجاری و یکپارچه پلتفرم رستورانی Flavor**
