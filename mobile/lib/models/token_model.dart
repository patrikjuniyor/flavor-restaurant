import 'json_fields.dart';

/// Authentication Tokens model.
///
/// Mirrors `TokenService::issue()/refresh()` (flavor-core/includes/Customer/
/// TokenService.php): token_type, access_token, refresh_token, expires_in
/// plus the absolute expiry (`expires_at`, "Y-m-d H:i:s") and the owning
/// `user_id` — see test/fixtures/token_refresh.json.
class TokenModel {
  final String accessToken;
  final String refreshToken;
  final String tokenType;
  final int expiresIn;
  final String expiresAt;
  final int userId;

  const TokenModel({
    required this.accessToken,
    required this.refreshToken,
    this.tokenType = 'Bearer',
    this.expiresIn = 604800,
    this.expiresAt = '',
    this.userId = 0,
  });

  factory TokenModel.fromJson(Map<String, dynamic> json) {
    return TokenModel(
      accessToken: strField(json['access_token']),
      refreshToken: strField(json['refresh_token']),
      tokenType: strField(json['token_type'], 'Bearer'),
      expiresIn: intField(json['expires_in'], 604800),
      expiresAt: strField(json['expires_at']),
      userId: intField(json['user_id']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'access_token': accessToken,
      'refresh_token': refreshToken,
      'token_type': tokenType,
      'expires_in': expiresIn,
      'expires_at': expiresAt,
      'user_id': userId,
    };
  }
}
