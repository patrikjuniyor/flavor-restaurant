import '../../config/constants.dart';
import '../../core/api/api_client.dart';
import '../../core/storage/secure_storage_service.dart';
import '../../core/utils/persian_number.dart';
import '../../models/token_model.dart';
import '../../models/user_model.dart';

/// Repository for OTP Authentication, Tokens, and User Profile management.
class AuthRepository {
  final ApiClient _apiClient;
  final StorageService _storage;

  AuthRepository({
    ApiClient? apiClient,
    StorageService? storage,
  })  : _apiClient = apiClient ?? ApiClient(),
        _storage = storage ?? StorageService();

  /// Requests SMS OTP Code.
  Future<Map<String, dynamic>> requestOtp(String mobile) async {
    final clean = PersianNumber.cleanMobile(mobile);
    final res = await _apiClient.post(
      AppConstants.epOtpRequest,
      body: {'mobile': clean},
    );
    return res['data'] as Map<String, dynamic>;
  }

  /// Verifies OTP code, saves JWT tokens, and returns User Profile.
  Future<UserModel> verifyOtp(
    String mobile,
    String code, {
    String? name,
    String? deviceName,
  }) async {
    final cleanMobile = PersianNumber.cleanMobile(mobile);
    final cleanCode = PersianNumber.toLatin(code);

    final res = await _apiClient.post(
      AppConstants.epOtpVerify,
      body: {
        'mobile': cleanMobile,
        'code': cleanCode,
        'name': name ?? '',
        'device_name': deviceName ?? 'Flutter Mobile App',
      },
    );

    final data = res['data'] as Map<String, dynamic>;
    final tokens = TokenModel.fromJson(data['tokens'] as Map<String, dynamic>);
    final user = UserModel.fromJson(data['user'] as Map<String, dynamic>);

    // Persist securely
    await _storage.saveTokens(
      accessToken: tokens.accessToken,
      refreshToken: tokens.refreshToken,
    );
    await _storage.saveUserProfile(user.toJson());

    return user;
  }

  /// Fetches current user profile from server or local cache.
  Future<UserModel?> getProfile({bool forceRefresh = false}) async {
    final accessToken = await _storage.getAccessToken();
    if (accessToken == null || accessToken.isEmpty) {
      return null;
    }

    if (!forceRefresh) {
      final cached = await _storage.getUserProfile();
      if (cached != null) {
        return UserModel.fromJson(cached);
      }
    }

    try {
      final res = await _apiClient.get(AppConstants.epMe, requiresAuth: true);
      final data = res['data'] as Map<String, dynamic>;
      if (data['logged_in'] == true && data['user'] is Map) {
        final user = UserModel.fromJson(data['user'] as Map<String, dynamic>);
        await _storage.saveUserProfile(user.toJson());
        return user;
      }
      return null;
    } catch (_) {
      final cached = await _storage.getUserProfile();
      if (cached != null) {
        return UserModel.fromJson(cached);
      }
      return null;
    }
  }

  /// Updates user profile details.
  Future<UserModel> updateProfile({
    String? displayName,
    String? email,
    List<String>? dietaryPreferences,
    List<SavedAddress>? savedAddresses,
  }) async {
    final body = <String, dynamic>{};
    if (displayName != null) body['display_name'] = displayName;
    if (email != null) body['email'] = email;
    if (dietaryPreferences != null) body['dietary_preferences'] = dietaryPreferences;
    if (savedAddresses != null) {
      body['saved_addresses'] = savedAddresses.map((e) => e.toJson()).toList();
    }

    final res = await _apiClient.put(
      AppConstants.epMe,
      body: body,
      requiresAuth: true,
    );

    final data = res['data'] as Map<String, dynamic>;
    final updated = UserModel.fromJson(data['user'] as Map<String, dynamic>);
    await _storage.saveUserProfile(updated.toJson());
    return updated;
  }

  /// Logs out user and revokes server tokens.
  Future<void> logout() async {
    try {
      await _apiClient.post(AppConstants.epTokenRevoke, requiresAuth: true);
    } catch (_) {}
    await _storage.clearTokens();
    await _storage.clearUserProfile();
  }

  /// Checks if user has valid stored access token.
  Future<bool> isAuthenticated() async {
    final token = await _storage.getAccessToken();
    return token != null && token.isNotEmpty;
  }
}
