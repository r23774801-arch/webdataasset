<?php
/**
 * "Password Reset Request" e-mail body — rendered inside the shared layout
 * (app/views/emails/layout.php). No HTML shell / header / footer here.
 *
 * Expected variables (set by MailService):
 *   $user (nrp, username, email, role), $config, $logo_url
 */
$user = (array)($user ?? []);
$nrp      = trim((string)($user['nrp'] ?? ''));
$username = trim((string)($user['username'] ?? ''));
$email    = trim((string)($user['email'] ?? ''));
$role     = strtoupper(trim((string)($user['role'] ?? '')));

ob_start();
?>
                            <!-- ============ INTRO ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:8px 36px 0 36px;">
                                        <span style="display:inline-block; background-color:#FFF4CC; color:#9A7300; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:6px 14px; border-radius:20px;">Password Reset Request</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 36px 0 36px;">
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7;">Dear Administrator,</div>
                                        <div style="color:#4B5563; font-size:14px; line-height:1.7; margin-top:6px;">A user has reported that they forgot their password. Please look up this user in the <strong>Data Akun</strong> page and change their password, then inform them of the new credentials.</div>
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
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #F1F3F5;">Role</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600; border-bottom:1px solid #F1F3F5;"><?php echo e($role !== '' ? $role : '-'); ?></td>
                                            </tr>
                                            <?php if ($email !== ''): ?>
                                            <tr>
                                                <td style="width:32%; padding:10px 18px; font-size:12px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #F1F3F5;">Email</td>
                                                <td style="padding:10px 18px; font-size:14px; color:#1F1F1F; font-weight:600; border-bottom:1px solid #F1F3F5;"><?php echo e($email); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- ============ ACTION ============ -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:24px 36px 0 36px;">
                                        <div style="background-color:#F5F6F8; border:1px solid #EEF0F2; border-radius:10px; padding:18px 20px; color:#4B5563; font-size:14px; line-height:1.7;">
                                            <strong style="color:#1F1F1F;">Langkah:</strong><br>
                                            1. Buka halaman <strong>Data Akun</strong> di portal.<br>
                                            2. Cari user dengan NRP <strong><?php echo e($nrp); ?></strong>.<br>
                                            3. Klik <strong>Ubah Password</strong>, set password baru, lalu beri tahu user tersebut.
                                        </div>
                                    </td>
                                </tr>
                            </table>
<?php
$title   = 'Password Reset Request';
$content = ob_get_clean();

include __DIR__ . '/layout.php';
