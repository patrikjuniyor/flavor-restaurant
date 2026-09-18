import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../config/constants.dart';

/// Secure token manager and local persistence service.
class StorageService {
  final FlutterSecureStorage _secureStorage;
  SharedPreferences? _prefs;

  StorageService({FlutterSecureStorage? secureStorage})
      : _secureStorage = secureStorage ?? const FlutterSecureStorage();

  Future<void> init() async {
    _prefs ??= await SharedPreferences.getInstance();
  }

  // --- Secure Tokens (Encrypted Keystore / Keychain) ---

  Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _secureStorage.write(key: AppConstants.keyAccessToken, value: accessToken);
    await _secureStorage.write(key: AppConstants.keyRefreshToken, value: refreshToken);
  }

  Future<String?> getAccessToken() async {
    return await _secureStorage.read(key: AppConstants.keyAccessToken);
  }

  Future<String?> getRefreshToken() async {
    return await _secureStorage.read(key: AppConstants.keyRefreshToken);
  }

  Future<void> clearTokens() async {
    await _secureStorage.delete(key: AppConstants.keyAccessToken);
    await _secureStorage.delete(key: AppConstants.keyRefreshToken);
  }

  // --- Guest Cart Token ---

  Future<String> getOrCreateCartToken() async {
    await init();
    var token = _prefs?.getString(AppConstants.keyCartToken);
    if (token == null || token.isEmpty) {
      token = 'cart_${DateTime.now().millisecondsSinceEpoch}_${(1000 + (DateTime.now().microsecond % 9000))}';
      await _prefs?.setString(AppConstants.keyCartToken, token);
    }
    return token;
  }

  // --- Active Branch Preference ---

  Future<void> saveActiveBranchId(int branchId) async {
    await init();
    await _prefs?.setInt(AppConstants.keyActiveBranch, branchId);
  }

  Future<int?> getActiveBranchId() async {
    await init();
    return _prefs?.getInt(AppConstants.keyActiveBranch);
  }

  // --- User Profile JSON Cache ---

  Future<void> saveUserProfile(Map<String, dynamic> userJson) async {
    await init();
    await _prefs?.setString(AppConstants.keyUserProfile, jsonEncode(userJson));
  }

  Future<Map<String, dynamic>?> getUserProfile() async {
    await init();
    final str = _prefs?.getString(AppConstants.keyUserProfile);
    if (str != null && str.isNotEmpty) {
      try {
        return jsonDecode(str) as Map<String, dynamic>;
      } catch (_) {}
    }
    return null;
  }

  Future<void> clearUserProfile() async {
    await init();
    await _prefs?.remove(AppConstants.keyUserProfile);
  }

  // --- Theme Mode ---

  Future<void> saveThemeMode(String mode) async {
    await init();
    await _prefs?.setString(AppConstants.keyThemeMode, mode);
  }

  Future<String> getThemeMode() async {
    await init();
    return _prefs?.getString(AppConstants.keyThemeMode) ?? 'system';
  }

  // --- Favorites (Dish IDs) ---

  Future<Set<int>> getFavoriteDishIds() async {
    await init();
    final list = _prefs?.getStringList(AppConstants.keyFavorites) ?? [];
    return list.map((e) => int.tryParse(e) ?? 0).where((e) => e > 0).toSet();
  }

  Future<void> toggleFavorite(int dishId) async {
    await init();
    final favs = await getFavoriteDishIds();
    if (favs.contains(dishId)) {
      favs.remove(dishId);
    } else {
      favs.add(dishId);
    }
    await _prefs?.setStringList(
      AppConstants.keyFavorites,
      favs.map((e) => e.toString()).toList(),
    );
  }

  // --- Offline Bootstrap Cache ---

  Future<void> saveBootstrapCache(Map<String, dynamic> json) async {
    await init();
    await _prefs?.setString(AppConstants.keyBootstrapCache, jsonEncode(json));
  }

  Future<Map<String, dynamic>?> getBootstrapCache() async {
    await init();
    final str = _prefs?.getString(AppConstants.keyBootstrapCache);
    if (str != null && str.isNotEmpty) {
      try {
        return jsonDecode(str) as Map<String, dynamic>;
      } catch (_) {}
    }
    return null;
  }
}
