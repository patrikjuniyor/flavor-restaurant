import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/core/utils/jalali_date.dart';
import 'package:flavor_mobile/repositories/reservation_repository.dart';
import 'package:flavor_mobile/models/reservation_model.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  SharedPreferences.setMockInitialValues({});
  FlutterSecureStorage.setMockInitialValues({});

  group('Table Reservation Integration Test Flow', () {
    test('Select Date -> Query Slots -> Book Table -> Confirm', () async {
      final mockHttpClient = MockClient((request) async {
        final path = request.url.path;

        // Real payloads captured from the live PHP controllers
        // (flavor-core/tests generator -> test/fixtures/, prompt 65): the
        // hand-rolled mocks used to invent a 'remaining_capacity' key the
        // API never sent (the real slot shape is {time,time_sql,available,free}).
        if (path.contains('/reservations/slots')) {
          return http.Response(
            File('test/fixtures/reservation_slots.json').readAsStringSync(),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('/reservations') && request.method == 'POST') {
          return http.Response(
            File('test/fixtures/reservation_book.json').readAsStringSync(),
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
      expect(slots, isNotEmpty);
      expect(slots.first.time, '12:00');
      expect(slots.first.timeSql, '12:00:00');
      expect(slots.first.available, isTrue);
      expect(slots.first.free, greaterThan(0));

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
      expect(booking['id'], 1);
      expect(booking['status'], 'pending');
      expect((booking['guest_token'] as String).length, 64);
      // The model parses the same fixture the repository just returned.
      final reservation = ReservationModel.fromJson(Map<String, dynamic>.from(booking));
      expect(reservation.reservationDate, '2026-10-28');
      expect(reservation.jalaliLabel, isNotEmpty);
    });
  });
}

