<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

// Server-side authorization: only ADMIN can approve/reject.
require_admin();
$user = current_user();

$input = read_json_input();
$id     = (int)($input['id'] ?? 0);
$status = $input['status'] ?? ApprovalService::STATUS_APPROVED;
$reason = trim((string)($input['reason'] ?? ''));

if ($id <= 0) {
    json_response(['status' => 'error', 'message' => 'Data tidak valid.']);
}

// Server-side whitelist: only Pending / Approved / Rejected are accepted.
if (!in_array($status, [ApprovalService::STATUS_PENDING, ApprovalService::STATUS_APPROVED, ApprovalService::STATUS_REJECTED], true)) {
    json_response(['status' => 'error', 'message' => 'Status tidak valid.']);
}

// A rejection without a reason is never accepted (server-side guard).
if ($status === ApprovalService::STATUS_REJECTED && $reason === '') {
    json_response(['status' => 'error', 'message' => 'Alasan penolakan wajib diisi.']);
}

$submission = ApprovalService::getById($conn, $id);
if (!$submission) {
    json_response(['status' => 'error', 'message' => 'Pengajuan tidak ditemukan.']);
}

// Always update the existing record — the status can change at any time.
$updated = ApprovalService::approve($conn, $id, $status, $user['nrp'], $user['username'], $reason);
if (!$updated) {
    json_response(['status' => 'error', 'message' => 'Gagal memperbarui status pengajuan.']);
}

if ($status === ApprovalService::STATUS_APPROVED) {
    $message = 'Pengajuan stocktaking disetujui.';
} elseif ($status === ApprovalService::STATUS_REJECTED) {
    $message = 'Pengajuan stocktaking ditolak.';
} else {
    $message = 'Pengajuan dikembalikan ke status Pending.';
}

// Audit the decision.
AuditService::log(
    $conn,
    $status === ApprovalService::STATUS_APPROVED ? 'Approved Stocktaking' : ($status === ApprovalService::STATUS_REJECTED ? 'Rejected Stocktaking' : 'Reopened Stocktaking'),
    'stocktaking_submissions',
    $id,
    ['submission_code' => $submission['submission_code'] ?? null, 'reason' => $reason]
);

// Mirror the status change to the Approval worksheet (best-effort only).
SpreadsheetService::sync(SpreadsheetService::SHEET_APPROVAL, [
    'submission_code'  => $updated['submission_code'] ?? $submission['submission_code'] ?? null,
    'asset_type'       => $updated['asset_type'] ?? $submission['asset_type'] ?? null,
    'submitted_by'     => $updated['submitted_by'] ?? $submission['submitted_by'] ?? null,
    'submitted_by_name'=> $updated['submitted_by_name'] ?? $submission['submitted_by_name'] ?? null,
    'department'       => $updated['department'] ?? $submission['department'] ?? null,
    'area'             => $updated['area'] ?? $submission['area'] ?? null,
    'total_assets'     => $updated['total_assets'] ?? $submission['total_assets'] ?? 0,
    'normal_count'     => $updated['normal_count'] ?? $submission['normal_count'] ?? 0,
    'broken_count'     => $updated['broken_count'] ?? $submission['broken_count'] ?? 0,
    'lost_count'       => $updated['lost_count'] ?? $submission['lost_count'] ?? 0,
    'pending_count'    => $updated['pending_count'] ?? $submission['pending_count'] ?? 0,
    'status'           => $status,
    'submission_date'  => $updated['submission_date'] ?? $submission['submission_date'] ?? null,
    'approval_date'    => $updated['approval_date'] ?? $submission['approval_date'] ?? null,
    'approved_by'      => $updated['approved_by'] ?? $submission['approved_by'] ?? null,
    'rejected_by'      => $updated['rejected_by'] ?? $submission['rejected_by'] ?? null,
    'rejection_reason' => $reason,
], 'submission_code');

// Notify the submitting user when the status actually changed.
// Best-effort: a missing/invalid e-mail or a send failure is only logged —
// it never cancels or rolls back the approval.
$emailSent = null;
$statusChanged = ($status !== ($submission['status'] ?? ''));
if ($statusChanged && in_array($status, [ApprovalService::STATUS_APPROVED, ApprovalService::STATUS_REJECTED], true)) {
    try {
        $profile = MailService::userProfile($conn, (string)($submission['submitted_by'] ?? ''));
        $userEmail = $profile['email'];

        if ($userEmail === '') {
            error_log('[approve_stocktaking] No valid e-mail on record for user ' . $submission['submitted_by'] . '; result notification skipped.');
            AuditService::log($conn, 'Notification Warning', 'stocktaking_submissions', $id, [
                'submission_code' => $submission['submission_code'] ?? null,
                'status'          => $status,
                'reason'          => 'No valid e-mail on record for user ' . ($submission['submitted_by'] ?? ''),
            ]);
        } else {
            $pengaju = [
                'nama'       => (string)($submission['submitted_by_name'] ?? $profile['nama'] ?? ''),
                'email'      => $userEmail,
                'departemen' => (string)($updated['department'] ?? ($updated['asset_type'] ?? '')),
                'role'       => $profile['role'],
                'tanggal'    => (string)($updated['submission_date'] ?? date('Y-m-d H:i:s')),
            ];
            $emailSent = MailService::instance()->sendStocktakingResult($userEmail, $updated, $updated['assets'] ?? [], $pengaju);
            if (!$emailSent) {
                error_log('[approve_stocktaking] Failed to send result notification to ' . $userEmail . ' for submission #' . $id);
                AuditService::log($conn, 'Notification Warning', 'stocktaking_submissions', $id, [
                    'submission_code' => $submission['submission_code'] ?? null,
                    'status'          => $status,
                    'reason'          => 'Failed to send result notification to ' . $userEmail,
                ]);
            }
        }
    } catch (\Throwable $t) {
        error_log('[approve_stocktaking] Notification lookup failed for submission #' . $id . ': ' . $t->getMessage());
    }
}

json_response([
    'status'     => 'success',
    'message'    => $message,
    'data'       => $updated,
    'email_sent' => $emailSent,
]);
