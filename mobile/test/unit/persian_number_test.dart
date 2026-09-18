import 'package:flutter_test/flutter_test.dart';
import '../../lib/core/utils/persian_number.dart';

void main() {
  group('PersianNumber Utility Tests', () {
    test('Converts Latin digits to Persian digits', () {
      expect(PersianNumber.toPersian('1234567890'), '۱۲۳۴۵۶۷۸۹۰');
      expect(PersianNumber.toPersian(1405), '۱۴۰۵');
    });

    test('Converts Persian digits back to Latin digits', () {
      expect(PersianNumber.toLatin('۱۲۳۴۵۶۷۸۹۰'), '1234567890');
      expect(PersianNumber.toLatin('١٢٣٤٥٦٧٨٩٠'), '1234567890');
    });

    test('Normalizes mobile phone numbers correctly', () {
      expect(PersianNumber.cleanMobile('09123456789'), '09123456789');
      expect(PersianNumber.cleanMobile('+989123456789'), '09123456789');
      expect(PersianNumber.cleanMobile('۰۹۱۲۳۴۵۶۷۸۹'), '09123456789');
      expect(PersianNumber.cleanMobile('0912-345-6789'), '09123456789');
    });
  });
}
