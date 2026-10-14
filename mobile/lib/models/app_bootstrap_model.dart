import 'package:flutter/material.dart';

import '../config/app_config.dart';
import 'branch_model.dart';
import 'calendar_model.dart';
import 'json_fields.dart';

/// App Bootstrap Configuration domain model from /settings/app-bootstrap.
/// Mirrors SettingsController::get_bootstrap() — see
/// test/fixtures/app_bootstrap.json.

class AppBrandInfo {
  final String name;
  final String description;
  final String logo;
  final String phone;
  final String instagram;
  final bool maintenanceMode;
  final String minAppVersion;
  final String currentVersion;

  const AppBrandInfo({
    required this.name,
    this.description = '',
    this.logo = '',
    this.phone = '',
    this.instagram = '',
    this.maintenanceMode = false,
    this.minAppVersion = '1.0.0',
    this.currentVersion = AppVersion.value,
  });

  factory AppBrandInfo.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const AppBrandInfo(name: 'رستوران طعم');
    }
    return AppBrandInfo(
      name: strField(json['name'], 'رستوران طعم'),
      description: strField(json['description']),
      logo: strField(json['logo']),
      phone: strField(json['phone']),
      instagram: strField(json['instagram']),
      maintenanceMode: boolField(json['maintenance_mode']),
      minAppVersion: strField(json['min_app_version'], '1.0.0'),
      currentVersion: strField(json['current_version'], AppVersion.value),
    );
  }
}

class AppDesignTokens {
  final String preset;
  final Color primaryColor;
  final Color accentColor;
  final String fontFamily;
  final double borderRadius;

  const AppDesignTokens({
    this.preset = 'modern_restaurant',
    this.primaryColor = const Color(0xFFC8102E),
    this.accentColor = const Color(0xFFD97706),
    this.fontFamily = 'Vazirmatn',
    this.borderRadius = 12.0,
  });

  factory AppDesignTokens.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const AppDesignTokens();

    Color parseColor(String? hex, Color fallback) {
      if (hex == null || hex.isEmpty) return fallback;
      final clean = hex.replaceAll('#', '');
      if (clean.length == 6) {
        return Color(int.parse('FF$clean', radix: 16));
      }
      return fallback;
    }

    double parseRadius(String? radiusStr) {
      if (radiusStr == null) return 12.0;
      if (radiusStr.contains('rem')) {
        final val = double.tryParse(radiusStr.replaceAll('rem', '')) ?? 0.75;
        return val * 16.0;
      }
      return double.tryParse(radiusStr.replaceAll('px', '')) ?? 12.0;
    }

    return AppDesignTokens(
      preset: strField(json['preset'], 'modern_restaurant'),
      primaryColor: parseColor(json['primary_color']?.toString(), const Color(0xFFC8102E)),
      accentColor: parseColor(json['accent_color']?.toString(), const Color(0xFFD97706)),
      fontFamily: strField(json['font_family'], 'Vazirmatn'),
      // border_radius arrives as a CSS length string like "0.75rem".
      borderRadius: parseRadius(json['border_radius']?.toString()),
    );
  }
}

class AppCurrencyConfig {
  final String storageUnit;
  final String displayUnit;
  final String label;
  final String symbol;
  final int decimals;

  const AppCurrencyConfig({
    this.storageUnit = 'IRR',
    this.displayUnit = 'IRT',
    this.label = 'تومان',
    this.symbol = 'تومان',
    this.decimals = 0,
  });

  factory AppCurrencyConfig.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const AppCurrencyConfig();
    return AppCurrencyConfig(
      storageUnit: strField(json['storage_unit'], 'IRR'),
      displayUnit: strField(json['display_unit'], 'IRT'),
      label: strField(json['label'], 'تومان'),
      symbol: strField(json['symbol'], 'تومان'),
      decimals: intField(json['decimals']),
    );
  }
}

/// OTP login constraints from the bootstrap `auth` block.
class AppAuthConfig {
  final int otpLength;
  final int otpResendSec;

  const AppAuthConfig({
    this.otpLength = 5,
    this.otpResendSec = 120,
  });

  factory AppAuthConfig.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const AppAuthConfig();
    return AppAuthConfig(
      otpLength: intField(json['otp_length'], 5),
      otpResendSec: intField(json['otp_resend_sec'], 120),
    );
  }
}

/// One selectable ordering mode from the bootstrap `ordering.modes` list.
class OrderingMode {
  final String id;
  final String label;
  final String icon;

  const OrderingMode({
    required this.id,
    required this.label,
    this.icon = '',
  });

  factory OrderingMode.fromJson(Map<String, dynamic> json) {
    return OrderingMode(
      id: strField(json['id']),
      label: strField(json['label']),
      icon: strField(json['icon']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'label': label,
      'icon': icon,
    };
  }
}

/// Server calendar block: today in both calendars.
class AppCalendarInfo {
  final String gregorianToday;
  final JalaliDateInfo jalaliToday;
  final String jalaliLabel;

  const AppCalendarInfo({
    this.gregorianToday = '',
    this.jalaliToday = const JalaliDateInfo(),
    this.jalaliLabel = '',
  });

  factory AppCalendarInfo.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const AppCalendarInfo();
    final jalaliRaw = json['jalali_today'];
    return AppCalendarInfo(
      gregorianToday: strField(json['gregorian_today']),
      jalaliToday: JalaliDateInfo.fromJson(
        jalaliRaw is Map<String, dynamic> ? jalaliRaw : null,
      ),
      jalaliLabel: strField(json['jalali_label']),
    );
  }
}

class AppBootstrapModel {
  final AppBrandInfo app;
  final AppDesignTokens design;
  final AppCurrencyConfig currency;
  final AppAuthConfig auth;
  final AppCalendarInfo calendar;
  final List<OrderingMode> orderingModes;
  final List<BranchModel> branches;
  final int defaultBranchId;
  final bool guestCheckout;

  const AppBootstrapModel({
    required this.app,
    required this.design,
    required this.currency,
    this.auth = const AppAuthConfig(),
    this.calendar = const AppCalendarInfo(),
    this.orderingModes = const [],
    this.branches = const [],
    this.defaultBranchId = 0,
    this.guestCheckout = true,
  });

  factory AppBootstrapModel.fromJson(Map<String, dynamic> json) {
    final branchList = (json['branches'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(BranchModel.fromJson)
            .toList() ??
        [];

    final ordering = json['ordering'];
    final orderingMap = ordering is Map<String, dynamic> ? ordering : const <String, dynamic>{};
    final modeList = (orderingMap['modes'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(OrderingMode.fromJson)
            .toList() ??
        [];

    return AppBootstrapModel(
      app: AppBrandInfo.fromJson(json['app'] as Map<String, dynamic>?),
      design: AppDesignTokens.fromJson(json['design'] as Map<String, dynamic>?),
      currency: AppCurrencyConfig.fromJson(json['currency'] as Map<String, dynamic>?),
      auth: AppAuthConfig.fromJson(json['auth'] as Map<String, dynamic>?),
      calendar: AppCalendarInfo.fromJson(json['calendar'] as Map<String, dynamic>?),
      orderingModes: modeList,
      branches: branchList,
      defaultBranchId: intField(json['default_branch_id']),
      guestCheckout: boolField(orderingMap['guest_checkout'], whenNull: true),
    );
  }
}
