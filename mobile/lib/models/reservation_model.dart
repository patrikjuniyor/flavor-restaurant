/// Table Reservation domain models.
///
/// Mirrors ReservationController + ReservationRepository — see
/// test/fixtures/reservation_slots.json, reservation_book.json and
/// reservation_row.json. The row endpoint returns raw DB columns, so several
/// fields are genuinely nullable there (table_id, section, special_requests).
library;

import 'calendar_model.dart';
import 'json_fields.dart';

class ReservationSlot {
  final String time;

  /// Full "HH:MM:SS" form for API round-trips.
  final String timeSql;
  final bool available;

  /// Remaining seats at this slot — the API key is `free`
  /// (SlotCalculator::slots()), NOT `remaining`.
  final int free;

  const ReservationSlot({
    required this.time,
    this.timeSql = '',
    required this.available,
    this.free = 0,
  });

  factory ReservationSlot.fromJson(Map<String, dynamic> json) {
    return ReservationSlot(
      time: strField(json['time']),
      timeSql: strField(json['time_sql']),
      available: boolField(json['available'], whenNull: true),
      free: intField(json['free']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'time': time,
      'time_sql': timeSql,
      'available': available,
      'free': free,
    };
  }
}

class ReservationModel {
  final int id;
  final int branchId;

  /// Nullable in the DB row (walk-in reservations carry no table).
  final int? tableId;
  final String reservationDate;

  /// Raw DB TIME value, e.g. "20:30:00".
  final String reservationTime;
  final int durationMinutes;
  final int partySize;
  final String section;
  final String status;

  /// Nullable in the DB row (pure guest reservation).
  final int? customerId;
  final String customerName;
  final String customerMobile;
  final JalaliDateInfo? jalali;
  final String jalaliLabel;
  final String specialRequests;
  final String source;

  const ReservationModel({
    required this.id,
    required this.branchId,
    this.tableId,
    required this.reservationDate,
    required this.reservationTime,
    this.durationMinutes = 90,
    required this.partySize,
    this.section = 'indoor',
    required this.status,
    this.customerId,
    required this.customerName,
    required this.customerMobile,
    this.jalali,
    this.jalaliLabel = '',
    this.specialRequests = '',
    this.source = 'online',
  });

  factory ReservationModel.fromJson(Map<String, dynamic> json) {
    final jalaliRaw = json['jalali'];
    return ReservationModel(
      id: intField(json['id']),
      branchId: intField(json['branch_id']),
      tableId: nullableIntField(json['table_id']),
      // The row payload uses reservation_date/reservation_time; the booking
      // response uses date/time — accept both.
      reservationDate: strField(json['reservation_date'], strField(json['date'])),
      reservationTime: strField(json['reservation_time'], strField(json['time'])),
      durationMinutes: intField(json['duration_minutes'], 90),
      partySize: intField(json['party_size'], intField(json['party'], 2)),
      section: strField(json['section'], 'indoor'),
      status: strField(json['status'], 'pending'),
      customerId: nullableIntField(json['customer_id']),
      customerName: strField(json['customer_name']),
      customerMobile: strField(json['customer_mobile'], strField(json['mobile'])),
      jalali: jalaliRaw is Map<String, dynamic> ? JalaliDateInfo.fromJson(jalaliRaw) : null,
      jalaliLabel: strField(json['jalali_label']),
      specialRequests: strField(json['special_requests'], strField(json['requests'])),
      source: strField(json['source'], 'online'),
    );
  }

  /// "HH:MM" without the DB seconds suffix, for display.
  String get reservationTimeShort =>
      reservationTime.length >= 5 ? reservationTime.substring(0, 5) : reservationTime;

  bool get isUpcoming => status == 'confirmed' || status == 'pending';
}
