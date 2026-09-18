import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/persian_number.dart';
import '../../../state/auth_state.dart';
import '../../../state/config_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_card.dart';

/// Customer Profile Dashboard Screen.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final auth = context.watch<AuthProvider>();
    final config = context.watch<ConfigProvider>();
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(title: const Text('پروفایل کاربری')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          children: [
            // User Card or Login Banner
            if (auth.isAuthenticated && user != null) ...[
              FlavorCard(
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 30,
                      backgroundColor: primary.withOpacity(0.15),
                      child: Text(
                        user.displayName.isNotEmpty ? user.displayName.substring(0, 1) : 'م',
                        style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: primary),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user.displayName,
                            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            PersianNumber.toPersian(user.mobile),
                            style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.edit_outlined),
                      onPressed: () => Navigator.pushNamed(context, AppRoutes.editProfile),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),

              // Loyalty Club Banner
              FlavorCard(
                color: theme.brightness == Brightness.dark ? const Color(0xFF2E2412) : const Color(0xFFFEF3C7),
                border: const BorderSide(color: Color(0xFFF59E0B)),
                child: Row(
                  children: [
                    const Icon(Icons.stars, color: Color(0xFFD97706), size: 36),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('باشگاه مشتریان', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFD97706),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  'سطح ${user.loyalty.tier}',
                                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'موجودی شما: ${PersianNumber.toPersian(user.loyalty.balance)} امتیاز (معادل ${CurrencyFormatter.format(user.loyalty.discountEquivalent)})',
                            style: const TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ] else ...[
              FlavorCard(
                child: Column(
                  children: [
                    const Icon(Icons.account_circle, size: 54, color: Colors.grey),
                    const SizedBox(height: 10),
                    const Text('هنوز وارد حساب خود نشده‌اید', style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 6),
                    const Text('برای ثبت سفارش، رهگیری و دریافت امتیازات وارد شوید.', style: TextStyle(fontSize: 12, color: Colors.grey), textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FlavorButton(
                      text: 'ورود یا عضویت سریع با پیامک',
                      onPressed: () => Navigator.pushNamed(context, AppRoutes.login),
                    ),
                  ],
                ),
              ),
            ],

            const SizedBox(height: 20),

            // Navigation Links Card
            FlavorCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.favorite_outline),
                    title: const Text('غذاهای مورد علاقه'),
                    trailing: const Icon(Icons.chevron_left),
                    onTap: () => Navigator.pushNamed(context, AppRoutes.favorites),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.location_on_outlined),
                    title: const Text('آدرس‌های منتخب من'),
                    trailing: const Icon(Icons.chevron_left),
                    onTap: () => Navigator.pushNamed(context, AppRoutes.addresses),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.history),
                    title: const Text('سوابق رزرو میز'),
                    trailing: const Icon(Icons.chevron_left),
                    onTap: () => Navigator.pushNamed(context, AppRoutes.reservationHistory),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.info_outline),
                    title: const Text('درباره رستوران، تماس و شعب'),
                    trailing: const Icon(Icons.chevron_left),
                    onTap: () => Navigator.pushNamed(context, AppRoutes.restaurantInfo),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Settings & Dark Mode Card
            FlavorCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: [
                  SwitchListTile(
                    secondary: const Icon(Icons.dark_mode_outlined),
                    title: const Text('حالت تاریک (Dark Mode)'),
                    value: config.themeMode == ThemeMode.dark,
                    onChanged: (isDark) {
                      config.setThemeMode(isDark ? ThemeMode.dark : ThemeMode.light);
                    },
                  ),
                ],
              ),
            ),

            if (auth.isAuthenticated) ...[
              const SizedBox(height: 24),
              FlavorButton(
                text: 'خروج از حساب کاربری',
                variant: FlavorButtonVariant.danger,
                onPressed: () => auth.logout(),
              ),
            ],
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }
}
