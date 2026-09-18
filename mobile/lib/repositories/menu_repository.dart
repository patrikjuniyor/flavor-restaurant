import '../../config/constants.dart';
import '../../core/api/api_client.dart';
import '../../core/storage/secure_storage_service.dart';
import '../../models/category_model.dart';
import '../../models/dish_model.dart';

/// Repository for Food Categories, Menu Catalog, Dish Customization, and Search.
class MenuRepository {
  final ApiClient _apiClient;
  final StorageService _storage;

  MenuRepository({
    ApiClient? apiClient,
    StorageService? storage,
  })  : _apiClient = apiClient ?? ApiClient(),
        _storage = storage ?? StorageService();

  /// Gets all food categories.
  Future<List<CategoryModel>> getCategories() async {
    final res = await _apiClient.get(AppConstants.epCategories);
    final list = res['data'] as List;
    return list.map((e) => CategoryModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Gets branch-specific menu with category and shift filters.
  Future<List<DishModel>> getMenu({
    int branchId = 0,
    int categoryId = 0,
    String? shift,
    String? search,
    int page = 1,
    int perPage = 30,
  }) async {
    final params = <String, dynamic>{
      'branch_id': branchId,
      'page': page,
      'per_page': perPage,
    };
    if (categoryId > 0) params['category'] = categoryId;
    if (shift != null && shift.isNotEmpty) params['shift'] = shift;
    if (search != null && search.isNotEmpty) params['search'] = search;

    final res = await _apiClient.get(AppConstants.epMenu, queryParameters: params);
    final list = res['data'] as List;
    return list.map((e) => DishModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Gets single dish details with full modifier groups.
  Future<DishModel> getDish(int id, {int branchId = 0}) async {
    final res = await _apiClient.get(
      '${AppConstants.epDishes}/$id',
      queryParameters: {'branch_id': branchId},
    );
    return DishModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Gets featured dishes for homepage carousel.
  Future<List<DishModel>> getFeatured({int branchId = 0, int limit = 6}) async {
    final res = await _apiClient.get(
      AppConstants.epFeatured,
      queryParameters: {'branch_id': branchId, 'limit': limit},
    );
    final list = res['data'] as List;
    return list.map((e) => DishModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Gets discounted dishes and special offers.
  Future<List<DishModel>> getSpecialOffers({int branchId = 0, int limit = 6}) async {
    final res = await _apiClient.get(
      AppConstants.epSpecialOffers,
      queryParameters: {'branch_id': branchId, 'limit': limit},
    );
    final list = res['data'] as List;
    return list.map((e) => DishModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Smart full-text search with dietary filters and price ranges.
  Future<List<DishModel>> search({
    required String query,
    int branchId = 0,
    int categoryId = 0,
    String? dietary,
    int? minPrice,
    int? maxPrice,
  }) async {
    final params = <String, dynamic>{
      'q': query,
      'branch_id': branchId,
    };
    if (categoryId > 0) params['category'] = categoryId;
    if (dietary != null && dietary.isNotEmpty) params['dietary'] = dietary;
    if (minPrice != null && minPrice > 0) params['min_price'] = minPrice;
    if (maxPrice != null && maxPrice > 0) params['max_price'] = maxPrice;

    final res = await _apiClient.get(AppConstants.epSearch, queryParameters: params);
    final list = res['data'] is Map ? (res['data']['items'] as List? ?? []) : (res['data'] as List? ?? []);
    return list.map((e) => DishModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Gets autocomplete instant search suggestions.
  Future<List<String>> getSuggestions(String query, {int branchId = 0}) async {
    if (query.trim().isEmpty) return [];
    try {
      final res = await _apiClient.get(
        AppConstants.epSearchSuggest,
        queryParameters: {'q': query, 'branch_id': branchId},
      );
      final data = res['data'] as Map<String, dynamic>;
      return (data['terms'] as List?)?.map((e) => e.toString()).toList() ?? [];
    } catch (_) {
      return [];
    }
  }

  /// Gets top trending / popular searches.
  Future<List<String>> getPopularSearches() async {
    try {
      final res = await _apiClient.get(AppConstants.epSearchPopular);
      final data = res['data'] as Map<String, dynamic>;
      return (data['terms'] as List?)?.map((e) => e.toString()).toList() ?? [];
    } catch (_) {
      return [];
    }
  }

  /// Local Favorites Management
  Future<Set<int>> getFavoriteIds() => _storage.getFavoriteDishIds();
  Future<void> toggleFavorite(int dishId) => _storage.toggleFavorite(dishId);
}
