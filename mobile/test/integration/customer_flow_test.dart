import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/models/dish_model.dart';
import 'package:flavor_mobile/repositories/auth_repository.dart';
import 'package:flavor_mobile/repositories/cart_repository.dart';
import 'package:flavor_mobile/repositories/menu_repository.dart';
import 'package:flavor_mobile/repositories/order_repository.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  SharedPreferences.setMockInitialValues({});
  FlutterSecureStorage.setMockInitialValues({});

  group('Full Customer Journey Integration Test Flow', () {
    test('Login -> Browse Menu -> Add to Cart -> Checkout -> Track Order', () async {
      final mockHttpClient = MockClient((request) async {
        final path = request.url.path;

        if (path.contains('/auth/otp/verify')) {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'tokens': {
                  'access_token': 'test_access_jwt_customer_123',
                  'refresh_token': 'test_refresh_jwt_customer_456',
                  'token_type': 'Bearer',
                  'expires_in': 604800,
                },
                'user': {
                  'id': 1001,
                  'mobile': '09123456789',
                  'display_name': 'علی رضایی',
                  'roles': ['customer'],
                }
              }
            }),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('categories')) {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': [
                {'id': 10, 'name': 'چلوکباب‌ها', 'slug': 'kabab', 'count': 8},
                {'id': 11, 'name': 'خورشت‌ها', 'slug': 'khoresh', 'count': 5},
              ]
            }),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('/cart/items')) {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'items': [
                  {
                    'key': 'cart_line_test_101',
                    'product_id': 201,
                    'name': 'چلوکباب شیشلیک مخصوص',
                    'quantity': 2,
                    'unit_price': 6500000,
                    'line_total': 13000000,
                    'price_html': '۶۵۰,۰۰۰ تومان',
                    'line_html': '۱,۳۰۰,۰۰۰ تومان',
                    'modifiers': [
                      {'id': 'size-double', 'name': 'سایز دو نفره', 'price': 0},
                      {'id': 'top-zeytoon', 'name': 'زیتون پرورده', 'price': 350000}
                    ]
                  }
                ],
                'count': 2,
                'subtotal': 13700000,
                'total': 13700000,
                'total_html': '۱,۳۷۰,۰۰۰ تومان'
              }
            }),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('/orders') && request.method == 'POST') {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'ok': true,
                'order_id': 8520,
                'order_number': '#8520',
                'status': 'received',
                'total': 13700000,
                'total_html': '۱,۳۷۰,۰۰۰ تومان'
              }
            }),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('/orders/8520/track') || path.contains('/orders/8520')) {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'order_id': 8520,
                'order_number': '#8520',
                'status': 'preparing',
                'status_label': 'در حال پخت و آماده‌سازی',
                'order_mode': 'delivery',
                'total': 13700000,
                'total_html': '۱,۳۷۰,۰۰۰ تومان',
                'steps': [
                  {'step': 'received', 'title': 'ثبت سفارش', 'completed': true, 'time': '12:00'},
                  {'step': 'preparing', 'title': 'در حال پخت و آماده‌سازی', 'completed': true, 'time': '12:05'},
                  {'step': 'on_way', 'title': 'تحویل پیک', 'completed': false, 'time': null},
                  {'step': 'delivered', 'title': 'تحویل به مشتری', 'completed': false, 'time': null},
                ]
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

      final authRepo = AuthRepository(apiClient: apiClient, storage: storage);
      final menuRepo = MenuRepository(apiClient: apiClient, storage: storage);
      final cartRepo = CartRepository(apiClient: apiClient);
      final orderRepo = OrderRepository(apiClient: apiClient);

      // 1. Verify Authentication State
      expect(await authRepo.isAuthenticated(), false);

      // 2. OTP Verification Flow
      final user = await authRepo.verifyOtp('09123456789', '12345', name: 'علی رضایی');
      expect(user.displayName, 'علی رضایی');
      expect(await authRepo.isAuthenticated(), true);

      // 3. Browse Menu & Categories
      final categories = await menuRepo.getCategories();
      expect(categories, isA<List>());
      expect(categories.length, 2);

      // 4. Customise Dish and Add to Cart
      const testDish = DishModel(
        id: 201,
        name: 'چلوکباب شیشلیک مخصوص',
        slug: 'shishlik',
        price: 6500000,
        priceHtml: '۶۵۰,۰۰۰ تومان',
      );

      final cart = await cartRepo.addItem(
        productId: testDish.id,
        quantity: 2,
        modifierIds: ['size-double', 'top-zeytoon'],
        instructions: 'سس اضافه لطفاً',
      );
      expect(cart.items.isNotEmpty, true);
      expect(cart.count, 2);

      // 5. Checkout & Submit Order
      final orderResult = await orderRepo.createOrder(
        orderMode: 'delivery',
        branchId: 101,
        name: user.displayName,
        mobile: user.mobile,
        address: {'address': 'تهران، زعفرانیه', 'unit': '۴'},
        paymentMethod: 'flavor_pay_at_counter',
      );
      expect(orderResult['ok'], true);
      expect(orderResult['order_id'], isNotNull);

      final orderId = int.parse(orderResult['order_id'].toString());
      expect(orderId, 8520);

      // 6. Live Track Order Progress
      final tracking = await orderRepo.trackOrder(orderId);
      expect(tracking.id, 8520);
      expect(tracking.status, 'preparing');
      expect(tracking.steps.length, 4);
    });
  });
}


