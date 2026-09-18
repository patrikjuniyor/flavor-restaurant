/// Order, Order History, and Live Tracking domain models.

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
      step: json['step']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      completed: json['completed'] == true,
      time: json['time']?.toString() ?? '',
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

class OrderLineItem {
  final int id;
  final int productId;
  final String name;
  final int quantity;
  final int total;
  final String totalHtml;
  final String image;
  final String instructions;

  const OrderLineItem({
    required this.id,
    required this.productId,
    required this.name,
    required this.quantity,
    required this.total,
    required this.totalHtml,
    this.image = '',
    this.instructions = '',
  });

  factory OrderLineItem.fromJson(Map<String, dynamic> json) {
    return OrderLineItem(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      productId: int.tryParse(json['product_id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? '',
      quantity: int.tryParse(json['quantity']?.toString() ?? '1') ?? 1,
      total: int.tryParse(json['total']?.toString() ?? '0') ?? 0,
      totalHtml: json['total_html']?.toString() ?? '',
      image: json['image']?.toString() ?? '',
      instructions: json['instructions']?.toString() ?? '',
    );
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
  });

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    final lines = (json['items'] as List?)
            ?.map((e) => OrderLineItem.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final trackingSteps = (json['steps'] as List?)
            ?.map((e) => OrderTrackingStep.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    return OrderModel(
      id: int.tryParse(json['id']?.toString() ?? json['order_id']?.toString() ?? '0') ?? 0,
      orderNumber: json['order_number']?.toString() ?? '#0',
      status: json['status']?.toString() ?? (json['current_status']?.toString() ?? 'pending'),
      statusLabel: json['status_label']?.toString() ?? 'در حال پردازش',
      orderMode: json['order_mode']?.toString() ?? 'takeaway',
      itemsSummary: json['items_summary']?.toString() ?? '',
      itemsCount: int.tryParse(json['items_count']?.toString() ?? '1') ?? 1,
      total: int.tryParse(json['total']?.toString() ?? '0') ?? 0,
      totalHtml: json['total_html']?.toString() ?? '',
      date: json['date']?.toString() ?? '',
      jalaliDate: json['jalali_date']?.toString() ?? '',
      branchId: int.tryParse(json['branch_id']?.toString() ?? '0') ?? 0,
      paymentMethod: json['payment_method']?.toString() ?? '',
      tableNumber: json['table_number']?.toString() ?? '',
      notes: json['notes']?.toString() ?? '',
      items: lines,
      steps: trackingSteps,
      estimatedTime: json['estimated_time']?.toString() ?? '۳۰ الی ۴۵ دقیقه',
      isCancelled: json['is_cancelled'] == true || json['status'] == 'cancelled',
    );
  }
}
