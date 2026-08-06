<?php
/**
 * AreaService — single source of truth for the application's Areas.
 *
 * Every Area dropdown, summary card, report and chart reads from the
 * master_area table through this service, so a new Area inserted into
 * master_area automatically appears everywhere without code changes.
 */
class AreaService
{
    /**
     * Every active Area as ['id' => int, 'area_name' => string], ordered by id.
     * Returns [] when master_area does not exist yet (pre-migration) so callers
     * can fall back to their previous behavior.
     */
    public static function active(mysqli $conn): array
    {
        if (!table_exists($conn, 'master_area')) {
            return [];
        }
        $result = $conn->query(
            "SELECT id, area_name FROM master_area WHERE is_active = 1 ORDER BY id ASC"
        );
        if (!$result) {
            return [];
        }
        $areas = [];
        while ($row = $result->fetch_assoc()) {
            $areas[] = [
                'id'        => (int)$row['id'],
                'area_name' => (string)$row['area_name'],
            ];
        }
        return $areas;
    }

    /**
     * Flat list of active Area names (used by reports, charts and dropdowns).
     */
    public static function names(mysqli $conn): array
    {
        return array_map(
            static fn(array $area): string => $area['area_name'],
            self::active($conn)
        );
    }
}
