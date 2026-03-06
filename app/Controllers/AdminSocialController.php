<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Setting;
use App\Models\SocialKeyword;
use App\Models\SocialMention;
use App\Services\Csrf;
use App\Services\Flash;
use App\Services\SocialMonitorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminSocialController extends Controller
{
    /** Social Monitor dashboard. */
    public function index(): Response
    {
        // Gracefully handle unmigrated database (0036_social_monitor.sql)
        try {
            SocialMention::queryOne("SELECT 1 FROM social_mentions LIMIT 1");
        } catch (\Throwable) {
            Flash::set('error', 'Social Monitor tables not found. Run migration: docker exec -it northern_times_app php database/migrate.php');
            return $this->render('admin/crawler/social', [
                'mentions' => [], 'stats' => ['total_mentions' => 0, 'today_mentions' => 0, 'unread_mentions' => 0],
                'sentimentStats' => [], 'platformStats' => [], 'keywords' => [], 'trend' => [],
                'filterPlatform' => null, 'filterSentiment' => null,
                'settings' => ['social_monitor_enabled' => 'false', 'social_monitor_interval' => '60',
                    'social_alert_negative' => 'true', 'social_alert_email' => '', 'social_last_scan' => ''],
                'csrf' => Csrf::token(), 'flash_success' => '', 'flash_error' => Flash::get('error'),
            ]);
        }

        $req       = Request::createFromGlobals();
        $platform  = $req->query->get('platform') ?: null;
        $sentiment = $req->query->get('sentiment') ?: null;

        $mentions  = SocialMention::recent(50, $platform, $sentiment);
        $stats     = SocialMention::dashboardStats();
        $sentStats = SocialMention::sentimentStats(7);
        $platStats = SocialMention::platformStats(7);
        $keywords  = SocialKeyword::all();
        $trend     = SocialMention::dailyTrend(14);

        return $this->render('admin/crawler/social', [
            'mentions'       => $mentions,
            'stats'          => $stats,
            'sentimentStats' => $sentStats,
            'platformStats'  => $platStats,
            'keywords'       => $keywords,
            'trend'          => $trend,
            'filterPlatform' => $platform,
            'filterSentiment'=> $sentiment,
            'settings'       => [
                'social_monitor_enabled'  => Setting::get('social_monitor_enabled', 'false'),
                'social_monitor_interval' => Setting::get('social_monitor_interval', '60'),
                'social_alert_negative'   => Setting::get('social_alert_negative', 'true'),
                'social_alert_email'      => Setting::get('social_alert_email', ''),
                'social_last_scan'        => Setting::get('social_last_scan', ''),
            ],
            'csrf'           => Csrf::token(),
            'flash_success'  => Flash::get('success'),
            'flash_error'    => Flash::get('error'),
        ]);
    }

    /** Run scan now. */
    public function scan(): Response
    {
        try {
            $result = SocialMonitorService::scan();
            if (isset($result['skipped'])) {
                Flash::set('error', 'Social monitor is disabled. Enable it in settings.');
            } else {
                Flash::set('success', "Scan complete: {$result['found']} found, {$result['saved']} new mentions saved.");
            }
        } catch (\Throwable $e) {
            Flash::set('error', 'Scan failed: ' . $e->getMessage());
        }

        return $this->redirect('/admin/crawler/social');
    }

    /** Add a keyword. */
    public function addKeyword(): Response
    {
        $req     = Request::createFromGlobals();
        $keyword = trim((string)$req->request->get('keyword', ''));
        $isComp  = (bool)$req->request->get('is_competitor');

        if ($keyword === '') {
            Flash::set('error', 'Keyword cannot be empty.');
            return $this->redirect('/admin/crawler/social');
        }

        SocialKeyword::store($keyword, $isComp);
        Flash::set('success', "Keyword \"{$keyword}\" added.");
        return $this->redirect('/admin/crawler/social');
    }

    /** Delete a keyword. */
    public function deleteKeyword(string $id): Response
    {
        SocialKeyword::remove((int)$id);
        Flash::set('success', 'Keyword removed.');
        return $this->redirect('/admin/crawler/social');
    }

    /** Toggle keyword active/inactive. */
    public function toggleKeyword(string $id): Response
    {
        SocialKeyword::toggleActive((int)$id);
        Flash::set('success', 'Keyword updated.');
        return $this->redirect('/admin/crawler/social');
    }

    /** Mark all mentions as read. */
    public function markAllRead(): Response
    {
        SocialMention::markAllRead();
        Flash::set('success', 'All mentions marked as read.');
        return $this->redirect('/admin/crawler/social');
    }

    /** Save social monitor settings. */
    public function saveSettings(): Response
    {
        $req = Request::createFromGlobals();

        Setting::set('social_monitor_enabled', $req->request->get('social_monitor_enabled') ? 'true' : 'false', 'social');
        Setting::set('social_monitor_interval', (string)(int)$req->request->get('social_monitor_interval', 60), 'social');
        Setting::set('social_alert_negative', $req->request->get('social_alert_negative') ? 'true' : 'false', 'social');
        Setting::set('social_alert_email', trim((string)$req->request->get('social_alert_email', '')), 'social');

        Flash::set('success', 'Social monitor settings saved.');
        return $this->redirect('/admin/crawler/social');
    }

    /** Competitor monitoring view. */
    public function competitors(): Response
    {
        $mentions = SocialMention::competitorMentions(50);

        return $this->render('admin/crawler/social', [
            'mentions'       => $mentions,
            'stats'          => SocialMention::dashboardStats(),
            'sentimentStats' => SocialMention::sentimentStats(7),
            'platformStats'  => SocialMention::platformStats(7),
            'keywords'       => SocialKeyword::all(),
            'trend'          => SocialMention::dailyTrend(14),
            'filterPlatform' => null,
            'filterSentiment'=> null,
            'settings'       => [
                'social_monitor_enabled'  => Setting::get('social_monitor_enabled', 'false'),
                'social_monitor_interval' => Setting::get('social_monitor_interval', '60'),
                'social_alert_negative'   => Setting::get('social_alert_negative', 'true'),
                'social_alert_email'      => Setting::get('social_alert_email', ''),
                'social_last_scan'        => Setting::get('social_last_scan', ''),
            ],
            'csrf'           => Csrf::token(),
            'flash_success'  => Flash::get('success'),
            'flash_error'    => Flash::get('error'),
        ]);
    }
}