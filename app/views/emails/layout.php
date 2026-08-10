<?php
/**
 * Shared e-mail layout — United Tractors Asset Management System.
 *
 * Single source of truth for the corporate header / footer used by every
 * notification e-mail (asset, barang, stocktaking approval & result).
 * Content templates render their body into $content, then include this file.
 *
 * Structure (identical for every e-mail):
 *   1. Header — centered UT logo embedded via AddEmbeddedImage (cid:utlogo).
 *   2. Title.
 *   3. "Informasi Pengaju" — the acting user's identity (from the DB, never
 *      the SMTP account).
 *   4. Body (per template).
 *   5. Login button + footer.
 *
 * When the logo file is missing ($logo_url empty) the e-mail still sends —
 * the header simply falls back to the brand text.
 *
 * Expected variables:
 *   $title    heading text shown under the header
 *   $pengaju  array with keys: nama, email, departemen, role, tanggal
 *   $content  body HTML of the card
 *   $logo_url 'cid:utlogo' when the logo is embedded, otherwise ''
 *   $config   mail_config() (used for the login URL)
 */
$title    = $title ?? 'Notification';
$pengaju  = (array)($pengaju ?? []);
$content  = (string)($content ?? '');
$logoUrl  = trim((string)($logo_url ?? ''));
$baseUrl  = trim((string)($config['app_url'] ?? ''));
$loginUrl = $baseUrl !== '' ? $baseUrl . '/login.html' : 'login.html';

$pengajuFields = [
    'Nama'       => (string)($pengaju['nama'] ?? ''),
    'Email'      => (string)($pengaju['email'] ?? ''),
    'Departemen' => (string)($pengaju['departemen'] ?? ''),
    'Role'       => (string)($pengaju['role'] ?? ''),
    'Tanggal'    => (string)($pengaju['tanggal'] ?? ''),
];
$hasPengaju = false;
foreach ($pengajuFields as $val) {
    if (trim($val) !== '') {
        $hasPengaju = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo e($title); ?></title>
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
                                    <td align="center" style="padding:32px 36px 28px 36px;">
                                        <?php if (!empty($logoUrl)): ?>
                                        <!-- Logo inside a white card, matching the Login page presentation
                                             (white background, rounded corners, subtle shadow, centered). -->
                                        <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFFFFF; border-radius:14px;">
                                            <tr>
                                                <td align="center" style="padding:10px 16px; line-height:0; font-size:0;">
                                                    <img src="<?php echo e($logoUrl); ?>" alt="United Tractors" width="170" style="display:inline-block; max-width:170px; width:170px; height:auto; border:0; line-height:0; font-size:0;">
                                                </td>
                                            </tr>
                                        </table>
                                        <?php else: ?>
                                        <div style="color:#FFC20E; font-size:20px; font-weight:800; letter-spacing:0.5px;">United Tractors</div>
                                        <div style="color:#FFFFFF; font-size:13px; opacity:0.75; margin-top:4px;">Asset Management System</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ TITLE ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding:32px 36px 8px 36px;">
                                        <div style="font-size:22px; font-weight:800; color:#1F1F1F; line-height:1.3; text-align:center;"><?php echo e($title); ?></div>
                                    </td>
                                </tr>
                            </table>

                            <?php if ($hasPengaju): ?>
                            <!-- ============ INFORMASI PENGAJU ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">Informasi Pengajuan</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 36px 0 36px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAFAFA; border:1px solid #EEF0F2; border-radius:10px;">
                                            <?php foreach ($pengajuFields as $label => $val): ?>
                                            <tr>
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #F1F3F5;"><?php echo e($label); ?></td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600; border-bottom:1px solid #F1F3F5;"><?php echo e($val !== '' ? $val : '-'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>

                            <!-- ============ BODY (per template) ============ -->
                            <?php echo $content; ?>

                            <!-- ============ LOGIN BUTTON ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding:28px 36px 8px 36px;">
                                        <a href="<?php echo e($loginUrl); ?>" target="_blank" style="display:inline-block; background-color:#1F1F1F; color:#FFC20E; font-size:13px; font-weight:800; text-decoration:none; padding:12px 32px; border-radius:8px; letter-spacing:0.3px;">Login to the System</a>
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
