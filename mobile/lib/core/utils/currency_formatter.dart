import 'package:intl/intl.dart';
import 'persian_number.dart';

/// Price and currency formatting for Iranian Toman & Rial.
class CurrencyFormatter {
  static final NumberFormat _formatter = NumberFormat('#,###', 'en_US');

  /// Formats integer price into localized string: e.g. "۱۲۵,۰۰۰ تومان"
  static String format(
    num? amount, {
    String unit = 'تومان',
    bool showUnit = true,
    bool toPersianDigits = true,
  }) {
    if (amount == null || amount == 0) {
      return showUnit ? (toPersianDigits ? '۰ $unit' : '0 $unit') : (toPersianDigits ? '۰' : '0');
    }

    final formattedLatin = _formatter.format(amount.round());
    final resultDigits = toPersianDigits ? PersianNumber.toPersian(formattedLatin) : formattedLatin;

    if (!showUnit) {
      return resultDigits;
    }

    return '$resultDigits $unit';
  }

  /// Converts stored Rial price to display Toman if needed.
  static int convertStorageToDisplay(int storedAmount, String storageUnit, String displayUnit) {
    if (storageUnit == 'IRR' && displayUnit == 'IRT') {
      return (storedAmount / 10).round();
    }
    if (storageUnit == 'IRT' && displayUnit == 'IRR') {
      return storedAmount * 10;
    }
    return storedAmount;
  }
}
