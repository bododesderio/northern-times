<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Parse user agent strings into device_type, browser, and OS.
 */
final class UserAgentParser
{
    public static function parse(string $ua): array
    {
        $lower = strtolower($ua);

        return [
            'device_type' => self::detectDevice($lower),
            'browser'     => self::detectBrowser($lower),
            'os'          => self::detectOS($lower),
        ];
    }

    private static function detectDevice(string $ua): string
    {
        if (preg_match('/bot|crawl|spider|slurp|wget|curl|headless|phantom|puppeteer/i', $ua)) {
            return 'bot';
        }
        if (preg_match('/tablet|ipad|playbook|silk|kindle|sm-t|gt-p/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|android.*mobile|iphone|ipod|blackberry|opera mini|iemobile|wpdesktop|windows phone|symbian/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    private static function detectBrowser(string $ua): string
    {
        // Order matters — check more specific browsers first
        if (str_contains($ua, 'edg/') || str_contains($ua, 'edga/') || str_contains($ua, 'edgios/') || str_contains($ua, 'edge/')) {
            return 'Edge';
        }
        if (str_contains($ua, 'brave')) {
            return 'Brave';
        }
        if (str_contains($ua, 'vivaldi')) {
            return 'Vivaldi';
        }
        if (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            return 'Opera';
        }
        if (str_contains($ua, 'ucbrowser') || str_contains($ua, 'ucweb')) {
            return 'UC Browser';
        }
        if (str_contains($ua, 'samsungbrowser') || str_contains($ua, 'samsung')) {
            return 'Samsung Internet';
        }
        if (str_contains($ua, 'yabrowser')) {
            return 'Yandex';
        }
        if (str_contains($ua, 'crios') || str_contains($ua, 'crmo')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'fxios')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'chrome') && !str_contains($ua, 'edg') && !str_contains($ua, 'opr')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'safari') && !str_contains($ua, 'chrome') && !str_contains($ua, 'chromium')) {
            return 'Safari';
        }
        if (str_contains($ua, 'firefox') || str_contains($ua, 'gecko/')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'msie') || str_contains($ua, 'trident')) {
            return 'IE';
        }
        if (str_contains($ua, 'curl') || str_contains($ua, 'wget') || str_contains($ua, 'httpie')) {
            return 'CLI';
        }
        return 'Other';
    }

    private static function detectOS(string $ua): string
    {
        if (str_contains($ua, 'windows nt 10') || str_contains($ua, 'windows nt 11')) {
            return 'Windows 10+';
        }
        if (str_contains($ua, 'windows')) {
            return 'Windows';
        }
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod')) {
            return 'iOS';
        }
        if (str_contains($ua, 'mac os') || str_contains($ua, 'macintosh')) {
            return 'macOS';
        }
        if (str_contains($ua, 'android')) {
            // Try to get version
            if (preg_match('/android (\d+)/', $ua, $m)) {
                return 'Android ' . $m[1];
            }
            return 'Android';
        }
        if (str_contains($ua, 'chromeos') || str_contains($ua, 'cros')) {
            return 'ChromeOS';
        }
        if (str_contains($ua, 'ubuntu')) {
            return 'Ubuntu';
        }
        if (str_contains($ua, 'linux')) {
            return 'Linux';
        }
        if (str_contains($ua, 'curl') || str_contains($ua, 'wget')) {
            return 'CLI';
        }
        return 'Other';
    }
}
