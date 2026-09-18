import '../config/constants.dart';
import '../core/api/api_client.dart';
import '../core/utils/persian_number.dart';
import '../models/reservation_model.dart';

/// Repository for Table Reservations and Jalali Calendar Slot calculations.
class ReservationRepository {
  final ApiClient _apiClient;

  ReservationRepository({ApiClient? apiClient}) : _apiClient = apiClient ?? ApiClient();

  /// Gets Jalali month days grid with open/closed statuses.
  Future<Map<String, dynamic>> getCalendar({int? jy, int? jm}) async {
    final params = <String, dynamic>{};
    if (jy != null && jy > 0) params['jy'] = jy;
    if (jm != null && jm > 0) params['jm'] = jm;

    final res = await _apiClient.get(AppConstants.epReservationCalendar, queryParameters: params);
    return res['data'] as Map<String, dynamic>;
  }

  /// Calculates available table time slots for party size and section.
  Future<List<ReservationSlot>> getSlots({
    required int branchId,
    required String date,
    int party = 2,
    String section = '',
  }) async {
    final res = await _apiClient.get(
      AppConstants.epReservationSlots,
      queryParameters: {
        'branch_id': branchId,
        'date': date,
        'party': party,
        'section': section,
      },
    );

    final data = res['data'] as Map<String, dynamic>;
    final list = data['slots'] as List? ?? [];
    return list.map((e) => ReservationSlot.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Submits online table reservation.
  Future<Map<String, dynamic>> bookTable({
    required int branchId,
    required String date,
    required String time,
    required int partySize,
    required String mobile,
    required String name,
    String section = 'indoor',
    String requests = '',
  }) async {
    final cleanMobile = PersianNumber.cleanMobile(mobile);
    final res = await _apiClient.post(
      AppConstants.epReservations,
      body: {
        'branch_id': branchId,
        'date': date,
        'time': time,
        'party_size': partySize,
        'mobile': cleanMobile,
        'name': name,
        'section': section,
        'requests': requests,
      },
    );
    return res['data'] as Map<String, dynamic>;
  }

  /// Gets authenticated user's past and upcoming reservations.
  Future<List<ReservationModel>> getMyReservations() async {
    final res = await _apiClient.get('${AppConstants.epReservations}/my', requiresAuth: true);
    final list = res['data'] as List;
    return list.map((e) => ReservationModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Cancels a reservation.
  Future<void> cancelReservation(int id) async {
    await _apiClient.post('${AppConstants.epReservations}/$id/cancel');
  }
}
