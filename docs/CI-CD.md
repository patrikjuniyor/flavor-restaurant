# راهنمای خط لوله اتوماسیون CI/CD و امنیت کلیدها (CI/CD & Secret Management)

این مستند تشریح‌کننده معماری یکپارچه‌سازی و استقرار مداوم (CI/CD) برای ساخت خودکار اپلیکیشن‌های موبایل سیستم Flavor با استفاده از **GitHub Actions** و **Fastlane** است.

---

## ۱. معماری ارتباطی وردپرس و CI/CD (Architecture Workflow)

```
[WordPress Dashboard]
       |
       |  1. POST /repos/{owner}/{repo}/actions/workflows/build-mobile.yml/dispatches
       v
[GitHub Actions Runner]
       |
       |  2. POST /wp-json/flavor/v1/mobile/builds/callback (Status: building)
       |
       |  3. GET /wp-json/flavor/v1/mobile/config (دریافت اطلاعات برند)
       |  4. اجرای اسکریپت python3 provision_brand.py
       |  5. اجرای تست‌های خودکار flutter test
       |  6. کامپایل بسته‌ها (flutter build apk / appbundle / ios)
       |  7. محاسبه چک‌سام SHA-256 و آپلود فایل‌ها در Release Artifacts
       |
       |  8. POST /wp-json/flavor/v1/mobile/builds/callback (Status: success + Artifacts)
       v
[WordPress Dashboard (Ready for Download)]
```

---

## ۲. مدیریت امن کلیدها و گواهی‌ها (Zero-Trust Secret Management)

یکی از اصول حیاتی معماری Flavor این است که **هیچ کلید امضا، پسورد Keystore یا توکن محرمانه مارکت‌ها نباید در دیتابیس وردپرس یا کدهای فرانت‌اند قرار گیرد.**

### متغیرهای امنیتی ذخیره‌شده در GitHub Secrets:

| نام متغیر (Secret) | هدف | سطح دسترسی |
| :--- | :--- | :--- |
| `FLAVOR_CI_WEBHOOK_SECRET` | کلید مشترک جهت تولید امضای HMAC-SHA256 در وب‌هوک‌های بازگشتی | سرور CI و وردپرس |
| `ANDROID_KEYSTORE_BASE64` | محتوای فایل کلید انتشار اندروید (`.jks` / `.keystore`) به صورت Base64 | فقط رانر CI |
| `ANDROID_KEYSTORE_PASSWORD` | کلمه عبور فایل Keystore | فقط رانر CI |
| `ANDROID_KEY_ALIAS` | نام مستعار کلید داخل Keystore | فقط رانر CI |
| `ANDROID_KEY_PASSWORD` | رمز اختصاصی کلید انتخابی | فقط رانر CI |
| `APP_STORE_CONNECT_KEY` | کلید خصوصی ارتباط با API اپ استور جهت توزیع خودکار TestFlight | فقط رانر CI (macOS) |
| `MATCH_PASSWORD` | کلمه عبور گواهی‌های اشتراکی Fastlane Match | فقط رانر CI (macOS) |

---

## ۳. نحوه امضای امن وب‌هوک با HMAC-SHA256

جهت جلوگیری از ریکوئست‌های جعلی به اندپوینت ثبت وضعیت بیلد (`/wp-json/flavor/v1/mobile/builds/callback`):
1. سرور CI قبل از ارسال درخواست، هش محتوای JSON را با استفاده از الگوریتم `HMAC-SHA256` و کلید مخفی `FLAVOR_CI_WEBHOOK_SECRET` تولید می‌کند:
   ```bash
   SIG=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$FLAVOR_CI_WEBHOOK_SECRET" | sed 's/^.* //')
   ```
2. مقدار هش در هدر `X-Flavor-Signature` ارسال می‌شود.
3. کلاس `BuildManager` در وردپرس امضای دریافتی را با تابع امن `hash_equals()` اعتبارسنجی کرده و در صورت عدم تطابق، بلافاصله خطای `403 Forbidden` برمی‌گرداند.

---

## ۴. پیکربندی Fastlane برای انتشار خودکار به استورها

برای تیم‌هایی که تمایل دارند علاوه بر بیلد خام، پکیج‌ها مستقیماً به کافه‌بازار، مایکت یا گوگل‌پلی و اپ‌استور ارسال شوند، نمونه فایل `mobile/fastlane/Fastfile`:

```ruby
default_platform(:android)

platform :android do
  desc "Build and upload release bundle to Cafe Bazaar / Play Store"
  lane :deploy_release do
    gradle(
      task: 'bundle',
      build_type: 'Release'
    )
    # Uploading via market APIs
    bazaar_upload(
      package_name: ENV["PACKAGE_NAME"],
      aab_path: "build/app/outputs/bundle/release/app-release.aab"
    )
  end
end

platform :ios do
  desc "Push a new beta build to TestFlight"
  lane :beta do
    match(type: "appstore")
    gym(scheme: "Runner", export_method: "app-store")
    pilot(skip_waiting_for_build_processing: true)
  end
end
```

## ۵. ممیزی وابستگی‌ها و لایسنس‌های باندل‌شده (R4-6 / پرامپت ۴۰ — ۲۰۲۶-۱۰-۱۲)

### ۵.۱ Composer (فقط ابزارِ توسعه؛ در بستهٔ فروخته‌شده نمی‌آید)
- `composer audit --locked` روی هر ۳۵ پکیجِ `composer.lock`: **صفرِ advisory** (دیتابیسِ Packagist، ۲۰۲۶-۱۰-۱۲).
- **منسوخ‌بودن بررسی شد:** `dealerdirect/phpcodesniffer-composer-installer` فلگِ abandoned ندارد و v1.2.1 هنوز آخرینِ خودش است (forkِ phpcsstandards فقط نامِ جایگزین است و برایِ کارِ ما لازم نیست).
- **آسیبِ واقعیِ پیدا و رفع‌شده — قفلِ ناسازگار با ماتریسِ CI:** lock روی ماشینِ PHP 8.4 ساخته شده بود و `doctrine/instantiator 2.1.0` (نیازمندِ `php ^8.4`) را قفل کرده بود؛ jobِ «WordPress Coding Standards (report)» روی PHP 8.2 در همان `composer install` می‌مرد و **هرگز به phpcs نمی‌رسید**. رفع: `config.platform.php = 8.2.34` در composer.json (lock حالا روی ۸.۱ تا ۸.۴ قابل‌install است) + re-resolve به `doctrine/instantiator 2.0.0` (`php ^8.1`). تستِ postinstall: `phpcs -i` هر ۲۳ استاندارد را لیست می‌کند و report ساخته می‌شود.
- **آسیبِ دومِ همان job که زیرِ آوارِ اول پنهان بود:** `phpcs.xml` یک exclude به `WordPress.WhiteSpace.PrecisionAlignment` داشت — این sniff در WPCS 3.0 حذف شده (به `Universal.WhiteSpace.PrecisionAlignment` نقل مکان) و ارجاعِ خط‌خورده، phpcs را با `ERROR: Referenced sniff does not exist` می‌کشت؛ یعنی حتی اگر install پاس می‌شد، report **همیشه تهی** بود. حذف شد و گیت ماشینی (`test-distribution.php` §7) بازنشستِ‌اش را FAIL می‌کند.

### ۵.۲ npm (تست‌های مرورگر — dev-only)
- `tests/ui` و `tests/demos`: `npm audit` → **۰ آسیب‌پذیری** (playwright 1.63.0، @axe-core/playwright 4.13.0).
- playwright 1.64.0 منتشر شده اما 1.63 منسوخ/آسیب‌دیده نیست؛ با per-scopeِ «تا تست‌ها نشکند» ارتقا داده **نشد** (job Chromiumِ Elementor-visual به همین pin سبز است). ارتقا را همراه فازِ ابزارِ UI انجام دهید.
- ریشهٔ مخزن فقط یک `package-lock.json` تهی (۸۳ بایت، `packages:{}`، بدونِ package.json) دارد — هیچ وابستگیِ ریشه‌ای نیست؛ عمداً دست‌نخورده چون حذفش workflowها را نمی‌شکند ولی چیزی هم امن‌تر نمی‌کند.

### ۵.۳ موبایل — pubspec
- `mobile/pubspec.lock`: **۱۰۵ پکیج** (۱۶ direct + ۸۹ transitive) تک‌تک روی **OSV.dev** (اکوسیستمِ Pub) کوئری شد: **صفر advisory** روی نسخه‌های قفل‌شده (۲۰۲۶-۱۰-۱۲).
- عقب‌ماندگیِ major (نه ناامنی): `firebase_core 2.32` و `firebase_messaging 14.9` نسبت به خط ۳/۴، `intl 0.19` و `flutter_local_notifications 17` — ارتقاها الزاماً فلایتر CI را از ۳.۲۴.x جلو می‌برند و `flutter test` موبایل را می‌شکنند؛ طبقِ same-قیدِ پرامپت ثبت شد و به laneِ ساختِ موبایل (فاز انتشار) موکول گشت.

### ۵.۴ لایسنس‌های باندل‌شده (گیتماشینی‌شده)
- `flavor-core/includes/lib/qrcode.php` (MIT، Kazuhiko Arase): سربرگِ license داخل ۳۰ خطِ اول + گارد `ABSPATH` — pin شد.
- فونت‌ها: هر دایرکتوریِ `flavor/assets/fonts/*` که فایلِ فونت باندل می‌کند **باید** OFL.txt/LICENSE داشته باشد؛ `estedad` داشت، **`vazirmatn` نداشت و اضافه شد** (OFL.txt رسمیِ مخزنِ rastikerdar/vazirmatn، کپی‌رایت ۲۰۱۵، ۴۳۹۱ بایت). `iransans` عمداً فونت ندارد (تایپ‌فیسِ تجاریِ Fontiran؛ README این را بیان می‌کند) — دایرکتوریِ مستنداتی از قاعدهٔ scan خارج است.
- بستهٔ نهایی: `build-distribution.sh` کل `flavor/` را کپی می‌کند و فقط `demos|tests|node_modules` را در scratch حذف می‌کند؛ گیت بررسی می‌کند هیچ `rm -rf`ِ دیگری روی مسیرِ سورس (assets/fonts/license) نباشد — پس لایسنس‌ها **ساختاری** در zip می‌مانند.

### ۵.۵ تکرارِ ممیزی (هر بار که قفلی عوض شد)
```bash
php composer.phar audit --locked                       # packagist advisories
( cd tests/ui   && npm audit --no-fund )              # 0 vulnerabilities
( cd tests/demos && npm audit --no-fund )             # 0 vulnerabilities
php tests/theme/test-distribution.php                  # §7 license gate
python3 dev-tools/osv-pub-audit.py                     # pub.lock → OSV (اسکریپتِ این‌جا مستند)
```
