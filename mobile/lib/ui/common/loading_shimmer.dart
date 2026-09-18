import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

/// Shimmer Skeleton loading placeholders for dishes, lists, and banners.
class LoadingShimmer extends StatelessWidget {
  final double width;
  final double height;
  final double borderRadius;
  final EdgeInsetsGeometry? margin;

  const LoadingShimmer({
    super.key,
    required this.width,
    required this.height,
    this.borderRadius = 8.0,
    this.margin,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final base = isDark ? const Color(0xFF2C2C2C) : const Color(0xFFE5E7EB);
    final highlight = isDark ? const Color(0xFF3A3A3A) : const Color(0xFFF3F4F6);

    return Container(
      margin: margin,
      child: Shimmer.fromColors(
        baseColor: base,
        highlightColor: highlight,
        child: Container(
          width: width,
          height: height,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(borderRadius),
          ),
        ),
      ),
    );
  }

  /// Helper: Grid of Shimmer Dish Cards
  static Widget dishGrid({int count = 4}) {
    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: count,
      itemBuilder: (_, __) => Padding(
        padding: const EdgeInsets.only(bottom: 16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const LoadingShimmer(width: double.infinity, height: 160, borderRadius: 12),
            const SizedBox(height: 10),
            const LoadingShimmer(width: 140, height: 16),
            const SizedBox(height: 6),
            const LoadingShimmer(width: 220, height: 12),
          ],
        ),
      ),
    );
  }
}
