/// Branch, Dining Table, and Delivery Zone domain models.

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
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      tableNumber: json['table_number']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      capacity: int.tryParse(json['capacity']?.toString() ?? '4') ?? 4,
      section: json['section']?.toString() ?? 'indoor',
      qrToken: json['qr_token']?.toString() ?? '',
      qrUrl: json['qr_url']?.toString() ?? '',
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
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? '',
      zoneType: json['zone_type']?.toString() ?? 'neighborhoods',
      deliveryFee: int.tryParse(json['delivery_fee']?.toString() ?? '0') ?? 0,
      deliveryFeeHtml: json['delivery_fee_html']?.toString() ?? '',
      minOrder: int.tryParse(json['min_order']?.toString() ?? '0') ?? 0,
      minOrderHtml: json['min_order_html']?.toString() ?? '',
      estimatedMinutes: int.tryParse(json['estimated_minutes']?.toString() ?? '45') ?? 45,
      neighborhoods: hoods,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'zone_type': zoneType,
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
  final List<String> orderModes;
  final bool isDefault;
  final bool isOpen;
  final String thumbnail;
  final int menuVersion;

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
    this.orderModes = const ['dine_in', 'takeaway', 'delivery'],
    this.isDefault = false,
    this.isOpen = true,
    this.thumbnail = '',
    this.menuVersion = 1,
  });

  factory BranchModel.fromJson(Map<String, dynamic> json) {
    final modes = (json['order_modes'] as List?)?.map((e) => e.toString()).toList() ??
        ['dine_in', 'takeaway', 'delivery'];

    return BranchModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? 'شعبه اصلی',
      slug: json['slug']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      province: json['province']?.toString() ?? 'تهران',
      city: json['city']?.toString() ?? 'تهران',
      neighborhood: json['neighborhood']?.toString() ?? '',
      lat: json['lat']?.toString(),
      lng: json['lng']?.toString(),
      orderModes: modes,
      isDefault: json['is_default'] == true,
      isOpen: json['is_open'] != false,
      thumbnail: json['thumbnail']?.toString() ?? '',
      menuVersion: int.tryParse(json['menu_version']?.toString() ?? '1') ?? 1,
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
      'order_modes': orderModes,
      'is_default': isDefault,
      'is_open': isOpen,
      'thumbnail': thumbnail,
      'menu_version': menuVersion,
    };
  }
}
