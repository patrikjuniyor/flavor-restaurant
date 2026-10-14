import 'dart:convert';

import 'package:crypto/crypto.dart';

/// Prompt 67: client-side idempotency key for checkout submissions.
///
/// `CheckoutService::place()` dedupes orders by an `idempotency_key` field in
/// the request body (server contract: `[A-Za-z0-9_-]`, max 64 chars). The key
/// is DERIVED from the submission payload, never random, so:
///  - retrying the SAME submission (double tap, network timeout, app restart)
///    carries the same key and the server replays the original order instead
///    of minting a duplicate;
///  - editing anything that makes it a different order (cart, table, address,
///    payment method, …) rotates the key, which is exactly what a genuinely
///    new order needs.
///
/// sha256 hex is 64 chars of `[a-z0-9]` — inside the server sanitizer's
/// allowed charset and length, so the key survives the round trip byte-exact.
String checkoutIdempotencyKey({
  required String cartSignature,
  required String orderMode,
  required int branchId,
  int? tableId,
  String? tableNumber,
  Map<String, dynamic>? address,
  required String paymentMethod,
  String? notes,
  String? mobile,
}) {
  // jsonEncode preserves literal insertion order, so the canonical string is
  // stable for identical inputs across runs and isolates.
  final canonical = jsonEncode({
    'cart': cartSignature,
    'mode': orderMode,
    'branch': branchId,
    'table_id': tableId ?? 0,
    'table': (tableNumber ?? '').trim(),
    'address': address ?? const <String, dynamic>{},
    'payment': paymentMethod,
    'notes': (notes ?? '').trim(),
    'mobile': (mobile ?? '').trim(),
  });
  return sha256.convert(utf8.encode(canonical)).toString();
}
