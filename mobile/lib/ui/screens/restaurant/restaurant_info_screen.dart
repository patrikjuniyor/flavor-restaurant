import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/utils/persian_number.dart';
import '../../../state/config_state.dart';
import '../../common/flavor_card.dart';

/// Restaurant About, Contact, Opening Hours, and Branches Screen.
class RestaurantInfoScreen extends StatelessWidget {
  const RestaurantInfoScreen({super.key});

  void _callPhone(String phone) async {
    final uri = Uri.parse('tel:$phone');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  void _openInstagram(String username) async {
    final uri = Uri.parse('https://instagram.com/$username');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final config = context.watch<ConfigProvider>();
    final brand = config.brand;

    return Scaffold(
      appBar: AppBar(title: const Text('درباره و شعب رستوران')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Restaurant Header Card
            FlavorCard(
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 36,
                    backgroundColor: primary,
                    child: const Icon(Icons.restaurant, size: 40, color: Colors.white),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    brand.name,
                    style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    brand.description,
                    style: theme.textTheme.bodyMedium?.copyWith(color: Colors.grey),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Working Hours Card
            FlavorCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.access_time, color: primary),
                      const SizedBox(width: 8),
                      const Text('ساعات کاری و پذیرش', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    ],
                  ),
                  const Divider(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: const [
                      Text('شنبه تا چهارشنبه:'),
                      Text('۱۱:۳۰ الی ۲۳:۳۰', style: TextStyle(fontWeight: FontWeight.bold)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: const [
                      Text('پنج‌شنبه و جمعه:'),
                      Text('۱۲:۰۰ الی ۰۰:۰۰', style: TextStyle(fontWeight: FontWeight.bold)),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Branches List Card
            Text('شعب رستوران', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ...config.branches.map(
              (branch) => FlavorCard(
                margin: const EdgeInsets.only(bottom: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(branch.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    const SizedBox(height: 6),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.location_on_outlined, size: 16, color: Colors.grey),
                        const SizedBox(width: 6),
                        Expanded(child: Text(branch.address, style: theme.textTheme.bodySmall)),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        const Icon(Icons.phone_outlined, size: 16, color: Colors.grey),
                        const SizedBox(width: 6),
                        Text(PersianNumber.toPersian(branch.phone), style: theme.textTheme.bodySmall),
                        const Spacer(),
                        TextButton.icon(
                          icon: const Icon(Icons.phone, size: 16),
                          label: const Text('تماس'),
                          onPressed: () => _callPhone(branch.phone),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Social links
            if (brand.instagram.isNotEmpty)
              FlavorCard(
                child: ListTile(
                  leading: const Icon(Icons.camera_alt_outlined, color: Colors.purple),
                  title: const Text('اینستاگرام رستوران'),
                  subtitle: Text('@${brand.instagram}', textDirection: TextDirection.ltr),
                  trailing: const Icon(Icons.open_in_new, size: 18),
                  onTap: () => _openInstagram(brand.instagram),
                ),
              ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }
}
