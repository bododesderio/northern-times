<?php
declare(strict_types=1);

namespace App\Services;

/**
 * HTTP client for the AI article rewriter microservice.
 *
 * The rewriter service calls Ollama (llama3:8b) to rewrite crawled articles
 * in The Northern Times editorial style. Returns null if unavailable,
 * allowing the caller to skip rewriting gracefully.
 */
final class RewriterClient
{
    private static function baseUrl(): string
    {
        return rtrim($_ENV['REWRITER_URL'] ?? 'http://rewriter:5001', '/');
    }

    private static function timeout(): int
    {
        return (int)($_ENV['REWRITER_TIMEOUT'] ?? 120);
    }

    /**
     * Check if the rewriter service is reachable and Ollama is connected.
     */
    public static function isHealthy(): bool
    {
        try {
            $ch = curl_init(self::baseUrl() . '/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200) return false;
            $data = json_decode($body, true);
            return ($data['status'] ?? '') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Rewrite an article's title, content and excerpt.
     *
     * @return array{title:string,content:string,excerpt:string,word_count:int,original_word_count:int,model_used:string}|null
     *         Null if the service is unavailable or rewrite failed.
     */
    public static function rewrite(
        string $title,
        string $content,
        string $excerpt = '',
        ?string $model = null,
        ?string $rules = null,
        ?int $maxChunkWords = null
    ): ?array {
        $payload = [
            'title'   => $title,
            'content' => $content,
            'excerpt' => $excerpt,
        ];
        if ($model !== null)         $payload['model'] = $model;
        if ($rules)                  $payload['rules'] = $rules;
        if ($maxChunkWords !== null)  $payload['max_chunk_words'] = $maxChunkWords;

        $result = self::post('/rewrite', $payload, self::timeout());
        if ($result === null) return null;

        if (empty($result['success'])) {
            error_log('RewriterClient: rewrite failed — ' . ($result['error'] ?? 'unknown error'));
            return null;
        }

        return [
            'title'               => $result['title']               ?? $title,
            'content'             => $result['content']             ?? $content,
            'excerpt'             => $result['excerpt']             ?? $excerpt,
            'word_count'          => (int)($result['word_count']    ?? 0),
            'original_word_count' => (int)($result['original_word_count'] ?? 0),
            'model_used'          => $result['model_used']          ?? '',
            'chunks_processed'    => (int)($result['chunks_processed'] ?? 1),
        ];
    }

    // ── Internal ─────────────────────────────────────────────────

    private static function post(string $path, array $payload, int $timeout = 30): ?array
    {
        try {
            $ch = curl_init(self::baseUrl() . $path);
            $body = json_encode($payload);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $response = curl_exec($ch);
            $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err      = curl_error($ch);
            curl_close($ch);

            if ($err || $code === 0) {
                error_log("RewriterClient: curl error on {$path} — {$err}");
                return null;
            }
            if ($code >= 500) {
                error_log("RewriterClient: HTTP {$code} on {$path}");
                return null;
            }

            return json_decode($response, true) ?? null;
        } catch (\Throwable $e) {
            error_log("RewriterClient: exception on {$path} — " . $e->getMessage());
            return null;
        }
    }
}
