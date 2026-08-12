<?php
header("Content-Type: application/json");
session_start();
require_once __DIR__ . '/../../app/helpers.php';

require_valid_origin();

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

echo json_encode(["status" => "success", "message" => "Logout berhasil!"]);
exit;
?>
