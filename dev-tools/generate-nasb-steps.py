# -*- coding: utf-8 -*-
"""
dev-tools/generate-nasb-steps.py
Prompt 94: Generate 12 real, crisp screenshot steps for the 12-step installation guide.
Saves to docs/fa/images/step-01.jpg ... step-12.jpg with exact 1200x675 (16:9 HD) resolution.
"""

import os
from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_DIR = os.path.join(ROOT, 'docs', 'fa', 'images')
os.makedirs(OUT_DIR, exist_ok=True)

STEPS = [
    {
        "num": "۰۱",
        "title": "ورود به پیشخوان وردپرس و آماده‌سازی هاست",
        "badge": "گام ۱: پیش‌نیازها و ورود",
        "screen_type": "wp-admin-dashboard",
        "panel_title": "پیشخوان وردپرس (مدیریت سایت)",
        "content_lines": [
            "بررسی نسخه PHP: نسخه فعال ۸.۳ (سازگار با ۸.۱ تا ۸.۴)",
            "ماژول‌های ضروری فعال: curl, mbstring, openssl, gd, zip",
            "وضعیت حافظه memory_limit: ۲۵۶ مگابایت — زمان اجرا: ۱۲۰ ثانیه",
            "آدرس ورود: yourdomain.com/wp-admin"
        ],
        "highlight_box": "پیشخوان مدیریت وردپرس آمادهٔ دریافت بسته‌های رستوران مستقیم"
    },
    {
        "num": "۰۲",
        "title": "نصب و بارگذاری افزونه هسته عملیاتی (Flavor Core)",
        "badge": "گام ۲: بارگذاری افزونه",
        "screen_type": "wp-plugins-add",
        "panel_title": "افزونه‌ها ← افزودن افزونه تازه ← بارگذاری افزونه",
        "content_lines": [
            "انتخاب فایل فشردهٔ افزونه: flavor-core.zip",
            "محتویات هسته: سفارش‌گیری، فیش آشپزخانه KDS، رزرو شمسی، پیامک و جداول اختصاصی",
            "فشردن دکمه: هم‌اکنون نصب کن (Install Now)"
        ],
        "highlight_box": "انتخاب فایل flavor-core.zip و آپلود به وردپرس"
    },
    {
        "num": "۰۳",
        "title": "فعال‌سازی افزونه Flavor Core و ساخت خودکار جداول دیتابیس",
        "badge": "گام ۳: فعال‌سازی هسته",
        "screen_type": "wp-plugins-activate",
        "panel_title": "نصب افزونه از پروندهٔ بارگذاری‌شده",
        "content_lines": [
            "گشایش بسته و بررسی سازگاری...",
            "ایجاد جداول اختصاصی پایگاه داده: wp_flavor_kitchen_tickets و wp_flavor_reservations",
            "پیام موفقیت: افزونه با موفقیت نصب شد.",
            "اقدام: فشردن دکمهٔ «فعال‌کردن افزونه»"
        ],
        "highlight_box": "افزونه فعال شد — جداول پایگاه داده ساخته شدند"
    },
    {
        "num": "۰۴",
        "title": "نصب خودکار یا فعال‌سازی ووکامرس (WooCommerce)",
        "badge": "گام ۴: پایگاه فروشگاهی",
        "screen_type": "wp-wc-setup",
        "panel_title": "بررسی زیرساخت فروشگاهی ووکامرس",
        "content_lines": [
            "شناسایی خودکار ووکامرس توسط Flavor Core",
            "بررسی نسخه ووکامرس: فعال روی نسخه ۸.۵+ تا ۱۱.۰+",
            "فعال‌سازی ذخیره‌سازی سفارش با کارایی بالا (HPOS) در ووکامرس ← پیکربندی",
            "تایید سبد خرید و تسویه‌حساب استاندارد ووکامرس"
        ],
        "highlight_box": "سازگاری کامل با ووکامرس و زیرساخت HPOS فعال شد"
    },
    {
        "num": "۰۵",
        "title": "نصب و بارگذاری پوسته اصلی (Flavor Theme)",
        "badge": "گام ۵: بارگذاری پوسته",
        "screen_type": "wp-themes-add",
        "panel_title": "نمایش ← پوسته‌ها ← افزودن پوسته تازه ← بارگذاری پوسته",
        "content_lines": [
            "انتخاب فایل قالب: flavor.zip (حجم سبک ۱.۳ مگابایت)",
            "پوسته اختصاصی رستوران مستقیم: کاملاً راست‌چین و موبایل‌اول",
            "بدون هیچ وابستگی به سرویس‌های خارجی یا مسدودشده",
            "فشردن دکمه: هم‌اکنون نصب کن (Install Now)"
        ],
        "highlight_box": "بارگذاری بستهٔ قالب flavor.zip در پوشه themes"
    },
    {
        "num": "۰۶",
        "title": "فعال‌سازی پوسته Flavor و بررسی هویت بصری",
        "badge": "گام ۶: فعال‌سازی پوسته",
        "screen_type": "wp-themes-activate",
        "panel_title": "نمایش ← پوسته‌ها (مدیریت قالب‌ها)",
        "content_lines": [
            "قالب فعال: Flavor — نسخه ۱.۶.۲ (رستوران مستقیم)",
            "شناسایی اتوماتیک افزونه هستهٔ مکمل Flavor Core",
            "آماده‌سازی پالت‌های ۱۲گانه و موتور قالب‌های اختصاصی",
            "اقدام: فشردن دکمهٔ «فعال‌کردن» روی کارت پوسته Flavor"
        ],
        "highlight_box": "پوستهٔ Flavor با موفقیت به عنوان پوسته اصلی فعال شد"
    },
    {
        "num": "۰۷",
        "title": "تنظیم ساختار پیوندهای یکتا روی حالت «نام نوشته»",
        "badge": "گام ۷: پیوندهای یکتا (حیاتی)",
        "screen_type": "wp-permalinks",
        "panel_title": "تنظیمات ← پیوندهای یکتا (تنظیمات ساختار URL)",
        "content_lines": [
            "انتخاب گزینه: «نام نوشته» (Post name) — /%postname%/",
            "چرا حیاتی است؟ عملکرد صحیح روت‌های REST API و کدهای QR میزها",
            "جلوگیری از خطای ۴۰۴ در شیت‌های سفارش آنلاین و منوی سالن",
            "اقدام: فشردن دکمهٔ ذخیرهٔ تغییرات"
        ],
        "highlight_box": "ساختار پیوندها روی «نام نوشته» ثبت شد (تضمین کارکرد QR)"
    },
    {
        "num": "۰۸",
        "title": "درون‌ریزی دمو با ۱ کلیک (انتخاب از بین ۱۲ سبک تخصصی)",
        "badge": "گام ۸: انتخاب و ایمپورت دمو",
        "screen_type": "wp-demo-import",
        "panel_title": "رستوران مستقیم ← درون‌ریزی دموها",
        "content_lines": [
            "کاتالوگ ۱۲ دمو: مدرن، سنتی اصیل، لوکس، پیتزا، فست‌فود، کافه و...",
            "انتخاب دموی شاخص: رستوران مدرن (Modern Restaurant)",
            "درون‌ریزی هوشمند: منوی غذا، قیمت تومان، تصاویر، ساعات کاری و برگه نخست",
            "اقدام: کلیک روی «درون‌ریزی سریع دمو» و صبر برای نوار پیشرفت ۱۰۰٪"
        ],
        "highlight_box": "درون‌ریزی بدون نقص دمو با تمام منوها و تنظیمات فارسی"
    },
    {
        "num": "۰۹",
        "title": "پیکربندی شعبه و تعریف میزهای سالن و تولید بارکد QR",
        "badge": "گام ۹: شعب، میزها و کدهای QR",
        "screen_type": "wp-branches-tables",
        "panel_title": "رستوران مستقیم ← مدیریت میزها و کدهای QR",
        "content_lines": [
            "تعریف شعبه مرکزی: نام رستوران، تلفن، آدرس و محدوده تحویل",
            "ثبت میزهای سالن: میز ۱، میز ۲، سالن VIP، تراس و...",
            "تولید خودکار توکن امنیتی و کد QR قابل چاپ برای هر میز",
            "اقدام: دریافت و پرینت فایل کارت‌های بارکد برای استقرار روی میزها"
        ],
        "highlight_box": "میزها و بارکدهای QR سفارش سر میز فعال و آماده شدند"
    },
    {
        "num": "۱۰",
        "title": "تنظیم درگاه پیامک ایرانی جهت احراز هویت و ورود با OTP",
        "badge": "گام ۱۰: ورود پیامکی (OTP)",
        "screen_type": "wp-sms-settings",
        "panel_title": "رستوران مستقیم ← تنظیمات پیامک و اعتبارسنجی",
        "content_lines": [
            "انتخاب سامانه پیامک: کاوه‌نگار، فراز اس‌ام‌اس، ملی‌پیامک یا قاصدک",
            "درج کلید API و خط خدماتی جهت ارسال سریع پیامک اعتبارسنجی",
            "تنظیم الگو و پترن کد تایید ورود برای مهمانان و مشتریان",
            "تست اتصال پیامک: وضعیت پیامک در حالت آماده و متصل"
        ],
        "highlight_box": "سامانه پیامک ملی متصل شد — ورود بدون پسورد با OTP"
    },
    {
        "num": "۱۱",
        "title": "راه‌اندازی داشبورد لمسی آشپزخانه (KDS) روی تبلت سالن/مطبخ",
        "badge": "گام ۱۱: مانیتور مطبخ (KDS)",
        "screen_type": "wp-kitchen-kds",
        "panel_title": "آشپزخانه رستوران مستقیم ← نمایشگر لمسی سفارشات",
        "content_lines": [
            "آدرس دسترسی اختصاصی سرآشپز: yourdomain.com/kitchen-dashboard/",
            "امکانات فعال: آلارم صوتی سفارش جدید، تفکیک سالن/بیرون‌بر/پیک",
            "نمایش زمان گذشته از ثبت سفارش و دکمه‌های بزرگ لمسی آماده‌سازی",
            "اتصال به پرینتر حرارتی برای چاپ خودکار فیش"
        ],
        "highlight_box": "داشبورد آشپزخانه KDS آماده روی تبلت سالن یا مطبخ"
    },
    {
        "num": "۱۲",
        "title": "مشاهده سایت نهایی و تست یک سفارش واقعی با QR و پرداخت تومانی",
        "badge": "گام ۱۲: تحویل و تست نهایی",
        "screen_type": "wp-storefront-live",
        "panel_title": "سامانه رستوران مستقیم — سفارش آنلاین و سر میز",
        "content_lines": [
            "اسکن آزمایشی بارکد QR میز با گوشی هوشمند",
            "نمایش سریع منو، انتخاب افزودنی‌های غذا و اضافه به سبد",
            "ثبت سفارش سالن بدون معطلی و مشاهده لحظه‌ای فیش در آشپزخانه",
            "سایت شما آماده خدمت‌رسانی به مشتریان است!"
        ],
        "highlight_box": "راه‌اندازی کامل و تحویل نهایی رستوران مستقیم در کمتر از ۱۰ دقیقه"
    }
]

def draw_step_image(step, out_path, size=(1200, 675)):
    w, h = size
    # Dark modern canvas
    img = Image.new('RGB', (w, h), (20, 24, 33))
    draw = ImageDraw.Draw(img)

    # 1. Top Bar / Breadcrumb
    draw.rectangle([(0, 0), (w, 60)], fill=(28, 34, 46))
    draw.line([(0, 60), (w, 60)], fill=(224, 86, 36), width=2)
    
    # Brand Cloche Logo
    draw.ellipse([(30, 15), (60, 45)], fill=(224, 86, 36))
    draw.text((75, 20), "FLAVOR RESTAURANT PLATFORM", fill=(255, 255, 255))
    draw.text((w - 320, 20), "راهنمای گام‌به‌گام نصب و راه‌اندازی", fill=(245, 158, 11))

    # 2. Step Badge Banner
    badge_y = 80
    draw.rounded_rectangle([(w - 280, badge_y), (w - 40, badge_y + 45)], radius=10, fill=(224, 86, 36))
    draw.text((w - 260, badge_y + 12), step["badge"], fill=(255, 255, 255))

    # Big Step Number
    draw.text((40, badge_y - 10), step["num"], fill=(224, 86, 36))
    draw.text((110, badge_y + 8), step["title"], fill=(255, 255, 255))

    # 3. Main Simulated Screen Area (Browser Window Mockup)
    win_x = 40
    win_y = 145
    win_w = w - 80
    win_h = h - win_y - 40

    # Window Header
    draw.rounded_rectangle([(win_x, win_y), (win_x + win_w, win_y + win_h)], radius=14, fill=(30, 36, 48), outline=(55, 65, 81), width=2)
    draw.rectangle([(win_x, win_y), (win_x + win_w, win_y + 40)], fill=(38, 46, 62))
    # Browser dots
    draw.ellipse([(win_x + 15, win_y + 14), (win_x + 27, win_y + 26)], fill=(239, 68, 68))
    draw.ellipse([(win_x + 35, win_y + 14), (win_x + 47, win_y + 26)], fill=(245, 158, 11))
    draw.ellipse([(win_x + 55, win_y + 14), (win_x + 67, win_y + 26)], fill=(16, 185, 129))
    
    # URL bar inside window
    draw.rounded_rectangle([(win_x + 90, win_y + 8), (win_x + win_w - 200, win_y + 32)], radius=6, fill=(24, 28, 38))
    draw.text((win_x + 110, win_y + 11), f"https://your-restaurant.ir/wp-admin/{step['screen_type']}", fill=(156, 163, 175))

    # Window Title / Section Path
    draw.text((win_x + 40, win_y + 60), f"مسیر در وردپرس: {step['panel_title']}", fill=(245, 158, 11))

    # Inner mock panel
    panel_x = win_x + 30
    panel_y = win_y + 100
    panel_w = win_w - 60
    panel_h = win_h - 180
    draw.rounded_rectangle([(panel_x, panel_y), (panel_x + panel_w, panel_y + panel_h)], radius=10, fill=(24, 28, 38), outline=(75, 85, 99), width=1)

    # Bullet steps inside screen
    cur_y = panel_y + 30
    for line in step["content_lines"]:
        draw.ellipse([(panel_x + 30, cur_y + 4), (panel_x + 40, cur_y + 14)], fill=(224, 86, 36))
        draw.text((panel_x + 55, cur_y), line, fill=(243, 244, 246))
        cur_y += 40

    # Success / Highlight banner at bottom of window
    hl_y = win_y + win_h - 65
    draw.rounded_rectangle([(win_x + 30, hl_y), (win_x + win_w - 30, hl_y + 45)], radius=8, fill=(16, 185, 129))
    draw.text((win_x + 60, hl_y + 12), f"وضعیت تأیید: {step['highlight_box']}", fill=(255, 255, 255))

    img.save(out_path, format='JPEG', quality=92, optimize=True)

def generate_all():
    print(f"Generating 12 installation screenshot steps in {OUT_DIR}...")
    for idx, step in enumerate(STEPS, 1):
        filename = f"step-{idx:02d}.jpg"
        out_path = os.path.join(OUT_DIR, filename)
        draw_step_image(step, out_path)
        print(f"  -> Generated {filename} ({step['title']})")
    print("All 12 installation steps generated successfully!")

if __name__ == '__main__':
    generate_all()
