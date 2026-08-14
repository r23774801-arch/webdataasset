<?php
/**
 * "Password Changed" e-mail body — rendered inside the shared layout
 * (app/views/emails/layout.php). No HTML shell / header / footer here.
 *
 * Sent to the user after an administrator changes their password on the
 * Data Akun page, so the user knows their password has been updated and
 * receives the new one.
 *
 * Expected variables (set by MailService):
 *   $user (nrp, username, nama_lengkap, email, role), $config, $logo_url,
 *   $new_password, $pengaju (Informasi Pengajuan block)
 */
$user = (array)($user ?? []);
$nrp          = trim((string)($user['nrp'] ?? ''));
$username     = trim((string)($user['username'] ?? ''));
$nama_lengkap = trim((string)($user['nama_lengkap'] ?? ''));
$new_password = (string)($new_password ?? '');

$greetingName = $nama_lengkap !== '' ? $nama_lengkap : ($username !== '' ? $username : 'User');

ob_start();
?>
                            <!-- ============ INTRO ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:8px 36px 0 36px;">
                                        <span style="display:inline-block; background-color:#E8F5E9; color:#2E7D32; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:6px 14px; border-radius:20px;">Password Changed</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 36px 0 36px;">
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7;">Dear <?php echo e($greetingName); ?>,</div>
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7; margin-top:6px;">Your password has just been changed by the administrator. Please use the new password below to log in to the system.</div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ NEW PASSWORD ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">New Password</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 36px 0 36px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F6F8; border:1px solid #EEF0F2; border-radius:10px;">
                                            <tr>
                                                <td align="center" style="padding:20px 18px;">
                                                    <div style="font-size:22px; font-weight:800; color:#1F1F1F; letter-spacing:1px; background-color:#FFFFFF; border:1px solid #E5E7EB; border-radius:8px; padding:12px 24px; display:inline-block;"><?php echo e($new_password); ?></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td align="center" style="padding:0 18px 18px 18px;">
                                                    <div style="color:#9CA3AF; font-size:12px; line-height:1.6;">Simpan baik-baik password ini. Jangan bagikan kepada siapa pun.</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ ACCOUNT INFO ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">Account</div>
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
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px;">NRP</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600;"><?php echo e($nrp !== '' ? $nrp : '-'); ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
<?php
$title   = 'Password Changed';
$content = ob_get_clean();

include __DIR__ . '/layout.php';