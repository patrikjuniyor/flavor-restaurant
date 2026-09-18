import 'package:flutter/material.dart';
import '../models/cart_model.dart';
import '../models/dish_model.dart';
import '../repositories/cart_repository.dart';

/// State management for Shopping Cart, Items, Modifiers, Coupons, and Loyalty discounts.
class CartProvider extends ChangeNotifier {
  final CartRepository _cartRepo;

  CartModel _cart = const CartModel();
  bool _isLoading = false;
  String? _errorMessage;

  CartProvider({CartRepository? cartRepo}) : _cartRepo = cartRepo ?? CartRepository();

  CartModel get cart => _cart;
  int get itemCount => _cart.count;
  int get total => _cart.total;
  int get subtotal => _cart.subtotal;
  bool get isEmpty => _cart.isEmpty;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Loads cart from backend.
  Future<void> loadCart() async {
    _isLoading = true;
    notifyListeners();
    try {
      _cart = await _cartRepo.getCart();
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Adds a dish to cart with options.
  Future<bool> addToCart(
    DishModel dish, {
    int quantity = 1,
    List<String> modifierIds = const [],
    String instructions = '',
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _cart = await _cartRepo.addItem(
        productId: dish.id,
        quantity: quantity,
        modifierIds: modifierIds,
        instructions: instructions,
      );
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Updates quantity of an existing cart item.
  Future<void> updateQuantity(String key, int quantity) async {
    _isLoading = true;
    notifyListeners();
    try {
      if (quantity <= 0) {
        _cart = await _cartRepo.removeItem(key);
      } else {
        _cart = await _cartRepo.updateItem(key, quantity);
      }
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Removes item completely from cart.
  Future<void> removeItem(String key) async {
    _isLoading = true;
    notifyListeners();
    try {
      _cart = await _cartRepo.removeItem(key);
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Empties cart.
  Future<void> clearCart() async {
    _isLoading = true;
    notifyListeners();
    try {
      _cart = await _cartRepo.clearCart();
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Applies discount coupon.
  Future<bool> applyCoupon(String code) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();
    try {
      _cart = await _cartRepo.applyCoupon(code);
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Removes coupon.
  Future<void> removeCoupon() async {
    _isLoading = true;
    notifyListeners();
    try {
      _cart = await _cartRepo.removeCoupon();
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Redeems loyalty points.
  Future<bool> applyLoyalty(int points) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();
    try {
      _cart = await _cartRepo.applyLoyalty(points);
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
