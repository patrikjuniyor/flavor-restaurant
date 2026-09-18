import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/models/app_bootstrap_model.dart';
import 'package:flavor_mobile/models/branch_model.dart';
import 'package:flavor_mobile/models/cart_model.dart';
import 'package:flavor_mobile/models/dish_model.dart';
import 'package:flavor_mobile/models/order_model.dart';
import 'package:flavor_mobile/models/reservation_model.dart';
import 'package:flavor_mobile/models/token_model.dart';
import 'package:flavor_mobile/models/user_model.dart';

void main() {
  group('Domain Models Serialization Tests', () {
    test('TokenModel json deserialization', () {
      final json = {
        'access_token': 'test_acc_123',
        'refresh_token': 'test_ref_456',
        'token_type': 'Bearer',
        'expires_in': 604800,
      };
      final token = TokenModel.fromJson(json);

      expect(token.accessToken, 'test_acc_123');
      expect(token.refreshToken, 'test_ref_456');
      expect(token.tokenType, 'Bearer');
      expect(token.expiresIn, 604800);
    });

    test('DishModel with ModifierGroups deserialization', () {
      final json = {
        'id': 101,
        'name': 'کباب کوبیده زعفرانی',
        'slug': 'koobideh',
        'price': 2500000,
        'price_html': '۲۵۰,۰۰۰ تومان',
        'prep_time': 15,
        'calories': 650,
        'available': true,
        'modifier_groups': [
          {
            'type': 'topping',
            'title': 'مخلفات',
            'required': false,
            'multi': true,
            'options': [
              {'id': 'top-1', 'name': 'گوجه اضافه', 'price': 25000, 'price_html': '+۲۵,۰۰۰ تومان'}
            ]
          }
        ]
      };

      final dish = DishModel.fromJson(json);

      expect(dish.id, 101);
      expect(dish.name, 'کباب کوبیده زعفرانی');
      expect(dish.price, 2500000);
      expect(dish.modifierGroups.length, 1);
      expect(dish.modifierGroups.first.options.first.name, 'گوجه اضافه');
    });

    test('CartModel deserialization', () {
      final json = {
        'items': [
          {
            'key': 'cart_line_1',
            'product_id': 101,
            'name': 'کباب کوبیده',
            'quantity': 2,
            'price_html': '۲۵۰,۰۰۰ تومان',
            'line_html': '۵۰۰,۰۰۰ تومان',
          }
        ],
        'count': 2,
        'subtotal': 5000000,
        'total': 5000000,
      };

      final cart = CartModel.fromJson(json);

      expect(cart.items.length, 1);
      expect(cart.count, 2);
      expect(cart.items.first.productId, 101);
      expect(cart.isEmpty, false);
    });

    test('OrderModel deserialization and tracking steps', () {
      final json = {
        'order_id': 8520,
        'order_number': '#8520',
        'status': 'preparing',
        'status_label': 'در حال پخت',
        'order_mode': 'delivery',
        'total': 5000000,
        'total_html': '۵۰۰,۰۰۰ تومان',
        'steps': [
          {'step': 'received', 'title': 'دریافت سفارش', 'completed': true, 'time': '12:00'},
          {'step': 'preparing', 'title': 'در حال آماده‌سازی', 'completed': true, 'time': '12:05'},
        ]
      };

      final order = OrderModel.fromJson(json);

      expect(order.id, 8520);
      expect(order.status, 'preparing');
      expect(order.steps.length, 2);
      expect(order.steps.first.completed, true);
    });
  });
}
