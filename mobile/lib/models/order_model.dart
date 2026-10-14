/// Order, Order History, and Live Tracking domain models.
///
/// Mirrors OrderController::format_order_summary()/format_order_detail()/
/// track_order() — see test/fixtures/orders_list.json, order_detail.json and
/// order_track.json. The same OrderModel parses all three shapes: the list
/// summary, the full detail and the tracking payload (which identifies the
/// order via `order_id`/`current_status` instead of `id`/`status`).
library;

import 'cart_model.dart' show CartItemModifier;
import 'json_fields.dart';

class OrderTrackingStep {
  final String step;
  final String title;
  final bool completed;
  final String time;

  const OrderTrackingStep({
    required this.step,
    required this.title,
    required this.completed,
    this.time = '',
  });

  factory OrderTrackingStep.fromJson(Map<String, dynamic> json) {
    return OrderTrackingStep(
      step: strField(json['step']),
      title: strField(json['title']),
      completed: boolField(json['completed']),
      time: strField(json['time']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'step': step,
      'title': title,
      'completed': completed,
      'time': time,
    };
  }
}

/// Billing/delivery contact block from format_order_detail().
class OrderBillingAddress {
  final String name;
  final String phone;
  final String address;
  final String city;
  final String province;

  const OrderBillingAddress({
    this.name = '',
    this.phone = '',
    this.address = '',
    this.city = '',
    this.province = '',
  });

  factory OrderBillingAddress.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const OrderBillingAddress();
    return OrderBillingAddress(
      name: strField(json['name']),
      phone: strField(json['phone']),
      address: strField(json['address']),
      city: strField(json['city']),
      province: strField(json['province']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'name': name,
      'phone': phone,
      'address': address,
      'city': city,
      'province': province,
    };
  }
}

class OrderLineItem {
  final int id;
  final int productId;
  final String name;
  final int quantity;
  final int total;
  final String totalHtml;

  /// Storage-unit formatted label from format_order_detail().
  final String lineTotalDisplay;

  /// Sanitized modifier selection rows persisted on the order line
  /// (`_flavor_modifiers` item meta) — same shape as cart item modifiers.
  final List<CartItemModifier> modifiers;

  /// Can be PHP `false` (no thumbnail) — strField maps that to ''.
  final String image;
  final String instructions;

  const OrderLineItem({
    required this.id,
    required this.productId,
    required this.name,
    required this.quantity,
    required this.total,
    required this.totalHtml,
    this.lineTotalDisplay = '',
    this.modifiers = const [],
    this.image = '',
    this.instructions = '',
  });

  factory OrderLineItem.fromJson(Map<String, dynamic> json) {
    final rawModifiers = json['modifiers'];
    final mods = rawModifiers is List
        ? rawModifiers
            .whereType<Map<String, dynamic>>()
            .map(CartItemModifier.fromJson)
            .toList()
        : <CartItemModifier>[];

    return OrderLineItem(
      id: intField(json['id']),
      productId: intField(json['product_id']),
      name: strField(json['name']),
      quantity: intField(json['quantity'], 1),
      total: intField(json['total']),
      totalHtml: strField(json['total_html']),
      lineTotalDisplay: strField(json['line_total_display']),
      modifiers: mods,
      image: strField(json['image']),
      instructions: strField(json['instructions']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'product_id': productId,
      'name': name,
      'quantity': quantity,
      'total': total,
      'total_html': totalHtml,
      'line_total_display': lineTotalDisplay,
      'modifiers': modifiers.map((e) => e.toJson()).toList(),
      'image': image,
      'instructions': instructions,
    };
  }
}

class OrderModel {
  final int id;
  final String orderNumber;
  final String status;
  final String statusLabel;
  final String orderMode;
  final String itemsSummary;
  final int itemsCount;
  final int total;
  final String totalHtml;
  final String date;
  final String jalaliDate;
  final int branchId;
  final String paymentMethod;
  final String tableNumber;
  final String notes;
  final List<OrderLineItem> items;
  final List<OrderTrackingStep> steps;
  final String estimatedTime;
  final bool isCancelled;

  /// Detail payload only.
  final OrderBillingAddress billingAddress;

  /// Kitchen ticket status ('new' when no ticket exists yet) and whether the
  /// ticket was actually found — detail payload only.
  final String kitchenStatus;
  final bool kitchenStatusKnown;

  const OrderModel({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.statusLabel,
    required this.orderMode,
    this.itemsSummary = '',
    this.itemsCount = 1,
    required this.total,
    required this.totalHtml,
    this.date = '',
    this.jalaliDate = '',
    this.branchId = 0,
    this.paymentMethod = '',
    this.tableNumber = '',
    this.notes = '',
    this.items = const [],
    this.steps = const [],
    this.estimatedTime = '۳۰ الی ۴۵ دقیقه',
    this.isCancelled = false,
    this.billingAddress = const OrderBillingAddress(),
    this.kitchenStatus = 'new',
    this.kitchenStatusKnown = false,
  });

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    final lines = (json['items'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(OrderLineItem.fromJson)
            .toList() ??
        [];

    final trackingSteps = (json['steps'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(OrderTrackingStep.fromJson)
            .toList() ??
        [];

    final status = strField(json['status'], strField(json['current_status'], 'pending'));

    return OrderModel(
      id: intField(json['id'], intField(json['order_id'])),
      orderNumber: strField(json['order_number'], '#0'),
      status: status,
      statusLabel: strField(json['status_label'], 'در حال پردازش'),
      orderMode: strField(json['order_mode'], 'takeaway'),
      itemsSummary: strField(json['items_summary']),
      itemsCount: intField(json['items_count'], 1),
      total: intField(json['total']),
      totalHtml: strField(json['total_html']),
      date: strField(json['date']),
      jalaliDate: strField(json['jalali_date']),
      branchId: intField(json['branch_id']),
      paymentMethod: strField(json['payment_method']),
      tableNumber: strField(json['table_number']),
      notes: strField(json['notes']),
      items: lines,
      steps: trackingSteps,
      estimatedTime: strField(json['estimated_time'], '۳۰ الی ۴۵ دقیقه'),
      isCancelled: boolField(json['is_cancelled']) || status == 'cancelled',
      billingAddress:
          OrderBillingAddress.fromJson(json['billing_address'] as Map<String, dynamic>?),
      kitchenStatus: strField(json['kitchen_status'], 'new'),
      kitchenStatusKnown: boolField(json['kitchen_status_known']),
    );
  }
}
