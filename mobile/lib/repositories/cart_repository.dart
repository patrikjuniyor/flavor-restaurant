import '../config/constants.dart';
import '../core/api/api_client.dart';
import '../models/cart_model.dart';

/// Repository for Shopping Cart operations, Coupons, and Loyalty point redemptions.
class CartRepository {
  final ApiClient _apiClient;

  CartRepository({ApiClient? apiClient}) : _apiClient = apiClient ?? ApiClient();

  /// Reads current cart state.
  Future<CartModel> getCart() async {
    final res = await _apiClient.get(AppConstants.epCart);
    return CartModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Adds food item with selected modifiers and instructions.
  Future<CartModel> addItem({
    required int productId,
    int quantity = 1,
    List<String> modifierIds = const [],
    String instructions = '',
  }) async {
    final res = await _apiClient.post(
      AppConstants.epCartItems,
      body: {
        'product_id': productId,
        'quantity': quantity,
        'modifier_ids': modifierIds,
        'instructions': instructions,
      },
    );
    return CartModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Updates quantity of a cart line.
  Future<CartModel> updateItem(String key, int quantity) async {
    final res = await _apiClient.put(
      '${AppConstants.epCartItems}/$key',
      body: {'quantity': quantity},
    );
    return CartModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Removes a line item from cart.
  Future<CartModel> removeItem(String key) async {
    final res = await _apiClient.delete('${AppConstants.epCartItems}/$key');
    return CartModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Empties the entire cart.
  Future<CartModel> clearCart() async {
    final res = await _apiClient.delete(AppConstants.epCart);
    return CartModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Validates and applies coupon code.
  Future<CartModel> applyCoupon(String code) async {
    final res = await _apiClient.post(
      AppConstants.epCartCoupon,
      body: {'code': code.trim()},
    );
    return CartModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Removes applied coupon.
  Future<CartModel> removeCoupon() async {
    final res = await _apiClient.delete(AppConstants.epCartCoupon);
    return CartModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Converts loyalty points to order discount.
  Future<CartModel> applyLoyalty(int points) async {
    final res = await _apiClient.post(
      AppConstants.epCartLoyalty,
      body: {'points': points},
      requiresAuth: true,
    );
    return CartModel.fromJson(res['data'] as Map<String, dynamic>);
  }
}
