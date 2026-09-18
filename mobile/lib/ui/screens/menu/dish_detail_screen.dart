import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/persian_number.dart';
import '../../../models/dish_model.dart';
import '../../../models/review_model.dart';
import '../../../repositories/menu_repository.dart';
import '../../../repositories/review_repository.dart';
import '../../../state/cart_state.dart';
import '../../../state/config_state.dart';
import '../../../state/favorites_state.dart';
import '../../common/flavor_button.dart';
import '../../common/flavor_card.dart';
import '../../common/flavor_text_field.dart';

/// Comprehensive Dish Details Screen with Customization Modifiers and Reviews.
class DishDetailScreen extends StatefulWidget {
  final int dishId;

  const DishDetailScreen({super.key, required this.dishId});

  @override
  State<DishDetailScreen> createState() => _DishDetailScreenState();
}

class _DishDetailScreenState extends State<DishDetailScreen> {
  final _menuRepo = MenuRepository();
  final _reviewRepo = ReviewRepository();

  DishModel? _dish;
  List<ReviewModel> _reviews = [];
  bool _isLoading = true;
  String? _errorMessage;

  // Customization selection state
  final Set<String> _selectedModifierIds = {};
  final _notesController = TextEditingController();
  int _quantity = 1;

  @override
  void initState() {
    super.initState();
    _loadDishDetails();
  }

  Future<void> _loadDishDetails() async {
    final branchId = context.read<ConfigProvider>().activeBranch?.id ?? 0;
    try {
      final dish = await _menuRepo.getDish(widget.dishId, branchId: branchId);
      final reviews = await _reviewRepo.getDishReviews(widget.dishId);

      // Pre-select default modifiers
      for (final group in dish.modifierGroups) {
        for (final opt in group.options) {
          if (opt.isDefault) {
            _selectedModifierIds.add(opt.id);
          }
        }
      }

      setState(() {
        _dish = dish;
        _reviews = reviews;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = e.toString();
        _isLoading = false;
      });
    }
  }

  int _calculateLivePrice() {
    if (_dish == null) return 0;
    int base = _dish!.price;
    int extra = 0;

    for (final group in _dish!.modifierGroups) {
      for (final opt in group.options) {
        if (_selectedModifierIds.contains(opt.id)) {
          extra += opt.price;
        }
      }
    }

    return (base + extra) * _quantity;
  }

  void _handleAddToCart() {
    if (_dish == null) return;
    final cart = context.read<CartProvider>();

    cart.addToCart(
      _dish!,
      quantity: _quantity,
      modifierIds: _selectedModifierIds.toList(),
      instructions: _notesController.text.trim(),
    );

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('«${_dish!.name}» به سبد خرید اضافه شد.')),
    );
    Navigator.pop(context);
  }

  void _showAddReviewDialog() {
    int rating = 5;
    final commentCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('ثبت دیدگاه و امتیاز'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(
                  5,
                  (index) => IconButton(
                    icon: Icon(
                      index < rating ? Icons.star : Icons.star_border,
                      color: Colors.amber,
                    ),
                    onPressed: () => setDialogState(() => rating = index + 1),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              FlavorTextField(
                controller: commentCtrl,
                label: 'متن دیدگاه شما',
                hintText: 'تجربه شما از کیفیت و طعم این غذا...',
                maxLines: 3,
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('انصراف'),
            ),
            ElevatedButton(
              onPressed: () async {
                if (commentCtrl.text.trim().isEmpty) return;
                await _reviewRepo.submitReview(
                  widget.dishId,
                  rating: rating,
                  comment: commentCtrl.text.trim(),
                );
                Navigator.pop(ctx);
                _loadDishDetails();
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('دیدگاه شما با موفقیت ثبت شد.')),
                );
              },
              child: const Text('ثبت نظر'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final primary = theme.primaryColor;
    final favs = context.watch<FavoritesProvider>();

    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_dish == null) {
      return Scaffold(
        appBar: AppBar(),
        body: Center(child: Text(_errorMessage ?? 'غذای مورد نظر یافت نشد.')),
      );
    }

    final dish = _dish!;
    final isFav = favs.isFavorite(dish.id);

    return Scaffold(
      appBar: AppBar(
        title: Text(dish.name),
        actions: [
          IconButton(
            icon: Icon(isFav ? Icons.favorite : Icons.favorite_border, color: isFav ? Colors.red : null),
            onPressed: () => favs.toggleFavorite(dish),
          ),
        ],
      ),
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
              // Total Price
              Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('مبلغ نهایی:', style: TextStyle(fontSize: 12, color: Colors.grey)),
                  Text(
                    CurrencyFormatter.format(_calculateLivePrice()),
                    style: TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.bold,
                      color: primary,
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 20),

              // Add to cart button
              Expanded(
                child: FlavorButton(
                  text: 'افزودن به سبد خرید',
                  icon: Icons.add_shopping_cart,
                  onPressed: dish.available ? _handleAddToCart : null,
                ),
              ),
            ],
          ),
        ),
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Gallery / Hero Image
            AspectRatio(
              aspectRatio: 16 / 10,
              child: dish.image.isNotEmpty
                  ? CachedNetworkImage(
                      imageUrl: dish.image,
                      fit: BoxFit.cover,
                    )
                  : Container(
                      color: isDark ? const Color(0xFF2B2B2B) : const Color(0xFFE5E7EB),
                      child: const Icon(Icons.restaurant, size: 64, color: Colors.grey),
                    ),
            ),

            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Title & Price
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          dish.name,
                          style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                        ),
                      ),
                      Text(
                        CurrencyFormatter.format(dish.price),
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: primary),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),

                  // Nutritional & Prep Meta Row
                  Row(
                    children: [
                      if (dish.prepTime > 0) ...[
                        const Icon(Icons.timer_outlined, size: 16, color: Colors.grey),
                        const SizedBox(width: 4),
                        Text('${PersianNumber.toPersian(dish.prepTime)} دقیقه آماده‌سازی', style: theme.textTheme.bodySmall),
                        const SizedBox(width: 14),
                      ],
                      if (dish.calories > 0) ...[
                        const Icon(Icons.local_fire_department_outlined, size: 16, color: Colors.orange),
                        const SizedBox(width: 4),
                        Text('${PersianNumber.toPersian(dish.calories)} کالری', style: theme.textTheme.bodySmall),
                      ],
                    ],
                  ),

                  // Description
                  if (dish.description.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    Text(
                      dish.description,
                      style: theme.textTheme.bodyMedium?.copyWith(height: 1.6),
                    ),
                  ],

                  const Divider(height: 32),

                  // Modifier Groups
                  ...dish.modifierGroups.map((group) {
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          group.title,
                          style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        ...group.options.map((opt) {
                          final isSelected = _selectedModifierIds.contains(opt.id);

                          return CheckboxListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(opt.name),
                            subtitle: opt.price > 0
                                ? Text(
                                    '+ ${CurrencyFormatter.format(opt.price)}',
                                    style: TextStyle(color: primary, fontSize: 12),
                                  )
                                : null,
                            value: isSelected,
                            onChanged: (val) {
                              setState(() {
                                if (group.multi) {
                                  if (val == true) {
                                    _selectedModifierIds.add(opt.id);
                                  } else {
                                    _selectedModifierIds.remove(opt.id);
                                  }
                                } else {
                                  // Single-choice
                                  for (final o in group.options) {
                                    _selectedModifierIds.remove(o.id);
                                  }
                                  if (val == true) {
                                    _selectedModifierIds.add(opt.id);
                                  }
                                }
                              });
                            },
                          );
                        }),
                        const SizedBox(height: 12),
                      ],
                    );
                  }),

                  // Chef notes input
                  FlavorTextField(
                    controller: _notesController,
                    label: 'یادداشت ویژه برای سرآشپز',
                    hintText: 'مثال: کم‌نمک، سس جداگانه، بدون فلفل...',
                  ),

                  const SizedBox(height: 20),

                  // Quantity Selector
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('تعداد:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      Container(
                        decoration: BoxDecoration(
                          border: Border.all(color: theme.dividerColor),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            IconButton(
                              icon: const Icon(Icons.remove),
                              onPressed: () {
                                if (_quantity > 1) {
                                  setState(() => _quantity--);
                                }
                              },
                            ),
                            Text(
                              PersianNumber.toPersian(_quantity),
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                            IconButton(
                              icon: const Icon(Icons.add),
                              onPressed: () => setState(() => _quantity++),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),

                  const Divider(height: 36),

                  // Customer Reviews Section
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'دیدگاه مشتریان (${PersianNumber.toPersian(_reviews.length)})',
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                      ),
                      TextButton.icon(
                        icon: const Icon(Icons.rate_review_outlined, size: 16),
                        label: const Text('ثبت نظر'),
                        onPressed: _showAddReviewDialog,
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),

                  if (_reviews.isEmpty)
                    const Text('هنوز دیدگاهی برای این غذا ثبت نشده است.', style: TextStyle(color: Colors.grey))
                  else
                    ..._reviews.map(
                      (r) => FlavorCard(
                        margin: const EdgeInsets.only(bottom: 10),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(r.authorName, style: const TextStyle(fontWeight: FontWeight.bold)),
                                Row(
                                  children: List.generate(
                                    5,
                                    (i) => Icon(
                                      i < r.rating ? Icons.star : Icons.star_border,
                                      size: 14,
                                      color: Colors.amber,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(r.comment, style: theme.textTheme.bodyMedium),
                          ],
                        ),
                      ),
                    ),
                  const SizedBox(height: 40),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
