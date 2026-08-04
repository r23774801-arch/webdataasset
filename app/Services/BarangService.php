<?php
/**
 * BarangService — shared CRUD + RBAC for the separated Barang tables.
 *
 * Tables (all typed): barang_masuk_it, barang_masuk_ga,
 *                     barang_keluar_it, barang_keluar_ga
 *
 * RBAC matrix (enforced server-side here):
 *   ADMIN : full manage (create/edit/delete) on all Barang tables
 *   IT    : full CRUD on *_it tables, view only on *_ga tables
 *   GA    : full CRUD on *_ga tables, view only on *_it tables
 */
class BarangService
{
    public const MODULES = ['masuk', 'keluar'];
    public const TYPES   = ['it', 'ga'];

    /**
     * Resolve a module+type pair to its table name, or null when invalid.
     */
    public static function table(string $module, string $type): ?string
    {
        $module = strtolower($module);
        $type   = strtolower($type);
        if (!in_array($module, self::MODULES, true) || !in_array($type, self::TYPES, true)) {
            return null;
        }
        return "barang_{$module}_{$type}";
    }

    /**
     * Whether the given role may create/edit/delete records of this type.
     */
    public static function canManage(string $role, string $type): bool
    {
        $role = strtoupper($role);
        $type = strtolower($type);
        if ($role === 'ADMIN') {
            return true;
        }
        if ($role === 'IT') {
            return $type === 'it';
        }
        if ($role === 'GA') {
            return $type === 'ga';
        }
        return false;
    }

    /**
     * Whether the given role may view records of this type (all logged-in roles can).
     */
    public static function canView(string $role): bool
    {
        return in_array(strtoupper($role), ['ADMIN', 'IT', 'GA'], true);
    }

    /**
     * List all rows for a module+type, newest first.
     */
    public static function listAll(mysqli $conn, string $module, string $type): array
    {
        $table = self::table($module, $type);
        if (!$table) {
            return [];
        }
        $result = $conn->query("SELECT * FROM $table ORDER BY created_at DESC, id DESC");
        if (!$result) {
            return [];
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Insert a new row. $data keys mirror the front-end field names.
     * Returns the new id or null on failure.
     */
    public static function create(mysqli $conn, string $module, string $type, array $data): ?int
    {
        $table = self::table($module, $type);
        if (!$table) {
            return null;
        }

        $assetNumber = trim((string)($data['asset_number'] ?? ''));
        $assetName   = trim((string)($data['asset_name'] ?? ''));
        $jumlah      = max(0, (int)($data['jumlah'] ?? 0));
        $tanggal     = trim((string)($data['tanggal'] ?? ''));
        $pic         = trim((string)($data['pic'] ?? ''));
        $area        = trim((string)($data['area'] ?? 'Main Office'));
        $supplier    = trim((string)($data['supplier'] ?? ''));

        if ($assetName === '' || $jumlah <= 0 || $tanggal === '') {
            return null;
        }

        if ($module === 'masuk') {
            $sql = "INSERT INTO $table (asset_number, asset_name, jumlah, supplier, tanggal, pic, area, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('ssissss', $assetNumber, $assetName, $jumlah, $supplier, $tanggal, $pic, $area);
        } else {
            $sql = "INSERT INTO $table (asset_number, asset_name, jumlah, tanggal, pic, area, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('ssisss', $assetNumber, $assetName, $jumlah, $tanggal, $pic, $area);
        }

        if (!$stmt->execute()) {
            return null;
        }
        return (int)$conn->insert_id;
    }

    /**
     * Update an existing row. Returns true on success.
     */
    public static function update(mysqli $conn, string $module, string $type, int $id, array $data): bool
    {
        $table = self::table($module, $type);
        if (!$table || $id <= 0) {
            return false;
        }

        $assetNumber = trim((string)($data['asset_number'] ?? ''));
        $assetName   = trim((string)($data['asset_name'] ?? ''));
        $jumlah      = max(0, (int)($data['jumlah'] ?? 0));
        $tanggal     = trim((string)($data['tanggal'] ?? ''));
        $pic         = trim((string)($data['pic'] ?? ''));
        $area        = trim((string)($data['area'] ?? 'Main Office'));
        $supplier    = trim((string)($data['supplier'] ?? ''));

        if ($assetName === '' || $jumlah <= 0 || $tanggal === '') {
            return false;
        }

        if ($module === 'masuk') {
            $sql = "UPDATE $table
                    SET asset_number = ?, asset_name = ?, jumlah = ?, supplier = ?, tanggal = ?, pic = ?, area = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ssissssi', $assetNumber, $assetName, $jumlah, $supplier, $tanggal, $pic, $area, $id);
        } else {
            $sql = "UPDATE $table
                    SET asset_number = ?, asset_name = ?, jumlah = ?, tanggal = ?, pic = ?, area = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ssisssi', $assetNumber, $assetName, $jumlah, $tanggal, $pic, $area, $id);
        }

        return $stmt->execute();
    }

    /**
     * Delete a row. Returns true on success.
     */
    public static function delete(mysqli $conn, string $module, string $type, int $id): bool
    {
        $table = self::table($module, $type);
        if (!$table || $id <= 0) {
            return false;
        }
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }
}
