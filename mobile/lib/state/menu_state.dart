import 'package:flutter/material.dart';
import '../models/category_model.dart';
import '../models/dish_model.dart';
import '../repositories/menu_repository.dart';

/// State management for Categories, Menus, Dish collections, and Shifts.
class MenuProvider extends ChangeNotifier {
  final MenuRepository _menuRepo;

  List<CategoryModel> _categories = [];
  int _selectedCategoryId = 0;
  String _selectedShift = '';

  List<DishModel> _dishes = [];
  List<DishModel> _featuredDishes = [];
  List<DishModel> _specialOffers = [];

  bool _isLoading = false;
  String? _errorMessage;

  MenuProvider({MenuRepository? menuRepo}) : _menuRepo = menuRepo ?? MenuRepository();

  List<CategoryModel> get categories => _categories;
  int get selectedCategoryId => _selectedCategoryId;
  String get selectedShift => _selectedShift;
  List<DishModel> get dishes => _dishes;
  List<DishModel> get featuredDishes => _featuredDishes;
  List<DishModel> get specialOffers => _specialOffers;
  bool get isLoading => _isLoading;
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
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Selects a category and fetches filtered dishes.
  Future<void> selectCategory(int categoryId, int branchId) async {
    _selectedCategoryId = categoryId;
    _isLoading = true;
    notifyListeners();

    try {
      _dishes = await _menuRepo.getMenu(
        branchId: branchId,
        categoryId: categoryId,
        shift: _selectedShift,
      );
    } catch (e) {
      _errorMessage = e.toString();
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
}
