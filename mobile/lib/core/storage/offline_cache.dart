import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Minimal persistent JSON cache backing the offline fallback (prompt 66).
///
/// Read-only GET payloads (the menu feed above all) are stored here on every
/// successful load. When the network is down, providers restore the last good
/// payload instead of stranding the customer on a dead error screen — the
/// offline menu still shows what was last on offer, flagged by OfflineBanner.
///
/// Deliberately small: one string blob per key in shared_preferences. A
/// missing or corrupt entry degrades to `null` so callers fall back to the
/// error path, and writes never throw into a live load path.
class OfflineCache {
  /// Menu feed blob shape:
  /// `{branch_id, fetched_at, categories, featured, offers, dishes}`.
  static const String menuKey = 'cache.menu.v1';

  final Future<SharedPreferences> _prefs;

  /// [prefs] is injectable for tests; production uses the shared instance.
  OfflineCache({Future<SharedPreferences>? prefs})
      : _prefs = prefs ?? SharedPreferences.getInstance();

  /// Persists a JSON-serialisable map. Failures are swallowed on purpose:
  /// a cache write must never fail an otherwise successful request.
  Future<void> saveJson(String key, Map<String, dynamic> value) async {
    try {
      final prefs = await _prefs;
      await prefs.setString(key, jsonEncode(value));
    } catch (_) {
      // Best-effort cache: storage failures must not break the live path.
    }
  }

  /// Reads a cached blob back. Returns null when the key is missing, empty,
  /// holds corrupt JSON, or does not decode to a map.
  Future<Map<String, dynamic>?> readJson(String key) async {
    try {
      final prefs = await _prefs;
      final raw = prefs.getString(key);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      return decoded is Map<String, dynamic> ? decoded : null;
    } catch (_) {
      // Corrupt blobs degrade to "no cache" so the caller shows the error view.
      return null;
    }
  }

  /// Drops a cached blob (e.g. when stored data is known to be stale).
  Future<void> remove(String key) async {
    try {
      final prefs = await _prefs;
      await prefs.remove(key);
    } catch (_) {
      // Best-effort: a failed removal only means the old blob survives.
    }
  }
}
