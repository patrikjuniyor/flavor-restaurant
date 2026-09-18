// Pure Dart verification runner without external flutter_test dependency.

import 'dart:convert';

class DeepLinkDestination {
  final String route;
  final dynamic args;
  final bool requiresAuth;

  const DeepLinkDestination({
    required this.route,
    this.args,
    this.requiresAuth = false,
  });

  @override
  String toString() => 'DeepLinkDestination(route: $route, args: $args, requiresAuth: $requiresAuth)';
}

class DeepLinkParser {
  static DeepLinkDestination? parseUri(String rawUrl) {
    final trimmed = rawUrl.trim();
    if (trimmed.isEmpty) return null;

    try {
      final uri = Uri.parse(trimmed);
      final scheme = uri.scheme.toLowerCase();
      final host = uri.host.toLowerCase();
      final pathSegments = uri.pathSegments;

      if (scheme == 'flavor') {
        final pathHead = host.isNotEmpty ? host : (pathSegments.isNotEmpty ? pathSegments.first : '');
        final query = uri.queryParameters;

        switch (pathHead) {
          case 'menu':
            final catId = int.tryParse(query['category'] ?? (pathSegments.length > 1 ? pathSegments[1] : '')) ?? 0;
            return DeepLinkDestination(route: '/menu', args: catId > 0 ? catId : null);

          case 'dish':
          case 'product':
            final idStr = pathSegments.isNotEmpty ? pathSegments.first : (query['id'] ?? '');
            final dishId = int.tryParse(idStr) ?? 0;
            return DeepLinkDestination(route: '/dish-detail', args: dishId);

          case 'order':
            final idStr = pathSegments.isNotEmpty ? pathSegments.first : (query['id'] ?? '');
            final orderId = int.tryParse(idStr) ?? 0;
            final guestToken = query['guest_token'] ?? '';
            return DeepLinkDestination(
              route: '/order-tracking',
              args: {'order_id': orderId, 'guest_token': guestToken.isNotEmpty ? guestToken : null},
            );

          case 'orders':
            return const DeepLinkDestination(route: '/orders', requiresAuth: true);

          case 'reservation':
            return const DeepLinkDestination(route: '/reservation');

          case 'reservation-history':
            return const DeepLinkDestination(route: '/reservation-history', requiresAuth: true);

          case 'cart':
            return const DeepLinkDestination(route: '/cart');

          case 'profile':
            return const DeepLinkDestination(route: '/profile', requiresAuth: true);

          case 'table':
          case 'qr':
            final tableNum = query['table_number'] ?? query['table'] ?? '';
            final branchId = int.tryParse(query['branch_id'] ?? '') ?? 0;
            return DeepLinkDestination(
              route: '/menu',
              args: {'table_number': tableNum, 'branch_id': branchId},
            );
        }
      }

      if (scheme == 'http' || scheme == 'https') {
        final query = uri.queryParameters;
        if (pathSegments.isEmpty) {
          return const DeepLinkDestination(route: '/home');
        }

        final first = pathSegments.first;
        if (first == 'product' || first == 'dish') {
          final id = int.tryParse(pathSegments.length > 1 ? pathSegments[1] : (query['id'] ?? '')) ?? 0;
          return DeepLinkDestination(route: '/dish-detail', args: id);
        }

        if (first == 'order') {
          final id = int.tryParse(pathSegments.length > 1 ? pathSegments[1] : (query['id'] ?? '')) ?? 0;
          final guestToken = query['guest_token'] ?? '';
          return DeepLinkDestination(
            route: '/order-tracking',
            args: {'order_id': id, 'guest_token': guestToken.isNotEmpty ? guestToken : null},
          );
        }

        if (first == 'profile' || first == 'my-account') {
          return const DeepLinkDestination(route: '/profile', requiresAuth: true);
        }
      }
    } catch (_) {}

    return null;
  }
}

void main() {
  int passed = 0;
  int failed = 0;

  void assertTest(bool condition, String message) {
    if (condition) {
      passed++;
      print('  [PASS] $message');
    } else {
      failed++;
      print('  [FAIL] $message');
    }
  }

  print('=== Running Pure Dart Verification ===\n');

  // 1. Deep Link Custom Scheme
  print('--- 1. Deep Link Parsing ---');
  final d1 = DeepLinkParser.parseUri('flavor://menu?category=5');
  assertTest(d1 != null && d1.route == '/menu' && d1.args == 5, 'flavor://menu?category=5 -> /menu with cat 5');

  final d2 = DeepLinkParser.parseUri('flavor://dish/42');
  assertTest(d2 != null && d2.route == '/dish-detail' && d2.args == 42, 'flavor://dish/42 -> /dish-detail with id 42');

  final d3 = DeepLinkParser.parseUri('flavor://order/999?guest_token=guest_sec_abc');
  assertTest(d3 != null && d3.route == '/order-tracking' && (d3.args as Map)['order_id'] == 999 && (d3.args as Map)['guest_token'] == 'guest_sec_abc', 'flavor://order/999?guest_token=... -> /order-tracking with guest_token');

  final d4 = DeepLinkParser.parseUri('flavor://profile');
  assertTest(d4 != null && d4.route == '/profile' && d4.requiresAuth == true, 'flavor://profile is auth-guarded');

  final d5 = DeepLinkParser.parseUri('https://restaurant.example.com/product/188');
  assertTest(d5 != null && d5.route == '/dish-detail' && d5.args == 188, 'HTTPS Universal link https://.../product/188 parsed');

  final d6 = DeepLinkParser.parseUri('flavor://table?branch_id=3&table_number=12');
  assertTest(d6 != null && (d6.args as Map)['branch_id'] == 3 && (d6.args as Map)['table_number'] == '12', 'Table QR link parsed');

  print('\n=======================================');
  print('Dart Verification: $passed Passed, $failed Failed');
  print('=======================================');

  if (failed > 0) {
    throw Exception('Verification failed');
  }
}
