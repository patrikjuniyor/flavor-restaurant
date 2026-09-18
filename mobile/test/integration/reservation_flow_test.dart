import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/utils/jalali_date.dart';
import 'package:flavor_mobile/repositories/reservation_repository.dart';

void main() {
  group('Table Reservation Integration Test Flow', () {
    test('Select Date -> Query Slots -> Book Table -> Confirm', () async {
      final resRepo = ReservationRepository();
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
      expect(booking['id'], isNotNull);
    });
  });
}
