import 'package:flutter/material.dart';
import 'branch_model.dart';

/// App Bootstrap Configuration domain model from /settings/app-bootstrap.

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
    this.currentVersion = '1.1.0',
  });

  factory AppBrandInfo.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const AppBrandInfo(name: 'رستوران طعم');
    }
    return AppBrandInfo(
      name: json['name']?.toString() ?? 'رستوران طعم',
      description: json['description']?.toString() ?? '',
      logo: json['logo']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      instagram: json['instagram']?.toString() ?? '',
      maintenanceMode: json['maintenance_mode'] == true,
      minAppVersion: json['min_app_version']?.toString() ?? '1.0.0',
      currentVersion: json['current_version']?.toString() ?? '1.1.0',
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
      preset: json['preset']?.toString() ?? 'modern_restaurant',
      primaryColor: parseColor(json['primary_color']?.toString(), const Color(0xFFC8102E)),
      accentColor: parseColor(json['accent_color']?.toString(), const Color(0xFFD97706)),
      fontFamily: json['font_family']?.toString() ?? 'Vazirmatn',
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
      storageUnit: json['storage_unit']?.toString() ?? 'IRR',
      displayUnit: json['display_unit']?.toString() ?? 'IRT',
      label: json['label']?.toString() ?? 'تومان',
      symbol: json['symbol']?.toString() ?? 'تومان',
      decimals: int.tryParse(json['decimals']?.toString() ?? '0') ?? 0,
    );
  }
}

class AppBootstrapModel {
  final AppBrandInfo app;
  final AppDesignTokens design;
  final AppCurrencyConfig currency;
  final List<BranchModel> branches;
  final int defaultBranchId;
  final bool guestCheckout;

  const AppBootstrapModel({
    required this.app,
    required this.design,
    required this.currency,
    this.branches = const [],
    this.defaultBranchId = 0,
    this.guestCheckout = true,
  });

  factory AppBootstrapModel.fromJson(Map<String, dynamic> json) {
    final branchList = (json['branches'] as List?)
            ?.map((e) => BranchModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final ordering = json['ordering'] as Map<String, dynamic>?;

    return AppBootstrapModel(
      app: AppBrandInfo.fromJson(json['app'] as Map<String, dynamic>?),
      design: AppDesignTokens.fromJson(json['design'] as Map<String, dynamic>?),
      currency: AppCurrencyConfig.fromJson(json['currency'] as Map<String, dynamic>?),
      branches: branchList,
      defaultBranchId: int.tryParse(json['default_branch_id']?.toString() ?? '0') ?? 0,
      guestCheckout: ordering?['guest_checkout'] != false,
    );
  }
}
