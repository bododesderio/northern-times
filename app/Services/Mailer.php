<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\BaseModel;

/**
 * Mailer — sends transactional + newsletter emails.
 *
 * Supports two drivers:
 *  1. 'smtp' — direct SMTP via socket (works in Docker, recommended)
 *  2. 'mail' — PHP mail() fallback (needs local MTA)
 *
 * Queue mode: insert into email_queue table, process via CLI cron.
 *
 * Env vars:
 *   MAIL_DRIVER=smtp           (smtp|mail)
 *   MAIL_HOST=smtp.gmail.com
 *   MAIL_PORT=587
 *   MAIL_USERNAME=you@gmail.com
 *   MAIL_PASSWORD=app-password
 *   MAIL_ENCRYPTION=tls        (tls|ssl|none)
 *   MAIL_FROM_ADDRESS=noreply@example.com
 *   MAIL_FROM_NAME=The Northern Times
 */
final class Mailer
{
    private static ?string $fromEmail = null;
    private static ?string $fromName  = null;

    private static function boot(): void
    {
        if (self::$fromEmail !== null) return;
        self::$fromEmail = self::setting('mail_from_address', $_ENV['MAIL_FROM_ADDRESS'] ?? null)
            ?? (function_exists('get_site_setting')
                ? get_site_setting('contact_email', 'noreply@example.com')
                : 'noreply@example.com');
        self::$fromName = self::setting('mail_from_name', $_ENV['MAIL_FROM_NAME'] ?? null)
            ?? (function_exists('get_site_setting')
                ? get_site_setting('site_title', 'Newsletter')
                : 'Newsletter');
    }

    /**
     * Read a mail setting: DB site_settings first, then .env fallback.
     */
    private static function setting(string $key, ?string $envFallback = null): ?string
    {
        if (function_exists('get_site_setting')) {
            $dbVal = get_site_setting($key, '');
            if ($dbVal !== '') return $dbVal;
        }
        return ($envFallback !== null && $envFallback !== '') ? $envFallback : null;
    }

    /* ================================================================
       DIRECT SEND — routes to SMTP or mail() based on MAIL_DRIVER
       ================================================================ */

    public static function send(string $to, string $subject, string $bodyHtml, ?string $bodyText = null, ?string $toName = null): bool
    {
        self::boot();

        $driver = strtolower(trim(
            self::setting('mail_driver', $_ENV['MAIL_DRIVER'] ?? null) ?? 'mail'
        ));

        if ($driver === 'smtp') {
            return self::sendViaSMTP($to, $subject, $bodyHtml, $bodyText, $toName);
        }

        return self::sendViaMail($to, $subject, $bodyHtml, $bodyText, $toName);
    }

    /**
     * Send via PHP mail() — requires local MTA.
     */
    private static function sendViaMail(string $to, string $subject, string $bodyHtml, ?string $bodyText, ?string $toName): bool
    {
        $boundary = '----=_Part_' . bin2hex(random_bytes(8));

        $headers  = "From: " . self::formatAddress(self::$fromEmail, self::$fromName) . "\r\n";
        $headers .= "Reply-To: " . self::$fromEmail . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: NorthernTimes/1.0\r\n";

        $textPart = $bodyText ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textPart . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $bodyHtml . "\r\n\r\n";
        $body .= "--{$boundary}--\r\n";

        $recipient = $toName ? self::formatAddress($to, $toName) : $to;

        return @mail($recipient, $subject, $body, $headers);
    }

    /**
     * Send via SMTP socket — works everywhere (Docker, VPS, etc.).
     */
    private static function sendViaSMTP(string $to, string $subject, string $bodyHtml, ?string $bodyText, ?string $toName): bool
    {
        $host       = self::setting('mail_host', $_ENV['MAIL_HOST'] ?? null) ?? 'localhost';
        $port       = (int)(self::setting('mail_port', $_ENV['MAIL_PORT'] ?? null) ?? 587);
        $username   = self::setting('mail_username', $_ENV['MAIL_USERNAME'] ?? null) ?? '';
        $password   = self::setting('mail_password', $_ENV['MAIL_PASSWORD'] ?? null) ?? '';
        $encryption = strtolower(self::setting('mail_encryption', $_ENV['MAIL_ENCRYPTION'] ?? null) ?? 'tls');

        try {
            // Connect
            $prefix  = ($encryption === 'ssl') ? 'ssl://' : '';
            $socket  = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
            if (!$socket) {
                error_log("SMTP connect failed: {$errstr} ({$errno}) to {$host}:{$port}");
                return false;
            }
            stream_set_timeout($socket, 30);

            self::smtpRead($socket); // greeting

            // EHLO
            self::smtpSend($socket, "EHLO " . gethostname());

            // STARTTLS if needed
            if ($encryption === 'tls') {
                self::smtpSend($socket, "STARTTLS");
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$crypto) {
                    error_log("SMTP STARTTLS failed for {$host}:{$port}");
                    fclose($socket);
                    return false;
                }
                self::smtpSend($socket, "EHLO " . gethostname());
            }

            // AUTH LOGIN
            if ($username !== '' && $password !== '') {
                self::smtpSend($socket, "AUTH LOGIN");
                self::smtpSend($socket, base64_encode($username));
                self::smtpSend($socket, base64_encode($password));
            }

            // MAIL FROM / RCPT TO
            self::smtpSend($socket, "MAIL FROM:<" . self::$fromEmail . ">");
            self::smtpSend($socket, "RCPT TO:<{$to}>");

            // DATA
            self::smtpSend($socket, "DATA");

            // Build message
            $boundary = '----=_Part_' . bin2hex(random_bytes(8));
            $textPart = $bodyText ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
            $recipientHeader = $toName ? self::formatAddress($to, $toName) : $to;

            $message  = "From: " . self::formatAddress(self::$fromEmail, self::$fromName) . "\r\n";
            $message .= "To: {$recipientHeader}\r\n";
            $message .= "Subject: {$subject}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $message .= "X-Mailer: NorthernTimes/1.0\r\n";
            $message .= "Date: " . date('r') . "\r\n";
            $message .= "Message-ID: <" . bin2hex(random_bytes(16)) . "@" . ($host) . ">\r\n";
            $message .= "\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $textPart . "\r\n\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $bodyHtml . "\r\n\r\n";
            $message .= "--{$boundary}--\r\n";

            // Escape dots at start of lines
            $message = str_replace("\r\n.", "\r\n..", $message);

            fwrite($socket, $message . "\r\n.\r\n");
            $response = self::smtpRead($socket);

            // QUIT
            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            // 250 = accepted
            return str_starts_with(trim($response), '250');

        } catch (\Throwable $e) {
            error_log("SMTP error: " . $e->getMessage());
            if (isset($socket) && is_resource($socket)) {
                @fclose($socket);
            }
            return false;
        }
    }

    private static function smtpSend($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return self::smtpRead($socket);
    }

    private static function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // 4th char is space = last line of response
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    }

    /* ================================================================
       QUEUE — for bulk sends (newsletters)
       ================================================================ */

    public static function queue(string $to, string $subject, string $bodyHtml, ?string $bodyText = null, ?string $toName = null): void
    {
        $pdo = BaseModel::pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO email_queue (to_email, to_name, subject, body_html, body_text)
             VALUES (:to, :name, :subj, :html, :text)"
        );
        $stmt->execute([
            ':to'   => $to,
            ':name' => $toName,
            ':subj' => $subject,
            ':html' => $bodyHtml,
            ':text' => $bodyText,
        ]);
    }

    public static function processQueue(int $batchSize = 50): array
    {
        $pdo = BaseModel::pdo();

        $stmt = $pdo->prepare(
            "SELECT id, to_email, to_name, subject, body_html, body_text
             FROM email_queue
             WHERE status = 'pending' AND scheduled_at <= NOW() AND attempts < 3
             ORDER BY created_at ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
        $stmt->execute();
        $emails = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sent = 0;
        $failed = 0;

        foreach ($emails as $email) {
            $ok = self::send(
                $email['to_email'],
                $email['subject'],
                $email['body_html'],
                $email['body_text'],
                $email['to_name']
            );

            if ($ok) {
                $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW(), attempts = attempts + 1 WHERE id = :id")
                    ->execute([':id' => $email['id']]);
                $sent++;
            } else {
                $error = error_get_last()['message'] ?? 'send() returned false';
                $pdo->prepare("UPDATE email_queue SET attempts = attempts + 1, last_error = :err,
                               status = CASE WHEN attempts + 1 >= 3 THEN 'failed' ELSE 'pending' END
                               WHERE id = :id")
                    ->execute([':id' => $email['id'], ':err' => $error]);
                $failed++;
            }

            usleep(50000); // 50ms rate limit
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public static function queueStats(): array
    {
        $pdo = BaseModel::pdo();
        return [
            'pending' => (int)$pdo->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn(),
            'sent'    => (int)$pdo->query("SELECT COUNT(*) FROM email_queue WHERE status='sent'")->fetchColumn(),
            'failed'  => (int)$pdo->query("SELECT COUNT(*) FROM email_queue WHERE status='failed'")->fetchColumn(),
        ];
    }

    /**
     * Fetch queue rows for the admin detail table.
     * Returns failed + pending first, then recent sent (capped at $limit).
     */
    public static function queueItems(int $limit = 50): array
    {
        $pdo  = BaseModel::pdo();
        $stmt = $pdo->prepare("
            SELECT
                id,
                to_email,
                to_name,
                subject,
                status,
                attempts,
                last_error,
                scheduled_at,
                sent_at,
                created_at
            FROM email_queue
            ORDER BY
                CASE status
                    WHEN 'failed'  THEN 1
                    WHEN 'pending' THEN 2
                    ELSE 3
                END ASC,
                created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Send a test email to verify SMTP config works.
     */
    public static function sendTest(string $to): array
    {
        self::boot();
        $driver = self::setting('mail_driver', $_ENV['MAIL_DRIVER'] ?? null) ?? 'mail';
        $host   = self::setting('mail_host', $_ENV['MAIL_HOST'] ?? null) ?? '(none)';

        $html = self::template(
            "<h2 style=\"margin:0 0 16px;color:#1a1a1a;font-size:24px;\">SMTP Test</h2>"
            . "<p>If you're reading this, your email configuration is working correctly.</p>"
            . "<p style=\"color:#666;font-size:13px;\">Sent at " . date('Y-m-d H:i:s T') . "</p>"
            . "<p style=\"color:#666;font-size:13px;\">Driver: {$driver} | Host: {$host}</p>"
        );

        $siteTitle = function_exists('get_site_setting') ? get_site_setting('site_title', 'Site') : 'Site';
        $ok = self::send($to, $siteTitle . ' — SMTP Test', $html);
        return [
            'success' => $ok,
            'driver'  => $driver,
            'host'    => $host,
            'from'    => self::$fromEmail,
        ];
    }

    /* ================================================================
       EMAIL TEMPLATES
       ================================================================ */

    public static function template(string $content, ?string $unsubLink = null): string
    {
        self::boot();
        $siteName = self::$fromName;
        $year     = date('Y');
        $unsub    = $unsubLink ? "<p style=\"margin-top:24px;\"><a href=\"{$unsubLink}\" style=\"color:#999;font-size:12px;\">Unsubscribe</a></p>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
  <tr>
    <td style="background:#1a1a1a;padding:24px 32px;text-align:center;">
      <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-.02em;">{$siteName}</h1>
    </td>
  </tr>
  <tr>
    <td style="padding:32px;color:#333333;font-size:16px;line-height:1.6;">
      {$content}
    </td>
  </tr>
  <tr>
    <td style="padding:20px 32px;background:#f9fafb;border-top:1px solid #eee;text-align:center;color:#999;font-size:12px;">
      &copy; {$year} {$siteName}. All rights reserved.
      {$unsub}
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    public static function welcomeEmail(string $name, string $unsubToken): string
    {
        self::boot();
        $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
        $unsubUrl = "{$appUrl}/unsubscribe?token={$unsubToken}";

        $content = "<h2 style=\"margin:0 0 16px;color:#1a1a1a;font-size:24px;\">Welcome, {$name}!</h2>
        <p>Thank you for subscribing to <strong>" . self::$fromName . "</strong>. You'll receive our best stories and editorial picks directly in your inbox.</p>
        <p style=\"margin:24px 0;\"><a href=\"{$appUrl}\" style=\"display:inline-block;background:#cc0000;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;\">Visit Our Site</a></p>
        <p style=\"color:#666;font-size:14px;\">If you didn't subscribe, you can safely ignore this email or <a href=\"{$unsubUrl}\" style=\"color:#cc0000;\">unsubscribe here</a>.</p>";

        return self::template($content, $unsubUrl);
    }

    public static function digestEmail(string $subject, array $articles, string $unsubToken, ?string $introText = null): string
    {
        self::boot();
        $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
        $unsubUrl = "{$appUrl}/unsubscribe?token={$unsubToken}";

        // Intro may contain HTML from CKEditor (rich text, inline images).
        // Convert any article-image figures to inline styles for email clients.
        $intro = '';
        if ($introText !== null && $introText !== '') {
            $intro = self::inlineImageStyles($introText);
            // Wrap plain text in <p> if it doesn't look like HTML
            if (strip_tags($intro) === $intro) {
                $intro = "<p style=\"margin-bottom:24px;\">{$intro}</p>";
            } else {
                $intro = "<div style=\"margin-bottom:24px;\">{$intro}</div>";
            }
        }

        $articleHtml = '';
        foreach ($articles as $a) {
            $url     = "{$appUrl}/article/" . ($a['slug'] ?? '');
            $title   = htmlspecialchars($a['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $excerpt = htmlspecialchars($a['excerpt'] ?? '', ENT_QUOTES, 'UTF-8');
            $author  = htmlspecialchars($a['author'] ?? $a['display_author'] ?? 'Staff', ENT_QUOTES, 'UTF-8');
            $img     = !empty($a['featured_image'])
                ? "<img src=\"{$a['featured_image']}\" alt=\"\" style=\"width:100%;height:180px;object-fit:cover;border-radius:8px;margin-bottom:12px;\">"
                : '';

            $articleHtml .= <<<ARTICLE
<div style="margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid #eee;">
  {$img}
  <h3 style="margin:0 0 8px;font-size:20px;line-height:1.3;"><a href="{$url}" style="color:#1a1a1a;text-decoration:none;">{$title}</a></h3>
  <p style="margin:0 0 8px;color:#555;font-size:14px;line-height:1.5;">{$excerpt}</p>
  <p style="margin:0;font-size:12px;color:#999;">By {$author}</p>
</div>
ARTICLE;
        }

        $content = "<h2 style=\"margin:0 0 20px;color:#1a1a1a;font-size:24px;\">{$subject}</h2>
        {$intro}
        {$articleHtml}
        <p style=\"text-align:center;margin:24px 0;\"><a href=\"{$appUrl}\" style=\"display:inline-block;background:#cc0000;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;\">Read More on Our Site</a></p>";

        return self::template($content, $unsubUrl);
    }

    public static function passwordResetEmail(string $name, string $resetUrl): string
    {
        $content = "<h2 style=\"margin:0 0 16px;color:#1a1a1a;font-size:24px;\">Password Reset</h2>
        <p>Hi {$name}, we received a request to reset your password.</p>
        <p style=\"margin:24px 0;\"><a href=\"{$resetUrl}\" style=\"display:inline-block;background:#cc0000;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;\">Reset My Password</a></p>
        <p style=\"color:#666;font-size:14px;\">This link expires in 1 hour. If you didn't request this, ignore this email.</p>";

        return self::template($content);
    }

    /* ================================================================
       HELPERS
       ================================================================ */

    /**
     * Convert article-image CSS classes to inline styles for email clients.
     * Email clients ignore <style> blocks and CSS classes, so we must inline everything.
     */
    private static function inlineImageStyles(string $html): string
    {
        // Convert <figure class="article-image ..."> to inline-styled tables (email-safe)
        $html = preg_replace_callback(
            '/<figure\b[^>]*class="([^"]*article-image[^"]*)"[^>]*>(.*?)<\/figure>/is',
            function (array $m) {
                $classes = $m[1];
                $inner   = $m[2];

                $style = 'margin:1.5rem 0;';
                if (str_contains($classes, 'article-image-half')) {
                    $style .= 'max-width:50%;';
                } else {
                    $style .= 'max-width:100%;';
                }
                if (str_contains($classes, 'article-image-left')) {
                    $style .= 'float:left;margin-right:1.5rem;';
                } elseif (str_contains($classes, 'article-image-right')) {
                    $style .= 'float:right;margin-left:1.5rem;';
                }

                $inner = preg_replace(
                    '/<img\b([^>]*)>/i',
                    '<img$1 style="width:100%;height:auto;border-radius:4px;display:block;">',
                    $inner
                );
                $inner = preg_replace(
                    '/<figcaption\b[^>]*>/i',
                    '<figcaption style="font-size:0.85rem;color:#999;margin-top:0.5rem;text-align:center;">',
                    $inner
                );

                return "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"{$style}\"><tr><td>{$inner}</td></tr></table>";
            },
            $html
        );

        // Also handle bare <img> tags from CKEditor (not wrapped in figure)
        $html = preg_replace_callback(
            '/<img\b(?![^>]*style=)[^>]*>/i',
            function (array $m) {
                return str_replace('<img', '<img style="max-width:100%;height:auto;border-radius:4px;"', $m[0]);
            },
            $html
        );

        return $html;
    }

    private static function formatAddress(string $email, ?string $name = null): string
    {
        if ($name === null || $name === '') return $email;
        $clean = str_replace(['"', '\\'], '', $name);
        return "\"{$clean}\" <{$email}>";
    }
}