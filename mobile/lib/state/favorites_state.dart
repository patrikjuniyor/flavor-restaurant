import 'package:flutter/material.dart';
import '../core/storage/secure_storage_service.dart';
import '../models/dish_model.dart';

/// State management for Customer Favorite Dishes.
class FavoritesProvider extends ChangeNotifier {
  final StorageService _storage;
  Set<int> _favoriteIds = {};

  FavoritesProvider({StorageService? storage}) : _storage = storage ?? StorageService();

  Set<int> get favoriteIds => _favoriteIds;

  Future<void> loadFavorites() async {
    _favoriteIds = await _storage.getFavoriteDishIds();
    notifyListeners();
  }

  bool isFavorite(int dishId) => _favoriteIds.contains(dishId);

  Future<void> toggleFavorite(DishModel dish) async {
    await _storage.toggleFavorite(dish.id);
    if (_favoriteIds.contains(dish.id)) {
      _favoriteIds.remove(dish.id);
    } else {
      _favoriteIds.add(dish.id);
    }
    notifyListeners();
  }
}
