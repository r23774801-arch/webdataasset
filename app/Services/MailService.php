<?php
/**
 * MailService — the only place that talks to PHPMailer.
 * All SMTP settings come from config/mail.php (single source of truth).
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../helpers.php'; // provides e() used by e-mail templates

use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    /** CID used for the embedded United Tractors logo in every e-mail. */
    public const LOGO_CID = 'utlogo';

    /** CID used for an attached photo shown inline (barang / asset e-mails). */
    public const PHOTO_CID = 'txphoto';

    /** @var MailService|null */
    private static $instance = null;

    /** @var array */
    private $config;

    private function __construct()
    {
        $this->config = mail_config();
    }

    public static function instance(): MailService
    {
        if (self::$instance === null) {
            self::$instance = new MailService();
        }
        return self::$instance;
    }

    /**
     * Resolve the e-mail addresses of every administrator from the users table
     * (role = admin), validated with FILTER_VALIDATE_EMAIL and deduplicated.
     *
     * This is the shared administrator lookup used by the notification
     * endpoints (e.g. Barang transaction notifications). Recipients are
     * resolved ONLY from the users table — never from config or .env.
     */
    public static function adminEmails(mysqli $conn): array
    {
        $emails = [];
        $result = $conn->query("SELECT nrp, email FROM users WHERE LOWER(TRIM(role)) = 'admin'");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $email = trim((string)($admin['email'] ?? ''));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
        }
        return array_values(array_unique($emails));
    }

    /**
     * Resolve a single user's e-mail address by NRP from the users table.
     *
     * This is the shared lookup used to notify the user who performed an
     * action (asset/barang creation, stocktaking submission). Recipients are
     * resolved ONLY from the users table — never from config or .env.
     *
     * Returns '' when the user has no valid e-mail on record.
     */
    public static function userEmail(mysqli $conn, string $nrp): string
    {
        $nrp = trim((string)$nrp);
        if ($nrp === '') {
            return '';
        }
        try {
            $stmt = $conn->prepare('SELECT email FROM users WHERE nrp = ? LIMIT 1');
            if (!$stmt) {
                return '';
            }
            $stmt->bind_param('s', $nrp);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (\Throwable $t) {
            error_log('[MailService] userEmail lookup failed for nrp ' . $nrp . ': ' . $t->getMessage());
            return '';
        }
        $email = trim((string)($row['email'] ?? ''));
        return ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : '';
    }

    /**
     * Resolve the full profile (name, e-mail, role) of a user by NRP from the
     * users table. Used for the "Informasi Pengaju" block in every e-mail.
     *
     * The e-mail is the user's address stored in the database — never the
     * SMTP account. Returns empty strings for any field that is missing.
     */
    public static function userProfile(mysqli $conn, string $nrp): array
    {
        $nrp = trim((string)$nrp);
        if ($nrp === '') {
            return ['nama' => '', 'email' => '', 'role' => ''];
        }
        try {
            $stmt = $conn->prepare('SELECT username, email, role FROM users WHERE nrp = ? LIMIT 1');
            if (!$stmt) {
                return ['nama' => '', 'email' => '', 'role' => ''];
            }
            $stmt->bind_param('s', $nrp);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (\Throwable $t) {
            error_log('[MailService] userProfile lookup failed for nrp ' . $nrp . ': ' . $t->getMessage());
            return ['nama' => '', 'email' => '', 'role' => ''];
        }
        $row   = $row ?? [];
        $email = trim((string)($row['email'] ?? ''));
        return [
            'nama'  => trim((string)($row['username'] ?? '')),
            'email' => ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : '',
            'role'  => strtoupper(trim((string)($row['role'] ?? ''))),
        ];
    }

    /**
     * Build the "Informasi Pengaju" block for a template from a data array.
     * The e-mail is the acting user's address from the DB (never SMTP).
     */
    private function buildPengaju(array $data, array $extra = []): array
    {
        return [
            'nama'       => (string)($data['user_name'] ?? $data['submitted_by_name'] ?? ($extra['nama'] ?? '')),
            'email'      => (string)($data['user_email'] ?? ($extra['email'] ?? '')),
            'departemen' => (string)($data['department'] ?? $data['asset_type'] ?? ($extra['departemen'] ?? '')),
            'role'       => (string)($data['user_role'] ?? ($extra['role'] ?? '')),
            'tanggal'    => (string)($data['timestamp'] ?? $data['submission_date'] ?? $data['tanggal'] ?? ($extra['tanggal'] ?? '')),
        ];
    }

    /**
     * Resolve the recipient list for a user-triggered notification.
     *
     * The acting user's own e-mail (resolved from the users table) is the
     * intended recipient — the SMTP account is ONLY ever the sender. If the
     * acting user has no valid e-mail on record, fall back to the
     * administrators so the notification is still delivered somewhere.
     */
    private static function recipientForActor(mysqli $conn, string $actorNrp): array
    {
        $actorEmail = self::userEmail($conn, $actorNrp);
        if ($actorEmail !== '') {
            return [$actorEmail];
        }
        error_log('[MailService] recipientForActor: acting user (nrp=' . $actorNrp . ') has no e-mail on record; falling back to administrators.');
        return self::adminEmails($conn);
    }

    /**
     * Build a configured PHPMailer instance. Supports TLS and SSL.
     *
     * The sender (From) is ALWAYS the identity configured in .env via
     * MAIL_FROM_ADDRESS / MAIL_FROM_NAME — never the SMTP account. The SMTP
     * account is used exclusively for authentication and is never a recipient.
     *
     * When $replyTo is provided (e.g. the e-mail of the user who performed the
     * action, resolved live from the users table), it is set as the Reply-To
     * header so replies reach that person instead of the sender.
     */
    private function createMailer(?string $replyTo = null): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $this->config['smtp_host'];
        $mail->SMTPAuth   = ($this->config['smtp_username'] !== '');
        $mail->Username   = $this->config['smtp_username'];
        $mail->Password   = $this->config['smtp_password'];
        $mail->SMTPSecure = ($this->config['smtp_encryption'] === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->config['smtp_port'];
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        // Sender is the configured brand identity (MAIL_FROM_ADDRESS/NAME).
        // Fall back to the SMTP account only when MAIL_FROM_ADDRESS is empty.
        $fromEmail = trim((string)$this->config['sender_email']);
        $fromName  = trim((string)$this->config['sender_name']);
        if ($fromEmail === '') {
            $fromEmail = (string)$this->config['smtp_username'];
        }
        if ($fromEmail !== '') {
            $mail->setFrom($fromEmail, $fromName !== '' ? $fromName : $fromEmail);
        }

        // Reply-To = the person who performed the action (never the sender).
        $replyTo = trim((string)$replyTo);
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }

        return $mail;
    }

    /**
     * Absolute filesystem path of the UT logo used for AddEmbeddedImage().
     * Returns '' when the file does not exist.
     */
    private function logoPath(): string
    {
        $path = __DIR__ . '/../../img/logo.png';
        return is_file($path) ? realpath($path) : '';
    }

    /**
     * Resolve a stored photo path (relative to the app root, e.g.
     * "uploads/abc.jpg" or "img/xyz.png") to an absolute filesystem path.
     * Returns '' when the file does not exist.
     */
    private function resolveMediaPath(string $relPath): string
    {
        $relPath = trim((string)$relPath);
        if ($relPath === '') {
            return '';
        }
        $path = __DIR__ . '/../../' . ltrim($relPath, '/');
        return is_file($path) ? realpath($path) : '';
    }

    /**
     * Embed the UT logo (AddEmbeddedImage) so it renders via cid:utlogo.
     * Never throws — when the file is missing the mail still sends.
     */
    private function attachLogo(PHPMailer $mail): void
    {
        $path = $this->logoPath();
        if ($path === '') {
            error_log('[MailService] logo file not found; embedding skipped.');
            return;
        }
        try {
            $mail->AddEmbeddedImage($path, self::LOGO_CID, 'ut-logo.png', 'base64', 'image/png');
        } catch (\Throwable $e) {
            error_log('[MailService] failed to embed logo: ' . $e->getMessage());
        }
    }

    /**
     * Attach a photo as a file attachment AND embed it inline (cid:txphoto)
     * when the file exists. Never throws — missing files are skipped.
     */
    private function attachPhoto(PHPMailer $mail, string $relPath): void
    {
        $path = $this->resolveMediaPath($relPath);
        if ($path === '') {
            error_log('[MailService] photo file not found; attachment skipped: ' . $relPath);
            return;
        }
        try {
            $mail->AddEmbeddedImage($path, self::PHOTO_CID, basename($path), 'base64', 'image/png');
            $mail->addAttachment($path, basename($path));
        } catch (\Throwable $e) {
            error_log('[MailService] failed to attach photo: ' . $e->getMessage());
        }
    }

    /**
     * Render a reusable HTML e-mail template with the given data.
     *
     * Two template styles are supported:
     *  - Templates that echo directly into the output buffer (most, e.g.
     *    stocktaking_result.php, which includes layout.php).
     *  - Templates that RETURN their body string (user_registration.php).
     * The buffered output wins; the include return value is the fallback.
     */
    public function renderTemplate(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        $included = include __DIR__ . '/../views/emails/' . $template;
        $buffered = (string) ob_get_clean();
        if (trim($buffered) !== '') {
            return $buffered;
        }
        return is_string($included) ? $included : $buffered;
    }

    /**
     * Send an HTML e-mail. Never throws — failures are logged and reported.
     *
     * @param string      $to           recipient e-mail address
     * @param string      $subject      subject line
     * @param string      $html         HTML body
     * @param string|null $replyTo      optional Reply-To address (e.g. acting user)
     * @param string      $photoRelPath optional stored photo path (uploads/...) to
     *                                  attach as a file and embed inline (cid:txphoto)
     */
    public function send(string $to, string $subject, string $html, ?string $replyTo = null, string $photoRelPath = ''): bool
    {
        try {
            $mail = $this->createMailer($replyTo);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $this->attachLogo($mail);
            $this->attachPhoto($mail, $photoRelPath);
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('[MailService] send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the "Stocktaking Approval Request" notification to one administrator.
     *
     * The recipient MUST be resolved by the caller from the users table
     * (every row whose role is admin) — never from config or .env.
     *
     * @param string $to          recipient e-mail address (must be non-empty)
     * @param array  $submission  stocktaking submission row
     * @param array  $assets      snapshot of the submitted assets
     * @param string|null $replyTo optional Reply-To address (submitting user)
     * @param array  $pengaju     optional pre-built "Informasi Pengaju" block
     */
    public function sendStocktakingApproval(string $to, array $submission, array $assets = [], ?string $replyTo = null, array $pengaju = []): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendStocktakingApproval skipped: recipient e-mail is empty.');
            return false;
        }
        try {
            $config   = mail_config();
            $baseUrl  = $config['app_url'];
            $reviewId = urlencode((string)($submission['id'] ?? ''));

            $html = $this->renderTemplate('stocktaking_approval.php', [
                'submission'      => $submission,
                'assets'          => $assets,
                'config'          => $config,
                'logo_url'        => $this->logoPath() !== '' ? 'cid:' . self::LOGO_CID : '',
                'approval_status' => $submission['status'] ?? 'Pending',
                'review_url'      => $baseUrl !== ''
                    ? $baseUrl . '/approval.html?id=' . $reviewId
                    : 'approval.html?id=' . $reviewId,
                'pengaju'         => $this->buildPengaju($submission, $pengaju),
            ]);

            return $this->send($to, 'Stocktaking Approval Request', $html, $replyTo);
        } catch (\Throwable $e) {
            // E-mail must never break the caller — log and report failure.
            error_log('[MailService] sendStocktakingApproval failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the "Stocktaking Approved / Rejected" result notification to the
     * submitting user after an administrator changes the approval status.
     *
     * @param string $to          recipient e-mail address (must be non-empty)
     * @param array  $submission  stocktaking submission row
     * @param array  $assets      snapshot of the submitted assets
     * @param array  $pengaju     optional pre-built "Informasi Pengaju" block
     */
    public function sendStocktakingResult(string $to, array $submission, array $assets = [], array $pengaju = []): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendStocktakingResult skipped: recipient e-mail is empty.');
            return false;
        }

        $status = $submission['status'] ?? '';

        if ($status === 'Approved') {
            $subject = 'Stocktaking Approved';
        } elseif ($status === 'Rejected') {
            $subject = 'Stocktaking Rejected';
        } else {
            error_log('[MailService] sendStocktakingResult skipped for status: ' . $status);
            return false;
        }
        try {
            $config = mail_config();

            $html = $this->renderTemplate('stocktaking_result.php', [
                'submission'      => $submission,
                'assets'          => $assets,
                'config'          => $config,
                'logo_url'        => $this->logoPath() !== '' ? 'cid:' . self::LOGO_CID : '',
                'approval_status' => $status,
                'pengaju'         => $this->buildPengaju($submission, $pengaju),
            ]);

            return $this->send($to, $subject, $html);
        } catch (\Throwable $e) {
            // E-mail must never break the caller — log and report failure.
            error_log('[MailService] sendStocktakingResult failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the "New Barang Transaction" notification to one administrator.
     *
     * The recipient MUST be resolved by the caller (e.g. via adminEmails())
     * from the users table — never from config or .env.
     *
     * @param string $to recipient e-mail address (must be non-empty)
     * @param array  $tx  transaction data; keys: module (masuk/keluar),
     *                    department (IT/GA), asset_number, nomor_tiket,
     *                    asset_name, jumlah, supplier, pic, area, tanggal,
     *                    user_name, user_nrp, timestamp
     * @param string|null $replyTo optional Reply-To address (acting user)
     */
    public function sendBarangTransaction(string $to, array $tx, ?string $replyTo = null): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendBarangTransaction skipped: recipient e-mail is empty.');
            return false;
        }

        $module     = strtolower((string)($tx['module'] ?? 'masuk'));
        $department = strtoupper((string)($tx['department'] ?? ''));
        $typeLabel  = $module === 'keluar' ? 'Keluar' : 'Masuk';
        $subject    = trim('New Barang ' . $typeLabel . ' ' . $department . ' Transaction');

        try {
            $config  = mail_config();

            $txPhoto = trim((string)($tx['attachment'] ?? ''));
            $html = $this->renderTemplate('barang_transaction.php', [
                'tx'       => $tx,
                'config'   => $config,
                'logo_url' => $this->logoPath() !== '' ? 'cid:' . self::LOGO_CID : '',
                'photo_cid' => ($txPhoto !== '' && $this->resolveMediaPath($txPhoto) !== '')
                    ? self::PHOTO_CID
                    : '',
                'pengaju'  => $this->buildPengaju($tx),
            ]);

            return $this->send($to, $subject, $html, $replyTo, $txPhoto);
        } catch (\Throwable $e) {
            // E-mail must never break the caller — log and report failure.
            error_log('[MailService] sendBarangTransaction failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Notify about a new Barang transaction.
     *
     * Shared helper for the Barang create endpoints (Masuk/Keluar x IT/GA).
     * The recipient is the acting user (resolved from the users table by
     * their NRP); administrators are only used as a fallback when the acting
     * user has no e-mail on record.
     *
     * Best-effort: a mail failure is logged by send() and never throws, so
     * the transaction is never affected. Returns the number of successfully
     * delivered notifications.
     *
     * @param string $actorNrp NRP of the logged-in user who created the transaction
     */
    public static function notifyBarangTransaction(mysqli $conn, array $tx, string $actorNrp = ''): int
    {
        $mailer     = self::instance();
        $recipients = self::recipientForActor($conn, $actorNrp);
        $sentCount  = 0;
        foreach ($recipients as $recipient) {
            if ($mailer->sendBarangTransaction($recipient, $tx)) {
                $sentCount++;
            }
        }
        return $sentCount;
    }

    /**
     * Send the "Password Reset Request" notification to one administrator.
     *
     * Triggered from the Login page when a user forgets their password. The
     * recipient MUST be resolved by the caller (e.g. via adminEmails()) from
     * the users table — never from config or .env. The admin then looks the
     * user up in the Data Akun page and changes their password.
     *
     * @param string $to   recipient e-mail address (must be non-empty)
     * @param array  $user user data; keys: nrp, username, email, role
     */
    public function sendPasswordResetRequest(string $to, array $user): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendPasswordResetRequest skipped: recipient e-mail is empty.');
            return false;
        }

        $subject = 'Password Reset Request';

        try {
            $config = mail_config();

            $html = $this->renderTemplate('password_reset_request.php', [
                'user'     => $user,
                'config'   => $config,
                'logo_url' => $this->logoPath() !== '' ? 'cid:' . self::LOGO_CID : '',
            ]);

            return $this->send($to, $subject, $html);
        } catch (\Throwable $e) {
            // E-mail must never break the caller — log and report failure.
            error_log('[MailService] sendPasswordResetRequest failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the "Password Changed" notification to the user after an
     * administrator changes their password on the Data Akun page.
     *
     * The recipient MUST be the user's own e-mail address resolved from the
     * users table (never the SMTP account). Best-effort: a failure is logged
     * and never breaks the caller's password-update flow.
     *
     * @param string $to           recipient e-mail address (must be non-empty)
     * @param array  $user         user data; keys: nrp, username, nama_lengkap, email
     * @param string $newPassword  the plaintext new password to send
     */
    public function sendPasswordChanged(string $to, array $user, string $newPassword): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendPasswordChanged skipped: recipient e-mail is empty.');
            return false;
        }

        $subject = 'Password Anda Telah Diubah';

        try {
            $config = mail_config();

            $html = $this->renderTemplate('password_changed.php', [
                'user'         => $user,
                'config'       => $config,
                'logo_url'     => $this->logoPath() !== '' ? 'cid:' . self::LOGO_CID : '',
                'new_password' => (string)$newPassword,
                'pengaju'      => $this->buildPengaju($user, [
                    'nama'    => (string)($user['nama_lengkap'] ?? $user['username'] ?? ''),
                    'email'   => (string)($user['email'] ?? ''),
                    'role'    => 'User',
                    'tanggal' => date('Y-m-d H:i:s'),
                ]),
            ]);

            return $this->send($to, $subject, $html);
        } catch (\Throwable $e) {
            // E-mail must never break the caller — log and report failure.
            error_log('[MailService] sendPasswordChanged failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the "New Asset Created" notification to one administrator.
     *
     * The recipient MUST be resolved by the caller (e.g. via adminEmails())
     * from the users table — never from config or .env.
     *
     * @param string $to    recipient e-mail address (must be non-empty)
     * @param array  $asset asset data; keys: asset_type (IT/GA), asset_number,
     *                      nama_barang, serial_number, asset_class (GA),
     *                      pic, area, location_note, utilisasi, date_of_entry,
     *                      attachment (photo path), user_name, user_nrp,
     *                      timestamp
     * @param string|null $replyTo optional Reply-To address (acting user)
     */
    public function sendAssetCreated(string $to, array $asset, ?string $replyTo = null): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendAssetCreated skipped: recipient e-mail is empty.');
            return false;
        }

        $assetType = strtoupper((string)($asset['asset_type'] ?? ''));
        $subject   = trim('New Asset ' . $assetType . ' Created');

        try {
            $config  = mail_config();

            $assetPhoto = trim((string)($asset['attachment'] ?? ''));
            $html = $this->renderTemplate('asset_created.php', [
                'asset'    => $asset,
                'config'   => $config,
                'logo_url' => $this->logoPath() !== '' ? 'cid:' . self::LOGO_CID : '',
                'photo_cid' => ($assetPhoto !== '' && $this->resolveMediaPath($assetPhoto) !== '')
                    ? self::PHOTO_CID
                    : '',
                'pengaju'  => $this->buildPengaju($asset),
            ]);

            return $this->send($to, $subject, $html, $replyTo, $assetPhoto);
        } catch (\Throwable $e) {
            // E-mail must never break the caller — log and report failure.
            error_log('[MailService] sendAssetCreated failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Notify about a newly created asset (IT or GA).
     *
     * Shared helper for the asset create endpoints. The recipient is the
     * acting user (resolved from the users table by their NRP);
     * administrators are only used as a fallback when the acting user has no
     * e-mail on record.
     *
     * Best-effort: a mail failure is logged by send() and never throws, so
     * the asset insert is never affected. Returns the number of successfully
     * delivered notifications.
     *
     * @param string $actorNrp NRP of the logged-in user who created the asset
     */
    public static function notifyAssetCreated(mysqli $conn, array $asset, string $actorNrp = ''): int
    {
        $mailer     = self::instance();
        $recipients = self::recipientForActor($conn, $actorNrp);
        $sentCount  = 0;
        foreach ($recipients as $recipient) {
            if ($mailer->sendAssetCreated($recipient, $asset)) {
                $sentCount++;
            }
        }
        return $sentCount;
    }

    /**
     * Notify every administrator about a newly created asset (IT or GA).
     *
     * The recipient list is resolved LIVE from the users table via
     * adminEmails() (every row whose role is admin) — never from config or
     * .env. Because the lookup reads the current users.email value, an admin
     * who changes their e-mail on the Profile page automatically receives
     * subsequent notifications at the new address.
     *
     * The acting user's e-mail (from asset['user_nrp']) is set as the
     * Reply-To header, so replies reach the person who created the asset.
     *
     * Never throws: per-recipient failures are caught and logged, so the
     * caller's flow (asset insert) is never affected. Returns the number of
     * successfully delivered notifications.
     */
    public static function notifyAdminsAssetCreated(mysqli $conn, array $asset): int
    {
        $mailer     = self::instance();
        $profile    = self::userProfile($conn, (string)($asset['user_nrp'] ?? ''));
        $asset['user_email'] = $profile['email'];
        $asset['user_role']  = $profile['role'];
        $replyTo    = self::userEmail($conn, (string)($asset['user_nrp'] ?? ''));
        $recipients = self::adminEmails($conn);
        $sentCount  = 0;
        foreach ($recipients as $recipient) {
            try {
                if ($mailer->sendAssetCreated($recipient, $asset, $replyTo)) {
                    $sentCount++;
                }
            } catch (\Throwable $t) {
                error_log('[MailService] notifyAdminsAssetCreated failed for ' . $recipient . ': ' . $t->getMessage());
            }
        }
        return $sentCount;
    }

    /**
     * Notify every administrator about a new Barang transaction
     * (Masuk/Keluar x IT/GA).
     *
     * Same live-users-table contract as notifyAdminsAssetCreated(): recipients
     * are resolved via adminEmails() only, so Profile e-mail changes are
     * picked up automatically. The acting user's e-mail (from tx['user_nrp'])
     * is set as the Reply-To header. Never throws; returns the number of
     * delivered notifications.
     */
    public static function notifyAdminsBarangTransaction(mysqli $conn, array $tx): int
    {
        $mailer     = self::instance();
        $profile    = self::userProfile($conn, (string)($tx['user_nrp'] ?? ''));
        $tx['user_email'] = $profile['email'];
        $tx['user_role']  = $profile['role'];
        $replyTo    = self::userEmail($conn, (string)($tx['user_nrp'] ?? ''));
        $recipients = self::adminEmails($conn);
        $sentCount  = 0;
        foreach ($recipients as $recipient) {
            try {
                if ($mailer->sendBarangTransaction($recipient, $tx, $replyTo)) {
                    $sentCount++;
                }
            } catch (\Throwable $t) {
                error_log('[MailService] notifyAdminsBarangTransaction failed for ' . $recipient . ': ' . $t->getMessage());
            }
        }
        return $sentCount;
    }

    /**
     * Send the "New User Registration Request" e-mail to every administrator.
     *
     * Recipients are resolved LIVE from the users table via adminEmails()
     * (every row whose role is admin) — never from config or .env. This is
     * called right after a registration lands in user_approvals, so admins can
     * open Data Akun -> Persetujuan User and approve/reject the request.
     *
     * Never throws; per-recipient failures are caught and logged. Returns the
     * number of successfully delivered notifications.
     */
    public static function notifyAdminsUserRegistration(mysqli $conn, array $user): int
    {
        $mailer     = self::instance();
        $recipients = self::adminEmails($conn);
        $sentCount  = 0;

        $body = $mailer->renderTemplate('user_registration.php', [
            'user'     => $user,
            'config'   => mail_config(),
            'logo_url' => $mailer->logoPath() !== '' ? 'cid:' . self::LOGO_CID : '',
            'pengaju'  => $mailer->buildPengaju($user, [
                'nama'       => (string)($user['nama_lengkap'] ?? $user['username'] ?? ''),
                'email'      => (string)($user['email'] ?? ''),
                'role'       => 'User',
                'tanggal'    => date('Y-m-d H:i:s'),
            ]),
        ]);

        foreach ($recipients as $recipient) {
            try {
                $mail = $mailer->createMailer();
                $mail->addAddress($recipient);
                $mail->Subject = '[Web Data Aset] Permintaan Registrasi User Baru';
                $mail->Body    = $body;
                $mailer->attachLogo($mail);
                $mail->send();
                $sentCount++;
            } catch (\Throwable $t) {
                error_log('[MailService] notifyAdminsUserRegistration failed for ' . $recipient . ': ' . $t->getMessage());
            }
        }
        return $sentCount;
    }

    /**
     * Send the "User Registration Approved / Rejected" result notification to
     * the registering user after an administrator approves or rejects their
     * registration request on the Data Akun -> Persetujuan User page.
     *
     * Best-effort: failures are logged and never break the caller. Returns
     * false when the recipient e-mail is empty or the send fails.
     *
     * @param string $to             recipient e-mail address (must be non-empty)
     * @param array  $user           user data; keys: nrp, username, nama_lengkap, email
     * @param string $status         'Approved' or 'Rejected'
     * @param string $reason         rejection reason (only used when Rejected)
     * @param string $reviewedByName name of the administrator who reviewed the request
     * @param string $reviewDate     review timestamp
     */
    public function sendUserRegistrationResult(string $to, array $user, string $status, string $reason = '', string $reviewedByName = '', string $reviewDate = ''): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendUserRegistrationResult skipped: recipient e-mail is empty.');
            return false;
        }

        $status = ($status === 'Approved') ? 'Approved' : 'Rejected';
        $subject = ($status === 'Approved')
            ? 'User Registration Approved'
            : 'User Registration Rejected';

        try {
            $config = mail_config();

            $html = $this->renderTemplate('user_registration_result.php', [
                'user'             => $user,
                'config'           => $config,
                'logo_url'         => $this->logoPath() !== '' ? 'cid:' . self::LOGO_CID : '',
                'approval_status'  => $status,
                'rejection_reason' => trim((string)$reason),
                'reviewed_by'      => trim((string)$reviewedByName),
                'review_date'      => trim((string)$reviewDate),
            ]);

            return $this->send($to, $subject, $html);
        } catch (\Throwable $e) {
            error_log('[MailService] sendUserRegistrationResult failed: ' . $e->getMessage());
            return false;
        }
    }
}
