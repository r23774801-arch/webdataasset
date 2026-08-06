<?php
/**
 * get_areas.php — Master Area API.
 *
 * Returns every active Area from the master_area table so the frontend can
 * populate Area dropdowns and summary cards dynamically. A new Area inserted
 * into master_area automatically appears here — and therefore everywhere —
 * without any code changes.
 *
 * Response: { "status": "success", "data": [ { "id": 1, "area_name": "Main Office", "is_active": 1 }, ... ] }
 */
header('Content-Type: application/json');
include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

json_response([
    'status' => 'success',
    'data'   => AreaService::active($conn),
]);
