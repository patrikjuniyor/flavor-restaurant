# چایلد‌تم Flavor

پوشهٔ `flavor-child/` برای سفارشی‌سازی امن است. قالب اصلی را دست‌کاری نکنید؛ هر تغییری
اینجا بماند تا با آپدیت بعدی پاک نشود.

## نصب

```bash
cp -r flavor-child /path/to/wp/wp-content/themes/flavor-child
```

یا در monorepo توسعه:

```bash
ln -s /path/to/flavor-restaurant/flavor-child /path/to/wp/wp-content/themes/flavor-child
```

سپس از **نمایش → پوسته‌ها** گزینهٔ «Flavor Child» را فعال کنید. تنظیمات، دموها، منوها و
سفارش‌ها با فعال‌کردن چایلد‌تم دست‌نخورده می‌مانند، چون همهٔ داده‌ها در افزونهٔ
`flavor-core` و ووکامرس ذخیره شده‌اند.

## چه چیزی را کجا بنویسیم

| نیاز | جای درست |
|---|---|
| تغییر رنگ، گوشه‌ها و تایپوگرافی | **سازگارساز → ویژگی‌های پیشرفته Flavor** (بدون کد) |
| بازنویسی CSS سفارشی | `flavor-child/style.css` |
| تغییر ساختار یا افزودن فیلد | `flavor-child/functions.php` با هوک‌های مستندشده |
| تغییر فایل‌های قالب | همان مسیر در چایلد‌تم (مثلاً `flavor-child/footer.php`) |

## هوک‌های مفید

```php
// گسترش مگامنو به پاورقی
add_filter( 'flavor_mega_locations', fn( $l ) => array_merge( $l, array( 'footer' ) ) );

// گرفتن ایمیل‌های خبرنامه و ارسال به سرویس بیرونی
add_action( 'flavor_newsletter_signup', function ( $email, $source ) {
    // ...
}, 10, 2 );
```

## نکتهٔ حالت شب

پالت شب در PHP ساخته می‌شود (`Flavor\Dark_Mode::palette()`). اگر رنگ برند را تغییر دادید و
می‌خواهید در حالت شب هم همان حس بماند، متغیرها را روی همان انتخابگر بازنویسی کنید:

```css
html[data-flavor-scheme="dark"],
html[data-flavor-scheme="dark"] body {
    --flavor-primary: #e7b27f;
}
```
