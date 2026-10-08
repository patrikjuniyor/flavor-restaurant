# ماموریت ممیزی مدیریت راه‌اندازی و سفارشی‌سازی Flavor در برابر پوسته‌های پولی

**تاریخ ممیزی:** ۲۰۲۶/۱۰/۰۸ — ۱۴۰۵/۰۷/۱۶  
**خط مبنا:** پوستهٔ Flavor نسخهٔ ۱.۵.۰، هستهٔ Flavor Core نسخهٔ ۱.۵.۱، commit `a287d01`  
**وضعیت ماموریت:** ممیزی و بک‌لاگ اولویت‌بندی‌شده انجام شد؛ این سند عمداً «لیست تفاوت‌ها و نواقص» است و به‌جای ادعای رفع آن‌ها، معیار پذیرش هر کار را ثبت می‌کند.

> این سند ادامهٔ [ممیزی هم‌ترازی عمومی با پوسته‌های پولی](PREMIUM-AUDIT-2026-10-08.md) و [ماموریت‌های هم‌ترازی ۱.۵.۰](PREMIUM-PARITY-2026-10-08.md) است. تمرکز این دور فقط بر دو سطحی است که صاحب رستوران در پیشخوان می‌بیند: **راه‌اندازی/مدیریت اولیه** و **سفارشی‌سازی ظاهر**.

---

## ۱. خلاصهٔ اجرایی

### چیزی که Flavor همین حالا بهتر یا هم‌سطح ارائه می‌کند

- دوازده preset راست‌چین و دوازده بستهٔ دموی محلی، بدون وابستگی به سرویس خارجی.
- سفارش سالن با QR، بیرون‌بر، ارسال، شعبه، میز، KDS آشپزخانه، رزرو شمسی، OTP، منطقهٔ ارسال، سفارش تلفنی و باشگاه مشتریان در هستهٔ بومی؛ این‌ها از بسیاری از پوسته‌های صرفاً ظاهری ThemeForest عمیق‌ترند.
- درون‌ریزی یک‌کلیکی صفحات، محصولات، تصاویر، دسته‌ها، شعبه، ساعت کاری و میزهای دمو.
- پنل Customizer فارسی با پالت رنگ، تایپوگرافی محلی، هدر، هیرو، سکشن‌های صفحهٔ نخست، گالری و لایهٔ فروش‌محور (حالت شب، علاقه‌مندی، مگامنو، Quick View، نوار ارسال رایگان و نشان اعتماد).
- یک Builder داخلی drag-and-drop برای محتوای صفحه و پنج ویجت اختصاصی Elementor؛ منطق سفارش در افزونه می‌ماند و با تعویض قالب از بین نمی‌رود.
- PWA، متاتگ رنگ برند، چایلدتم رسمی، RTL-first و کنترل‌های دسترس‌پذیری/reduced-motion.

### فاصلهٔ اصلی با تجربهٔ «پریمیوم»

1. **راه‌اندازی یکپارچه نیست:** ویزارد ۱۰ مرحله‌ای، درون‌ریز دمو و تنظیمات Flavor Core سه نقطهٔ جدا هستند؛ کاربر در پایان ویزارد الزاماً سایت آمادهٔ سفارش‌گیری ندارد.
2. **یک نقص واقعی در ویزارد وجود دارد:** چند فیلدی که در UI از کاربر گرفته می‌شوند در `handle_submit()` اصلاً ذخیره نمی‌شوند؛ بنابراین ادعای «راه‌اندازی کامل» در حال حاضر دقیق نیست.
3. **ایمنی و قابلیت بازگشت درون‌ریزی کافی نیست:** دموهای قبلی پاک می‌شوند، اما dry-run، پشتیبان، rollback، انتخاب اجزای import و نوار پیشرفت وجود ندارد.
4. **عمق طراحی از صفحهٔ تنظیمات پوسته‌های پولی کمتر است:** کنترل‌های per-device، تایپوگرافی جزئی، Header/Footer Builder، الگوهای آماده، گزینه‌های page-level و export/import تنظیمات کم هستند.
5. **Builder داخلی، ویرایشگر بصری زنده نیست:** در پیشخوان بلوک‌ها را می‌چیند، اما preview واقعی و هم‌زمانِ صفحهٔ نهایی مثل Elementor/Theme Builderهای رایج ندارد.

### اولویت فوری

| اولویت | تعداد | نتیجهٔ مورد انتظار |
|---|---:|---|
| **P0 — پیش از فروش عمومی** | ۵ | ویزارد باید واقعاً داده‌ها را ذخیره کند، قبل از launch سلامت سایت را بررسی کند و import قابل بازگشت باشد. |
| **P1 — هم‌ترازی با تجربهٔ پریمیوم** | ۱۰ | export/import، پیش‌نمایش زندهٔ کامل، کنترل‌های responsive، قالب‌ساز هدر/فوتر و کتابخانهٔ سکشن اضافه شود. |
| **P2 — مزیت رقابتی** | ۶ | اتصال بازاریابی، چندزبانه، پشتیبانی درون‌پنل، گزارش‌گیری قابل خروجی و قابلیت‌های چندفروشندگی تکمیل شود. |

---

## ۲. روش و معیار مقایسه

### شواهد داخل مخزن

- ویزارد: `flavor/inc/class-onboarding.php`
- درون‌ریز: `flavor/inc/class-demo-importer.php` و `flavor/inc/demo-catalog.php`
- سفارشی‌ساز پایه: `flavor/inc/class-customizer.php`
- کنترل‌های UI و اعتبارسنجی انتخاب‌ها: `flavor/inc/class-ui-customizer.php`
- لایهٔ امکانات پریمیوم: `flavor/inc/class-premium-customizer.php`
- Builder داخلی: `flavor/inc/class-builder.php` و `flavor/assets/js/builder-admin.js`
- اتصال Elementor: `flavor/inc/class-elementor.php`
- مدیریت عملیات و تنظیمات: `flavor-core/includes/Admin/AdminMenus.php`
- راهنمای نصب و پیکربندی: `docs/fa/01-nasb.md` و `docs/fa/02-peykar-bandi.md`

### نمونه‌های بازار

صفحات فروش زیر به‌عنوان **ادعای قابلیت از طرف سازندهٔ محصول** بررسی شده‌اند؛ بعضی قابلیت‌ها به Elementor، افزونهٔ رزرو، Slider Revolution، فرم‌ساز یا افزونه‌های همراه وابسته‌اند. بنابراین این‌ها معیار تجربهٔ کاربر خریدار هستند، نه تست مستقل همهٔ دموها.

| محصول مرجع | چیزی که در صفحهٔ فروش برای راه‌اندازی/سفارشی‌سازی برجسته شده است |
|---|---|
| [Tastyc](https://themeforest.net/item/tastyc-restaurant-wordpress-theme/32386289) | نصب یک‌کلیکی دمو، چند دمو، حالت روشن/تیره، RTL، WooCommerce، تقویم رزرو و Header/Footer Builder در Elementor، به‌همراه ۳۵+ ویجت اختصاصی. |
| [Grand Restaurant](https://themeforest.net/item/grand-restaurant-restaurant-cafe-theme/11812117) | درون‌ریزی یک‌کلیکی صفحات/نوشته‌ها/ویجت‌ها/تنظیمات، Customizer با preview واکنش‌گرا، چند نوع هدر، منوهای متعدد، مگامنو، بک‌گراند و تنظیمات ذخیره‌شدنی. |
| [Delicioz](https://themeforest.net/item/delicioz-restaurant-wordpress-theme/39339156) | چند دمو، Elementor، Header/Footer Builder، sidebarهای قابل ساخت، Quick View، جست‌وجو/فیلتر فروشگاه و گزینه‌های گستردهٔ layout/style. |
| [Dine](https://themeforest.net/item/dine-elegant-restaurant-theme/19489643) | وعدهٔ راه‌اندازی پنج‌دقیقه‌ای با one-click import، Elementor، منوی غذای بومی، رزرو self-hosted و هدر sticky/transparent. |
| [Restaurant Food / nicdark](https://themeforest.net/item/ristorante-restaurant-wordpress-theme/23195638) | Custom Admin Panel، Live Customizer، مدیریت رزرو، Elementor، Footer Builder و **backup/import/export تنظیمات پوسته**. |
| [FoodBakery](https://themeforest.net/item/food-bakery-restaurant-bakery-responsive-wordpress-theme/18970331) | داشبورد جدا برای مدیر و رستوران، چندرستورانی/merchant، menu builder drag-and-drop، سفارش، رزرو، درآمد، برداشت و گزارش. این مورد برای مدل چندفروشندگی است و همهٔ آن الزاماً هدف Flavor نیست. |

### راهنمای وضعیت

- **✅ هم‌سطح/برتر:** قابلیت کاربردی وجود دارد یا Flavor در منطق رستورانی مزیت دارد.
- **◑ ناقص:** بخشی از تجربه وجود دارد، اما سطح کنترل یا یکپارچگی پایین‌تر است.
- **❌ شکاف:** قابلیت مورد انتظار وجود ندارد.
- **⚠️ نقص/ریسک واقعی:** از خواندن کد یا جریان فعلی قابل اثبات است، نه صرفاً مقایسهٔ بازاری.
- **N/A:** تفاوت مدل محصول است؛ فقط وقتی باید اجرا شود که Flavor عمداً به marketplace/multi-vendor تبدیل شود.

---

## ۳. ممیزی بخش مدیریت و راه‌اندازی

| شناسه | قابلیت/تجربهٔ مورد انتظار | وضعیت فعلی Flavor و شاهد کد | وضعیت | اولویت / معیار پذیرش |
|---|---|---|---|---|
| **A-01** | انتخاب دمو با preview و نصب یک‌کلیکی | `Demo_Importer` دمو را از `flavor_demo_catalog()` می‌خواند و صفحات/محصولات/رسانه/شعبه/ساعت/میز می‌سازد. دوازده بستهٔ دمو موجود است. | ✅ | حفظ شود؛ برای هر کارت لینک preview زنده، تعداد اجزا و زمان تقریبی import اضافه شود. |
| **A-02** | ویزارد راه‌اندازی | `Onboarding` ویزارد ۱۰ مرحله‌ای دارد، اما کل فرم فقط در پایان با یک POST ذخیره می‌شود؛ draft و resume وجود ندارد. گام‌ها با کلیک قابل پرش‌اند و فقط نام سایت `required` است. | ◑ | **M-01/M-02:** ذخیرهٔ مرحله‌ای، ادامه از آخرین گام، validation واقعی و صفحهٔ خلاصه پیش از publish. |
| **A-03** | یک مسیر واحد از نصب تا launch | ویزارد در `themes.php?page=flavor-setup`، دموها در `themes.php?page=flavor-demos` و تنظیمات عملیاتی در منوی جداگانهٔ Flavor Core هستند. انتخاب skin در ویزارد، import دمو را اجرا نمی‌کند. | ❌ | **M-02:** wizard باید «انتخاب دمو → import → تنظیمات core → تست → launch» را با لینک و وضعیت مرحله‌ای به هم وصل کند. |
| **A-04** | درستی ذخیرهٔ اطلاعات راه‌اندازی | UI فیلدهای `hero_image_url`، `opening_hours`، `mode_dine_in`، `mode_takeaway`، `mode_delivery` و `city` را می‌گیرد؛ `handle_submit()` آن‌ها را نمی‌خواند. `hero_image_url` به `flavor_hero_image` تبدیل نمی‌شود و ساعت/حالت سفارش/شهر در Branch ذخیره نمی‌شوند. | ⚠️ | **P0 / M-01:** برای هر field تست submit اضافه شود؛ تصویر به attachment ID/URL معتبر، ساعت به جدول branch hours، شهر به متای شعبه و modeها به `_flavor_order_modes` برسند. |
| **A-05** | نصب و بررسی dependencyها | Flavor Core برای WooCommerce تلاش به نصب/فعال‌سازی دارد؛ اما ویزارد صفحهٔ وضعیت برای PHP/WP/WooCommerce، افزونهٔ درگاه، SMS، ایمیل، Elementor/فرم/رزرو و مجوزهای filesystem ندارد. | ◑ | **M-03:** dependency checker با وضعیت سبز/زرد/قرمز و action مستقیم؛ ادعای «در کمتر از ۲ دقیقه» فقط پس از سبز شدن checkها نمایش داده شود. |
| **A-06** | تنظیم خودکار WooCommerce و پیوندهای یکتا | راهنمای فارسی ذخیرهٔ دستی permalinks، ارز، درگاه و پیامک را لازم می‌داند؛ ویزارد این موارد را verify یا configure نمی‌کند. | ❌ | **M-02:** check برای permalink، صفحات Woo، currency storage/display، timezone و gateway؛ لینک اصلاح یا راهنمای inline برای هر مورد. |
| **A-07** | آماده‌سازی واقعی سفارش | ویزارد حداکثر یک محصول ساده می‌سازد؛ شعبهٔ کامل، دسته/عکس/مدیفایر، منطقهٔ ارسال، میز/QR، ظرفیت رزرو، ایمیل/SMS و test order را کامل نمی‌کند. | ◑ | **M-02:** با یک launch checklist حداقل یک شعبه، یک آیتم قابل سفارش، mode فعال، QR، zone/payment و سفارش آزمایشی end-to-end بررسی شود. |
| **A-08** | گزارش و داشبورد عملیات | Flavor Core منوی مستقل برای dashboard/KPI، kitchen، reservations، phone orders، schedules، availability، loyalty، tables/QR و zones دارد؛ این از نظر عملیات از بسیاری پوسته‌های صرفاً ظاهری قوی‌تر است. | ✅/◑ | مسیرها باید از wizard و dashboard به‌صورت contextual link در دسترس باشند؛ گزارش CSV/PDF و گزارش زمان‌بندی‌شده هنوز در scope نیست. |
| **A-09** | import امن و قابل بازگشت | `Demo_Importer::cleanup()` محتوای دارای `_flavor_demo` را حذف می‌کند و رسانه‌های دمو را نیز پاک می‌کند؛ backup، dry-run، انتخاب اجزا، تأیید دومرحله‌ای و rollback ندارد. خطا در میانه ممکن است سایت را نیمه‌واردشده بگذارد. | ⚠️ | **P0 / M-04:** snapshot از theme mods/options/post IDs، import مرحله‌ای با progress، انتخاب «فقط ظاهر/فقط منو/همه»، rollback و پیام خطای قابل فهم. |
| **A-10** | پیشرفت، timeout و گزارش خطا | import با request هم‌زمان و بدون progress UI انجام می‌شود؛ گزارش موفقیت فقط بعد از redirect نشان داده می‌شود. | ❌ | **M-04:** jobهای قابل ادامه با AJAX/REST، درصد مرحله، log قابل مشاهده و retry برای assetهای ناموفق. |
| **A-11** | حفظ محتوای مشتری هنگام تعویض دمو | cleanup فقط برچسب `_flavor_demo` را هدف می‌گیرد، که تصمیم خوبی است؛ اما انتخاب کاربر برای نگه‌داشتن صفحات/محصولات یا backup قابل restore وجود ندارد. | ◑ | **M-04:** پیش‌نمایش چیزهای قابل حذف، backup خودکار، گزینهٔ preserve و rollback. |
| **A-12** | نقش‌ها و دسترسی تیم | هسته capabilityهای جدا برای branch/kitchen/settings/reservation/phone دارد؛ اما wizard فقط `manage_options` می‌خواهد و دعوت پرسنل/اختصاص شعبه از راه‌اندازی قابل انجام نیست. | ◑ | **M-05:** ساخت roleهای آماده، دعوت پرسنل، اختصاص branch و لینک ورود به KDS با حداقل دسترسی. |
| **A-13** | backup/export تنظیمات | در مسیرهای فعلی export/import تنظیمات theme mods و `flavor_core_settings` وجود ندارد؛ این در محصولاتی مثل nicdark صریحاً به‌عنوان feature مطرح شده است. | ❌ | **M-06:** فایل JSON نسخه‌دار برای skin، theme mods، تنظیمات Core و منو؛ secretها/کلیدها export نشوند؛ import با preview و rollback. |
| **A-14** | آموزش درون‌پنل | راهنمای فارسی موجود است، ولی wizard در هر مرحله لینک «راهنما/ویدئو/مشکل‌یابی» و توضیح context-sensitive ندارد. | ◑ | **P2 / M-15:** help center، لینک مستندات همان field و checklist قابل چاپ. |
| **A-15** | چندرستورانی/merchant dashboard | Flavor شعب و نقش‌های عملیاتی دارد، اما FoodBakery داشبورد مستقل merchant، membership/commission، withdrawal و گزارش هر رستوران را هدف می‌گیرد. | N/A | فقط در صورت تصمیم محصول برای marketplace اجرا شود؛ برای مدل single-brand/multi-branch فعلی، نقص محسوب نمی‌شود. |
| **A-16** | update/support/license center | در هستهٔ فعلی صفحهٔ اتصال license، changelog، rollback نسخه و تیکت پشتیبانی دیده نمی‌شود؛ پوسته‌های پولی معمولاً این تجربه را با Envato/پشتیبانی عرضه می‌کنند. | ❌ | **P2 / M-16:** برای نسخهٔ تجاری، update channel امن، changelog و لینک پشتیبانی؛ برای GPL بدون قفل‌کردن قابلیت‌های اصلی. |

### نقص‌های قطعی و قابل بازتولید در ویزارد

در `flavor/inc/class-onboarding.php`:

- UI در خطوط مربوط به گام ۳ تا ۷ `hero_image_url`، `opening_hours`، سه checkbox حالت سفارش و `city` را نشان می‌دهد.
- `handle_submit()` در بخش ذخیره، نام/شعار، لوگو، skin/رنگ، تلفن/نشانی، شبکه‌های اجتماعی و اولین محصول را می‌خواند؛ اما fieldهای بالا در آن branch وجود ندارند.
- `ensure_pages()` صفحات مشترک را می‌سازد، ولی branch، ساعت کاری، modeها، zone، QR و launch health را کامل نمی‌کند.

این مورد فقط «کمبود نسبت به پوسته‌های پولی» نیست؛ یک **ناسازگاری UI و backend** است و باید قبل از تبلیغ ویزارد به‌عنوان راه‌اندازی کامل رفع شود.

---

## ۴. ممیزی بخش سفارشی‌سازی پوسته

| شناسه | قابلیت/تجربهٔ مورد انتظار | وضعیت فعلی Flavor و شاهد کد | وضعیت | اولویت / معیار پذیرش |
|---|---|---|---|---|
| **C-01** | presetهای آمادهٔ زیاد با RTL | `Design::skins()` دوازده preset با tokenهای رنگ، radius، shadow و فونت دارد؛ درون‌ریز هم دوازده بستهٔ محتوا ارائه می‌کند. | ✅ | مزیت حفظ شود؛ کارت preset باید thumbnail، preview و تفاوت‌های قابل مشاهده داشته باشد. |
| **C-02** | رنگ‌های global و dark/light | نه نقش رنگی اصلی/ثانویه/accent/background/surface/text، بلکه حالت شب، toggle و کنترل‌های فروش‌محور نیز وجود دارد. | ✅/◑ | برای رنگ‌های دستی contrast warning و دکمهٔ reset per-token اضافه شود؛ اکنون کاربر می‌تواند ترکیب کم‌خوانا بسازد. |
| **C-03** | typography عمیق | انتخاب خانوادهٔ فونت محلی و دو نقش heading/body وجود دارد؛ اما اندازه، وزن، line-height، letter-spacing و مقیاس جداگانهٔ تیترها/متن‌ها کنترل نمی‌شود. | ◑ | **M-08:** type scale با کنترل دسکتاپ/تبلت/موبایل، وزن و line-height؛ محدود و validate‌شده تا layout نشکند. |
| **C-04** | live preview واقعی | WordPress Customizer وجود دارد و بخشی از UI با `postMessage` live است؛ بسیاری از settings با transport پیش‌فرض refresh کار می‌کنند. | ◑ | **M-07:** برای همهٔ کنترل‌های امن postMessage/selective refresh؛ وضعیت unsaved و preview شکست‌خورده باید قابل برگشت باشد. |
| **C-05** | preview تصویری preset | presetها در wizard/Customizer عمدتاً کارت متن و radio هستند؛ مقایسهٔ before/after یا preview iframe برای هر skin وجود ندارد. | ❌ | **M-07:** thumbnailهای محلی، preview در همان frame، «اعمال و بازگشت» و نشان‌دادن محتوای تحت‌تأثیر. |
| **C-06** | Header Builder | چهار layout هدر، sticky، topbar و ارتفاع لوگو وجود دارد؛ اما جابه‌جایی drag-and-drop اجزای logo/menu/search/cart/CTA و چند header با شرط صفحه وجود ندارد. | ◑ | **M-09:** header template builder با presetهای آماده و شروط home/shop/product/menu. |
| **C-07** | Footer Builder | کنترل social link و copyright وجود دارد؛ footer چندستونه، widget/column، template قابل ذخیره و footer شرطی وجود ندارد. | ❌ | **M-09:** footer builder با ۳ تا ۵ ستون، بلوک‌های داخلی و preview واکنش‌گرا. |
| **C-08** | page builder بصری | Builder داخلی در admin meta box، بلوک‌ها را با drag-and-drop مرتب می‌کند و server-side render دارد؛ `builder-admin.js` preview front-end هم‌زمان ندارد. پنج ویجت Elementor ثبت می‌شود. | ◑ | **M-10:** یا canvas زندهٔ داخلی، یا تمرکز رسمی روی Elementor Theme Builder با widgets/conditions/template kit بیشتر. |
| **C-09** | تعداد widget/section و template kit | Flavor پنج widget اختصاصی Elementor و builder داخلی دارد؛ Tastyc از ۳۵+ widget و محصولات مشابه از ده‌ها ready section/template تبلیغ می‌کنند. | ◑ | **M-10/M-11:** حداقل widgets منو، شعب، رزرو، ساعت، CTA سفارش، cart، testimonial، offers و صفحات آماده؛ هر widget با RTL/accessibility test. |
| **C-10** | کنترل responsive per-device | preview دستگاه در Customizer خود WordPress وجود دارد، اما settingهای رنگ/فاصله/اندازهٔ هر سکشن breakpoint جدا ندارند. | ❌ | **M-08:** حداقل typography, spacing, columns, visibility برای desktop/tablet/mobile. |
| **C-11** | layout و spacing عمیق | عرض container، radius، button radius، چند استایل header/hero و toggle سکشن‌ها وجود دارد؛ کنترل grid/column/gap/padding/overlay/focal point برای هر سکشن محدود است. | ◑ | **M-11:** کنترل‌های محدود و توکن‌محور per-section، نه CSS آزاد و شکننده. |
| **C-12** | تکرارشونده‌ها و reorder سکشن‌ها | گالری شش slot ثابت دارد و testimonial فقط enable می‌شود؛ repeater برای کارت‌ها، caption/alt/order و افزودن بی‌نهایت آیتم در Customizer وجود ندارد. | ❌ | **M-11:** repeater امن یا block pattern library؛ reorder، duplicate، delete و media/alt text برای هر آیتم. |
| **C-13** | hero پیشرفته | سه style، تصویر، badge، title، text و دو CTA وجود دارد؛ slider/video/background position/overlay/device art direction ندارد. | ◑ | **P1:** حداقل focal point، overlay، ارتفاع/تراز responsive و یک media mode سبک؛ video فقط با performance budget. |
| **C-14** | shop/menu/product style | Quick View، sticky add-to-cart، wishlist، filters، free-shipping و trust badges در لایهٔ premium وجود دارد؛ اما کنترل تعداد ستون کارت، نسبت تصویر، badge، sidebar، archive/single product/cart/checkout به‌صورت theme option کامل نیست. | ◑ | **M-12:** style controls برای catalog، menu، single product، cart/checkout و page-level override؛ منطق قیمت و checkout سمت سرور دست‌نخورده بماند. |
| **C-15** | page-level options | Builder محتوای page را کنترل می‌کند؛ گزینه‌های عمومی برای hide title, sidebar, transparent header, page background, hero per page و layout اختصاصی هر صفحه محدود/غایب است. | ❌ | **M-12:** meta controls امن برای page/post/product و template conditions، بدون overwrite محتوای کاربر. |
| **C-16** | ذخیره، خروجی و preset شخصی | child theme رسمی دارد، اما export/import/reset theme mods و ذخیرهٔ preset شخصی در Customizer وجود ندارد. | ❌ | **M-06:** backup نسخه‌دار، reset کنترل‌شده، export بدون secret و import با diff/preview. |
| **C-17** | رسانه و image workflow | image controlهای متعدد و fallback دمو وجود دارد؛ اما crop/focal point، alt/caption برای slotها، compression/WebP و مدیریت bulk رسانه در سطح theme workflow نیست. | ◑ | **P1:** metadata دسترس‌پذیری اجباری، srcset/WebP در صورت پشتیبانی و کنترل focal point. |
| **C-18** | نوار کناری و templateهای قابل ذخیره | در Builder عناصر محتوا وجود دارد، اما custom sidebar generator و تخصیص sidebar به هر صفحه مانند برخی محصولات پولی ارائه نشده است. | ❌ | **P1:** sidebar/template library فقط اگر با block editor و WooCommerce تداخل ایجاد نکند. |
| **C-19** | integrationهای marketing/design | Flavor newsletter محلی، cookie consent، countdown و popup دارد؛ اتصال native به Mailchimp/CRM، Contact Form 7، Events Calendar، slider premium یا analytics marketing کامل ندارد. | ◑ | **P2 / M-13:** adapterهای اختیاری و privacy-aware، نه وابستگی اجباری به سرویس پولی. |
| **C-20** | چندزبانهٔ واقعی | RTL و ترجمه‌پذیری فارسی نقطهٔ قوت است؛ اما WPML/TranslatePress و محتوای چندزبانهٔ منو/صفحه در V1 هدف اعلام‌شده نیست. | ◑ | **P2 / M-14:** تصمیم محصول روشن: فارسی‌محور بماند یا integration رسمی چندزبانه با تست قیمت/رزرو. |

---

## ۵. نقشهٔ مأموریت اجرایی

### فاز P0 — درست‌کردن اعتماد به launch

| ID | ماموریت | خروجی | معیار پذیرش |
|---|---|---|---|
| **M-01** | اصلاح قرارداد ویزارد | save همهٔ fieldها، validation سرور، attachment/branch mapping | ارسال fixture شامل لوگو، هیرو، شهر، ساعت و modeها؛ همه در صفحه/branch/Customizer قابل مشاهده باشند؛ هیچ field نمایشی silently drop نشود. |
| **M-02** | Launch Center یکپارچه | وضعیت مرحله‌ای نصب، دمو، Core، WooCommerce و تست | برای سایت تازه، چک‌های dependency، permalink، page mapping، currency، branch، menu، QR، zone، gateway/SMS/email و test order سبز یا actionable باشند. |
| **M-03** | draft/resume و validation | ذخیرهٔ امنٔ draft و ادامهٔ wizard | reload یا خروج از پیشخوان دادهٔ ثبت‌شده را از بین نبرد؛ step ناقص اجازهٔ launch ندهد؛ nonce/capability در هر save بررسی شود. |
| **M-04** | importer امن | dry-run، انتخاب اجزا، snapshot، progress، rollback | import شکست‌خورده سایت را به وضعیت قبل برگرداند؛ کاربر قبل از حذف محتوا فهرست دقیق objects را ببیند؛ retry asset وجود داشته باشد. |
| **M-05** | آماده‌سازی تیم و نقش | role/capability/branch assignment | مدیر بتواند پرسنل آشپزخانه/رزرو/شعبه را با حداقل دسترسی آماده کند؛ wizard فقط `manage_options` را به‌عنوان راه‌حل همه‌چیز فرض نکند. |

### فاز P1 — بستن شکاف تجربهٔ پوسته‌های پولی

| ID | ماموریت | خروجی | معیار پذیرش |
|---|---|---|---|
| **M-06** | backup/export/import | JSON نسخه‌دار برای theme/core settings | secret و OTP/API key خروجی نشود؛ diff، تأیید و rollback قبل از import وجود داشته باشد. |
| **M-07** | preview preset و postMessage | thumbnail/preview و preview زندهٔ تنظیمات امن | تغییر رنگ/فونت/هدر/سکشن بدون refresh دیده شود؛ اعمال skin قابل undo باشد و محتوای منو سفارش‌ها تغییر نکند. |
| **M-08** | responsive typography/layout | type scale و spacing/visibility per-device | سه breakpoint، contrast check و clamp مقادیر؛ تنظیم موبایل نباید desktop را ناخواسته خراب کند. |
| **M-09** | Header/Footer Builder | templateهای drag-and-drop و شرط نمایش | حداقل header/footerهای آماده، preview واقعی، fallback قابل دسترس و سازگاری با menu/cart/mobile nav. |
| **M-10** | widget/template kit | widgetهای رستورانی بیشتر یا integration کامل Elementor | منو، رزرو، branch, hours, CTA, offer, cart و testimonial به‌صورت widget قابل استفاده باشند؛ هرکدام RTL و keyboard test داشته باشند. |
| **M-11** | سکشن‌های تکرارشونده | repeater/block patterns | add/reorder/duplicate/delete، media library، alt text، fallback و ترجمه برای gallery/testimonial/features. |
| **M-12** | Woo/page-level controls | archive/product/cart/checkout/page templates | کنترل ظاهر باشد، نه محاسبهٔ مالی؛ مبلغ نهایی، stock، shipping و authorization فقط server-side بماند. |

### فاز P2 — تمایز تجاری

| ID | ماموریت | خروجی | معیار پذیرش |
|---|---|---|---|
| **M-13** | marketing adapters | Mailchimp/CRM/forms/events به‌صورت اختیاری | consent gate، خاموش‌کردن کامل integration، no third-party request پیش‌فرض و ثبت خطای قابل فهم. |
| **M-14** | multilingual contract | WPML/TranslatePress یا اعلام رسمی فارسی‌محور | منو، قیمت، reservation labels، schema و admin strings در حد انتخاب محصول تست شوند. |
| **M-15** | help center درون‌پنل | راهنما، ویدئو/اسکرین‌شات، troubleshooting و لینک docs | هر check/error به راهنمای همان اقدام برسد؛ setup checklist قابل چاپ باشد. |
| **M-16** | update/support center | changelog، نسخهٔ سازگار، update امن و support link | کانال update با GPL سازگار باشد؛ شکست update rollback یا recovery path داشته باشد. |
| **M-17** | گزارش‌گیری خروجی | CSV/PDF/export و گزارش زمان‌بندی‌شده | فیلتر شعبه/تاریخ/حریم خصوصی، timezone مشخص، قابلیت لغو و عدم افشای OTP/اطلاعات حساس. |
| **M-18** | multi-vendor فقط در صورت تصمیم محصول | داشبورد merchant و commission/membership | مرز branch و vendor روشن؛ دادهٔ هر merchant ایزوله؛ این کار تا زمان تصمیم product انجام نشود. |

---

## ۶. پیشنهاد ترتیب اجرا

1. **ابتدا M-01 تا M-04:** اعتماد به launch و حفظ داده از تعداد feature مهم‌تر است.
2. **سپس M-06 و M-07:** پوستهٔ پولی بدون backup و preview قوی، برای مشتری agency ریسک دارد.
3. **بعد M-08 تا M-12:** عمق سفارشی‌سازی و Elementor/Builder parity.
4. **در پایان M-13 تا M-18:** فقط بر اساس بازار هدف و مدل درآمدی، نه برای پرکردن فهرست feature.

### کارهایی که نباید صرفاً برای تقلید اضافه شوند

- وابسته‌کردن checkout یا مبلغ نهایی به JavaScript/Elementor.
- افزودن ده‌ها افزونهٔ اجباری که با تعویض قالب دادهٔ سفارش را از بین می‌برند.
- افزودن Google Maps/API یا فونت/asset خارجی وقتی OSM و asset محلی نیاز محصول را پوشش می‌دهند.
- تبدیل «تعداد feature» به معیار کیفیت، بدون تست RTL، keyboard، reduced-motion، performance و امنیت.
- پیاده‌سازی marketplace/multi-vendor بدون تصمیم محصول؛ این با مدل فعلی single-brand/multi-branch یکی نیست.

---

## ۷. برنامهٔ راستی‌آزمایی قبل از بستن ماموریت

### آزمون خودکار پیشنهادی

- `tests/theme/test-onboarding-contract.php`: هر field ویزارد را به خروجی/option/meta متناظر وصل کند.
- `tests/theme/test-customizer-contract.php`: تمام settingها، choiceها، sanitize/validate، transport و reset را بررسی کند.
- `tests/theme/test-demo-import-safety.php`: snapshot، objectهای demo-owned، rollback و حفظ محتوای غیر دمو.
- آزمون PHP موجود در README و lint همهٔ `flavor/`، `flavor-core/`، `flavor-child/` و `tests/`.

### سناریوی دستی E2E

1. وردپرس تازه با WooCommerce و Flavor Core نصب شود.
2. ویزارد با تصویر، ساعت، شهر و فقط دو mode تکمیل شود.
3. بررسی شود که branch، mode، ساعت، hero، page mapping و محصول درست ذخیره شده‌اند.
4. یک دمو با import کامل و سپس import فقط ظاهر اجرا شود؛ محتوای قبلی و rollback بررسی شود.
5. Customizer در desktop/tablet/mobile برای skin، رنگ، فونت، hero، gallery، header/footer و product اجرا شود.
6. یک سفارش QR، یک سفارش delivery، یک رزرو و یک تغییر KDS انجام شود.
7. export/import تنظیمات در یک سایت staging اجرا و عدم انتقال secret بررسی شود.
8. Lighthouse/axe و keyboard/reduced-motion/RTL روی صفحات home/menu/product/cart/checkout/kitchen اجرا شود.

> در محیط این ممیزی PHP روی sandbox نصب نبود (`php: command not found`)، بنابراین اجرای runtime testها در این دور ممکن نشد. یافته‌های P0 بخش ۳ از تطبیق مستقیم fieldهای فرم و `handle_submit()` به‌دست آمده و باید با تست WordPress واقعی قبل از merge کد تأیید شود.

---

## ۸. نتیجهٔ نهایی

Flavor از نظر **منطق عملیاتی رستوران، RTL فارسی، QR/KDS/رزرو/شعب و featureهای native فروش** ضعیف‌تر از بسیاری پوسته‌های پولی نیست؛ حتی در چند بخش مزیت دارد. فاصلهٔ واقعی در **محصول‌سازی تجربهٔ مدیریت** است: کاربر باید بدون خواندن چند سند بداند چه چیزی نصب شده، چه چیزی باقی مانده، چه چیزی حذف می‌شود و چگونه یک کلیک را برگرداند.

اگر فقط پنج کار P0 انجام شود، ادعای «پوستهٔ آمادهٔ تجاری» قابل اتکاتر می‌شود. بعد از آن، M-06 تا M-12 بیشترین اثر را بر هم‌ترازی با Customizer/Elementor پوسته‌های پولی خواهد داشت.
