<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\BaseModel;
use App\Models\SocialKeyword;
use App\Models\SocialMention;
use App\Models\Setting;

/**
 * Social Media Monitor — expanded multi-platform keyword tracking.
 *
 * Scans these sources (all free, no API keys required):
 *  1. Google News RSS — news articles mentioning keywords
 *  2. Reddit RSS — posts and comments mentioning keywords
 *  3. Bing News RSS — alternative news source
 *  4. Twitter/X via Nitter — tweets mentioning keywords (RSS mirrors)
 *  5. Web search — Google web results for broader web mentions
 *  6. Hacker News — tech/startup community mentions
 *  7. YouTube RSS — video mentions
 *
 * Auto-runs via cron every N minutes (configurable in settings).
 * Classifies sentiment, deduplicates, alerts on negative spikes.
 */
final class SocialMonitorService
{
    private const USER_AGENT    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    /** Dynamic bot UA. */
    private static function botUA(): string
    {
        $name = \get_site_setting('site_title', 'SocialMonitor');
        $url  = rtrim(\get_site_setting('site_url', $_ENV['APP_URL'] ?? 'http://localhost'), '/');
        return preg_replace('/[^A-Za-z0-9]/', '', $name) . 'SocialMonitor/1.0 (+' . $url . ')';
    }
    private const FETCH_TIMEOUT = 12;
    private const MAX_PER_SOURCE = 25;

    /** Nitter instances (Twitter/X RSS mirrors) — try multiple, they go up/down */
    private const NITTER_INSTANCES = [
        'https://nitter.privacydev.net',
        'https://nitter.poast.org',
        'https://nitter.cz',
        'https://nitter.1d4.us',
    ];

    private const POSITIVE_WORDS = [
        'praise', 'lauds', 'congratulates', 'celebrates', 'award', 'success',
        'breakthrough', 'excellent', 'impressive', 'wins', 'victory', 'growth',
        'positive', 'achievement', 'commends', 'applauds', 'progress', 'boost',
        'thriving', 'milestone', 'innovation', 'recognition', 'honored', 'best',
        'incredible', 'outstanding', 'remarkable', 'inspiring', 'proud', 'champion',
        'brilliant', 'wonderful', 'fantastic', 'great', 'top', 'leading',
    ];

    private const NEGATIVE_WORDS = [
        'scandal', 'controversy', 'accused', 'crisis', 'failure', 'corrupt',
        'arrested', 'fraud', 'fake', 'misinformation', 'boycott', 'protest',
        'outrage', 'condemns', 'criticism', 'backlash', 'lawsuit', 'terrible',
        'disaster', 'collapse', 'shutdown', 'banned', 'violated', 'worst',
        'complaint', 'threatens', 'warning', 'alarm', 'decline', 'loss',
        'death', 'killed', 'fired', 'sued', 'scam', 'expose', 'resign',
        'incompetent', 'disgrace', 'pathetic', 'outrageous', 'shameful',
    ];

    // ═══════════════════════════════════════════════════════════
    //  PUBLIC API
    // ═══════════════════════════════════════════════════════════

    /**
     * Run a full scan across all platforms for all active keywords.
     * Called by cron or manual "Scan Now" button.
     */
    public static function scan(): array
    {
        if (Setting::get('social_monitor_enabled', 'false') !== 'true') {
            return ['skipped' => true, 'reason' => 'Social monitor disabled'];
        }

        $keywords   = SocialKeyword::active();
        $totalFound = 0;
        $newSaved   = 0;
        $errors     = 0;

        foreach ($keywords as $kw) {
            $keyword = $kw['keyword'];
            $isComp  = !empty($kw['is_competitor']);

            // ── 1. Google News RSS ────────────────────────────
            try {
                $results = self::searchGoogleNews($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Google News error for '{$keyword}': " . $e->getMessage()); }

            // ── 2. Reddit RSS ─────────────────────────────────
            try {
                $results = self::searchReddit($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Reddit error for '{$keyword}': " . $e->getMessage()); }

            // ── 3. Bing News RSS ──────────────────────────────
            try {
                $results = self::searchBingNews($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Bing error for '{$keyword}': " . $e->getMessage()); }

            // ── 4. Twitter/X via Nitter ───────────────────────
            try {
                $results = self::searchTwitter($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Twitter error for '{$keyword}': " . $e->getMessage()); }

            // ── 5. Hacker News (Algolia API — free) ───────────
            try {
                $results = self::searchHackerNews($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: HN error for '{$keyword}': " . $e->getMessage()); }

            // ── 6. YouTube RSS ────────────────────────────────
            try {
                $results = self::searchYouTube($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: YouTube error for '{$keyword}': " . $e->getMessage()); }

            // ── 7. Web (DuckDuckGo HTML scrape) ───────────────
            try {
                $results = self::searchWeb($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Web error for '{$keyword}': " . $e->getMessage()); }

            // ── 8. Facebook (site-specific search) ────────────
            try {
                $results = self::searchPlatformSite('facebook.com', $keyword, 'facebook');
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Facebook error for '{$keyword}': " . $e->getMessage()); }

            // ── 9. Instagram (site-specific search) ───────────
            try {
                $results = self::searchPlatformSite('instagram.com', $keyword, 'instagram');
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Instagram error for '{$keyword}': " . $e->getMessage()); }

            // ── 10. LinkedIn (site-specific search) ───────────
            try {
                $results = self::searchPlatformSite('linkedin.com', $keyword, 'linkedin');
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: LinkedIn error for '{$keyword}': " . $e->getMessage()); }

            // ── 11. Mastodon (public API) ─────────────────────
            try {
                $results = self::searchMastodon($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Mastodon error for '{$keyword}': " . $e->getMessage()); }

            // ── 12. Bluesky (public API) ──────────────────────
            try {
                $results = self::searchBluesky($keyword);
                $totalFound += count($results);
                $newSaved += self::saveResults($results, $keyword, $isComp);
            } catch (\Throwable $e) { $errors++; error_log("Social: Bluesky error for '{$keyword}': " . $e->getMessage()); }

            // Small delay between keywords to avoid rate limiting
            usleep(300000); // 300ms
        }

        Setting::set('social_last_scan', date('Y-m-d H:i:s'), 'social');

        // Alert on negative spike
        self::checkNegativeSpike();

        return ['found' => $totalFound, 'saved' => $newSaved, 'errors' => $errors];
    }

    /**
     * Check if auto-scan is due based on interval setting.
     * Called by cron every 5 minutes.
     */
    public static function isAutoScanDue(): bool
    {
        if (Setting::get('social_monitor_enabled', 'false') !== 'true') return false;

        $interval = (int)Setting::get('social_monitor_interval', '60'); // minutes
        $lastScan = Setting::get('social_last_scan', '');

        if (empty($lastScan)) return true;

        $elapsed = (time() - strtotime($lastScan)) / 60;
        return $elapsed >= $interval;
    }

    // ═══════════════════════════════════════════════════════════
    //  PLATFORM SCANNERS
    // ═══════════════════════════════════════════════════════════

    /** Google News RSS */
    private static function searchGoogleNews(string $keyword): array
    {
        $url  = 'https://news.google.com/rss/search?q=' . urlencode('"' . $keyword . '"') . '&hl=en&gl=UG&ceid=UG:en';
        $body = self::fetch($url, self::botUA());
        if (!$body) return [];

        return self::parseRss($body, 'google_news');
    }

    /** Reddit search RSS */
    private static function searchReddit(string $keyword): array
    {
        $url  = 'https://www.reddit.com/search.rss?q=' . urlencode('"' . $keyword . '"') . '&sort=new&limit=15';
        $body = self::fetch($url);
        if (!$body) return [];

        // Reddit uses Atom format
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body);
        libxml_clear_errors();
        if (!$xml) return [];

        $results = [];
        foreach ($xml->entry ?? [] as $entry) {
            $link = '';
            foreach ($entry->link as $l) {
                $href = (string)($l->attributes()['href'] ?? '');
                if ($href) { $link = $href; break; }
            }

            $results[] = [
                'platform' => 'reddit',
                'title'    => trim((string)($entry->title ?? '')),
                'url'      => $link,
                'snippet'  => mb_substr(strip_tags((string)($entry->content ?? '')), 0, 500),
                'author'   => trim((string)($entry->author->name ?? '')),
            ];
        }

        return array_slice($results, 0, self::MAX_PER_SOURCE);
    }

    /** Bing News RSS */
    private static function searchBingNews(string $keyword): array
    {
        $url  = 'https://www.bing.com/news/search?q=' . urlencode('"' . $keyword . '"') . '&format=rss&count=15';
        $body = self::fetch($url);
        if (!$body) return [];

        $results = self::parseRss($body, 'web');
        // Override platform for Bing News results
        foreach ($results as &$r) {
            $r['platform'] = 'google_news'; // Group with news
            $r['author']   = $r['author'] ?: 'Bing News';
        }
        return $results;
    }

    /** Twitter/X via Nitter RSS mirrors */
    private static function searchTwitter(string $keyword): array
    {
        foreach (self::NITTER_INSTANCES as $instance) {
            try {
                $url  = $instance . '/search/rss?f=tweets&q=' . urlencode('"' . $keyword . '"');
                $body = self::fetch($url, self::botUA(), 8); // shorter timeout
                if (!$body) continue;

                $results = self::parseRss($body, 'twitter');
                if (!empty($results)) return array_slice($results, 0, self::MAX_PER_SOURCE);
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    /** Hacker News via Algolia API (free, no key) */
    private static function searchHackerNews(string $keyword): array
    {
        $url = 'https://hn.algolia.com/api/v1/search_by_date?query=' . urlencode('"' . $keyword . '"') . '&tags=(story,comment)&hitsPerPage=10';
        $body = self::fetch($url);
        if (!$body) return [];

        $data = @json_decode($body, true);
        if (!$data || empty($data['hits'])) return [];

        $results = [];
        foreach ($data['hits'] as $hit) {
            $title = $hit['story_title'] ?? $hit['title'] ?? '';
            $url   = $hit['story_url'] ?? ('https://news.ycombinator.com/item?id=' . ($hit['story_id'] ?? $hit['objectID'] ?? ''));
            $text  = $hit['comment_text'] ?? $hit['story_text'] ?? '';

            $results[] = [
                'platform' => 'web',
                'title'    => strip_tags($title),
                'url'      => $url,
                'snippet'  => mb_substr(strip_tags($text), 0, 500),
                'author'   => $hit['author'] ?? 'HN User',
            ];
        }

        return $results;
    }

    /** YouTube search via RSS feed */
    private static function searchYouTube(string $keyword): array
    {
        // YouTube doesn't have a direct search RSS, but Google News often picks up YouTube videos
        // We use the Invidious API as a free alternative
        $instances = [
            'https://vid.puffyan.us',
            'https://invidious.snopyta.org',
            'https://yewtu.be',
        ];

        foreach ($instances as $instance) {
            try {
                $url  = $instance . '/api/v1/search?q=' . urlencode('"' . $keyword . '"') . '&type=video&sort=upload_date';
                $body = self::fetch($url, self::USER_AGENT, 8);
                if (!$body) continue;

                $data = @json_decode($body, true);
                if (!$data || !is_array($data)) continue;

                $results = [];
                foreach (array_slice($data, 0, 10) as $item) {
                    if (($item['type'] ?? '') !== 'video') continue;
                    $results[] = [
                        'platform' => 'web',
                        'title'    => $item['title'] ?? '',
                        'url'      => 'https://www.youtube.com/watch?v=' . ($item['videoId'] ?? ''),
                        'snippet'  => $item['description'] ?? '',
                        'author'   => $item['author'] ?? 'YouTube',
                    ];
                }

                if (!empty($results)) return $results;
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    /** General web search via DuckDuckGo HTML */
    private static function searchWeb(string $keyword): array
    {
        $url  = 'https://html.duckduckgo.com/html/?q=' . urlencode('"' . $keyword . '"');
        $body = self::fetch($url);
        if (!$body) return [];

        $results = [];

        // Parse DuckDuckGo HTML results
        if (preg_match_all('#<a[^>]+class="result__a"[^>]+href="([^"]+)"[^>]*>(.*?)</a>.*?<a[^>]+class="result__snippet"[^>]*>(.*?)</a>#is', $body, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 10) as $m) {
                $link = html_entity_decode($m[1]);
                // DuckDuckGo wraps URLs in a redirect, extract actual URL
                if (preg_match('/uddg=([^&]+)/', $link, $urlMatch)) {
                    $link = urldecode($urlMatch[1]);
                }

                $results[] = [
                    'platform' => 'web',
                    'title'    => strip_tags(html_entity_decode($m[2])),
                    'url'      => $link,
                    'snippet'  => mb_substr(strip_tags(html_entity_decode($m[3])), 0, 500),
                    'author'   => parse_url($link, PHP_URL_HOST) ?: 'Web',
                ];
            }
        }

        return $results;
    }

    /** Site-specific search (Facebook, Instagram, LinkedIn) via DuckDuckGo */
    private static function searchPlatformSite(string $site, string $keyword, string $platform): array
    {
        $url  = 'https://html.duckduckgo.com/html/?q=' . urlencode('site:' . $site . ' "' . $keyword . '"');
        $body = self::fetch($url);
        if (!$body) return [];

        $results = [];
        if (preg_match_all('#<a[^>]+class="result__a"[^>]+href="([^"]+)"[^>]*>(.*?)</a>.*?<a[^>]+class="result__snippet"[^>]*>(.*?)</a>#is', $body, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 10) as $m) {
                $link = html_entity_decode($m[1]);
                if (preg_match('/uddg=([^&]+)/', $link, $urlMatch)) {
                    $link = urldecode($urlMatch[1]);
                }
                // Only keep results actually from the target site
                if (!str_contains(strtolower($link), strtolower($site))) continue;

                $results[] = [
                    'platform' => $platform,
                    'title'    => strip_tags(html_entity_decode($m[2])),
                    'url'      => $link,
                    'snippet'  => mb_substr(strip_tags(html_entity_decode($m[3])), 0, 500),
                    'author'   => parse_url($link, PHP_URL_HOST) ?: $platform,
                ];
            }
        }

        return $results;
    }

    /** Mastodon public search API (mastodon.social — largest instance) */
    private static function searchMastodon(string $keyword): array
    {
        $instances = [
            'https://mastodon.social',
            'https://mstdn.social',
            'https://mas.to',
        ];

        foreach ($instances as $instance) {
            try {
                $url  = $instance . '/api/v2/search?q=' . urlencode('"' . $keyword . '"') . '&type=statuses&limit=15';
                $body = self::fetch($url, self::botUA(), 8);
                if (!$body) continue;

                $data = @json_decode($body, true);
                if (!$data || empty($data['statuses'])) continue;

                $results = [];
                foreach ($data['statuses'] as $status) {
                    $content = strip_tags($status['content'] ?? '');
                    $results[] = [
                        'platform' => 'mastodon',
                        'title'    => mb_substr($content, 0, 120),
                        'url'      => $status['url'] ?? $status['uri'] ?? '',
                        'snippet'  => mb_substr($content, 0, 500),
                        'author'   => ($status['account']['acct'] ?? '') ?: 'Mastodon User',
                    ];
                }

                if (!empty($results)) return array_slice($results, 0, self::MAX_PER_SOURCE);
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    /** Bluesky public search API (no auth required) */
    private static function searchBluesky(string $keyword): array
    {
        $url  = 'https://public.api.bsky.app/xrpc/app.bsky.feed.searchPosts?q=' . urlencode('"' . $keyword . '"') . '&limit=15&sort=latest';
        $body = self::fetch($url, self::botUA(), 10);
        if (!$body) return [];

        $data = @json_decode($body, true);
        if (!$data || empty($data['posts'])) return [];

        $results = [];
        foreach ($data['posts'] as $post) {
            $text   = $post['record']['text'] ?? '';
            $author = $post['author']['handle'] ?? 'Bluesky User';
            $uri    = $post['uri'] ?? '';
            // Convert AT URI to web URL: at://did:plc:xxx/app.bsky.feed.post/yyy → bsky.app profile link
            $webUrl = '';
            if (!empty($post['author']['handle']) && preg_match('#/app\.bsky\.feed\.post/([a-z0-9]+)$#', $uri, $m)) {
                $webUrl = 'https://bsky.app/profile/' . $post['author']['handle'] . '/post/' . $m[1];
            }

            $results[] = [
                'platform' => 'bluesky',
                'title'    => mb_substr($text, 0, 120),
                'url'      => $webUrl ?: $uri,
                'snippet'  => mb_substr($text, 0, 500),
                'author'   => '@' . $author,
            ];
        }

        return array_slice($results, 0, self::MAX_PER_SOURCE);
    }

    // ═══════════════════════════════════════════════════════════
    //  SHARED HELPERS
    // ═══════════════════════════════════════════════════════════

    /** Parse standard RSS 2.0 feed */
    private static function parseRss(string $body, string $platform): array
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body);
        libxml_clear_errors();
        if (!$xml || !isset($xml->channel->item)) return [];

        $results = [];
        $count   = 0;
        foreach ($xml->channel->item as $item) {
            if ($count++ >= self::MAX_PER_SOURCE) break;
            $results[] = [
                'platform' => $platform,
                'title'    => trim((string)($item->title ?? '')),
                'url'      => trim((string)($item->link ?? '')),
                'snippet'  => mb_substr(strip_tags((string)($item->description ?? '')), 0, 500),
                'author'   => trim((string)($item->source ?? $item->author ?? '')),
            ];
        }

        return $results;
    }

    /** Save a batch of results, returns count of new saves */
    private static function saveResults(array $results, string $keyword, bool $isComp): int
    {
        $saved = 0;
        foreach ($results as $result) {
            if (empty($result['url']) || self::isDuplicate($result['url'])) continue;

            $sentiment = self::classifySentiment($result['title'] . ' ' . ($result['snippet'] ?? ''));

            try {
                $row = BaseModel::queryOne(
                    "INSERT INTO social_mentions
                        (platform, author_name, content, mention_url, sentiment, keyword_matched, is_competitor)
                     VALUES
                        (:platform, :author, :content, :url, :sentiment, :keyword, :competitor)
                     ON CONFLICT DO NOTHING
                     RETURNING id",
                    [
                        ':platform'  => $result['platform'],
                        ':author'    => mb_substr($result['author'] ?? '', 0, 255) ?: null,
                        ':content'   => mb_substr($result['title'] . ': ' . ($result['snippet'] ?? ''), 0, 2000),
                        ':url'       => $result['url'],
                        ':sentiment' => $sentiment,
                        ':keyword'   => $keyword,
                        ':competitor'=> $isComp ? 'true' : 'false',
                    ]
                );
                if ($row) $saved++;
            } catch (\Throwable) {}
        }

        return $saved;
    }

    /** Classify sentiment */
    private static function classifySentiment(string $text): string
    {
        $lower = strtolower($text);
        $pos = $neg = 0;

        foreach (self::POSITIVE_WORDS as $w) { if (str_contains($lower, $w)) $pos++; }
        foreach (self::NEGATIVE_WORDS as $w) { if (str_contains($lower, $w)) $neg++; }

        if ($neg > $pos && $neg >= 2) return SocialMention::NEGATIVE;
        if ($pos > $neg && $pos >= 2) return SocialMention::POSITIVE;
        if ($neg > 0 && $pos === 0)   return SocialMention::NEGATIVE;
        if ($pos > 0 && $neg === 0)   return SocialMention::POSITIVE;
        return SocialMention::NEUTRAL;
    }

    /** Deduplicate by URL */
    private static function isDuplicate(string $url): bool
    {
        return (bool)BaseModel::queryOne(
            "SELECT id FROM social_mentions WHERE mention_url = :url LIMIT 1",
            [':url' => $url]
        );
    }

    /** Alert on negative sentiment spike */
    private static function checkNegativeSpike(): void
    {
        if (Setting::get('social_alert_negative', 'false') !== 'true') return;
        $email = Setting::get('social_alert_email', '');
        if (empty($email)) return;

        $recent = (int)BaseModel::queryColumn(
            "SELECT COUNT(*) FROM social_mentions WHERE sentiment = 'negative' AND found_at > NOW() - INTERVAL '2 hours'"
        );

        if ($recent >= 5) {
            @mail(
                $email,
                '[' . \get_site_setting('site_title', 'News Site') . '] Negative sentiment spike detected',
                "{$recent} negative mentions detected in the last 2 hours.\n\n"
                . "Review them at: /admin/crawler/social?sentiment=negative\n\n"
                . "— " . \get_site_setting('site_title', 'News Site') . " Social Monitor",
                "From: noreply@" . parse_url(\get_site_setting('site_url', 'http://localhost'), PHP_URL_HOST) . "\r\nContent-Type: text/plain; charset=UTF-8"
            );
        }
    }

    /** Fetch URL with cURL (more reliable than file_get_contents) */
    private static function fetch(string $url, ?string $ua = null, ?int $timeout = null): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout ?? self::FETCH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => $ua ?? self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($data !== false && $code >= 200 && $code < 400) ? $data : null;
    }
}