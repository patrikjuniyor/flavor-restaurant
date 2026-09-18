import 'package:flutter/material.dart';

/// Standard Card container following Design System tokens.
class FlavorCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final VoidCallback? onTap;
  final Color? color;
  final double borderRadius;
  final BorderSide? border;

  const FlavorCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16.0),
    this.margin,
    this.onTap,
    this.color,
    this.borderRadius = 12.0,
    this.border,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    final cardBg = color ?? (isDark ? const Color(0xFF222222) : Colors.white);
    final borderColor = isDark ? const Color(0xFF2E2E2E) : const Color(0xFFE5E7EB);

    Widget content = Container(
      padding: padding,
      child: child,
    );

    return Container(
      margin: margin,
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(borderRadius),
        border: border != null ? Border.fromBorderSide(border!) : Border.all(color: borderColor, width: 1),
        boxShadow: isDark
            ? null
            : [
                BoxShadow(
                  color: Colors.black.withOpacity(0.03),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(borderRadius),
        clipBehavior: Clip.antiAlias,
        child: onTap != null
            ? InkWell(
                onTap: onTap,
                child: content,
              )
            : content,
      ),
    );
  }
}
