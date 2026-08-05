<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config/upload.php';

$cfg = upload_config();

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment'])) {
    $file = $_FILES['attachment'];

    // Reject failed uploads
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "Upload failed with error code: " . $file['error']]);
        exit;
    }

    // Validate MIME type — only JPG / JPEG / PNG / WEBP are allowed
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');
    if (!in_array($mime, $allowedMimes, true)) {
        echo json_encode(["status" => "error", "message" => "Tipe file tidak didukung. Hanya JPG, JPEG, PNG, dan WEBP yang diizinkan."]);
        exit;
    }

    // Extension whitelist (must match the MIME type)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $cfg['allowed_extensions'], true)) {
        echo json_encode(["status" => "error", "message" => "Tipe file tidak didukung. Hanya JPG, JPEG, PNG, dan WEBP yang diizinkan."]);
        exit;
    }

    // Configurable maximum size
    if ($file['size'] > $cfg['max_size']) {
        $maxMb = round($cfg['max_size'] / 1024 / 1024, 1);
        echo json_encode(["status" => "error", "message" => "Ukuran file melebihi batas maksimum ({$maxMb} MB)."]);
        exit;
    }

    // Generate unique filename — never overwrites an existing image
    $filename = uniqid() . '.' . $ext;
    $destination = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Return the relative path that will be stored in the database
        echo json_encode([
            "status" => "success",
            "message" => "File uploaded successfully",
            "path" => $uploadDir . $filename
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "No file uploaded"]);
}
