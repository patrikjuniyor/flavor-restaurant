<?php
/** Pack — online-first food, delivery and pickup, no dining-room fiction. @package Flavor */
defined( 'ABSPATH' ) || exit;
return array(
	'slug' => 'cloud-kitchen', 'title' => 'آشپزخانه ابری و دلیوری — پک', 'site_title' => 'پک', 'tagline' => 'غذای خوب، برای هرجایی که تویی',
	'hero_title' => 'خوب بخور. هرجا که هستی.',
	'hero_text' => 'آشپزخانهٔ پک برای سفارش آنلاین ساخته شده؛ بول‌های تازه، ساندویچ‌های خوش‌طعم و انتخاب‌هایی برای روزهای شلوغ. بدون سالن، با تمام توجه به غذای تو.',
	'about' => 'پک یعنی یک آشپزخانه با تمرکز روی غذایی که همراه تو می‌آید. به جای سالن بزرگ، روی مواد اولیه، آماده‌سازی و بسته‌بندی مناسب کار می‌کنیم. منو را طوری می‌چینیم که از یک ناهار پشت میز کار تا یک شام راحت در خانه، انتخاب روشنی داشته باشی. ترکیبات را بخوان، نیاز غذایی‌ات را بگو و روش دریافت را خودت انتخاب کن.',
	'branch_name' => 'آشپزخانهٔ پک — ونک', 'city' => 'تهران', 'phone' => '02188653210', 'address' => 'تهران، ونک، خیابان ملاصدرا، کوچهٔ شیراز، پلاک ۲۴؛ آشپزخانهٔ بیرون‌بر، بدون سالن',
	'order_modes' => array( 'takeaway', 'delivery' ), 'tables' => 0,
	'opening_hours' => array( 'open' => '11:00:00', 'close' => '22:00:00' ),
	// Pack amounts are toman; importer converts them to the configured Core storage unit.
	'delivery_zones' => array( array( 'name' => 'محدودهٔ ونک و اطراف', 'zone_type' => 'neighborhoods', 'neighborhoods' => array( 'ونک', 'یوسف‌آباد', 'ملاصدرا', 'جردن' ), 'delivery_fee' => 35000, 'min_order' => 150000, 'estimated_minutes' => 45, 'is_active' => 1 ) ),
	'theme_mods' => array(
		'flavor_hero_style' => 'split', 'flavor_hero_badge' => 'آشپزخانه اینجاست؛ میز، هرجا که تویی', 'flavor_hero_cta' => 'شروع سفارش', 'flavor_hero_cta2' => 'بررسی محدودهٔ ارسال',
		'flavor_header_topbar' => 'فقط بیرون‌بر و ارسال  ·  همه‌روزه ۱۱ تا ۲۲', 'flavor_phone' => '02188653210', 'flavor_address' => 'تهران، ونک، خیابان ملاصدرا، کوچهٔ شیراز، پلاک ۲۴؛ آشپزخانهٔ بیرون‌بر، بدون سالن', 'flavor_hours' => 'همه‌روزه، ۱۱ تا ۲۲', 'flavor_featured_title' => 'پکِ امروزت را پیدا کن',
	),
	'navigation' => array( array( 'label' => 'منوی پک', 'anchor' => '#menu' ), array( 'label' => 'چطور سفارش بدهم؟', 'anchor' => '#process' ), array( 'label' => 'محدودهٔ ارسال', 'anchor' => '#coverage' ), array( 'label' => 'پشت پک', 'anchor' => '#story' ), array( 'label' => 'تماس', 'anchor' => '#visit' ) ),
	'categories' => array( 'بول‌های رنگی' => 'pack-bowls', 'ساندویچ' => 'pack-sandwich', 'سالاد' => 'pack-salad', 'نوشیدنی' => 'pack-drinks' ),
	'category_images' => array( 'pack-bowls' => 'category-bowls.jpg', 'pack-sandwich' => 'category-sandwich.jpg', 'pack-salad' => 'category-salad.jpg', 'pack-drinks' => 'category-drinks.jpg' ), 'testimonials' => array(),
	'items' => array(
		flavor_demo_item( 'بول کینوا و نخود', 'کینوا، نخود، گوجه، خیار، زیتون و سس لیمو', 195000, 'pack-bowls', array( 'prep' => 12, 'dietary' => array( 'vegetarian' ) ) ),
		flavor_demo_item( 'ساندویچ مرغ کلاسیک', 'مرغ، کاهو و سس مایونز در نان نرم', 175000, 'pack-sandwich', array( 'prep' => 15, 'allergens' => array( 'gluten', 'eggs' ) ) ),
		flavor_demo_item( 'سالاد مرغ و پارمزان', 'کاهو، مرغ گریل، پارمزان و نان برشته', 205000, 'pack-salad', array( 'prep' => 12, 'allergens' => array( 'milk', 'gluten' ) ) ),
		flavor_demo_item( 'لیموناد تازه', 'لیمو، آب گازدار و شیرینی ملایم', 75000, 'pack-drinks', array( 'prep' => 5 ) ),
		flavor_demo_item( 'بول کینوا با سبزی معطر', 'کینوا و نخود با خیار، گوجه، زیتون و سبزی تازه', 205000, 'pack-bowls', array( 'prep' => 12, 'dietary' => array( 'vegetarian' ) ) ),
		flavor_demo_item( 'ساندویچ مرغ با سس لیمو', 'مرغ، کاهو و سس مایونز لیمویی در نان نرم', 185000, 'pack-sandwich', array( 'prep' => 15, 'allergens' => array( 'gluten', 'eggs' ) ) ),
		flavor_demo_item( 'سالاد مرغ با سس سبک', 'کاهو، مرغ گریل و پارمزان با سس لیموی سبک', 215000, 'pack-salad', array( 'prep' => 12, 'allergens' => array( 'milk', 'gluten' ) ) ),
		flavor_demo_item( 'موکتل مرکبات', 'مرکبات تازه، نعنا و آب گازدار؛ بدون الکل', 85000, 'pack-drinks', array( 'prep' => 5 ) ),
	),
	'landing' => array(
		'wordmark' => 'PACK / GOOD FOOD. ANYWHERE.', 'brand_caption' => 'آشپزخانهٔ آنلاین', 'brand_icon' => 'bag', 'eyebrow' => 'آشپزخانه اینجاست؛ میز، هرجا که تویی', 'hero_accent' => 'هرجا که هستی.', 'hero_alt' => 'بول‌های کینوا، نخود و سبزیجات کنار ظرف بیرون‌بر',
		'primary_action' => 'menu', 'primary_label' => 'شروع سفارش', 'primary_icon' => 'bag', 'mobile_label' => 'سفارش آنلاین', 'default_mode' => 'delivery', 'secondary_label' => 'بررسی محدودهٔ ارسال', 'perks' => array( 'بدون سالن', 'بیرون‌بر و ارسال', 'ترکیبات روشن' ),
		'menu_eyebrow' => 'یک انتخاب خوش‌طعم', 'menu_title' => 'پکِ امروزت را پیدا کن.', 'menu_text' => 'بول، ساندویچ، سالاد یا یک نوشیدنی کنار غذا؛ جزئیات را ببین و انتخاب کن.', 'menu_link' => 'رفتن به سفارش آنلاین',
		'process_eyebrow' => 'از انتخاب تا دریافت', 'process_title' => 'سفارش، در سه قدم روشن.',
		'steps' => array( array( 'title' => 'پکِ خودت را انتخاب کن', 'text' => 'ترکیبات و گزینه‌های هر آیتم را ببین و آن را به سبد اضافه کن.' ), array( 'title' => 'روش دریافت را مشخص کن', 'text' => 'بیرون‌بر یا ارسال؛ محله و هزینهٔ ارسال هنگام سفارش بررسی می‌شوند.' ), array( 'title' => 'اطلاعات را نهایی کن', 'text' => 'اطلاعات تماس و روش پرداخت را کامل کن؛ ثبت نهایی در فرایند سفارش انجام می‌شود.' ) ),
		'story_eyebrow' => 'پشت پک', 'story_title' => 'سالن نداریم. تمرکز داریم.', 'story_alt' => 'آشپز در حال آماده‌سازی غذا در آشپزخانه', 'story_caption' => 'تصویر نمونهٔ فرایند آشپزی؛ عکس تیم واقعی پک نیست.',
		'story_values' => array( 'منویی طراحی‌شده برای بیرون‌بر و ارسال', 'توجه به بسته‌بندی و نظم آماده‌سازی', 'اطلاعات روشن دربارهٔ ترکیبات و نیاز غذایی' ), 'story_action' => 'menu', 'story_link' => 'سفارش امروز را شروع کن',
		'feature_eyebrow' => 'پک برای تیم تو', 'feature_title' => 'ناهارِ میز کار، بی‌دردسرتر.', 'feature_text' => 'برای سفارش گروهی یا برنامهٔ ناهار تیم، پیش از پرداخت با ما هماهنگ کن. تعداد، زمان دریافت و ترکیب غذاها را با هم بررسی می‌کنیم؛ تأیید سفارش گروهی نیاز به هماهنگی دارد.', 'feature_label' => 'هماهنگی سفارش گروهی',
		'coverage_eyebrow' => 'اول محله، بعد سفارش', 'coverage_title' => 'پک به محلهٔ تو می‌رسد؟', 'coverage_text' => 'محدوده، هزینه و حداقل سفارش از تنظیمات واقعی شعبه خوانده می‌شوند. محله را وارد کن تا پیش از سفارش بررسی شود.',
		'faq_title' => 'سؤال‌های قبل از سفارش', 'faq_text' => 'چند نکته برای انتخاب و دریافت راحت‌تر.',
		'faq' => array(
			array( 'question' => 'پک سالن یا میز برای صرف غذا دارد؟', 'answer' => 'خیر؛ این دمو برای آشپزخانهٔ ابری، بیرون‌بر و ارسال طراحی شده است. امکان رزرو میز یا صرف غذا در سالن معرفی نمی‌شود.' ),
			array( 'question' => 'ارسال به محلهٔ من امکان‌پذیر است؟', 'answer' => 'محله را در بخش محدودهٔ ارسال یا هنگام سفارش بررسی کن. مناطق و هزینه‌ها از تنظیمات شعبه خوانده می‌شوند. زمان اعلام‌شده برآورد تنظیم‌شدهٔ منطقه است، نه رهگیری زندهٔ پیک.' ),
			array( 'question' => 'زمان آماده‌سازی با زمان رسیدن یکی است؟', 'answer' => 'خیر. زمان درج‌شده کنار غذا فقط زمان آماده‌سازی نمونه است. زمان ارسال به منطقه، حجم سفارش و شرایط همان روز بستگی دارد و زمان تضمین‌شده نیست.' ),
			array( 'question' => 'برای تیم یا شرکت سفارش گروهی می‌گیرید؟', 'answer' => 'برای هماهنگی تعداد، ترکیب غذا، روز و ساعت دریافت، قبل از پرداخت تماس بگیر. ثبت خودکار یک سفارش تکی به معنی تأیید خدمات گروهی یا قرارداد سازمانی نیست.' ),
		), 'visit_eyebrow' => 'آشپزخانهٔ ما', 'visit_title' => 'یک پک خوب، همین نزدیکی.', 'hours' => 'همه‌روزه، ۱۱ تا ۲۲',
	),
);
