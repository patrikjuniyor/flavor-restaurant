/// Iran geography reference — provinces mapped to starter city lists.
///
/// Mirrored from backend `flavor-core/data/iran-provinces.php`.
/// Can be enriched at runtime via `IranGeography.setProvinces(...)`
/// when populated from the server API (e.g. GET /checkout/options or bootstrap).
class IranGeography {
  IranGeography._();

  /// Default local province-to-cities catalogue (31 provinces).
  static const Map<String, List<String>> localProvinces = {
    'آذربایجان شرقی': ['تبریز', 'مراغه', 'مرند', 'اهر'],
    'آذربایجان غربی': ['ارومیه', 'خوی', 'مهاباد', 'بوکان'],
    'اردبیل': ['اردبیل', 'پارس‌آباد', 'مشگین‌شهر'],
    'اصفهان': ['اصفهان', 'کاشان', 'خمینی‌شهر', 'نجف‌آباد'],
    'البرز': ['کرج', 'فردیس', 'کمال‌شهر', 'نظرآباد'],
    'ایلام': ['ایلام', 'دهلران', 'ایوان'],
    'بوشهر': ['بوشهر', 'برازجان', 'کنگان', 'عسلویه'],
    'تهران': ['تهران', 'اسلامشهر', 'شهریار', 'ری', 'قدس', 'ملارد', 'پاکدشت', 'ورامین'],
    'چهارمحال و بختیاری': ['شهرکرد', 'بروجن', 'فارسان'],
    'خراسان جنوبی': ['بیرجند', 'قائن', 'فردوس'],
    'خراسان رضوی': ['مشهد', 'نیشابور', 'سبزوار', 'تربت حیدریه'],
    'خراسان شمالی': ['بجنورد', 'شیروان', 'اسفراین'],
    'خوزستان': ['اهواز', 'آبادان', 'خرمشهر', 'دزفول', 'ماهشهر'],
    'زنجان': ['زنجان', 'ابهر', 'خدابنده'],
    'سمنان': ['سمنان', 'شاهرود', 'دامغان'],
    'سیستان و بلوچستان': ['زاهدان', 'چابهار', 'ایرانشهر', 'زابل'],
    'فارس': ['شیراز', 'مرودشت', 'جهرم', 'کازرون', 'فسا'],
    'قزوین': ['قزوین', 'الوند', 'تاکستان'],
    'قم': ['قم'],
    'کردستان': ['سنندج', 'سقز', 'مریوان', 'بانه'],
    'کرمان': ['کرمان', 'سیرجان', 'رفسنجان', 'جیرفت'],
    'کرمانشاه': ['کرمانشاه', 'اسلام‌آباد غرب', 'سنقر'],
    'کهگیلویه و بویراحمد': ['یاسوج', 'دوگنبدان', 'دهدشت'],
    'گلستان': ['گرگان', 'گنبد کاووس', 'علی‌آباد'],
    'گیلان': ['رشت', 'انزلی', 'لاهیجان', 'لنگرود'],
    'لرستان': ['خرم‌آباد', 'بروجرد', 'دورود', 'الیگودرز'],
    'مازندران': ['ساری', 'بابل', 'آمل', 'قائم‌شهر', 'نوشهر'],
    'مرکزی': ['اراک', 'ساوه', 'خمین'],
    'هرمزگان': ['بندرعباس', 'قشم', 'کیش', 'میناب'],
    'همدان': ['همدان', 'ملایر', 'نهاوند'],
    'یزد': ['یزد', 'میبد', 'اردکان'],
  };

  /// In-memory cache when populated from remote API.
  static Map<String, List<String>>? _remoteProvinces;

  /// Injects or updates remote provinces fetched from API.
  static void setProvinces(Map<String, List<String>> provinces) {
    if (provinces.isEmpty) return;
    _remoteProvinces = Map<String, List<String>>.unmodifiable(
      provinces.map((k, v) => MapEntry(k.trim(), List<String>.unmodifiable(v.map((c) => c.trim())))),
    );
  }

  /// Clears runtime remote cache back to local defaults.
  static void clearCache() {
    _remoteProvinces = null;
  }

  /// Active list of Iranian provinces.
  static List<String> get provinces =>
      (_remoteProvinces ?? localProvinces).keys.toList(growable: false);

  /// Cities for the given province. Returns empty list if province is unknown.
  static List<String> citiesFor(String? province) {
    if (province == null || province.trim().isEmpty) return const [];
    final map = _remoteProvinces ?? localProvinces;
    return map[province.trim()] ?? const [];
  }

  /// Whether the province exists in the active dataset.
  static bool isValidProvince(String? province) {
    if (province == null) return false;
    final map = _remoteProvinces ?? localProvinces;
    return map.containsKey(province.trim());
  }

  /// Whether the city belongs to the given province.
  static bool isValidCity(String? province, String? city) {
    if (province == null || city == null) return false;
    final cities = citiesFor(province);
    return cities.contains(city.trim());
  }
}
