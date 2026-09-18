import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/persian_number.dart';
import '../../../state/auth_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_text_field.dart';

/// Screen for initiating Mobile OTP Login / Registration.
class OtpRequestScreen extends StatefulWidget {
  const OtpRequestScreen({super.key});

  @override
  State<OtpRequestScreen> createState() => _OtpRequestScreenState();
}

class _OtpRequestScreenState extends State<OtpRequestScreen> {
  final _mobileController = TextEditingController();
  String? _clientError;

  Future<void> _handleSubmit() async {
    final raw = _mobileController.text.trim();
    final mobile = PersianNumber.cleanMobile(raw);

    if (mobile.length != 11 || !mobile.startsWith('09')) {
      setState(() => _clientError = 'شماره موبایل نامعتبر است. نمونه: ۰۹۱۲۳۴۵۶۷۸۹');
      return;
    }

    setState(() => _clientError = null);
    final auth = context.read<AuthProvider>();
    final success = await auth.requestOtp(mobile);

    if (success && mounted) {
      Navigator.pushNamed(context, AppRoutes.otpVerify, arguments: mobile);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('ورود یا ثبت‌نام'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 20),
              Icon(Icons.phone_iphone, size: 64, color: theme.primaryColor),
              const SizedBox(height: 16),
              Text(
                'شماره موبایل خود را وارد کنید',
                textAlign: TextAlign.center,
                style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'کد تأیید پیامکی برای ورود یا عضویت به این شماره ارسال خواهد شد.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(color: Colors.grey),
              ),
              const SizedBox(height: 32),
              FlavorTextField(
                controller: _mobileController,
                label: 'شماره موبایل',
                hintText: '۰۹۱۲۳۴۵۶۷۸۹',
                keyboardType: TextInputType.phone,
                maxLength: 11,
                errorText: _clientError ?? auth.errorMessage,
                prefixIcon: const Icon(Icons.phone_android),
                autofocus: true,
              ),
              const SizedBox(height: 24),
              FlavorButton(
                text: 'دریافت کد تأیید',
                isLoading: auth.isLoading,
                onPressed: _handleSubmit,
              ),
              const SizedBox(height: 24),
              Text(
                'با ورود به برنامه، شرایط و قوانین استفاده از خدمات رستوران را می‌پذیرید.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey, height: 1.5),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
