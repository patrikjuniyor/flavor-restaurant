/// User Profile and Loyalty domain models.
///
/// Mirrors AuthController::format_user_profile() + PointsManager::summary() —
/// see test/fixtures/user_profile.json and auth_otp_verify.json.
library;

import 'json_fields.dart';

/// Loyalty stamp-card summary. The API contract is PointsManager::summary():
/// {points, stamps, stamp_target, stamp_cycle, free_item}. (An earlier model
/// invented balance/tier/discount_equivalent keys the backend never sent —
/// prompt 65 aligned it with the real payload.)
class LoyaltySummary {
  final int points;
  final int stamps;
  final int stampTarget;
  final int stampCycle;
  final bool freeItem;

  const LoyaltySummary({
    this.points = 0,
    this.stamps = 0,
    this.stampTarget = 10,
    this.stampCycle = 0,
    this.freeItem = false,
  });

  factory LoyaltySummary.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const LoyaltySummary();
    return LoyaltySummary(
      points: intField(json['points']),
      stamps: intField(json['stamps']),
      stampTarget: intField(json['stamp_target'], 10),
      stampCycle: intField(json['stamp_cycle']),
      freeItem: boolField(json['free_item']),
    );
  }

  /// A free item is redeemable when the flag is set or a full stamp cycle
  /// has been completed.
  bool get hasFreeItemReward => freeItem || (stampTarget > 0 && stamps >= stampTarget);

  Map<String, dynamic> toJson() {
    return {
      'points': points,
      'stamps': stamps,
      'stamp_target': stampTarget,
      'stamp_cycle': stampCycle,
      'free_item': freeItem,
    };
  }
}

class SavedAddress {
  final String id;
  final String title;
  final String address;
  final String province;

  /// Coordinates are PHP floats or explicit nulls when the address was
  /// saved without a map pin.
  final double? lat;
  final double? lng;
  final String unit;
  final String postal;

  const SavedAddress({
    required this.id,
    required this.title,
    required this.address,
    this.province = 'تهران',
    this.lat,
    this.lng,
    this.unit = '',
    this.postal = '',
  });

  factory SavedAddress.fromJson(Map<String, dynamic> json) {
    return SavedAddress(
      id: strField(json['id']),
      title: strField(json['title'], 'آدرس من'),
      address: strField(json['address']),
      province: strField(json['province'], 'تهران'),
      lat: nullableDoubleField(json['lat']),
      lng: nullableDoubleField(json['lng']),
      unit: strField(json['unit']),
      postal: strField(json['postal']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'address': address,
      'province': province,
      'lat': lat,
      'lng': lng,
      'unit': unit,
      'postal': postal,
    };
  }
}

class UserModel {
  final int id;
  final String displayName;

  /// Empty string for OTP-only accounts: the API strips the synthetic
  /// `@otp.flavor.local` placeholder address before serializing.
  final String email;
  final String mobile;
  final String avatarUrl;
  final LoyaltySummary loyalty;
  final List<String> dietaryPreferences;
  final List<SavedAddress> savedAddresses;
  final bool isKitchenStaff;
  final bool isAdmin;
  final bool isDriver;

  const UserModel({
    required this.id,
    required this.displayName,
    required this.email,
    required this.mobile,
    required this.avatarUrl,
    required this.loyalty,
    this.dietaryPreferences = const [],
    this.savedAddresses = const [],
    this.isKitchenStaff = false,
    this.isAdmin = false,
    this.isDriver = false,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final prefs = (json['dietary_preferences'] as List?)?.map((e) => e.toString()).toList() ?? [];
    final addrs = (json['saved_addresses'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(SavedAddress.fromJson)
            .toList() ??
        [];

    final caps = json['capabilities'];
    final capsMap = caps is Map<String, dynamic> ? caps : const <String, dynamic>{};

    return UserModel(
      id: intField(json['id']),
      displayName: strField(json['display_name'], 'مشتری گرامی'),
      email: strField(json['email']),
      mobile: strField(json['mobile']),
      avatarUrl: strField(json['avatar_url']),
      loyalty: LoyaltySummary.fromJson(json['loyalty'] as Map<String, dynamic>?),
      dietaryPreferences: prefs,
      savedAddresses: addrs,
      isKitchenStaff: boolField(capsMap['is_kitchen_staff']),
      isAdmin: boolField(capsMap['is_admin']),
      isDriver: boolField(capsMap['is_driver']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'display_name': displayName,
      'email': email,
      'mobile': mobile,
      'avatar_url': avatarUrl,
      'loyalty': loyalty.toJson(),
      'dietary_preferences': dietaryPreferences,
      'saved_addresses': savedAddresses.map((e) => e.toJson()).toList(),
      'capabilities': {
        'is_kitchen_staff': isKitchenStaff,
        'is_admin': isAdmin,
        'is_driver': isDriver,
      },
    };
  }
}
