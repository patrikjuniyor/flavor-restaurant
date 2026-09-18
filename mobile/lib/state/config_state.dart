import 'package:flutter/material.dart';
import '../core/storage/secure_storage_service.dart';
import '../models/app_bootstrap_model.dart';
import '../models/branch_model.dart';
import '../repositories/config_repository.dart';

/// State management for Restaurant White-label Configuration, Active Branch, and Theme.
class ConfigProvider extends ChangeNotifier {
  final ConfigRepository _configRepo;
  final StorageService _storage;

  AppBootstrapModel? _bootstrap;
  BranchModel? _activeBranch;
  ThemeMode _themeMode = ThemeMode.system;
  bool _isLoading = false;
  String? _errorMessage;

  ConfigProvider({
    ConfigRepository? configRepo,
    StorageService? storage,
  })  : _configRepo = configRepo ?? ConfigRepository(),
        _storage = storage ?? StorageService();

  AppBootstrapModel? get bootstrap => _bootstrap;
  BranchModel? get activeBranch => _activeBranch;
  ThemeMode get themeMode => _themeMode;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  AppBrandInfo get brand => _bootstrap?.app ?? const AppBrandInfo(name: 'رستوران طعم');
  AppDesignTokens get design => _bootstrap?.design ?? const AppDesignTokens();
  AppCurrencyConfig get currency => _bootstrap?.currency ?? const AppCurrencyConfig();
  List<BranchModel> get branches => _bootstrap?.branches ?? [];

  /// Loads bootstrap configuration on app startup.
  Future<void> loadConfig() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final savedTheme = await _storage.getThemeMode();
      _themeMode = savedTheme == 'dark'
          ? ThemeMode.dark
          : (savedTheme == 'light' ? ThemeMode.light : ThemeMode.system);

      _bootstrap = await _configRepo.getBootstrapConfig();

      // Resolve Active Branch
      final savedBranchId = await _storage.getActiveBranchId();
      if (_bootstrap!.branches.isNotEmpty) {
        if (savedBranchId != null && savedBranchId > 0) {
          _activeBranch = _bootstrap!.branches.firstWhere(
            (b) => b.id == savedBranchId,
            orElse: () => _bootstrap!.branches.first,
          );
        } else {
          _activeBranch = _bootstrap!.branches.firstWhere(
            (b) => b.isDefault,
            orElse: () => _bootstrap!.branches.first,
          );
        }
      }
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Changes the currently selected branch.
  Future<void> setActiveBranch(BranchModel branch) async {
    _activeBranch = branch;
    await _storage.saveActiveBranchId(branch.id);
    notifyListeners();
  }

  /// Toggles between Light, Dark, and System theme.
  Future<void> setThemeMode(ThemeMode mode) async {
    _themeMode = mode;
    final modeStr = mode == ThemeMode.dark ? 'dark' : (mode == ThemeMode.light ? 'light' : 'system');
    await _storage.saveThemeMode(modeStr);
    notifyListeners();
  }
}
