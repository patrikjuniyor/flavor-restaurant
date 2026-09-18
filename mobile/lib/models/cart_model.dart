/// Shopping Cart domain models.

class CartItemModifier {
  final String id;
  final String name;
  final String type;
  final String typeLabel;
  final int price;

  const CartItemModifier({
    required this.id,
    required this.name,
    this.type = 'topping',
    this.typeLabel = 'مخلفات',
    this.price = 0,
  });

  factory CartItemModifier.fromJson(Map<String, dynamic> json) {
    return CartItemModifier(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      type: json['type']?.toString() ?? 'topping',
      typeLabel: json['type_label']?.toString() ?? (json['type']?.toString() ?? 'مخلفات'),
      price: int.tryParse(json['price']?.toString() ?? '0') ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'type': type,
      'type_label': typeLabel,
      'price': price,
    };
  }
}

class CartItem {
  final String key;
  final int productId;
  final String name;
  final int quantity;
  final String priceHtml;
  final String lineHtml;
  final String image;
  final List<CartItemModifier> modifiers;
  final String instructions;

  const CartItem({
    required this.key,
    required this.productId,
    required this.name,
    required this.quantity,
    required this.priceHtml,
    required this.lineHtml,
    this.image = '',
    this.modifiers = const [],
    this.instructions = '',
  });

  factory CartItem.fromJson(Map<String, dynamic> json) {
    final mods = (json['modifiers'] as List?)
            ?.map((e) => CartItemModifier.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    return CartItem(
      key: json['key']?.toString() ?? '',
      productId: int.tryParse(json['product_id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? '',
      quantity: int.tryParse(json['quantity']?.toString() ?? '1') ?? 1,
      priceHtml: json['price_html']?.toString() ?? '',
      lineHtml: json['line_html']?.toString() ?? '',
      image: json['image']?.toString() ?? '',
      modifiers: mods,
      instructions: json['instructions']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'key': key,
      'product_id': productId,
      'name': name,
      'quantity': quantity,
      'price_html': priceHtml,
      'line_html': lineHtml,
      'image': image,
      'modifiers': modifiers.map((e) => e.toJson()).toList(),
      'instructions': instructions,
    };
  }
}

class CartModel {
  final List<CartItem> items;
  final int count;
  final int subtotal;
  final String subtotalHtml;
  final int total;
  final String totalHtml;
  final List<Map<String, dynamic>> fees;
  final List<String> coupons;
  final int pointsToEarn;
  final bool needsPayment;

  const CartModel({
    this.items = const [],
    this.count = 0,
    this.subtotal = 0,
    this.subtotalHtml = '',
    this.total = 0,
    this.totalHtml = '',
    this.fees = const [],
    this.coupons = const [],
    this.pointsToEarn = 0,
    this.needsPayment = true,
  });

  factory CartModel.fromJson(Map<String, dynamic> json) {
    final itemsList = (json['items'] as List?)
            ?.map((e) => CartItem.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final feeList = (json['fees'] as List?)
            ?.map((e) => e as Map<String, dynamic>)
            .toList() ??
        [];

    final couponList = (json['coupons'] as List?)?.map((e) => e.toString()).toList() ?? [];

    return CartModel(
      items: itemsList,
      count: int.tryParse(json['count']?.toString() ?? '0') ?? itemsList.length,
      subtotal: int.tryParse(json['subtotal']?.toString() ?? '0') ?? 0,
      subtotalHtml: json['subtotal_html']?.toString() ?? '',
      total: int.tryParse(json['total']?.toString() ?? '0') ?? 0,
      totalHtml: json['total_html']?.toString() ?? '',
      fees: feeList,
      coupons: couponList,
      pointsToEarn: int.tryParse(json['points_to_earn']?.toString() ?? '0') ?? 0,
      needsPayment: json['needs_payment'] != false,
    );
  }

  bool get isEmpty => items.isEmpty;
  bool get isNotEmpty => items.isNotEmpty;
}
