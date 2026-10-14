import 'iran_provinces.dart';
import 'persian_number.dart';

/// Form and domain validation utilities for Flavor mobile.
class Validators {
  Validators._();

  static final RegExp _mobileRegex = RegExp(r'^09\d{9}$');
  static final RegExp _emailRegex =
      RegExp(r'^[a-zA-Z0-9.!#$%&’*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+$');

  /// Validates Iranian mobile numbers against `09xxxxxxxxx`.
  ///
  /// Handles Persian and Arabic digits, strip-spaces, and international prefixes
  /// (+98 / 0098 / 98) via [PersianNumber.cleanMobile].
  static bool isValidMobile(String? raw) {
    if (raw == null || raw.trim().isEmpty) return false;
    final cleaned = PersianNumber.cleanMobile(raw.trim());
    return _mobileRegex.hasMatch(cleaned);
  }

  /// Form validator for mobile field. Returns null when valid or an error message.
  static String? validateMobile(String? value, {bool required = true}) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) {
      return required ? 'شماره موبایل الزامی است.' : null;
    }
    if (!isValidMobile(raw)) {
      return 'شماره موبایل نامعتبر است (نمونه: ۰۹۱۲۳۴۵۶۷۸۹)';
    }
    return null;
  }

  /// Validates email address format. Returns true if empty (when optional).
  static bool isValidEmail(String? raw) {
    if (raw == null || raw.trim().isEmpty) return false;
    return _emailRegex.hasMatch(raw.trim());
  }

  /// Form validator for email field.
  static String? validateEmail(String? value, {bool required = false}) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) {
      return required ? 'ایمیل الزامی است.' : null;
    }
    if (!isValidEmail(raw)) {
      return 'قالب ایمیل نامعتبر است.';
    }
    return null;
  }

  /// Validates full display name.
  static String? validateDisplayName(String? value, {int minLength = 3}) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) {
      return 'نام و نام‌خانوادگی الزامی است.';
    }
    if (raw.length < minLength) {
      return 'نام باید حداقل $minLength حرف باشد.';
    }
    return null;
  }

  /// Validates province selection against [IranGeography].
  static String? validateProvince(String? value) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) {
      return 'انتخاب استان الزامی است.';
    }
    if (!IranGeography.isValidProvince(raw)) {
      return 'استان انتخاب‌شده معتبر نیست.';
    }
    return null;
  }

  /// Validates city selection against selected province.
  static String? validateCity(String? province, String? city) {
    final rawCity = city?.trim() ?? '';
    if (rawCity.isEmpty) {
      return 'انتخاب یا وارد کردن شهر الزامی است.';
    }
    if (province != null &&
        IranGeography.isValidProvince(province) &&
        !IranGeography.isValidCity(province, rawCity)) {
      return 'شهر انتخاب‌شده با استان هم‌خوانی ندارد.';
    }
    return null;
  }

  /// Validates address line.
  static String? validateAddressLine(String? value, {int minLength = 5}) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) {
      return 'نشانی دقیق الزامی است.';
    }
    if (raw.length < minLength) {
      return 'نشانی باید حداقل $minLength حرف باشد.';
    }
    return null;
  }
}
