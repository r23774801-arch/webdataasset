<?php
/**
 * Shared helper functions for the approval workflow.
 */

/**
 * HTML-escape a value for safe output.
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Send a JSON response and stop execution.
 */
function json_response(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

/**
 * Read the JSON request body as an array.
 */
function read_json_input(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

/**
 * Current authenticated user (from the PHP session).
 */
function current_user(): array
{
    return [
        'nrp'      => $_SESSION['nrp'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role'     => strtoupper($_SESSION['role'] ?? ''),
    ];
}

/**
 * Server-side authorization: require an authenticated session.
 */
function require_login(): void
{
    if (empty($_SESSION['nrp'])) {
        json_response(['status' => 'error', 'message' => 'Akses ditolak. Silakan login kembali.']);
    }
}

/**
 * Server-side authorization: require a specific role.
 */
function require_role(string $role): void
{
    require_login();
    if (strtoupper($_SESSION['role'] ?? '') !== strtoupper($role)) {
        json_response(['status' => 'error', 'message' => 'Akses ditolak. Anda tidak memiliki izin untuk aksi ini.']);
    }
}

/**
 * Server-side authorization: require the ADMIN role.
 */
function require_admin(): void
{
    require_role('ADMIN');
}

/**
 * RBAC guard: the ADMIN role must never perform asset/barang transactions
 * (create / edit / delete / transfer). Admin is monitoring & approval only.
 *
 * Single shared rule for every transaction endpoint. Returns HTTP 403 with a
 * consistent JSON body and stops execution when the caller is ADMIN.
 */
function deny_admin_transaction(): void
{
    if (isset($_SESSION['role']) && strtoupper($_SESSION['role']) === 'ADMIN') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Administrator tidak diizinkan melakukan transaksi aset.',
        ]);
        exit;
    }
}

/**
 * Check whether a table exists in the connected database.
 */
function table_exists(mysqli $conn, string $name): bool
{
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($name) . "'");
    return $result !== false && $result->num_rows > 0;
}
