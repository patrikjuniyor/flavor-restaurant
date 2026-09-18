import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../state/cart_state.dart';
import '../../../state/favorites_state.dart';
import '../../../state/menu_state.dart';
import '../../common/dish_card.dart';
import '../../common/empty_state.dart';

/// Favorite Dishes Screen.
class FavoritesScreen extends StatelessWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final favs = context.watch<FavoritesProvider>();
    final menu = context.watch<MenuProvider>();
    final cart = context.watch<CartProvider>();

    final favoriteDishes = menu.dishes.where((d) => favs.isFavorite(d.id)).toList();

    return Scaffold(
      appBar: AppBar(title: const Text('غذاهای مورد علاقه')),
      body: favoriteDishes.isEmpty
          ? const EmptyState(
              title: 'لیست علاقه‌مندی‌ها خالی است',
              description: 'غذاهای مورد علاقه خود را با زدن علامت قلب ذخیره کنید.',
              icon: Icons.favorite_border,
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: favoriteDishes.length,
              itemBuilder: (context, index) {
                final dish = favoriteDishes[index];
                return DishCard(
                  dish: dish,
                  isFavorite: true,
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
    );
  }
}
