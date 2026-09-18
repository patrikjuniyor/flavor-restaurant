/// Environment Flavor definitions and dynamic restaurant tenant configuration.
enum FlavorEnvironment {
  dev,
  staging,
  prod,
}

class AppConfig {
  final FlavorEnvironment environment;
  final String appName;
  final String apiBaseUrl;
  final String tenantId;
  final int defaultBranchId;
  final bool enableLogging;
  final Duration apiTimeout;

  static AppConfig? _instance;

  AppConfig._({
    required this.environment,
    required this.appName,
    required this.apiBaseUrl,
    required this.tenantId,
    required this.defaultBranchId,
    required this.enableLogging,
    required this.apiTimeout,
  });

  static AppConfig get instance {
    _instance ??= AppConfig.dev();
    return _instance!;
  }

  static void initialize({
    required FlavorEnvironment environment,
    required String appName,
    required String apiBaseUrl,
    String tenantId = 'default',
    int defaultBranchId = 0,
    bool enableLogging = false,
    Duration apiTimeout = const Duration(seconds: 25),
  }) {
    _instance = AppConfig._(
      environment: environment,
      appName: appName,
      apiBaseUrl: apiBaseUrl,
      tenantId: tenantId,
      defaultBranchId: defaultBranchId,
      enableLogging: enableLogging,
      apiTimeout: apiTimeout,
    );
  }

  factory AppConfig.dev() {
    return AppConfig._(
      environment: FlavorEnvironment.dev,
      appName: 'Flavor Dev',
      apiBaseUrl: 'http://localhost/wp-json/flavor/v1',
      tenantId: 'dev_tenant',
      defaultBranchId: 0,
      enableLogging: true,
      apiTimeout: const Duration(seconds: 30),
    );
  }

  factory AppConfig.staging() {
    return AppConfig._(
      environment: FlavorEnvironment.staging,
      appName: 'Flavor Staging',
      apiBaseUrl: 'https://staging.restaurant.com/wp-json/flavor/v1',
      tenantId: 'staging_tenant',
      defaultBranchId: 0,
      enableLogging: true,
      apiTimeout: const Duration(seconds: 25),
    );
  }

  factory AppConfig.prod({
    required String appName,
    required String apiBaseUrl,
    String tenantId = 'prod_tenant',
  }) {
    return AppConfig._(
      environment: FlavorEnvironment.prod,
      appName: appName,
      apiBaseUrl: apiBaseUrl,
      tenantId: tenantId,
      defaultBranchId: 0,
      enableLogging: false,
      apiTimeout: const Duration(seconds: 20),
    );
  }

  bool get isDev => environment == FlavorEnvironment.dev;
  bool get isProd => environment == FlavorEnvironment.prod;
}
