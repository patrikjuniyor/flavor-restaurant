# مستند توسعه‌دهنده

فضای نام: `FlavorCore\` (افزونه) و `Flavor\` (قالب).  
دامنه ترجمه: `flavor-core` / `flavor`.

## هوک‌ها و REST

فهرست کامل: [`docs/HOOKS.md`](../HOOKS.md)

نمونه:

```php
add_action( 'flavor_core_kitchen_ticket_created', function ( $id, $row ) {
    // تیکت جدید
}, 10, 2 );

add_filter( 'flavor_core_sms_providers', function ( $list ) {
    $list[] = new My_SMS_Provider();
    return $list;
} );
```

## بازنویسی قالب

کپی کنید به پوستهٔ فرزند (Child Theme) با حفظ ساختار مسیر:

- `flavor/template-parts/menu/*` (شیت‌های منو، جست‌وجو و کارت‌های غذا)
- `flavor/template-parts/marketing/*` (بخش‌های هیرو، درباره ما، گالری و نظرات)
- `flavor/page-templates/template-menu.php` (برگهٔ اصلی سفارش آنلاین)

داشبورد آشپزخانه را با فیلتر عوض کنید (فقط پوسته، نه داده):

```php
add_filter( 'flavor_core_kitchen_view', fn() => get_stylesheet_directory() . '/kitchen.php' );
```

## چایلدتم

یک قالب خالی با `Template: flavor` بسازید. توکن‌ها از کاستومایزر می‌آیند؛ SCSS لازم نیست.

## آزمون‌ها و ارزیابی خودکار

آزمون‌های لاجیک هسته و یکپارچگی نیازی به وابستگی خارجی سنگین ندارند و با اسکریپت‌های توکار اجرا می‌شوند:

```bash
# آزمون‌های لاجیک خالص هسته (۲۲۶ تست)
php flavor-core/tests/run-pure-tests.php

# آزمون‌های یکپارچگی سرتاسری و سناریوهای سفارش/رزرو (۸۲ تست)
php flavor-core/tests/run-e2e-integration.php

# آزمون‌های جامع قالب وردپرس (۶۸ سوئیت)
bash dev-tools/run-theme-tests.sh
```

تست‌های موجود: قیمت/ارز، مناطق ارسال، تاریخ جلالی، عدم همپوشانی اسلات رزرو، کش، تیکت آشپزخانه و سناریوهای پرداخت.

## امنیت

- REST عمومی منو nonce نمی‌خواهد؛ OTP/رزرو/کوپن rate-limit دارند.
- جهش سبد و چک‌اوت: هدر `X-WP-Nonce: wp_rest`.
- آشپزخانه: قابلیت `flavor_manage_kitchen`.
- هرگز `get_post_meta` روی سفارش نزنید؛ CRUD ووکامرس + جدول تیکت.
