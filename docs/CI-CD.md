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
