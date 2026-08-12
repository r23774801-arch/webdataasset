<?php
header('Content-Type: application/json');

// Authentication: uploads are only allowed for authenticated, non-ADMIN
// transaction roles. Admin is monitoring/approval only.
session_start();
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();
deny_admin_transaction();
require_once __DIR__ . '/../../config/upload.php';

$cfg = upload_config();

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment'])) {
    $file = $_FILES['attachment'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "Upload failed with error code: " . $file['error']]);
        exit;
    }
    
    // Check file type �?" images (JPG/JPEG/PNG/GIF/WEBP) and PDF documents
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $fileType = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');
    
    if (!in_array($fileType, $allowedTypes, true)) {
        echo json_encode(["status" => "error", "message" => "Tipe file tidak didukung. Hanya JPG, JPEG, PNG, WEBP, dan PDF yang diizinkan."]);
        exit;
    }
    
    // Configurable maximum size.
    if ($file['size'] > $cfg['max_size']) {
        $maxMb = round($cfg['max_size'] / 1024 / 1024, 1);
        echo json_encode(["status" => "error", "message" => "Ukuran file melebihi batas maksimum ({$maxMb} MB)."]);
        exit;
    }
    
    // Extension is derived from the VERIFIED MIME type (never from the
    // client-supplied filename) so a stored file can never claim a PHP ext.
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    $extension = $mimeToExt[$fileType] ?? '';
    if ($extension === '' || !in_array($extension, $cfg['allowed_extensions'], true)) {
        echo json_encode(["status" => "error", "message" => "Tipe file tidak didukung. Hanya JPG, JPEG, PNG, WEBP, dan PDF yang diizinkan."]);
        exit;
    }
    $filename = uniqid() . '.' . $extension;
    $destination = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Return the path that will be stored in database
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
?>
