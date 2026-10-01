<?php
/** Noir — contemporary charcoal/copper evening dining. @package Flavor */
defined( 'ABSPATH' ) || exit;
$cook = array(
	array( 'type' => 'cook', 'name' => 'متوسط آبدار', 'price' => 0, 'is_default' => 1 ),
	array( 'type' => 'cook', 'name' => 'کاملاً مغزپخت', 'price' => 0 ),
);
return array(
	'slug' => 'dark-luxe', 'title' => 'رستوران دارک پریمیوم — نوار', 'site_title' => 'نوار',
	'tagline' => 'آشپزی معاصر، نور کم، طعم ماندگار',
	'hero_title' => 'یک شب متفاوت. یک طعم ماندگار.',
	'hero_text' => 'آتشِ گریل، مواد فصل و میزبانی آرام. در نوار، جزئیات کوچک کنار هم قرار می‌گیرند تا شام، بیشتر از یک وعده باشد.',
	'about' => 'نوار برای لحظه‌هایی ساخته شده که می‌خواهی از شتاب روز فاصله بگیری. منوی کوتاه ما حول مواد فصل، پخت دقیق و طعم‌های روشن شکل می‌گیرد. از اولین پیش‌غذا تا آخرین جرعه، هدفمان تجربه‌ای هماهنگ و بی‌تکلف است؛ با نوری ملایم و فضایی برای گفت‌وگو.',
	'branch_name' => 'رستوران نوار — فرشته', 'city' => 'تهران', 'phone' => '02122674560',
	'address' => 'تهران، فرشته، خیابان آقابزرگی، پلاک ۱۸',
	'order_modes' => array( 'dine_in', 'takeaway', 'delivery' ), 'tables' => 8,
	'opening_hours' => array( 'open' => '17:00:00', 'close' => '23:00:00' ),
	'theme_mods' => array(
		'flavor_hero_style' => 'split', 'flavor_hero_badge' => 'شام، به روایت نوار', 'flavor_hero_cta' => 'رزرو یک شب متفاوت', 'flavor_hero_cta2' => 'کشف منو',
		'flavor_header_topbar' => 'شام، از ساعت ۱۷ تا ۲۳  ·  برای مناسبت‌های خاص، پیش از مراجعه با ما هماهنگ کنید',
		'flavor_phone' => '02122674560', 'flavor_address' => 'تهران، فرشته، خیابان آقابزرگی، پلاک ۱۸', 'flavor_hours' => 'همه‌روزه، ۱۷ تا ۲۳', 'flavor_featured_title' => 'امضای آشپزخانه',
	),
	'navigation' => array(
		array( 'label' => 'منوی نوار', 'anchor' => '#menu' ), array( 'label' => 'فلسفهٔ ما', 'anchor' => '#story' ),
		array( 'label' => 'تجربه و رزرو', 'anchor' => '#experience' ), array( 'label' => 'تماس', 'anchor' => '#visit' ),
	),
	'categories' => array( 'از گریل' => 'noir-grill', 'برای شروع' => 'noir-starters', 'پایان شیرین' => 'noir-dessert', 'بار بدون الکل' => 'noir-bar' ),
	'category_images' => array( 'noir-grill' => 'category-grill.jpg', 'noir-starters' => 'category-starters.jpg', 'noir-dessert' => 'category-dessert.jpg', 'noir-bar' => 'category-bar.jpg' ),
	'testimonials' => array(),
	'items' => array(
		flavor_demo_item( 'فیلهٔ گریل و پوره', 'فیلهٔ گوساله، پورهٔ سیب‌زمینی، مارچوبه و سس فلفل', 725000, 'noir-grill', array( 'prep' => 25, 'modifiers' => $cook, 'allergens' => array( 'milk' ) ) ),
		flavor_demo_item( 'قارچ کبابی و فندق', 'قارچ صدفی برشته، روغن سبزی و کرامبل فندق', 255000, 'noir-starters', array( 'prep' => 15, 'allergens' => array( 'nuts' ), 'dietary' => array( 'vegetarian' ) ) ),
		flavor_demo_item( 'تارت شکلات تلخ', 'گاناش شکلات تلخ، خمیر کره‌ای و نمک دریایی', 195000, 'noir-dessert', array( 'prep' => 8, 'allergens' => array( 'milk', 'gluten' ) ) ),
		flavor_demo_item( 'موکتل پشن‌فروت', 'پشن‌فروت، مرکبات تازه و عطر ملایم گل‌محمدی؛ بدون الکل', 145000, 'noir-bar', array( 'prep' => 7 ) ),
		flavor_demo_item( 'فیله و کرهٔ آویشن', 'فیلهٔ گریل با کرهٔ آویشن و سبزیجات فصل', 745000, 'noir-grill', array( 'prep' => 25, 'modifiers' => $cook, 'allergens' => array( 'milk' ) ) ),
		flavor_demo_item( 'قارچ برشته و پارمزان', 'قارچ صدفی، پارمزان و روغن سبزی تازه', 265000, 'noir-starters', array( 'prep' => 15, 'allergens' => array( 'milk' ), 'dietary' => array( 'vegetarian' ) ) ),
		flavor_demo_item( 'تارت شکلات و فندق', 'شکلات تلخ، کرامبل فندق و خمیر سابله', 215000, 'noir-dessert', array( 'prep' => 8, 'allergens' => array( 'nuts', 'milk', 'gluten' ) ) ),
		flavor_demo_item( 'موکتل مرکبات فصل', 'ترکیب مرکبات فصل و عطر رزماری؛ بدون الکل', 125000, 'noir-bar', array( 'prep' => 7 ) ),
	),
	'landing' => array(
		'wordmark' => 'NOIR / AFTER DARK', 'brand_caption' => 'آشپزی معاصر · از غروب', 'brand_icon' => 'spark',
		'eyebrow' => 'شام، به روایت نوار', 'hero_accent' => 'یک طعم ماندگار.',
		'hero_alt' => 'فیلهٔ گریل روی بشقاب مشکی با پوره و سبزیجات',
		'primary_action' => 'reservation', 'primary_label' => 'رزرو میز', 'primary_icon' => 'calendar', 'mobile_label' => 'رزرو میز',
		'secondary_label' => 'کشف منوی نوار', 'perks' => array( 'منوی فصل', 'گریل دقیق', 'میزبانی آرام' ),
		'menu_eyebrow' => 'منتخب‌های فصل', 'menu_title' => 'امضای آشپزخانه', 'menu_text' => 'مواد ساده، تکنیک دقیق و طعم‌هایی که در خاطر می‌مانند.',
		'process_eyebrow' => 'جزئیات، تفاوت را می‌سازند', 'process_title' => 'فراتر از یک شام معمولی.',
		'steps' => array(
			array( 'title' => 'طعم آتش', 'text' => 'حرارت کنترل‌شده و مواد فصل؛ بدون پنهان کردن طعم اصلی.' ),
			array( 'title' => 'فضای گفت‌وگو', 'text' => 'نور ملایم و چیدمانی آرام، برای مکثی طولانی‌تر.' ),
			array( 'title' => 'میزبانی شخصی', 'text' => 'هماهنگی سلیقه، حساسیت غذایی و مناسبت شما قبل از سرو.' ),
		),
		'story_eyebrow' => 'فلسفهٔ نوار', 'story_title' => 'وقتی جزئیات، حرف می‌زنند.', 'story_alt' => 'چیدمان معاصر با صندلی‌های تیره و نورپردازی ملایم',
		'story_caption' => 'تصویر حال‌وهوای طراحی؛ محیط نمونهٔ دمو.',
		'story_values' => array( 'منویی کوتاه با تمرکز روی مواد فصل', 'سرو مرحله‌به‌مرحله و توجه به ریتم میز', 'نوشیدنی‌های دست‌ساز، همگی بدون الکل' ),
		'story_action' => 'reservation', 'story_link' => 'شب خودت را برنامه‌ریزی کن',
		'feature_eyebrow' => 'برای قرار بعدی تو', 'feature_title' => 'میز تو. شب تو.',
		'feature_text' => 'شام دونفره، دورهمی کوچک یا یک مناسبت خاص؛ تاریخ و تعداد مهمان‌ها را انتخاب کن. ساعت‌های قابل رزرو از ظرفیت واقعی شعبه خوانده می‌شوند.',
		'feature_label' => 'انتخاب روز و ساعت', 'faq_title' => 'قبل از آمدن به نوار',
		'faq_text' => 'اگر سؤال دیگری داری، تیم ما تلفنی راهنمایی‌ات می‌کند.',
		'faq' => array(
			array( 'question' => 'برای شام باید از قبل رزرو کنم؟', 'answer' => 'رزرو قبلی به برنامه‌ریزی بهتر کمک می‌کند. تاریخ، تعداد نفرات و ساعت‌های دارای ظرفیت را در صفحهٔ رزرو انتخاب کنید. وضعیت نهایی درخواست طبق تنظیمات رستوران به شما اعلام می‌شود.' ),
			array( 'question' => 'برای تولد یا سالگرد هماهنگی جدا لازم است؟', 'answer' => 'لطفاً پیش از ثبت درخواست با ما تماس بگیرید تا دربارهٔ چیدمان، کیک و تعداد مهمان‌ها هماهنگ کنیم. انتخاب میز یا تزئین خاص بدون هماهنگی تضمین نمی‌شود.' ),
			array( 'question' => 'انتخاب گیاهی یا رسپی متناسب با حساسیت دارم؟', 'answer' => 'ترکیبات هر غذا در منو نوشته شده‌اند. قبل از سفارش، حساسیت و نیاز غذایی خود را اعلام کنید؛ آشپزخانهٔ مشترک داریم و نبود تماس متقاطع را تضمین نمی‌کنیم.' ),
			array( 'question' => 'غذا را بیرون‌بر هم می‌توانم سفارش بدهم؟', 'answer' => 'از منوی کامل، سفارش بیرون‌بر یا ارسال را بررسی کنید. برخی آیتم‌ها برای سرو در سالن مناسب‌ترند؛ موجودی و محدودهٔ ارسال در فرایند سفارش مشخص می‌شود.' ),
		),
		'visit_eyebrow' => 'از غروب، منتظرت هستیم', 'visit_title' => 'یک قرار خوب، از اینجا شروع می‌شود.', 'hours' => 'همه‌روزه، ۱۷ تا ۲۳',
	),
);
