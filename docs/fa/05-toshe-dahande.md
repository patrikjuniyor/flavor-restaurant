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

کپی کنید به چایلدتم:

- `template-parts/menu/*`
- `template-parts/marketing/*`
- `page-templates/template-menu.php`

داشبورد آشپزخانه را با فیلتر عوض کنید (فقط پوسته، نه داده):

```php
add_filter( 'flavor_core_kitchen_view', fn() => get_stylesheet_directory() . '/kitchen.php' );
```

## چایلدتم

یک قالب خالی با `Template: flavor` بسازید. توکن‌ها از کاستومایزر می‌آیند؛ SCSS لازم نیست.

## تست

```
cd flavor-core
# WP_TESTS_DIR را به wordpress-tests-lib بدهید
vendor/bin/phpunit
```

تست‌های موجود: قیمت/ارز، منطقه ارسال، جلالی، همپوشانی اسلات.

## امنیت

- REST عمومی منو nonce نمی‌خواهد؛ OTP/رزرو/کوپن rate-limit دارند.
- جهش سبد و چک‌اوت: هدر `X-WP-Nonce: wp_rest`.
- آشپزخانه: قابلیت `flavor_manage_kitchen`.
- هرگز `get_post_meta` روی سفارش نزنید؛ CRUD ووکامرس + جدول تیکت.
