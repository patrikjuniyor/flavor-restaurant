/// Shopping Cart domain models.
///
/// Mirrors CartController::build_cart_payload() + CartSession::payload() —
/// see test/fixtures/cart.json and test/fixtures/cart_update_item.json.
library;

import 'json_fields.dart';

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
      id: strField(json['id']),
      name: strField(json['name']),
      type: strField(json['type'], 'topping'),
      typeLabel: strField(json['type_label'], strField(json['type'], 'مخلفات')),
      price: intField(json['price']),
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

  /// Catalog-unit amounts as PHP floats (`unit_price_raw`/`line_total_raw`).
  /// They already include modifier extras: the server re-prices the line's
  /// product object before serializing.
  final double unitPriceRaw;
  final double lineTotalRaw;

  /// Authoritative storage-unit integers (`unit_price`/`line_total`) plus
  /// their pre-formatted Persian labels.
  final int unitPrice;
  final int lineTotal;
  final String unitPriceHtml;
  final String lineTotalHtml;

  /// Legacy display strings; kept because older payloads label them
  /// price_html/line_html.
  final String priceHtml;
  final String lineHtml;

  /// Can be PHP `false` (no thumbnail) — strField maps that to ''.
  final String image;
  final List<CartItemModifier> modifiers;
  final String instructions;

  const CartItem({
    required this.key,
    required this.productId,
    required this.name,
    required this.quantity,
    this.unitPriceRaw = 0,
    this.lineTotalRaw = 0,
    this.unitPrice = 0,
    this.lineTotal = 0,
    this.unitPriceHtml = '',
    this.lineTotalHtml = '',
    required this.priceHtml,
    required this.lineHtml,
    this.image = '',
    this.modifiers = const [],
    this.instructions = '',
  });

  factory CartItem.fromJson(Map<String, dynamic> json) {
    final rawModifiers = json['modifiers'];
    // The legacy mock/old-client shape {'ids': [...]} carries no display rows;
    // the real API stores the sanitized selection list (rows with
    // id/type/type_label/name/price).
    final mods = rawModifiers is List
        ? rawModifiers
            .whereType<Map<String, dynamic>>()
            .map(CartItemModifier.fromJson)
            .toList()
        : <CartItemModifier>[];

    return CartItem(
      key: strField(json['key']),
      productId: intField(json['product_id']),
      name: strField(json['name']),
      quantity: intField(json['quantity'], 1),
      unitPriceRaw: doubleField(json['unit_price_raw']),
      lineTotalRaw: doubleField(json['line_total_raw']),
      unitPrice: intField(json['unit_price']),
      lineTotal: intField(json['line_total']),
      unitPriceHtml: strField(json['unit_price_html']),
      lineTotalHtml: strField(json['line_total_html']),
      priceHtml: strField(json['price_html']),
      lineHtml: strField(json['line_html']),
      image: strField(json['image']),
      modifiers: mods,
      instructions: strField(json['instructions']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'key': key,
      'product_id': productId,
      'name': name,
      'quantity': quantity,
      'unit_price_raw': unitPriceRaw,
      'line_total_raw': lineTotalRaw,
      'unit_price': unitPrice,
      'line_total': lineTotal,
      'unit_price_html': unitPriceHtml,
      'line_total_html': lineTotalHtml,
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

  /// Totals breakdown, storage-unit integers + Persian labels, exactly as
  /// build_cart_payload() emits them.
  final int discountTotal;
  final String discountHtml;
  final int taxTotal;
  final String taxHtml;
  final int shippingTotal;
  final String shippingHtml;

  /// Fee rows stay raw maps: {name, total, html, amount_stored, amount_html}.
  final List<Map<String, dynamic>> fees;
  final List<String> coupons;
  final int pointsToEarn;
  final bool needsPayment;

  /// Additive capability flag from build_cart_payload(). An older core that
  /// predates line editing omits the key entirely, so "absent" must mean
  /// NOT supported (whenNull: false) — the edit UI stays hidden there.
  final bool supportsItemEdit;

  const CartModel({
    this.items = const [],
    this.count = 0,
    this.subtotal = 0,
    this.subtotalHtml = '',
    this.total = 0,
    this.totalHtml = '',
    this.discountTotal = 0,
    this.discountHtml = '',
    this.taxTotal = 0,
    this.taxHtml = '',
    this.shippingTotal = 0,
    this.shippingHtml = '',
    this.fees = const [],
    this.coupons = const [],
    this.pointsToEarn = 0,
    this.needsPayment = true,
    this.supportsItemEdit = false,
  });

  factory CartModel.fromJson(Map<String, dynamic> json) {
    final itemsList = (json['items'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(CartItem.fromJson)
            .toList() ??
        [];

    final feeList = (json['fees'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .toList() ??
        [];

    final couponList = (json['coupons'] as List?)?.map((e) => e.toString()).toList() ?? [];

    return CartModel(
      items: itemsList,
      count: intField(json['count'], itemsList.length),
      subtotal: intField(json['subtotal']),
      subtotalHtml: strField(json['subtotal_html']),
      total: intField(json['total']),
      totalHtml: strField(json['total_html']),
      discountTotal: intField(json['discount_total']),
      discountHtml: strField(json['discount_html']),
      taxTotal: intField(json['tax_total']),
      taxHtml: strField(json['tax_html']),
      shippingTotal: intField(json['shipping_total']),
      shippingHtml: strField(json['shipping_html']),
      fees: feeList,
      coupons: couponList,
      pointsToEarn: intField(json['points_to_earn']),
      needsPayment: boolField(json['needs_payment'], whenNull: true),
      supportsItemEdit: boolField(json['supports_item_edit']),
    );
  }

  bool get isEmpty => items.isEmpty;
  bool get isNotEmpty => items.isNotEmpty;
}
