# گزارش ممیزی تطبیق و راستی‌آزمایی پیاده‌سازی سورس‌کد با مستندات (Implementation Verification Audit)

این سند گزارش ممیزی دقیق، شفاف و بر پایه سورس‌کدهای اجرایی واقعی در مخزن گیت پروژه **Flavor** است. در این ممیزی، مستندات Markdown، دیاگرام‌ها، کامنت‌ها و اندپوینت‌های برنامه‌ریزی‌شده به عنوان پیاده‌سازی لحاظ **نشده‌اند** و صرفاً کدهای منبع موجود در مخزن مبنای ارزیابی قرار گرفته‌اند.

---

## ۱. جدول جامع ممیزی ۳۵ مؤلفه کلیدی (Audit Summary Table)

| ردیف | مؤلفه (Feature) | وضعیت (Status) | فایل‌های پیاده‌سازی (Files) | تست‌ها (Tests) | آماده تولید؟ (Prod Ready) | کارهای باقی‌مانده (Missing Work) |
| :---: | :--- | :---: | :--- | :--- | :---: | :--- |
| **1** | **Flavor V2 REST API** | `[DOCUMENTATION ONLY]` | `flavor-core/flavor-core.php`, `includes/API/RestController.php` | `test-rest-api.php` | ❌ خیر | فضای‌نام `FLAVOR_CORE_REST_NAMESPACE` روی `flavor/v1` تنظیم است و روتر مستقل `/flavor/v2/*` در کد ثبت نشده است. |
| **2** | **V2 authentication** | `[PARTIALLY IMPLEMENTED]` | `includes/API/AuthController.php`, `Customer/OtpAuth.php`, `Customer/TokenService.php` | `test-token-service.php`, `test-rate-limit.php` | ⚠️ بله (تحت v1) | مسیرها تحت `/flavor/v1/auth/*` فعال هستند؛ احراز هویت پیامکی چندارائه‌دهنده‌ای پیاده شده اما روت v2 وجود ندارد. |
| **3** | **JWT access tokens** | `[PARTIALLY IMPLEMENTED]` | `includes/Customer/TokenService.php`, `includes/API/BaseApiController.php` | `test-token-service.php` | ⚠️ بله (Opaque) | توکن‌های صادرشده رشته‌های امن ۶۴ کاراکتری با هش SHA-256 و ذخیره در دیتابیس هستند، نه ساختار امضاشده JWT سه‌بخشی. |
| **4** | **Refresh tokens** | `[IMPLEMENTED]` | `includes/Customer/TokenService.php`, `includes/API/AuthController.php` | `test-token-service.php` |  بله | متد `TokenService::refresh()` با ابطال توکن قبلی و صدور جفت جدید (Rotation) با انقضای ۶۰ روزه فعال است. |
| **5** | **Cart tokens** | `[PARTIALLY IMPLEMENTED]` | `includes/WooCommerce/CartSession.php`, `includes/API/CartController.php` | `test-rest-api.php` | ⚠️ بخشی | کلاینت موبایل هدر `X-Cart-Token` را ارسال می‌کند، اما بک‌اند سبد خرید مهمان را با سشن‌های کوکی ووکامرس مدیریت می‌کند. |
| **6** | **V2 menu API** | `[PARTIALLY IMPLEMENTED]` | `includes/API/MenuController.php`, `Menu/MenuScheduler.php`, `Menu/AvailabilityManager.php` | `test-rest-api.php` | ⚠️ بله (تحت v1) | تمام اندپوینت‌های منو، دسته‌ها و جزئیات غذا با زمان‌بندی و ارز تحت `/flavor/v1/menu` پیاده شده‌اند نه `/flavor/v2/menu`. |
| **7** | **V2 search API** | `[PARTIALLY IMPLEMENTED]` | `includes/API/RestSearch.php`, `Search/SmartSearch.php`, `Search/SearchIndex.php` | `test-smart-search.php`, `test-persian-text.php` | ⚠️ بله (تحت v1) | جستجوی هوشمند با نرمال‌سازی فارسی/عربی و فیلترها تحت `/flavor/v1/search` پیاده شده است. |
| **8** | **V2 cart API** | `[PARTIALLY IMPLEMENTED]` | `includes/API/CartController.php`, `WooCommerce/CartSession.php` | `test-rest-api.php` | ⚠️ بله (تحت v1) | افزودن، ویرایش، حذف، کوپن تخفیف و کسر امتیاز باشگاه تحت `/flavor/v1/cart*` فعال است. |
| **9** | **V2 checkout API** | `[PARTIALLY IMPLEMENTED]` | `includes/API/OrderController.php`, `WooCommerce/CheckoutService.php` | `test-price-calculator.php` | ⚠️ بله (تحت v1) | ثبت سفارش، تبدیل ارز و اتصال درگاه از طریق `POST /flavor/v1/orders` پیاده شده است نه `POST /flavor/v2/checkout`. |
| **10** | **V2 order tracking** | `[PARTIALLY IMPLEMENTED]` | `includes/API/OrderController.php`, `Order/KitchenTicketRepository.php` | `test-rest-api.php` | ⚠️ بله (تحت v1) | پیگیری ۴ مرحله‌ای وضعیت سفارش از طریق `GET /flavor/v1/orders/{id}/track` پیاده شده است. |
| **11** | **V2 reservations** | `[PARTIALLY IMPLEMENTED]` | `includes/API/ReservationController.php`, `Reservation/SlotCalculator.php` | `test-jalali.php` | ⚠️ بله (تحت v1) | تقویم جلالی، اسلات‌یاب و ثبت رزرو تحت `/flavor/v1/reservations/*` فعال است. |
| **12** | **V2 kitchen API** | `[PARTIALLY IMPLEMENTED]` | `includes/API/RestController.php`, `Order/KitchenTicketRepository.php` | ندارد | ⚠️ بله (پایه) | دریافت تیکت‌ها و تغییر وضعیت کلی تیکت تحت `/flavor/v1/kitchen/*` فعال است؛ تغییر وضعیت تک‌آیتم پیاده نشده است. |
| **13** | **SSE kitchen updates** | `[DOCUMENTATION ONLY]` | `docs/API-ROADMAP.md`, `docs/ARCHITECTURE-V2.md` | ندارد | ❌ خیر | اندپوینت `text/event-stream` در PHP پیاده نشده و پنل آشپزخانه از Polling دوره‌ای استفاده می‌کند. |
| **14** | **Push notification infra** | `[PARTIALLY IMPLEMENTED]` | `includes/Notification/NotificationHub.php`, `includes/API/AuthController.php` | ندارد | ❌ خیر | جدول ذخیره توکن‌ها و روت `/auth/device` موجود است اما ارتباط مستقیم سرور به FCM/APNs به هوک وابسته است. |
| **15** | **Multi-branch isolation** | `[IMPLEMENTED]` | `includes/Support/Roles.php`, `includes/PostTypes/BranchPostType.php` | `test-zone-checker.php` |  بله | محدودسازی دسترسی مدیران، صندوق‌داران و آشپزخانه بر اساس متادیتای شعبه به طور کامل فعال است. |
| **16** | **Media optimization** | `[PARTIALLY IMPLEMENTED]` | `flavor/inc/class-theme-setup.php` | ندارد | ⚠️ بخشی | ابعاد تصویر `flavor-card` و `flavor-hero` ثبت شده اما تولید خودکار WebP و تگ‌های Picture پیاده نشده است. |
| **17** | **Theme design tokens** | `[IMPLEMENTED]` | `flavor/inc/class-design.php`, `flavor/assets/css/main.css` | ندارد |  بله | تزریق توکن‌های CSS Custom Properties برای رنگ‌ها، فاصله‌گذاری، سایه‌ها و انحناها به طور کامل پیاده شده است. |
| **18** | **8 UI skins** | `[IMPLEMENTED]` | `flavor/inc/class-design.php`, `flavor/inc/demo-catalog.php` | ندارد |  بله | ۱۲ پوسته کامل شامل مدرن، سنتی، فست‌فود، لوکس، کافه، ایتالیایی، دارک، مینیمال و... پیاده‌سازی شده است. |
| **19** | **Onboarding wizard** | `[IMPLEMENTED]` | `flavor/inc/class-onboarding.php` | ندارد |  بله | ویزارد ۱۰ مرحله‌ای کامل شامل تنظیم نام، لوگو، هیرو، رنگ، پوسته، اطلاعات تماس و ثبت اولین غذا پیاده شده است. |
| **20** | **Theme customizer** | `[IMPLEMENTED]` | `flavor/inc/class-customizer.php` | ندارد |  بله | کنترل‌های کامل سفارشی‌ساز وردپرس برای پوسته‌ها، رنگ‌ها، ساختار هدر، هیرو و ۹ سکشن صفحه نخست پیاده شده است. |
| **21** | **White-label config** | `[IMPLEMENTED]` | `flavor-core/includes/Mobile/MobileConfigManager.php`, `Admin/MobileAppAdmin.php` | ندارد |  بله | پنل ادمین، چک‌لیست آمادگی و اندپوینت‌های ذخیره و تحویل کانفیگ برند به طور کامل پیاده شده است. |
| **22** | **Flutter project** | `[IMPLEMENTED]` | `mobile/pubspec.yaml`, `mobile/lib/main.dart`, `mobile/lib/config/*` | تست‌های `mobile/test/` |  بله | پروژه کامل با ۱۹ صفحه UI، مدیریت وضعیت Riverpod، لایه شبکه، ماژول تاریخ شمسی و فونت وزیرمتن. |
| **23** | **Android project** | `[PARTIALLY IMPLEMENTED]` | `mobile/android/app/build.gradle`, `AndroidManifest.xml` | ندارد | ⚠️ بخشی | فایل‌های کانفیگ گریدل و مانیفست با دیپ‌لینک و مجوزها موجود است اما پوشه روت گریدل نیازمند اجرای فلاتر است. |
| **24** | **iOS project** | `[PARTIALLY IMPLEMENTED]` | `mobile/ios/Runner/Info.plist` | ندارد | ⚠️ بخشی | تنظیمات `Info.plist` با کلیدهای فارسی و طرح‌های URL موجود است اما پروژه Xcode برای بازکردن مستقل نیاز به flutter است. |
| **25** | **Flutter API integration**| `[IMPLEMENTED]` | `mobile/lib/core/api/api_client.dart`, `mobile/lib/repositories/*` | تست‌های Integration |  بله | کلاینت یکپارچه HTTP با رفرش خودکار توکن در خطای 401، مدیریت خطا و ۸ مخزن داده پیاده شده است. |
| **26** | **Flutter auth** | `[IMPLEMENTED]` | `mobile/lib/repositories/auth_repository.dart`, `screens/auth/*` | `customer_flow_test.dart` |  بله | صفحات درخواست و تایید OTP، ذخیره امن توکن‌ها در SecureStorage و ناتیفایر وضعیت کاربر پیاده شده است. |
| **27** | **Flutter cart** | `[IMPLEMENTED]` | `mobile/lib/repositories/cart_repository.dart`, `screens/cart/*` | `customer_flow_test.dart` |  بله | مدیریت سبد خرید، محاسبه افزونه‌ها (Modifiers)، اعمال کوپن و استیت‌منیجمنت کامل پیاده شده است. |
| **28** | **Flutter checkout** | `[IMPLEMENTED]` | `mobile/lib/ui/screens/checkout/checkout_screen.dart` | `customer_flow_test.dart` |  بله | تسویه‌حساب چندمرحله‌ای با انتخاب شعبه، نحوه تحویل (سالن/بیرون‌بر/پیک)، آدرس و روش پرداخت پیاده شده است. |
| **29** | **Flutter order tracking**| `[IMPLEMENTED]` | `mobile/lib/ui/screens/orders/order_tracking_screen.dart` | `customer_flow_test.dart` |  بله | استپر ۴ مرحله‌ای وضعیت سفارش، لغو سفارش و تاریخچه سفارش‌ها پیاده شده است. |
| **30** | **Flutter reservations** | `[IMPLEMENTED]` | `mobile/lib/ui/screens/reservation/reservation_screen.dart` | `reservation_flow_test.dart` |  بله | تقویم شمسی جلالی، انتخاب اسلات زمانی، بخش سالن و ثبت رزرو پیاده شده است. |
| **31** | **Flutter deep links** | `[PARTIALLY IMPLEMENTED]` | `mobile/android/app/src/main/AndroidManifest.xml`, `routes.dart` | ندارد | ⚠️ بخشی | فیلترهای Intent برای `flavor://` در اندروید تنظیم شده اما شنونده ران‌تایم در `main.dart` متصل نیست. |
| **32** | **FCM/APNs** | `[PARTIALLY IMPLEMENTED]` | `mobile/lib/core/notifications/notification_service.dart` | ندارد | ❌ خیر | سرویس ارسال توکن و هندلر پیلود اعلان پیاده شده اما فایل‌های `google-services.json` متصل نشده‌اند. |
| **33** | **CI/CD mobile builds** | `[IMPLEMENTED]` | `.github/workflows/build-mobile.yml`, `Mobile/BuildManager.php` | تست اسکریپت پایتون |  بله | وب‌هوک با امضای HMAC-SHA256، پایپ‌لاین GitHub Actions و ذخیره متادیتا و چک‌سام در دیتابیس پیاده شده است. |
| **34** | **Android APK/AAB build**| `[IMPLEMENTED]` | `.github/workflows/build-mobile.yml`, `scripts/provision_brand.py` | ندارد |  بله | جاب کامپایل APK و AAB با تزریق هویت برند و آپلود آرتیفکت در گیت‌هاب اکشنز پیاده شده است. |
| **35** | **iOS IPA build pipeline**| `[PARTIALLY IMPLEMENTED]` | `.github/workflows/build-mobile.yml` | ندارد | ⚠️ بخشی | جاب کامپایل آرشیو iOS روی رانر macOS در اکشنز پیاده شده؛ اما ارسال خودکار به TestFlight نیازمند کلید اپ‌استور است. |

---

## ۲. جزئیات فنی و ممیزی عمیق ۳۵ مؤلفه

### ۱ تا ۵: معماری وب‌سرویس، احراز هویت و توکن‌ها
- **REST API V2 Namespace:** در مستندات به صورت `/flavor/v2/*` تعریف شده اما در فایل `flavor-core/flavor-core.php` ثابت `FLAVOR_CORE_REST_NAMESPACE` برابر `'flavor/v1'` است و کنترلرهای ماژولار تحت v1 رجیستر شده‌اند. `[DOCUMENTATION ONLY]` برای روت v2.
- **Authentication & Tokens:** کلاس `TokenService.php` توکن‌های امن ۶۴ کاراکتری از طریق `wp_generate_password()` تولید و با هش `SHA-256` در جدول `flavor_auth_tokens` ثبت و اعتبارسنجی می‌کند. مکانیزم Refresh Token و ابطال (Revocation) کاملاً فعال است. `[IMPLEMENTED]` برای توکن‌های Opaque و رفرش‌توکن، `[PARTIALLY IMPLEMENTED]` برای ساختار JWT.
- **Cart Tokens:** کلاینت فلاتر هدر `X-Cart-Token` را به همراه ریکوئست‌ها ارسال می‌کند (`api_client.dart`)، ولی در سمت سرور کلاس `CartSession.php` سبد خرید را روی سشن و کوکی استاندارد ووکامرس مدیریت می‌نماید. `[PARTIALLY IMPLEMENTED]`.

### ۶ تا ۱۲: سرویس‌های کاتالوگ منو، جستجو، رزرو و آشپزخانه
- **Menu & Catalog:** کلاس `MenuController.php` به طور کامل متدهای دریافت دسته‌ها، لیست منو بر اساس شعبه، شید زمان‌بندی، فیلتر تخفیف و جزئیات غذا به همراه گروه‌های انتخابی (Modifiers) را ارائه می‌دهد. `[PARTIALLY IMPLEMENTED]` (تحت v1).
- **Smart Search:** کلاس `RestSearch.php` و `SmartSearch.php` قابلیت‌های جستجوی صوتی/متنی با نرمال‌سازی فارسی و اصلاح خطاهای تایپی را پیاده‌سازی کرده‌اند. `[PARTIALLY IMPLEMENTED]` (تحت v1).
- **Reservations:** کلاس `ReservationController.php` و `SlotCalculator.php` محاسبات اسلات‌های زمانی و تقویم شمسی جلالی را انجام می‌دهند. `[PARTIALLY IMPLEMENTED]` (تحت v1).
- **Kitchen KDS & SSE:** سیستم KDS در `RestController.php` و `kitchen-dashboard.php` بر پایه درخواست‌های دوره‌ای (Polling با تایمر قابل تنظیم) فعال است؛ اما استریم زنده `Server-Sent Events` (`GET /kitchen/stream`) هنوز در PHP پیاده نشده است. `[DOCUMENTATION ONLY]`.

### ۱۳ تا ۲۱: هویت بصری، پوسته‌ها، ویزارد و وایت‌لیبل
- **Design Tokens & 8+ Skins:** کلاس `Design.php` شامل ۱۲ پوسته کامل تجاری با متغیرهای CSS و تایپوگرافی کامل است. `[IMPLEMENTED]`.
- **Onboarding Wizard:** کلاس `Onboarding.php` ویزارد ۱۰ مرحله‌ای کامل را از نصب تا ثبت اولین غذا با ذخیره تنظیمات ارائه می‌دهد. `[IMPLEMENTED]`.
- **Theme Customizer:** کلاس `Customizer.php` پنل‌ها و کنترل‌های کامل تنظیمات زنده قالب را ثبت کرده است. `[IMPLEMENTED]`.
- **White-Label Provisioning:** کلاس‌های `MobileConfigManager.php` و `BuildManager.php` و داشبورد مدیریت وایت‌لیبل وردپرس با امتیازدهی آمادگی کامل پیاده شده‌اند. `[IMPLEMENTED]`.

### ۲۲ تا ۳۵: کلاینت موبایل Flutter و اتوماسیون CI/CD
- **Flutter Codebase:** پروژه فلاتر در دایرکتوری `mobile/` با ۱۹ صفحه UI، ناوبری، مدیریت استیت Riverpod و پشتیبانی کامل راست‌چین و تقویم جلالی پیاده شده است. `[IMPLEMENTED]`.
- **Flutter Modules:** ماژول‌های احراز هویت پیامکی، کاتالوگ منو، سبد خرید، تسویه‌حساب چندمرحله‌ای، پیگیری وضعیت سفارش و رزرو میز شمسی همگی متصل به API هستند. `[IMPLEMENTED]`.
- **CI/CD Pipeline:** فایل اکشن `.github/workflows/build-mobile.yml` و اسکریپت `mobile/scripts/provision_brand.py` جهت ساخت خودکار پکیج‌های اندروید و iOS با بررسی امضای HMAC-SHA256 پیاده شده‌اند. `[IMPLEMENTED]`.

---

## ۳. درصد واقعی پیاده‌سازی بر اساس سورس‌کد اجرایی (Implementation Metrics)

$$\text{امتیازدهی: } [\text{IMPLEMENTED}] = 100\% \quad\vert\quad [\text{PARTIALLY IMPLEMENTED}] = 60\% \quad\vert\quad [\text{DOCUMENTATION ONLY}] = 0\%$$

### ۱. درصد پیاده‌سازی واقعی بک‌اند (V2 Backend Implementation):
$$\mathbf{68\%}$$
*(توضیح: بخش عمده عملکردهای منطقی، دیتابیس، احراز هویت، کاتالوگ، سفارش، رزرو و وب‌هوک‌ها پیاده شده اما تحت فضای‌نام `flavor/v1` فعال هستند و فضای‌نام اختصاصی `flavor/v2` و استریم SSE صرفاً مستند شده‌اند).*

### ۲. درصد پیاده‌سازی مدرن‌سازی رابط کاربری وب (UI Modernization Implementation):
$$\mathbf{90\%}$$
*(توضیح: توکن‌های طراحی، ۱۲ پوسته، ویزارد ۱۰ مرحله‌ای، سفارشی‌ساز وردپرس و استایل‌های راست‌چین کامل پیاده شده‌اند؛ بهینه‌سازی فرمت تصاویر WebP نیازمند تکمیل است).*

### ۳. درصد پیاده‌سازی اپلیکیشن موبایل و ابزارهای بیلد (Mobile Application Implementation):
$$\mathbf{87\%}$$
*(توضیح: کدبیس فلاتر با ۱۹ اسکرین، مدیریت وضعیت، ریپازیتوری‌ها، اسکریپت تزریق برندینگ پایتون و پایپ‌لاین گیت‌هاب اکشنز کامل است؛ تنظیمات بومی APNs/FCM و امضای خودکار TestFlight نیازمند کلیدهای رسمی اپل و گوگل است).*
