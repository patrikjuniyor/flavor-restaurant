import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import '../../config/app_config.dart';
import '../../config/constants.dart';
import '../storage/secure_storage_service.dart';
import 'api_exception.dart';

/// Production HTTP Client for Flavor REST API.
/// Injects Bearer tokens, guest cart headers, handles token rotation, error envelopes, and retries.
class ApiClient {
  final http.Client _httpClient;
  final StorageService _storage;
  bool _isRefreshing = false;

  ApiClient({
    http.Client? httpClient,
    StorageService? storage,
  })  : _httpClient = httpClient ?? http.Client(),
        _storage = storage ?? StorageService();

  String get _baseUrl => AppConfig.instance.apiBaseUrl;

  /// Performs GET request.
  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = false,
  }) async {
    return _send('GET', path, queryParameters: queryParameters, requiresAuth: requiresAuth);
  }

  /// Performs POST request.
  Future<Map<String, dynamic>> post(
    String path, {
    dynamic body,
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = false,
  }) async {
    return _send('POST', path, body: body, queryParameters: queryParameters, requiresAuth: requiresAuth);
  }

  /// Performs PUT request.
  Future<Map<String, dynamic>> put(
    String path, {
    dynamic body,
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = false,
  }) async {
    return _send('PUT', path, body: body, queryParameters: queryParameters, requiresAuth: requiresAuth);
  }

  /// Performs DELETE request.
  Future<Map<String, dynamic>> delete(
    String path, {
    dynamic body,
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = false,
  }) async {
    return _send('DELETE', path, body: body, queryParameters: queryParameters, requiresAuth: requiresAuth);
  }

  /// Internal request pipeline.
  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    dynamic body,
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = false,
    bool isRetryAfterRefresh = false,
  }) async {
    final cleanPath = path.startsWith('/') ? path : '/$path';
    var uri = Uri.parse('$_baseUrl$cleanPath');

    if (queryParameters != null && queryParameters.isNotEmpty) {
      final cleanParams = queryParameters.map((k, v) => MapEntry(k, v?.toString() ?? ''));
      uri = uri.replace(queryParameters: cleanParams);
    }

    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-App-Platform': Platform.isIOS ? 'ios' : 'android',
      'X-App-Version': '1.1.0',
    };

    // Inject Guest Cart Token
    final cartToken = await _storage.getOrCreateCartToken();
    headers['X-Cart-Token'] = cartToken;

    // Inject Auth Token if available
    final accessToken = await _storage.getAccessToken();
    if (accessToken != null && accessToken.isNotEmpty) {
      headers['Authorization'] = 'Bearer $accessToken';
    } else if (requiresAuth) {
      throw ApiException.unauthorized();
    }

    http.Response response;
    try {
      final request = http.Request(method, uri);
      request.headers.addAll(headers);

      if (body != null) {
        request.body = jsonEncode(body);
      }

      if (AppConfig.instance.enableLogging) {
        // ignore: avoid_print
        print('🌐 [API Request] $method $uri\nHeaders: $headers\nBody: ${request.body}');
      }

      final streamedResponse = await _httpClient.send(request).timeout(AppConfig.instance.apiTimeout);
      response = await http.Response.fromStream(streamedResponse);

      if (AppConfig.instance.enableLogging) {
        // ignore: avoid_print
        print('📥 [API Response] ${response.statusCode} $uri\nBody: ${response.body}');
      }
    } on SocketException {
      throw ApiException.networkError();
    } on TimeoutException {
      throw ApiException.timeout();
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException(message: 'خطا در برقراری ارتباط با سرور: $e');
    }

    // Handle 401 Unauthorized -> Token rotation and retry
    if (response.statusCode == 401 && !isRetryAfterRefresh && accessToken != null) {
      final refreshed = await _attemptTokenRefresh();
      if (refreshed) {
        return _send(
          method,
          path,
          body: body,
          queryParameters: queryParameters,
          requiresAuth: requiresAuth,
          isRetryAfterRefresh: true,
        );
      } else {
        await _storage.clearTokens();
        throw ApiException.unauthorized();
      }
    }

    return _processResponse(response);
  }

  /// Processes HTTP response and unpacks standard envelope.
  Map<String, dynamic> _processResponse(http.Response response) {
    Map<String, dynamic> json;
    try {
      json = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      if (response.statusCode >= 200 && response.statusCode < 300) {
        return {'success': true, 'data': null};
      }
      throw ApiException.serverError('پاسخ نامعتبر از سرور دریافت شد.');
    }

    final isSuccess = json['success'] == true;
    final statusCode = response.statusCode;

    if (isSuccess && statusCode >= 200 && statusCode < 300) {
      return json;
    }

    // Extract error details from envelope
    String errorMessage = 'خطای نامشخص در پردازش اطلاعات.';
    String? errorCode;
    dynamic errorDetails;

    if (json['errors'] is List && (json['errors'] as List).isNotEmpty) {
      final firstErr = (json['errors'] as List).first;
      if (firstErr is Map) {
        errorMessage = firstErr['message']?.toString() ?? errorMessage;
        errorCode = firstErr['code']?.toString();
        errorDetails = firstErr['details'];
      }
    } else if (json['message'] != null) {
      errorMessage = json['message'].toString();
    }

    switch (statusCode) {
      case 400:
        throw ApiException(message: errorMessage, code: errorCode ?? 'bad_request', statusCode: 400, details: errorDetails);
      case 401:
        throw ApiException.unauthorized(errorMessage);
      case 403:
        throw ApiException.forbidden(errorMessage);
      case 404:
        throw ApiException.notFound(errorMessage);
      case 409:
        throw ApiException(message: errorMessage, code: errorCode ?? 'conflict', statusCode: 409);
      case 429:
        throw ApiException.rateLimited(errorMessage);
      default:
        throw ApiException(message: errorMessage, code: errorCode ?? 'server_error', statusCode: statusCode);
    }
  }

  /// Automatically rotates token when access token expires.
  Future<bool> _attemptTokenRefresh() async {
    if (_isRefreshing) return false;
    _isRefreshing = true;

    try {
      final refreshToken = await _storage.getRefreshToken();
      if (refreshToken == null || refreshToken.isEmpty) {
        return false;
      }

      final uri = Uri.parse('$_baseUrl${AppConstants.epTokenRefresh}');
      final response = await _httpClient.post(
        uri,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'refresh_token': refreshToken}),
      );

      if (response.statusCode == 200) {
        final json = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
        if (json['success'] == true && json['data'] is Map) {
          final data = json['data'] as Map<String, dynamic>;
          final newAccess = data['access_token']?.toString() ?? '';
          final newRefresh = data['refresh_token']?.toString() ?? '';

          if (newAccess.isNotEmpty && newRefresh.isNotEmpty) {
            await _storage.saveTokens(accessToken: newAccess, refreshToken: newRefresh);
            return true;
          }
        }
      }
      return false;
    } catch (_) {
      return false;
    } finally {
      _isRefreshing = false;
    }
  }
}
