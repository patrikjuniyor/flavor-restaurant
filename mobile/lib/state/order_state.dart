import 'dart:async';
import 'package:flutter/material.dart';
import '../models/order_model.dart';
import '../repositories/order_repository.dart';

/// State management for Orders, History, and Live Tracking polling.
class OrderProvider extends ChangeNotifier {
  final OrderRepository _orderRepo;

  List<OrderModel> _orders = [];
  OrderModel? _trackedOrder;
  Timer? _pollingTimer;

  bool _isLoading = false;
  String? _errorMessage;

  /// Prompt 67: the typed exception behind [_errorMessage] so callers can
  /// branch on ApiException.code/statusCode (e.g. the 409 idempotency
  /// conflict) while the SnackBar keeps showing the Persian [errorMessage].
  Object? _lastException;

  OrderProvider({OrderRepository? orderRepo}) : _orderRepo = orderRepo ?? OrderRepository();

  List<OrderModel> get orders => _orders;
  OrderModel? get trackedOrder => _trackedOrder;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  Object? get lastException => _lastException;

  /// Loads customer order history.
  Future<void> loadOrders({int page = 1}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _orders = await _orderRepo.getOrders(page: page);
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Submits checkout order.
  Future<Map<String, dynamic>?> submitOrder({
    required String orderMode,
    required int branchId,
    String? mobile,
    String? name,
    int? tableId,
    String? tableNumber,
    Map<String, dynamic>? address,
    String paymentMethod = 'flavor_pay_at_counter',
    String? notes,
    String? idempotencyKey,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    _lastException = null;
    notifyListeners();

    try {
      final res = await _orderRepo.createOrder(
        orderMode: orderMode,
        branchId: branchId,
        mobile: mobile,
        name: name,
        tableId: tableId,
        tableNumber: tableNumber,
        address: address,
        paymentMethod: paymentMethod,
        notes: notes,
        idempotencyKey: idempotencyKey,
      );
      return res;
    } catch (e) {
      _lastException = e;
      // ApiException.toString() is the Persian server message itself, so the
      // raw exception text never reaches the UI (prompt 67).
      _errorMessage = e.toString();
      return null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Starts live tracking with auto-polling every 12 seconds.
  void startTracking(int orderId, {String? guestToken}) {
    _stopPolling();
    _fetchTracking(orderId, guestToken: guestToken);

    _pollingTimer = Timer.periodic(const Duration(seconds: 12), (_) {
      _fetchTracking(orderId, guestToken: guestToken);
    });
  }

  /// Stops tracking polling.
  void stopTracking() {
    _stopPolling();
    _trackedOrder = null;
    notifyListeners();
  }

  Future<void> _fetchTracking(int orderId, {String? guestToken}) async {
    try {
      final order = await _orderRepo.trackOrder(orderId, guestToken: guestToken);
      _trackedOrder = order;
      notifyListeners();

      if (order.status == 'completed' || order.status == 'cancelled') {
        _stopPolling();
      }
    } catch (_) {}
  }

  void _stopPolling() {
    _pollingTimer?.cancel();
    _pollingTimer = null;
  }

  /// Cancels an active order.
  Future<bool> cancelOrder(int orderId, {String? guestToken}) async {
    try {
      await _orderRepo.cancelOrder(orderId, guestToken: guestToken);
      await loadOrders();
      if (_trackedOrder?.id == orderId) {
        await _fetchTracking(orderId, guestToken: guestToken);
      }
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }

  /// Reorders previous items.
  Future<bool> reorder(int orderId, {String? guestToken}) async {
    _isLoading = true;
    notifyListeners();
    try {
      await _orderRepo.reorder(orderId, guestToken: guestToken);
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _stopPolling();
    super.dispose();
  }
}
