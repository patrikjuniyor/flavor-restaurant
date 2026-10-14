/// Defensive JSON field readers shared by the domain models.
///
/// The REST API (flavor-core/includes/API) is the source of truth for these
/// payloads — see mobile/test/fixtures/*.json, generated from the live PHP
/// controllers. Two PHP quirks drive this helper:
///
///  * `wp_get_attachment_image_url()` returns `false` (not `''`) when a post
///    has no thumbnail, and unguarded serializers pass that straight into
///    `json_encode` — a Dart `false.toString()` would silently become the
///    string `"false"` as an image URL.
///  * Money columns travel as JSON ints in storage units, but raw catalog
///    amounts (`*_raw`) are PHP floats that may arrive as `123`, `123.0` or
///    the string `"123.50"` depending on the serializer path, so numeric
///    readers accept int, double and string forms.
library;

/// String field: `null` and booleans (PHP `false` leaks) map to [fallback].
String strField(dynamic value, [String fallback = '']) {
  if (value == null || value is bool) return fallback;
  return value.toString();
}

/// Int field: accepts int, double (`123.0`), numeric strings (`"123"`,
/// `"123.50"` rounds) and maps anything else to [fallback].
int intField(dynamic value, [int fallback = 0]) {
  if (value == null || value is bool) return fallback;
  if (value is int) return value;
  if (value is double) return value.round();
  final asInt = int.tryParse(value.toString());
  if (asInt != null) return asInt;
  final asDouble = double.tryParse(value.toString());
  return asDouble?.round() ?? fallback;
}

/// Double field: same tolerance as [intField] without rounding.
double doubleField(dynamic value, [double fallback = 0]) {
  if (value == null || value is bool) return fallback;
  if (value is double) return value;
  if (value is int) return value.toDouble();
  return double.tryParse(value.toString()) ?? fallback;
}

/// Nullable double field: `null` stays `null` (API sends explicit nulls for
/// unset coordinates and radius values), everything else parses leniently.
double? nullableDoubleField(dynamic value) {
  if (value == null || value is bool) return null;
  if (value is double) return value;
  if (value is int) return value.toDouble();
  return double.tryParse(value.toString());
}

/// Nullable int field with the same contract as [nullableDoubleField].
int? nullableIntField(dynamic value) {
  if (value == null || value is bool) return null;
  if (value is int) return value;
  if (value is double) return value.round();
  final asInt = int.tryParse(value.toString());
  if (asInt != null) return asInt;
  return double.tryParse(value.toString())?.round();
}

/// Bool field: only an explicit `true` is true; `null` maps to [whenNull].
bool boolField(dynamic value, {bool whenNull = false}) {
  if (value == null) return whenNull;
  return value == true;
}
