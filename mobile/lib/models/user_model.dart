/// User Profile and Loyalty domain models.

class LoyaltySummary {
  final int balance;
  final String balanceFormatted;
  final String tier;
  final int discountEquivalent;

  const LoyaltySummary({
    required this.balance,
    required this.balanceFormatted,
    required this.tier,
    required this.discountEquivalent,
  });

  factory LoyaltySummary.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const LoyaltySummary(
        balance: 0,
        balanceFormatted: '۰ امتیاز',
        tier: 'برنزی',
        discountEquivalent: 0,
      );
    }
    return LoyaltySummary(
      balance: int.tryParse(json['balance']?.toString() ?? '0') ?? 0,
      balanceFormatted: json['balance_formatted']?.toString() ?? '۰ امتیاز',
      tier: json['tier']?.toString() ?? 'برنزی',
      discountEquivalent: int.tryParse(json['discount_equivalent']?.toString() ?? '0') ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'balance': balance,
      'balance_formatted': balanceFormatted,
      'tier': tier,
      'discount_equivalent': discountEquivalent,
    };
  }
}

class SavedAddress {
  final String id;
  final String title;
  final String address;
  final String province;
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
      id: json['id']?.toString() ?? '',
      title: json['title']?.toString() ?? 'آدرس من',
      address: json['address']?.toString() ?? '',
      province: json['province']?.toString() ?? 'تهران',
      lat: json['lat'] != null ? double.tryParse(json['lat'].toString()) : null,
      lng: json['lng'] != null ? double.tryParse(json['lng'].toString()) : null,
      unit: json['unit']?.toString() ?? '',
      postal: json['postal']?.toString() ?? '',
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
  final String email;
  final String mobile;
  final String avatarUrl;
  final LoyaltySummary loyalty;
  final List<String> dietaryPreferences;
  final List<SavedAddress> savedAddresses;
  final bool isKitchenStaff;
  final bool isAdmin;

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
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final prefs = (json['dietary_preferences'] as List?)?.map((e) => e.toString()).toList() ?? [];
    final addrs = (json['saved_addresses'] as List?)
            ?.map((e) => SavedAddress.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final caps = json['capabilities'] as Map<String, dynamic>?;

    return UserModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      displayName: json['display_name']?.toString() ?? 'مشتری گرامی',
      email: json['email']?.toString() ?? '',
      mobile: json['mobile']?.toString() ?? '',
      avatarUrl: json['avatar_url']?.toString() ?? '',
      loyalty: LoyaltySummary.fromJson(json['loyalty'] as Map<String, dynamic>?),
      dietaryPreferences: prefs,
      savedAddresses: addrs,
      isKitchenStaff: caps?['is_kitchen_staff'] == true,
      isAdmin: caps?['is_admin'] == true,
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
      },
    };
  }
}
