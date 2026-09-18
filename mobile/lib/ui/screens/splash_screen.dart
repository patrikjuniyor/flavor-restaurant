import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/routes.dart';
import '../../state/auth_state.dart';
import '../../state/cart_state.dart';
import '../../state/config_state.dart';
import '../../state/favorites_state.dart';

/// Splash Screen: Loads bootstrap configuration, verifies tokens, and initializes app state.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  late AnimationController _animController;
  late Animation<double> _fadeAnim;

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    );
    _fadeAnim = CurvedAnimation(parent: _animController, curve: Curves.easeIn);
    _animController.forward();

    _initializeApp();
  }

  Future<void> _initializeApp() async {
    final config = context.read<ConfigProvider>();
    final auth = context.read<AuthProvider>();
    final cart = context.read<CartProvider>();
    final favs = context.read<FavoritesProvider>();

    await Future.wait([
      config.loadConfig(),
      auth.checkAuth(),
      cart.loadCart(),
      favs.loadFavorites(),
      Future.delayed(const Duration(milliseconds: 1500)),
    ]);

    if (!mounted) return;
    Navigator.pushReplacementNamed(context, AppRoutes.home);
  }

  @override
  void dispose() {
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: Center(
        child: FadeTransition(
          opacity: _fadeAnim,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  color: primary,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: primary.withOpacity(0.3),
                      blurRadius: 20,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.restaurant_menu,
                  size: 54,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 24),
              Consumer<ConfigProvider>(
                builder: (_, config, __) => Text(
                  config.brand.name,
                  style: theme.textTheme.displaySmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Consumer<ConfigProvider>(
                builder: (_, config, __) => Text(
                  config.brand.description,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: Colors.grey,
                  ),
                ),
              ),
              const SizedBox(height: 48),
              SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 2.5,
                  valueColor: AlwaysStoppedAnimation<Color>(primary),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
