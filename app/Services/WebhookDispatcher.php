<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Webhook Dispatcher — fires HTTP POST to registered webhook URLs.
 */
final class WebhookDispatcher
{
    public static function dispatch(string $event, array $payload): void
    {
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("
                SELECT id, url, secret FROM webhooks
                WHERE is_active = TRUE
                  AND (events = :event
                       OR events LIKE :starts_with
                       OR events LIKE :ends_with
                       OR events LIKE :middle)
            ");
            // Escape LIKE wildcards in event name to prevent unintended matches
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $event);
            $stmt->execute([
                ':event' => $event,
                ':starts_with' => $escaped . ',%',
                ':ends_with' => '%,' . $escaped,
                ':middle' => '%,' . $escaped . ',%',
            ]);
            $webhooks = $stmt->fetchAll();

            if (empty($webhooks)) return;

            // Prepare all curl handles
            $mh = curl_multi_init();
            $handles = [];

            foreach ($webhooks as $wh) {
                $ch = self::buildCurlHandle($wh, $event, $payload);
                if ($ch === null) {
                    self::logResult($wh, $event, $payload, 0, 'Blocked: internal/private URL');
                    continue;
                }
                curl_multi_add_handle($mh, $ch);
                $handles[] = ['ch' => $ch, 'webhook' => $wh];
            }

            // Execute all in parallel (fire-and-forget with 5s max)
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                if ($running > 0) {
                    curl_multi_select($mh, 0.1);
                }
            } while ($running > 0);

            // Log results
            foreach ($handles as $h) {
                $response = curl_multi_getcontent($h['ch']);
                $httpCode = (int)curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($mh, $h['ch']);
                curl_close($h['ch']);
                self::logResult($h['webhook'], $event, $payload, $httpCode, $response);
            }

            curl_multi_close($mh);
        } catch (\Throwable $e) {
            error_log('[Webhook] Dispatch error: ' . $e->getMessage());
        }
    }

    private static function buildCurlHandle(array $webhook, string $event, array $payload): \CurlHandle
    {
        $body = json_encode([
            'event' => $event,
            'timestamp' => gmdate('c'),
            'data' => $payload,
        ]);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: NorthernTimes-Webhook/1.0',
        ];

        if (!empty($webhook['secret'])) {
            $sig = hash_hmac('sha256', $body, $webhook['secret']);
            $headers[] = 'X-Webhook-Signature: sha256=' . $sig;
        }

        // SSRF protection: block internal/private URLs
        $parsedUrl = parse_url($webhook['url']);
        $host = $parsedUrl['host'] ?? '';
        $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1', 'extractor', 'postgres', 'redis', 'mail', 'pgadmin', 'ollama'];
        if (in_array(strtolower($host), $blockedHosts, true)) {
            error_log("WebhookDispatcher: blocked internal URL {$webhook['url']}");
            return null;
        }
        $ip = gethostbyname($host);
        if ($ip !== $host) {
            $longIp = ip2long($ip);
            if ($longIp !== false && (
                ($longIp >> 24) === 127 ||           // 127.0.0.0/8
                ($longIp >> 24) === 10 ||            // 10.0.0.0/8
                ($longIp >> 20) === (172 << 4 | 1) ||// 172.16.0.0/12
                ($longIp >> 16) === (192 << 8 | 168)||// 192.168.0.0/16
                ($longIp >> 16) === (169 << 8 | 254) // 169.254.0.0/16
            )) {
                error_log("WebhookDispatcher: blocked private IP {$ip} for {$webhook['url']}");
                return null;
            }
        }

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        return $ch;
    }

    private static function logResult(array $webhook, string $event, array $payload, int $httpCode, ?string $response): void
    {
        $body = json_encode([
            'event' => $event,
            'timestamp' => gmdate('c'),
            'data' => $payload,
        ]);

        try {
            $pdo = DB::pdo();
            $pdo->prepare("
                INSERT INTO webhook_logs (webhook_id, event, payload, response_code, response_body)
                VALUES (:wid, :event, :payload, :code, :body)
            ")->execute([
                ':wid' => $webhook['id'],
                ':event' => $event,
                ':payload' => $body,
                ':code' => $httpCode,
                ':body' => mb_substr((string)$response, 0, 1000),
            ]);

            // Update webhook status
            $success = $httpCode >= 200 && $httpCode < 300;
            $pdo->prepare("
                UPDATE webhooks SET
                    last_triggered_at = NOW(),
                    last_status_code = :code,
                    failure_count = CASE WHEN :success THEN 0 ELSE failure_count + 1 END
                WHERE id = :id
            ")->execute([
                ':code' => $httpCode,
                ':success' => $success ? 'true' : 'false',
                ':id' => $webhook['id'],
            ]);

            // Auto-disable after 10 consecutive failures
            if (!$success) {
                $pdo->prepare("
                    UPDATE webhooks SET is_active = FALSE
                    WHERE id = :id AND failure_count >= 10
                ")->execute([':id' => $webhook['id']]);
            }
        } catch (\Throwable $e) {
            error_log('[Webhook] Log error: ' . $e->getMessage());
        }
    }
}
