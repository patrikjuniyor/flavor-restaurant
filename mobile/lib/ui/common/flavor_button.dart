import 'package:flutter/material.dart';

enum FlavorButtonVariant { primary, secondary, outline, danger, text }

/// Standard Design Token Button with loading state, icons, and variants.
class FlavorButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final FlavorButtonVariant variant;
  final bool isLoading;
  final IconData? icon;
  final double? width;
  final double height;
  final double borderRadius;

  const FlavorButton({
    super.key,
    required this.text,
    this.onPressed,
    this.variant = FlavorButtonVariant.primary,
    this.isLoading = false,
    this.icon,
    this.width,
    this.height = 50.0,
    this.borderRadius = 12.0,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;

    Color bg;
    Color fg;
    BorderSide border = BorderSide.none;

    switch (variant) {
      case FlavorButtonVariant.primary:
        bg = primary;
        fg = Colors.white;
        break;
      case FlavorButtonVariant.secondary:
        bg = theme.colorScheme.secondary;
        fg = Colors.white;
        break;
      case FlavorButtonVariant.outline:
        bg = Colors.transparent;
        fg = primary;
        border = BorderSide(color: primary, width: 1.5);
        break;
      case FlavorButtonVariant.danger:
        bg = const Color(0xFFDC2626);
        fg = Colors.white;
        break;
      case FlavorButtonVariant.text:
        bg = Colors.transparent;
        fg = primary;
        break;
    }

    Widget content;
    if (isLoading) {
      content = SizedBox(
        width: 22,
        height: 22,
        child: CircularProgressIndicator(
          strokeWidth: 2.5,
          valueColor: AlwaysStoppedAnimation<Color>(fg),
        ),
      );
    } else {
      content = Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 18, color: fg),
            const SizedBox(width: 8),
          ],
          Text(
            text,
            style: TextStyle(
              fontFamily: theme.textTheme.labelLarge?.fontFamily,
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: fg,
            ),
          ),
        ],
      );
    }

    return SizedBox(
      width: width,
      height: height,
      child: Material(
        color: bg,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius),
          side: border,
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: isLoading ? null : onPressed,
          child: Center(child: content),
        ),
      ),
    );
  }
}
