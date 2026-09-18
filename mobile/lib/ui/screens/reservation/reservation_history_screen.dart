import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/utils/persian_number.dart';
import '../../../models/reservation_model.dart';
import '../../../state/auth_state.dart';
import '../../../state/reservation_state.dart';
import '../../common/empty_state.dart';
import '../../common/flavor_card.dart';

/// Screen listing customer past and upcoming table reservations.
class ReservationHistoryScreen extends StatefulWidget {
  const ReservationHistoryScreen({super.key});

  @override
  State<ReservationHistoryScreen> createState() => _ReservationHistoryScreenState();
}

class _ReservationHistoryScreenState extends State<ReservationHistoryScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ReservationProvider>().loadMyReservations();
    });
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'confirmed':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'cancelled':
      case 'no_show':
        return Colors.red;
      default:
        return Colors.blue;
    }
  }

  String _getStatusLabel(String status) {
    switch (status) {
      case 'confirmed':
        return 'تأیید شده';
      case 'pending':
        return 'در انتظار بررسی';
      case 'cancelled':
        return 'لغو شده';
      case 'seated':
        return 'پذیرش شده در سالن';
      case 'completed':
        return 'انجام شده';
      case 'no_show':
        return 'عدم مراجعه';
      default:
        return status;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();
    final res = context.watch<ReservationProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('سوابق رزرو میز')),
      body: res.isLoading
          ? const Center(child: CircularProgressIndicator())
          : res.myReservations.isEmpty
              ? const EmptyState(
                  title: 'رزروی ثبت نشده است',
                  description: 'میز دلخواه خود را برای دورهمی‌های خانوادگی و کاری رزرو کنید.',
                  icon: Icons.calendar_today_outlined,
                )
              : RefreshIndicator(
                  onRefresh: () => res.loadMyReservations(),
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: res.myReservations.length,
                    itemBuilder: (context, index) {
                      final item = res.myReservations[index];
                      final color = _getStatusColor(item.status);

                      return FlavorCard(
                        margin: const EdgeInsets.only(bottom: 12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Row(
                                  children: [
                                    const Icon(Icons.event, size: 18),
                                    const SizedBox(width: 8),
                                    Text(
                                      item.jalaliLabel.isNotEmpty ? item.jalaliLabel : item.reservationDate,
                                      style: const TextStyle(fontWeight: FontWeight.bold),
                                    ),
                                  ],
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: color.withOpacity(0.12),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Text(
                                    _getStatusLabel(item.status),
                                    style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            Text(
                              'ساعت: ${PersianNumber.toPersian(item.reservationTime.substring(0, 5))} | تعداد: ${PersianNumber.toPersian(item.partySize)} نفر | بخش: ${item.section}',
                              style: theme.textTheme.bodyMedium,
                            ),
                            if (item.specialRequests.isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(
                                'درخواست ویژه: ${item.specialRequests}',
                                style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey),
                              ),
                            ],
                            if (item.isUpcoming) ...[
                              const Divider(height: 20),
                              Align(
                                alignment: Alignment.centerLeft,
                                child: TextButton(
                                  style: TextButton.styleFrom(foregroundColor: Colors.red),
                                  onPressed: () async {
                                    final ok = await res.cancelReservation(item.id);
                                    if (ok && mounted) {
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        const SnackBar(content: Text('رزرو لغو شد.')),
                                      );
                                    }
                                  },
                                  child: const Text('لغو رزرو'),
                                ),
                              ),
                            ],
                          ],
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
