import 'persian_number.dart';

/// Pure Dart Jalali (Solar Hijri) Calendar calculation and formatting engine.
class JalaliDate {
  final int year;
  final int month;
  final int day;
  final int hour;
  final int minute;
  final int second;

  static const List<String> monthNames = [
    '',
    'فروردین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند',
  ];

  static const List<String> weekDayNames = [
    'دوشنبه',
    'سه‌شنبه',
    'چهارشنبه',
    'پنج‌شنبه',
    'جمعه',
    'شنبه',
    'یک‌شنبه',
  ];

  const JalaliDate(
    this.year,
    this.month,
    this.day, [
    this.hour = 0,
    this.minute = 0,
    this.second = 0,
  ]);

  /// Creates JalaliDate from Gregorian DateTime.
  factory JalaliDate.fromDateTime(DateTime dt) {
    final gYear = dt.year;
    final gMonth = dt.month;
    final gDay = dt.day;

    final gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    int gy = gYear - 1600;
    int gm = gMonth - 1;
    int gd = gDay - 1;

    int gDayNo = 365 * gy + ((gy + 3) ~/ 4) - ((gy + 99) ~/ 100) + ((gy + 399) ~/ 400);
    gDayNo += gDaysInMonth[gm] + gd;
    if (gm > 1 && ((gYear % 4 == 0 && gYear % 100 != 0) || (gYear % 400 == 0))) {
      gDayNo++;
    }

    int jDayNo = gDayNo - 79;
    int jNp = jDayNo ~/ 12053;
    jDayNo %= 12053;

    int jy = 979 + 33 * jNp + 4 * (jDayNo ~/ 1461);
    jDayNo %= 1461;

    if (jDayNo >= 366) {
      jy += (jDayNo - 1) ~/ 365;
      jDayNo = (jDayNo - 1) % 365;
    }

    int jm;
    int jd;
    if (jDayNo < 186) {
      jm = 1 + (jDayNo ~/ 31);
      jd = 1 + (jDayNo % 31);
    } else {
      jm = 7 + ((jDayNo - 186) ~/ 30);
      jd = 1 + ((jDayNo - 186) % 30);
    }

    return JalaliDate(jy, jm, jd, dt.hour, dt.minute, dt.second);
  }

  /// Converts this Jalali date to standard Gregorian DateTime.
  DateTime toDateTime() {
    int jy = year - 979;
    int jm = month - 1;
    int jd = day - 1;

    int jDayNo = 365 * jy + (jy ~/ 33) * 8 + (((jy % 33) + 3) ~/ 4);
    for (int i = 0; i < jm; ++i) {
      jDayNo += (i < 6) ? 31 : 30;
    }
    jDayNo += jd;

    int gDayNo = jDayNo + 79;
    int gy = 1600 + 400 * (gDayNo ~/ 146097);
    gDayNo %= 146097;

    bool leap = true;
    if (gDayNo >= 36525) {
      gDayNo--;
      gy += 100 * (gDayNo ~/ 36524);
      gDayNo %= 36524;
      if (gDayNo >= 365) {
        gDayNo++;
      } else {
        leap = false;
      }
    }

    gy += 4 * (gDayNo ~/ 1461);
    gDayNo %= 1461;

    if (gDayNo >= 366) {
      leap = false;
      gDayNo--;
      gy += gDayNo ~/ 365;
      gDayNo %= 365;
    }

    final gDaysInMonth = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    int gm = 0;
    while (gm < 12 && gDayNo >= gDaysInMonth[gm + 1]) {
      gDayNo -= gDaysInMonth[gm + 1];
      gm++;
    }

    return DateTime(gy, gm + 1, gDayNo + 1, hour, minute, second);
  }

  /// Returns localized full string: "جمعه ۲۷ شهریور ۱۴۰۵"
  String formatFull({bool includeWeekDay = true}) {
    final gDate = toDateTime();
    final weekDay = weekDayNames[gDate.weekday - 1];
    final mName = monthNames[month];
    final d = PersianNumber.toPersian(day);
    final y = PersianNumber.toPersian(year);

    if (includeWeekDay) {
      return '$weekDay $d $mName $y';
    }
    return '$d $mName $y';
  }

  /// Returns numeric format: "۱۴۰۵/۰۶/۲۷"
  String formatNumeric() {
    final m = month < 10 ? '0$month' : '$month';
    final d = day < 10 ? '0$day' : '$day';
    return PersianNumber.toPersian('$year/$m/$d');
  }

  /// Returns ISO format for API payload: "2026-09-18"
  String toIsoGregorianString() {
    final dt = toDateTime();
    final m = dt.month < 10 ? '0${dt.month}' : '${dt.month}';
    final d = dt.day < 10 ? '0${dt.day}' : '${dt.day}';
    return '${dt.year}-$m-$d';
  }

  /// Human relative time string: "۵ دقیقه پیش", "دیروز"
  static String relativeTime(DateTime past) {
    final now = DateTime.now();
    final diff = now.difference(past);

    if (diff.inSeconds < 60) {
      return 'همین الان';
    } else if (diff.inMinutes < 60) {
      return '${PersianNumber.toPersian(diff.inMinutes)} دقیقه پیش';
    } else if (diff.inHours < 24) {
      return '${PersianNumber.toPersian(diff.inHours)} ساعت پیش';
    } else if (diff.inDays == 1) {
      return 'دیروز';
    } else if (diff.inDays < 7) {
      return '${PersianNumber.toPersian(diff.inDays)} روز پیش';
    } else {
      return JalaliDate.fromDateTime(past).formatFull(includeWeekDay: false);
    }
  }

  /// Parses an ISO Gregorian date string (e.g. "2026-09-18") to [JalaliDate].
  /// Returns null if format is invalid.
  static JalaliDate? tryParseIsoGregorian(String? iso) {
    if (iso == null || iso.trim().isEmpty) return null;
    final trimmed = iso.trim();
    final parts = trimmed.split('-');
    if (parts.length != 3) return null;
    final y = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    final d = int.tryParse(parts[2]);
    if (y == null || m == null || d == null) return null;
    try {
      final dt = DateTime(y, m, d);
      // Guard against DateTime overflow roll (e.g. month=13, day=32)
      if (dt.year != y || dt.month != m || dt.day != d) return null;
      return JalaliDate.fromDateTime(dt);
    } catch (_) {
      return null;
    }
  }

  /// Parses a Persian/Latin Jalali date string (e.g. "1405/06/27" or "۱۴۰۵/۰۶/۲۷").
  /// Returns null if format is invalid.
  static JalaliDate? tryParseJalali(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    final latin = PersianNumber.toLatin(raw.trim());
    final parts = latin.split(RegExp(r'[/.-]'));
    if (parts.length != 3) return null;
    final jy = int.tryParse(parts[0]);
    final jm = int.tryParse(parts[1]);
    final jd = int.tryParse(parts[2]);
    if (jy == null || jm == null || jd == null) return null;
    if (jm < 1 || jm > 12) return null;
    final maxDays = monthLength(jy, jm);
    if (jd < 1 || jd > maxDays) return null;
    return JalaliDate(jy, jm, jd);
  }

  /// Total days in this Jalali month.
  static int monthLength(int jy, int jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isLeapYear(jy) ? 30 : 29;
  }

  /// Checks whether a Jalali year is a leap year.
  ///
  /// Port of jalaali-js `isLeapJalaliYear()` matching backend
  /// `FlavorCore\Support\Jalali::is_leap()` algorithm.
  static bool isLeapYear(int jy) {
    const breaks = [
      -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262,
      2324, 2394, 2456, 3178
    ];
    final bl = breaks.length;
    int jp = breaks[0];
    int jump = 0;
    for (int i = 1; i < bl; i++) {
      final jm = breaks[i];
      jump = jm - jp;
      if (jy < jm) {
        break;
      }
      jp = jm;
    }
    int n = jy - jp;
    if (jump - n < 6) {
      n = n - jump + ((jump + 4) ~/ 33) * 33;
    }
    int leap = (((n + 1) % 33) - 1) % 4;
    if (leap == -1) {
      leap = 4;
    }
    return leap == 0;
  }
}
