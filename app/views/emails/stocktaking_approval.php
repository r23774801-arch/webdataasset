<?php
/**
 * "Stocktaking Approval Request" e-mail body — rendered inside the shared
 * layout (app/views/emails/layout.php). No HTML shell / header / footer here.
 *
 * Expected variables (set by MailService):
 *   $submission, $assets, $config, $logo_url, $approval_status, $review_url
 */
$sub = $submission ?? [];
$assetRows = $assets ?? [];

$approvalStatus = $approval_status ?? 'Pending';
$statusColor = ($approvalStatus === 'Approved') ? '#2E7D32' : (($approvalStatus === 'Rejected') ? '#C62828' : '#9A7300');
$statusBg    = ($approvalStatus === 'Approved') ? '#E8F5E9' : (($approvalStatus === 'Rejected') ? '#FFEBEE' : '#FFF4CC');
$statusLabel = ($approvalStatus === 'Approved') ? 'Approved' : (($approvalStatus === 'Rejected') ? 'Rejected' : 'Pending');
$reviewUrl   = trim((string)($review_url ?? ''));

ob_start();
?>
                            <!-- ============ STATUS BADGE ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:8px 36px 0 36px;">
                                        <span style="display:inline-block; background-color:<?php echo $statusBg; ?>; color:<?php echo $statusColor; ?>; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:6px 14px; border-radius:20px;">Approval Status: <?php echo e($statusLabel); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 36px 0 36px;">
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7;">Dear Administrator,</div>
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7; margin-top:6px;">A stocktaking submission has been completed and is awaiting your review and approval.</div>
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
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Submission Time</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e(isset($sub['submission_date']) ? date('H:i:s', strtotime($sub['submission_date'])) : '-'); ?></div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

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

                            <!-- ============ DETAIL TABLE ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:28px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">Stocktaking Details</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 36px 0 36px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                            <thead>
                                                <tr style="background-color:#1F1F1F;">
                                                    <?php $headers = ['Asset Number', 'Serial Number', 'Brand', 'Area', 'Department', 'Condition', 'Status']; ?>
                                                    <?php foreach ($headers as $h): ?>
                                                    <th style="color:#FFC20E; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:10px 8px; text-align:left; border-bottom:3px solid #FFC20E;"><?php echo e($h); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($assetRows)): ?>
                                                <tr>
                                                    <td colspan="7" style="padding:14px 8px; color:#6B7280; font-size:13px; border-bottom:1px solid #F1F3F5;">No asset details recorded.</td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($assetRows as $i => $a): ?>
                                                <tr style="background-color:<?php echo ($i % 2 === 0) ? '#FFFFFF' : '#F9FAFB'; ?>;">
                                                    <td style="padding:10px 8px; font-size:12px; color:#1F1F1F; border-bottom:1px solid #F1F3F5;"><?php echo e($a['asset_number'] ?? '-'); ?></td>
                                                    <td style="padding:10px 8px; font-size:12px; color:#374151; border-bottom:1px solid #F1F3F5;"><?php echo e($a['serial_number'] ?? '-'); ?></td>
                                                    <td style="padding:10px 8px; font-size:12px; color:#374151; border-bottom:1px solid #F1F3F5;"><?php echo e($a['nama_barang'] ?? '-'); ?></td>
                                                    <td style="padding:10px 8px; font-size:12px; color:#374151; border-bottom:1px solid #F1F3F5;"><?php echo e($a['area'] ?? '-'); ?></td>
                                                    <td style="padding:10px 8px; font-size:12px; color:#374151; border-bottom:1px solid #F1F3F5;"><?php echo e($a['location_note'] ?? '-'); ?></td>
                                                    <td style="padding:10px 8px; font-size:12px; border-bottom:1px solid #F1F3F5;">
                                                        <?php
                                                        $cond = $a['condition'] ?? '';
                                                        $condBg = '#E5E7EB'; $condColor = '#374151';
                                                        if ($cond === 'Normal') { $condBg = '#DBEAFE'; $condColor = '#1E40AF'; }
                                                        elseif ($cond === 'Broken') { $condBg = '#D1FAE5'; $condColor = '#065F46'; }
                                                        elseif ($cond === 'Lost') { $condBg = '#FEE2E2'; $condColor = '#991B1B'; }
                                                        ?>
                                                        <span style="background-color:<?php echo $condBg; ?>; color:<?php echo $condColor; ?>; font-size:11px; font-weight:700; padding:3px 10px; border-radius:12px;"><?php echo e($cond !== '' ? $cond : '-'); ?></span>
                                                    </td>
                                                    <td style="padding:10px 8px; font-size:12px; color:#374151; border-bottom:1px solid #F1F3F5;"><?php echo e($a['stocktaking_status'] ?? 'Stocktaked'); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <?php if ($reviewUrl !== ''): ?>
                            <!-- ============ REVIEW BUTTON ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding:32px 36px 8px 36px;">
                                        <a href="<?php echo e($reviewUrl); ?>" target="_blank" style="display:inline-block; background-color:#FFC20E; color:#1F1F1F; font-size:14px; font-weight:800; text-decoration:none; padding:14px 36px; border-radius:8px; letter-spacing:0.3px;">Review Submission</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:10px 36px 4px 36px;">
                                        <div style="color:#9CA3AF; font-size:12px;">You will be redirected to the approval page. This e-mail is a notification only.</div>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>
<?php
$title   = 'Stocktaking Approval Request';
$content = ob_get_clean();

include __DIR__ . '/layout.php';
