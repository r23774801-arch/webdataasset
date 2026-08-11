<?php
/**
 * "New User Registration Request" e-mail body — rendered inside the shared
 * layout (app/views/emails/layout.php). No HTML shell / header / footer here.
 *
 * Expected variables (set by MailService):
 *   $user (nrp, username, nama_lengkap, email), $config, $logo_url
 */
$user = (array)($user ?? []);
$nrp         = trim((string)($user['nrp'] ?? ''));
$username    = trim((string)($user['username'] ?? ''));
$nama_lengkap = trim((string)($user['nama_lengkap'] ?? ''));
$email       = trim((string)($user['email'] ?? ''));

ob_start();
?>
                            <!-- ============ INTRO ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:8px 36px 0 36px;">
                                        <span style="display:inline-block; background-color:#EEF2FF; color:#4F46E5; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:6px 14px; border-radius:20px;">User Registration Request</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 36px 0 36px;">
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7;">Dear Administrator,</div>
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7; margin-top:6px;">A new account has requested access to the system. Please review the request on the <strong>Data Akun &rarr; Persetujuan User</strong> page and approve or reject it. The account can only log in after being approved.</div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ USER INFORMATION ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="font-size:13px; font-weight:800; color:#1F1F1F; text-transform:uppercase; letter-spacing:0.8px; padding-bottom:10px; border-bottom:3px solid #FFC20E;">User Information</div>
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
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #F1F3F5;">Nama Lengkap</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600; border-bottom:1px solid #F1F3F5;"><?php echo e($nama_lengkap !== '' ? $nama_lengkap : '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px;">E-mail</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600;"><?php echo e($email !== '' ? $email : '-'); ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
<?php
return trim(ob_get_clean());
