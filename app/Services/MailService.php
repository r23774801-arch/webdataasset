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

        if ($this->config['sender_email'] !== '') {
            $mail->setFrom($this->config['sender_email'], $this->config['sender_name']);
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
     * Send the "Stocktaking Approval Request" notification to the Administrator.
     *
     * @param array $submission  stocktaking submission row
     * @param array $assets      snapshot of the submitted assets
     */
    public function sendStocktakingApproval(array $submission, array $assets = []): bool
    {
        $config = mail_config();
        if ($config['admin_email'] === '') {
            error_log('[MailService] MAIL_ADMIN_ADDRESS is not configured. Skipping notification.');
            return false;
        }

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

        return $this->send($config['admin_email'], 'Stocktaking Approval Request', $html);
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
}
