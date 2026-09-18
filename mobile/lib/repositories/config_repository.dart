import '../config/constants.dart';
import '../core/api/api_client.dart';
import '../core/storage/secure_storage_service.dart';
import '../models/app_bootstrap_model.dart';

/// Repository for Restaurant White-label Configuration and Branding tokens.
class ConfigRepository {
  final ApiClient _apiClient;
  final StorageService _storage;

  ConfigRepository({
    ApiClient? apiClient,
    StorageService? storage,
  })  : _apiClient = apiClient ?? ApiClient(),
        _storage = storage ?? StorageService();

  /// Fetches restaurant configuration from backend with offline fallback.
  Future<AppBootstrapModel> getBootstrapConfig() async {
    try {
      final res = await _apiClient.get(AppConstants.epBootstrap);
      final data = res['data'] as Map<String, dynamic>;
      await _storage.saveBootstrapCache(data);
      return AppBootstrapModel.fromJson(data);
    } catch (e) {
      // Offline fallback: try reading from cache
      final cached = await _storage.getBootstrapCache();
      if (cached != null) {
        return AppBootstrapModel.fromJson(cached);
      }
      rethrow;
    }
  }
}
