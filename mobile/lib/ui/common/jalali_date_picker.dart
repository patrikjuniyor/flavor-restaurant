import 'package:flutter/material.dart';
import '../../core/utils/jalali_date.dart';
import '../../core/utils/persian_number.dart';
import 'flavor_button.dart';

/// Interactive Jalali Calendar Month grid picker dialog.
class JalaliDatePickerDialog extends StatefulWidget {
  final JalaliDate initialDate;
  final ValueChanged<JalaliDate> onDateSelected;

  const JalaliDatePickerDialog({
    super.key,
    required this.initialDate,
    required this.onDateSelected,
  });

  @override
  State<JalaliDatePickerDialog> createState() => _JalaliDatePickerDialogState();
}

class _JalaliDatePickerDialogState extends State<JalaliDatePickerDialog> {
  late int _year;
  late int _month;
  late int _selectedDay;

  @override
  void initState() {
    super.initState();
    _year = widget.initialDate.year;
    _month = widget.initialDate.month;
    _selectedDay = widget.initialDate.day;
  }

  void _nextMonth() {
    setState(() {
      if (_month == 12) {
        _year++;
        _month = 1;
      } else {
        _month++;
      }
      _selectedDay = 1;
    });
  }

  void _prevMonth() {
    setState(() {
      if (_month == 1) {
        _year--;
        _month = 12;
      } else {
        _month--;
      }
      _selectedDay = 1;
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = theme.primaryColor;
    final totalDays = JalaliDate.monthLength(_year, _month);

    final firstDayG = JalaliDate(_year, _month, 1).toDateTime();
    final firstDayOfWeek = (firstDayG.weekday + 1) % 7; // Iranian Saturday=0

    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Month Header with arrows
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                IconButton(
                  icon: const Icon(Icons.chevron_right),
                  onPressed: _prevMonth,
                ),
                Text(
                  '${JalaliDate.monthNames[_month]} ${PersianNumber.toPersian(_year)}',
                  style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                ),
                IconButton(
                  icon: const Icon(Icons.chevron_left),
                  onPressed: _nextMonth,
                ),
              ],
            ),
            const Divider(),

            // Weekday Headers
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: const ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']
                  .map(
                    (d) => Text(
                      d,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.grey),
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 8),

            // Days Grid
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 7,
                mainAxisSpacing: 6,
                crossAxisSpacing: 6,
              ),
              itemCount: firstDayOfWeek + totalDays,
              itemBuilder: (context, index) {
                if (index < firstDayOfWeek) {
                  return const SizedBox();
                }
                final day = index - firstDayOfWeek + 1;
                final isSelected = day == _selectedDay;

                return GestureDetector(
                  onTap: () {
                    setState(() => _selectedDay = day);
                  },
                  child: Container(
                    decoration: BoxDecoration(
                      color: isSelected ? primary : Colors.transparent,
                      shape: BoxShape.circle,
                      border: isSelected ? null : Border.all(color: Colors.transparent),
                    ),
                    child: Center(
                      child: Text(
                        PersianNumber.toPersian(day),
                        style: TextStyle(
                          color: isSelected ? Colors.white : theme.textTheme.bodyMedium?.color,
                          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
            const SizedBox(height: 16),

            // Action Buttons
            Row(
              children: [
                Expanded(
                  child: FlavorButton(
                    text: 'تأیید تاریخ',
                    onPressed: () {
                      widget.onDateSelected(JalaliDate(_year, _month, _selectedDay));
                      Navigator.pop(context);
                    },
                  ),
                ),
                const SizedBox(width: 8),
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('انصراف'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
