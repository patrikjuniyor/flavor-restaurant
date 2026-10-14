/// Food Dish and Customization Modifier domain models.
///
/// Mirrors MenuController::serialize_dish()/serialize_dish_detail() — see
/// test/fixtures/menu.json and test/fixtures/dish_detail.json.
library;

import 'category_model.dart';
import 'json_fields.dart';

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
      id: strField(json['id']),
      name: strField(json['name']),
      price: intField(json['price']),
      priceHtml: strField(json['price_html']),
      isDefault: boolField(json['is_default']),
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
      type: strField(json['type'], 'topping'),
      title: strField(json['title'], 'مخلفات'),
      required: boolField(json['required']),
      multi: boolField(json['multi']),
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

  /// Sanitized allergen keys (`_flavor_allergens` meta) — card payload.
  final List<String> allergens;
  final double ratingAvg;
  final int ratingCount;
  final bool available;
  final bool inSchedule;
  final String availableAt;
  final List<CategoryModel> categories;
  final List<ModifierGroup> modifierGroups;

  /// Per-day serving schedule rows (`_flavor_schedule` meta) — detail payload;
  /// shape is free-form server data, kept raw on purpose.
  final List<dynamic> schedules;
  final bool hasModifiers;
  final String permalink;

  /// Stock keeping unit — detail payload only.
  final String sku;

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
    this.allergens = const [],
    this.ratingAvg = 5.0,
    this.ratingCount = 0,
    this.available = true,
    this.inSchedule = true,
    this.availableAt = '',
    this.categories = const [],
    this.modifierGroups = const [],
    this.schedules = const [],
    this.hasModifiers = false,
    this.permalink = '',
    this.sku = '',
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
    final allergenList = (json['allergens'] as List?)?.map((e) => e.toString()).toList() ?? [];
    final scheduleList = (json['schedules'] as List?)?.toList() ?? [];

    return DishModel(
      id: intField(json['id']),
      name: strField(json['name']),
      slug: strField(json['slug']),
      shortDesc: strField(json['short_desc'], strField(json['short'])),
      description: strField(json['description']),
      price: intField(json['price']),
      priceHtml: strField(json['price_html']),
      regularPrice: intField(json['regular_price']),
      onSale: boolField(json['on_sale']),
      discountPct: intField(json['discount_pct']),
      image: strField(json['image']),
      gallery: gal,
      prepTime: intField(json['prep_time']),
      calories: intField(json['calories']),
      dietary: diets,
      allergens: allergenList,
      // rating_avg is a PHP float (0.0 when the dish has no reviews yet).
      ratingAvg: doubleField(json['rating_avg'], 5.0),
      ratingCount: intField(json['rating_count']),
      available: boolField(json['available'], whenNull: true),
      inSchedule: boolField(json['in_schedule'], whenNull: true),
      availableAt: strField(json['available_at']),
      categories: cats,
      modifierGroups: groups,
      schedules: scheduleList,
      hasModifiers: boolField(json['has_modifiers']) || groups.isNotEmpty,
      permalink: strField(json['permalink']),
      sku: strField(json['sku']),
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
      'allergens': allergens,
      'rating_avg': ratingAvg,
      'rating_count': ratingCount,
      'available': available,
      'in_schedule': inSchedule,
      'available_at': availableAt,
      'categories': categories.map((e) => e.toJson()).toList(),
      'modifier_groups': modifierGroups.map((e) => e.toJson()).toList(),
      'schedules': schedules,
      'has_modifiers': hasModifiers,
      'permalink': permalink,
      'sku': sku,
    };
  }
}
