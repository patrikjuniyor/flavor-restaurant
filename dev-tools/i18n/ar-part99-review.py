#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""R3-05 native-review overrides for the Arabic catalogue.

Every value here was chosen against how Gulf/Levant restaurant menus and
WordPress Arabic UIs actually phrase things; the structural audit
(audit-ar-po.py) enforces the mechanics, this file owns the style. Where
a choice was a judgement call it is noted inline. Named part99 so the
lexicographic merge order in build-po.py loads it last and its keys win.

Native-translator review is still pending for register (mix of singular
and plural address inherited from older parts); that pass belongs to a
human reviewer, not this audit.
"""

PART_99 = {
    '%1$s — %2$s به ۱ (حداقل %3$s به ۱)': '%1$s — %2$s إلى ١ (الحد الأدنى %3$s إلى ١)',
    'از ۹۹۲ پیکسل بالا در هدر است؛ در موبایل داخل کشوی منو نمایش داده می\u200cشود.': 'فوق ٩٩٢ بكسل يظهر في الترويسة؛ وعلى الجوال يظهر داخل درج القائمة.',
    'تعداد پیشنهادی را بین ۱ تا ۱۰۰۰ وارد کنید؛ برای تعداد بیشتر تماس بگیرید.': 'أدخل عددًا مقترحًا بين ١ و١٠٠٠؛ للكميات الأكبر اتصل بنا.',
    'شمارهٔ معتبر مانند ۰۹۱۲۱۲۳۴۵۶۷ وارد کنید.': 'أدخل رقمًا صحيحًا مثل ٠٩١٢١٢٣٤٥٦٧',
    'از سال ۱۳۹۵ با یک هدف ساده شروع کردیم: غذای خانگی با مواد تازه و قیمت منصفانه. امروز هر روز صبح مواد اولیه را خودمان از بازار می\u200cخریم.': 'منذ عام ٢٠١٦م بدأنا بهدف بسيط: طعام بيتي بمكونات طازجة وسعر منصف. واليوم نشتري المكونات بأنفسنا من السوق كل صباح.',
    'طرح اصیل زرشکی، طلایی و لاجوردی با حس نوستالژیک': 'تصميم أصيل بألوان العنابي والذهبي والأزرق اللازوردي يبعث أجواء الحنين',
    'گوشه\u200cهایی از فضا و طعم\u200cها': 'لمحات من أجوائنا ونكهاتنا',
    'مثلاً سس جداگانه یا کم\u200cنمک': 'مثلاً الصلصة منفصلة أو طبق خفيف الملح',
    'سالاد فصل، ماست\u200cموسیر و پیش\u200cغذاهای خانگی.': 'سلطة الموسم، وزبادي النعناع المجفف، ومقبلات بيتية.',
}
