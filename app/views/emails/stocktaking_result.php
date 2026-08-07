<?php
/**
 * "Stocktaking Approved / Rejected" e-mail body — rendered inside the shared
 * layout (app/views/emails/layout.php). No HTML shell / header / footer here.
 *
 * Expected variables (set by MailService):
 *   $submission, $assets, $config, $logo_url, $approval_status
 */
$sub = $submission ?? [];
$assetRows = $assets ?? [];

$approvalStatus = $approval_status ?? 'Pending';
$isApproved = ($approvalStatus === 'Approved');

$statusColor = $isApproved ? '#2E7D32' : '#C62828';
$statusBg    = $isApproved ? '#E8F5E9' : '#FFEBEE';
$statusLabel = $isApproved ? 'Approved' : 'Rejected';

$titleText  = $isApproved ? 'Stocktaking Approved' : 'Stocktaking Rejected';
$greetingLine = $isApproved
    ? 'Your stocktaking submission has been reviewed and approved.'
    : 'Your stocktaking submission has been reviewed and rejected.';

// Label variants per result type.
$dateLabel = $isApproved ? 'Approval Date' : 'Rejection Date';
$byLabel   = $isApproved ? 'Approved By'   : 'Rejected By';

// Who acted + when, depending on the outcome.
$actorValue = $isApproved
    ? ($sub['approved_by_name'] ?? $sub['approved_by'] ?? '-')
    : ($sub['rejected_by_name'] ?? $sub['rejected_by'] ?? '-');
$dateValue = $isApproved
    ? ($sub['approval_date'] ?? '-')
    : ($sub['rejection_date'] ?? '-');
$reasonText = trim((string)($sub['rejection_reason'] ?? ''));

ob_start();
?>
                            <!-- ============ STATUS BADGE ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:8px 36px 0 36px;">
                                        <span style="display:inline-block; background-color:<?php echo $statusBg; ?>; color:<?php echo $statusColor; ?>; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:6px 14px; border-radius:20px;"><?php echo e($statusLabel); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 36px 0 36px;">
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7;">Dear <?php echo e($sub['submitted_by_name'] ?? 'User'); ?>,</div>
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7; margin-top:6px;"><?php echo e($greetingLine); ?></div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ SUBMISSION INFORMATION ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">Submission Information</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 36px 0 36px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="width:33.3%; padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">User Name</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($sub['submitted_by_name'] ?? '-'); ?></div>
                                                </td>
                                                <td style="width:33.3%; padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">NRP</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($sub['submitted_by'] ?? '-'); ?></div>
                                                </td>
                                                <td style="width:33.3%; padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Asset Type</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($sub['asset_type'] ?? '-'); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Nomor Submission</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($sub['submission_code'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Department</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($sub['department'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Area</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($sub['area'] ?? '-'); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Submission Date</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($sub['submission_date'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;"><?php echo e($dateLabel); ?></div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($dateValue); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;"><?php echo e($byLabel); ?></div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($actorValue); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Total Assets</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo (int)($sub['total_assets'] ?? 0); ?></div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <?php if (!$isApproved && $reasonText !== ''): ?>
                            <!-- ============ REJECTION REASON ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">Rejection Reason</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 36px 0 36px;">
                                        <div style="background-color:#FFEBEE; border:1px solid #F5C6CB; border-radius:10px; padding:14px 16px; color:#7F1D1D; font-size:14px; line-height:1.7;">
                                            <?php echo e($reasonText); ?>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>

                            <!-- ============ SUMMARY CARD ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:28px 36px 0 36px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F6F8; border-radius:10px;">
                                            <tr>
                                                <?php
                                                $summary = [
                                                    ['label' => 'Total Assets', 'value' => (int)($sub['total_assets'] ?? 0), 'bg' => '#1F1F1F', 'color' => '#FFFFFF'],
                                                    ['label' => 'Normal',       'value' => (int)($sub['normal_count'] ?? 0), 'bg' => '#1E5AA8', 'color' => '#FFFFFF'],
                                                    ['label' => 'Broken',       'value' => (int)($sub['broken_count'] ?? 0), 'bg' => '#0D9488', 'color' => '#FFFFFF'],
                                                    ['label' => 'Lost',         'value' => (int)($sub['lost_count'] ?? 0),   'bg' => '#DC3545', 'color' => '#FFFFFF'],
                                                    ['label' => 'Pending',      'value' => (int)($sub['pending_count'] ?? 0), 'bg' => '#FFC20E', 'color' => '#1F1F1F'],
                                                ];
                                                foreach ($summary as $item):
                                                ?>
                                                <td align="center" style="padding:18px 8px; width:20%;">
                                                    <div style="background-color:<?php echo $item['bg']; ?>; color:<?php echo $item['color']; ?>; border-radius:8px; padding:12px 6px;">
                                                        <div style="font-size:22px; font-weight:800; line-height:1.1;"><?php echo (int)$item['value']; ?></div>
                                                        <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-top:4px; opacity:0.9;"><?php echo e($item['label']); ?></div>
                                                    </div>
                                                </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
<?php
$title   = $titleText;
$content = ob_get_clean();

include __DIR__ . '/layout.php';
