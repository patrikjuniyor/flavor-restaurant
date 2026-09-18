import '../config/constants.dart';
import '../core/api/api_client.dart';
import '../core/storage/secure_storage_service.dart';
import '../models/branch_model.dart';

/// Repository for Branches, Dining Tables, and Delivery Zones.
class BranchRepository {
  final ApiClient _apiClient;
  final StorageService _storage;

  BranchRepository({
    ApiClient? apiClient,
    StorageService? storage,
  })  : _apiClient = apiClient ?? ApiClient(),
        _storage = storage ?? StorageService();

  /// Gets all active restaurant branches.
  Future<List<BranchModel>> getBranches() async {
    final res = await _apiClient.get(AppConstants.epBranches);
    final list = res['data'] as List;
    return list.map((e) => BranchModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Gets single branch detail.
  Future<BranchModel> getBranch(int id) async {
    final res = await _apiClient.get('${AppConstants.epBranches}/$id');
    return BranchModel.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Gets dining tables for a branch.
  Future<List<DiningTable>> getTables(int branchId) async {
    final res = await _apiClient.get('${AppConstants.epBranches}/$branchId/tables');
    final list = res['data'] as List;
    return list.map((e) => DiningTable.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Gets delivery zones for a branch.
  Future<List<DeliveryZone>> getZones(int branchId) async {
    final res = await _apiClient.get('${AppConstants.epBranches}/$branchId/zones');
    final list = res['data'] as List;
    return list.map((e) => DeliveryZone.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Gets locally selected active branch ID.
  Future<int?> getActiveBranchId() => _storage.getActiveBranchId();

  /// Saves active branch ID to local storage.
  Future<void> setActiveBranchId(int branchId) => _storage.saveActiveBranchId(branchId);
}
