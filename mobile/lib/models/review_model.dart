/// Customer Review and Testimonial domain models.

class ReviewModel {
  final int id;
  final int productId;
  final String authorName;
  final int rating;
  final String comment;
  final bool isVerified;
  final String date;
  final String jalaliDate;
  final String dishName;
  final String dishImage;

  const ReviewModel({
    required this.id,
    required this.productId,
    required this.authorName,
    required this.rating,
    required this.comment,
    this.isVerified = false,
    this.date = '',
    this.jalaliDate = '',
    this.dishName = '',
    this.dishImage = '',
  });

  factory ReviewModel.fromJson(Map<String, dynamic> json) {
    return ReviewModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      productId: int.tryParse(json['product_id']?.toString() ?? '0') ?? 0,
      authorName: json['author_name']?.toString() ?? 'مشتری رستوران',
      rating: int.tryParse(json['rating']?.toString() ?? '5') ?? 5,
      comment: json['comment']?.toString() ?? '',
      isVerified: json['is_verified'] == true,
      date: json['date']?.toString() ?? '',
      jalaliDate: json['jalali_date']?.toString() ?? '',
      dishName: json['dish_name']?.toString() ?? '',
      dishImage: json['dish_image']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'product_id': productId,
      'author_name': authorName,
      'rating': rating,
      'comment': comment,
      'is_verified': isVerified,
      'date': date,
      'jalali_date': jalaliDate,
      'dish_name': dishName,
      'dish_image': dishImage,
    };
  }
}
