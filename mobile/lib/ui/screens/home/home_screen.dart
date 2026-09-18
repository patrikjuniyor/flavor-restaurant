import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/persian_number.dart';
import '../../../state/cart_state.dart';
import '../../../state/config_state.dart';
import '../../../state/favorites_state.dart';
import '../../../state/menu_state.dart';
import '../../common/dish_card.dart';
import '../../common/flavor_card.dart';
import '../../common/loading_shimmer.dart';
import '../../common/section_header.dart';
import '../menu/menu_screen.dart';
import '../orders/orders_screen.dart';
import '../profile/profile_screen.dart';
import '../reservation/reservation_screen.dart';

/// Main Customer Dashboard with Bottom Navigation.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentTab = 0;

  final List<Widget> _tabs = [
    const _HomeFeedView(),
    const MenuScreen(),
    const ReservationScreen(),
    const OrdersScreen(),
    const ProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentTab,
        children: _tabs,
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentTab,
        onTap: (index) => setState(() => _currentTab = index),
        items: [
          const BottomNavigationBarItem(
            icon: Icon(Icons.home_outlined),
            activeIcon: Icon(Icons.home),
            label: 'خانه',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.restaurant_menu_outlined),
            activeIcon: Icon(Icons.restaurant_menu),
            label: 'منو',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.calendar_today_outlined),
            activeIcon: Icon(Icons.calendar_today),
            label: 'رزرو میز',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.receipt_long_outlined),
            activeIcon: Icon(Icons.receipt_long),
            label: 'سفارش‌ها',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'پروفایل',
          ),
        ],
      ),
    );
  }
}

class _HomeFeedView extends StatefulWidget {
  const _HomeFeedView();

  @override
  State<_HomeFeedView> createState() => _HomeFeedViewState();
}

class _HomeFeedViewState extends State<_HomeFeedView> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final config = context.read<ConfigProvider>();
      final branchId = config.activeBranch?.id ?? config.bootstrap?.defaultBranchId ?? 0;
      context.read<MenuProvider>().loadInitial(branchId);
    });
  }

  void _showBranchSelector() {
    final config = context.read<ConfigProvider>();
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'انتخاب شعبه رستوران',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              ...config.branches.map(
                (b) => ListTile(
                  leading: const Icon(Icons.storefront),
                  title: Text(b.name, style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: Text(b.address, maxLines: 1, overflow: TextOverflow.ellipsis),
                  trailing: config.activeBranch?.id == b.id
                      ? Icon(Icons.check_circle, color: Theme.of(context).primaryColor)
                      : null,
                  onTap: () {
                    config.setActiveBranch(b);
                    context.read<MenuProvider>().loadInitial(b.id);
                    Navigator.pop(context);
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final config = context.watch<ConfigProvider>();
    final menu = context.watch<MenuProvider>();
    final favs = context.watch<FavoritesProvider>();
    final cart = context.watch<CartProvider>();

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: GestureDetector(
          onTap: _showBranchSelector,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.location_on, size: 18, color: primary),
              const SizedBox(width: 6),
              Text(
                config.activeBranch?.name ?? config.brand.name,
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
              ),
              const Icon(Icons.keyboard_arrow_down, size: 18),
            ],
          ),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.search),
            onPressed: () => Navigator.pushNamed(context, AppRoutes.search),
          ),
          IconButton(
            icon: const Icon(Icons.notifications_none),
            onPressed: () => Navigator.pushNamed(context, AppRoutes.notifications),
          ),
          // Cart badge
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: const Icon(Icons.shopping_bag_outlined),
                onPressed: () => Navigator.pushNamed(context, AppRoutes.cart),
              ),
              if (cart.itemCount > 0)
                Positioned(
                  top: 8,
                  left: 8,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: primary,
                      shape: BoxShape.circle,
                    ),
                    constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                    child: Text(
                      PersianNumber.toPersian(cart.itemCount),
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          final branchId = config.activeBranch?.id ?? 0;
          await menu.loadInitial(branchId);
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Hero Promotional Banner
              Container(
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [primary, primary.withOpacity(0.8)],
                    begin: Alignment.topRight,
                    end: Alignment.bottomLeft,
                  ),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.2),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'سفارش آنلاین و تحویل سریع',
                              style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            config.brand.name,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            config.brand.description,
                            style: const TextStyle(color: Colors.white70, fontSize: 12),
                            maxLines: 2,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    const Icon(Icons.fastfood, size: 54, color: Colors.white),
                  ],
                ),
              ),

              // Categories Grid
              SectionHeader(
                title: 'دسته‌بندی‌ها',
                onActionTap: () => Navigator.pushNamed(context, AppRoutes.menu),
              ),
              if (menu.isLoading)
                const LoadingShimmer(width: double.infinity, height: 80, margin: EdgeInsets.symmetric(horizontal: 16))
              else
                SizedBox(
                  height: 95,
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    scrollDirection: Axis.horizontal,
                    itemCount: menu.categories.length,
                    itemBuilder: (context, index) {
                      final cat = menu.categories[index];
                      return GestureDetector(
                        onTap: () => Navigator.pushNamed(context, AppRoutes.menu, arguments: cat.id),
                        child: Container(
                          width: 80,
                          margin: const EdgeInsets.symmetric(horizontal: 6),
                          child: Column(
                            children: [
                              Container(
                                width: 56,
                                height: 56,
                                decoration: BoxDecoration(
                                  color: theme.cardColor,
                                  borderRadius: BorderRadius.circular(16),
                                  border: Border.all(color: theme.dividerColor),
                                ),
                                child: Icon(Icons.restaurant, color: primary, size: 28),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                cat.name,
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),

              // Special Offers Horizontal List
              if (menu.specialOffers.isNotEmpty) ...[
                const SizedBox(height: 12),
                SectionHeader(
                  title: 'پیشنهادات ویژه و تخفیف‌ها',
                  onActionTap: () => Navigator.pushNamed(context, AppRoutes.menu),
                ),
                SizedBox(
                  height: 250,
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    scrollDirection: Axis.horizontal,
                    itemCount: menu.specialOffers.length,
                    itemBuilder: (context, index) {
                      final dish = menu.specialOffers[index];
                      return Container(
                        width: 220,
                        margin: const EdgeInsets.symmetric(horizontal: 6),
                        child: DishCard(
                          dish: dish,
                          isFavorite: favs.isFavorite(dish.id),
                          onToggleFavorite: () => favs.toggleFavorite(dish),
                          onTap: () => Navigator.pushNamed(context, AppRoutes.dishDetail, arguments: dish.id),
                          onAddToCart: () {
                            if (dish.hasModifiers) {
                              Navigator.pushNamed(context, AppRoutes.dishDetail, arguments: dish.id);
                            } else {
                              cart.addToCart(dish);
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text('«${dish.name}» به سبد خرید افزوده شد.')),
                              );
                            }
                          },
                        ),
                      );
                    },
                  ),
                ),
              ],

              // Table Reservation Quick CTA
              FlavorCard(
                margin: const EdgeInsets.all(16),
                onTap: () => Navigator.pushNamed(context, AppRoutes.reservation),
                color: theme.brightness == Brightness.dark ? const Color(0xFF1E293B) : const Color(0xFFEFF6FF),
                border: const BorderSide(color: Color(0xFF3B82F6), width: 1),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF3B82F6).withOpacity(0.15),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.table_restaurant, color: Color(0xFF3B82F6), size: 28),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: const [
                          Text(
                            'رزرو آنلاین میز در سالن و تراس',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'میز دلخواه خود را بدون معطلی آنلاین رزرو کنید.',
                            style: TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_left, color: Color(0xFF3B82F6)),
                  ],
                ),
              ),

              // Featured / Popular Menu Items
              SectionHeader(
                title: 'غذاهای محبوب رستوران',
                onActionTap: () => Navigator.pushNamed(context, AppRoutes.menu),
              ),
              if (menu.isLoading)
                Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: LoadingShimmer.dishGrid(count: 3),
                )
              else
                ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: menu.featuredDishes.length,
                  itemBuilder: (context, index) {
                    final dish = menu.featuredDishes[index];
                    return DishCard(
                      dish: dish,
                      isFavorite: favs.isFavorite(dish.id),
                      onToggleFavorite: () => favs.toggleFavorite(dish),
                      onTap: () => Navigator.pushNamed(context, AppRoutes.dishDetail, arguments: dish.id),
                      onAddToCart: () {
                        if (dish.hasModifiers) {
                          Navigator.pushNamed(context, AppRoutes.dishDetail, arguments: dish.id);
                        } else {
                          cart.addToCart(dish);
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text('«${dish.name}» به سبد خرید افزوده شد.')),
                          );
                        }
                      },
                    );
                  },
                ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}
