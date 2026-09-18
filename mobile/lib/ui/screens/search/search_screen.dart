import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/routes.dart';
import '../../../models/dish_model.dart';
import '../../../repositories/menu_repository.dart';
import '../../../state/cart_state.dart';
import '../../../state/config_state.dart';
import '../../../state/favorites_state.dart';
import '../../common/dish_card.dart';
import '../../common/empty_state.dart';
import '../../common/flavor_text_field.dart';

/// Smart Search Screen with Autocomplete suggestions and Dietary facets.
class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key});

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final _menuRepo = MenuRepository();
  final _searchController = TextEditingController();

  List<DishModel> _results = [];
  List<String> _suggestions = [];
  List<String> _popularSearches = [];
  String? _selectedDietary;
  bool _isLoading = false;
  bool _hasSearched = false;

  @override
  void initState() {
    super.initState();
    _loadPopular();
  }

  Future<void> _loadPopular() async {
    final pop = await _menuRepo.getPopularSearches();
    setState(() => _popularSearches = pop);
  }

  Future<void> _handleSearch(String query) async {
    if (query.trim().isEmpty) return;
    final branchId = context.read<ConfigProvider>().activeBranch?.id ?? 0;

    setState(() {
      _isLoading = true;
      _hasSearched = true;
    });

    try {
      final dishes = await _menuRepo.search(
        query: query.trim(),
        branchId: branchId,
        dietary: _selectedDietary,
      );
      setState(() {
        _results = dishes;
        _isLoading = false;
      });
    } catch (_) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _onQueryChanged(String val) async {
    if (val.trim().length >= 2) {
      final branchId = context.read<ConfigProvider>().activeBranch?.id ?? 0;
      final sugg = await _menuRepo.getSuggestions(val, branchId: branchId);
      setState(() => _suggestions = sugg);
    } else {
      setState(() => _suggestions = []);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final favs = context.watch<FavoritesProvider>();
    final cart = context.watch<CartProvider>();

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Padding(
          padding: const EdgeInsets.only(left: 16),
          child: FlavorTextField(
            controller: _searchController,
            hintText: 'جستجوی نام غذا، کباب، پیتزا، دسر...',
            prefixIcon: const Icon(Icons.search),
            autofocus: true,
            onChanged: _onQueryChanged,
            suffixIcon: _searchController.text.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.clear),
                    onPressed: () {
                      _searchController.clear();
                      setState(() {
                        _results = [];
                        _suggestions = [];
                        _hasSearched = false;
                      });
                    },
                  )
                : null,
          ),
        ),
      ),
      body: Column(
        children: [
          // Dietary Filter Row
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                'گیاهی',
                'وگان',
                'تند',
                'بدون گلوتن',
              ].map((diet) {
                final isSelected = _selectedDietary == diet;
                return Padding(
                  padding: const EdgeInsets.only(left: 8),
                  child: FilterChip(
                    label: Text(diet),
                    selected: isSelected,
                    onSelected: (val) {
                      setState(() => _selectedDietary = val ? diet : null);
                      if (_searchController.text.isNotEmpty) {
                        _handleSearch(_searchController.text);
                      }
                    },
                  ),
                );
              }).toList(),
            ),
          ),

          // Autocomplete Suggestions
          if (_suggestions.isNotEmpty && !_hasSearched)
            Expanded(
              child: ListView.builder(
                itemCount: _suggestions.length,
                itemBuilder: (context, index) {
                  final s = _suggestions[index];
                  return ListTile(
                    leading: const Icon(Icons.search, size: 20),
                    title: Text(s),
                    onTap: () {
                      _searchController.text = s;
                      _handleSearch(s);
                    },
                  );
                },
              ),
            )
          // Search Results
          else if (_hasSearched)
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _results.isEmpty
                      ? const EmptyState(
                          title: 'نتیجه‌ای یافت نشد',
                          description: 'لطفاً عبارت دیگری را جستجو کنید.',
                          icon: Icons.search_off,
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _results.length,
                          itemBuilder: (context, index) {
                            final dish = _results[index];
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
            )
          // Popular Searches
          else if (_popularSearches.isNotEmpty)
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'بیشترین جستجوهای کاربران',
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: _popularSearches.map((tag) {
                        return ActionChip(
                          avatar: const Icon(Icons.trending_up, size: 16),
                          label: Text(tag),
                          onPressed: () {
                            _searchController.text = tag;
                            _handleSearch(tag);
                          },
                        );
                      }).toList(),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
