import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/utils/iran_provinces.dart';
import '../../../core/utils/validators.dart';
import '../../../models/user_model.dart';
import '../../../state/auth_state.dart';
import '../../common/empty_state.dart';
import '../../common/flavor_card.dart';
import '../../common/flavor_text_field.dart';

/// Manage Saved Addresses Screen with Iran province/city cascaded selection.
class AddressesScreen extends StatelessWidget {
  const AddressesScreen({super.key});

  void _showAddressDialog(BuildContext context, [SavedAddress? existing]) {
    final isEdit = existing != null;
    final formKey = GlobalKey<FormState>();
    final titleCtrl = TextEditingController(text: existing?.title ?? 'منزل');
    final addrCtrl = TextEditingController(text: existing?.address ?? '');
    final unitCtrl = TextEditingController(text: existing?.unit ?? '');
    final postalCtrl = TextEditingController(text: existing?.postal ?? '');

    final allProvinces = IranGeography.provinces;
    String selectedProvince = existing != null && IranGeography.isValidProvince(existing.province)
        ? existing.province
        : (allProvinces.contains('تهران') ? 'تهران' : allProvinces.first);

    List<String> currentCities = IranGeography.citiesFor(selectedProvince);
    String selectedCity = existing != null && currentCities.contains(existing.city)
        ? existing.city
        : (currentCities.isNotEmpty ? currentCities.first : '');

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (dialogCtx, setDialogState) {
          return AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            title: Text(isEdit ? 'ویرایش آدرس' : 'افزودن آدرس جدید'),
            content: SizedBox(
              width: double.maxFinite,
              child: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      FlavorTextField(
                        controller: titleCtrl,
                        label: 'عنوان آدرس (منزل، محل کار...)',
                        hintText: 'مثال: منزل، شرکت',
                        validator: (val) {
                          if (val == null || val.trim().isEmpty) {
                            return 'عنوان آدرس الزامی است.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),

                      // Province Dropdown
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'استان',
                            style: Theme.of(dialogCtx).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                          ),
                          const SizedBox(height: 6),
                          DropdownButtonFormField<String>(
                            value: selectedProvince,
                            isExpanded: true,
                            decoration: const InputDecoration(
                              contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                            ),
                            items: allProvinces
                                .map(
                                  (p) => DropdownMenuItem(
                                    value: p,
                                    child: Text(p),
                                  ),
                                )
                                .toList(),
                            onChanged: (newProv) {
                              if (newProv == null) return;
                              setDialogState(() {
                                selectedProvince = newProv;
                                currentCities = IranGeography.citiesFor(newProv);
                                selectedCity = currentCities.isNotEmpty ? currentCities.first : '';
                              });
                            },
                            validator: Validators.validateProvince,
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // City Dropdown
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'شهر',
                            style: Theme.of(dialogCtx).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                          ),
                          const SizedBox(height: 6),
                          DropdownButtonFormField<String>(
                            value: currentCities.contains(selectedCity) ? selectedCity : null,
                            isExpanded: true,
                            decoration: const InputDecoration(
                              contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                            ),
                            hint: const Text('انتخاب شهر'),
                            items: currentCities
                                .map(
                                  (c) => DropdownMenuItem(
                                    value: c,
                                    child: Text(c),
                                  ),
                                )
                                .toList(),
                            onChanged: (newCity) {
                              if (newCity == null) return;
                              setDialogState(() => selectedCity = newCity);
                            },
                            validator: (val) => Validators.validateCity(selectedProvince, val),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      FlavorTextField(
                        controller: addrCtrl,
                        label: 'نشانی دقیق (خیابان، کوچه...)',
                        hintText: 'خیابان اصلی، کوچه، پلاک...',
                        maxLines: 2,
                        validator: Validators.validateAddressLine,
                      ),
                      const SizedBox(height: 12),

                      Row(
                        children: [
                          Expanded(
                            child: FlavorTextField(
                              controller: unitCtrl,
                              label: 'واحد / طبقه',
                              hintText: 'اختیاری',
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: FlavorTextField(
                              controller: postalCtrl,
                              label: 'کد پستی',
                              keyboardType: TextInputType.number,
                              hintText: '۱۰ رقم (اختیاری)',
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('انصراف')),
              ElevatedButton(
                onPressed: () {
                  if (!formKey.currentState!.validate()) return;

                  final newAddr = SavedAddress(
                    id: existing?.id ?? 'addr_${DateTime.now().millisecondsSinceEpoch}',
                    title: titleCtrl.text.trim(),
                    province: selectedProvince,
                    city: selectedCity,
                    address: addrCtrl.text.trim(),
                    unit: unitCtrl.text.trim(),
                    postal: postalCtrl.text.trim(),
                    lat: existing?.lat,
                    lng: existing?.lng,
                  );
                  context.read<AuthProvider>().saveAddress(newAddr);
                  Navigator.pop(ctx);
                },
                child: Text(isEdit ? 'ذخیره' : 'افزودن'),
              ),
            ],
          );
        },
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
        onPressed: () => _showAddressDialog(context),
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
                final locationSummary = [
                  addr.province,
                  if (addr.city.isNotEmpty && addr.city != addr.province) addr.city,
                ].join('، ');

                return FlavorCard(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Padding(
                        padding: EdgeInsets.only(top: 2.0),
                        child: Icon(Icons.location_on, color: Colors.redAccent),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Text(
                                  addr.title,
                                  style: const TextStyle(fontWeight: FontWeight.bold),
                                ),
                                if (locationSummary.isNotEmpty) ...[
                                  const SizedBox(width: 8),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: Colors.grey.withOpacity(0.15),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      locationSummary,
                                      style: const TextStyle(fontSize: 11, color: Colors.grey),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              addr.address,
                              style: const TextStyle(fontSize: 13, color: Colors.grey),
                            ),
                            if (addr.unit.isNotEmpty || addr.postal.isNotEmpty) ...[
                              const SizedBox(height: 2),
                              Text(
                                [
                                  if (addr.unit.isNotEmpty) 'واحد: ${addr.unit}',
                                  if (addr.postal.isNotEmpty) 'کدپستی: ${addr.postal}',
                                ].join(' · '),
                                style: const TextStyle(fontSize: 11, color: Colors.grey),
                              ),
                            ],
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.edit_outlined, size: 20, color: Colors.grey),
                        tooltip: 'ویرایش',
                        onPressed: () => _showAddressDialog(context, addr),
                      ),
                      IconButton(
                        icon: const Icon(Icons.delete_outline, size: 20, color: Colors.grey),
                        tooltip: 'حذف',
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
