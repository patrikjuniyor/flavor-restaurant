/// Food Category domain model.
class CategoryModel {
  final int id;
  final String name;
  final String slug;
  final String description;
  final int count;
  final String image;
  final String icon;

  const CategoryModel({
    required this.id,
    required this.name,
    required this.slug,
    this.description = '',
    this.count = 0,
    this.image = '',
    this.icon = '',
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      count: int.tryParse(json['count']?.toString() ?? '0') ?? 0,
      image: json['image']?.toString() ?? '',
      icon: json['icon']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'slug': slug,
      'description': description,
      'count': count,
      'image': image,
      'icon': icon,
    };
  }
}
