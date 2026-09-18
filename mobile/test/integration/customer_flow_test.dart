import 'package:flutter_test/flutter_test.dart';
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
      final storage = StorageService();
      final apiClient = ApiClient(storage: storage);

      final authRepo = AuthRepository(apiClient: apiClient, storage: storage);
      final menuRepo = MenuRepository(apiClient: apiClient, storage: storage);
      final cartRepo = CartRepository(apiClient: apiClient);
      final orderRepo = OrderRepository(apiClient: apiClient);

      // 1. Verify Authentication State
      expect(await authRepo.isAuthenticated(), false);

      // 2. Mock / Simulate OTP Verification
      final user = await authRepo.verifyOtp('09123456789', '12345', name: 'علی رضایی');
      expect(user.displayName, 'علی رضایی');
      expect(await authRepo.isAuthenticated(), true);

      // 3. Browse Menu & Categories
      final categories = await menuRepo.getCategories();
      expect(categories, isA<List>());

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

      // 6. Live Track Order Progress
      final tracking = await orderRepo.trackOrder(orderId);
      expect(tracking.id, orderId);
      expect(tracking.steps.isNotEmpty, true);
    });
  });
}

