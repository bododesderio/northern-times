<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\BaseModel;

/**
 * SocialPoster — auto-posts published articles to social media platforms.
 *
 * Supported platforms (enabled per-platform via site settings):
 *  - Facebook  : Graph API page posts
 *  - Twitter/X : v2 API tweet
 *  - Telegram  : Bot API sendMessage to a channel
 *  - WhatsApp  : Cloud API message to a phone number / group
 *  - LinkedIn  : Share API ugcPosts
 *
 * Called after article publish in AdminController.
 * All failures are caught and logged — never block article save.
 *
 * Settings keys (stored in site_settings):
 *  social_facebook_enabled, social_facebook_page_id, social_facebook_token
 *  social_twitter_enabled, social_twitter_bearer, social_twitter_api_key,
 *    social_twitter_api_secret, social_twitter_access_token, social_twitter_access_secret
 *  social_telegram_enabled, social_telegram_bot_token, social_telegram_chat_id
 *  social_whatsapp_enabled, social_whatsapp_phone_id, social_whatsapp_token, social_whatsapp_to
 *  social_linkedin_enabled, social_linkedin_token, social_linkedin_person_urn
 */
final class SocialPoster
{
    private const TIMEOUT = 15;

    // ─────────────────────────────────────────────────────────────
    //  Public entry point
    // ─────────────────────────────────────────────────────────────

    /**
     * Post an article to all enabled social platforms.
     * Returns array of ['platform' => 'status', ...].
     */
    public static function postArticle(array $article): array
    {
        $results = [];

        $title   = $article['title']       ?? 'New article';
        $excerpt = $article['excerpt']      ?? '';
        $slug    = $article['slug']         ?? '';
        $image   = $article['featured_image'] ?? null;
        $id      = $article['id']           ?? null;

        $siteUrl  = rtrim(\get_site_setting('site_url', $_ENV['APP_URL'] ?? ''), '/');
        $articleUrl = $siteUrl . '/' . $slug;

        // Build the post text
        $text = self::buildText($title, $excerpt, $articleUrl);

        // ── Facebook ───────────────────────────────────────────
        if (\get_site_setting('social_facebook_enabled') === '1') {
            $results['facebook'] = self::postFacebook($text, $articleUrl, $image, $id);
        }

        // ── Twitter/X ──────────────────────────────────────────
        if (\get_site_setting('social_twitter_enabled') === '1') {
            $results['twitter'] = self::postTwitter($text, $id);
        }

        // ── Telegram ───────────────────────────────────────────
        if (\get_site_setting('social_telegram_enabled') === '1') {
            $results['telegram'] = self::postTelegram($text, $articleUrl, $image, $id);
        }

        // ── WhatsApp ───────────────────────────────────────────
        if (\get_site_setting('social_whatsapp_enabled') === '1') {
            $results['whatsapp'] = self::postWhatsApp($text, $articleUrl, $id);
        }

        // ── LinkedIn ───────────────────────────────────────────
        if (\get_site_setting('social_linkedin_enabled') === '1') {
            $results['linkedin'] = self::postLinkedIn($title, $excerpt, $articleUrl, $image, $id);
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────
    //  Platform implementations
    // ─────────────────────────────────────────────────────────────

    private static function postFacebook(string $text, string $url, ?string $image, ?string $articleId): string
    {
        $pageId = \get_site_setting('social_facebook_page_id');
        $token  = \get_site_setting('social_facebook_token');

        if (!$pageId || !$token) {
            return self::log($articleId, 'facebook', 'skipped', null, 'Missing page ID or token');
        }

        $payload = ['message' => $text, 'link' => $url, 'access_token' => $token];

        [$body, $code] = self::httpPost(
            "https://graph.facebook.com/v19.0/{$pageId}/feed",
            http_build_query($payload),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        $data = json_decode($body, true);
        if ($code === 200 && isset($data['id'])) {
            $postUrl = "https://facebook.com/{$data['id']}";
            return self::log($articleId, 'facebook', 'sent', $postUrl);
        }

        return self::log($articleId, 'facebook', 'failed', null, $body);
    }

    private static function postTwitter(string $text, ?string $articleId): string
    {
        $apiKey        = \get_site_setting('social_twitter_api_key');
        $apiSecret     = \get_site_setting('social_twitter_api_secret');
        $accessToken   = \get_site_setting('social_twitter_access_token');
        $accessSecret  = \get_site_setting('social_twitter_access_secret');

        if (!$apiKey || !$apiSecret || !$accessToken || !$accessSecret) {
            return self::log($articleId, 'twitter', 'skipped', null, 'Missing API credentials');
        }

        // Twitter v2 API uses OAuth 1.0a
        $url     = 'https://api.twitter.com/2/tweets';
        $payload = json_encode(['text' => mb_substr($text, 0, 280)]);

        $oauthHeader = self::buildOAuth1Header('POST', $url, $apiKey, $apiSecret, $accessToken, $accessSecret);

        [$body, $code] = self::httpPost($url, $payload, [
            'Content-Type: application/json',
            'Authorization: ' . $oauthHeader,
        ]);

        $data = json_decode($body, true);
        if ($code === 201 && isset($data['data']['id'])) {
            $tweetId  = $data['data']['id'];
            $postUrl  = "https://twitter.com/i/web/status/{$tweetId}";
            return self::log($articleId, 'twitter', 'sent', $postUrl);
        }

        return self::log($articleId, 'twitter', 'failed', null, $body);
    }

    private static function postTelegram(string $text, string $url, ?string $image, ?string $articleId): string
    {
        $botToken = \get_site_setting('social_telegram_bot_token');
        $chatId   = \get_site_setting('social_telegram_chat_id');

        if (!$botToken || !$chatId) {
            return self::log($articleId, 'telegram', 'skipped', null, 'Missing bot token or chat ID');
        }

        $baseUrl = "https://api.telegram.org/bot{$botToken}";

        if ($image && filter_var($image, FILTER_VALIDATE_URL)) {
            // Send photo with caption
            $payload = json_encode([
                'chat_id'    => $chatId,
                'photo'      => $image,
                'caption'    => mb_substr($text, 0, 1024),
                'parse_mode' => 'HTML',
            ]);
            [$body, $code] = self::httpPost("{$baseUrl}/sendPhoto", $payload, ['Content-Type: application/json']);
        } else {
            // Send text message
            $payload = json_encode([
                'chat_id'                  => $chatId,
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => false,
            ]);
            [$body, $code] = self::httpPost("{$baseUrl}/sendMessage", $payload, ['Content-Type: application/json']);
        }

        $data = json_decode($body, true);
        if ($code === 200 && ($data['ok'] ?? false)) {
            $msgId   = $data['result']['message_id'] ?? '';
            $channel = ltrim($chatId, '@');
            $postUrl = $msgId ? "https://t.me/{$channel}/{$msgId}" : null;
            return self::log($articleId, 'telegram', 'sent', $postUrl);
        }

        return self::log($articleId, 'telegram', 'failed', null, $body);
    }

    private static function postWhatsApp(string $text, string $url, ?string $articleId): string
    {
        $phoneId = \get_site_setting('social_whatsapp_phone_id');
        $token   = \get_site_setting('social_whatsapp_token');
        $to      = \get_site_setting('social_whatsapp_to');

        if (!$phoneId || !$token || !$to) {
            return self::log($articleId, 'whatsapp', 'skipped', null, 'Missing phone ID, token, or recipient');
        }

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['preview_url' => true, 'body' => $text],
        ]);

        [$body, $code] = self::httpPost(
            "https://graph.facebook.com/v19.0/{$phoneId}/messages",
            $payload,
            ['Content-Type: application/json', 'Authorization: Bearer ' . $token]
        );

        $data = json_decode($body, true);
        if ($code === 200 && isset($data['messages'][0]['id'])) {
            return self::log($articleId, 'whatsapp', 'sent', null);
        }

        return self::log($articleId, 'whatsapp', 'failed', null, $body);
    }

    private static function postLinkedIn(string $title, string $excerpt, string $url, ?string $image, ?string $articleId): string
    {
        $token      = \get_site_setting('social_linkedin_token');
        $personUrn  = \get_site_setting('social_linkedin_person_urn'); // e.g. urn:li:person:xxxxx

        if (!$token || !$personUrn) {
            return self::log($articleId, 'linkedin', 'skipped', null, 'Missing token or person URN');
        }

        $commentary = mb_substr($excerpt ?: $title, 0, 700);

        $payload = json_encode([
            'author'          => $personUrn,
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'  => ['text' => $commentary],
                    'shareMediaCategory' => 'ARTICLE',
                    'media'            => [[
                        'status'      => 'READY',
                        'description' => ['text' => mb_substr($excerpt, 0, 256)],
                        'originalUrl' => $url,
                        'title'       => ['text' => mb_substr($title, 0, 200)],
                    ]],
                ],
            ],
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
        ]);

        [$body, $code] = self::httpPost(
            'https://api.linkedin.com/v2/ugcPosts',
            $payload,
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-Restli-Protocol-Version: 2.0.0',
            ]
        );

        if ($code === 201) {
            $data    = json_decode($body, true);
            $postId  = $data['id'] ?? null;
            $postUrl = $postId ? 'https://www.linkedin.com/feed/update/' . urlencode($postId) : null;
            return self::log($articleId, 'linkedin', 'sent', $postUrl);
        }

        return self::log($articleId, 'linkedin', 'failed', null, $body);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private static function buildText(string $title, string $excerpt, string $url): string
    {
        $siteName = \site_name();
        $body = $excerpt !== '' ? mb_substr(strip_tags($excerpt), 0, 200) : '';
        $text = "📰 {$title}";
        if ($body !== '') $text .= "\n\n{$body}";
        $text .= "\n\n🔗 {$url}";
        $text .= "\n\n— {$siteName}";
        return $text;
    }

    private static function httpPost(string $url, string $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'NorthernTimesSocialPoster/1.0',
        ]);
        $response = curl_exec($ch);
        $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $response = json_encode(['curl_error' => curl_error($ch)]);
        }
        curl_close($ch);
        return [$response, $code];
    }

    /**
     * Build OAuth 1.0a Authorization header for Twitter v2 API.
     */
    private static function buildOAuth1Header(
        string $method, string $url,
        string $apiKey, string $apiSecret,
        string $accessToken, string $accessSecret
    ): string {
        $nonce     = bin2hex(random_bytes(16));
        $timestamp = (string)time();

        $oauthParams = [
            'oauth_consumer_key'     => $apiKey,
            'oauth_nonce'            => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => $timestamp,
            'oauth_token'            => $accessToken,
            'oauth_version'          => '1.0',
        ];

        // Build signature base string
        $params = $oauthParams;
        ksort($params);
        $paramStr = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $base     = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramStr);
        $key      = rawurlencode($apiSecret) . '&' . rawurlencode($accessSecret);
        $sig      = base64_encode(hash_hmac('sha1', $base, $key, true));

        $oauthParams['oauth_signature'] = $sig;

        $parts = [];
        foreach ($oauthParams as $k => $v) {
            $parts[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
        }

        return 'OAuth ' . implode(', ', $parts);
    }

    /**
     * Write to social_posts_log table and return the status string.
     */
    private static function log(
        ?string $articleId, string $platform, string $status,
        ?string $postUrl = null, ?string $error = null
    ): string {
        if (!$articleId) return $status;

        try {
            $pdo = BaseModel::pdo();
            $pdo->prepare(
                "INSERT INTO social_posts_log (article_id, platform, status, post_url, error)
                 VALUES (:aid, :platform, :status, :post_url, :error)"
            )->execute([
                ':aid'      => $articleId,
                ':platform' => $platform,
                ':status'   => $status,
                ':post_url' => $postUrl,
                ':error'    => $error ? mb_substr($error, 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[SocialPoster] Log write failed: ' . $e->getMessage());
        }

        return $status;
    }
}