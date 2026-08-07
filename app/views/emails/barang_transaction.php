<?php
/**
 * "New Barang Transaction" e-mail body — rendered inside the shared layout
 * (app/views/emails/layout.php). No HTML shell / header / footer here; the
 * shared layout owns the corporate header (embedded UT logo) and footer.
 *
 * Expected variables (set by MailService::sendBarangTransaction):
 *   $tx, $config, $logo_url, $photo_cid
 */
$tx = $tx ?? [];

$module     = strtolower((string)($tx['module'] ?? 'masuk'));
$department = strtoupper((string)($tx['department'] ?? ''));
$typeLabel  = $module === 'keluar' ? 'Barang Keluar' : 'Barang Masuk';
$hasSupplier = trim((string)($tx['supplier'] ?? '')) !== '';

// Inline photo is rendered via an embedded CID (cid:txphoto) when the file was
// attached; the template only renders the block when a CID is available.
$photoCid = trim((string)($photo_cid ?? ''));

ob_start();
?>
                            <!-- ============ INTRO ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:8px 36px 0 36px;">
                                        <span style="display:inline-block; background-color:#FFF4CC; color:#9A7300; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:6px 14px; border-radius:20px;">Department: <?php echo e($department !== '' ? $department : '-'); ?></span>
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
                                                    <div style="font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.5px;">Unit</div>
                                                    <div style="font-size:14px; color:#1F1F1F; font-weight:600; margin-top:3px;"><?php echo e($tx['unit'] ?? '-'); ?></div>
                                                </td>
                                                <td style="padding:6px 0;" valign="top"></td>
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

                            <?php if ($photoCid !== ''): ?>
                            <!-- ============ PHOTO ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">Transaction Photo</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:20px 36px 0 36px;">
                                        <img src="cid:<?php echo e($photoCid); ?>" alt="Transaction Photo" style="display:block; max-width:100%; height:auto; border-radius:8px; border:1px solid #E5E7EB;">
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>
<?php
$title   = 'New ' . $typeLabel . ' Transaction';
$content = ob_get_clean();

include __DIR__ . '/layout.php';
