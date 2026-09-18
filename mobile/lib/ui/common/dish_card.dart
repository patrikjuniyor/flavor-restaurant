import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import '../../core/utils/currency_formatter.dart';
import '../../core/utils/persian_number.dart';
import '../../models/dish_model.dart';
import 'flavor_card.dart';

/// Restaurant Food Dish Card for menus, featured lists, and search results.
class DishCard extends StatelessWidget {
  final DishModel dish;
  final VoidCallback? onTap;
  final VoidCallback? onAddToCart;
  final bool isFavorite;
  final VoidCallback? onToggleFavorite;

  const DishCard({
    super.key,
    required this.dish,
    this.onTap,
    this.onAddToCart,
    this.isFavorite = false,
    this.onToggleFavorite,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final primary = theme.primaryColor;

    final isAvailable = dish.available;

    return FlavorCard(
      onTap: onTap,
      padding: EdgeInsets.zero,
      margin: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Dish Image + Badges
          Stack(
            children: [
              ClipRRect(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                child: AspectRatio(
                  aspectRatio: 16 / 9,
                  child: dish.image.isNotEmpty
                      ? CachedNetworkImage(
                          imageUrl: dish.image,
                          fit: BoxFit.cover,
                          placeholder: (_, __) => Container(
                            color: isDark ? const Color(0xFF333333) : const Color(0xFFF3F4F6),
                            child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
                          ),
                          errorWidget: (_, __, ___) => Container(
                            color: isDark ? const Color(0xFF333333) : const Color(0xFFF3F4F6),
                            child: const Icon(Icons.restaurant, size: 40, color: Colors.grey),
                          ),
                        )
                      : Container(
                          color: isDark ? const Color(0xFF333333) : const Color(0xFFF3F4F6),
                          child: const Icon(Icons.restaurant, size: 40, color: Colors.grey),
                        ),
                ),
              ),

              // Category Badge
              if (dish.categories.isNotEmpty)
                Positioned(
                  top: 10,
                  right: 10,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.7),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      dish.categories.first.name,
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white),
                    ),
                  ),
                ),

              // Unavailable Badge
              if (!isAvailable)
                Positioned.fill(
                  child: Container(
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.6),
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                    ),
                    child: const Center(
                      child: Chip(
                        label: Text('اتمام موجودی', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                        backgroundColor: Colors.red,
                      ),
                    ),
                  ),
                ),

              // Favorite Icon Button
              if (onToggleFavorite != null)
                Positioned(
                  top: 10,
                  left: 10,
                  child: GestureDetector(
                    onTap: onToggleFavorite,
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.6),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        isFavorite ? Icons.favorite : Icons.favorite_border,
                        size: 18,
                        color: isFavorite ? Colors.redAccent : Colors.white,
                      ),
                    ),
                  ),
                ),
            ],
          ),

          // Dish Info & Actions
          Padding(
            padding: const EdgeInsets.all(14.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        dish.name,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (dish.prepTime > 0)
                      Row(
                        children: [
                          const Icon(Icons.access_time, size: 14, color: Colors.grey),
                          const SizedBox(width: 4),
                          Text(
                            '${PersianNumber.toPersian(dish.prepTime)} د',
                            style: theme.textTheme.bodySmall,
                          ),
                        ],
                      ),
                  ],
                ),
                if (dish.shortDesc.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    dish.shortDesc,
                    style: theme.textTheme.bodySmall?.copyWith(height: 1.4),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    // Price
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (dish.onSale && dish.regularPrice > 0)
                          Text(
                            CurrencyFormatter.format(dish.regularPrice),
                            style: const TextStyle(
                              fontSize: 12,
                              color: Colors.grey,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                        Text(
                          CurrencyFormatter.format(dish.price),
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: primary,
                          ),
                        ),
                      ],
                    ),

                    // Add button
                    ElevatedButton.icon(
                      onPressed: isAvailable ? onAddToCart : null,
                      icon: const Icon(Icons.add, size: 16),
                      label: Text(dish.hasModifiers ? 'انتخاب' : 'افزودن'),
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        visualDensity: VisualDensity.compact,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
