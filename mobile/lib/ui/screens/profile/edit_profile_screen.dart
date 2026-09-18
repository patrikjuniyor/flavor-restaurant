import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../state/auth_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_text_field.dart';

/// Screen for updating User Profile details.
class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  late TextEditingController _nameController;
  late TextEditingController _emailController;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _nameController = TextEditingController(text: user?.displayName ?? '');
    _emailController = TextEditingController(text: user?.email ?? '');
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('ویرایش پروفایل')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FlavorTextField(
              controller: _nameController,
              label: 'نام و نام‌خانوادگی',
              prefixIcon: const Icon(Icons.person_outline),
            ),
            const SizedBox(height: 16),
            FlavorTextField(
              controller: _emailController,
              label: 'پست الکترونیک (ایمیل)',
              keyboardType: TextInputType.emailAddress,
              prefixIcon: const Icon(Icons.email_outlined),
            ),
            const SizedBox(height: 32),
            FlavorButton(
              text: 'ذخیره تغییرات',
              isLoading: auth.isLoading,
              onPressed: () async {
                final ok = await auth.updateProfile(
                  displayName: _nameController.text.trim(),
                  email: _emailController.text.trim(),
                );
                if (ok && mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('پروفایل با موفقیت بروزرسانی شد.')),
                  );
                  Navigator.pop(context);
                }
              },
            ),
          ],
        ),
      ),
    );
  }
}
