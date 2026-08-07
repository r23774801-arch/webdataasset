<?php
// TEMPORARY audit helper — authenticates a session for endpoint testing.
// DELETE AFTER AUDIT.
session_start();
$_SESSION['nrp'] = $_GET['nrp'] ?? '0097682144';
$_SESSION['username'] = $_GET['username'] ?? 'Audit User';
$_SESSION['role'] = $_GET['role'] ?? 'it';
header('Content-Type: application/json');
echo json_encode(['session_id' => session_id(), 'nrp' => $_SESSION['nrp'], 'role' => $_SESSION['role']]);
