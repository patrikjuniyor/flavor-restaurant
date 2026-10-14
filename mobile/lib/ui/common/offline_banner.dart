import 'package:flutter/material.dart';

/// Thin amber strip telling the customer they are looking at stored data
/// (prompt 66). Shown at the top of feeds that were restored from the local
/// offline cache after a failed network load, so cached prices/menu items are
/// never mistaken for live ones.
///
/// Fixed amber palette on purpose: it must read identically in light and dark
/// themes and never blend into a brand-tinted surface.
class OfflineBanner extends StatelessWidget {
  final String message;

  const OfflineBanner({
    super.key,
    this.message = 'اتصال شبکه برقرار نیست — آخرین داده‌های ذخیره‌شده نمایش داده می‌شود.',
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: const Color(0xFFFEF3C7),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: [
          const Icon(Icons.cloud_off, size: 18, color: Color(0xFF92400E)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                fontSize: 12,
                height: 1.6,
                fontWeight: FontWeight.bold,
                color: Color(0xFF92400E),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
