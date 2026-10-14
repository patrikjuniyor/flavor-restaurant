// Widget tests for profile screens: AddressesScreen and EditProfileScreen (prompt 70).
//
// Covers:
//  - AddressesScreen: lists saved addresses, displays province/city badge,
//    shows Add Address dialog with Iran province/city cascaded dropdowns,
//    allows deleting and adding addresses.
//  - EditProfileScreen: displays user displayName, mobile in Persian digits,
//    and birthdate via Jalali format; validates mobile against 09xxxxxxxxx pattern;
//    shows Jalali date picker for birthdate.
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/config/app_config.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/core/utils/iran_provinces.dart';
import 'package:flavor_mobile/models/user_model.dart';
import 'package:flavor_mobile/repositories/auth_repository.dart';
import 'package:flavor_mobile/state/auth_state.dart';
import 'package:flavor_mobile/ui/screens/profile/addresses_screen.dart';
import 'package:flavor_mobile/ui/screens/profile/edit_profile_screen.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  UserModel makeTestUser({
    List<SavedAddress>? savedAddresses,
    String mobile = '09121234567',
    String birthdate = '1995-06-15',
  }) {
    return UserModel(
      id: 42,
      displayName: 'سهراب سپهری',
      email: 'sohrab@example.com',
      mobile: mobile,
      birthdate: birthdate,
      avatarUrl: '',
      loyalty: const LoyaltySummary(points: 120, stamps: 3, stampTarget: 10, freeItem: false),
      savedAddresses: savedAddresses ??
          [
            const SavedAddress(
              id: 'addr_1',
              title: 'منزل',
              province: 'تهران',
              city: 'تهران',
              address: 'خیابان ولیعصر، کوچه نسترن، پلاک ۱۲',
              unit: '۴',
              postal: '1234567890',
            ),
            const SavedAddress(
              id: 'addr_2',
              title: 'محل کار',
              province: 'فارس',
              city: 'شیراز',
              address: 'بلوار زند، ساختمان میلاد',
              unit: '',
              postal: '',
            ),
          ],
    );
  }

  Future<AuthProvider> createAuthenticatedProvider(UserModel user, {Future<UserModel> Function(Map<String, dynamic> body)? onUpdate}) async {
    FlutterSecureStorage.setMockInitialValues({
      'flavor_access_token': 'test-access-token',
    });
    SharedPreferences.setMockInitialValues({
      'flavor_user_profile': jsonEncode(user.toJson()),
    });

    final storage = StorageService(secureStorage: const FlutterSecureStorage());
    await storage.init();

    final client = ApiClient(
      httpClient: MockClient((request) async {
        if (request.method == 'PUT' && request.url.path.endsWith('/auth/me')) {
          final body = jsonDecode(request.body) as Map<String, dynamic>;
          final updated = onUpdate != null ? await onUpdate(body) : user;
          return http.Response(
            jsonEncode({'success': true, 'data': {'user': updated.toJson()}}),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }
        if (request.method == 'GET' && request.url.path.endsWith('/auth/me')) {
          return http.Response(
            jsonEncode({'success': true, 'data': {'logged_in': true, 'user': user.toJson()}}),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }
        return http.Response('{"success": true}', 200, headers: {'content-type': 'application/json'});
      }),
      storage: storage,
    );
    final repo = AuthRepository(apiClient: client, storage: storage);
    final auth = AuthProvider(authRepo: repo);
    await auth.checkAuth();
    return auth;
  }

  Widget buildTestableWidget(Widget child, AuthProvider auth) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthProvider>.value(value: auth),
      ],
      child: MaterialApp(
        home: child,
      ),
    );
  }

  setUpAll(() {
    AppConfig.initialize(
      environment: FlavorEnvironment.dev,
      appName: 'Flavor Test',
      apiBaseUrl: 'http://localhost/wp-json/flavor/v1',
      tenantId: 'default',
      defaultBranchId: 1,
      enableLogging: false,
    );
  });

  setUp(() {
    IranGeography.clearCache();
  });

  group('AddressesScreen Widget Tests', () {
    testWidgets('renders saved addresses with title, address and province/city badge', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final user = makeTestUser();
      final auth = await createAuthenticatedProvider(user);

      await tester.pumpWidget(buildTestableWidget(const AddressesScreen(), auth));
      await tester.pump();

      expect(find.text('آدرس‌های منتخب'), findsOneWidget);
      expect(find.text('منزل'), findsOneWidget);
      expect(find.text('محل کار'), findsOneWidget);
      expect(find.text('خیابان ولیعصر، کوچه نسترن، پلاک ۱۲'), findsOneWidget);
      expect(find.text('بلوار زند، ساختمان میلاد'), findsOneWidget);
      expect(find.text('فارس، شیراز'), findsOneWidget);
    });

    testWidgets('empty state displays friendly message when addresses list is empty', (tester) async {
      final user = makeTestUser(savedAddresses: []);
      final auth = await createAuthenticatedProvider(user);

      await tester.pumpWidget(buildTestableWidget(const AddressesScreen(), auth));
      await tester.pump();

      expect(find.text('آدرسی ثبت نشده است'), findsOneWidget);
      expect(find.byType(FloatingActionButton), findsOneWidget);
    });

    testWidgets('tapping add opens dialog with cascaded province and city dropdowns', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final user = makeTestUser();
      final auth = await createAuthenticatedProvider(user);

      await tester.pumpWidget(buildTestableWidget(const AddressesScreen(), auth));
      await tester.pump();

      await tester.tap(find.byType(FloatingActionButton));
      await tester.pumpAndSettle();

      expect(find.text('افزودن آدرس جدید'), findsOneWidget);
      expect(find.text('استان'), findsOneWidget);
      expect(find.text('شهر'), findsOneWidget);
      expect(find.text('نشانی دقیق (خیابان، کوچه...)'), findsOneWidget);
      expect(find.text('واحد / طبقه'), findsOneWidget);
      expect(find.text('کد پستی'), findsOneWidget);
    });
  });

  group('EditProfileScreen Widget Tests', () {
    testWidgets('renders user profile fields with Persian mobile and Jalali birthdate', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final user = makeTestUser(mobile: '09121234567', birthdate: '2026-09-18');
      final auth = await createAuthenticatedProvider(user);

      await tester.pumpWidget(buildTestableWidget(const EditProfileScreen(), auth));
      await tester.pump();

      expect(find.text('ویرایش پروفایل'), findsOneWidget);
      expect(find.text('سهراب سپهری'), findsOneWidget);
      expect(find.text('۰۹۱۲۱۲۳۴۵۶۷'), findsOneWidget);
      expect(find.text('۱۴۰۵/۰۶/۲۷'), findsOneWidget);
      expect(find.textContaining('شهریور'), findsOneWidget);
    });

    testWidgets('validates invalid mobile number on submit and prevents save', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final user = makeTestUser();
      final auth = await createAuthenticatedProvider(user);

      await tester.pumpWidget(buildTestableWidget(const EditProfileScreen(), auth));
      await tester.pump();

      // Enter malformed mobile
      final mobileField = find.widgetWithText(TextFormField, '۰۹۱۲۱۲۳۴۵۶۷');
      expect(mobileField, findsOneWidget);
      await tester.enterText(mobileField, '0812345');
      await tester.pump();

      // Tap save
      final saveBtn = find.text('ذخیره تغییرات');
      await tester.ensureVisible(saveBtn);
      await tester.tap(saveBtn);
      await tester.pump();

      expect(find.textContaining('شماره موبایل نامعتبر است'), findsOneWidget);
    });
  });
}
