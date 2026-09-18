import '../../config/constants.dart';
import '../../core/api/api_client.dart';
import '../../models/review_model.dart';

/// Repository for Customer Reviews and Testimonials.
class ReviewRepository {
  final ApiClient _apiClient;

  ReviewRepository({ApiClient? apiClient}) : _apiClient = apiClient ?? ApiClient();

  /// Gets customer reviews for a specific food dish.
  Future<List<ReviewModel>> getDishReviews(int dishId, {int page = 1, int perPage = 10}) async {
    final res = await _apiClient.get(
      '${AppConstants.epDishes}/$dishId/reviews',
      queryParameters: {'page': page, 'per_page': perPage},
    );
    final list = res['data'] as List;
    return list.map((e) => ReviewModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Submits a customer review for a dish.
  Future<Map<String, dynamic>> submitReview(
    int dishId, {
    required int rating,
    required String comment,
    String? name,
  }) async {
    final body = <String, dynamic>{
      'rating': rating,
      'comment': comment,
    };
    if (name != null && name.isNotEmpty) body['name'] = name;

    final res = await _apiClient.post(
      '${AppConstants.epDishes}/$dishId/reviews',
      body: body,
    );
    return res['data'] as Map<String, dynamic>;
  }

  /// Gets recent positive testimonials for homepage.
  Future<List<ReviewModel>> getRecentTestimonials({int limit = 6}) async {
    final res = await _apiClient.get(
      AppConstants.epReviewsRecent,
      queryParameters: {'limit': limit},
    );
    final list = res['data'] as List;
    return list.map((e) => ReviewModel.fromJson(e as Map<String, dynamic>)).toList();
  }
}
