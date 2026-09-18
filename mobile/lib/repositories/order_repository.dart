import '../config/constants.dart';
import '../core/api/api_client.dart';
import '../models/order_model.dart';

/// Repository for Checkout, Order History, Live Tracking, and Reorders.
/// Supports guest token verification for secure guest order tracking.
class OrderRepository {
  final ApiClient _apiClient;

  OrderRepository({ApiClient? apiClient}) : _apiClient = apiClient ?? ApiClient();

  /// Submits new order (Dine-in, Takeaway, or Delivery).
  Future<Map<String, dynamic>> createOrder({
    required String orderMode,
    required int branchId,
    String? mobile,
    String? name,
    int? tableId,
    String? tableNumber,
    Map<String, dynamic>? address,
    String paymentMethod = 'flavor_pay_at_counter',
    String? notes,
  }) async {
    final body = <String, dynamic>{
      'order_mode': orderMode,
      'branch_id': branchId,
      'payment_method': paymentMethod,
    };
    if (mobile != null && mobile.isNotEmpty) body['mobile'] = mobile;
    if (name != null && name.isNotEmpty) body['name'] = name;
    if (tableId != null && tableId > 0) body['table_id'] = tableId;
    if (tableNumber != null && tableNumber.isNotEmpty) body['table_number'] = tableNumber;
    if (address != null) body['address'] = address;
    if (notes != null && notes.isNotEmpty) body['notes'] = notes;

    final res = await _apiClient.post(AppConstants.epOrders, body: body);
    return res['data'] as Map<String, dynamic>;
  }

  /// Gets customer order history with pagination.
  Future<List<OrderModel>> getOrders({int page = 1, int perPage = 10}) async {
    final res = await _apiClient.get(
      AppConstants.epOrders,
      queryParameters: {'page': page, 'per_page': perPage},
      requiresAuth: true,
    );
    final list = res['data'] as List;
    return list.map((e) => OrderModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Gets single order details with optional guest token.
  Future<OrderModel> getOrder(int id, {String? guestToken}) async {
    final params = <String, dynamic>{};
    if (guestToken != null && guestToken.isNotEmpty) {
      params['guest_token'] = guestToken;
    }
    final res = await _apiClient.get('${AppConstants.epOrders}/$id', queryParameters: params);
    return OrderModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Live tracking endpoint with real-time step status and optional guest token.
  Future<OrderModel> trackOrder(int id, {String? guestToken}) async {
    final params = <String, dynamic>{};
    if (guestToken != null && guestToken.isNotEmpty) {
      params['guest_token'] = guestToken;
    }
    final res = await _apiClient.get('${AppConstants.epOrders}/$id/track', queryParameters: params);
    return OrderModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Cancels an order.
  Future<void> cancelOrder(int id, {String? guestToken}) async {
    final params = <String, dynamic>{};
    if (guestToken != null && guestToken.isNotEmpty) {
      params['guest_token'] = guestToken;
    }
    await _apiClient.post('${AppConstants.epOrders}/$id/cancel', queryParameters: params);
  }

  /// Adds all items from previous order back into active cart.
  Future<Map<String, dynamic>> reorder(int id, {String? guestToken}) async {
    final params = <String, dynamic>{};
    if (guestToken != null && guestToken.isNotEmpty) {
      params['guest_token'] = guestToken;
    }
    final res = await _apiClient.post('${AppConstants.epOrders}/$id/reorder', queryParameters: params);
    return res['data'] as Map<String, dynamic>;
  }
}
