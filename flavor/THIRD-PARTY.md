# وابستگی‌های شخص ثالث و لایسنس‌ها (Third-Party Licenses & Credits)

محصول **«رستوران مستقیم» (Flavor Restaurant)** شامل پوستهٔ اصلی (`flavor`)، افزونهٔ هستهٔ عملیاتی (`flavor-core`) و قالب فرزند (`flavor-child`) به صورت ۱۰۰٪ تحت مجوز آزاد و متن‌باز **GNU General Public License v2.0 یا بالاتر (GPL-2.0-or-later)** منتشر می‌شود.

این سند اعتبارات، پدیدآورندگان و مجوزهای رسمی تمامی دارایی‌ها و کتابخانه‌های شخص ثالث بسته‌بندی‌شده را به صورت شفاف و تفصیلی اعلام می‌دارد:

---

## ۱. نرم‌افزار اصلی (Primary Software)

- **Flavor Theme:** تحت مجوز GNU General Public License v2.0 or later (GPL-2.0-or-later)
- **Flavor Core Plugin:** تحت مجوز GNU General Public License v2.0 or later (GPL-2.0-or-later)
- **Flavor Child Theme:** تحت مجوز GNU General Public License v2.0 or later (GPL-2.0-or-later)
- **متن لایسنس:** موجود در فایل `LICENSE` در ریشهٔ مخزن و داخل تمامی بسته‌های توزیع.

---

## ۲. تایپوگرافی و قلم‌های وب (Web Fonts)

تمامی فونت‌های فارسی و وب گنجانده‌شده در این محصول، نرم‌افزارهای آزاد دارای مجوز رسمی هستند و به هیچ عنوان از فونت‌های تجاری و فاقد لایسنس بازتوزیع استفاده نشده است:

### ۱) قلم وزیرمتن (Vazirmatn)
- **پدیدآورنده:** صابر راستی‌کردار (Saber Rastikerdar)
- **لایسنس:** SIL Open Font License, Version 1.1 (OFL-1.1)
- **مخزن رسمی:** [https://github.com/rastikerdar/vazirmatn](https://github.com/rastikerdar/vazirmatn)
- **فایل لایسنس:** موجود در `flavor/assets/fonts/vazirmatn/OFL.txt`
- **کپی‌رایت:** Copyright (c) 2015-2023, Saber Rastikerdar.

### ۲) قلم استعداد (Estedad)
- **پدیدآورنده:** امین عابدی (Amin Abedi)
- **لایسنس:** SIL Open Font License, Version 1.1 (OFL-1.1)
- **مخزن رسمی:** [https://github.com/aminabedi68/Estedad](https://github.com/aminabedi68/Estedad)
- **فایل لایسنس:** موجود در `flavor/assets/fonts/estedad/OFL.txt`
- **کپی‌رایت:** Copyright (c) 2021, Amin Abedi.

### ۳) قلم ایران‌سنس (IRANSans — اختیاری)
- **وضعیت:** **به هیچ عنوان در این بسته توزیع نشده است (Not Bundled).**
- **توضیح:** ایران‌سنس یک تایپ‌فیس تجاری متعلق به فونت‌ایران (Fontiran) است. پوشهٔ `flavor/assets/fonts/iransans/` تنها حاوی یک فایل راهنما است تا در صورتی که خریدار مجوز معتبر استفاده دارد، بتواند فایل‌های قلم خود را در آن قرار دهد. در غیر این صورت، پوسته به صورت پیش‌فرض و ۱۰۰٪ امن از قلم آزاد وزیرمتن استفاده می‌کند.

---

## ۳. کتابخانه‌های کد شخص ثالث (Third-Party Libraries)

### موتور تولید بارکد QR (QRCode PHP Encoder)
- **پدیدآورنده:** کازو هیکو آراسه (Kazuhiko Arase)
- **فایل در مخزن:** `flavor-core/includes/lib/qrcode.php`
- **لایسنس:** MIT License
- **کپی‌رایت:** Copyright (c) 2008 Kazuhiko Arase
- **مجوز استفاده:** کتابخانه سبک و مستقل تولید QR در لایهٔ سرور جهت چاپ کارت‌های رومیزی و اسکن سفارش میز بدون نیاز به هیچ ابزار و افزونهٔ خارجی.

---

## ۴. اعلامیه عدم وابستگی تجاری و خارجی

- **فونت‌های گوگل:** هیچ اتصالی به سرورهای Google Fonts برقرار نمی‌شود و تمام فونت‌ها محلی (Self-Hosted) هستند.
- **سرویس‌های تحلیل خارجی:** هیچ اسکریپت ردیابی یا وابستگی ابری پنهان در کدهای فرانت‌اند مشتری وجود ندارد.
- **سازگاری کامل:** ۱۰۰٪ منطبق بر الزامات اکوسیستم وردپرس و مارکت‌پلیس‌های ایرانی (راست‌چین و ژاکت).
