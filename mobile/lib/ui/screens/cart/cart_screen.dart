import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/persian_number.dart';
import '../../../state/auth_state.dart';
import '../../../state/cart_state.dart';
import '../../common/empty_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_card.dart';
import '../../common/flavor_text_field.dart';

/// Shopping Cart Screen with coupon code input and price calculations.
class CartScreen extends StatefulWidget {
  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  final _couponController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartProvider>().loadCart();
    });
  }

  void _handleApplyCoupon() async {
    final code = _couponController.text.trim();
    if (code.isEmpty) return;

    final cart = context.read<CartProvider>();
    final success = await cart.applyCoupon(code);

    if (success) {
      _couponController.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('کد تخفیف با موفقیت اعمال شد.')),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(cart.errorMessage ?? 'کد تخفیف نامعتبر است.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final cart = context.watch<CartProvider>();

    if (cart.isEmpty && !cart.isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text('سبد خرید')),
        body: EmptyState(
          title: 'سبد خرید شما خالی است',
          description: 'از منوی رستوران غذای دلخواه خود را انتخاب کنید.',
          icon: Icons.shopping_basket_outlined,
          buttonText: 'مشاهده منوی رستوران',
          onButtonPressed: () => Navigator.pushReplacementNamed(context, AppRoutes.home),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('سبد خرید'),
        actions: [
          if (cart.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.delete_outline),
              tooltip: 'خالی کردن سبد',
              onPressed: () => cart.clearCart(),
            ),
        ],
      ),
      bottomNavigationBar: cart.isNotEmpty
          ? Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: theme.cardColor,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.08),
                    blurRadius: 10,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SafeArea(
                child: Row(
                  children: [
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('مبلغ کل:', style: TextStyle(fontSize: 12, color: Colors.grey)),
                        Text(
                          CurrencyFormatter.format(cart.total),
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: primary),
                        ),
                      ],
                    ),
                    const SizedBox(width: 20),
                    Expanded(
                      child: FlavorButton(
                        text: 'ادامه و انتخاب نحوه تحویل',
                        icon: Icons.arrow_back,
                        onPressed: () {
                          Navigator.pushNamed(context, AppRoutes.checkout);
                        },
                      ),
                    ),
                  ],
                ),
              ),
            )
          : null,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Cart Items List
            ...cart.cart.items.map((item) {
              final modsText = item.modifiers.map((m) => m.name).join('، ');

              return FlavorCard(
                margin: const EdgeInsets.only(bottom: 12),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Item Info
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.name,
                            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                          ),
                          if (modsText.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              modsText,
                              style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey),
                            ),
                          ],
                          if (item.instructions.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              'یادداشت: ${item.instructions}',
                              style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey),
                            ),
                          ],
                          const SizedBox(height: 8),
                          Text(
                            item.lineHtml.isNotEmpty ? item.lineHtml : item.priceHtml,
                            style: TextStyle(fontWeight: FontWeight.bold, color: primary),
                          ),
                        ],
                      ),
                    ),

                    // Quantity Counter
                    Container(
                      decoration: BoxDecoration(
                        border: Border.all(color: theme.dividerColor),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.remove, size: 18),
                            onPressed: () => cart.updateQuantity(item.key, item.quantity - 1),
                          ),
                          Text(
                            PersianNumber.toPersian(item.quantity),
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          IconButton(
                            icon: const Icon(Icons.add, size: 18),
                            onPressed: () => cart.updateQuantity(item.key, item.quantity + 1),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            }),

            const SizedBox(height: 16),

            // Coupon Code Card
            FlavorCard(
              child: Row(
                children: [
                  Expanded(
                    child: FlavorTextField(
                      controller: _couponController,
                      hintText: 'کد تخفیف دارید؟',
                      prefixIcon: const Icon(Icons.card_giftcard),
                    ),
                  ),
                  const SizedBox(width: 10),
                  FlavorButton(
                    text: 'اعمال',
                    width: 90,
                    height: 48,
                    onPressed: _handleApplyCoupon,
                  ),
                ],
              ),
            ),

            if (cart.cart.coupons.isNotEmpty) ...[
              const SizedBox(height: 8),
              ...cart.cart.coupons.map(
                (c) => Chip(
                  label: Text('کوپن فعال: $c'),
                  deleteIcon: const Icon(Icons.close, size: 16),
                  onDeleted: () => cart.removeCoupon(),
                ),
              ),
            ],

            const SizedBox(height: 16),

            // Bill Summary
            FlavorCard(
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('جمع اقلام:'),
                      Text(CurrencyFormatter.format(cart.subtotal)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('تخفیف:'),
                      Text(
                        cart.subtotal > cart.total
                            ? '- ${CurrencyFormatter.format(cart.subtotal - cart.total)}'
                            : '۰ تومان',
                        style: const TextStyle(color: Colors.green, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'مبلغ نهایی قابل پرداخت:',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                      Text(
                        CurrencyFormatter.format(cart.total),
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: primary),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }
}
