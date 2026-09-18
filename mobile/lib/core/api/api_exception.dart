/// Custom API exceptions covering HTTP errors, authentication expiry, rate limits, and network connectivity.
class ApiException implements Exception {
  final String message;
  final String? code;
  final int? statusCode;
  final dynamic details;

  ApiException({
    required this.message,
    this.code,
    this.statusCode,
    this.details,
  });

  factory ApiException.networkError() {
    return ApiException(
      message: 'عدم دسترسی به اینترنت. لطفاً اتصال شبکه خود را بررسی کنید.',
      code: 'network_error',
      statusCode: 0,
    );
  }

  factory ApiException.timeout() {
    return ApiException(
      message: 'زمان پاسخ‌دهی سرور به پایان رسید. لطفاً مجدداً تلاش کنید.',
      code: 'timeout',
      statusCode: 408,
    );
  }

  factory ApiException.unauthorized([String? msg]) {
    return ApiException(
      message: msg ?? 'نشست کاربری شما منقضی شده است. لطفاً مجدداً وارد شوید.',
      code: 'unauthorized',
      statusCode: 401,
    );
  }

  factory ApiException.forbidden([String? msg]) {
    return ApiException(
      message: msg ?? 'شما دسترسی لازم برای این عملیات را ندارید.',
      code: 'forbidden',
      statusCode: 403,
    );
  }

  factory ApiException.notFound([String? msg]) {
    return ApiException(
      message: msg ?? 'مورد درخواستی در سرور پیدا نشد.',
      code: 'not_found',
      statusCode: 404,
    );
  }

  factory ApiException.rateLimited([String? msg]) {
    return ApiException(
      message: msg ?? 'تعداد درخواست‌ها بیش از حد مجاز است. چند دقیقه بعد تلاش کنید.',
      code: 'rate_limited',
      statusCode: 429,
    );
  }

  factory ApiException.serverError([String? msg]) {
    return ApiException(
      message: msg ?? 'خطایی در سرور رخ داده است. لطفاً بعداً تلاش کنید.',
      code: 'server_error',
      statusCode: 500,
    );
  }

  @override
  String toString() => message;
}
