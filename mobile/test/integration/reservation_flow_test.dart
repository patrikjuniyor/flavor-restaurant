import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/core/utils/jalali_date.dart';
import 'package:flavor_mobile/repositories/reservation_repository.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  SharedPreferences.setMockInitialValues({});
  FlutterSecureStorage.setMockInitialValues({});

  group('Table Reservation Integration Test Flow', () {
    test('Select Date -> Query Slots -> Book Table -> Confirm', () async {
      final mockHttpClient = MockClient((request) async {
        final path = request.url.path;

        if (path.contains('/reservations/slots')) {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'slots': [
                  {'time': '19:30', 'available': true, 'remaining_capacity': 12},
                  {'time': '20:30', 'available': true, 'remaining_capacity': 8},
                  {'time': '21:30', 'available': false, 'remaining_capacity': 0},
                ]
              }
            }),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('/reservations') && request.method == 'POST') {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'ok': true,
                'id': 305,
                'status': 'confirmed',
                'branch_id': 101,
                'reservation_date': '2026-09-18',
                'reservation_time': '20:30',
                'party_size': 4,
                'guest_token': 'guest_res_tok_sec_991'
              }
            }),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        return http.Response(jsonEncode({'success': true, 'data': {}}), 200);
      });

      final storage = StorageService();
      final apiClient = ApiClient(httpClient: mockHttpClient, storage: storage);
      final resRepo = ReservationRepository(apiClient: apiClient);

      const testBranchId = 101;
      final testDate = JalaliDate.fromDateTime(DateTime.now()).toIsoGregorianString();

      // 1. Query available slots
      final slots = await resRepo.getSlots(
        branchId: testBranchId,
        date: testDate,
        party: 4,
        section: 'indoor',
      );
      expect(slots, isA<List>());
      expect(slots.length, 3);
      expect(slots[0]['time'], '19:30');

      // 2. Book Table
      final booking = await resRepo.bookTable(
        branchId: testBranchId,
        date: testDate,
        time: '20:30',
        partySize: 4,
        mobile: '09123456789',
        name: 'رضا حسینی',
        section: 'indoor',
        requests: 'صندلی کودک و میز کنار پنجره',
      );

      expect(booking['ok'], true);
      expect(booking['id'], 305);
      expect(booking['status'], 'confirmed');
    });
  });
}

