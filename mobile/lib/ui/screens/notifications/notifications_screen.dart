import 'package:flutter/material.dart';
import '../../common/empty_state.dart';
import '../../common/flavor_card.dart';

/// Customer In-App Notifications Inbox Screen.
class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    // In production, real-time push logs from Firestore/Local DB are listed here.
    final List<Map<String, String>> notifications = [
      {
        'title': 'سفارش شما تحویل داده شد',
        'body': 'نوش جان! امیدواریم از غذای خود لذت برده باشید. امتیازدهی به غذاها را فراموش نکنید.',
        'time': '۲ ساعت پیش',
        'type': 'order',
      },
      {
        'title': 'تخفیف ویژه پایان هفته',
        'body': 'با کد WEEKEND از ۲۰٪ تخفیف روی تمامی سفارش‌های بیرون‌بر بهره‌مند شوید.',
        'time': 'دیروز',
        'type': 'promo',
      },
      {
        'title': 'رزرو میز شما تأیید شد',
        'body': 'رزرو میز شما برای جمعه ساعت ۲۰:۳۰ در شعبه مرکزی با موفقیت نهایی شد.',
        'time': '۳ روز پیش',
        'type': 'reservation',
      },
    ];

    return Scaffold(
      appBar: AppBar(title: const Text('اعلان‌ها و پیام‌ها')),
      body: notifications.isEmpty
          ? const EmptyState(
              title: 'پیامی ندارید',
              description: 'اعلان‌های وضعیت سفارش و تخفیف‌های ویژه اینجا نمایش داده می‌شوند.',
              icon: Icons.notifications_none,
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: notifications.length,
              itemBuilder: (context, index) {
                final notif = notifications[index];
                final isOrder = notif['type'] == 'order';

                return FlavorCard(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: isOrder ? Colors.green.withOpacity(0.12) : Colors.orange.withOpacity(0.12),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          isOrder ? Icons.receipt_long : Icons.campaign,
                          color: isOrder ? Colors.green : Colors.orange,
                          size: 22,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              notif['title']!,
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              notif['body']!,
                              style: const TextStyle(fontSize: 13, height: 1.4, color: Colors.grey),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              notif['time']!,
                              style: const TextStyle(fontSize: 11, color: Colors.grey),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}
