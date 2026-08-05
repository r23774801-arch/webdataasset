<?php
/**
 * Reusable enterprise HTML e-mail template — United Tractors Asset Management System.
 *
 * Corporate palette:
 *   Primary   : UT Yellow  #FFC20E
 *   Secondary : Dark Charcoal #1F1F1F
 *   Background: White / Light Gray #F5F6F8
 *
 * Expected variables (set by MailService::sendBarangTransaction):
 *   $tx, $config, $logo_url
 */
$tx = $tx ?? [];

$module     = strtolower((string)($tx['module'] ?? 'masuk'));
$department = strtoupper((string)($tx['department'] ?? ''));
$typeLabel  = $module === 'keluar' ? 'Barang Keluar' : 'Barang Masuk';
$hasSupplier = trim((string)($tx['supplier'] ?? '')) !== '';
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>New <?php echo e($typeLabel); ?> Transaction</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F6F8; font-family:'Segoe UI', Arial, Helvetica, sans-serif; -webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F6F8;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <!-- Main Card -->
                <table role="presentation" width="100%" style="max-width:680px; background-color:#FFFFFF; border-radius:12px; overflow:hidden; border:1px solid #E5E7EB;" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td>
                            <!-- ============ HEADER ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#1F1F1F;">
                                <tr>
                                    <td style="padding:28px 36px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <?php if (!empty($logo_url)): ?>
                                                <td style="padding-right:16px; vertical-align:middle;">
                                                    <img src="<?php echo e($logo_url); ?>" alt="United Tractors" width="52" height="52" style="display:block; border:0; border-radius:8px;">
                                                </td>
                                                <?php endif; ?>
                                                <td style="vertical-align:middle;">
                                                    <div style="color:#FFC20E; font-size:16px; font-weight:700; letter-spacing:0.5px;">United Tractors</div>
                                                    <div style="color:#FFFFFF; font-size:13px; opacity:0.75; margin-top:2px;">Asset Management System</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ TITLE ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:32px 36px 8px 36px;">
                                        <div style="font-size:22px; font-weight:800; color:#1F1F1F; line-height:1.3;">New <?php echo e($typeLabel); ?> Transaction</div>
                                        <div style="margin-top:12px;">
                                            <span style="display:inline-block; background-color:#FFF4CC; color:#9A7300; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:6px 14px; border-radius:20px;">Department: <?php echo e($department !== '' ? $department : '-'); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 36px 0 36px;">
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7;">Dear Administrator,</div>
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7; margin-top:6px;">A new <?php echo e($typeLabel); ?> (<?php echo e($department !== '' ? $department : '-'); ?>) transaction has been recorded in the system. Details are below.</div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ TRANSACTION INFORMATION ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">Transaction Information</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 36px 0 36px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="width:50%; padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Transaction Type</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($typeLabel); ?></div>
                                                </td>
                                                <td style="width:50%; padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Department</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($department !== '' ? $department : '-'); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Asset Number</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['asset_number'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Nomor Tiket / Resi</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['nomor_tiket'] ?? '-'); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Asset Name</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['asset_name'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Quantity</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo (int)($tx['jumlah'] ?? 0); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">PIC</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['pic'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Area</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['area'] ?? '-'); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Date</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['tanggal'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">User Name</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['user_name'] ?? '-'); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">User NRP</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['user_nrp'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Submission Timestamp</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['timestamp'] ?? '-'); ?></div>
                                                </td>
                                            </tr>
                                            <?php if ($hasSupplier): ?>
                                            <tr>
                                                <td style="padding:6px 0;" valign="top">
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Supplier</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['supplier'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top"></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ FOOTER ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F6F8;">
                                <tr>
                                    <td align="center" style="padding:24px 36px; border-top:1px solid #E5E7EB;">
                                        <div style="color:#6B7280; font-size:12px; line-height:1.7;">This is an automated notification generated by the<br><strong style="color:#1F1F1F;">United Tractors Asset Management System.</strong><br>Please do not reply to this email.</div>
                                        <div style="color:#9CA3AF; font-size:12px; margin-top:12px;">&copy; PT United Tractors Tbk. All Rights Reserved.</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
