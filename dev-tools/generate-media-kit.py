# -*- coding: utf-8 -*-
"""
dev-tools/generate-media-kit.py
Prompt 93: Generate complete marketing media kit in media-kit/ folder:
1. Vector typography logo: logo.svg & high-res PNG (logo.png)
2. Top 6 curated screenshots (desktop & mobile) in media-kit/top-screenshots/
3. Features infographic SVG (features-infographic.svg)
4. Marketplace listing copy file (listing-copy.md) extracted and structured from docs/raastichin/listing.md
"""

import os
import shutil
from PIL import Image, ImageDraw

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MEDIA_KIT_DIR = os.path.join(ROOT, 'media-kit')
TOP_SCREENSHOTS_DIR = os.path.join(MEDIA_KIT_DIR, 'top-screenshots')
DOCS_PREVIEWS_DIR = os.path.join(ROOT, 'docs', 'demo-previews')

os.makedirs(MEDIA_KIT_DIR, exist_ok=True)
os.makedirs(TOP_SCREENSHOTS_DIR, exist_ok=True)

# 1. Generate Typography Logo SVG & PNG
logo_svg_content = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 160" width="600" height="160" dir="rtl">
  <defs>
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#1E232E"/>
      <stop offset="100%" stop-color="#12151B"/>
    </linearGradient>
    <linearGradient id="primaryGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#E05624"/>
      <stop offset="100%" stop-color="#F59E0B"/>
    </linearGradient>
    <filter id="shadow" x="-10%" y="-10%" width="120%" height="120%">
      <feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="#000000" flood-opacity="0.4"/>
    </filter>
  </defs>

  <!-- Container -->
  <rect width="600" height="160" rx="20" fill="url(#bgGrad)" filter="url(#shadow)"/>
  <rect x="1" y="1" width="598" height="158" rx="19" fill="none" stroke="#E05624" stroke-opacity="0.3" stroke-width="2"/>

  <!-- Brand Symbol / Emblem: Cloche & Fork -->
  <g transform="translate(480, 40)">
    <circle cx="40" cy="40" r="38" fill="url(#primaryGrad)"/>
    <path d="M 22 48 C 22 28, 58 28, 58 48 Z" fill="#FFFFFF"/>
    <circle cx="40" cy="24" r="5" fill="#FFFFFF"/>
    <rect x="16" y="52" width="48" height="5" rx="2.5" fill="#FFFFFF"/>
  </g>

  <!-- Main Typography Persian -->
  <text x="450" y="78" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="900" font-size="44" fill="#FFFFFF" letter-spacing="0">رستوران مستقیم</text>
  
  <!-- Subtitle Persian & English tag -->
  <text x="450" y="116" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="500" font-size="18" fill="#F59E0B">قالب و افزونه تخصصی سفارش‌گیری کافه و رستوران</text>
  <text x="50" y="90" font-family="sans-serif" font-weight="700" font-size="20" fill="#9CA3AF" letter-spacing="2">FLAVOR PLATFORM</text>
  <text x="50" y="114" font-family="sans-serif" font-weight="500" font-size="14" fill="#E05624">WordPress &amp; WooCommerce</text>
</svg>
"""

with open(os.path.join(MEDIA_KIT_DIR, 'logo.svg'), 'w', encoding='utf-8') as f:
    f.write(logo_svg_content.strip() + '\n')

# High-resolution PNG logo
logo_png = Image.new('RGB', (1200, 320), (24, 28, 36))
d = ImageDraw.Draw(logo_png)
d.rounded_rectangle([(10, 10), (1190, 310)], radius=30, fill=(30, 36, 46), outline=(224, 86, 36), width=4)
# Cloche emblem
d.ellipse([(960, 70), (1120, 230)], fill=(224, 86, 36))
d.pieslice([(990, 80), (1090, 230)], 180, 360, fill=(255, 255, 255))
d.rectangle([(980, 210), (1100, 225)], fill=(255, 255, 255))
# Brand typography
d.text((920, 90), "رستوران مستقیم", fill=(255, 255, 255), anchor="ra")
d.text((920, 180), "قالب و افزونه تخصصی سفارش‌گیری کافه و رستوران (نسخه ۱٫۶٫۲)", fill=(245, 158, 11), anchor="ra")
d.text((90, 110), "FLAVOR RESTAURANT PLATFORM", fill=(180, 190, 205))
d.text((90, 170), "RTL-FIRST • TOMAN CURRENCY • HPOS COMPATIBLE", fill=(224, 86, 36))
logo_png.save(os.path.join(MEDIA_KIT_DIR, 'logo.png'), format='PNG', optimize=True)


# 2. Select and Copy Top 6 Screenshots
top_6_demos = [
    'modern-restaurant',
    'luxury-dining',
    'persian-traditional',
    'pizza-italian',
    'fast-food',
    'cafe-bistro'
]

for idx, demo in enumerate(top_6_demos, 1):
    desk_src = os.path.join(DOCS_PREVIEWS_DIR, f"{demo}-desktop.jpg")
    mob_src = os.path.join(DOCS_PREVIEWS_DIR, f"{demo}-mobile.jpg")
    
    desk_dst = os.path.join(TOP_SCREENSHOTS_DIR, f"top-0{idx}-{demo}-desktop.jpg")
    mob_dst = os.path.join(TOP_SCREENSHOTS_DIR, f"top-0{idx}-{demo}-mobile.jpg")
    
    shutil.copyfile(desk_src, desk_dst)
    shutil.copyfile(mob_src, mob_dst)


# 3. Generate Features Infographic SVG
infographic_svg = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 900" width="1200" height="900" dir="rtl">
  <defs>
    <linearGradient id="mainBg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#181B22"/>
      <stop offset="100%" stop-color="#0F1116"/>
    </linearGradient>
    <linearGradient id="cardBg" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="#262C38"/>
      <stop offset="100%" stop-color="#1D212B"/>
    </linearGradient>
    <linearGradient id="accentOrange" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#E05624"/>
      <stop offset="100%" stop-color="#F59E0B"/>
    </linearGradient>
  </defs>

  <!-- Background -->
  <rect width="1200" height="900" fill="url(#mainBg)"/>

  <!-- Header Banner -->
  <rect x="60" y="40" width="1080" height="120" rx="16" fill="url(#cardBg)" stroke="#E05624" stroke-width="2"/>
  <text x="1100" y="95" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="900" font-size="34" fill="#FFFFFF">رستوران مستقیم — نقشه ویژگی‌های تجاری و عملیاتی</text>
  <text x="1100" y="130" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="500" font-size="18" fill="#F59E0B">پلتفرم یکپارچه سفارش سالن با QR، بیرون‌بر، داشبورد آشپزخانه و رزرو شمسی</text>
  <rect x="90" y="70" width="180" height="60" rx="10" fill="url(#accentOrange)"/>
  <text x="180" y="107" text-anchor="middle" font-family="sans-serif" font-weight="bold" font-size="20" fill="#FFFFFF">FLAVOR v1.6.2</text>

  <!-- 6 Grid Feature Cards (2 Rows of 3 Cards) -->
  
  <!-- Row 1, Card 1: QR & Table Ordering -->
  <g transform="translate(800, 190)">
    <rect width="340" height="200" rx="14" fill="url(#cardBg)" stroke="#374151" stroke-width="1.5"/>
    <rect x="250" y="20" width="70" height="40" rx="8" fill="#E05624"/>
    <text x="285" y="46" text-anchor="middle" font-size="20" fill="#FFFFFF">QR</text>
    <text x="230" y="48" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="bold" font-size="20" fill="#FFFFFF">سفارش سر میز با QR</text>
    <text x="315" y="90" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• توکن اختصاصی برای هر میز سالن</text>
    <text x="315" y="120" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• ورود خودکار مهمان بدون نیاز به نصب اپ</text>
    <text x="315" y="150" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• انتخاب مدیفایرها و ارسال مستقیم به مطبخ</text>
  </g>

  <!-- Row 1, Card 2: Kitchen KDS -->
  <g transform="translate(430, 190)">
    <rect width="340" height="200" rx="14" fill="url(#cardBg)" stroke="#374151" stroke-width="1.5"/>
    <rect x="250" y="20" width="70" height="40" rx="8" fill="#10B981"/>
    <text x="285" y="46" text-anchor="middle" font-size="20" fill="#FFFFFF">KDS</text>
    <text x="230" y="48" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="bold" font-size="20" fill="#FFFFFF">داشبورد آشپزخانه لمسی</text>
    <text x="315" y="90" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• بهینه‌شده برای تبلت و تاچ ۴۸ پیکسلی</text>
    <text x="315" y="120" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• آلارم صوتی سفارش جدید و تم دارک خوانا</text>
    <text x="315" y="150" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• چاپ خودکار فیش حرارتی سالن و پیک</text>
  </g>

  <!-- Row 1, Card 3: Jalali Calendar Reservations -->
  <g transform="translate(60, 190)">
    <rect width="340" height="200" rx="14" fill="url(#cardBg)" stroke="#374151" stroke-width="1.5"/>
    <rect x="250" y="20" width="70" height="40" rx="8" fill="#6366F1"/>
    <text x="285" y="46" text-anchor="middle" font-size="20" fill="#FFFFFF">شمسی</text>
    <text x="230" y="48" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="bold" font-size="20" fill="#FFFFFF">رزرو میز شمسی و جلالی</text>
    <text x="315" y="90" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• تقویم جلالی بدون وابستگی به اسکریپت خارجی</text>
    <text x="315" y="120" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• ظرفیت‌سنجی هوشمند بر اساس اسلات زمانی</text>
    <text x="315" y="150" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• پیامک خودکار تایید و یادآوری نوبت رزرو</text>
  </g>

  <!-- Row 2, Card 4: Architecture & Decoupling -->
  <g transform="translate(800, 420)">
    <rect width="340" height="200" rx="14" fill="url(#cardBg)" stroke="#374151" stroke-width="1.5"/>
    <rect x="250" y="20" width="70" height="40" rx="8" fill="#EC4899"/>
    <text x="285" y="46" text-anchor="middle" font-size="18" fill="#FFFFFF">معماری</text>
    <text x="230" y="48" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="bold" font-size="20" fill="#FFFFFF">تفکیک پوسته و افزونه</text>
    <text x="315" y="90" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• داده‌های رستوران در افزونهٔ هسته محفوظ است</text>
    <text x="315" y="120" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• تغییر پوسته بدون از دست رفتن حتی ۱ سفارش</text>
    <text x="315" y="150" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• بدون دستکاری قالب‌های هسته ووکامرس</text>
  </g>

  <!-- Row 2, Card 5: Mobile App & REST API -->
  <g transform="translate(430, 420)">
    <rect width="340" height="200" rx="14" fill="url(#cardBg)" stroke="#374151" stroke-width="1.5"/>
    <rect x="250" y="20" width="70" height="40" rx="8" fill="#0EA5E9"/>
    <text x="285" y="46" text-anchor="middle" font-size="18" fill="#FFFFFF">اپ بومی</text>
    <text x="230" y="48" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="bold" font-size="20" fill="#FFFFFF">اپلیکیشن فلاتر و API v2</text>
    <text x="315" y="90" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• کدهای آماده فلاتر در دایرکتوری mobile/</text>
    <text x="315" y="120" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• کش آفلاین منو و مدیریت سبد مهمان</text>
    <text x="315" y="150" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• پوش نوتیفیکیشن FCM و انتخابگر شهرهای ایران</text>
  </g>

  <!-- Row 2, Card 6: Commerce, HPOS & Security -->
  <g transform="translate(60, 420)">
    <rect width="340" height="200" rx="14" fill="url(#cardBg)" stroke="#374151" stroke-width="1.5"/>
    <rect x="250" y="20" width="70" height="40" rx="8" fill="#F59E0B"/>
    <text x="285" y="46" text-anchor="middle" font-size="18" fill="#FFFFFF">امنیت</text>
    <text x="230" y="48" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="bold" font-size="20" fill="#FFFFFF">ووکامرس HPOS و امنیت</text>
    <text x="315" y="90" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• پشتیبانی رسمی از High-Performance Storage</text>
    <text x="315" y="120" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• به‌روزرسانی امضاشده با کلید Ed25519</text>
    <text x="315" y="150" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-size="15" fill="#D1D5DB">• پشتیبان‌گیری اتمیک خودکار پیش از به‌روزرسانی</text>
  </g>

  <!-- Bottom Badges Strip -->
  <rect x="60" y="650" width="1080" height="190" rx="16" fill="url(#cardBg)" stroke="#374151" stroke-width="1.5"/>
  <text x="1100" y="700" text-anchor="end" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="bold" font-size="24" fill="#FFFFFF">سازگاری و فناوری‌های پشتیبانی‌شده</text>
  
  <g transform="translate(80, 730)">
    <rect x="800" y="0" width="200" height="60" rx="30" fill="#1F2937" stroke="#4B5563" stroke-width="1"/>
    <text x="900" y="37" text-anchor="middle" font-size="16" fill="#F3F4F6">۱۲ دموی کامل آماده</text>

    <rect x="540" y="0" width="230" height="60" rx="30" fill="#1F2937" stroke="#4B5563" stroke-width="1"/>
    <text x="655" y="37" text-anchor="middle" font-size="16" fill="#F3F4F6">۱۷ ویجت اختصاصی المنتور</text>

    <rect x="280" y="0" width="230" height="60" rx="30" fill="#1F2937" stroke="#4B5563" stroke-width="1"/>
    <text x="395" y="37" text-anchor="middle" font-size="16" fill="#F3F4F6">۱۰۰٪ راست‌چین و بدون تحریم</text>

    <rect x="20" y="0" width="230" height="60" rx="30" fill="#1F2937" stroke="#4B5563" stroke-width="1"/>
    <text x="135" y="37" text-anchor="middle" font-size="16" fill="#F3F4F6">کنتراست استاندارد WCAG AA</text>
  </g>
</svg>
"""

with open(os.path.join(MEDIA_KIT_DIR, 'features-infographic.svg'), 'w', encoding='utf-8') as f:
    f.write(infographic_svg.strip() + '\n')


# 4. Generate Structured Marketplace Listing Copy (listing-copy.md)
listing_src_path = os.path.join(ROOT, 'docs', 'raastichin', 'listing.md')
with open(listing_src_path, 'r', encoding='utf-8') as f:
    raw_listing = f.read()

listing_copy = f"""# متون آمادهٔ لیستینگ و بازاریابی — رستوران مستقیم (Flavor)
> این سند برای انتشار مستقیم در راست‌چین و مارکت‌پلیس‌های ایرانی آماده شده است. تمام متون فارسی و راست‌چین هستند.

---

## ۱. عنوان محصول (Title)
رستوران مستقیم | قالب + افزونه سفارش‌گیری کافه و رستوران (QR، آشپزخانه، رزرو شمسی)

---

## ۲. چکیده کوتاه ۱۶۰ کاراکتری (Short Summary / Meta Description)
سفارش سالن با QR، بیرون‌بر و ارسال، داشبورد آشپزخانه، رزرو شمسی، ورود با موبایل و تومان — بدون وابستگی به فونت گوگل و سرویس خارجی.

---

## ۳. ویژگی‌های کلیدی با بولت (Key Features Bullet Points)
- **سفارش سر میز با QR:** صدور توکن یکتای میز، سفارش بدون تماس، شیت انتخاب مخلفات و ثبت آنی سفارش.
- **داشبورد لمسی آشپزخانه (KDS):** نمایشگر مدرن تبلت با تایپوگرافی خوانا، زنگ هشدار سفارش جدید، تفکیک سالن/بیرون‌بر و چاپ خودکار فیش.
- **رزرو آنلاین میز با تقویم شمسی جلالی:** الگوریتم کاملاً دقیق تقویم بدون تکیه به سرویس‌های ابری خارجی، محاسبه خودکار سال‌های کبیسه و اسلات‌های زمانی.
- **معماری مستقل و امن:** نگهداری سفارش‌ها، شعب و میزها در افزونهٔ هسته (`flavor-core`) با جدول‌های سفارشی دیتابیس؛ تعویض پوسته هیچ داده‌ای را پاک نمی‌کند.
- **سازگاری کامل با ووکامرس و HPOS:** استفاده از High-Performance Order Storage، سبد خرید و تسویه‌حساب ایمن بدون دستکاری کدهای هستهٔ ووکامرس.
- **سیستم ورود و احراز هویت با پیامک (OTP):** پشتیبانی از سرشماره‌ها و درگاه‌های پیامک ایرانی (کاوه‌نگار، فراز اس‌ام‌اس، ملی‌پیامک، قاصدک) همراه با لایه امنیتی ضد Brute-Force.
- **۱۲ دموی آماده با یک کلیک:** رستوران مدرن، لوکس، سنتی، فست‌فود، پیتزا، کافه، شیرینی‌فرانسوی، اسموتی، دارک، مینیمال، کیترینگ و آشپزخانه ابری.
- **۱۷ ویجت اختصاصی المنتور و ۳ قالب کامل:** طراحی کامل هدر، فوتر و صفحات بدون نیاز به نسخهٔ تجاری Elementor Pro.
- **اپلیکیشن همراه Flutter:** سورس‌کد کامل پروژه در دایرکتوری `mobile/` با پشتیبانی از کش آفلاین منو، مدیریت سبد مهمان و پوش‌نوتیفیکیشن.
- **به‌روزرسانی امن و بدون دغدغه:** کانال اختصاصی مجهز به امضای دیجیتال Ed25519 و بک‌آپ‌گیری اتمیک خودکار پیش از به‌روزرسانی.

---

## ۴. توضیح کامل و بلند محصول (Full Description)
{raw_listing.strip()}
"""

with open(os.path.join(MEDIA_KIT_DIR, 'listing-copy.md'), 'w', encoding='utf-8') as f:
    f.write(listing_copy.strip() + '\n')

print("Marketing media kit generated successfully!")
