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

  OrderProvider({OrderRepository? orderRepo}) : _orderRepo = orderRepo ?? OrderRepository();

  List<OrderModel> get orders => _orders;
  OrderModel? get trackedOrder => _trackedOrder;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

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
  }) async {
    _isLoading = true;
    _errorMessage = null;
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
      );
      return res;
    } catch (e) {
      _errorMessage = e.toString();
      return null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Starts live tracking with auto-polling every 12 seconds.
  void startTracking(int orderId) {
    _stopPolling();
    _fetchTracking(orderId);

    _pollingTimer = Timer.periodic(const Duration(seconds: 12), (_) {
      _fetchTracking(orderId);
    });
  }

  /// Stops tracking polling.
  void stopTracking() {
    _stopPolling();
    _trackedOrder = null;
    notifyListeners();
  }

  Future<void> _fetchTracking(int orderId) async {
    try {
      final order = await _orderRepo.trackOrder(orderId);
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
  Future<bool> cancelOrder(int orderId) async {
    try {
      await _orderRepo.cancelOrder(orderId);
      await loadOrders();
      if (_trackedOrder?.id == orderId) {
        await _fetchTracking(orderId);
      }
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }

  /// Reorders previous items.
  Future<bool> reorder(int orderId) async {
    _isLoading = true;
    notifyListeners();
    try {
      await _orderRepo.reorder(orderId);
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
