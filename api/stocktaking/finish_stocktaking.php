<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

// Server-side authorization: must be logged in as a non-ADMIN role.
require_login();
$user = current_user();
$role = $user['role'];

if ($role === 'ADMIN') {
    json_response(['status' => 'error', 'message' => 'Akses ditolak. Hanya role non-ADMIN yang dapat mengirim stocktaking untuk persetujuan.']);
}

$input = read_json_input();
$tableType = strtolower($input['table_type'] ?? '');

if (!in_array($tableType, ['it', 'ga'], true)) {
    json_response(['status' => 'error', 'message' => 'Data tidak valid.']);
}

$assetType = strtoupper($tableType);

// The approval system must exist (run migrate_db.php once).
if (!table_exists($conn, 'stocktaking_submissions')) {
    json_response(['status' => 'error', 'message' => 'Sistem persetujuan belum tersedia. Jalankan migrate_db.php terlebih dahulu.']);
}

// Phase 4.15 — global (asset-type-wide) lock: the latest submission for this
// asset type across ALL users is Pending or Approved, so no user may finish a
// new stocktaking cycle right now. The per-user checks below are NOT enough:
// a user who never submitted has no submission row of their own ($latest = null),
// which would wrongly allow them to open a parallel/duplicate submission while
// another user's cycle is awaiting review or has already been approved.
if (ApprovalService::isStocktakingLocked($conn, $assetType)) {
    json_response([
        'status'  => 'error',
        'message' => 'Stocktaking aset ' . $assetType . ' sedang berlangsung atau telah disetujui oleh user lain. Anda tidak dapat mengirim stocktaking lagi.'
    ]);
}

// Every Stocktaked asset must have a photo/PDF, a condition, and an utilisasi
// before the submission can be created. Block early and list the incomplete ones
// (by asset number) so the user knows exactly what still needs to be filled in.
$incomplete = ApprovalService::findIncompleteAssets($conn, $assetType);
if (!empty($incomplete)) {
    $preview = implode('; ', array_slice($incomplete, 0, 8));
    if (count($incomplete) > 8) {
        $preview .= '; +' . (count($incomplete) - 8) . ' aset lainnya';
    }
    json_response([
        'status'  => 'error',
        'message' => 'Stocktaking belum bisa diselesaikan. Data aset berikut masih belum lengkap (photo/PDF, kondisi, atau utilisasi): ' . $preview,
        'data'    => ['incomplete' => $incomplete],
    ]);
}

// Route based on the latest submission: block while Pending/Approved,
// resubmit the same row after a rejection, create a new one otherwise.
$latest = ApprovalService::getLatestForUser($conn, $user['nrp'], $assetType);
$isResubmit = false;

if ($latest) {
    if ($latest['status'] === ApprovalService::STATUS_PENDING) {
        json_response([
            'status'  => 'error',
            'message' => 'Stocktaking Anda masih menunggu persetujuan admin.',
            'data'    => $latest,
        ]);
    }
    if ($latest['status'] === ApprovalService::STATUS_APPROVED) {
        json_response(['status' => 'error', 'message' => 'Stocktaking Anda telah disetujui dan selesai.']);
    }
    // Rejected → reuse the existing submission (no duplicates).
    $isResubmit = true;
    $submission = ApprovalService::resubmit($conn, (int)$latest['id'], $user['nrp']);
} else {
    $submission = ApprovalService::createSubmission($conn, $assetType, $user['nrp'], $user['username']);
}

if (!$submission) {
    json_response(['status' => 'error', 'message' => 'Stocktaking belum lengkap. Pastikan seluruh aset berstatus Stocktaked sebelum menyelesaikan.']);
}

// Mirror the submission to the Approval worksheet (best-effort only).
SpreadsheetService::sync(SpreadsheetService::SHEET_APPROVAL, [
    'submission_code' => $submission['submission_code'] ?? null,
    'asset_type'      => $submission['asset_type'] ?? $assetType,
    'submitted_by'    => $submission['submitted_by'] ?? $user['nrp'],
    'submitted_by_name' => $submission['submitted_by_name'] ?? $user['username'],
    'department'      => $submission['department'] ?? null,
    'area'            => $submission['area'] ?? null,
    'total_assets'    => $submission['total_assets'] ?? 0,
    'normal_count'    => $submission['normal_count'] ?? 0,
    'broken_count'    => $submission['broken_count'] ?? 0,
    'lost_count'      => $submission['lost_count'] ?? 0,
    'pending_count'   => $submission['pending_count'] ?? 0,
    'status'          => $submission['status'] ?? ApprovalService::STATUS_PENDING,
    'submission_date' => $submission['submission_date'] ?? date('Y-m-d H:i:s'),
    'approval_date'   => $submission['approval_date'] ?? null,
    'approved_by'     => $submission['approved_by'] ?? null,
    'rejected_by'     => $submission['rejected_by'] ?? null,
    'rejection_reason'=> $submission['rejection_reason'] ?? null,
], 'submission_code');

// Notify every administrator (best-effort; the submission stays valid if mail fails).
// Recipients are resolved ONLY from the users table (role = admin) via the shared
// MailService::adminEmails() — never from config or .env. Admins without a valid
// e-mail are skipped silently; if no admin has an e-mail at all, a warning is
// logged and the submission still completes. The submitting user's e-mail is set
// as the Reply-To header so admin replies reach the person who submitted.
$emailSent = null;
$replyTo   = MailService::userEmail($conn, (string)($user['nrp'] ?? ''));
// "Informasi Pengaju" block — identity of the submitting user (from the DB).
$profile   = MailService::userProfile($conn, (string)($user['nrp'] ?? ''));
$pengaju   = [
    'nama'       => (string)($user['username'] ?? ''),
    'email'      => $profile['email'],
    'departemen' => (string)($submission['department'] ?? ($submission['asset_type'] ?? '')),
    'role'       => $profile['role'] ?: strtoupper((string)($user['role'] ?? '')),
    'tanggal'    => (string)($submission['submission_date'] ?? date('Y-m-d H:i:s')),
];
try {
    $adminEmails = MailService::adminEmails($conn);

    if (empty($adminEmails)) {
        error_log('[finish_stocktaking] No admin e-mail address found in users table; admin notification skipped.');
        AuditService::log($conn, 'Notification Warning', 'stocktaking_submissions', (int)($submission['id'] ?? 0), [
            'submission_code' => $submission['submission_code'] ?? null,
            'reason'          => 'No admin e-mail address in users table; admin notification not sent.',
        ]);
    } else {
        $mailer    = MailService::instance();
        $sentCount = 0;
        foreach ($adminEmails as $adminEmail) {
            if ($mailer->sendStocktakingApproval($adminEmail, $submission, $submission['assets'] ?? [], $replyTo, $pengaju)) {
                $sentCount++;
            }
        }
        $emailSent = ($sentCount > 0);
        if ($sentCount < count($adminEmails)) {
            error_log('[finish_stocktaking] Approval notification sent to ' . $sentCount . '/' . count($adminEmails) . ' admins for submission #' . ($submission['id'] ?? 0));
        }
    }
} catch (\Throwable $e) {
    // E-mail must never break the submission response — log and continue.
    error_log('[finish_stocktaking] Email notification failed: ' . $e->getMessage());
}

$message = $isResubmit
    ? ($emailSent
        ? 'Stocktaking selesai. Pengajuan telah dikirim ulang ke admin untuk persetujuan.'
        : 'Stocktaking selesai. Pengajuan dikirim ulang (notifikasi e-mail tidak terkirim).')
    : ($emailSent
        ? 'Stocktaking selesai. Pengajuan telah dikirim ke admin untuk persetujuan.'
        : 'Stocktaking selesai. Menunggu persetujuan admin (notifikasi e-mail tidak terkirim).');

json_response([
    'status'     => 'success',
    'message'    => $message,
    'data'       => $submission,
    'email_sent' => $emailSent,
]);
