import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/core/utils/currency_formatter.dart';

void main() {
  group('CurrencyFormatter Tests', () {
    test('Formats Toman currency with comma separators and Persian digits', () {
      expect(CurrencyFormatter.format(125000), '۱۲۵,۰۰۰ تومان');
      expect(CurrencyFormatter.format(6500000), '۶,۵۰۰,۰۰۰ تومان');
      expect(CurrencyFormatter.format(0), '۰ تومان');
    });

    test('Converts stored Rial to display Toman unit', () {
      expect(CurrencyFormatter.convertStorageToDisplay(1000000, 'IRR', 'IRT'), 100000);
      expect(CurrencyFormatter.convertStorageToDisplay(50000, 'IRT', 'IRT'), 50000);
    });
  });
}
