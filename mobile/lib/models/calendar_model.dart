import 'json_fields.dart';

/// Jalali calendar date as served by the API.
///
/// Both `/settings/app-bootstrap` (`calendar.jalali_today`) and the
/// reservation endpoints (`jalali`) serialize `Jalali::parse_gregorian()`
/// with exactly these five keys — see test/fixtures/app_bootstrap.json and
/// test/fixtures/reservation_row.json.
class JalaliDateInfo {
  final int year;
  final int month;
  final int day;
  final String iso;
  final String label;

  const JalaliDateInfo({
    this.year = 0,
    this.month = 0,
    this.day = 0,
    this.iso = '',
    this.label = '',
  });

  factory JalaliDateInfo.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const JalaliDateInfo();
    return JalaliDateInfo(
      year: intField(json['y']),
      month: intField(json['m']),
      day: intField(json['d']),
      iso: strField(json['iso']),
      label: strField(json['label']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'y': year,
      'm': month,
      'd': day,
      'iso': iso,
      'label': label,
    };
  }
}
