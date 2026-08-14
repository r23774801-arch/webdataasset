<?php
/**
 * "User Registration Approved / Rejected" e-mail body — rendered inside the
 * shared layout (app/views/emails/layout.php). No HTML shell / header /
 * footer here.
 *
 * Expected variables (set by MailService):
 *   $user (nrp, username, nama_lengkap, email), $config, $logo_url,
 *   $approval_status, $rejection_reason, $reviewed_by, $review_date
 */
$user = (array)($user ?? []);
$nrp         = trim((string)($user['nrp'] ?? ''));
$username    = trim((string)($user['username'] ?? ''));
$namaLengkap = trim((string)($user['nama_lengkap'] ?? ''));
$email       = trim((string)($user['email'] ?? ''));

$approvalStatus = $approval_status ?? 'Rejected';
$isApproved = ($approvalStatus === 'Approved');

$statusColor = $isApproved ? '#2E7D32' : '#C62828';
$statusBg    = $isApproved ? '#E8F5E9' : '#FFEBEE';
$statusLabel = $isApproved ? 'Approved' : 'Rejected';

$titleText = $isApproved ? 'User Registration Approved' : 'User Registration Rejected';
$greetingLine = $isApproved
    ? 'Your registration request has been approved. Your account is now active and you can log in to the system.'
    : 'Your registration request has been rejected. Please contact the administrator if you believe this is a mistake.';

$reasonText = trim((string)($rejection_reason ?? ''));
$reviewedBy = trim((string)($reviewed_by ?? ''));
$reviewDate = trim((string)($review_date ?? ''));

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
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7;">Dear <?php echo e($namaLengkap !== '' ? $namaLengkap : ($username !== '' ? $username : 'User')); ?>,</div>
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7; margin-top:6px;"><?php echo e($greetingLine); ?></div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ ACCOUNT INFORMATION ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">Account Information</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 36px 0 36px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAFAFA; border:1px solid #EEF0F2; border-radius:10px;">
                                            <tr>
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #F1F3F5;">Username</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600; border-bottom:1px solid #F1F3F5;"><?php echo e($username !== '' ? $username : '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #F1F3F5;">NRP</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600; border-bottom:1px solid #F1F3F5;"><?php echo e($nrp !== '' ? $nrp : '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #F1F3F5;">E-mail</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600; border-bottom:1px solid #F1F3F5;"><?php echo e($email !== '' ? $email : '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #F1F3F5;">Status</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600; border-bottom:1px solid #F1F3F5;"><?php echo e($statusLabel); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px;">Reviewed By</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600;"><?php echo e($reviewedBy !== '' ? $reviewedBy : '-'); ?></td>
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
<?php
$title   = $titleText;
$content = ob_get_clean();

include __DIR__ . '/layout.php';
