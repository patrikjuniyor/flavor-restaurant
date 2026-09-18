import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/models/cart_model.dart';

void main() {
  group('Mobile Guest Cart Model & Serialization Tests', () {
    test('CartModel deserializes with items, modifier selections, and price formatting', () {
      final json = {
        'items': [
          {
            'key': 'item_cart_101',
            'product_id': 55,
            'name': 'کباب کوبیده مخصوص زعفرانی',
            'quantity': 2,
            'price_html': '۲۸۰,۰۰۰ تومان',
            'line_html': '۵۶۰,۰۰۰ تومان',
            'modifiers': [
              {'id': 'extra_rice', 'name': 'برنج اضافه', 'price': 40000}
            ],
            'instructions': 'لطفاً سماق جداگانه ارسال شود.',
            'image': 'https://example.com/images/kabab.jpg',
          }
        ],
        'count': 2,
        'subtotal': 560000,
        'total': 560000,
        'subtotal_html': '۵۶۰,۰۰۰ تومان',
        'total_html': '۵۶۰,۰۰۰ تومان',
        'fees': [],
        'coupons': ['NOROOZ1403'],
        'points_to_earn': 56,
        'needs_payment': true,
      };

      final cart = CartModel.fromJson(json);

      expect(cart.count, 2);
      expect(cart.subtotal, 560000);
      expect(cart.total, 560000);
      expect(cart.isEmpty, isFalse);
      expect(cart.items.length, 1);

      final item = cart.items.first;
      expect(item.key, 'item_cart_101');
      expect(item.productId, 55);
      expect(item.name, 'کباب کوبیده مخصوص زعفرانی');
      expect(item.quantity, 2);
      expect(item.instructions, 'لطفاً سماق جداگانه ارسال شود.');
      expect(item.modifiers.length, 1);
      expect(item.modifiers.first.id, 'extra_rice');
    });

    test('Empty CartModel returns default zero state', () {
      const cart = CartModel();
      expect(cart.isEmpty, isTrue);
      expect(cart.count, 0);
      expect(cart.total, 0);
      expect(cart.items, isEmpty);
    });
  });
}
