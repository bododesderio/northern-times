<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\RobotsChecker;

/**
 * Industry-grade full-page article scraper.
 *
 * Extraction pipeline:
 *   1. Fetch page with real browser headers + cookie jar
 *   2. Extract hero image (og:image → twitter:image → schema → largest in-body)
 *   3. Try custom CSS selector (if configured per-source)
 *   4. Try known site-specific selectors
 *   5. Try generic content selectors
 *   6. Fallback: text-density algorithm (Readability-style)
 *   7. Strip boilerplate (nav, ads, share, related, WP cruft)
 *   8. Fix relative URLs → absolute
 *   9. Only return if scraped is longer than RSS content
 */
final class ArticleScraper
{
    private const TIMEOUT  = 12;
    // No artificial size limits — extract full articles regardless of size

    private const SITE_SELECTORS = [
        'aljazeera.com'      => ['.wysiwyg', '.article__body-content', '#main-content-area'],
        'theguardian.com'    => ['.article-body-commercial-selector', '.content--article-body', '[data-gu-name="body"]'],
        'bbc.com'            => ['[data-component="text-block"]', '.ssrcss-11r1m41-RichTextComponentWrapper', '.story-body__inner'],
        'bbc.co.uk'          => ['[data-component="text-block"]', '.ssrcss-11r1m41-RichTextComponentWrapper', '.story-body__inner'],
        'reuters.com'        => ['.article-body__content', '.ArticleBody__content'],
        'cnn.com'            => ['.article__content', '.zn-body__paragraph'],
        'nytimes.com'        => ['.StoryBodyCompanionColumn', '.story-body', '[name="articleBody"]'],
        'apnews.com'         => ['.RichTextStoryBody', '.Article'],
        'npr.org'            => ['#storytext', '.storytext'],
        'france24.com'       => ['.t-content__body', '.article__text'],
        'dw.com'             => ['.rich-text', '.longText'],
        'monitor.co.ug'      => ['.article-body', '.body-text'],
        'observer.ug'        => ['.entry-content', '.post-content'],
        'independent.co.ug'  => ['.entry-content', '.tdb-block-inner'],
        'nilepost.co.ug'     => ['.entry-content', '.post-content'],
        'newvision.co.ug'    => ['.article-body', '.field-name-body'],
        'nation.africa'      => ['.article-body', '.paragraph-wrapper'],
        'theeastafrican.co.ke' => ['.article-body', '.paragraph-wrapper'],
        'citizen.digital'    => ['.article-body', '.entry-content'],
        'thecitizen.co.tz'   => ['.article-body', '.field-name-body'],
        'news24.com'         => ['.article__body', '.article_body'],
        'timeslive.co.za'    => ['.article-widgets', '.text'],
        'punchng.com'        => ['.entry-content', '.post-content'],
        'premiumtimesng.com' => ['.entry-content', '.post-content'],
        'africanews.com'     => ['.article__text', '.article-content'],
        'independent.co.uk'  => ['#main-content article', '.article-body'],
        'telegraph.co.uk'    => ['.article-body-text', '.articleBodyText'],
        'skynews.com'        => ['.sdc-article-body', '.article-body'],
        'mirror.co.uk'       => ['.article-body', '.body-content'],
        'nbcnews.com'        => ['.article-body', '.article-body__content'],
        'abcnews.go.com'     => ['.Article__Content', '.article-copy'],
        'cbsnews.com'        => ['.content__body', '.article-body'],
        'usatoday.com'       => ['.gnt_ar_b', '.article-body'],
        'foxnews.com'        => ['.article-body', '.body-text'],
        'thehindu.com'       => ['.article', '#content-body-14269002-0'],
        'scmp.com'           => ['.article-body', '.body-output'],
        'smh.com.au'         => ['[data-testid="article-body"]', '.article__body'],
        'dokolopost.com'     => ['.entry-content', '.post-content'],
        'ugandaradionetwork.net' => ['.entry-content', '.post-content'],
    ];

    private const GENERIC_SELECTORS = [
        'article .entry-content', 'article .post-content',
        'article .article-content', 'article .article-body',
        'article .story-body', 'article .td-post-content',
        '.entry-content', '.post-content', '.article-content',
        '.article-body', '.story-body', '.td-post-content',
        '.post-body', '.field-name-body', '.node-content', '.body-text',
        '[itemprop="articleBody"]', '[role="main"] article',
        'article', 'main .content', 'main',
    ];

    private const STRIP_TAGS = [
        'script', 'style', 'noscript', 'svg', 'form', 'input',
        'button', 'select', 'textarea', 'nav', 'header', 'footer', 'aside',
    ];

    private const STRIP_CLASSES = [
        'sidebar', 'widget', 'ad', 'ads', 'advert', 'advertisement',
        'ad-slot', 'ad-container', 'social-share', 'share-buttons',
        'sharing', 'social-links', 'share-this', 'sd-sharing', 'sd-like',
        'sd-content', 'sd-block', 'ssba', 'heateor_sss', 'addtoany',
        'sharedaddy', 'jp-relatedposts', 'jetpack-likes', 'likes-widget',
        'related-posts', 'related-articles', 'related', 'yarpp-related',
        'wp-related', 'comments', 'comment-section', 'comment-respond',
        'respond', 'newsletter', 'subscribe', 'signup', 'sign-up',
        'breadcrumb', 'breadcrumbs', 'author-box', 'author-info',
        'author-bio', 'tags', 'tag-list', 'post-tags', 'entry-tags',
        'navigation', 'nav-links', 'post-navigation', 'pager',
        'popup', 'modal', 'overlay', 'cookie-banner', 'consent',
    ];

    // ── Public API ──────────────────────────────────────────────

    public static function scrape(string $url, array $source = []): ?array
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) return null;

        // robots.txt compliance — skip article if disallowed (admin-toggleable)
        if (\App\Models\Setting::get('crawler_robots_check', 'false') === 'true' && !RobotsChecker::isAllowed($url)) {
            error_log("ArticleScraper: robots.txt disallows {$url}");
            return null;
        }

        try {
            $html = self::fetchPage($url);
            if (!$html || strlen($html) < 500) return null;

            libxml_use_internal_errors(true);
            $doc = new \DOMDocument('1.0', 'UTF-8');
            @$doc->loadHTML(
                '<meta charset="UTF-8">' . $html,
                LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED
            );
            $xpath = new \DOMXPath($doc);

            $heroImage = self::extractHeroImage($xpath, $url);
            $excerpt   = self::extractExcerpt($xpath);
            $content   = self::extractContent($doc, $xpath, $url, $source);

            $textLen = strlen(strip_tags($content ?? ''));

            // If DOM extraction failed or got <500 chars, try Readability.js
            if ($textLen < 500 && !empty($html)) {
                $readability = self::readabilityExtract($html, $url);
                if ($readability) {
                    $rdLen = strlen(strip_tags($readability['content']));
                    if ($rdLen > $textLen) {
                        $content = $readability['content'];
                        $textLen = $rdLen;
                        if (empty($excerpt) && !empty($readability['excerpt'])) {
                            $excerpt = $readability['excerpt'];
                        }
                    }
                }
            }

            if (!$heroImage && $content) {
                $heroImage = self::extractFirstLargeImage($content);
            }

            libxml_clear_errors();

            if ($content && $textLen > 400) {
                return [
                    'content'     => $content,
                    'hero_image'  => $heroImage,
                    'excerpt'     => $excerpt,
                    'text_length' => $textLen,
                ];
            }
            return null;

        } catch (\Throwable $e) {
            error_log("ArticleScraper::scrape failed [{$url}]: " . $e->getMessage());
            libxml_clear_errors();
            return null;
        }
    }

    // ── Fetch with browser-grade headers ────────────────────────

    private static function fetchPage(string $url): ?string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ];

        $cookieFile = sys_get_temp_dir() . '/crawl_' . hash('sha256', $url) . '.txt';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 8,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => $agents[array_rand($agents)],
            CURLOPT_SSL_VERIFYPEER => ($_ENV['VERIFY_SSL'] ?? 'true') !== 'false',
            CURLOPT_SSL_VERIFYHOST => ($_ENV['VERIFY_SSL'] ?? 'true') !== 'false' ? 2 : 0,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate',
                'DNT: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Upgrade-Insecure-Requests: 1',
                'X-Crawler-Identity: ' . RobotsChecker::USER_AGENT_FULL,
            ],
            CURLOPT_ENCODING => '',
        ]);

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        @unlink($cookieFile);

        if ($data === false || $code < 200 || $code >= 400) return null;

        if (!mb_check_encoding($data, 'UTF-8')) {
            if (preg_match('/charset=["\']?([^"\';\s]+)/i', substr($data, 0, 5000), $m)) {
                $data = @mb_convert_encoding($data, 'UTF-8', $m[1]) ?: $data;
            } else {
                $data = @mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII') ?: $data;
            }
        }
        return $data;
    }

    // ── Multi-strategy content extraction ───────────────────────

    private static function extractContent(\DOMDocument $doc, \DOMXPath $xpath, string $url, array $source): ?string
    {
        $results = [];

        // 1. Custom source selector
        if (!empty($source['content_selector'])) {
            $h = self::trySelector($doc, $xpath, $source['content_selector']);
            if ($h) $results['custom'] = $h;
        }

        // 2. Site-specific selectors
        $domain = preg_replace('/^www\./i', '', parse_url($url, PHP_URL_HOST) ?? '');
        if (isset(self::SITE_SELECTORS[$domain])) {
            foreach (self::SITE_SELECTORS[$domain] as $sel) {
                $h = self::trySelector($doc, $xpath, $sel);
                if ($h && strlen(strip_tags($h)) > 200) { $results['site'] = $h; break; }
            }
        }

        // 3. Generic selectors
        if (empty($results)) {
            foreach (self::GENERIC_SELECTORS as $sel) {
                $h = self::trySelector($doc, $xpath, $sel);
                if ($h && strlen(strip_tags($h)) > 200) { $results['generic'] = $h; break; }
            }
        }

        // 4. Text-density fallback (Readability-style)
        if (empty($results)) {
            $h = self::textDensityExtract($doc, $xpath);
            if ($h) $results['density'] = $h;
        }

        if (empty($results)) return null;

        // Pick longest
        $best = '';
        foreach ($results as $h) {
            if (strlen(strip_tags($h)) > strlen(strip_tags($best))) $best = $h;
        }

        $best = self::cleanExtractedHtml($best);
        $best = self::makeUrlsAbsolute($best, $url);
        return $best ?: null;
    }

    private static function trySelector(\DOMDocument $doc, \DOMXPath $xpath, string $selector): ?string
    {
        $xq = self::cssToXpath(trim($selector));
        if (!$xq) return null;

        try {
            $nodes = $xpath->query($xq);
            if (!$nodes || $nodes->length === 0) return null;

            $node = $nodes->item(0);
            self::stripUnwanted($node, $xpath);

            $html = '';
            foreach ($node->childNodes as $child) {
                $html .= $doc->saveHTML($child);
            }
            return (strlen(strip_tags($html)) > 400) ? $html : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Text-density extraction (Readability-inspired).
     * Score every block node by text_length + p_tag_bonus - link_penalty.
     */
    private static function textDensityExtract(\DOMDocument $doc, \DOMXPath $xpath): ?string
    {
        $candidates = [];
        $containers = $xpath->query('//div|//article|//section|//main|//td');
        if (!$containers) return null;

        foreach ($containers as $node) {
            $text = trim($node->textContent);
            $textLen = mb_strlen($text);
            if ($textLen < 200) continue;

            $pTags = 0; $imgTags = 0;
            foreach ($node->getElementsByTagName('p') as $_) $pTags++;
            foreach ($node->getElementsByTagName('img') as $_) $imgTags++;

            $linkLen = 0;
            foreach ($node->getElementsByTagName('a') as $a) $linkLen += mb_strlen(trim($a->textContent));
            if ($textLen > 0 && ($linkLen / $textLen) > 0.5) continue;

            $score = $textLen + ($pTags * 50) + ($imgTags * 20) - ($linkLen * 2);

            $cls = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id'));
            foreach (['nav', 'sidebar', 'footer', 'header', 'menu', 'comment', 'widget'] as $bad) {
                if (str_contains($cls, $bad)) { $score *= 0.2; break; }
            }
            foreach (['article', 'content', 'entry', 'post', 'story', 'body', 'text'] as $good) {
                if (str_contains($cls, $good)) { $score *= 1.5; break; }
            }

            $candidates[] = ['node' => $node, 'score' => $score];
        }

        if (empty($candidates)) return null;
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        $bestNode = $candidates[0]['node'];
        self::stripUnwanted($bestNode, $xpath);

        $html = '';
        foreach ($bestNode->childNodes as $child) $html .= $doc->saveHTML($child);
        return (strlen(strip_tags($html)) > 400) ? $html : null;
    }

    // ── DOM cleanup ─────────────────────────────────────────────

    private static function stripUnwanted(\DOMNode $node, \DOMXPath $xpath): void
    {
        $toRemove = [];
        foreach (self::STRIP_TAGS as $tag) {
            try { $els = $xpath->query('.//' . $tag, $node); if ($els) foreach ($els as $el) $toRemove[] = $el; } catch (\Throwable) {}
        }
        foreach (self::STRIP_CLASSES as $cls) {
            try {
                $els = $xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," ' . $cls . ' ")]', $node);
                if ($els) foreach ($els as $el) $toRemove[] = $el;
            } catch (\Throwable) {}
        }
        foreach (['comments', 'respond', 'social', 'share', 'newsletter', 'related'] as $id) {
            try { $els = $xpath->query('.//*[contains(@id,"' . $id . '")]', $node); if ($els) foreach ($els as $el) $toRemove[] = $el; } catch (\Throwable) {}
        }

        $seen = new \SplObjectStorage();
        foreach ($toRemove as $el) {
            if (!$seen->contains($el) && $el->parentNode) { $seen->attach($el); $el->parentNode->removeChild($el); }
        }
    }

    // ── HTML cleaning ───────────────────────────────────────────

    // ── Readability.js extraction (Mozilla) ───────────────────────

    /**
     * Send raw HTML to Node.js Readability for clean extraction.
     * This is the same engine Firefox Reader View uses.
     * Returns null if Node.js or Readability is unavailable.
     */
    private static function readabilityExtract(string $html, string $url): ?array
    {
        $script = '/opt/readability/extract.js';
        if (!file_exists($script)) return null;

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $cmd = 'node ' . escapeshellarg($script) . ' ' . escapeshellarg($url);
        $proc = @proc_open($cmd, $descriptors, $pipes, null, null);
        if (!is_resource($proc)) return null;

        // Write HTML to stdin
        fwrite($pipes[0], $html);
        fclose($pipes[0]);

        // Read stdout
        $output = stream_get_contents($pipes[1], 2 * 1024 * 1024); // 2MB max
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);
        if ($exitCode !== 0 || empty($output)) return null;

        $data = @json_decode($output, true);
        if (!$data || isset($data['error']) || empty($data['content'])) return null;

        return $data;
    }

    private static function cleanExtractedHtml(string $html): string
    {
        // Convert lazy-load data-src to real src BEFORE stripping
        $html = preg_replace_callback('#<img([^>]*)>#i', function($m) {
            $attrs = $m[1];
            $hasSrc = preg_match('/\bsrc=["\']([^"\']+)["\']/', $attrs, $srcM);
            $srcVal = $hasSrc ? $srcM[1] : '';
            $isPlaceholder = empty($srcVal) || str_contains($srcVal, 'data:') || str_contains($srcVal, 'placeholder') || str_contains($srcVal, '1x1') || str_contains($srcVal, 'blank');
            if ($isPlaceholder) {
                foreach (['data-src', 'data-lazy-src', 'data-original', 'data-full-src'] as $da) {
                    if (preg_match('/' . preg_quote($da) . '=["\']([^"\']+)["\']/', $attrs, $dm)) {
                        $attrs = $hasSrc
                            ? preg_replace('/\bsrc=["\'][^"\']+["\']/', 'src="' . $dm[1] . '"', $attrs)
                            : ('src="' . $dm[1] . '" ' . $attrs);
                        break;
                    }
                }
            }
            return '<img' . $attrs . '>';
        }, $html);

        $html = preg_replace('/\s+style="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+class="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+id="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+data-[\w-]+="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+on\w+="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+aria-[\w-]+="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+role="[^"]*"/i', '', $html);

        // Strip author bio / share / social / subscribe blocks by common patterns
        $junkBlocks = [
            '#<div[^>]*(?:author[-_]?bio|author[-_]?box|author[-_]?card|byline[-_]?box)[^>]*>.*?</div>#is',
            '#<div[^>]*(?:share[-_]?bar|share[-_]?button|social[-_]?share|addtoany|addthis|sharedaddy)[^>]*>.*?</div>#is',
            '#<div[^>]*(?:related[-_]?posts?|yarpp|jp[-_]?relatedposts|more[-_]?stories)[^>]*>.*?</div>#is',
            '#<div[^>]*(?:newsletter|subscribe|email[-_]?signup|opt[-_]?in)[^>]*>.*?</div>#is',
            '#<div[^>]*(?:post[-_]?tags|entry[-_]?tags|tag[-_]?list)[^>]*>.*?</div>#is',
            '#<div[^>]*(?:post[-_]?navigation|nav[-_]?links|pagination)[^>]*>.*?</div>#is',
            '#<ul[^>]*(?:social[-_]?links|share[-_]?links|follow[-_]?us)[^>]*>.*?</ul>#is',
        ];
        foreach ($junkBlocks as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        $html = preg_replace('#<p[^>]*>\s*The post\s+<a[^>]*>.*?</a>\s+appeared first on\s+<a[^>]*>.*?</a>\.\s*</p>#is', '', $html);
        $html = preg_replace('#<p[^>]*>\s*The post\s+.{5,300}\s+appeared first on\s+.{3,100}\.\s*</p>#is', '', $html);
        $html = preg_replace('#<h[2-6][^>]*>\s*Share\s+this\s*:?\s*</h[2-6]>.*?(?=<h[1-2]|$)#is', '', $html);
        $html = preg_replace('#<h[2-6][^>]*>\s*Like\s+this\s*:?\s*</h[2-6]>.*?(?=<h[1-2]|$)#is', '', $html);
        $html = preg_replace('#<h[2-6][^>]*>\s*Related\s*:?\s*</h[2-6]>.*$#is', '', $html);
        $html = preg_replace('#<ul[^>]*>\s*(?:<li[^>]*>\s*<a[^>]*>Share\s+on\s+\w+[^<]*</a>\s*</li>\s*){2,}</ul>#is', '', $html);
        $html = preg_replace('#<a[^>]*>Share\s+on\s+\w+\s*\([^)]*\)\s*\w*</a>#is', '', $html);
        $html = preg_replace('#<p[^>]*>\s*Like\s+Loading\s*\.{0,3}\s*</p>#is', '', $html);
        $html = preg_replace('#<p[^>]*>\s*<a[^>]*>\s*See\s+also\s+[^<]+</a>\s*</p>#is', '', $html);

        $html = preg_replace('#<(p|div|span|li|ul|ol|h[1-6])(\s[^>]*)?>\s*</\1>#i', '', $html);
        $html = preg_replace('#<(p|div|span|li|ul|ol|h[1-6])(\s[^>]*)?>\s*</\1>#i', '', $html);

        $html = preg_replace_callback('#<iframe[^>]*>.*?</iframe>#is', function($m) {
            if (preg_match('/youtube|youtu\.be|vimeo|dailymotion|twitter|x\.com|instagram|facebook|fb\.watch|tiktok|rumble|bitchute|odysee|spotify|soundcloud|streamable|jwplatform|brightcove|kaltura|vidyard|wistia/i', $m[0])) {
                return '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%">' . $m[0] . '</div>';
            }
            return '';
        }, $html);

        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        return trim($html);
    }

    // ── Meta extraction ─────────────────────────────────────────

    private static function extractHeroImage(\DOMXPath $xpath, string $baseUrl): ?string
    {
        $src = self::getMeta($xpath, 'og:image');
        if ($src && strlen($src) > 10) return self::resolveUrl($src, $baseUrl);
        $src = self::getMeta($xpath, 'twitter:image');
        if ($src && strlen($src) > 10) return self::resolveUrl($src, $baseUrl);
        try {
            $nodes = $xpath->query('//*[@itemprop="image"]/@content|//*[@itemprop="image"]/@src');
            if ($nodes && $nodes->length > 0) return self::resolveUrl($nodes->item(0)->nodeValue, $baseUrl);
        } catch (\Throwable) {}
        return null;
    }

    private static function extractExcerpt(\DOMXPath $xpath): ?string
    {
        $d = self::getMeta($xpath, 'og:description') ?? self::getMeta($xpath, 'description');
        return ($d && strlen($d) > 20) ? mb_substr(trim($d), 0, 500) : null;
    }

    private static function extractFirstLargeImage(string $html): ?string
    {
        if (preg_match_all('#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#i', $html, $m)) {
            foreach ($m[1] as $src) {
                if (str_contains($src, 'data:')) continue;
                if (preg_match('/\b(icon|logo|avatar|pixel|track|badge|emoji|gravatar)\b/i', $src)) continue;
                return $src;
            }
        }
        return null;
    }

    // ── Utilities ───────────────────────────────────────────────

    private static function getMeta(\DOMXPath $xpath, string $name): ?string
    {
        foreach (['property', 'name'] as $attr) {
            try {
                $nodes = $xpath->query('//meta[@' . $attr . '="' . $name . '"]/@content');
                if ($nodes && $nodes->length > 0) { $v = trim($nodes->item(0)->nodeValue); if ($v !== '') return $v; }
            } catch (\Throwable) {}
        }
        return null;
    }

    private static function cssToXpath(string $css): string
    {
        $parts = preg_split('/\s+/', trim($css));
        $xp = [];
        foreach ($parts as $part) {
            if (str_starts_with($part, '.')) {
                $xp[] = '//*[contains(concat(" ",normalize-space(@class)," ")," ' . substr($part, 1) . ' ")]';
            } elseif (str_starts_with($part, '#')) {
                $xp[] = '//*[@id="' . substr($part, 1) . '"]';
            } elseif (str_starts_with($part, '[')) {
                if (preg_match('/\[([\w-]+)=["\']([^"\']+)["\']\]/', $part, $m))
                    $xp[] = '//*[@' . $m[1] . '="' . $m[2] . '"]';
            } elseif (str_contains($part, '.')) {
                [$tag, $cls] = explode('.', $part, 2);
                $xp[] = '//' . $tag . '[contains(concat(" ",normalize-space(@class)," ")," ' . $cls . ' ")]';
            } else {
                $xp[] = '//' . $part;
            }
        }
        return implode('', $xp);
    }

    private static function makeUrlsAbsolute(string $html, string $baseUrl): string
    {
        $p = parse_url($baseUrl);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        $html = preg_replace('#((?:src|href)=["\'])(//[^"\']+)#i', '$1https:$2', $html);
        $html = preg_replace_callback(
            '#((?:src|href)=["\'])(/[^/"\'][^"\']*)(["\']{1})#i',
            fn($m) => $m[1] . $origin . $m[2] . $m[3], $html
        );
        return $html;
    }

    private static function resolveUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) return $url;
        $p = parse_url($baseUrl);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        if (str_starts_with($url, '//')) return ($p['scheme'] ?? 'https') . ':' . $url;
        if (str_starts_with($url, '/')) return $origin . $url;
        return $origin . '/' . $url;
    }
}