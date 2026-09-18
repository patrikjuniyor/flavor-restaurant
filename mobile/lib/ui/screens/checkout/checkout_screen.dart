import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../models/branch_model.dart';
import '../../../models/user_model.dart';
import '../../../repositories/branch_repository.dart';
import '../../../state/auth_state.dart';
import '../../../state/cart_state.dart';
import '../../../state/config_state.dart';
import '../../../state/order_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_card.dart';
import '../../common/flavor_text_field.dart';

/// Checkout Screen: Order Mode Selection, Dining Table / Delivery Address, Payment & Order Submission.
class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _branchRepo = BranchRepository();

  String _orderMode = 'delivery'; // dine_in, takeaway, delivery
  final _nameController = TextEditingController();
  final _mobileController = TextEditingController();
  final _tableController = TextEditingController();
  final _notesController = TextEditingController();

  List<DiningTable> _tables = [];
  int? _selectedTableId;
  SavedAddress? _selectedAddress;
  String _paymentMethod = 'flavor_pay_at_counter';

  @override
  void initState() {
    super.initState();
    _initDefaults();
  }

  void _initDefaults() {
    final auth = context.read<AuthProvider>();
    final config = context.read<ConfigProvider>();
    final branchId = config.activeBranch?.id ?? 0;

    if (auth.user != null) {
      _nameController.text = auth.user!.displayName;
      _mobileController.text = auth.user!.mobile;
      if (auth.user!.savedAddresses.isNotEmpty) {
        _selectedAddress = auth.user!.savedAddresses.first;
      }
    }

    _loadTables(branchId);
  }

  Future<void> _loadTables(int branchId) async {
    try {
      final tables = await _branchRepo.getTables(branchId);
      setState(() => _tables = tables);
    } catch (_) {}
  }

  Future<void> _handleSubmitOrder() async {
    final auth = context.read<AuthProvider>();
    final config = context.read<ConfigProvider>();
    final cart = context.read<CartProvider>();
    final orderProvider = context.read<OrderProvider>();

    final branchId = config.activeBranch?.id ?? 0;

    if (_mobileController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لطفاً شماره موبایل خود را وارد کنید.')),
      );
      return;
    }

    if (_orderMode == 'delivery' && _selectedAddress == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لطفاً آدرس تحویل سفارش را مشخص کنید.')),
      );
      return;
    }

    final res = await orderProvider.submitOrder(
      orderMode: _orderMode,
      branchId: branchId,
      name: _nameController.text.trim(),
      mobile: _mobileController.text.trim(),
      tableId: _selectedTableId,
      tableNumber: _tableController.text.trim(),
      address: _selectedAddress?.toJson(),
      paymentMethod: _paymentMethod,
      notes: _notesController.text.trim(),
    );

    if (res != null && res['ok'] == true) {
      final orderId = int.tryParse(res['order_id']?.toString() ?? '0') ?? 0;
      await cart.clearCart();

      if (mounted) {
        Navigator.pushNamedAndRemoveUntil(
          context,
          AppRoutes.orderTracking,
          (r) => r.isFirst,
          arguments: orderId,
        );
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(orderProvider.errorMessage ?? 'ثبت سفارش با خطا مواجه شد.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final cart = context.watch<CartProvider>();
    final auth = context.watch<AuthProvider>();
    final orderProvider = context.watch<OrderProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('انتخاب نحوه تحویل و پرداخت')),
      bottomNavigationBar: Container(
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
                  const Text('مبلغ قابل پرداخت:', style: TextStyle(fontSize: 12, color: Colors.grey)),
                  Text(
                    CurrencyFormatter.format(cart.total),
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: primary),
                  ),
                ],
              ),
              const SizedBox(width: 20),
              Expanded(
                child: FlavorButton(
                  text: 'ثبت نهایی سفارش',
                  icon: Icons.check,
                  isLoading: orderProvider.isLoading,
                  onPressed: _handleSubmitOrder,
                ),
              ),
            ],
          ),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Order Mode Selector Tabs
            Text(
              'نوع دریافت سفارش',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: ChoiceChip(
                    avatar: const Icon(Icons.motorcycle, size: 18),
                    label: const Center(child: Text('ارسال با پیک')),
                    selected: _orderMode == 'delivery',
                    onSelected: (_) => setState(() => _orderMode = 'delivery'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: ChoiceChip(
                    avatar: const Icon(Icons.shopping_bag_outlined, size: 18),
                    label: const Center(child: Text('تحویل حضوری')),
                    selected: _orderMode == 'takeaway',
                    onSelected: (_) => setState(() => _orderMode = 'takeaway'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: ChoiceChip(
                    avatar: const Icon(Icons.table_restaurant, size: 18),
                    label: const Center(child: Text('سر میز (سالن)')),
                    selected: _orderMode == 'dine_in',
                    onSelected: (_) => setState(() => _orderMode = 'dine_in'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Mode Details (Address vs Table Number)
            if (_orderMode == 'delivery') ...[
              Text(
                'آدرس تحویل سفارش',
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              if (auth.user?.savedAddresses.isNotEmpty == true) ...[
                ...auth.user!.savedAddresses.map(
                  (addr) => RadioListTile<SavedAddress>(
                    title: Text(addr.title, style: const TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: Text(addr.address),
                    value: addr,
                    groupValue: _selectedAddress,
                    onChanged: (val) => setState(() => _selectedAddress = val),
                  ),
                ),
              ] else
                FlavorCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('آدرس تحویل را وارد کنید:'),
                      const SizedBox(height: 8),
                      FlavorTextField(
                        hintText: 'تهران، خیابان، پلاک، زنگ...',
                        onChanged: (val) {
                          _selectedAddress = SavedAddress(id: 'manual', title: 'آدرس تحویل', address: val);
                        },
                      ),
                    ],
                  ),
                ),
            ] else if (_orderMode == 'dine_in') ...[
              Text(
                'شماره میز در سالن',
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              if (_tables.isNotEmpty)
                DropdownButtonFormField<int>(
                  decoration: const InputDecoration(labelText: 'انتخاب میز'),
                  value: _selectedTableId,
                  items: _tables
                      .map(
                        (t) => DropdownMenuItem(
                          value: t.id,
                          child: Text('میز ${t.tableNumber} (${t.capacity} نفره - ${t.section})'),
                        ),
                      )
                      .toList(),
                  onChanged: (val) {
                    setState(() {
                      _selectedTableId = val;
                      _tableController.text = _tables.firstWhere((t) => t.id == val).tableNumber;
                    });
                  },
                )
              else
                FlavorTextField(
                  controller: _tableController,
                  label: 'شماره میز',
                  hintText: 'مثال: ۵',
                  keyboardType: TextInputType.number,
                ),
            ],

            const SizedBox(height: 20),

            // Customer Info Fields
            Text(
              'اطلاعات تماس',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 10),
            FlavorTextField(
              controller: _nameController,
              label: 'نام و نام‌خانوادگی',
              hintText: 'نام تحویل‌گیرنده',
              prefixIcon: const Icon(Icons.person_outline),
            ),
            const SizedBox(height: 12),
            FlavorTextField(
              controller: _mobileController,
              label: 'شماره موبایل',
              hintText: '۰۹۱۲۳۴۵۶۷۸۹',
              keyboardType: TextInputType.phone,
              prefixIcon: const Icon(Icons.phone_android),
            ),
            const SizedBox(height: 12),
            FlavorTextField(
              controller: _notesController,
              label: 'توضیحات سفارش (اختیاری)',
              hintText: 'مثال: لطفاً زنگ طبقه دوم زده شود...',
              maxLines: 2,
            ),

            const SizedBox(height: 24),

            // Payment Method Selector
            Text(
              'نحوه پرداخت',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 10),
            FlavorCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: [
                  RadioListTile<String>(
                    title: const Text('پرداخت در محل / سالن'),
                    subtitle: const Text('پرداخت نقدی یا با دستگاه کارت‌خوان'),
                    value: 'flavor_pay_at_counter',
                    groupValue: _paymentMethod,
                    onChanged: (val) => setState(() => _paymentMethod = val!),
                  ),
                  const Divider(height: 1),
                  RadioListTile<String>(
                    title: const Text('پرداخت آنلاین اینترنتی'),
                    subtitle: const Text('تمامی کارت‌های عضو شتاب'),
                    value: 'online_gateway',
                    groupValue: _paymentMethod,
                    onChanged: (val) => setState(() => _paymentMethod = val!),
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
