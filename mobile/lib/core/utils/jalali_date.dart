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

  /// Total days in this Jalali month.
  static int monthLength(int jy, int jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isLeapYear(jy) ? 30 : 29;
  }

  /// Checks whether a Jalali year is a leap year.
  static bool isLeapYear(int jy) {
    final rem = ((jy - (jy > 0 ? 474 : 473)) % 2820 + 474 + 38) * 682 % 2816;
    return rem < 682;
  }
}
