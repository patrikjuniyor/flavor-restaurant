# استثناء کش

این رشته‌ها را در LiteSpeed Cache، WP Rocket یا Super Cache به «هرگز کش نشوند» اضافه کنید:

```
/kitchen-dashboard
/kitchen-receipt
/wp-json/flavor/
/branch/.*/table/
```

کوکی خصوصی:

```
flavor_ctx
wordpress_logged_in_
woocommerce_items_in_cart
```

اگر WP Rocket نصب باشد افزونه خودش `rocket_cache_reject_uri` را پر می‌کند.
