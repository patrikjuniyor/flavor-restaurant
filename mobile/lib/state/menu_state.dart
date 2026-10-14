import 'package:flutter/material.dart';
import '../core/storage/offline_cache.dart';
import '../models/category_model.dart';
import '../models/dish_model.dart';
import '../repositories/menu_repository.dart';

/// State management for Categories, Menus, Dish collections, and Shifts.
///
/// Prompt 66 (offline mode): every successful load is persisted through
/// [OfflineCache]; a failed load restores the last good menu and flags
/// [isOfflineData] (screens render OfflineBanner) instead of stranding the
/// customer on an error view. The error view only appears when the network
/// failed AND there is nothing cached yet (first run offline).
class MenuProvider extends ChangeNotifier {
  final MenuRepository _menuRepo;
  final OfflineCache? _cache;

  List<CategoryModel> _categories = [];
  int _selectedCategoryId = 0;
  String _selectedShift = '';

  List<DishModel> _dishes = [];
  List<DishModel> _featuredDishes = [];
  List<DishModel> _specialOffers = [];

  bool _isLoading = false;
  bool _isOfflineData = false;
  String? _errorMessage;

  MenuProvider({MenuRepository? menuRepo, OfflineCache? cache})
      : _menuRepo = menuRepo ?? MenuRepository(),
        _cache = cache ?? OfflineCache();

  List<CategoryModel> get categories => _categories;
  int get selectedCategoryId => _selectedCategoryId;
  String get selectedShift => _selectedShift;
  List<DishModel> get dishes => _dishes;
  List<DishModel> get featuredDishes => _featuredDishes;
  List<DishModel> get specialOffers => _specialOffers;
  bool get isLoading => _isLoading;

  /// True while the visible menu came from the offline cache, not the network.
  bool get isOfflineData => _isOfflineData;
  String? get errorMessage => _errorMessage;

  /// Loads homepage and menu data for the active branch.
  Future<void> loadInitial(int branchId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final results = await Future.wait([
        _menuRepo.getCategories(),
        _menuRepo.getFeatured(branchId: branchId),
        _menuRepo.getSpecialOffers(branchId: branchId),
        _menuRepo.getMenu(branchId: branchId, categoryId: _selectedCategoryId),
      ]);

      _categories = results[0] as List<CategoryModel>;
      _featuredDishes = results[1] as List<DishModel>;
      _specialOffers = results[2] as List<DishModel>;
      _dishes = results[3] as List<DishModel>;
      _isOfflineData = false;
      await _persistCache(branchId);
    } catch (e) {
      // Offline fallback first, error view only when there is no cache.
      final restored = await _restoreCache();
      if (!restored) {
        _errorMessage = e.toString();
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Selects a category and fetches filtered dishes.
  Future<void> selectCategory(int categoryId, int branchId) async {
    _selectedCategoryId = categoryId;
    _isLoading = true;
    // Prompt 59: a retry (or the pull-to-refresh) must clear the stale
    // failure, or a successful reload would keep the screen on the error
    // view. loadInitial() already clears it; every reload path now does.
    _errorMessage = null;
    notifyListeners();

    try {
      _dishes = await _menuRepo.getMenu(
        branchId: branchId,
        categoryId: categoryId,
        shift: _selectedShift,
      );
      _isOfflineData = false;
    } catch (e) {
      // Never overwrite good live dishes with the (unfiltered) cache — only
      // fall back when the list on screen would otherwise be empty.
      if (_dishes.isEmpty) {
        final restored = await _restoreCache();
        if (!restored) {
          _errorMessage = e.toString();
        }
      } else {
        _errorMessage = e.toString();
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Filters by meal shift (breakfast, lunch, dinner, late_night).
  Future<void> selectShift(String shift, int branchId) async {
    _selectedShift = shift;
    await selectCategory(_selectedCategoryId, branchId);
  }

  /// Stores the current feed for the offline fallback. Best-effort by design:
  /// [OfflineCache.saveJson] never throws.
  Future<void> _persistCache(int branchId) async {
    final cache = _cache;
    if (cache == null) return;
    await cache.saveJson(OfflineCache.menuKey, {
      'branch_id': branchId,
      'fetched_at': DateTime.now().toIso8601String(),
      'categories': _categories.map((c) => c.toJson()).toList(),
      'featured': _featuredDishes.map((d) => d.toJson()).toList(),
      'offers': _specialOffers.map((d) => d.toJson()).toList(),
      'dishes': _dishes.map((d) => d.toJson()).toList(),
    });
  }

  /// Restores the last cached feed. Returns false when nothing usable is
  /// stored, so the caller keeps the network error on screen.
  Future<bool> _restoreCache() async {
    final cache = _cache;
    if (cache == null) return false;
    final blob = await cache.readJson(OfflineCache.menuKey);
    if (blob == null) return false;

    final categories = _decodeList(blob['categories'], CategoryModel.fromJson);
    final dishes = _decodeList(blob['dishes'], DishModel.fromJson);
    final featured = _decodeList(blob['featured'], DishModel.fromJson);
    final offers = _decodeList(blob['offers'], DishModel.fromJson);

    if (categories.isEmpty && dishes.isEmpty) return false;

    _categories = categories;
    _dishes = dishes;
    _featuredDishes = featured;
    _specialOffers = offers;
    _isOfflineData = true;
    _errorMessage = null;
    return true;
  }

  List<T> _decodeList<T>(dynamic raw, T Function(Map<String, dynamic>) parse) {
    if (raw is! List) return [];
    return raw
        .whereType<Map<String, dynamic>>()
        .map(parse)
        .toList(growable: false);
  }
}
