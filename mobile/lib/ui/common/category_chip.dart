import 'package:flutter/material.dart';
import '../../models/category_model.dart';

/// Horizontal Category selector chip.
class CategoryChip extends StatelessWidget {
  final CategoryModel category;
  final bool isSelected;
  final VoidCallback onTap;

  const CategoryChip({
    super.key,
    required this.category,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;

    return Padding(
      padding: const EdgeInsets.only(left: 8.0),
      child: FilterChip(
        label: Text(
          category.name,
          style: TextStyle(
            color: isSelected ? Colors.white : theme.textTheme.bodyMedium?.color,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            fontSize: 13,
          ),
        ),
        selected: isSelected,
        onSelected: (_) => onTap(),
        selectedColor: primary,
        backgroundColor: theme.cardColor,
        showCheckmark: false,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(
            color: isSelected ? primary : theme.dividerColor,
            width: 1,
          ),
        ),
      ),
    );
  }
}
