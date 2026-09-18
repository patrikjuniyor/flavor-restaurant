import 'package:flutter/material.dart';
import '../../config/routes.dart';

/// Represents a parsed Deep Link destination with authentication requirements.
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
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is DeepLinkDestination &&
          runtimeType == other.runtimeType &&
          route == other.route &&
          args.toString() == other.args.toString() &&
          requiresAuth == other.requiresAuth;

  @override
  int get hashCode => Object.hash(route, args, requiresAuth);

  @override
  String toString() => 'DeepLinkDestination(route: $route, args: $args, requiresAuth: $requiresAuth)';
}

/// Central Deep Link resolver and runtime navigation dispatcher.
/// Handles custom schemes (flavor://) and Universal/App Links (https://*).
/// Preserves unauthenticated deep link destinations and redirects upon login.
class DeepLinkService {
  static final DeepLinkService instance = DeepLinkService._internal();

  DeepLinkService._internal();

  factory DeepLinkService() => instance;

  /// Global navigator key for programmatic deep link routing.
  final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

  /// Preserved destination when user hits an auth-guarded deep link while logged out.
  DeepLinkDestination? _pendingDestination;

  DeepLinkDestination? get pendingDestination => _pendingDestination;

  /// Store unauthenticated pending destination.
  void setPendingDestination(DeepLinkDestination destination) {
    _pendingDestination = destination;
  }

  /// Consumes and clears pending destination.
  DeepLinkDestination? consumePendingDestination() {
    final dest = _pendingDestination;
    _pendingDestination = null;
    return dest;
  }

  /// Clear pending destination.
  void clearPendingDestination() {
    _pendingDestination = null;
  }

  /// Parses external deep link URI into a typed [DeepLinkDestination].
  DeepLinkDestination? parseUri(String rawUrl) {
    final trimmed = rawUrl.trim();
    if (trimmed.isEmpty) return null;

    try {
      final uri = Uri.parse(trimmed);
      final scheme = uri.scheme.toLowerCase();
      final host = uri.host.toLowerCase();
      final pathSegments = uri.pathSegments;

      // 1. Custom Scheme: flavor://<host>/<path>
      if (scheme == 'flavor') {
        // e.g. flavor://dish/123 or flavor://order/456?guest_token=xyz
        final pathHead = host.isNotEmpty ? host : (pathSegments.isNotEmpty ? pathSegments.first : '');
        final query = uri.queryParameters;

        switch (pathHead) {
          case 'menu':
            final catId = int.tryParse(query['category'] ?? (pathSegments.length > 1 ? pathSegments[1] : '')) ?? 0;
            return DeepLinkDestination(route: AppRoutes.menu, args: catId > 0 ? catId : null);

          case 'dish':
          case 'product':
            final idStr = pathSegments.isNotEmpty ? pathSegments.first : (query['id'] ?? '');
            final dishId = int.tryParse(idStr) ?? 0;
            return DeepLinkDestination(route: AppRoutes.dishDetail, args: dishId);

          case 'order':
            final idStr = pathSegments.isNotEmpty ? pathSegments.first : (query['id'] ?? '');
            final orderId = int.tryParse(idStr) ?? 0;
            final guestToken = query['guest_token'] ?? '';
            return DeepLinkDestination(
              route: AppRoutes.orderTracking,
              args: {'order_id': orderId, 'guest_token': guestToken.isNotEmpty ? guestToken : null},
            );

          case 'orders':
            return const DeepLinkDestination(route: AppRoutes.orders, requiresAuth: true);

          case 'reservation':
            return const DeepLinkDestination(route: AppRoutes.reservation);

          case 'reservation-history':
            return const DeepLinkDestination(route: AppRoutes.reservationHistory, requiresAuth: true);

          case 'cart':
            return const DeepLinkDestination(route: AppRoutes.cart);

          case 'checkout':
            return const DeepLinkDestination(route: AppRoutes.checkout);

          case 'profile':
            return const DeepLinkDestination(route: AppRoutes.profile, requiresAuth: true);

          case 'profile-edit':
            return const DeepLinkDestination(route: AppRoutes.editProfile, requiresAuth: true);

          case 'addresses':
            return const DeepLinkDestination(route: AppRoutes.addresses, requiresAuth: true);

          case 'favorites':
            return const DeepLinkDestination(route: AppRoutes.favorites);

          case 'restaurant-info':
            return const DeepLinkDestination(route: AppRoutes.restaurantInfo);

          case 'notifications':
            return const DeepLinkDestination(route: AppRoutes.notifications);

          case 'table':
          case 'qr':
            final tableNum = query['table_number'] ?? query['table'] ?? '';
            final branchId = int.tryParse(query['branch_id'] ?? '') ?? 0;
            return DeepLinkDestination(
              route: AppRoutes.menu,
              args: {'table_number': tableNum, 'branch_id': branchId},
            );
        }
      }

      // 2. HTTPS / HTTP Universal Links
      if (scheme == 'http' || scheme == 'https') {
        final query = uri.queryParameters;
        if (pathSegments.isEmpty) {
          return const DeepLinkDestination(route: AppRoutes.home);
        }

        final first = pathSegments.first;
        if (first == 'product' || first == 'dish') {
          final id = int.tryParse(pathSegments.length > 1 ? pathSegments[1] : (query['id'] ?? '')) ?? 0;
          return DeepLinkDestination(route: AppRoutes.dishDetail, args: id);
        }

        if (first == 'order') {
          final id = int.tryParse(pathSegments.length > 1 ? pathSegments[1] : (query['id'] ?? '')) ?? 0;
          final guestToken = query['guest_token'] ?? '';
          return DeepLinkDestination(
            route: AppRoutes.orderTracking,
            args: {'order_id': id, 'guest_token': guestToken.isNotEmpty ? guestToken : null},
          );
        }

        if (first == 'menu') {
          final catId = int.tryParse(query['category'] ?? (pathSegments.length > 1 ? pathSegments[1] : '')) ?? 0;
          return DeepLinkDestination(route: AppRoutes.menu, args: catId > 0 ? catId : null);
        }

        if (first == 'reservation') {
          return const DeepLinkDestination(route: AppRoutes.reservation);
        }

        if (first == 'reservations' || first == 'my-reservations') {
          return const DeepLinkDestination(route: AppRoutes.reservationHistory, requiresAuth: true);
        }

        if (first == 'cart') {
          return const DeepLinkDestination(route: AppRoutes.cart);
        }

        if (first == 'profile' || first == 'my-account') {
          return const DeepLinkDestination(route: AppRoutes.profile, requiresAuth: true);
        }
      }
    } catch (_) {}

    return null;
  }

  /// Dispatches deep link navigation safely with Auth Guard support.
  bool dispatch(String rawUrl, {required bool isAuthenticated}) {
    final destination = parseUri(rawUrl);
    if (destination == null) return false;

    final nav = navigatorKey.currentState;
    if (nav == null) return false;

    if (destination.requiresAuth && !isAuthenticated) {
      setPendingDestination(destination);
      nav.pushNamed(AppRoutes.login);
      return true;
    }

    nav.pushNamed(destination.route, arguments: destination.args);
    return true;
  }

  /// Called after successful login to navigate to preserved destination if any.
  bool resumePendingDestination() {
    final destination = consumePendingDestination();
    if (destination == null) return false;

    final nav = navigatorKey.currentState;
    if (nav == null) return false;

    nav.pushNamed(destination.route, arguments: destination.args);
    return true;
  }
}
