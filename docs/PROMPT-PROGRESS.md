# پیشرفت مجموعه پرامپت‌های رفع نواقص (prompt fix progress)

مخزن: `patrikjuniyor/flavor-restaurant` — سند مرجع: `flavor-restaurant-fix-prompts.txt` (۱۰۸ پرامپت در ۱۳ فاز).

| وضعیت | تعداد |
|---|---:|
| ✅ انجام‌شده | 55 |
| 🟨 بخشی انجام‌شده | 0 |
| ⛔ مسدود (نیاز به محیط/تصمیم مالک) | 0 |
| ⬜ انجام‌نشده | 53 |
| **مجموع** | **108** |

درصد پیشرفت کامل: **51%** (55/108)

این فایل با `python3 dev-tools/build-progress.py` از روی `progress-status.json` بازتولید می‌شود؛ ویرایش دستی را از دست می‌دهد.


## فاز 0 — آماده‌سازی محیط (فقط یک‌بار، پیش از همه‌چیز)

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 1 | ✅ انجام شد | PHP 8.3 (یا 8.2) را با اکستنشن‌های mbstring, sqlite3, pdo_sqlite, curl, json, | PHP 8.4 + mbstring/sqlite3/pdo_sqlite/curl/json/openssl/sodium/zip، composer install، run-pure-tests: 23/23 — `commit e2655f9` |
| 2 | ✅ انجام شد | Node.js 20+ و npm را نصب کن. سپس `node tests/ui/motion.mjs` را اجرا کن تا | Node 20.20 + npm ci در tests/ui و tests/demos؛ motion.mjs: 11/11 سبز — `commit e2655f9` |
| 3 | ✅ انجام شد | ابزار gettext (msgfmt, msgattrib, msgcat) و Python 3 را نصب کن. | gettext 0.23.1 + Python 3.13؛ check-i18n سبز؛ کاتالوگ عربی 1380/1380 (100%)؛ test-translation 84/84 — `commit e2655f9` |
| 4 | ✅ انجام شد | Flutter SDK (پایدار، 3.22 یا جدیدتر) را نصب کن. در پوشهٔ mobile دستورات | Flutter 3.24.5 (همان pin که CI استفاده می‌کند): pub get OK، analyze بدون هشدار، test 43/43. با SDK جدیدتر (3.35) resolver روی محدودیت intl ^0.19.0 می‌شکند — `commit e2655f9` |
| 5 | ✅ انجام شد | اسکریپت `./dev-tools/run-theme-tests.sh` را اجرا کن. همهٔ مجموعه‌های تست قالب | run-theme-tests.sh سبز (1831 بررسی در 22 فایل) + اسکریپت dev-tools/run-baseline.sh و فایل baseline-tests.txt در ریشه — `commit e2655f9` |
| 6 | ✅ انجام شد | `php dev-tools/check-versions.php` و `php dev-tools/check-class-wiring.php` و | check-versions، check-class-wiring، check-child-theme هر سه سبز؛ نسخه قالب 1.6.2 / هسته 1.5.1 یکدست است — `commit e2655f9` |

## فاز 1 — اصلاح رشته‌های منبع معیوب (پیش از هر بازسازی کاتالوگ)

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 7 | ✅ انجام شد | در فایل flavor/inc/class-customizer.php خط حدود ۴۵۲، متن توضیح حاوی واژهٔ | «títuloها» → «تیترها» در class-customizer.php:452 — `commit 89a88b4` |
| 8 | ✅ انجام شد | در همان فایل flavor/inc/class-customizer.php خط حدود ۴۶۹، رشتهٔ «فقط | «títuloهای h2 تا h6»/«título اصلی» → «تیترهای…»/«تیتر اصلی» در class-customizer.php:469 — `commit 89a88b4` |
| 9 | ✅ انجام شد | در فایل flavor/inc/class-ui-customizer.php خط حدود ۵۸، رشتهٔ «استعداد و | توضیح فونت به «فونت‌های استعداد (Estedad) و وزیرمتن (Vazirmatn) …» تغییر کرد — `commit 89a88b4` |
| 10 | ✅ انجام شد | در فایل dev-tools/i18n/ar-part5.py سه کلید ترجمهٔ قدیمی مربوط به پرامپت‌های | سه کلید در dev-tools/i18n/ar-part5.py جایگزین شد؛ ترجمه عربی توضیح فونت نیز اصلاح شد (خط وزير متن → وزيرمتن/Estedad) — `commit 89a88b4` |
| 11 | ✅ انجام شد | کاتالوگ‌های ترجمه را بازسازی کن: ابتدا `php dev-tools/build-pot.php` (یا | pot/po بازسازی و mo کامپایل شد؛ تعداد msgid unchanged (1380); check-i18n/test-translation/test-update-channel/run-theme-tests سبز — `commit 89a88b4` |
| 12 | ✅ انجام شد | یک گشت کامل غلط‌های تایپی و املایی روی متن‌های فارسی منبع بزن: در همهٔ | اسکنر dev-tools/i18n/scan-persian-typos.py اضافه شد (۷ طبقه)؛ «اوليه»→«اولیه»، فاصلهٔ قبل از «،» در CacheHints؛ گام CI جدید در i18n-guard؛ خروجی اسکن = ۰ — `commit 89a88b4` |

## فاز 2 — نواقص قطعی کد (یافته‌های بازبینی ۱۴۰۵/۰۷/۲۰)

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 13 | ✅ انجام شد | در فایل flavor/header.php خط ۲۳ صفت dir="rtl" هاردکد شده است | هر دو هدر فقط language_attributes() چاپ می‌کنند؛ direction/text-align از main.css بیرون رفت، آفست‌ها به inset-inline-*، drawer/انیمیشن‌ها نسخهٔ LTR گرفتند. تست جدید tests/theme/test-rtl-ltr-parity.php (۲۵ بررسی) + بازنویسی شواهد RTL در test-distribution.php — `commit 05d0b32` |
| 14 | ✅ انجام شد | به OrderController در flavor-core/includes/API/OrderController.php متد | RateLimit::guard('ord_create',10,600,$cart_token) در create_order + scope اختیاری در RateLimit؛ پاسخ ۴۲۹ rate_limit_exceeded. سناریوهای e2e با reset جدا می‌شوند؛ افراز ۱۰×۲01 و ۴۲۹ در یازدهمی و تست جدا بودن سطل‌ها اضافه شد → run-e2e-integration.php ۸۱/۸۱ — `commit d558a6e` |
| 15 | ✅ انجام شد | فایل mobile/ios/Flutter/flutter_export_environment.sh در مخزن کامیت شده | mobile/ios/Flutter/flutter_export_environment.sh از git بیرون رفت (مولّد، با مسیرهای /opt/flutter و build ۱۸) و .gitignore چهار قانون گرفت: همین فایل، GeneratedPluginRegistrant اندروید، ios/Pods/، ios/Podfile.lock. تنها فایل Generated که هنوز ترک شده brand_tokens.g.dart عمدی است — `commit 16faad6` |
| 16 | ✅ انجام شد | نسخهٔ اپ موبایل را یکدست کن: mobile/pubspec.yaml می‌گوید version: 1.0.0+1 | AppVersion در mobile/lib/config/app_config.dart منبع یکتای نسخه (define FLAVOR_APP_VERSION، fallback 1.0.0)؛ هدر API، main.dart، AppBrandInfo و syncTokenRegistration از آن می‌خوانند و هیچ نسخهٔ سفت‌کدشده‌ای در lib نمانده. build-mobile.yml یک‌بار از pubspec می‌خواند و به apk/appbundle/ipa پاس می‌دهد؛ MOBILE.md، چک‌لیست و PHASE-2-VERIFICATION هم‌راستا شدند — `commit a688c80` |
| 17 | ✅ انجام شد | چهار پوشهٔ یتیم demos را پاک‌سازی کن: flavor/demos/fine-dining ، | چهار پوشهٔ یتیم flavor/demos حذف شد و وابستگی پنهان Repeater::fallback('gallery') به آن‌ها رفع شد (فهرست از اسلاگ معتبر ساخته می‌شود تا همیشه شش کاشی). کاتالوگِ تست حالا هر پوشهٔ یتیم را رد می‌کند؛ build-distribution.sh سبز — `commit cb188da` |
| 18 | ✅ انجام شد | دموی persian-traditional (پرچم‌دار بازار ایران) ناقص است: پوشهٔ | چهار تصویر دسته‌بندی هم‌سبک hero.jpg (۱۲۰۰×۹۰۰، ≤۲۵۰KB) برای persian-traditional + کلید category_images در کاتالوگ. تست کاتالوگ دو بررسی گرفت: دسته بدون تصویر اختصاصی، و تصویر با ضلع کوچک‌تر از ۶۰۰px — `commit 95483ea` |
| 19 | ✅ انجام شد | فایل docs/raastichin/listing.md ادعا می‌کند «هشت دمو» در حالی که محصول | listing.md: «هشت دمو»/«۸ دمو» → فهرست واقعی ۱۲ دمو + چک‌لیست ارسال با وضعیت واقعی هر بند (flavor-core.zip، LICENSE داخل بسته‌ها، ویدیو = انجام‌نشده با اشاره به بندهای ۵۳/۱۰۰/۹۴)؛ README هم اصلاح شد و تست کاتالوگ هر ادعای «N دمو» را با کاتالوگ می‌سنجد — `commit b3bc786` |
| 20 | ✅ انجام شد | رشته‌های فارسی هاردکد در جاوااسکریپت سمت مشتری را قابل ترجمه کن. فایل | ۷۴ رشته از menu.js به Enqueue::menu_strings() و flavorData.i18n.menu منتقل شد؛ برچسب رژیم غذایی به UI::dietary_labels() (تک‌نسخه، فیلترها هم از همان می‌خوانند)؛ فاصلهٔ اول/آخر رشته‌ها به markup رفت چون xgettext msgid را trim می‌کند؛ ar-part6.py هر ۶۵ msgid تازه را عربی کرد (کاتالوگ ۱۴۴۵/۱۴۴۵)؛ تست جدید tests/theme/test-js-i18n.php با parity دوسویه، بررسی placeholder، فهرست بدهی ۱۳ فایل، و دو آزمون منفی — `commit 53cee48` |
| 21 | ✅ انجام شد | همان کار پرامپت ۲۰ را برای این فایل‌ها انجام بده (هر فایل در یک نوبت | هر هفت فایل منتقل شد: premium.js (۳۰ رشته → Premium_Assets::strings/Wishlist::strings)، customer/auth/pages/navigation (خانوادهٔ flavor-ui-js → UI::strings روی flavorData.i18n.ui)، catering/bespoke-demos (→ Enqueue::catering_strings و Bespoke_Demos::strings). فهرست بدهیِ JS از ۱۳ فایل به ۴ رسید (reservation/search/tracking/ui؛ motion.js و demo-animations.js فقط جدول رقم داشتند — پرامپت ۲۲). تست قرارداد: جدول arity + بررسی شکلِ helperها + سیم‌کشیِ payload؛ ۱۰۴/۰. کاتالوگ عربی ۱۴۸۸/۱۴۸۸. — `commit 9bf00a9` |
| 22 | ✅ انجام شد | در فایل flavor/assets/js/menu.js تابع fa() اعداد را فارسی می‌کند و | یک شکل‌دهندهٔ عدد: digits.js (جدید) + UI::digits_config() روی flavorData.digits {zero, arabicZero, thousands, mode}. هشت فایل (ui, menu, navigation, pages, bespoke-demos, catering, search, motion, demo-animations) از toLocaleString/جدولِ دست‌نویس به FlavorDigits وصل شدند؛ flavor-main-js وابسته به flavor-digits. جداکننده از number_format_i18n خوانده می‌شود، mode از تنظیمِ digitsِ کور، و fallbackِ آفلاین با مقدارِ سرور برابری سنجیده‌شده است. تست‌ها: tests/theme/test-digit-parity.php (۲۷/۰) + tests/ui/digits.mjs (۳۰/۰، در npm run test:digits و CI). docs/fa/02 بخش «ارقام و قیمت» گرفت. منفی‌آزماییِ هر چهار قانون. — `commit 7b21e2b` |
| 23 | ✅ انجام شد | تابع date() در flavor-core/includes/API/OrderController.php (چند جا در | FlavorCore\Support\Clock (جدید): لنگر کردن رشتهٔ naive در منطقهٔ زمانیِ سایت + قالب با wp_date(). سه زمانِ مرحله در OrderController::track_order به آن وصل شد؛ get_date_created()->date() و Jalali عمداً دست‌نخورده‌اند (کامنت دارد) و TokenService/OtpAuth هم چون رفت‌وبرگشتِ naive خنثی است. mock-wp-environment سه تابع wp_timezone/wp_date/current_datetime گرفت. تست: tests/theme/test-order-clock.php ۳۴/۰ با دو منفی‌آزمایی (۳ شکست و ۱۲ شکست). — `commit 7542c24` |

## فاز 3 — مأموریت‌های باز اسناد پروژه (R3) با ترتیب وابستگی

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 24 | ✅ انجام شد | تصمیم R3-01 دربارهٔ Theme Builder را اجرایی کن با گزینهٔ (ب): حفظ هدر و | تصمیم R3-01 با گزینهٔ (ب) اجرا شد: Flavor\Product_Page انتخاب‌گر مدیفایر را روی woocommerce_before_add_to_cart_button رندر می‌کند (هیچ قالب ووکامرس کپی نمی‌شود)، برچسب‌ها از UI::modifier_labels()، ui-product.css فقط روی صفحهٔ محصول. R3-11 هم بسته شد: README/readme.txt/listing.md بدون ادعای Theme Builder و با تعریف دقیق. تست tests/theme/test-product-page.php ۷۵/۰ با پنج منفی‌آزمایی؛ build-ar-part.py هم برای کلاس‌های همه‌بزرگ (UI) اصلاح شد. — `commit dae74ca` |
| 25 | ✅ انجام شد | R3-10: دکمهٔ «همگام‌سازی با Elementor» را به صفحهٔ تنظیمات Flavor در | R3-10 اجرا شد: بخش «همگام‌سازی کیت Elementor» در مرکز آمادگی انتشار (Launch_Center) با دکمهٔ دستی، تیکِ بازنویسی اجباری، گزارشِ written/skipped به تفکیک توکن، و ماندنِ نتیجه در flavor_kit_sync_report. چهار وضعیتِ بی‌صدای قبلاً پیام دارند و ردیفِ چک‌لیست required=false ماند تا سایتِ بدونِ المنتور جریمه نشود. تست tests/theme/test-kit-sync-action.php ۷۹/۰ با فیکسچرِ رندر ادمین و هفت منفی‌آزمایی. — `commit 950ab8e` |
| 26 | ✅ انجام شد | R3-09: ۵۴ کنترل از ۱۴۴ کنترل Customizer فاقد توضیح‌اند (طبق | ۵۴ توضیح فارسی نوشته شد (۲۸ premium + ۱۷ chrome + ۳ typo + ۱ ui؛ شش اسلات گالری یک msgid موجود را مشترکاً خواندند). جدول مرجع با رندر آفلاینِ جدید (tests/theme/test-customizer-reference.php + مدیرِ ضبط‌کنندهٔ تازه) بازتولید و با جدولِ زنده هم‌سان سنجیده شد؛ گاردِ ۱۱ بررسی، منفی‌آزمایی‌شده. POT ۲۱۰۴؛ عربی ۱۵۵۸/۱۵۵۸ با ar-part13.py. — `commit 6ccacc3` |
| 27 | ✅ انجام شد | R3-02: بررسی بصری سه قالب Elementor (flavor-home، flavor-about، | گاردِ بصریِ Playwright ساخته شد: tests/ui/elementor-pages.php (رندر آفلاینِ هر سه قالب با template-partهای واقعی و تصویرهای دمو) + tests/ui/elementor-visual.mjs (۳ قالب × ۲ حالت × ۳ عرض؛ overlap دونفره، کنتراستِ هر تکّه‌متن با فرمولِ Contrast، سرریز افقی؛ ۱۸ اسکرین‌شات + report.json) + گامِ elementor-visual-guard در CI پس از motion-layer-guard. سه عیبِ واقعی رفع شد: بوم/تیترهای hard-paint شده در شش اسکینِ کلاسیک که در حالت شب برنمی‌گشتند (نگاشتِ متغیرهای خام به توکن‌ها)، #fff ثابت روی دکمهٔ primary و بنرِ پیشنهاد (حالا var(--ui-action-ink)) و رنگِ ثابتِ تیتر بنر (inherit). منفی‌آزمایی: حذفِ بلوکِ شب → ۴۰ تخلف؛ margin منفی → overlap؛ خاکستری → ۱۵ تخلف؛ تصویر شکسته → سرریز؛ بعدِ رفع ۱۸/۱۸ سبز. — `commit 5156b7f` |
| 28 | ✅ انجام شد | R3-03: رندر کامل ویجت‌های داده‌وابسته را تأیید و تثبیت کن: با ووکامرس | حالت پر/خالیِ ویجت‌های داده‌وابسته: پیامِ note برای gallery+offers، صفحات states در فیچر-بیلدر، گاردِ 30-بررسی با چکِ شکلِ وضع و دروازهٔ PHP-diagnostics، smoke واقعی با warning-capture، کانترکت 298/0، POT تم 1560 و عربی 1560/1560 (pushed: 864f7f0+9909526) — `commit 864f7f0` |
| 29 | ✅ انجام شد | R3-05 (بخش قابل اجرا): فهرست رشته‌های عربی را یک بازبینی ساختاری-زبانی | بازرسِ ماندگارِ flavor-ar.po (audit-ar-po.py) به check-i18n وصل شد؛ ۹ اصلاحِ ترجمه در ar-part99-review.py (ارقامِ عربی، ۲۰۱۶م، العنابی، رفع mistranslationِ ماست→مخلل)؛ یادداشتِ بازبینیِ بومی در صدرِ کاتالوگ؛ msgfmt+check-i18n سبز، منفی‌آزماییِ ۱۸-خطا — `commit c8dce84` |
| 30 | ✅ انجام شد | R3-06: کانال به‌روزرسانی امضاشده را به یک سرور واقعی وصل کن. از آنجا که | کانال امضاشده زنده شد: keygen خارج از مخزن، public.key کامیت‌شده + امپد در functions.php با گارد defined، مانیفستِ ۱.۶.۲ امضا و کنارِ zip در release-site/flavor؛ verify سبز، منفی‌آزماییِ دستکاریِ نسخه و گاردِ کلیدِ داخل‌مخزن؛ publish-release.yml با کلیدِ عمومی چک می‌کند؛ docs 10 با آدرسِ نهایی — `commit 4ca1e85` |
| 31 | ✅ انجام شد | R3-11 (تکمیلی): یک گشت «ادعا در برابر واقعیت» روی همهٔ متون فروش و | گشت ادعا-در-برابر-واقعیت: اصلاح README (اپ بومی داخل مخزن است، چندزبانه دقیق)، listing (حذف زرین‌پال، علامت تصمیم مالک، جدول نهایی ادعاها با پشتوانه)، mobile/README (پرداخت=ثبت روش، QR=deeplink بدون اسکنر)؛ گیتِ ماندگار tests/theme/test-claims.php با ۲۵ بررسی عددخوانی‌شده از درخت زنده؛ منفی‌آزمایی ۱۷→۲۰ — `commit d547349` |
| 32 | ✅ انجام شد | R3-12 (هشدار امنیتی مالک — در فایل مستندات ثبت کن): در | یادداشتِ امنیتیِ R3-12 در صدرِ سند R3 و بخشِ تازهٔ README؛ بازرسیِ کاملِ تاریخچه (صفرِ رمز واقعی؛ فقط placeholderِ README-updater)؛ گیتِ تازه در شغلِ security-scan برای credential-in-history — `commit c31c5b8` |

## فاز 4 — سخت‌گیری امنیتی (سیستماتیک)

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 33 | ✅ انجام شد | گشت nonce و CSRF روی همهٔ فرم‌های ادمین قالب و افزونه بزن: فایل‌های | ممیزیِ nonce/capability روی ۲۱ هندلرِ admin-post/admin_init؛ تنها شکاف (capability در github-updater) بسته شد؛ گیتِ ماندگار test-nonce-coverage.php با پارسرِ token_get_all و منفی‌آزماییِ زنده — `commit bab039b` |
| 34 | ✅ انجام شد | گشت capability و نقش‌ها: فایل flavor-core/includes/Support/Roles.php و | گشتِ نقش/شعبه: resolve_branch_scope در API مشترک؛ سه گپِ «حذفِ branch_id = همهٔ شعبه» بسته شد (Analytics، رزروها، گزارش‌ساز ادمین+CSV)؛ تست IDOR (phpunit) + رانرِ مستقل run-branch-scope-guards.php به گامِ Securityِ CI وصل شد (۲۳ بررسی، منفی‌آزماییِ زنده سبز)؛ e2e ۸۱/۸۱ — `commit 98d493b` |
| 35 | ✅ انجام شد | گشت escape خروجی در قالب‌ها: همهٔ echo/print در فایل‌های PHP زیر | گشتِ escape خروجی روی ۵۳ فایل template/view با اسکنرِ token-based: صفرِ echo خام؛ هر ۱۱ phpcs:ignoreِ escape بازرسی و مؤید شد (flavor_responsive_image داخل esc_attr/esc_url می‌کند). کشفِ جانبی: ۱۳ تستِ tests/theme هیچ‌وقت در CI اجرا نمی‌شدند — گامِ globِ ایستاده (run-theme-tests.sh) به backend job اضافه شد — `commit b49d1a1` |
| 36 | ✅ انجام شد | گشت sanitize ورودی: همهٔ $_GET/$_POST/$_REQUEST و get_json_params و | گیت sanitize ورودی: ۹۸ فایل flavor-core (API + ادمین + views) — ۲۳۰ خواندن superglobal/get_param و ۵۵ تجزیه بدنه JSON بازرسی شد؛ ۹ اصلاح واقعی (۸ فیلتر ReportAdmin با sanitize_key/text_field/absint + یک CacheHints)؛ سه ignoreِ ساختاری (آرایه modifier/qty/mod) پس از تأییدِ sanitize در sink، allowlist صریح خوردند؛ گیتِ مستقل ۹ بررسی با منفی‌آزماییِ زنده — `commit b49a12b` |
| 37 | ✅ انجام شد | endpoint های عمومی REST را سخت‌تر کن: SettingsController مسیری با | سخت‌کردن endpointهای عمومی: منیفست ۴۳ مسیریِ __return_true پین شد (مسیرِ تازه بدونِ بازبینی → FAIL)؛ canary زنده (mock WP، توکن‌هایِ کاشت‌شده از مسیرِ تولیدی) روی app-bootstrap/mobile-config/system-health: هیچِ رازی در پاسخِ عمومی نیست؛ health برایِ ناشناس فقطِ status است و برایِ ادمین کامل (اثباتِ redaction واقعی)؛ HMACِ build-callback با hash_equals pin شد؛ صفرِ نشتِ واقعی — بازبینی تأیید کرد هر سه کنترلر از قبل درست بودند — `commit 7d683b4` |
| 38 | ✅ انجام شد | GuestToken و OTP را بازبینی کن: (۱) GuestToken::generate باید از | بازبینی GuestToken/OTP: generateها CSPRNG-only شدند (fallback ضعیف uniqid+microtime حذف، fail-closed)؛ OTP از قبل clamp 4-6 + سقف 5 تلاش + hash_equals + HMAC-peppered بود (pin شد)؛ دو تست phpunit تکمیل و آیینهٔ ماشینی‌شان (۱۶ assertion تازه) در run-pure اجراشدنیِ CI نشست: ۳۹/۰ — `commit dae61f2` |
| 39 | ✅ انجام شد | هدرهای امنیتی پاسخ‌ها: در BaseApiController یا یک middleware، هدرهای | هدرهای امنیتی پاسخ: middleware تازهٔ Support\SecurityHeaders روی rest_post_dispatch — nosniff + Referrer-Policy برای همهٔ پاسخ‌های flavor/v1 (خطاها هم) و Cache-Control: no-store به‌صورتِ پیش‌فرضِ منع برای دادهٔ مشتری (orders/reservation/auth/cart)؛ opt-inِ صریحِ public, max-age برای menu/search حفظ شد. HTML عمومی: CSPِ Report-Only نرم (unsafe-inlineِ Elementor-ساز، بدونِ report-uri، فیلترِ flavor_csp_report_only). گیتِ تازهٔ تم ۲۶ بررسی + آیینهٔ ۸تایی در run-pure: ۴۷/۰؛ سه منفی‌آزماییِ زنده دقیقاً هر سه FAIL را داد؛ e2e 81/81 و گیت ۳۷ بدون‌تغییر سبز — `commit 325c829` |
| 40 | ✅ انجام شد | وابستگی‌های شخص ثالث را audit کن: composer.json (dev)، package.json های | ممیزی وابستگی‌ها: composer audit=0 advisory (35 پکیج)، npm tests/ui+demos=0، OSV روی هر 105 پکیج pubspec.lock=0 (اسکریپت dev-tools/osv-pub-audit.py). دو رفع واقعی: پین platform.php=8.2.34 + downgrade instantiator 2.1.0→2.0.0 (composer install در job 8.2 می‌مرد) و حذف exclude مردهٔ WPCS3 از phpcs.xml (report همیشه تهی بود). لایسنس‌ها: vazirmatn/OFL.txt اضافه شد (estedad داشت، iransans تجاری=بدون باندل)، MIT هدر qrcode پین؛ گیت §۷ test-distribution (10 بررسی، 59/0) با منفی‌آزمایی؛ یافته‌ها در docs/CI-CD.md §۵ — `commit ac8175a` |
| 41 | ✅ انجام شد | تست نفوذ کوچک روی درون‌ریزی دمو: class-demo-importer.php فایل‌های تصویر | تست‌نفوذ demo-importer: گیتِ نامِ تخت (basename+sanitize+regex لنگردار+ردِ دابل‌اکستنشن)، محصورسازی realpath داخل demos/ (symlink فرارکننده رد می‌شود)، وایت‌لیستِ موافقِ پرامپت jpg/png/svg (webp حذف؛ svg فقط بی‌ضرر — script/on*/ENTITY/foreignObject → reject). حفرهٔ دومِ واقعی: مقصدِ copy در rollback از آپشن می‌آمد → گاردِ basedir+ضدِ.. با ترتیبِ mkdir-پسِ-تأیید. گیتِ تازه ۳۱ بررسی با پروب‌هایِ زنده و سه منفی‌آزماییِ هدف‌دار روی کدِ پروداکشن — `commit 0354520` |
| 42 | ✅ انجام شد | WebhookManager و WebhookController (flavor-core/includes/Webhooks و | وب‌هوک خروجی: sign_body/verify_signature با hash_equals (قالب build-mobile.yml؛ fail-closed روی رمز خالی)، https-only با opt-in صریح FLAVOR_WEBHOOK_ALLOW_HTTP (حصار SSRF تکان‌نخورده)، retry backoff نمایی ۳۰s→سقف ۱h با بودجه ۵ (بدون مهاجرت schema؛ cron روی نزدیک‌ترین بیداری) + چرخش رمز در update. گیت ۴۴ بررسی + e2e با اثبات رفتاری پنجره backoff و exhaustion (۸۱/۸۱)؛ چهار منفی‌آزماییِ هدفمند — `commit 5c3895f` |

## فاز 5 — کیفیت کد و سازگاری

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 43 | ✅ انجام شد | گزارش phpcs (composer phpcs با WPCS 3.1) را بگیر. نقض‌های خطای سطح Error | بک‌لاگ phpcs: ۱۸۷۴ خطای Error → صفر (پاسِ بازسازی‌شده پس از wipe سندباکس؛ درخت از origin@P42 + رانِ مجدد کل فیکس‌ها)؛ گیتِ ماشینی dev-tools/phpcs-error-gate.sh در jobِ WPCSِ CI بلوکه‌کننده شد؛ DirectDatabaseQuery از اول صفر؛ سه sniffِ سلیقه‌ای با دلیل exclude؛ POT/PO دوباره‌سازی (۱۰۰٪ عربی) و پین‌هایِ همهٔ گیت‌ها زنده — `commit 887efda` |
| 44 | ✅ انجام شد | سازگاری PHP 8.0 تا 8.4: با اجرای `php -l` روی همهٔ فایل‌ها (در CI هست) | سازگاری PHP 8.0–8.4: ممیزیِ ۲۲۷ فایلیِ پرامپت (interpolation/dynamic-prop/null-internal/named-args/removed-fns) صفرِ واقعی داد؛ استابِ preset-control به‌خاطرِ $jsonِ غایبِ dynamic-property 8.4 می‌زد — mirror core شد؛ گیتِ ماشینی test-php8x-compat با ۷ clause + پینِ matrix و پینِ صداقتِ Requires PHP 8.0؛ sabotage چهارالگویی → FAIL → revert — `commit 33fb00b` |
| 45 | ✅ انجام شد | کد مرده و فایل‌های بدون استفاده را حذف کن: dev-tools/README-updater.md، | ۲۱ متد/تابعِ تک‌مرجع و CSS یتیم (fd-mobile-bar، responsive-token، cart-badge، سلکتور entry-title) حذف شد؛ ۸۱۸ کلاسِ namespace‌دار همه reachable (زنده‌بودنِ dynamic از سطرهایِ چاپِ کلاس اثبات شد)؛ سه موردِ نام‌برده درِ پرامپت با دلیل ماندند (README-updater، اسلاتِ iransans، print-receipt تا ۶۲)؛ lib/ِ vendored excludedِ مستند؛ گیتِ test-dead-code هفت‌clause + دو اثباتِ sabotage → ۷/۰ — `commit ded8bc2` |
| 46 | ✅ انجام شد | یکدست‌سازی مدیریت خطا در REST: الگوی respond_error/respond_success در | یکدست‌سازی مدیریت خطا در REST: هر ۸۱ callback فقط از respond_success/respond_error/relay_error پاسخ می‌دهد (چهار کلاس legacy هم extends Base شدند)؛ میان‌افزار ResponseEnvelope ردِ permissionها و خطاهای هسته را فقط روی /flavor/* به پاکت {success,data,meta,errors} نرمال می‌کند؛ جدول status پین شد (۴۰۰/۴۰۱/۴۰۳/۴۰۴/۴۰۹-idempotency/۴۲۹) و کدهای بی‌پیشوند unauthorized/forbidden به flavor_* یکدست شدند؛ مصرف‌کننده‌ها (kitchen-dashboard JS، e2e ۸۱/۸۱) مهاجرت کردند؛ گیت token-based test-rest-response-contract هفت‌clause + سه اثبات sabotage → ۲۸/۰ — `commit 538eae2` |
| 47 | ✅ انجام شد | پوشش تست هسته را بالا ببر: فایل‌های flavor-core/includes که تست واحد | پوشش تست هسته: پنج سوئیت منطق خالص در flavor-core/tests/pure/ (تجمیع با glob در run-pure-tests + micro-stubهای exists-guarded، بدون WP و بدون mock) برای شش کلاس بی‌تست — PointsManager/Currency/DiscountManager (اولویت پول: convert floor، مرزهای estimate_points، ثابت‌های اقتصاد وفاداری)، WebhookManager (ماتریس sign/verify، backoff با cap، validate_events، حصار SSRF روی literalها)، CheckoutService helpers با reflection (idempotency sanitize/fingerprint/window ۱۲۰s) + SlotCalculator، ReceiptRenderer/KitchenTicketSync (mode_label زنده، اثبات short-circuitِ suspend بدون WC، پین‌های cancel-map و hook-drift)، PushNotificationService (بردارهای DER→raw + round-tripِ openssl_verify روی build_jwt با کلید P-256 یک‌بارمصرف)؛ run-pure-tests از ۴۷ به ۱۷۳؛ گیت test-core-test-coverage با اجرای زندهٔ runner + دو اثبات sabotage → ۱۲/۰ — `commit e9f34b0` |
| 48 | ✅ انجام شد | Jalali و زمان: تست‌های test-jalali.php را با لبه‌های تقویمی تکمیل کن: | تقویم جلالی و زمان: اصلاح is_leap به پورت وفادار jalaali-js (رجرشن کبیسه شبح ۱۴۰۷/۱۴۱۱/۱۴۱۵/۱۴۱۹/۱۴۲۳/۱۴۲۷ — اسفند ۳۰ روزه ناموجود که اعتبارسنج رزرو از مارس ۲۰۲۸ می‌پذیرفت)، سه جفت‌سازی epoch بومی (یادآور رزرو ۳:۳۰ ساعت دیر، پنجره ناموجودی ۳:۳۰ ساعت اضافه، سن تیکت آشپزخانه چسبیده به صفر)، لبه ۲۴:۰۰ سانس‌های بعداز‌نیمه‌شب پنجره شبانه، و fallback امروز جلالی با current_time؛ جاروب‌های راستی‌آزمایی: صفر واگرایی با jalaali-js (۱۳۸۰–۱۴۳۰) و is_leap⇔۳۶۶روز (۱۳۸۰–۱۴۲۹)؛ سوئیت pure/jalali-time (۵۳ assertion: ماتریس کبیسه، مرز ماه‌ها، لنگرهای رسمی، round-trip ۱۴۶۱ روزه، iran_dow، پین‌های سیاست +۳:۳۰، جاروب صفر-mktime) + پنج متد PHPUnit در test-jalali.php؛ گیت ۱۲→۱۵ با دو اثبات sabotage؛ run-pure-tests ۲۲۶/۰ — `commit 126d262` |
| 49 | ✅ انجام شد | گشت سازگاری ووکامرس ۱۱ / HPOS: مطمئن شو هیچ کوئری مستقیمی روی | گشت سازگاری WooCommerce 11/HPOS: ۱۶۸ فایل تولیدی، صفر تخلف — صفر کوئری مستقیم wp_posts برای سفارش‌ها (همه‌جا wc_get_orders/wc_get_order/CRUD)، declare_compatibilityها (custom_order_tables + cart_checkout_blocks با گارد FeaturesUtil در before_woocommerce_init) تأیید و پین شد، GatewayRegistrar با رشتهٔ نام کلاس و پشت کلید Settings ثبت می‌کند (بدنهٔ register صفر new/WC — بدون فراخوانی کلاس منسوخ) و گیتوی‌ها \WC_Payment_Gateway جاری را با گارد class_exists ارث می‌برند؛ حکم‌های صریح گشت: post_meta روی کوپن (DiscountManager) و محصول (KitchenTicketRepository) زیر HPOS مشروع است — فقط shop_order/refund مهاجرت می‌کنند. یافتهٔ واقعی: دو assertion شکستهٔ هرگز‌اجرا‌نشده در test-price-calculator (float بی‌معنای ۲۵۰۰۰۰۱ و انتظارِ U+066C از مبدل digits-only) اصلاح شد. گیت tests/theme/test-hpos-compat.php (۲۹ assertion، globِ CI) با اجرای زندهٔ هر دو تستِ نام‌بردهٔ پرامپت: price-calculator با شیم WP_UnitTestCase (۵ متد/۱۱ assert) و run-e2e-integration (۸۱/۸۱، کفِ ≥۸۱) + ۴ اثبات منفی زنده. — `commit 826192f` |
| 50 | ✅ انجام شد | فایل flavor-core/tests/mock-wp-environment.php را مرور کن: mock ها نباید | وفاداری ماک تست به وردپرس واقعی: ممیزی خط‌به‌خط mock-wp-environment.php با سورس WP 6.8 (class-wpdb/meta/pluggable/formatting). اصلاح‌ها: prepare پورت وفادار (NULL→'' نه توکن برهنه، آرگومان کم→رشته خالی+warning، %f/%F، %i با backtick دوبرابر، '%s' نرمال، LIKE '%%%s%%'، %% و % داخل مقدار literal، charset واقعی mysqli)؛ insert/replace/update/delete/query خروجی int\|false با rowCount (update صفر‌ردیفی قبلاً true بود=موفقیت جعلی در هر check مثل TokenService::revoke)؛ get_var مقدار '0'/0 را نگه می‌دارد و فقط ''/بی‌سطری→null (?: قدیمی COUNT=0 را می‌بلعید)؛ last_error ثبت/پاک؛ $wpdb->options/postmeta + جداول واقعی wp_options/wp_postmeta در SQLite (مسیر اینترپولیشن SearchIndex/uninstall قبلاً روی هیچ query می‌زد)؛ ساعت سایت Asia/Tehran با current_time وفادار (offset+۱۲۶۰۰ نه time() خام — هر باگ ‎+3:30 در سوئیت خنثی می‌شد) و WC_DateTime با ->date() در منطقهٔ سایت (قرارداد placed_at)؛ هوک‌ها: add_filter واقعی با registry مشترک، apply_filters زنجیره‌ای با priority/accepted_args (قبلاً no-op سخت)، OTP listener ثبت‌شده، wpautop روی the_content؛ nonce با دستور واقعی tick\|action\|uid\|session → خروجی 1\|2\|false (قبلاً هر رشته غیرخالی=۱)؛ get_userdata/get_user_by/wc_get_order→false نه null؛ get_user_meta تکی نبود→''؛ get_posts آبجکت مگر fields=ids؛ get_bloginfo per-key؛ term-meta/object-cache فروشگاه واقعی؛ transient با انقضای واقعی (پنجره RateLimit جاودانه بود)؛ wp_mail رکورد ۵-پارامتری+seam شکست؛ wp_remote_post با پاس WP_Error (شاخه is_wp_error در WebhookManager)؛ WP_Error با data مختلط؛ REST get_header غایب→null؛ sanitizerهای پورت core (octet‌زدایی text_field، حفظ newline در textarea، sanitize_title فقط ASCII→اسلاگ فارسی صادقانه خالی، hex_color رد با NULL، esc_url_raw gate طرح)؛ مجموع سفارش/سبد از آیتم+فی واقعی (نه ثابت ۳۵۰۰۰۰)، شلیک woocommerce_checkout_create_order در create_order، فیلترهای status/date_created در wc_get_orders. یک pin فیکسچر به حقیقت تولید اصلاح شد (test-chrome-builder: sanitize_hex_color('red')=null نه ''). گیت tests/theme/test-mock-fidelity.php: ۸۷ بردار + اجرای زندهٔ e2e (۸۱/۸۱ کف ≥۸۱) و guards (۲۳/۰) + ۵ اثبات خرابکاری زنده. مرجع نهایی زیر ماک وفادار: e2e ۸۱/۸۱، guards ۲۳/۰، reporting ۸۱/۰، theme ۴۳ فایل، pure ۲۲۶/۰، phpcs ۰، lint ۳۳۴. — `commit 30bf258` |

## فاز 6 — دسترس‌پذیری (WCAG 2.1 AA)

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 51 | ✅ انجام شد | اسکن axe (بستهٔ @axe-core/playwright که در tests/ui هست) را روی صفحات | نگهبان دسترس‌پذیری واقعی: tests/ui/a11y.mjs بیست‌وهشت سطح واقعی را آفلاین اسکن می‌کند — شش قالب صفحه از فیکسچر a11y-pages.php (خانه/منو/رزرو/تماس/درباره/پیگیری) + دوازده دموهای یک‌کلیک از build-preview.sh + پنل‌های بازشدنی با JS (دراور سبد، فرم پرداخت، شیت محصول) دقیقاً با مسیری که اسکریپت‌های ارسالی باز می‌کنند — با @axe-core/playwright زیر تگ‌های wcag2a/wcag2aa/wcag21aa؛ هر serious/critical = شکست. نتیجه: ۲۸ سطح، صفر تخلف. گیت test-a11y-suite.php + سیم‌کشی CI و آرتیفکت شواهد. دو follow-up: کاتالوگ‌کردن رشتهٔ امتیاز ستاره‌ای و به‌روزرسانی ledger کاتالوگ به ۱۵۶۱/۱۵۶۱. — `commit f7225bb` |
| 52 | ✅ انجام شد | ناوبری با کیبورد: منوی اصلی/مگامنو (class-nav-mega.php)، دراور سبد | کیبورد: کل فروشگاه فقط با صفحه‌کلید قابل استفاده شد — سوئیت tests/ui/keyboard.mjs با ۴۶ بررسی (گروه‌های A-H) روی فیکسچر آفلاین keyboard-pages.php؛ مگامنو/زیرمنوها disclosure واقعی (Escape/جهت‌نماها/Home/End، aria-controls، پرچم یک‌بارمصرف Escape تا refocus پنل را دوباره باز نکند، پنل بسته بیرون از دید و ترتیب Tab)؛ رفع سایه‌زدنی var price در paintSheetPrice (باگ پرامپت ۲۲ که شیت محصول را در فروشگاه زنده با TypeError می‌کشت)؛ کپی کشوی موبایل از منو ویژگی‌های disclosure را می‌گیرد؛ wishlist/quickview روی flavor:dialog-closed aria-expanded را ریست می‌کنند؛ صدور ui.dialogCount برای هماهنگی Escape کشو+دیالوگ؛ نشانگر html.flavor-js inline پیش از همهٔ اسکریپت‌ها. گیت test-keyboard-suite.php + سیم‌کشی CI و آرتیفکت شواهد. باتری: keyboard ۴۶/۴۶، a11y ۲۸/۰، theme ۴۵ فایل، e2e ۸۱/۸۱. — `commit ef4a283` |
| 53 | ✅ انجام شد | فرم‌ها: فرم رزرو (reservation-form.php)، فرم ورود/OTP (ui/login.php)، | دسترس‌پذیری فرم‌ها: چهار فرم روبهٔ مشتری (رزرو، ورود/OTP، پرداختِ کشوی سبد، تماس) قرارداد یکسان گرفتند — خطای متنیِ راه‌حل‌دار روی خود فیلد (.flavor-ui-field__err از طریق FlavorUI.fieldError/fieldClear با aria-describedby + aria-invalid؛ ویرایش فیلد خطا را فوراً پاک می‌کند)؛ پیام واحد «شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود؛ مانند ۰۹۱۲۱۲۳۴۵۶۷.» با یک msgid به هر چهار فرم خدمت می‌کند (جایگزین دو پیام مبهم قبلی)؛ auth.js ناحیهٔ زنده را هنگام خطا assertive می‌کند و خطای کد بازهٔ ۴-۶ رقم را می‌گوید؛ reservation.js از دفتر بدهی ترجمه فارغ‌التحصیل شد (Enqueue::reservation_strings در payload + اعتبارسنجی سمت مشتری: بازهٔ مهمان ۱-۲۰، نام، قالب موبایل) و ledger در test-js-i18n همان کامیت کوچک شد؛ فرم تماس — نخستین فرم ارسال‌کنندهٔ پوسته: Flavor\Contact_Form نخستین مسیر REST پوسته (flavor/v1/contact) را با الگوی nonce مسیرهای Core ثبت می‌کند + honeypot + محدودسازی نرخ (۳ پیام/۱۰ دقیقه/IP-hash) + پاک‌سازی کامل + دروازهٔ ایمیل مجموعه (۵۰۳ صریح) + Reply-To و جایگزین noscript؛ رفع باگ خام «در حال دریافت سبد…» در cart-drawer؛ CSS خطای فیلد/hint/حاشیهٔ خطرِ aria-invalid/honeypot در ui.css و هر دو بلوک dark-mode. i18n: ۴۲ msgid تازه (ar-part15.py از diffِ POT) + حذف ۳ کلید کهنه از part6/part8/part99؛ ۱۶۰۰/۱۶۰۰ (۱۰۰٪)؛ ledger ادعاها به‌روز (test-claims سبز). گیت tests/theme/test-forms-a11y.php: ۷۵ بررسی — مارکاپ چهار فرم، قراردادهای JS و اجرای واقعی اندپوینت تماس روی ماک (nonce/honeypot/نرخ/اعتبارسنجی/wp_mail/503)؛ سه خرابکاری زنده اثبات و بازگردانی شد. follow-up 99e026c: گیت HPOS نامِ بررسیِ شکست‌خوردهٔ e2e را چاپ می‌کند (یک flake تک‌بار ۸۰/۸۱ فقط روی CI/8.2 زیر بار؛ محلی ۵/۵ و بقیهٔ ماتریس ۸۱/۸۱ — در تکرار بعدی CI همهٔ ماتریس سبز). — `commit 1a051df` |
| 54 | ✅ انجام شد | کنتراست و تم‌ها: تست test-contrast.php را اجرا کن و سپس هر ۱۲ پوسته | پرامپت ۵۴ (کنتراست و تم‌ها): هر ۱۲ پوسته در هر دو طرح روز و شب به ≥۴٫۵:۱ برای متن (ink/muted)، لینک (primary) روی هر سه بوم (bg/surface/surface_alt) و متن دکمه روی primary رسیدند. شش هکز روز با همهٔ بازتاب‌های rgb و فایل‌های آینه اصلاح شد (primary مدرن #a44c2a، کافه #964f30 و muted #716357، فست‌فود #cd2a14، پیتزا muted #726357، نانوایی muted #776369)؛ Dark_Mode::palette سطح شب را روی surface_alt هم تضمین می‌کند؛ باگ ریشه‌ای: ensure() هنگام عبورِ بدون تنظیم، hex بی‌علامت برمی‌گرداند و هر إعلانِ مصرف‌کنندهٔ --flavor-ink/--flavor-primary/--flavor-muted/--flavor-accent شب را در computed-value باطل می‌کرد — اکنون همیشه #hex معتبر. سه جفت لینک تازه در Contrast::pairs و گزارش کاستومایزر. گیت: test-contrast ۱۵۵/۰ (ماتریس ۲۴ بررسیِ دو-طرح + ۲۴ بررسی صحت hex)؛ خرابکاری‌های زنده: بازگردانی رنگ کافه → ۲ FAIL، حذف تضمین primary → ۷ FAIL، بازگردانی اصلاح ensure → ۲۴ FAIL + ۲۱ خطای elementor-visual — همه بازگردانده و سبز. fixture صفحه‌های a11y اکنون لایهٔ flavor-ui و متغیرهای UI را مثل سایت واقعی می‌گیرد؛ لینک breadcrumb زیرخط گرفت (WCAG 1.4.1 — axe link-in-text-block). elementor-visual ۳۰/۳۰، a11y سطح ۲۸ پاک، keyboard ۴۶/۴۶، motion ۱۱/۱۱. i18n: ۳ msgid تازه (عنوان جفت‌های لینک)، عربی ۱۶۰۳/۱۶۰۳ (۱۰۰٪) با ar-part16.py، msgfmt و check-i18n سبز، دفتر ادعاها ۱۶۰۳ (test-claims سبز). باتری کامل سبز (theme ۴۶ فایل، pure ۲۲۶، e2e ۸۱، branch-scope ۲۳، versions/wiring/lint/phpcs/typos). — `commit e760ff6` |
| 55 | ✅ انجام شد | ARIA برای اجزای پویا: کارت‌های منو (افزودن به سبد)، شمارنده‌های سبد، | پرامپت ۵۵ (ARIA برای اجزای پویا): تراشه‌های دستهٔ منو از دکمه‌های aria-pressed به الگوی کامل WAI-ARIA tabs رفتند — role=tablist/tab روی ریل چسبان، aria-selected، tabindex چرخان (یک توقف به‌جای N)، aria-controls، و grid منو به role=tabpanel با aria-labelledbyِ تبِ فعال؛ کلیدهای جهت با جهت‌گیری وارونهٔ RTL + Home/End و فعال‌سازی خودکار — با پروب زندهٔ Chromium روی fixture منو اثبات شد (۴ تب: جهت/End/جهت/Home همه درست). نام دسترس‌پذیر لینک سبد هدر حالا شمار قلم‌ها را می‌برد (sprintf سمت سرور + همگام‌سازی JS نشان و aria-label پس از هر رفرش؛ اعلام تغییرات سبد همان ui.announce موجود بدون ناحیهٔ موازی). داک شناور role=group با برچسب گرفت. ۲۷ SVG تزئینی (۳ آیکون اجتماعی footer + ۲۴ در هشت template-part بازاریابی، شامل ستاره‌های داخل role=img) aria-hidden و focusable=false گرفتند. میزبان نتیجهٔ پیگیری سفارش aria-live=polite گرفت (کنار role=region و focus و تایم‌لاین aria-current موجود). الگوهای فیلتر/تاگل موجود (نمای کارت/فهرست، حالت سرویس در fieldset، فیلتر شهر، علاقه‌مندی، تاگل شب) در گیت پین شدند. گیت tests/theme/test-aria-dynamic.php: ۵۴ بررسی + اسکن هر <svg> در لایه‌های PHP و JS؛ دو خرابکاری زنده (بازگردانی role=tab → ۳ FAIL، حذف یک aria-hidden → FAIL با نام فایل) اثبات و بازگردانی شد. i18n: ۲ msgid تازه (نام لینک سبد با شمار + برچسب داک)، عربی ۱۶۰۵/۱۶۰۵ (۱۰۰٪) با ar-part17.py، msgfmt و check-i18n سبز، دفتر ادعاها ۱۶۰۵ (test-claims سبز). باتری کامل سبز (theme ۴۷ فایل، pure ۲۲۶، e2e ۸۱، branch-scope ۲۳، lint ۳۴۱، phpcs ۰، versions/wiring)؛ مرورگر: a11y سطح ۲۸ پاک، keyboard ۴۶/۴۶، motion ۱۱/۱۱، elementor-visual ۳۰/۳۰. — `commit 97ffa94` |
| 56 | ⬜ انجام‌نشده | prefers-reduced-motion و prefers-contrast: مطمئن شو لایهٔ حرکت (motion.css |  |

## فاز 7 — رابط کاربری، RTL و حالت شب

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 57 | ⬜ انجام‌نشده | پس از پرامپت ۱۳ (پشتیبانی LTR)، یک گشت RTL/LTR دوقلو بزن: همهٔ صفحات |  |
| 58 | ⬜ انجام‌نشده | حالت شب (class-dark-mode.php و dark-mode.css) را صفحه‌به‌صفحه بسنج: |  |
| 59 | ⬜ انجام‌نشده | حالت‌های خالی و خطا: برای هر نمای داده‌محور (لیست منو، سبد خالی، |  |
| 60 | ⬜ انجام‌نشده | لودینگ‌ها: اسکلتون/شیمر برای کارت‌های منو (همان‌طور که موبایل دارد) |  |
| 61 | ⬜ انجام‌نشده | ریسپانسیو نهایی: در عرض‌های 320، 360، 390، 430، 768، 1024، 1440 |  |
| 62 | ⬜ انجام‌نشده | چاپ رسید (print-receipt.css و ReceiptRenderer) را روی یک نمونه سفارش |  |
| 63 | ⬜ انجام‌نشده | PWA (class-pwa.php): مانیفست را بازبینی کن (نام، آیکون‌ها 192/512، |  |

## فاز 8 — اپ موبایل Flutter

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 64 | ⬜ انجام‌نشده | flutter analyze را بدون هشدار کن: هر warning/info باقی‌مانده در |  |
| 65 | ⬜ انجام‌نشده | مدل‌ها و سریالایز: mobile/lib/models/*.dart را با پاسخ‌های واقعی REST |  |
| 66 | ⬜ انجام‌نشده | حالت آفلاین و خطا در اپ: هر صفحه (home، menu، cart، checkout، orders، |  |
| 67 | ⬜ انجام‌نشده | مسیر checkout در اپ (checkout_screen.dart و cart_state.dart) را با |  |
| 68 | ⬜ انجام‌نشده | deep link ها (core/navigation/deep_link_service.dart + قالب‌های |  |
| 69 | ⬜ انجام‌نشده | اعلان‌ها (notification_service.dart و firebase_gateway.dart): ساختار |  |
| 70 | ⬜ انجام‌نشده | اسکرین‌های پروفایل/آدرس (addresses_screen.dart، edit_profile_screen.dart): |  |
| 71 | ⬜ انجام‌نشده | پکیج‌بندی برند (scripts/provision_brand.py + test_provision_brand.py): |  |
| 72 | ⬜ انجام‌نشده | workflow موبایل (build-mobile.yml) را خشک‌کن: در بخش iOS مطمئن شو |  |

## فاز 9 — پرفورمنس، SEO و محتوا

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 73 | ⬜ انجام‌نشده | تصاویر: همهٔ تصاویر demos/ باید بهینه (کیفیت ۸۰-۸۵، progressive JPEG) |  |
| 74 | ⬜ انجام‌نشده | بارگذاری دارایی: مطمئن شو (۱) هیچ CSS/JS بلااستفاده‌ای در صفحات |  |
| 75 | ⬜ انجام‌نشده | SEO پایه: (۱) title و meta description یکتا برای هر صفحه/پست/محصول |  |
| 76 | ⬜ انجام‌نشده | Structured data: کلاس Schema_Output را بررسی کن: نام/آدرس/ساعت/تلفن |  |
| 77 | ⬜ انجام‌نشده | کش و هدرها: کلاس Support/CacheHints را مرور کن: بسته‌های static |  |
| 78 | ⬜ انجام‌نشده | داشبورد آشپزخانه (kitchen-dashboard.php/js) را برای تبلت بهینه کن: |  |
| 79 | ⬜ انجام‌نشده | جست‌وجوی هوشمند (SmartSearch + RestSearch + search.js) را بسنج: |  |
| 80 | ⬜ انجام‌نشده | محتوای دموها: متن هر ۱۲ دمو را از نظر واقع‌گرایی مرور کن: قیمت‌ها |  |

## فاز 10 — تست‌ها و CI

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 81 | ⬜ انجام‌نشده | تست‌های مرورگری Playwright (tests/ui/*.mjs: customer.mjs، customizer.mjs، |  |
| 82 | ⬜ انجام‌نشده | گام Elementor واقعی در CI را تثبیت کن (طبق R3-04 که انجام شده): مطمئن |  |
| 83 | ⬜ انجام‌نشده | پوشش تست موبایل در CI: گام flutter-mobile-ci را گسترش بده تا |  |
| 84 | ⬜ انجام‌نشده | workflow ها را سخت‌تر کن: در ci.yml و build-mobile.yml و |  |
| 85 | ⬜ انجام‌نشده | یک workflow «release-check» بساز که روی tag های v* اجرا شود: (۱) همهٔ |  |

## فاز 11 — مستندات، فروش و تجربهٔ خریدار

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 86 | ⬜ انجام‌نشده | README.md را بازبینی کامل کن: نسخه‌ها با check-versions یکی باشند، |  |
| 87 | ⬜ انجام‌نشده | مستندات فارسی docs/fa/ را صفحه‌به‌صفحه با محصول امروز تطبیق بده: |  |
| 88 | ⬜ انجام‌نشده | راهنمای نصب خریدار را بنویس: docs/fa/01-nasb.md باید یک مسیر «نصب |  |
| 89 | ⬜ انجام‌نشده | docs/fa/03-bahrebardari.md (بهره‌برداری) را تکمیل کن: چرخهٔ روزانهٔ |  |
| 90 | ⬜ انجام‌نشده | فایل CHANGELOG.md را برای انتشار آماده کن: بخش [Unreleased] را به |  |
| 91 | ⬜ انجام‌نشده | readme.txt های وردپرس (flavor/readme.txt و flavor-core/readme.txt) را |  |
| 92 | ⬜ انجام‌نشده | اسکرین‌شات‌های فروش: برای هر ۱۲ دمو یک اسکرین‌شات دسکتاپ ۱۴۴۰×۹۰۰ |  |
| 93 | ⬜ انجام‌نشده | رسانهٔ بازاریابی: پوشهٔ media-kit/ بساز شامل: لوگو (اگر نیست — |  |
| 94 | ⬜ انجام‌نشده | ویدیوی نصب (جایگزین R3-07): چون ضبط ویدیو در این محیط ممکن نیست، |  |
| 95 | ⬜ انجام‌نشده | سند پشتیبانی و ضمانت: docs/SUPPORT.md بنویس: محدودهٔ پشتیبانی (۶ ماه |  |
| 96 | ⬜ انجام‌نشده | SECURITY.md را به‌روز کن: نحوهٔ گزارش آسیب‌پذیری (ایمیل)، زمان‌بندی |  |
| 97 | ⬜ انجام‌نشده | سند قیمت‌گذاری و بسته‌های فروش: docs/PRICING.md بنویس بر اساس |  |
| 98 | ⬜ انجام‌نشده | تجربهٔ اولین اجرا (onboarding): کلاس Onboarding و Launch_Center را |  |
| 99 | ⬜ انجام‌نشده | حذف یادداشت‌های داخلی توسعه از بستهٔ فروش: در build-distribution.sh |  |
| 100 | ⬜ انجام‌نشده | لایسنس: مطمئن شو در هر دو بستهٔ نهایی، فایل LICENSE (GPL-2.0-or-later) |  |

## فاز 12 — بازبینی نهایی و انتشار

| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |
|---:|---|---|---|
| 101 | ⬜ انجام‌نشده | گشت ادغام‌شکنی: کل run-theme-tests.sh را دوباره اجرا کن، سپس همهٔ |  |
| 102 | ⬜ انجام‌نشده | اجرای واقعی وردپرس: طبق الگوی ci.yml، یک وردپرس تازه با PHP 8.3، |  |
| 103 | ⬜ انجام‌نشده | اجرای امنیتی نهایی: تست‌های test-idor-security.php، test-rate-limit.php |  |
| 104 | ⬜ انجام‌نشده | تست نصب تمیز: دو بستهٔ نهایی (flavor.zip و flavor-core.zip خروجی |  |
| 105 | ⬜ انجام‌نشده | نسخهٔ نهایی را قفل کن: نسخهٔ قالب و هسته را (در صورت تغییرات این دوره) |  |
| 106 | ⬜ انجام‌نشده | چک‌لیست نهایی فروش را مهر بزن: docs/raastichin/listing.md چک‌لیست |  |
| 107 | ⬜ انجام‌نشده | شبیه‌سازی خریدار تازه‌کار: نقش یک کاربر غیرفنی را بازی کن که فقط |  |
| 108 | ⬜ انجام‌نشده | آرشیو نهایی: سه فایل flavor.zip، flavor-core.zip و |  |
