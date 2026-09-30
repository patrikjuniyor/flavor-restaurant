<?php
/**
 * Importable demo packs for Flavor Restaurant Platform.
 * Prices are Toman.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a menu item row.
 *
 * @param string               $name Name.
 * @param string               $desc Description.
 * @param int                  $price Toman.
 * @param string               $cat Category slug.
 * @param array<string, mixed> $extra Extra.
 * @return array<string, mixed>
 */
function flavor_demo_item( string $name, string $desc, int $price, string $cat, array $extra = array() ): array {
	return array_merge(
		array(
			'name'        => $name,
			'description' => $desc,
			'price'       => $price,
			'category'    => $cat,
			'prep'        => 15,
			'modifiers'   => array(),
			'dietary'     => array(),
			'schedule'    => array(),
		),
		$extra
	);
}

/**
 * All demos.
 *
 * @return array<string, array<string, mixed>>
 */
function flavor_demo_catalog(): array {
	$size_mod = array(
		array( 'type' => 'size', 'name' => 'یک‌نفره (استاندارد)', 'price' => 0, 'is_default' => 1 ),
		array( 'type' => 'size', 'name' => 'دو‌نفره (دوبل)', 'price' => 45000, 'is_default' => 0 ),
	);

	$cook_mod = array(
		array( 'type' => 'cook', 'name' => 'متوسط آبدار (Medium)', 'price' => 0, 'is_default' => 1 ),
		array( 'type' => 'cook', 'name' => 'کاملاً مغزپخت (Well Done)', 'price' => 0 ),
	);

	return array(
		'modern-restaurant' => array(
			'slug'         => 'modern-restaurant',
			'title'        => 'رستوران مدرن',
			'site_title'   => 'رستوران و کافه گندم',
			'tagline'      => 'ترکیب اصالت ایرانی با آشپزی مدرن معاصر',
			'hero_title'   => 'تجربه طعم‌های مدرن در فضایی گرم و دلنشین',
			'hero_text'    => 'غذاهای تلفیقی، برگرهای گریل دودی و پاستاهای دست‌ساز با تازه‌ترین مواد ارگانیک.',
			'about'        => 'رستوران گندم با الهام از طعم‌های غنی اقلیم‌های مختلف و بکارگیری تکنیک‌های نوین آشپزی، تجربه‌ای متفاوت از غذاخوری مدرن را برای شما خلق می‌کند.',
			'branch_name'  => 'شعبه مرکزی شهرک غرب',
			'city'         => 'تهران',
			'phone'        => '02188001234',
			'address'      => 'شهرک غرب، بلوار دادمان، نبش خیابان هرمزان',
			'theme_mods'   => array(
				'flavor_hero_badge'      => 'آشپزی معاصر با ریشه‌های ایرانی',
				'flavor_hero_style'      => 'fullscreen',
				'flavor_hero_cta2'       => 'رزرو تجربه گندم',
				'flavor_header_topbar'   => 'ارسال رایگان سفارش‌های بالای ۷۰۰ هزار تومان در محدوده شهرک غرب',
				'flavor_phone'           => '02188001234',
				'flavor_address'         => 'شهرک غرب، بلوار دادمان، نبش خیابان هرمزان',
				'flavor_featured_title'  => 'منتخب‌های امروز سرآشپز',
			),
			'categories'      => array( 'غذاهای اصلی' => 'main', 'برگر و ساندویچ' => 'burger', 'پاستا و پیتزا' => 'pasta', 'پیش‌غذا و سالاد' => 'starters', 'نوشیدنی بار' => 'drinks' ),
			'category_images' => array(
				'main'     => 'category-main.jpg',
				'burger'   => 'category-burger.jpg',
				'pasta'    => 'category-pasta.jpg',
				'starters' => 'category-starters.jpg',
				'drinks'   => 'category-drinks.jpg',
			),
			'testimonials' => array(
				array( 'name' => 'سارا محمدی', 'text' => 'استیک فیله مینیون و سس قارچ بی‌نظیر بود؛ فضای آرام و پذیرایی بسیار محترمانه.' ),
				array( 'name' => 'کیان رضایی', 'text' => 'سفارش آنلاین با بسته‌بندی عایق در کمتر از ۲۵ دقیقه رسید؛ دقیقاً مثل سرو در سالن داغ بود.' ),
			),
			'items'        => array(
				flavor_demo_item( 'استیک فیله گوساله', '۲۵۰ گرم فیله گریل با سبزیجات بخارپز و سس فلفل سیاه', 485000, 'main', array( 'modifiers' => $cook_mod ) ),
				flavor_demo_item( 'چیکن پارمزان کریسپی', 'سینه مرغ سوخاری با سس مارینارا و پنیر موزارلا', 295000, 'main' ),
				flavor_demo_item( 'پاستا پنه آلفردو مرغ', 'پنه با سس خامه قارچ و پنیر پارمزان ایتالیایی', 260000, 'pasta' ),
				flavor_demo_item( 'اسمش برگر دوبل گندم', 'دو لایه گوشت گوساله دست‌ساز با پنیر چدار و سس ترافل', 285000, 'burger', array( 'modifiers' => $size_mod ) ),
				flavor_demo_item( 'پیتزا سیر و استیک', 'خمیر دست‌ساز ناپلی با راسته گوساله، سیر تازه و قارچ', 340000, 'pasta' ),
				flavor_demo_item( 'سالاد سزار گریل', 'کاهو رسمی، فیله گریل، نان سیر و سس سزار خانگی', 185000, 'starters' ),
				flavor_demo_item( 'سیب‌زمینی ترافل و پارمزان', 'سیب دست‌ساز با روغن ترافل اصل و پنیر پارمزان', 115000, 'starters' ),
				flavor_demo_item( 'موهیتو دست‌ساز تازه', 'نعنا تازه، لیمو، عسل و آب گازدار', 65000, 'drinks' ),
			),
		),

		'luxury-dining' => array(
			'slug'         => 'luxury-dining',
			'title'        => 'رستوران لوکس و مجلل',
			'site_title'   => 'رستوران رویال لانژ',
			'tagline'      => 'هنر میزبانی فاخر و منوی چشایی اختصاصی',
			'hero_title'   => 'ضیافتی شاهانه از اصیل‌ترین طعم‌های بین‌المللی',
			'hero_text'    => 'میزبانی VIP، اجرای پیانو زنده، چشم‌انداز پانوراما و منوی منتخب سرآشپزان بین‌المللی.',
			'about'        => 'رویال لانژ نماد شکوه و اصالت در هنر هتلداری و رستوران‌داری لوکس است. کلیه رسپی‌ها توسط سرآشپز اجرایی با استانداردهای میشلن تدوین شده‌اند.',
			'branch_name'  => 'شعبه نیاوران VIP',
			'city'         => 'تهران',
			'phone'        => '02122334455',
			'address'      => 'نیاوران، مژده، برج رویال',
			'theme_mods'   => array(
				'flavor_hero_badge'      => 'تجربه اختصاصی سرآشپز اجرایی',
				'flavor_hero_style'      => 'fullscreen',
				'flavor_hero_cta2'       => 'رزرو میز VIP',
				'flavor_header_topbar'   => 'پذیرش با رزرو قبلی  •  اجرای پیانو زنده از ساعت ۲۰',
				'flavor_phone'           => '02122334455',
				'flavor_address'         => 'نیاوران، مژده، برج رویال',
				'flavor_featured_title'  => 'امضای سرآشپز',
			),
			'categories'      => array( 'منوی سرآشپز' => 'chef', 'دریایی و خاویار' => 'seafood', 'گوشت و استیک' => 'steak', 'دسر دست‌ساز' => 'dessert', 'نوشیدنی اختصاصی' => 'mocktails' ),
			'category_images' => array(
				'chef'      => 'category-chef.jpg',
				'seafood'   => 'category-seafood.jpg',
				'steak'     => 'category-steak.jpg',
				'dessert'   => 'category-dessert.jpg',
				'mocktails' => 'category-mocktails.jpg',
			),
			'testimonials' => array(
				array( 'name' => 'دکتر احسان فرهمند', 'text' => 'بهترین تجربه فاین‌داینینگ تهران؛ خاویار و استیک ریب‌آی با کیفیتی بی‌رقیب سرو شد.' ),
				array( 'name' => 'مهندس نیلوفر راد', 'text' => 'مراسم سالگرد عقدمان را در سالن خصوصی برگزار کردیم؛ پذیرایی بی‌نقص و باشکوه بود.' ),
			),
			'items'        => array(
				flavor_demo_item( 'استیک ریب‌آی واگیو', 'گوشت پرورده ممتاز با پوره سیب‌زمینی ترافلی و سس ردواین', 890000, 'steak', array( 'modifiers' => $cook_mod ) ),
				flavor_demo_item( 'سالمون نروژی گریل', 'فیله سالمون تازه با سس هلندی، خاویار خلیج فارس و مارچوبه', 640000, 'seafood' ),
				flavor_demo_item( 'شاه‌میگو بوشهر گریل', 'میگوی جامبو گریل‌شده با سس کره لیمویی و سبزیجات ساطوری', 580000, 'seafood' ),
				flavor_demo_item( 'بیف استروگانف اشرافی', 'مغز راسته گوساله با قارچ صدفی و چیپس رشته‌ای خانگی', 420000, 'chef' ),
				flavor_demo_item( 'سالاد میگو آووکادو', 'میگو گریل، آووکادو هاس، دانه انار و سس درسینگ مرکبات', 260000, 'chef' ),
				flavor_demo_item( 'تیرامیسو کلاسیک ونیزی', 'لیدی‌فینگر دست‌ساز با ماسکارپونه ایتالیایی و شات اسپرسو', 160000, 'dessert' ),
				flavor_demo_item( 'سوفله شکلات تلخ ۸۵٪', 'سوفله گرم شکلاتی همراه با اسکوپ بستنی وانیل ماداگاسکار', 175000, 'dessert' ),
				flavor_demo_item( 'موکتل پشن‌فروت زعفران', 'عصاره پشن‌فروت، گلاب درجه یک قمصر و عسل کوهی', 95000, 'mocktails' ),
			),
		),

		'persian-traditional' => array(
			'slug'         => 'persian-traditional',
			'title'        => 'رستوران سنتی و اصیل ایرانی',
			'site_title'   => 'سفره‌خانه سنتی نقش جهان',
			'tagline'      => 'عطر برنج طارم و زعفران ناب ایرانی در ظروف مسی',
			'hero_title'   => 'لذت چلوکباب‌های ذغالی و خورشت‌های جاافتاده',
			'hero_text'    => 'دیزی سنگی تبریز، باقالی‌پلو با ماهیچه، نان سنگک تازه و چای قندپهلو در فضایی دل‌انگیز.',
			'about'        => 'نقش جهان با حفظ اصالت دستورپخت‌های مادربزرگ‌ها، برنج دم‌کشیده با زعفران اعلا و گوشت گوسفندی کشتار روز را بر سر سفره‌های گرم ایرانی می‌آورد.',
			'branch_name'  => 'شعبه میدان نقش جهان',
			'city'         => 'اصفهان',
			'phone'        => '03132221100',
			'address'      => 'اصفهان، میدان نقش جهان، خیابان حافظ',
			'theme_mods'   => array(
				'flavor_hero_badge'    => 'از دل اصفهان، بر سر سفره شما',
				'flavor_hero_style'    => 'fullscreen',
				'flavor_hero_cta2'     => 'رزرو سفره و تخت سنتی',
				'flavor_header_topbar' => 'هر روز از ساعت ۱۲ تا ۲۳:۳۰ میزبان شما هستیم  •  موسیقی زنده پنج‌شنبه‌ها',
				'flavor_phone'          => '03132221100',
				'flavor_address'        => 'اصفهان، میدان نقش جهان، خیابان حافظ',
				'flavor_featured_title' => 'گزیده سفره نقش جهان',
			),
			'categories'   => array( 'کباب‌های ذغالی' => 'kebab', 'چلو خورشت اصیل' => 'stew', 'پیش‌غذا و مخلفات' => 'side', 'نوشیدنی و شربتخانه' => 'drinks' ),
			'testimonials' => array(
				array( 'name' => 'حاج مرتضی حسینی', 'text' => 'کباب برگ و شیشلیک بی‌نظیر؛ گوشت کاملاً پنبه و بدون ذره‌ای بو.' ),
				array( 'name' => 'مریم صادقی', 'text' => 'دیزی سنگی با نان تازه و ترشی بادمجان یاد خاطرات خانه پدری را زنده کرد.' ),
			),
			'items'        => array(
				flavor_demo_item( 'چلو کباب شیشلیک مخصوص', 'شش تکه دنده گوسفندی نرم با برنج زعفرانی طارم و کره محلی', 540000, 'kebab' ),
				flavor_demo_item( 'چلو کباب برگ ممتاز', 'یک سیخ راسته گوسفندی مرینیت‌شده با زعفران و کره', 460000, 'kebab' ),
				flavor_demo_item( 'چلو کباب کوبیده زعفرانی (دو سیخ)', 'ترکیب گوشت قلوه‌گاه و راسته با سماق تبریز و گوجه ذغالی', 285000, 'kebab' ),
				flavor_demo_item( 'باقالی پلو با ماهیچه گوسفندی', 'ماهیچه مغزپخت در سس زعفران همراه با باقالی‌پلو شویددار', 490000, 'stew' ),
				flavor_demo_item( 'دیزی سنگی مخصوص', 'گوشت گردن، نخود، لوبیا و دنبه همراه با سنگک داغ و سبزی خوردن', 240000, 'stew' ),
				flavor_demo_item( 'چلو جوجه کباب فیله زعفرانی', 'فیله سینه بی‌استخوان خوابانده در آب‌لیمو، پیاز و زعفران', 290000, 'kebab' ),
				flavor_demo_item( 'کشک بادمجان ذغالی', 'بادمجان کبابی با کشک بیرجند، نعناداغ و گردوی تویسرکان', 110000, 'side' ),
				flavor_demo_item( 'دوغ سنتی محلی با نعنا و گل‌محمدی', 'دوغ طبیعی بدون گاز در پارچ مسی خنک', 35000, 'drinks' ),
			),
		),

		'fast-food' => array(
			'slug'         => 'fast-food',
			'title'        => 'فست‌فود و برگر بار',
			'site_title'   => 'برگر باکس فایو',
			'tagline'      => 'برگرهای اسمش، نان بریوش کره و سوخاری‌های داغ',
			'hero_title'   => 'طعم واقعی برگر اسمش و سوخاری‌های کرانچی',
			'hero_text'    => 'آماده‌سازی فوری زیر ۱۰ دقیقه. کمبوهای هیجان‌انگیز، سس‌های دست‌ساز و سیب‌زمینی پنیری.',
			'about'        => 'برگر باکس فایو با گوشت تازه گوساله روز و نان‌های روزپخت، سریع‌ترین و لذیذترین تجربه غذای آماده را به دست شما می‌رساند.',
			'branch_name'  => 'شعبه سعادت‌آباد',
			'city'         => 'تهران',
			'phone'        => '02122110011',
			'address'      => 'سعادت‌آباد، سرو غربی، پلاک ۲۴',
			'theme_mods'   => array(
				'flavor_hero_badge'      => 'داغ، تازه، آماده زیر ۱۰ دقیقه',
				'flavor_hero_style'      => 'fullscreen',
				'flavor_hero_cta2'       => 'رزرو جشن تولد',
				'flavor_header_topbar'   => 'کمبو دو نفره امروز با ارسال رایگان  ⚡  فقط تا ساعت ۲۳',
				'flavor_phone'           => '02122110011',
				'flavor_address'         => 'سعادت‌آباد، سرو غربی، پلاک ۲۴',
				'flavor_featured_title'  => 'پرفروش‌های باکس فایو',
			),
			'categories'      => array( 'برگر دست‌ساز' => 'burger', 'مرغ و سوخاری' => 'chicken', 'ساندویچ و هات‌داگ' => 'sandwich', 'پیش‌غذا و سیب' => 'sides', 'نوشیدنی و شیک' => 'drinks' ),
			'category_images' => array(
				'burger'   => 'category-burger.jpg',
				'chicken'  => 'category-chicken.jpg',
				'sandwich' => 'category-sandwich.jpg',
				'sides'    => 'category-sides.jpg',
				'drinks'   => 'category-drinks.jpg',
			),
			'testimonials' => array(
				array( 'name' => 'امیرحسین پارسا', 'text' => 'بهترین اسمش برگر تهران؛ آبدار، پنیری و با سس باربیکیو تند عالی!' ),
				array( 'name' => 'الهام خسروی', 'text' => 'استریپس‌های ۳ تکه با سس سیر واقعاً کرانچی و بدون روغن اضافه بود.' ),
			),
			'items'        => array(
				flavor_demo_item( 'ترافل اسمش برگر', 'دو لایه گوشت گوساله اسمش، پنیر چدار دوبل، سس قارچ و ترافل', 265000, 'burger', array( 'modifiers' => $size_mod ) ),
				flavor_demo_item( 'چیزبرگر کلاسیک آمریکایی', 'گوشت دست‌ساز ۱۵۰ گرم، خیارشور خانگی، گوجه و سس مخصوص', 215000, 'burger' ),
				flavor_demo_item( 'چیکن استریپس سوخاری (۴ تکه)', 'فیله مرغ مرینیت‌شده با ادویه تند لوئیزیانا و سس عسل و خردل', 195000, 'chicken' ),
				flavor_demo_item( 'ماشروم برگر پنیری', 'گوشت گوساله با قارچ کاراملی و سس پنیر سوئیسی', 245000, 'burger' ),
				flavor_demo_item( 'هات‌داگ گریل پنیری', 'هات‌داگ ۹۰٪ با سس پنیر چدار ذوب‌شده و چیپس خلالی', 145000, 'sandwich' ),
				flavor_demo_item( 'سیب‌زمینی سرخ‌کرده با سس چدار و بیکن', 'سیب کرانچی با سس پنیر داغ و بیکن گوساله خردشده', 125000, 'sides' ),
				flavor_demo_item( 'پیاز سوخاری حلقه‌ای (۸ عدد)', 'حلقه‌های پیاز ترد با سس تارتار دست‌ساز', 75000, 'sides' ),
				flavor_demo_item( 'شیک شکلات و نوتلا غلیظ', 'بستنی شکلاتی دست‌ساز، نوتلا اصل و پودر فندق', 95000, 'drinks' ),
			),
		),

		'cafe-bistro' => array(
			'slug'         => 'cafe-bistro',
			'title'        => 'کافه و بیسترو',
			'site_title'   => 'کافه خانه سبز',
			'tagline'      => 'قهوه تخصصی، برانچ‌های آرام و نان تازه هر صبح',
			'hero_title'   => 'جایی برای مکث، قهوه خوب و گفت‌وگوهای طولانی',
			'hero_text'    => 'دانه‌های تازه‌برشت، صبحانه‌های دست‌ساز و آشپزی سبک بیسترو در فضایی گرم، سبز و آرام.',
			'about'        => 'خانه سبز از عشق به قهوه دقیق و مهمان‌نوازی بی‌تکلف شکل گرفت. دانه‌ها هر هفته تازه‌برشت می‌شوند، نان و شیرینی هر صبح در آشپزخانه ما پخته می‌شوند و منوی برانچ با مواد فصلی و محلی آماده می‌شود.',
			'branch_name'  => 'شعبه بلوار کشاورز',
			'city'         => 'تهران',
			'phone'        => '02188991234',
			'address'      => 'تهران، بلوار کشاورز، خیابان فلسطین، پلاک ۴۲',
			'theme_mods'   => array(
				'flavor_hero_badge'      => 'هر روز از ۷:۳۰ صبح؛ قهوه و نان تازه',
				'flavor_hero_style'      => 'fullscreen',
				'flavor_hero_cta2'       => 'رزرو میز کنار پنجره',
				'flavor_header_topbar'   => 'صبحانه هر روز تا ساعت ۱۳  •  اینترنت پرسرعت و فضای کار آرام',
				'flavor_phone'           => '02188991234',
				'flavor_address'         => 'تهران، بلوار کشاورز، خیابان فلسطین، پلاک ۴۲',
				'flavor_featured_title'  => 'پیشنهاد باریستا و آشپزخانه',
			),
			'categories'      => array( 'قهوه تخصصی' => 'coffee', 'صبحانه و برانچ' => 'brunch', 'نان و شیرینی' => 'bakery', 'بشقاب‌های بیسترو' => 'bistro', 'نوشیدنی سرد' => 'cold' ),
			'category_images' => array(
				'coffee' => 'category-coffee.jpg',
				'brunch' => 'category-brunch.jpg',
				'bakery' => 'category-bakery.jpg',
				'bistro' => 'category-bistro.jpg',
				'cold'   => 'category-cold.jpg',
			),
			'testimonials' => array(
				array( 'name' => 'نگار احمدی', 'text' => 'قهوه V60 دقیق و خوش‌عطر بود؛ فضای روشن و آرام کافه برای یک صبح طولانی عالی است.' ),
				array( 'name' => 'سامان وثوقی', 'text' => 'تست آووکادو و تخم‌مرغ پوچ تازه و متعادل بود؛ برخورد باریستاها هم بسیار حرفه‌ای است.' ),
			),
			'items'        => array(
				flavor_demo_item( 'فلت‌وایت خانه سبز', 'دبل اسپرسو با شیر مخملی و لاته‌آرت، تهیه‌شده با دانه فصل', 95000, 'coffee', array( 'prep' => 7 ) ),
				flavor_demo_item( 'قهوه دمی V60 تک‌خاستگاه', 'انتخاب روز باریستا با نت‌های میوه‌ای و شکلاتی', 115000, 'coffee', array( 'prep' => 10 ) ),
				flavor_demo_item( 'تست آووکادو و تخم‌مرغ پوچ', 'نان خمیرترش، آووکادو، دو تخم‌مرغ پوچ، فتا و سبزی تازه', 245000, 'brunch' ),
				flavor_demo_item( 'اگ بندیکت با سس هلندی', 'نان بریوش، تخم‌مرغ پوچ، بیکن گوساله و سس هلندی لیمویی', 265000, 'brunch' ),
				flavor_demo_item( 'ساندویچ مرغ پستو', 'مرغ گریل، پستوی ریحان، پنیر و سبزی فصل در نان خمیرترش', 230000, 'bistro' ),
				flavor_demo_item( 'سالاد فصل با پنیر فتا', 'سبزی تازه، گلابی، گردو، فتا و وینگرت عسل و خردل', 185000, 'bistro' ),
				flavor_demo_item( 'کروسان کره‌ای روز', 'کروسان لایه‌ای با کره خالص، پخت تازه هر صبح', 85000, 'bakery', array( 'prep' => 5 ) ),
				flavor_demo_item( 'آیس لاته پسته', 'اسپرسو، شیر سرد، کرم پسته و یخ شفاف', 145000, 'cold', array( 'prep' => 8 ) ),
			),
		),

		'pizza-italian' => array(
			'slug'         => 'pizza-italian',
			'title'        => 'پیتزا و رستوران ایتالیایی',
			'site_title'   => 'تراتوریا روسو',
			'tagline'      => 'خمیر ۴۸ ساعته، تنور هیزمی و عطر ریحان تازه',
			'hero_title'   => 'یک شب ایتالیایی، از دل تنور تا سر میز',
			'hero_text'    => 'پیتزای ناپلی با خمیر دست‌ساز، پاستای تازه و دسرهای کلاسیک در یک تراتوریای گرم و صمیمی.',
			'about'        => 'روسو با الهام از تراتوریاهای خانوادگی ناپل شکل گرفته است. خمیر پیتزا ۴۸ ساعت استراحت می‌کند، پاستا هر روز در آشپزخانه آماده می‌شود و مواد ساده اما باکیفیت، اساس تمام بشقاب‌های ما هستند.',
			'branch_name'  => 'شعبه فرشته',
			'city'         => 'تهران',
			'phone'        => '02122661234',
			'address'      => 'تهران، فرشته، خیابان بوسنی و هرزگوین، پلاک ۱۸',
			'theme_mods'   => array(
				'flavor_hero_badge'      => 'خمیر ۴۸ ساعته؛ پخت در تنور هیزمی',
				'flavor_hero_style'      => 'fullscreen',
				'flavor_hero_cta2'       => 'رزرو میز شام',
				'flavor_header_topbar'   => 'هر شب از ساعت ۱۸ موسیقی ایتالیایی زنده  •  ظرفیت محدود',
				'flavor_phone'           => '02122661234',
				'flavor_address'         => 'تهران، فرشته، خیابان بوسنی و هرزگوین، پلاک ۱۸',
				'flavor_featured_title'  => 'منتخب تنور و آشپزخانه',
			),
			'categories'      => array( 'پیتزای ناپلی' => 'pizza', 'پاستای تازه' => 'pasta', 'آنتی‌پاستی و سالاد' => 'antipasti', 'دسر ایتالیایی' => 'dessert', 'نوشیدنی' => 'drinks' ),
			'category_images' => array(
				'pizza'     => 'category-pizza.jpg',
				'pasta'     => 'category-pasta.jpg',
				'antipasti' => 'category-antipasti.jpg',
				'dessert'   => 'category-dessert.jpg',
				'drinks'    => 'category-drinks.jpg',
			),
			'testimonials' => array(
				array( 'name' => 'آرین نادری', 'text' => 'خمیر پیتزا سبک و لبه‌ها کاملاً پلنگی بود؛ مارگاریتا دقیقاً همان طعمی را داشت که در ناپل تجربه کردم.' ),
				array( 'name' => 'مهسا رستگار', 'text' => 'پاستای راگو تازه و تیرامیسو فوق‌العاده بود؛ فضای گرم روسو برای شام دونفره عالی است.' ),
			),
			'items'        => array(
				flavor_demo_item( 'پیتزا مارگاریتا کلاسیک', 'خمیر ۴۸ ساعته، گوجه سن‌مارزانو، فیور دی لاته، ریحان و روغن زیتون', 285000, 'pizza' ),
				flavor_demo_item( 'پیتزا استیک و قارچ', 'راسته گوساله، قارچ، موزارلا، پیاز کاراملی و روغن ترافل', 390000, 'pizza' ),
				flavor_demo_item( 'پیتزا دیابولا', 'سالامی گوساله تند، فلفل چیلی، گوجه و موزارلا', 335000, 'pizza' ),
				flavor_demo_item( 'پاپاردله راگو', 'پاستای دست‌ساز با راگوی آرام‌پز گوشت و پارمزان', 345000, 'pasta' ),
				flavor_demo_item( 'راویولی اسفناج و ریکوتا', 'راویولی تازه، کره مریم‌گلی، ریکوتا و پارمزان', 325000, 'pasta' ),
				flavor_demo_item( 'بورراتا و گوجه کبابی', 'پنیر بورراتا، گوجه رنگی، ریحان، بالزامیک و نان فوکاچیا', 245000, 'antipasti' ),
				flavor_demo_item( 'تیرامیسو کلاسیک', 'لیدی‌فینگر، اسپرسو، ماسکارپونه و پودر کاکائو', 165000, 'dessert' ),
				flavor_demo_item( 'لیموناتای ریحان', 'لیمو تازه، ریحان، عسل و آب گازدار', 85000, 'drinks' ),
			),
		),
	);
}
