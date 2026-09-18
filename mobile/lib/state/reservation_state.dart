import 'package:flutter/material.dart';
import '../core/utils/jalali_date.dart';
import '../models/reservation_model.dart';
import '../repositories/reservation_repository.dart';

/// State management for Table Bookings, Jalali Slots, and Customer History.
class ReservationProvider extends ChangeNotifier {
  final ReservationRepository _resRepo;

  List<ReservationModel> _myReservations = [];
  List<ReservationSlot> _availableSlots = [];
  JalaliDate _selectedDate = JalaliDate.fromDateTime(DateTime.now());
  int _partySize = 2;
  String _section = 'indoor';

  bool _isLoading = false;
  String? _errorMessage;

  ReservationProvider({ReservationRepository? resRepo})
      : _resRepo = resRepo ?? ReservationRepository();

  List<ReservationModel> get myReservations => _myReservations;
  List<ReservationSlot> get availableSlots => _availableSlots;
  JalaliDate get selectedDate => _selectedDate;
  int get partySize => _partySize;
  String get section => _section;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Sets selected reservation date.
  void setDate(JalaliDate date, int branchId) {
    _selectedDate = date;
    notifyListeners();
    loadSlots(branchId);
  }

  /// Sets party size.
  void setPartySize(int size, int branchId) {
    _partySize = size;
    notifyListeners();
    loadSlots(branchId);
  }

  /// Sets dining section.
  void setSection(String sec, int branchId) {
    _section = sec;
    notifyListeners();
    loadSlots(branchId);
  }

  /// Loads available slots for branch, date, party size, and section.
  Future<void> loadSlots(int branchId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _availableSlots = await _resRepo.getSlots(
        branchId: branchId,
        date: _selectedDate.toIsoGregorianString(),
        party: _partySize,
        section: _section,
      );
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Loads customer reservation history.
  Future<void> loadMyReservations() async {
    _isLoading = true;
    notifyListeners();
    try {
      _myReservations = await _resRepo.getMyReservations();
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Submits table reservation.
  Future<bool> bookTable({
    required int branchId,
    required String time,
    required String mobile,
    required String name,
    String requests = '',
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _resRepo.bookTable(
        branchId: branchId,
        date: _selectedDate.toIsoGregorianString(),
        time: time,
        partySize: _partySize,
        mobile: mobile,
        name: name,
        section: _section,
        requests: requests,
      );
      await loadMyReservations();
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Cancels a reservation.
  Future<bool> cancelReservation(int id) async {
    try {
      await _resRepo.cancelReservation(id);
      await loadMyReservations();
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }
}
