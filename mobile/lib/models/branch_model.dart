/// Branch, Dining Table, and Delivery Zone domain models.
///
/// Mirrors BranchController + BranchPostType::to_array() — see
/// test/fixtures/branches.json, branch_detail.json, tables.json, zones.json.
library;

import 'json_fields.dart';

class DiningTable {
  final int id;
  final String tableNumber;
  final String label;
  final int capacity;
  final String section;
  final String qrToken;
  final String qrUrl;

  const DiningTable({
    required this.id,
    required this.tableNumber,
    required this.label,
    required this.capacity,
    required this.section,
    required this.qrToken,
    required this.qrUrl,
  });

  factory DiningTable.fromJson(Map<String, dynamic> json) {
    return DiningTable(
      id: intField(json['id']),
      tableNumber: strField(json['table_number']),
      label: strField(json['label']),
      capacity: intField(json['capacity'], 4),
      section: strField(json['section'], 'indoor'),
      qrToken: strField(json['qr_token']),
      qrUrl: strField(json['qr_url']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'table_number': tableNumber,
      'label': label,
      'capacity': capacity,
      'section': section,
      'qr_token': qrToken,
      'qr_url': qrUrl,
    };
  }
}

class DeliveryZone {
  final int id;
  final String name;
  final String zoneType;

  /// Radius zones only; the API sends an explicit `null` for neighborhood
  /// zones (fixtures/zones.json), so this stays nullable.
  final double? radiusKm;
  final int deliveryFee;
  final String deliveryFeeHtml;
  final int minOrder;
  final String minOrderHtml;
  final int estimatedMinutes;
  final List<String> neighborhoods;

  const DeliveryZone({
    required this.id,
    required this.name,
    required this.zoneType,
    this.radiusKm,
    required this.deliveryFee,
    required this.deliveryFeeHtml,
    required this.minOrder,
    required this.minOrderHtml,
    required this.estimatedMinutes,
    this.neighborhoods = const [],
  });

  factory DeliveryZone.fromJson(Map<String, dynamic> json) {
    final hoods = (json['neighborhoods'] as List?)?.map((e) => e.toString()).toList() ?? [];
    return DeliveryZone(
      id: intField(json['id']),
      name: strField(json['name']),
      zoneType: strField(json['zone_type'], 'neighborhoods'),
      radiusKm: nullableDoubleField(json['radius_km']),
      deliveryFee: intField(json['delivery_fee']),
      deliveryFeeHtml: strField(json['delivery_fee_html']),
      minOrder: intField(json['min_order']),
      minOrderHtml: strField(json['min_order_html']),
      estimatedMinutes: intField(json['estimated_minutes'], 45),
      neighborhoods: hoods,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'zone_type': zoneType,
      'radius_km': radiusKm,
      'delivery_fee': deliveryFee,
      'delivery_fee_html': deliveryFeeHtml,
      'min_order': minOrder,
      'min_order_html': minOrderHtml,
      'estimated_minutes': estimatedMinutes,
      'neighborhoods': neighborhoods,
    };
  }
}

class BranchModel {
  final int id;
  final String name;
  final String slug;
  final String phone;
  final String address;
  final String province;
  final String city;
  final String neighborhood;
  final String? lat;
  final String? lng;
  final String timezone;
  final List<String> orderModes;
  final bool isDefault;
  final bool isOpen;
  final String thumbnail;
  final int menuVersion;
  final String permalink;

  const BranchModel({
    required this.id,
    required this.name,
    required this.slug,
    required this.phone,
    required this.address,
    this.province = 'تهران',
    this.city = 'تهران',
    this.neighborhood = '',
    this.lat,
    this.lng,
    this.timezone = 'Asia/Tehran',
    this.orderModes = const ['dine_in', 'takeaway', 'delivery'],
    this.isDefault = false,
    this.isOpen = true,
    this.thumbnail = '',
    this.menuVersion = 1,
    this.permalink = '',
  });

  factory BranchModel.fromJson(Map<String, dynamic> json) {
    final modes = (json['order_modes'] as List?)?.map((e) => e.toString()).toList() ??
        ['dine_in', 'takeaway', 'delivery'];

    return BranchModel(
      id: intField(json['id']),
      name: strField(json['name'], 'شعبه اصلی'),
      slug: strField(json['slug']),
      phone: strField(json['phone']),
      address: strField(json['address']),
      province: strField(json['province'], 'تهران'),
      city: strField(json['city'], 'تهران'),
      neighborhood: strField(json['neighborhood']),
      // to_array() casts coordinates to string, so "unset" arrives as '' —
      // keep null only for a genuinely absent key.
      lat: json['lat'] == null ? null : strField(json['lat']),
      lng: json['lng'] == null ? null : strField(json['lng']),
      timezone: strField(json['timezone'], 'Asia/Tehran'),
      orderModes: modes,
      isDefault: boolField(json['is_default']),
      isOpen: boolField(json['is_open'], whenNull: true),
      thumbnail: strField(json['thumbnail']),
      menuVersion: intField(json['menu_version'], 1),
      permalink: strField(json['permalink']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'slug': slug,
      'phone': phone,
      'address': address,
      'province': province,
      'city': city,
      'neighborhood': neighborhood,
      'lat': lat,
      'lng': lng,
      'timezone': timezone,
      'order_modes': orderModes,
      'is_default': isDefault,
      'is_open': isOpen,
      'thumbnail': thumbnail,
      'menu_version': menuVersion,
      'permalink': permalink,
    };
  }
}
