import 'dart:async';
import 'package:flutter/material.dart';
import '../core/navigation/deep_link_service.dart';
import '../models/user_model.dart';
import '../repositories/auth_repository.dart';

/// State management for Authentication, OTP countdown timers, and User Profile.
class AuthProvider extends ChangeNotifier {
  final AuthRepository _authRepo;

  UserModel? _user;
  bool _isLoading = false;
  String? _errorMessage;

  // OTP Countdown Timer
  Timer? _timer;
  int _countdown = 0;

  AuthProvider({AuthRepository? authRepo}) : _authRepo = authRepo ?? AuthRepository();

  UserModel? get user => _user;
  bool get isAuthenticated => _user != null;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  int get countdown => _countdown;
  bool get canResendOtp => _countdown <= 0;

  /// Checks auth status on app start.
  Future<void> checkAuth() async {
    _isLoading = true;
    notifyListeners();
    try {
      _user = await _authRepo.getProfile();
    } catch (_) {
      _user = null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Sends OTP SMS code.
  Future<bool> requestOtp(String mobile) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await _authRepo.requestOtp(mobile);
      final ttlMinutes = int.tryParse(res['ttl']?.toString() ?? '2') ?? 2;
      startCountdown(ttlMinutes * 60);
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Verifies OTP code and authenticates.
  Future<bool> verifyOtp(String mobile, String code, {String? name}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _user = await _authRepo.verifyOtp(mobile, code, name: name);
      _timer?.cancel();
      _countdown = 0;

      // Resume pending deep link destination if unauthenticated redirection occurred
      DeepLinkService.instance.resumePendingDestination();

      return true;
    } catch (e) {
      _errorMessage = e.toString();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Starts OTP countdown timer.
  void startCountdown(int seconds) {
    _timer?.cancel();
    _countdown = seconds;
    notifyListeners();

    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_countdown > 0) {
        _countdown--;
        notifyListeners();
      } else {
        _timer?.cancel();
      }
    });
  }

  /// Updates profile details.
  Future<bool> updateProfile({
    String? displayName,
    String? email,
    List<String>? dietaryPreferences,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _user = await _authRepo.updateProfile(
        displayName: displayName,
        email: email,
        dietaryPreferences: dietaryPreferences,
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

  /// Adds or edits a saved address.
  Future<bool> saveAddress(SavedAddress address) async {
    if (_user == null) return false;
    final list = List<SavedAddress>.from(_user!.savedAddresses);
    final index = list.indexWhere((a) => a.id == address.id);
    if (index >= 0) {
      list[index] = address;
    } else {
      list.add(address);
    }

    try {
      _user = await _authRepo.updateProfile(savedAddresses: list);
      notifyListeners();
      return true;
    } catch (_) {
      return false;
    }
  }

  /// Deletes a saved address.
  Future<bool> deleteAddress(String id) async {
    if (_user == null) return false;
    final list = _user!.savedAddresses.where((a) => a.id != id).toList();
    try {
      _user = await _authRepo.updateProfile(savedAddresses: list);
      notifyListeners();
      return true;
    } catch (_) {
      return false;
    }
  }

  /// Logs out user.
  Future<void> logout() async {
    await _authRepo.logout();
    _user = null;
    _timer?.cancel();
    notifyListeners();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}
