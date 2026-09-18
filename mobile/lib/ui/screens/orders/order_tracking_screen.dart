import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../state/order_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_card.dart';

/// Real-time Order Tracking Screen with Animated Step Progress Bar.
/// Supports guest token verification for unauthenticated guest orders.
class OrderTrackingScreen extends StatefulWidget {
  final int orderId;
  final String? guestToken;

  const OrderTrackingScreen({
    super.key,
    required this.orderId,
    this.guestToken,
  });

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OrderProvider>().startTracking(widget.orderId, guestToken: widget.guestToken);
    });
  }

  @override
  void dispose() {
    context.read<OrderProvider>().stopTracking();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final orderProvider = context.watch<OrderProvider>();
    final order = orderProvider.trackedOrder;

    if (order == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('رهگیری سفارش')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    final isDelivered = order.status == 'completed';
    final isCancelled = order.isCancelled;

    return Scaffold(
      appBar: AppBar(
        title: Text('رهگیری سفارش ${order.orderNumber}'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Status Header Banner
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: isCancelled ? Colors.red.withOpacity(0.1) : primary.withOpacity(0.08),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: isCancelled ? Colors.red : primary.withOpacity(0.3),
                ),
              ),
              child: Column(
                children: [
                  Icon(
                    isCancelled
                        ? Icons.cancel_outlined
                        : (isDelivered ? Icons.check_circle_outline : Icons.access_time),
                    size: 48,
                    color: isCancelled ? Colors.red : primary,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    isCancelled
                        ? 'این سفارش لغو شده است'
                        : (isDelivered ? 'سفارش شما تحویل داده شد' : 'سفارش شما در حال پردازش است'),
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 6),
                  if (!isCancelled && !isDelivered)
                    Text(
                      'زمان تخمینی تحویل: ${order.estimatedTime}',
                      style: theme.textTheme.bodyMedium?.copyWith(color: Colors.grey),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Live Tracking 4-Step Timeline
            if (!isCancelled) ...[
              Text(
                'مراحل آماده‌سازی سفارش',
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 16),
              FlavorCard(
                child: Column(
                  children: [
                    _buildTrackingStep(
                      title: 'دریافت سفارش',
                      subtitle: 'سفارش در سیستم ثبت شد',
                      isCompleted: true,
                      isCurrent: order.status == 'new',
                      isFirst: true,
                    ),
                    _buildTrackingStep(
                      title: 'در حال پخت در آشپزخانه',
                      subtitle: 'سرآشپز در حال آماده‌سازی سفارش است',
                      isCompleted: inArray(order.status, ['preparing', 'ready', 'completed']),
                      isCurrent: order.status == 'preparing',
                    ),
                    _buildTrackingStep(
                      title: order.orderMode == 'delivery' ? 'تحویل به پیک' : 'آماده تحویل / سرو',
                      subtitle: 'غذا بسته‌بندی و آماده ارسال شد',
                      isCompleted: inArray(order.status, ['ready', 'completed']),
                      isCurrent: order.status == 'ready',
                    ),
                    _buildTrackingStep(
                      title: 'تحویل نهایی',
                      subtitle: 'سفارش با موفقیت تحویل شد',
                      isCompleted: order.status == 'completed',
                      isCurrent: order.status == 'completed',
                      isLast: true,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
            ],

            // Order Items Details
            Text(
              'اقلام سفارش',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 10),
            FlavorCard(
              child: Column(
                children: [
                  ...order.items.map(
                    (item) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('${item.name} × ${item.quantity}'),
                          Text(item.totalHtml.isNotEmpty ? item.totalHtml : CurrencyFormatter.format(item.total)),
                        ],
                      ),
                    ),
                  ),
                  const Divider(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('مجموع فاکتور:', style: TextStyle(fontWeight: FontWeight.bold)),
                      Text(
                        CurrencyFormatter.format(order.total),
                        style: TextStyle(fontWeight: FontWeight.bold, color: primary),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Cancel Button if still pending
            if (!isCancelled && !isDelivered && order.status == 'new')
              FlavorButton(
                text: 'لغو سفارش',
                variant: FlavorButtonVariant.outline,
                onPressed: () async {
                  final confirm = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('لغو سفارش'),
                      content: const Text('آیا از لغو این سفارش مطمئن هستید؟'),
                      actions: [
                        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('خیر')),
                        ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('بله، لغو شود')),
                      ],
                    ),
                  );

                  if (confirm == true) {
                    await orderProvider.cancelOrder(order.id, guestToken: widget.guestToken);
                  }
                },
              ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }

  bool inArray(String val, List<String> arr) => arr.contains(val);

  Widget _buildTrackingStep({
    required String title,
    required String subtitle,
    required bool isCompleted,
    required bool isCurrent,
    bool isFirst = false,
    bool isLast = false,
  }) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Indicator circle and line
        Column(
          children: [
            Container(
              width: 24,
              height: 24,
              decoration: BoxDecoration(
                color: isCompleted ? primary : Colors.grey.withOpacity(0.3),
                shape: BoxShape.circle,
              ),
              child: Icon(
                isCompleted ? Icons.check : Icons.circle,
                size: 14,
                color: Colors.white,
              ),
            ),
            if (!isLast)
              Container(
                width: 2,
                height: 40,
                color: isCompleted ? primary : Colors.grey.withOpacity(0.3),
              ),
          ],
        ),
        const SizedBox(width: 14),

        // Step Title & Subtitle
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 14,
                  color: isCurrent ? primary : (isCompleted ? null : Colors.grey),
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey),
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ],
    );
  }
}
