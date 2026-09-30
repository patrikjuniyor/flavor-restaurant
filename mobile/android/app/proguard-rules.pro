# Release ProGuard rules (minifyEnabled is currently off; keep rules here
# for the day shrinking is enabled — see docs/MOBILE-RELEASE-CHECKLIST.md).
# Flutter engine requires these keeps to compile release mode correctly.
-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.**  { *; }
-keep class io.flutter.util.**  { *; }
-keep class io.flutter.view.**  { *; }
-keep class io.flutter.**  { *; }
-keep class io.flutter.plugins.**  { *; }
