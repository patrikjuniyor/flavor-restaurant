import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/utils/jalali_date.dart';
import '../../../core/utils/persian_number.dart';
import '../../../core/utils/validators.dart';
import '../../../state/auth_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_text_field.dart';
import '../../common/jalali_date_picker.dart';

/// Screen for updating User Profile details.
class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late TextEditingController _nameController;
  late TextEditingController _emailController;
  late TextEditingController _mobileController;
  late TextEditingController _birthdateController;
  JalaliDate? _selectedBirthdate;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _nameController = TextEditingController(text: user?.displayName ?? '');
    _emailController = TextEditingController(text: user?.email ?? '');
    _mobileController = TextEditingController(
      text: user != null && user.mobile.isNotEmpty
          ? PersianNumber.toPersian(user.mobile)
          : '',
    );

    // Initial birthdate: parse from stored ISO or keep empty
    final initialIso = user?.birthdate ?? '';
    _selectedBirthdate = JalaliDate.tryParseIsoGregorian(initialIso);
    _birthdateController = TextEditingController(
      text: _selectedBirthdate != null ? _selectedBirthdate!.formatNumeric() : '',
    );
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _mobileController.dispose();
    _birthdateController.dispose();
    super.dispose();
  }

  Future<void> _pickBirthdate() async {
    final nowJ = JalaliDate.fromDateTime(DateTime.now());
    final initial = _selectedBirthdate ?? JalaliDate(nowJ.year - 25, 1, 1);

    await showDialog<void>(
      context: context,
      builder: (ctx) => JalaliDatePickerDialog(
        initialDate: initial,
        onDateSelected: (date) {
          setState(() {
            _selectedBirthdate = date;
            _birthdateController.text = date.formatNumeric();
          });
        },
      ),
    );
  }

  void _clearBirthdate() {
    setState(() {
      _selectedBirthdate = null;
      _birthdateController.clear();
    });
  }

  Future<void> _handleSave() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final auth = context.read<AuthProvider>();
    final cleanedMobile = PersianNumber.cleanMobile(_mobileController.text.trim());
    final isoBirthdate = _selectedBirthdate?.toIsoGregorianString() ?? '';

    final ok = await auth.updateProfile(
      displayName: _nameController.text.trim(),
      email: _emailController.text.trim(),
      mobile: cleanedMobile.isNotEmpty ? cleanedMobile : null,
      birthdate: isoBirthdate,
    );

    if (ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('پروفایل با موفقیت بروزرسانی شد.')),
      );
      Navigator.pop(context);
    } else if (!ok && mounted) {
      final err = auth.errorMessage ?? 'بروزرسانی پروفایل با خطا مواجه شد.';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(err), backgroundColor: Colors.redAccent),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('ویرایش پروفایل')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              FlavorTextField(
                controller: _nameController,
                label: 'نام و نام‌خانوادگی',
                prefixIcon: const Icon(Icons.person_outline),
                validator: (val) => Validators.validateDisplayName(val),
              ),
              const SizedBox(height: 16),
              FlavorTextField(
                controller: _mobileController,
                label: 'شماره موبایل',
                keyboardType: TextInputType.phone,
                prefixIcon: const Icon(Icons.phone_iphone_outlined),
                hintText: '۰۹۱۲۳۴۵۶۷۸۹',
                validator: (val) => Validators.validateMobile(val, required: true),
              ),
              const SizedBox(height: 16),
              FlavorTextField(
                controller: _emailController,
                label: 'پست الکترونیک (ایمیل)',
                keyboardType: TextInputType.emailAddress,
                prefixIcon: const Icon(Icons.email_outlined),
                hintText: 'name@example.com (اختیاری)',
                validator: (val) => Validators.validateEmail(val, required: false),
              ),
              const SizedBox(height: 16),
              FlavorTextField(
                controller: _birthdateController,
                label: 'تاریخ تولد (شمسی)',
                readOnly: true,
                onTap: _pickBirthdate,
                prefixIcon: const Icon(Icons.cake_outlined),
                hintText: 'انتخاب تاریخ شمسی (اختیاری)',
                suffixIcon: _selectedBirthdate != null
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 20),
                        onPressed: _clearBirthdate,
                      )
                    : const Icon(Icons.calendar_today_outlined, size: 20),
              ),
              if (_selectedBirthdate != null) ...[
                const SizedBox(height: 4),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4.0),
                  child: Text(
                    _selectedBirthdate!.formatFull(includeWeekDay: true),
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).primaryColor,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 32),
              FlavorButton(
                text: 'ذخیره تغییرات',
                isLoading: auth.isLoading,
                onPressed: _handleSave,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
