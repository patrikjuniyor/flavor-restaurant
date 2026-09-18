/// Food Dish and Customization Modifier domain models.
import 'category_model.dart';

class ModifierOption {
  final String id;
  final String name;
  final int price;
  final String priceHtml;
  final bool isDefault;

  const ModifierOption({
    required this.id,
    required this.name,
    required this.price,
    required this.priceHtml,
    this.isDefault = false,
  });

  factory ModifierOption.fromJson(Map<String, dynamic> json) {
    return ModifierOption(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      price: int.tryParse(json['price']?.toString() ?? '0') ?? 0,
      priceHtml: json['price_html']?.toString() ?? '',
      isDefault: json['is_default'] == true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'price': price,
      'price_html': priceHtml,
      'is_default': isDefault,
    };
  }
}

class ModifierGroup {
  final String type;
  final String title;
  final bool required;
  final bool multi;
  final List<ModifierOption> options;

  const ModifierGroup({
    required this.type,
    required this.title,
    this.required = false,
    this.multi = false,
    this.options = const [],
  });

  factory ModifierGroup.fromJson(Map<String, dynamic> json) {
    final opts = (json['options'] as List?)
            ?.map((e) => ModifierOption.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    return ModifierGroup(
      type: json['type']?.toString() ?? 'topping',
      title: json['title']?.toString() ?? 'مخلفات',
      required: json['required'] == true,
      multi: json['multi'] == true,
      options: opts,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'type': type,
      'title': title,
      'required': required,
      'multi': multi,
      'options': options.map((e) => e.toJson()).toList(),
    };
  }
}

class DishModel {
  final int id;
  final String name;
  final String slug;
  final String shortDesc;
  final String description;
  final int price;
  final String priceHtml;
  final int regularPrice;
  final bool onSale;
  final int discountPct;
  final String image;
  final List<String> gallery;
  final int prepTime;
  final int calories;
  final List<String> dietary;
  final double ratingAvg;
  final int ratingCount;
  final bool available;
  final bool inSchedule;
  final String availableAt;
  final List<CategoryModel> categories;
  final List<ModifierGroup> modifierGroups;
  final bool hasModifiers;
  final String permalink;

  const DishModel({
    required this.id,
    required this.name,
    required this.slug,
    this.shortDesc = '',
    this.description = '',
    required this.price,
    required this.priceHtml,
    this.regularPrice = 0,
    this.onSale = false,
    this.discountPct = 0,
    this.image = '',
    this.gallery = const [],
    this.prepTime = 0,
    this.calories = 0,
    this.dietary = const [],
    this.ratingAvg = 5.0,
    this.ratingCount = 0,
    this.available = true,
    this.inSchedule = true,
    this.availableAt = '',
    this.categories = const [],
    this.modifierGroups = const [],
    this.hasModifiers = false,
    this.permalink = '',
  });

  factory DishModel.fromJson(Map<String, dynamic> json) {
    final cats = (json['categories'] as List?)
            ?.map((e) => CategoryModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final groups = (json['modifier_groups'] as List?)
            ?.map((e) => ModifierGroup.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final gal = (json['gallery'] as List?)?.map((e) => e.toString()).toList() ?? [];
    final diets = (json['dietary'] as List?)?.map((e) => e.toString()).toList() ?? [];

    return DishModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
      shortDesc: json['short_desc']?.toString() ?? (json['short']?.toString() ?? ''),
      description: json['description']?.toString() ?? '',
      price: int.tryParse(json['price']?.toString() ?? '0') ?? 0,
      priceHtml: json['price_html']?.toString() ?? '',
      regularPrice: int.tryParse(json['regular_price']?.toString() ?? '0') ?? 0,
      onSale: json['on_sale'] == true,
      discountPct: int.tryParse(json['discount_pct']?.toString() ?? '0') ?? 0,
      image: json['image']?.toString() ?? '',
      gallery: gal,
      prepTime: int.tryParse(json['prep_time']?.toString() ?? '0') ?? 0,
      calories: int.tryParse(json['calories']?.toString() ?? '0') ?? 0,
      dietary: diets,
      ratingAvg: double.tryParse(json['rating_avg']?.toString() ?? '5.0') ?? 5.0,
      ratingCount: int.tryParse(json['rating_count']?.toString() ?? '0') ?? 0,
      available: json['available'] != false,
      inSchedule: json['in_schedule'] != false,
      availableAt: json['available_at']?.toString() ?? '',
      categories: cats,
      modifierGroups: groups,
      hasModifiers: json['has_modifiers'] == true || groups.isNotEmpty,
      permalink: json['permalink']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'slug': slug,
      'short_desc': shortDesc,
      'description': description,
      'price': price,
      'price_html': priceHtml,
      'regular_price': regularPrice,
      'on_sale': onSale,
      'discount_pct': discountPct,
      'image': image,
      'gallery': gallery,
      'prep_time': prepTime,
      'calories': calories,
      'dietary': dietary,
      'rating_avg': ratingAvg,
      'rating_count': ratingCount,
      'available': available,
      'in_schedule': inSchedule,
      'available_at': availableAt,
      'categories': categories.map((e) => e.toJson()).toList(),
      'modifier_groups': modifierGroups.map((e) => e.toJson()).toList(),
      'has_modifiers': hasModifiers,
      'permalink': permalink,
    };
  }
}
