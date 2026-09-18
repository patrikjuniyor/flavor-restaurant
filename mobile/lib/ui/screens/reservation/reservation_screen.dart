import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/persian_number.dart';
import '../../../models/branch_model.dart';
import '../../../state/auth_state.dart';
import '../../../state/config_state.dart';
import '../../../state/reservation_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_card.dart';
import '../../common/flavor_text_field.dart';
import '../../common/jalali_date_picker.dart';

/// Table Reservation Booking Screen with Jalali Calendar and Slot Grid.
class ReservationScreen extends StatefulWidget {
  const ReservationScreen({super.key});

  @override
  State<ReservationScreen> createState() => _ReservationScreenState();
}

class _ReservationScreenState extends State<ReservationScreen> {
  final _nameController = TextEditingController();
  final _mobileController = TextEditingController();
  final _requestsController = TextEditingController();

  String? _selectedTimeSlot;

  @override
  void initState() {
    super.initState();
    _initDefaults();
  }

  void _initDefaults() {
    final auth = context.read<AuthProvider>();
    final config = context.read<ConfigProvider>();
    final res = context.read<ReservationProvider>();

    if (auth.user != null) {
      _nameController.text = auth.user!.displayName;
      _mobileController.text = auth.user!.mobile;
    }

    final branchId = config.activeBranch?.id ?? 0;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      res.loadSlots(branchId);
    });
  }

  void _openDatePicker() {
    final res = context.read<ReservationProvider>();
    final config = context.read<ConfigProvider>();
    final branchId = config.activeBranch?.id ?? 0;

    showDialog(
      context: context,
      builder: (ctx) => JalaliDatePickerDialog(
        initialDate: res.selectedDate,
        onDateSelected: (date) {
          res.setDate(date, branchId);
          setState(() => _selectedTimeSlot = null);
        },
      ),
    );
  }

  Future<void> _handleBookTable() async {
    final res = context.read<ReservationProvider>();
    final config = context.read<ConfigProvider>();
    final branchId = config.activeBranch?.id ?? 0;

    if (_selectedTimeSlot == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لطفاً ساعت رزرو را انتخاب کنید.')),
      );
      return;
    }

    if (_mobileController.text.trim().isEmpty || _nameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('نام و شماره تماس برای رزرو الزامی است.')),
      );
      return;
    }

    final success = await res.bookTable(
      branchId: branchId,
      time: _selectedTimeSlot!,
      mobile: _mobileController.text.trim(),
      name: _nameController.text.trim(),
      requests: _requestsController.text.trim(),
    );

    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('درخواست رزرو شما با موفقیت ثبت شد و پیامک تأیید ارسال گردید.')),
      );
      Navigator.pushNamed(context, AppRoutes.reservationHistory);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.errorMessage ?? 'ثبت رزرو با خطا مواجه شد.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final config = context.watch<ConfigProvider>();
    final res = context.watch<ReservationProvider>();
    final branchId = config.activeBranch?.id ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('رزرو آنلاین میز'),
        actions: [
          IconButton(
            icon: const Icon(Icons.history),
            tooltip: 'سوابق رزرو',
            onPressed: () => Navigator.pushNamed(context, AppRoutes.reservationHistory),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Branch Picker
            Text('شعبه رستوران', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            FlavorCard(
              child: DropdownButtonHideUnderline(
                child: DropdownButton<BranchModel>(
                  isExpanded: true,
                  value: config.activeBranch,
                  items: config.branches
                      .map((b) => DropdownMenuItem(value: b, child: Text(b.name, style: const TextStyle(fontWeight: FontWeight.bold))))
                      .toList(),
                  onChanged: (b) {
                    if (b != null) {
                      config.setActiveBranch(b);
                      res.loadSlots(b.id);
                    }
                  },
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Date Picker Card
            Text('تاریخ رزرو (تقویم خورشیدی)', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            FlavorCard(
              onTap: _openDatePicker,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Icon(Icons.calendar_month, color: primary),
                      const SizedBox(width: 12),
                      Text(
                        res.selectedDate.formatFull(),
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                    ],
                  ),
                  const Icon(Icons.arrow_drop_down),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Party Size Counter
            Text('تعداد نفرات', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            FlavorCard(
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    '${PersianNumber.toPersian(res.partySize)} نفر',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  Row(
                    children: [
                      IconButton(
                        icon: const Icon(Icons.remove_circle_outline),
                        onPressed: () {
                          if (res.partySize > 1) {
                            res.setPartySize(res.partySize - 1, branchId);
                          }
                        },
                      ),
                      IconButton(
                        icon: const Icon(Icons.add_circle_outline),
                        onPressed: () {
                          if (res.partySize < 30) {
                            res.setPartySize(res.partySize + 1, branchId);
                          }
                        },
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Dining Section Selector
            Text('موقعیت میز در رستوران', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: ChoiceChip(
                    label: const Center(child: Text('سالن اصلی')),
                    selected: res.section == 'indoor',
                    onSelected: (_) => res.setSection('indoor', branchId),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: ChoiceChip(
                    label: const Center(child: Text('تراس / روباز')),
                    selected: res.section == 'outdoor',
                    onSelected: (_) => res.setSection('outdoor', branchId),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: ChoiceChip(
                    label: const Center(child: Text('جایگاه VIP')),
                    selected: res.section == 'vip',
                    onSelected: (_) => res.setSection('vip', branchId),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Available Time Slots Grid
            Text('ساعت رزرو', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 10),
            if (res.isLoading)
              const Center(child: CircularProgressIndicator())
            else if (res.availableSlots.isEmpty)
              const Text('اسلات زمانی در این تاریخ در دسترس نیست.', style: TextStyle(color: Colors.grey))
            else
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: res.availableSlots.map((slot) {
                  final isSelected = _selectedTimeSlot == slot.time;
                  final timeLabel = PersianNumber.toPersian(slot.time.substring(0, 5));

                  return ChoiceChip(
                    label: Text(timeLabel),
                    selected: isSelected,
                    onSelected: slot.available
                        ? (_) => setState(() => _selectedTimeSlot = slot.time)
                        : null,
                  );
                }).toList(),
              ),
            const SizedBox(height: 24),

            // Customer Contact Info
            Text('اطلاعات رزروکننده', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 10),
            FlavorTextField(
              controller: _nameController,
              label: 'نام و نام‌خانوادگی',
              hintText: 'نام سرپرست رزرو',
              prefixIcon: const Icon(Icons.person_outline),
            ),
            const SizedBox(height: 12),
            FlavorTextField(
              controller: _mobileController,
              label: 'شماره موبایل جهت ارسال پیامک تأیید',
              hintText: '۰۹۱۲۳۴۵۶۷۸۹',
              keyboardType: TextInputType.phone,
              prefixIcon: const Icon(Icons.phone_android),
            ),
            const SizedBox(height: 12),
            FlavorTextField(
              controller: _requestsController,
              label: 'درخواست‌های ویژه (اختیاری)',
              hintText: 'تولد، سالگرد، صندلی کودک، میز کنار پنجره...',
              maxLines: 2,
            ),
            const SizedBox(height: 32),

            // Submit Button
            FlavorButton(
              text: 'تأیید و رزرو نهایی میز',
              icon: Icons.check,
              isLoading: res.isLoading,
              onPressed: _handleBookTable,
            ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }
}
