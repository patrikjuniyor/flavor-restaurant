import 'package:flutter/material.dart';

/// Dynamic Theme generator supporting server design tokens, Light & Dark themes,
/// RTL typography, and rounded commercial restaurant UI components.
class AppTheme {
  static ThemeData createTheme({
    required Brightness brightness,
    Color primaryColor = const Color(0xFFC8102E),
    Color accentColor = const Color(0xFFD97706),
    double borderRadius = 12.0,
    String fontFamily = 'Vazirmatn',
  }) {
    final isDark = brightness == Brightness.dark;

    final bg = isDark ? const Color(0xFF121212) : const Color(0xFFF9FAFB);
    final surface = isDark ? const Color(0xFF1E1E1E) : Colors.white;
    final cardColor = isDark ? const Color(0xFF242424) : Colors.white;
    final textPrimary = isDark ? const Color(0xFFF3F4F6) : const Color(0xFF111827);
    final textSecondary = isDark ? const Color(0xFF9CA3AF) : const Color(0xFF6B7280);
    final borderColor = isDark ? const Color(0xFF2D2D2D) : const Color(0xFFE5E7EB);

    final colorScheme = ColorScheme.fromSeed(
      seedColor: primaryColor,
      brightness: brightness,
      primary: primaryColor,
      secondary: accentColor,
      surface: surface,
    );

    final textTheme = TextTheme(
      displayLarge: TextStyle(fontFamily: fontFamily, fontSize: 32, fontWeight: FontWeight.bold, color: textPrimary),
      displayMedium: TextStyle(fontFamily: fontFamily, fontSize: 26, fontWeight: FontWeight.bold, color: textPrimary),
      displaySmall: TextStyle(fontFamily: fontFamily, fontSize: 22, fontWeight: FontWeight.bold, color: textPrimary),
      headlineMedium: TextStyle(fontFamily: fontFamily, fontSize: 18, fontWeight: FontWeight.w700, color: textPrimary),
      headlineSmall: TextStyle(fontFamily: fontFamily, fontSize: 16, fontWeight: FontWeight.w600, color: textPrimary),
      titleLarge: TextStyle(fontFamily: fontFamily, fontSize: 16, fontWeight: FontWeight.bold, color: textPrimary),
      titleMedium: TextStyle(fontFamily: fontFamily, fontSize: 14, fontWeight: FontWeight.w600, color: textPrimary),
      titleSmall: TextStyle(fontFamily: fontFamily, fontSize: 13, fontWeight: FontWeight.w500, color: textSecondary),
      bodyLarge: TextStyle(fontFamily: fontFamily, fontSize: 15, fontWeight: FontWeight.normal, color: textPrimary),
      bodyMedium: TextStyle(fontFamily: fontFamily, fontSize: 13, fontWeight: FontWeight.normal, color: textPrimary),
      bodySmall: TextStyle(fontFamily: fontFamily, fontSize: 12, fontWeight: FontWeight.normal, color: textSecondary),
      labelLarge: TextStyle(fontFamily: fontFamily, fontSize: 14, fontWeight: FontWeight.w600, color: Colors.white),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      primaryColor: primaryColor,
      scaffoldBackgroundColor: bg,
      cardColor: cardColor,
      colorScheme: colorScheme,
      fontFamily: fontFamily,
      textTheme: textTheme,
      dividerColor: borderColor,
      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        elevation: 0,
        centerTitle: true,
        scrolledUnderElevation: 1,
        iconTheme: IconThemeData(color: textPrimary),
        titleTextStyle: TextStyle(
          fontFamily: fontFamily,
          fontSize: 17,
          fontWeight: FontWeight.bold,
          color: textPrimary,
        ),
      ),
      cardTheme: CardTheme(
        color: cardColor,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius),
          side: BorderSide(color: borderColor, width: 1),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primaryColor,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(borderRadius),
          ),
          textStyle: TextStyle(
            fontFamily: fontFamily,
            fontSize: 15,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primaryColor,
          side: BorderSide(color: primaryColor, width: 1.5),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(borderRadius),
          ),
          textStyle: TextStyle(
            fontFamily: fontFamily,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? const Color(0xFF242424) : const Color(0xFFF3F4F6),
        hintStyle: TextStyle(fontFamily: fontFamily, fontSize: 13, color: textSecondary),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(borderRadius),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(borderRadius),
          borderSide: BorderSide(color: borderColor, width: 1),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(borderRadius),
          borderSide: BorderSide(color: primaryColor, width: 1.5),
        ),
      ),
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        backgroundColor: surface,
        selectedItemColor: primaryColor,
        unselectedItemColor: textSecondary,
        selectedLabelStyle: TextStyle(fontFamily: fontFamily, fontSize: 12, fontWeight: FontWeight.bold),
        unselectedLabelStyle: TextStyle(fontFamily: fontFamily, fontSize: 11, fontWeight: FontWeight.normal),
        type: BottomNavigationBarType.fixed,
        elevation: 8,
      ),
      // ---------------------------------------------------------------
      // Premium component layer (1.3.0): dialogs, chips, snackbars and
      // list affordances tuned to match the storefront design system.
      // ---------------------------------------------------------------
      dialogTheme: DialogTheme(
        backgroundColor: cardColor,
        surfaceTintColor: Colors.transparent,
        elevation: 6,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius + 4),
          side: BorderSide(color: borderColor, width: 1),
        ),
        titleTextStyle: TextStyle(
          fontFamily: fontFamily,
          fontSize: 17,
          fontWeight: FontWeight.bold,
          color: textPrimary,
        ),
        contentTextStyle: TextStyle(
          fontFamily: fontFamily,
          fontSize: 13.5,
          height: 1.9,
          color: textSecondary,
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: isDark ? const Color(0xFF2A2A2A) : const Color(0xFFF3F4F6),
        selectedColor: primaryColor.withOpacity(0.14),
        checkmarkColor: primaryColor,
        labelStyle: TextStyle(
          fontFamily: fontFamily,
          fontSize: 12.5,
          fontWeight: FontWeight.w600,
          color: textPrimary,
        ),
        side: BorderSide(color: borderColor, width: 1),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: isDark ? const Color(0xFF2E2E2E) : const Color(0xFF1F2937),
        contentTextStyle: TextStyle(fontFamily: fontFamily, fontSize: 13, color: Colors.white),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius),
        ),
        elevation: 8,
      ),
      listTileTheme: ListTileThemeData(
        iconColor: textSecondary,
        textColor: textPrimary,
        selectedColor: primaryColor,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius),
        ),
      ),
      dividerTheme: DividerThemeData(
        color: borderColor,
        thickness: 1,
        space: 1,
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: primaryColor,
        linearTrackColor: borderColor,
        circularTrackColor: borderColor,
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        elevation: 3,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius + 2),
        ),
      ),
      tooltipTheme: TooltipThemeData(
        textStyle: TextStyle(fontFamily: fontFamily, fontSize: 12, color: Colors.white),
        decoration: BoxDecoration(
          color: const Color(0xFF111827),
          borderRadius: BorderRadius.circular(8),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      ),
      textSelectionTheme: TextSelectionThemeData(
        cursorColor: primaryColor,
        selectionColor: primaryColor.withOpacity(0.22),
        selectionHandleColor: primaryColor,
      ),
      highlightColor: Colors.transparent,
    );
  }
}
