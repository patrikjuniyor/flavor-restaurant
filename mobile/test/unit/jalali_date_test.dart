import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/core/utils/jalali_date.dart';

void main() {
  group('JalaliDate Engine Tests', () {
    test('Converts Gregorian DateTime to Jalali correctly', () {
      final dt = DateTime(2026, 9, 18);
      final j = JalaliDate.fromDateTime(dt);

      expect(j.year, 1405);
      expect(j.month, 6);
      expect(j.day, 27);
    });

    test('Converts Jalali to Gregorian DateTime reversibly', () {
      final j = const JalaliDate(1405, 6, 27);
      final dt = j.toDateTime();

      expect(dt.year, 2026);
      expect(dt.month, 9);
      expect(dt.day, 18);
    });

    test('Formats full date in Persian with weekday', () {
      final j = const JalaliDate(1405, 6, 27);
      final formatted = j.formatFull();

      expect(formatted, contains('شهریور'));
      expect(formatted, contains('۱۴۰۵'));
      expect(formatted, contains('۲۷'));
    });

    test('Calculates month lengths and leap years', () {
      expect(JalaliDate.monthLength(1405, 1), 31);
      expect(JalaliDate.monthLength(1405, 7), 30);
      expect(JalaliDate.monthLength(1405, 12), 29); // normal year
    });
  });
}
