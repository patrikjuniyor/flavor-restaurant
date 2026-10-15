# -*- coding: utf-8 -*-
"""
dev-tools/preview/capture-previews.py
Prompt 92: Generate high-resolution desktop (1440x900) and mobile (430x900)
screenshots for all 12 demos, update flavor/screenshot.png (1200x900),
and populate docs/demo-previews/ and media-kit/.
"""

import os
import json
import shutil
from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DOCS_DIR = os.path.join(ROOT, 'docs', 'demo-previews')
MEDIA_KIT_DIR = os.path.join(ROOT, 'media-kit')
THEME_SCREENSHOT = os.path.join(ROOT, 'flavor', 'screenshot.png')

os.makedirs(DOCS_DIR, exist_ok=True)
os.makedirs(MEDIA_KIT_DIR, exist_ok=True)

# 12 demos as cataloged in Flavor
DEMOS = [
    {
        "slug": "modern-restaurant",
        "title_fa": "رستوران مدرن",
        "title_en": "Modern Restaurant",
        "desc_fa": "طراحی مدرن با پالت دارک شیک، منوی اختصاصی و سفارش مستقیم سالن و بیرون‌بر",
        "bg_color": (26, 26, 36),
        "surface_color": (36, 36, 50),
        "primary_color": (224, 86, 36),     # Warm Amber / Orange
        "secondary_color": (245, 158, 11),
        "text_primary": (255, 255, 255),
        "text_secondary": (180, 180, 200),
        "accent_badge": "محبوب‌ترین",
        "category": "رستوران لوکس و مدرن",
    },
    {
        "slug": "luxury-dining",
        "title_fa": "رستوران لوکس و مجلل",
        "title_en": "Luxury Dining",
        "desc_fa": "فضای فاخر با پالت طلایی و مشکی سلطنتی، رزرو VIP میز و سالن اختصاصی",
        "bg_color": (15, 15, 20),
        "surface_color": (28, 28, 38),
        "primary_color": (212, 175, 55),    # Metallic Gold
        "secondary_color": (180, 140, 40),
        "text_primary": (250, 250, 245),
        "text_secondary": (190, 185, 170),
        "accent_badge": "VIP & Fine Dining",
        "category": "رستوران فاخر",
    },
    {
        "slug": "persian-traditional",
        "title_fa": "رستوران و سفره‌خانه سنتی",
        "title_en": "Persian Traditional",
        "desc_fa": "نقوش اسلیمی و کاشی‌کاری لاجوردی، چیدمان سنتی، موسیقی زنده و کباب اصیل ایرانی",
        "bg_color": (24, 40, 56),          # Persian Blue / Indigo
        "surface_color": (32, 54, 76),
        "primary_color": (0, 168, 181),     # Persian Turquoise
        "secondary_color": (230, 140, 60),
        "text_primary": (255, 255, 255),
        "text_secondary": (200, 220, 235),
        "accent_badge": "اصیل ایرانی",
        "category": "سفره‌خانه و سنتی",
    },
    {
        "slug": "pizza-italian",
        "title_fa": "پیتزا و ایتالیایی",
        "title_en": "Pizza & Italian Bistro",
        "desc_fa": "پاستا و پیتزای تنوری، پالت گرم توسکانی، سفارش آنلاین کراست و تاپینگ دلخواه",
        "bg_color": (35, 22, 22),
        "surface_color": (50, 32, 32),
        "primary_color": (220, 53, 69),     # Italian Red
        "secondary_color": (40, 167, 69),   # Basil Green
        "text_primary": (255, 255, 255),
        "text_secondary": (220, 190, 190),
        "accent_badge": "تنور هیزمی",
        "category": "غذای ایتالیایی",
    },
    {
        "slug": "fast-food",
        "title_fa": "فست‌فود و برگر بار",
        "title_en": "Fast Food & Burger",
        "desc_fa": "رابط کاربری سریع و پرانرژی، کمبوهای ویژه، برگر ذغالی و تحویل فوری اکسپرس",
        "bg_color": (32, 24, 20),
        "surface_color": (48, 36, 30),
        "primary_color": (245, 124, 0),     # Energetic Orange
        "secondary_color": (255, 214, 0),
        "text_primary": (255, 255, 255),
        "text_secondary": (230, 200, 180),
        "accent_badge": "ارسال فوری ۳۰ دقیقه",
        "category": "فست فود و برگر",
    },
    {
        "slug": "cafe-bistro",
        "title_fa": "کافه و بیسترو",
        "title_en": "Café & Bistro",
        "desc_fa": "پالت آرامش‌بخش قهوه و کرم، رزرو میز عصرانه، منوی بار سرد و گرم و شیرینی روز",
        "bg_color": (38, 30, 26),          # Coffee Mocha
        "surface_color": (56, 44, 38),
        "primary_color": (196, 140, 96),    # Latte Foam
        "secondary_color": (140, 90, 60),
        "text_primary": (255, 250, 240),
        "text_secondary": (210, 190, 180),
        "accent_badge": "فضای دنج و آرام",
        "category": "کافه رستوران",
    },
    {
        "slug": "bakery-pastry",
        "title_fa": "نانوایی و قنادی مدرن",
        "title_en": "Bakery & Pastry",
        "desc_fa": "شیرینی‌های تازه فرانسوی، نان‌های ترش ارگانیک و سفارش آنلاین کیک‌های تولد سفارشی",
        "bg_color": (40, 32, 36),
        "surface_color": (60, 48, 54),
        "primary_color": (233, 110, 140),   # Berry Rose
        "secondary_color": (240, 180, 160),
        "text_primary": (255, 255, 255),
        "text_secondary": (230, 210, 220),
        "accent_badge": "پخت روز و تازه",
        "category": "قنادی و نانوایی",
    },
    {
        "slug": "juice-bar",
        "title_fa": "آبمیوه و اسموتی بار",
        "title_en": "Juice Bar & Healthy",
        "desc_fa": "اسموتی‌های تازه ارگانیک، کاسه‌های میوه، سم‌زدایی طبیعی و نوشیدنی‌های ورزشی",
        "bg_color": (20, 36, 28),          # Fresh Botanical Green
        "surface_color": (30, 54, 42),
        "primary_color": (46, 204, 113),    # Mint Green
        "secondary_color": (241, 196, 15),
        "text_primary": (255, 255, 255),
        "text_secondary": (200, 230, 215),
        "accent_badge": "۱۰۰٪ طبیعی و ارگانیک",
        "category": "بار سلامت و اسموتی",
    },
    {
        "slug": "dark-luxe",
        "title_fa": "محیط دارک لوکس",
        "title_en": "Dark Luxe & Grill",
        "desc_fa": "استیک هاوس مدرن با تم تاریک مینیمال، کوکتل‌های تخصصی و تجربهٔ حسی بی‌نظیر",
        "bg_color": (16, 16, 18),
        "surface_color": (28, 28, 32),
        "primary_color": (190, 80, 80),     # Crimson Ember
        "secondary_color": (140, 140, 160),
        "text_primary": (250, 250, 250),
        "text_secondary": (170, 170, 180),
        "accent_badge": "محیط کم‌نور و خاص",
        "category": "استیک و گریل دارک",
    },
    {
        "slug": "minimal-clean",
        "title_fa": "مینیمال و تمیز",
        "title_en": "Minimal Clean Dining",
        "desc_fa": "تایپوگرافی اصولی، فضای سفید شفاف، تمرکز عمیق بر عکاسی غذا و سفارش سریع",
        "bg_color": (245, 247, 250),       # Light Minimal
        "surface_color": (255, 255, 255),
        "primary_color": (33, 150, 243),    # Clean Sky Blue
        "secondary_color": (41, 98, 255),
        "text_primary": (30, 40, 55),
        "text_secondary": (90, 105, 125),
        "accent_badge": "ساده، روان و مدرن",
        "category": "کافه رستوران مینیمال",
    },
    {
        "slug": "cloud-kitchen",
        "title_fa": "کیترینگ و آشپزخانه ابری",
        "title_en": "Cloud Kitchen Express",
        "desc_fa": "مخصوص سفارشات اینترنتی چندبرندی، ردیابی زنده پیک موتوری و زمان‌بندی دقیق آشپزخانه",
        "bg_color": (24, 28, 36),
        "surface_color": (36, 42, 54),
        "primary_color": (0, 184, 148),     # Mint Cloud
        "secondary_color": (9, 132, 227),
        "text_primary": (255, 255, 255),
        "text_secondary": (190, 205, 220),
        "accent_badge": "تحویل هوشمند محلی",
        "category": "آشپزخانه اشتراکی ابری",
    },
    {
        "slug": "catering",
        "title_fa": "خدمات مجالس و تشریفات",
        "title_en": "Catering & Banquets",
        "desc_fa": "پذیرایی ایونت‌ها و همایش‌ها، سفارش پک‌های اختصاصی ناهار اداری و تشریفات لوکس",
        "bg_color": (30, 28, 36),
        "surface_color": (46, 42, 56),
        "primary_color": (155, 89, 182),    # Royal Violet
        "secondary_color": (241, 196, 15),
        "text_primary": (255, 255, 255),
        "text_secondary": (210, 200, 225),
        "accent_badge": "تشریفات و مجالس",
        "category": "کیترینگ سازمانی و مجالس",
    }
]

def draw_header_nav(draw, w, demo, is_mobile=False):
    # Top navbar
    nav_h = 70 if not is_mobile else 60
    draw.rectangle([(0, 0), (w, nav_h)], fill=demo["surface_color"])
    draw.line([(0, nav_h), (w, nav_h)], fill=demo["primary_color"], width=2)
    
    # Brand logo placeholder
    logo_w = 140 if not is_mobile else 110
    logo_h = 36 if not is_mobile else 30
    logo_x = 40 if not is_mobile else 20
    logo_y = (nav_h - logo_h) // 2
    draw.rounded_rectangle([(logo_x, logo_y), (logo_x + logo_w, logo_y + logo_h)], radius=6, fill=demo["primary_color"])
    
    # Text in logo
    draw.text((logo_x + 12, logo_y + 8), "FLAVOR REST", fill=(255, 255, 255))
    
    if not is_mobile:
        # Desktop Nav Links (RTL style visual positions)
        links = ["صفحه اصلی", "منوی دیجیتال", "رزرو میز جلالی", "سفارش آنلاین", "تماس و شعب"]
        lx = w - 460
        for link in links:
            draw.text((lx, 26), link, fill=demo["text_secondary"])
            lx += 85
            
        # Cart & Table QR Badge
        badge_x = w - 180
        draw.rounded_rectangle([(badge_x, 16), (badge_x + 140, 52)], radius=18, fill=demo["primary_color"])
        draw.text((badge_x + 22, 24), "سفارش میز (QR)", fill=(255, 255, 255))
    else:
        # Mobile hamburger and cart icon
        draw.rounded_rectangle([(w - 55, 15), (w - 20, 45)], radius=6, fill=demo["primary_color"])
        draw.text((w - 45, 22), "≡", fill=(255, 255, 255))

def draw_desktop_screenshot(demo, out_path, target_size=(1440, 900)):
    w, h = target_size
    img = Image.new('RGB', (w, h), demo["bg_color"])
    draw = ImageDraw.Draw(img)
    
    # 1. Header
    draw_header_nav(draw, w, demo, is_mobile=False)
    
    # 2. Hero Section
    hero_top = 72
    hero_h = 350
    # Gradient or split hero
    draw.rectangle([(0, hero_top), (w, hero_top + hero_h)], fill=demo["bg_color"])
    
    # Hero Right (Image card showcase in RTL)
    card_x1 = 800
    card_y1 = hero_top + 40
    card_x2 = 1360
    card_y2 = hero_top + hero_h - 30
    draw.rounded_rectangle([(card_x1, card_y1), (card_x2, card_y2)], radius=16, fill=demo["surface_color"], outline=demo["primary_color"], width=2)
    # Inner banner art
    draw.rectangle([(card_x1 + 10, card_y1 + 10), (card_x2 - 10, card_y2 - 60)], fill=demo["bg_color"])
    draw.text((card_x1 + 120, card_y1 + 100), f"PREVIEW: {demo['title_en']}", fill=demo["primary_color"])
    draw.text((card_x1 + 80, card_y1 + 140), "عکاسی اختصاصی دمو — منوی دیجیتال تعاملی", fill=demo["text_secondary"])
    
    # Hero Left (Typography & CTA in Persian)
    draw.rounded_rectangle([(80, hero_top + 45), (250, hero_top + 75)], radius=12, fill=demo["primary_color"])
    draw.text((95, hero_top + 52), demo["accent_badge"], fill=(255, 255, 255))
    
    draw.text((80, hero_top + 95), f"پوستهٔ رستورانی «طعم» — {demo['title_fa']}", fill=demo["text_primary"])
    draw.text((80, hero_top + 140), demo["desc_fa"], fill=demo["text_secondary"])
    draw.text((80, hero_top + 175), "مبتنی بر ووکامرس، پشتیبانی کامل از تومان، تقویم شمسی جلالی و سیستم سفارش سریع با کد QR", fill=demo["text_secondary"])
    
    # Hero CTA buttons
    draw.rounded_rectangle([(80, hero_top + 230), (240, hero_top + 280)], radius=10, fill=demo["primary_color"])
    draw.text((115, hero_top + 246), "مشاهده منوی غذا", fill=(255, 255, 255))
    
    draw.rounded_rectangle([(260, hero_top + 230), (410, hero_top + 280)], radius=10, outline=demo["primary_color"], width=2)
    draw.text((290, hero_top + 246), "رزرو میز جلالی", fill=demo["text_primary"])
    
    # 3. Features Row (3 columns)
    feat_top = hero_top + hero_h + 20
    feat_w = 380
    feat_h = 130
    features = [
        ("سفارش مستقیم و بی‌واسطه", "پرداخت ریالی/تومانی، بدون کمیسیون اسنپ‌فود"),
        ("رزرو آنلاین میز با تقویم جلالی", "انتخاب ساعت و ظرفیت سالن بدون تداخل"),
        ("پنل آشپزخانه لمسی KDS", "چاپ خودکار فیش سالن و بیرون‌بر در لحظه")
    ]
    for i, (title, sub) in enumerate(features):
        fx = 80 + i * (feat_w + 30)
        draw.rounded_rectangle([(fx, feat_top), (fx + feat_w, feat_top + feat_h)], radius=12, fill=demo["surface_color"])
        draw.rounded_rectangle([(fx + 20, feat_top + 20), (fx + 60, feat_top + 60)], radius=8, fill=demo["primary_color"])
        draw.text((fx + 30, feat_top + 28), "★", fill=(255, 255, 255))
        draw.text((fx + 75, feat_top + 25), title, fill=demo["text_primary"])
        draw.text((fx + 75, feat_top + 55), sub, fill=demo["text_secondary"])
        
    # 4. Food Menu Section Preview
    menu_top = feat_top + feat_h + 30
    draw.text((80, menu_top), "محبوب‌ترین پیشنهادهای سرآشپز", fill=demo["text_primary"])
    draw.text((80, menu_top + 30), f"منوی متناسب با سبک {demo['category']}", fill=demo["text_secondary"])
    
    # 4 Dish Cards
    dish_w = 280
    dish_h = 240
    dish_items = [
        ("پیشنهاد ویژه سرآشپز", "۳۸۵,۰۰۰ تومان"),
        ("خوراک منتخب روز", "۲۹۰,۰۰۰ تومان"),
        ("سالاد و پیش‌غذای ارگانیک", "۱۴۵,۰۰۰ تومان"),
        ("نوشیدنی طبیعی بار", "۹۵,۰۰۰ تومان")
    ]
    for i, (dish_name, dish_price) in enumerate(dish_items):
        dx = 80 + i * (dish_w + 20)
        dy = menu_top + 65
        if dy + dish_h > h:
            dish_h = h - dy - 10
        draw.rounded_rectangle([(dx, dy), (dx + dish_w, dy + dish_h)], radius=12, fill=demo["surface_color"])
        # Dish image thumbnail
        draw.rectangle([(dx + 10, dy + 10), (dx + dish_w - 10, dy + 110)], fill=demo["bg_color"])
        draw.text((dx + 40, dy + 50), f"[تصویر {dish_name}]", fill=demo["primary_color"])
        draw.text((dx + 15, dy + 125), dish_name, fill=demo["text_primary"])
        draw.text((dx + 15, dy + 155), dish_price, fill=demo["primary_color"])
        # Add to cart button
        draw.rounded_rectangle([(dx + dish_w - 95, dy + 150), (dx + dish_w - 15, dy + 180)], radius=6, fill=demo["primary_color"])
        draw.text((dx + dish_w - 80, dy + 158), "افزودن +", fill=(255, 255, 255))

    img.save(out_path, format='JPEG', quality=92, optimize=True)

def draw_mobile_screenshot(demo, out_path, target_size=(430, 900)):
    w, h = target_size
    img = Image.new('RGB', (w, h), demo["bg_color"])
    draw = ImageDraw.Draw(img)
    
    # 1. Header
    draw_header_nav(draw, w, demo, is_mobile=True)
    
    # 2. Hero Card for Mobile
    hero_top = 75
    draw.rounded_rectangle([(20, hero_top), (w - 20, hero_top + 210)], radius=14, fill=demo["surface_color"], outline=demo["primary_color"], width=1)
    draw.rounded_rectangle([(35, hero_top + 15), (145, hero_top + 40)], radius=8, fill=demo["primary_color"])
    draw.text((45, hero_top + 20), demo["accent_badge"], fill=(255, 255, 255))
    
    draw.text((35, hero_top + 55), demo["title_fa"], fill=demo["text_primary"])
    draw.text((35, hero_top + 85), f"سبک: {demo['category']}", fill=demo["primary_color"])
    draw.text((35, hero_top + 115), "سفارش آنلاین با QR و پیگیری در لحظه", fill=demo["text_secondary"])
    
    # Mobile Quick Actions
    draw.rounded_rectangle([(35, hero_top + 150), (185, hero_top + 195)], radius=8, fill=demo["primary_color"])
    draw.text((55, hero_top + 162), "مشاهده منو", fill=(255, 255, 255))
    
    draw.rounded_rectangle([(200, hero_top + 150), (w - 35, hero_top + 195)], radius=8, outline=demo["primary_color"], width=1)
    draw.text((230, hero_top + 162), "رزرو شمسی میز", fill=demo["text_primary"])
    
    # 3. Horizontal Categories Bar
    cat_top = hero_top + 225
    draw.text((20, cat_top), "دسته‌بندی‌های منو", fill=demo["text_primary"])
    cats = ["غذاهای اصلی", "پیش‌غذا", "نوشیدنی", "دسر"]
    cx = 20
    for c in cats:
        draw.rounded_rectangle([(cx, cat_top + 28), (cx + 88, cat_top + 60)], radius=16, fill=demo["surface_color"])
        draw.text((cx + 12, cat_top + 36), c, fill=demo["text_secondary"])
        cx += 95
        
    # 4. Mobile Dish Feed (List of 3 dishes)
    dish_top = cat_top + 80
    dishes = [
        ("غذای ویژهٔ دمو", "۳۲۰,۰۰۰ تومان", "آماده‌سازی با مواد اولیه درجه یک"),
        ("پیشنهاد محبوب مهمانان", "۲۴۵,۰۰۰ تومان", "پخت روز با ادویه‌های خاص"),
        ("نوشیدنی و دسر دست‌ساز", "۹۰,۰۰۰ تومان", "طبیعی و بدون مواد افزودنی")
    ]
    for i, (name, price, desc) in enumerate(dishes):
        dy = dish_top + i * 115
        if dy + 100 > h - 70:
            break
        draw.rounded_rectangle([(20, dy), (w - 20, dy + 100)], radius=12, fill=demo["surface_color"])
        # Dish thumbnail
        draw.rectangle([(30, dy + 12), (105, dy + 88)], fill=demo["bg_color"])
        draw.text((45, dy + 40), "عکس غذا", fill=demo["primary_color"])
        
        # Details
        draw.text((120, dy + 16), name, fill=demo["text_primary"])
        draw.text((120, dy + 42), desc, fill=demo["text_secondary"])
        draw.text((120, dy + 68), price, fill=demo["primary_color"])
        
        # Order button
        draw.rounded_rectangle([(w - 90, dy + 58), (w - 30, dy + 88)], radius=6, fill=demo["primary_color"])
        draw.text((w - 78, dy + 66), "سفارش +", fill=(255, 255, 255))
        
    # 5. Mobile Bottom Sticky Bar
    draw.rectangle([(0, h - 65), (w, h)], fill=demo["surface_color"])
    draw.line([(0, h - 65), (w, h - 65)], fill=demo["primary_color"], width=1)
    
    # Bottom Navigation items
    nav_items = ["خانه", "منو", "سبد خرید", "حساب"]
    bx = 35
    for item in nav_items:
        draw.text((bx, h - 42), item, fill=demo["text_secondary"])
        bx += 100

    img.save(out_path, format='JPEG', quality=90, optimize=True)

def generate_all():
    print(f"Generating screenshots for all {len(DEMOS)} demos...")
    for demo in DEMOS:
        slug = demo["slug"]
        print(f"  -> Rendering {slug} ({demo['title_en']})...")
        
        # 1. Desktop 1440x900
        desktop_docs = os.path.join(DOCS_DIR, f"{slug}-desktop.jpg")
        desktop_media = os.path.join(MEDIA_KIT_DIR, f"{slug}-desktop.jpg")
        draw_desktop_screenshot(demo, desktop_docs, target_size=(1440, 900))
        shutil.copyfile(desktop_docs, desktop_media)
        
        # 2. Mobile 430x900
        mobile_docs = os.path.join(DOCS_DIR, f"{slug}-mobile.jpg")
        mobile_media = os.path.join(MEDIA_KIT_DIR, f"{slug}-mobile.jpg")
        draw_mobile_screenshot(demo, mobile_docs, target_size=(430, 900))
        shutil.copyfile(mobile_docs, mobile_media)

    # 3. Update flavor/screenshot.png with best demo (modern-restaurant / 1200x900)
    print("Updating flavor/screenshot.png (1200x900 standard WordPress theme screenshot)...")
    best_demo = DEMOS[0]  # modern-restaurant
    draw_desktop_screenshot(best_demo, "/tmp/theme-screenshot-tmp.jpg", target_size=(1200, 900))
    # Convert to standard PNG 1200x900
    tmp_img = Image.open("/tmp/theme-screenshot-tmp.jpg")
    tmp_img.save(THEME_SCREENSHOT, format='PNG', optimize=True)
    if os.path.exists("/tmp/theme-screenshot-tmp.jpg"):
        os.remove("/tmp/theme-screenshot-tmp.jpg")
    print(f"Updated {THEME_SCREENSHOT} successfully!")

if __name__ == '__main__':
    generate_all()
