import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../state/auth_state.dart';
import '../../../state/order_state.dart';
import '../../common/empty_state.dart';
import '../../common/flavor_card.dart';

/// Customer Order History Screen.
class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthProvider>();
      if (auth.isAuthenticated) {
        context.read<OrderProvider>().loadOrders();
      }
    });
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'completed':
        return Colors.green;
      case 'preparing':
      case 'ready':
        return Colors.orange;
      case 'cancelled':
        return Colors.red;
      default:
        return Colors.blue;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();
    final orderProvider = context.watch<OrderProvider>();

    if (!auth.isAuthenticated) {
      return Scaffold(
        appBar: AppBar(title: const Text('سفارش‌های من')),
        body: EmptyState(
          title: 'وارد حساب کاربری خود شوید',
          description: 'برای مشاهده سوابق و وضعیت سفارش‌های خود ابتدا وارد شوید.',
          icon: Icons.lock_outline,
          buttonText: 'ورود به حساب',
          onButtonPressed: () => Navigator.pushNamed(context, AppRoutes.login),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('سفارش‌های من')),
      body: orderProvider.isLoading
          ? const Center(child: CircularProgressIndicator())
          : orderProvider.orders.isEmpty
              ? EmptyState(
                  title: 'سفارشی ثبت نکرده‌اید',
                  description: 'اولین سفارش خود را از منوی رستوران ثبت کنید.',
                  icon: Icons.receipt_long_outlined,
                  buttonText: 'مشاهده منو',
                  onButtonPressed: () => Navigator.pushReplacementNamed(context, AppRoutes.home),
                )
              : RefreshIndicator(
                  onRefresh: () => orderProvider.loadOrders(),
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: orderProvider.orders.length,
                    itemBuilder: (context, index) {
                      final order = orderProvider.orders[index];
                      final statusColor = _getStatusColor(order.status);

                      return FlavorCard(
                        margin: const EdgeInsets.only(bottom: 14),
                        onTap: () => Navigator.pushNamed(
                          context,
                          AppRoutes.orderTracking,
                          arguments: order.id,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  'سفارش ${order.orderNumber}',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: statusColor.withOpacity(0.12),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Text(
                                    order.statusLabel,
                                    style: TextStyle(
                                      color: statusColor,
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            if (order.itemsSummary.isNotEmpty)
                              Text(
                                order.itemsSummary,
                                style: theme.textTheme.bodyMedium,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            const SizedBox(height: 10),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  order.jalaliDate.isNotEmpty ? order.jalaliDate : order.date,
                                  style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey),
                                ),
                                Text(
                                  CurrencyFormatter.format(order.total),
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: theme.primaryColor,
                                  ),
                                ),
                              ],
                            ),
                            const Divider(height: 20),
                            Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton.icon(
                                    icon: const Icon(Icons.track_changes, size: 16),
                                    label: const Text('رهگیری زنده'),
                                    onPressed: () => Navigator.pushNamed(
                                      context,
                                      AppRoutes.orderTracking,
                                      arguments: order.id,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: OutlinedButton.icon(
                                    icon: const Icon(Icons.replay, size: 16),
                                    label: const Text('سفارش مجدد'),
                                    onPressed: () async {
                                      final ok = await orderProvider.reorder(order.id);
                                      if (ok && mounted) {
                                        Navigator.pushNamed(context, AppRoutes.cart);
                                      }
                                    },
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
