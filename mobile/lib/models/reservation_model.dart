/// Table Reservation domain models.

class ReservationSlot {
  final String time;
  final bool available;
  final int remaining;

  const ReservationSlot({
    required this.time,
    required this.available,
    this.remaining = 1,
  });

  factory ReservationSlot.fromJson(Map<String, dynamic> json) {
    return ReservationSlot(
      time: json['time']?.toString() ?? '',
      available: json['available'] != false,
      remaining: int.tryParse(json['remaining']?.toString() ?? '1') ?? 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'time': time,
      'available': available,
      'remaining': remaining,
    };
  }
}

class ReservationModel {
  final int id;
  final int branchId;
  final String reservationDate;
  final String reservationTime;
  final int partySize;
  final String section;
  final String status;
  final String customerName;
  final String customerMobile;
  final String jalaliLabel;
  final String specialRequests;

  const ReservationModel({
    required this.id,
    required this.branchId,
    required this.reservationDate,
    required this.reservationTime,
    required this.partySize,
    this.section = 'indoor',
    required this.status,
    required this.customerName,
    required this.customerMobile,
    this.jalaliLabel = '',
    this.specialRequests = '',
  });

  factory ReservationModel.fromJson(Map<String, dynamic> json) {
    return ReservationModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      branchId: int.tryParse(json['branch_id']?.toString() ?? '0') ?? 0,
      reservationDate: json['reservation_date']?.toString() ?? (json['date']?.toString() ?? ''),
      reservationTime: json['reservation_time']?.toString() ?? (json['time']?.toString() ?? ''),
      partySize: int.tryParse(json['party_size']?.toString() ?? (json['party']?.toString() ?? '2')) ?? 2,
      section: json['section']?.toString() ?? 'indoor',
      status: json['status']?.toString() ?? 'pending',
      customerName: json['customer_name']?.toString() ?? '',
      customerMobile: json['customer_mobile']?.toString() ?? (json['mobile']?.toString() ?? ''),
      jalaliLabel: json['jalali_label']?.toString() ?? '',
      specialRequests: json['special_requests']?.toString() ?? (json['requests']?.toString() ?? ''),
    );
  }

  bool get isUpcoming => status == 'confirmed' || status == 'pending';
}
