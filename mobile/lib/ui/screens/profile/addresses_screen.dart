import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../models/user_model.dart';
import '../../../state/auth_state.dart';
import '../../common/empty_state.dart';
import '../../common/flavor_card.dart';
import '../../common/flavor_text_field.dart';

/// Manage Saved Addresses Screen.
class AddressesScreen extends StatelessWidget {
  const AddressesScreen({super.key});

  void _showAddAddressDialog(BuildContext context) {
    final titleCtrl = TextEditingController(text: 'منزل');
    final addrCtrl = TextEditingController();
    final unitCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('افزودن آدرس جدید'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            FlavorTextField(controller: titleCtrl, label: 'عنوان آدرس (منزل، محل کار...)'),
            const SizedBox(height: 10),
            FlavorTextField(controller: addrCtrl, label: 'نشانی دقیق', maxLines: 2),
            const SizedBox(height: 10),
            FlavorTextField(controller: unitCtrl, label: 'واحد / پلاک'),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('انصراف')),
          ElevatedButton(
            onPressed: () {
              if (addrCtrl.text.trim().isEmpty) return;
              final newAddr = SavedAddress(
                id: 'addr_${DateTime.now().millisecondsSinceEpoch}',
                title: titleCtrl.text.trim(),
                address: addrCtrl.text.trim(),
                unit: unitCtrl.text.trim(),
              );
              context.read<AuthProvider>().saveAddress(newAddr);
              Navigator.pop(ctx);
            },
            child: const Text('افزودن'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final addresses = auth.user?.savedAddresses ?? [];

    return Scaffold(
      appBar: AppBar(title: const Text('آدرس‌های منتخب')),
      floatingActionButton: FloatingActionButton.extended(
        icon: const Icon(Icons.add),
        label: const Text('آدرس جدید'),
        onPressed: () => _showAddAddressDialog(context),
      ),
      body: addresses.isEmpty
          ? const EmptyState(
              title: 'آدرسی ثبت نشده است',
              description: 'آدرس‌های منتخب خود را برای تسریع در ثبت سفارش اضافه کنید.',
              icon: Icons.location_on_outlined,
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: addresses.length,
              itemBuilder: (context, index) {
                final addr = addresses[index];
                return FlavorCard(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Row(
                    children: [
                      const Icon(Icons.location_on, color: Colors.redAccent),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(addr.title, style: const TextStyle(fontWeight: FontWeight.bold)),
                            const SizedBox(height: 4),
                            Text(addr.address, style: const TextStyle(fontSize: 13, color: Colors.grey)),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.delete_outline, size: 20, color: Colors.grey),
                        onPressed: () => auth.deleteAddress(addr.id),
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}
