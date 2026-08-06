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
     * Build a configured PHPMailer instance. Supports TLS and SSL.
     */
    private function createMailer(): PHPMailer
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

        // The authenticated SMTP account is always the sender. Gmail (and most
        // SMTP relays) rewrite the From header to the authenticated account, so
        // a MAIL_FROM_ADDRESS that differs from SMTP_USERNAME silently delivers
        // a bare address without the intended display name. Prefer
        // sender_email only when it matches the SMTP account (or when no SMTP
        // account is configured); otherwise fall back to the account itself.
        $smtpUser  = $this->config['smtp_username'];
        $fromEmail = $this->config['sender_email'];
        $fromName  = $this->config['sender_name'];
        if ($fromEmail === '' || ($smtpUser !== '' && strcasecmp($fromEmail, $smtpUser) !== 0)) {
            $fromEmail = $smtpUser;
        }
        if ($fromEmail !== '') {
            $mail->setFrom($fromEmail, $fromName !== '' ? $fromName : $fromEmail);
        }

        return $mail;
    }

    /**
     * Render a reusable HTML e-mail template with the given data.
     */
    public function renderTemplate(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include __DIR__ . '/../views/emails/' . $template;
        return (string) ob_get_clean();
    }

    /**
     * Send an HTML e-mail. Never throws — failures are logged and reported.
     */
    public function send(string $to, string $subject, string $html): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $html;
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
     */
    public function sendStocktakingApproval(string $to, array $submission, array $assets = []): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendStocktakingApproval skipped: recipient e-mail is empty.');
            return false;
        }

        $config   = mail_config();
        $baseUrl  = $config['app_url'];
        $reviewId = urlencode((string)($submission['id'] ?? ''));

        $html = $this->renderTemplate('stocktaking_approval.php', [
            'submission'      => $submission,
            'assets'          => $assets,
            'config'          => $config,
            'logo_url'        => $baseUrl !== '' ? $baseUrl . '/img/logo.png' : '',
            'approval_status' => $submission['status'] ?? 'Pending',
            'review_url'      => $baseUrl !== ''
                ? $baseUrl . '/approval.html?id=' . $reviewId
                : 'approval.html?id=' . $reviewId,
        ]);

        return $this->send($to, 'Stocktaking Approval Request', $html);
    }

    /**
     * Send the "Stocktaking Approved / Rejected" result notification to the
     * submitting user after an administrator changes the approval status.
     *
     * @param string $to          recipient e-mail address (must be non-empty)
     * @param array  $submission  stocktaking submission row
     * @param array  $assets      snapshot of the submitted assets
     */
    public function sendStocktakingResult(string $to, array $submission, array $assets = []): bool
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

        $config  = mail_config();
        $baseUrl = $config['app_url'];

        $html = $this->renderTemplate('stocktaking_result.php', [
            'submission'      => $submission,
            'assets'          => $assets,
            'config'          => $config,
            'logo_url'        => $baseUrl !== '' ? $baseUrl . '/img/logo.png' : '',
            'approval_status' => $status,
        ]);

        return $this->send($to, $subject, $html);
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
     */
    public function sendBarangTransaction(string $to, array $tx): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendBarangTransaction skipped: recipient e-mail is empty.');
            return false;
        }

        $module     = strtolower((string)($tx['module'] ?? 'masuk'));
        $department = strtoupper((string)($tx['department'] ?? ''));
        $typeLabel  = $module === 'keluar' ? 'Keluar' : 'Masuk';
        $subject    = trim('New Barang ' . $typeLabel . ' ' . $department . ' Transaction');

        $config  = mail_config();
        $baseUrl = $config['app_url'];

        $html = $this->renderTemplate('barang_transaction.php', [
            'tx'       => $tx,
            'config'   => $config,
            'logo_url' => $baseUrl !== '' ? $baseUrl . '/img/logo.png' : '',
        ]);

        return $this->send($to, $subject, $html);
    }

    /**
     * Notify every administrator about a new Barang transaction.
     *
     * Shared helper for the Barang create endpoints (Masuk/Keluar x IT/GA).
     * Best-effort: a mail failure is logged by send() and never throws, so
     * the transaction is never affected. Returns the number of successfully
     * delivered notifications.
     */
    public static function notifyAdminsBarangTransaction(mysqli $conn, array $tx): int
    {
        $mailer      = self::instance();
        $adminEmails = self::adminEmails($conn);
        $sentCount   = 0;
        foreach ($adminEmails as $adminEmail) {
            if ($mailer->sendBarangTransaction($adminEmail, $tx)) {
                $sentCount++;
            }
        }
        return $sentCount;
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
     */
    public function sendAssetCreated(string $to, array $asset): bool
    {
        if (trim($to) === '') {
            error_log('[MailService] sendAssetCreated skipped: recipient e-mail is empty.');
            return false;
        }

        $assetType = strtoupper((string)($asset['asset_type'] ?? ''));
        $subject   = trim('New Asset ' . $assetType . ' Created');

        $config  = mail_config();
        $baseUrl = $config['app_url'];

        $html = $this->renderTemplate('asset_created.php', [
            'asset'    => $asset,
            'config'   => $config,
            'logo_url' => $baseUrl !== '' ? $baseUrl . '/img/logo.png' : '',
        ]);

        return $this->send($to, $subject, $html);
    }

    /**
     * Notify every administrator about a newly created asset (IT or GA).
     *
     * Shared helper for the asset create endpoints. Best-effort: a mail
     * failure is logged by send() and never throws, so the asset insert is
     * never affected. Returns the number of successfully delivered
     * notifications.
     */
    public static function notifyAdminsAssetCreated(mysqli $conn, array $asset): int
    {
        $mailer      = self::instance();
        $adminEmails = self::adminEmails($conn);
        $sentCount   = 0;
        foreach ($adminEmails as $adminEmail) {
            if ($mailer->sendAssetCreated($adminEmail, $asset)) {
                $sentCount++;
            }
        }
        return $sentCount;
    }
}
