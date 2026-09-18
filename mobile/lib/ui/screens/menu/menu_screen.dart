import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../core/utils/persian_number.dart';
import '../../../models/category_model.dart';
import '../../../state/cart_state.dart';
import '../../../state/config_state.dart';
import '../../../state/favorites_state.dart';
import '../../../state/menu_state.dart';
import '../../common/category_chip.dart';
import '../../common/dish_card.dart';
import '../../common/empty_state.dart';
import '../../common/loading_shimmer.dart';

/// Full Menu Screen with Category tabs, Shift filter, and Floating Cart.
class MenuScreen extends StatefulWidget {
  final int initialCategoryId;

  const MenuScreen({super.key, this.initialCategoryId = 0});

  @override
  State<MenuScreen> createState() => _MenuScreenState();
}

class _MenuScreenState extends State<MenuScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final config = context.read<ConfigProvider>();
      final branchId = config.activeBranch?.id ?? 0;
      final menu = context.read<MenuProvider>();
      if (widget.initialCategoryId > 0) {
        menu.selectCategory(widget.initialCategoryId, branchId);
      } else if (menu.dishes.isEmpty) {
        menu.loadInitial(branchId);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final config = context.watch<ConfigProvider>();
    final menu = context.watch<MenuProvider>();
    final cart = context.watch<CartProvider>();
    final favs = context.watch<FavoritesProvider>();
    final branchId = config.activeBranch?.id ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('منوی کامل رستوران'),
        actions: [
          IconButton(
            icon: const Icon(Icons.search),
            onPressed: () => Navigator.pushNamed(context, AppRoutes.search),
          ),
        ],
      ),
      floatingActionButton: cart.itemCount > 0
          ? FloatingActionButton.extended(
              onPressed: () => Navigator.pushNamed(context, AppRoutes.cart),
              backgroundColor: primary,
              icon: const Icon(Icons.shopping_bag, color: Colors.white),
              label: Text(
                'مشاهده سبد خرید (${PersianNumber.toPersian(cart.itemCount)})',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
              ),
            )
          : null,
      body: Column(
        children: [
          // Category horizontal scroll bar
          Container(
            padding: const EdgeInsets.symmetric(vertical: 10),
            decoration: BoxDecoration(
              color: theme.appBarTheme.backgroundColor,
              border: Border(bottom: BorderSide(color: theme.dividerColor)),
            ),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Row(
                children: [
                  CategoryChip(
                    category: const CategoryModel(id: 0, name: 'همه غذاها', slug: 'all'),
                    isSelected: menu.selectedCategoryId == 0,
                    onTap: () => menu.selectCategory(0, branchId),
                  ),
                  ...menu.categories.map(
                    (cat) => CategoryChip(
                      category: cat,
                      isSelected: menu.selectedCategoryId == cat.id,
                      onTap: () => menu.selectCategory(cat.id, branchId),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Dishes List
          Expanded(
            child: menu.isLoading
                ? Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: LoadingShimmer.dishGrid(count: 4),
                  )
                : menu.dishes.isEmpty
                    ? const EmptyState(
                        title: 'غذایی در این دسته یافت نشد',
                        description: 'لطفاً دسته یا فیلتر دیگری را انتخاب کنید.',
                        icon: Icons.restaurant,
                      )
                    : RefreshIndicator(
                        onRefresh: () => menu.selectCategory(menu.selectedCategoryId, branchId),
                        child: ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: menu.dishes.length,
                          itemBuilder: (context, index) {
                            final dish = menu.dishes[index];
                            return DishCard(
                              dish: dish,
                              isFavorite: favs.isFavorite(dish.id),
                              onToggleFavorite: () => favs.toggleFavorite(dish),
                              onTap: () => Navigator.pushNamed(
                                context,
                                AppRoutes.dishDetail,
                                arguments: dish.id,
                              ),
                              onAddToCart: () {
                                if (dish.hasModifiers) {
                                  Navigator.pushNamed(
                                    context,
                                    AppRoutes.dishDetail,
                                    arguments: dish.id,
                                  );
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
                      ),
          ),
        ],
      ),
    );
  }
}
