import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/persian_number.dart';
import '../../../state/auth_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_text_field.dart';

/// Screen for verifying SMS OTP code and completing login.
class OtpVerifyScreen extends StatefulWidget {
  final String mobile;

  const OtpVerifyScreen({super.key, required this.mobile});

  @override
  State<OtpVerifyScreen> createState() => _OtpVerifyScreenState();
}

class _OtpVerifyScreenState extends State<OtpVerifyScreen> {
  final _codeController = TextEditingController();
  final _nameController = TextEditingController();

  Future<void> _handleVerify() async {
    final code = _codeController.text.trim();
    if (code.length < 4) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لطفاً کد تأیید را کامل وارد کنید.')),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    final success = await auth.verifyOtp(
      widget.mobile,
      code,
      name: _nameController.text.trim(),
    );

    if (success && mounted) {
      Navigator.pushNamedAndRemoveUntil(context, AppRoutes.home, (route) => false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();

    final formattedMobile = PersianNumber.toPersian(widget.mobile);

    return Scaffold(
      appBar: AppBar(
        title: const Text('تأیید کد ورود'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 16),
              Icon(Icons.sms_outlined, size: 60, color: theme.primaryColor),
              const SizedBox(height: 16),
              Text(
                'کد ارسال شده به $formattedMobile را وارد کنید',
                textAlign: TextAlign.center,
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Text(
                  'ویرایش شماره موبایل',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: theme.primaryColor,
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(height: 32),
              FlavorTextField(
                controller: _codeController,
                label: 'کد تأیید پیامک شده',
                hintText: '----',
                keyboardType: TextInputType.number,
                maxLength: 6,
                errorText: auth.errorMessage,
                prefixIcon: const Icon(Icons.lock_outline),
                autofocus: true,
              ),
              const SizedBox(height: 16),
              FlavorTextField(
                controller: _nameController,
                label: 'نام و نام‌خانوادگی (اختیاری)',
                hintText: 'نام شما جهت صدور فاکتور',
                prefixIcon: const Icon(Icons.person_outline),
              ),
              const SizedBox(height: 24),
              FlavorButton(
                text: 'تأیید و ورود',
                isLoading: auth.isLoading,
                onPressed: _handleVerify,
              ),
              const SizedBox(height: 24),
              Center(
                child: auth.canResendOtp
                    ? TextButton.icon(
                        icon: const Icon(Icons.replay),
                        label: const Text('ارسال مجدد کد'),
                        onPressed: () => auth.requestOtp(widget.mobile),
                      )
                    : Text(
                        'ارسال مجدد کد تا ${PersianNumber.toPersian(auth.countdown)} ثانیه دیگر',
                        style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
