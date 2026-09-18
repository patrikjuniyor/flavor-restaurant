/// Converter between Latin and Persian/Arabic digits.
class PersianNumber {
  static const Map<String, String> _latinToPersian = {
    '0': '۰',
    '1': '۱',
    '2': '۲',
    '3': '۳',
    '4': '۴',
    '5': '۵',
    '6': '۶',
    '7': '۷',
    '8': '۸',
    '9': '۹',
  };

  static const Map<String, String> _persianToLatin = {
    '۰': '0',
    '۱': '1',
    '۲': '2',
    '۳': '3',
    '۴': '4',
    '۵': '5',
    '۶': '6',
    '۷': '7',
    '۸': '8',
    '۹': '9',
    '٠': '0',
    '١': '1',
    '٢': '2',
    '٣': '3',
    '٤': '4',
    '٥': '5',
    '٦': '6',
    '٧': '7',
    '٨': '8',
    '٩': '9',
  };

  /// Converts any string containing Latin digits to Persian digits.
  static String toPersian(dynamic input) {
    if (input == null) return '';
    String text = input.toString();
    _latinToPersian.forEach((latin, persian) {
      text = text.replaceAll(latin, persian);
    });
    return text;
  }

  /// Normalizes Persian / Arabic digits back to standard ASCII Latin digits.
  static String toLatin(String? input) {
    if (input == null || input.isEmpty) return '';
    String text = input;
    _persianToLatin.forEach((persian, latin) {
      text = text.replaceAll(persian, latin);
    });
    return text.trim();
  }

  /// Cleans and formats Iranian mobile numbers: 09xxxxxxxxx.
  static String cleanMobile(String raw) {
    String clean = toLatin(raw).replaceAll(RegExp(r'\D'), '');
    if (clean.startsWith('98') && clean.length == 12) {
      clean = '0' + clean.substring(2);
    } else if (clean.startsWith('9') && clean.length == 10) {
      clean = '0' + clean;
    }
    return clean;
  }
}
