import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/core/utils/iran_provinces.dart';
import 'package:flavor_mobile/core/utils/jalali_date.dart';
import 'package:flavor_mobile/core/utils/validators.dart';

void main() {
  group('Validators — Mobile Number (09xxxxxxxxx)', () {
    test('accepts valid 11-digit Iranian mobile numbers', () {
      expect(Validators.isValidMobile('09121234567'), isTrue);
      expect(Validators.isValidMobile('09351112233'), isTrue);
      expect(Validators.isValidMobile('09019998877'), isTrue);
      expect(Validators.isValidMobile('09901234567'), isTrue);
    });

    test('accepts Persian and Arabic digits and strips spaces/hyphens', () {
      expect(Validators.isValidMobile('۰۹۱۲۱۲۳۴۵۶۷'), isTrue);
      expect(Validators.isValidMobile('٠٩١٢١٢٣٤٥٦٧'), isTrue);
      expect(Validators.isValidMobile('0912-123-4567'), isTrue);
      expect(Validators.isValidMobile(' 0912 123 4567 '), isTrue);
    });

    test('accepts +98 / 0098 / 98 international prefixes', () {
      expect(Validators.isValidMobile('+989121234567'), isTrue);
      expect(Validators.isValidMobile('00989121234567'), isTrue);
      expect(Validators.isValidMobile('989121234567'), isTrue);
      expect(Validators.isValidMobile('+۹۸۹۱۲۱۲۳۴۵۶۷'), isTrue);
    });

    test('rejects malformed numbers and non-Iranian formats', () {
      expect(Validators.isValidMobile(null), isFalse);
      expect(Validators.isValidMobile(''), isFalse);
      expect(Validators.isValidMobile('   '), isFalse);
      expect(Validators.isValidMobile('0912123456'), isFalse); // 10 digits
      expect(Validators.isValidMobile('091212345678'), isFalse); // 12 digits
      expect(Validators.isValidMobile('02188888888'), isFalse); // Landline
      expect(Validators.isValidMobile('08121234567'), isFalse); // Starts with 08
      expect(Validators.isValidMobile('abcdefghijk'), isFalse);
      expect(Validators.isValidMobile('+14155552671'), isFalse); // US format
    });

    test('validateMobile helper produces friendly localized messages', () {
      expect(Validators.validateMobile('09121234567'), isNull);
      expect(Validators.validateMobile('', required: true), contains('الزامی'));
      expect(Validators.validateMobile(null, required: false), isNull);
      expect(Validators.validateMobile('12345'), contains('نامعتبر'));
      expect(Validators.validateMobile('02188888888'), contains('۰۹۱۲۳۴۵۶۷۸۹'));
    });
  });

  group('Validators — Email & Display Name', () {
    test('validates RFC-style email addresses', () {
      expect(Validators.isValidEmail('user@example.com'), isTrue);
      expect(Validators.isValidEmail('first.last+tag@sub.domain.ir'), isTrue);
      expect(Validators.isValidEmail('invalid-email'), isFalse);
      expect(Validators.isValidEmail('@example.com'), isFalse);
      expect(Validators.isValidEmail('user@'), isFalse);
    });

    test('validateEmail optional vs required handling', () {
      expect(Validators.validateEmail('', required: false), isNull);
      expect(Validators.validateEmail(null, required: false), isNull);
      expect(Validators.validateEmail('', required: true), contains('الزامی'));
      expect(Validators.validateEmail('not-an-email'), contains('نامعتبر'));
      expect(Validators.validateEmail('user@domain.com'), isNull);
    });

    test('validateDisplayName enforces length and presence', () {
      expect(Validators.validateDisplayName(null), contains('الزامی'));
      expect(Validators.validateDisplayName('  '), contains('الزامی'));
      expect(Validators.validateDisplayName('علی'), isNull);
      expect(Validators.validateDisplayName('مه'), contains('حداقل'));
      expect(Validators.validateDisplayName('محمد حسینی'), isNull);
    });
  });

  group('Validators — Iran Geography (data/iran-provinces.php alignment)', () {
    tearDown(() {
      IranGeography.clearCache();
    });

    test('contains all 31 Iranian provinces in local list', () {
      expect(IranGeography.provinces.length, 31);
      expect(IranGeography.provinces, contains('تهران'));
      expect(IranGeography.provinces, contains('فارس'));
      expect(IranGeography.provinces, contains('خراسان رضوی'));
      expect(IranGeography.provinces, contains('آذربایجان شرقی'));
      expect(IranGeography.provinces, contains('یزد'));
    });

    test('resolves starter cities for given province', () {
      final tehranCities = IranGeography.citiesFor('تهران');
      expect(tehranCities, contains('تهران'));
      expect(tehranCities, contains('شهریار'));
      expect(tehranCities, contains('اسلامشهر'));

      final farsCities = IranGeography.citiesFor('فارس');
      expect(farsCities, contains('شیراز'));
      expect(farsCities, contains('مرودشت'));

      expect(IranGeography.citiesFor('unknown_province'), isEmpty);
      expect(IranGeography.citiesFor(null), isEmpty);
    });

    test('validates province & city relations', () {
      expect(IranGeography.isValidProvince('تهران'), isTrue);
      expect(IranGeography.isValidProvince('ناموجود'), isFalse);

      expect(IranGeography.isValidCity('تهران', 'تهران'), isTrue);
      expect(IranGeography.isValidCity('تهران', 'شهریار'), isTrue);
      expect(IranGeography.isValidCity('تهران', 'شیراز'), isFalse);
      expect(IranGeography.isValidCity('فارس', 'شیراز'), isTrue);
    });

    test('supports remote override / enrichment from API (setProvinces)', () {
      IranGeography.setProvinces({
        'تهران': ['تهران', 'پردیس', 'دماوند'],
        'استان جدید': ['شهر الف', 'شهر ب'],
      });

      expect(IranGeography.provinces, contains('استان جدید'));
      expect(IranGeography.citiesFor('تهران'), contains('پردیس'));
      expect(IranGeography.isValidCity('استان جدید', 'شهر الف'), isTrue);

      IranGeography.clearCache();
      expect(IranGeography.provinces.length, 31);
      expect(IranGeography.provinces, isNot(contains('استان جدید')));
    });

    test('validateProvince and validateCity return friendly errors', () {
      expect(Validators.validateProvince(null), contains('الزامی'));
      expect(Validators.validateProvince(''), contains('الزامی'));
      expect(Validators.validateProvince('استان خیالی'), contains('معتبر نیست'));
      expect(Validators.validateProvince('تهران'), isNull);

      expect(Validators.validateCity('تهران', null), contains('الزامی'));
      expect(Validators.validateCity('تهران', ''), contains('الزامی'));
      expect(Validators.validateCity('تهران', 'شیراز'), contains('هم‌خوانی ندارد'));
      expect(Validators.validateCity('تهران', 'تهران'), isNull);
    });

    test('validateAddressLine enforces minimum detail', () {
      expect(Validators.validateAddressLine(''), contains('الزامی'));
      expect(Validators.validateAddressLine('کوچه'), contains('حداقل'));
      expect(Validators.validateAddressLine('خیابان ولیعصر، پلاک ۱۰'), isNull);
    });
  });

  group('Validators & JalaliDate — Solar Hijri Integrations', () {
    test('parses ISO Gregorian string to JalaliDate', () {
      final j = JalaliDate.tryParseIsoGregorian('2026-09-18');
      expect(j, isNotNull);
      expect(j!.year, 1405);
      expect(j.month, 6);
      expect(j.day, 27);
    });

    test('returns null for malformed ISO Gregorian string', () {
      expect(JalaliDate.tryParseIsoGregorian(null), isNull);
      expect(JalaliDate.tryParseIsoGregorian(''), isNull);
      expect(JalaliDate.tryParseIsoGregorian('2026/09/18'), isNull);
      expect(JalaliDate.tryParseIsoGregorian('2026-13-01'), isNull); // Month 13
      expect(JalaliDate.tryParseIsoGregorian('2026-02-31'), isNull); // Invalid day
    });

    test('parses Jalali numeric strings (both Latin & Persian digits)', () {
      final j1 = JalaliDate.tryParseJalali('1405/06/27');
      expect(j1, isNotNull);
      expect(j1!.year, 1405);
      expect(j1.month, 6);
      expect(j1.day, 27);

      final j2 = JalaliDate.tryParseJalali('۱۴۰۵/۰۶/۲۷');
      expect(j2, isNotNull);
      expect(j2!.year, 1405);
      expect(j2.month, 6);
      expect(j2.day, 27);
    });

    test('rejects impossible Jalali dates (e.g. Esfand 30 in non-leap year)', () {
      expect(JalaliDate.tryParseJalali('1405/12/30'), isNull); // 1405 not leap
      expect(JalaliDate.tryParseJalali('1403/12/30'), isNotNull); // 1403 leap year
      expect(JalaliDate.tryParseJalali('1405/13/01'), isNull);
      expect(JalaliDate.tryParseJalali('1405/07/31'), isNull); // Second-half months max 30
    });

    test('toIsoGregorianString produces canonical YYYY-MM-DD for APIs', () {
      const j = JalaliDate(1405, 6, 27);
      expect(j.toIsoGregorianString(), '2026-09-18');
    });

    test('formatNumeric formats in Persian digits', () {
      const j = JalaliDate(1405, 6, 27);
      expect(j.formatNumeric(), '۱۴۰۵/۰۶/۲۷');
    });
  });
}
