<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ImageHealthLog;
use App\Models\SeoAudit;
use App\Models\SeoIssue;
use App\Models\Setting;
use App\Services\Csrf;
use App\Services\Flash;
use App\Services\ImageHealthChecker;
use App\Services\SeoAuditEngine;
use App\Services\SeoReportPdf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminSeoController extends Controller
{
    /** SEO Audit dashboard. */
    public function index(): Response
    {
        // Gracefully handle unmigrated database (0035_seo_audit.sql)
        try {
            SeoAudit::queryOne("SELECT 1 FROM seo_audits LIMIT 1");
        } catch (\Throwable) {
            Flash::set('error', 'SEO Audit tables not found. Run migration: php database/migrate.php');
            return $this->render('admin/crawler/seo', [
                'latest' => null, 'recentAudits' => [], 'trend' => [], 'issueSummary' => [],
                'checkDetails' => [], 'settings' => ['seo_audit_enabled' => 'false', 'seo_audit_schedule' => 'weekly', 'seo_last_run' => ''],
                'csrf' => Csrf::token(), 'flash_success' => '', 'flash_error' => Flash::get('error'),
            ]);
        }

        $latest = SeoAudit::latest();
        $recent = SeoAudit::recent(10);
        $trend  = SeoAudit::scoreTrend(10);

        $issues  = $latest ? SeoIssue::summaryForAudit((int)$latest['id']) : [];
        $details = $latest ? (is_string($latest['details'] ?? null) ? json_decode($latest['details'], true) : ($latest['details'] ?? [])) : [];

        return $this->render('admin/crawler/seo', [
            'latest'      => $latest,
            'recentAudits'=> $recent,
            'trend'       => $trend,
            'issueSummary'=> $issues,
            'checkDetails'=> $details,
            'settings'    => [
                'seo_audit_enabled'  => Setting::get('seo_audit_enabled', 'true'),
                'seo_audit_schedule' => Setting::get('seo_audit_schedule', 'weekly'),
                'seo_last_run'       => Setting::get('seo_last_run', ''),
            ],
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    /** Run SEO audit now. */
    public function runAudit(): Response
    {
        try {
            $auditId = SeoAuditEngine::run();
            $audit   = SeoAudit::latest();
            Flash::set('success', "SEO audit complete! Score: {$audit['health_score']}/100 — {$audit['issues_found']} issues found.");
        } catch (\Throwable $e) {
            Flash::set('error', 'SEO audit failed: ' . $e->getMessage());
        }

        return $this->redirect('/admin/crawler/seo');
    }

    /** View detailed issues for a specific audit. */
    public function auditDetail(string $id): Response
    {
        $audit = SeoAudit::queryOne(
            "SELECT * FROM seo_audits WHERE id = :id",
            [':id' => (int)$id]
        );

        if (!$audit) {
            Flash::set('error', 'Audit not found.');
            return $this->redirect('/admin/crawler/seo');
        }

        $issues = SeoIssue::forAudit((int)$id);

        return $this->render('admin/crawler/seo_detail', [
            'audit'  => $audit,
            'issues' => $issues,
        ]);
    }

    /** Image health dashboard. */
    public function imageHealth(): Response
    {
        $stats  = ImageHealthLog::stats();
        $broken = ImageHealthLog::brokenImages(50);

        return $this->render('admin/crawler/image_health', [
            'stats'         => $stats,
            'brokenImages'  => $broken,
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    /** Run image health check. */
    public function runImageCheck(): Response
    {
        try {
            [$checked, $broken, $replaced] = ImageHealthChecker::run();
            Flash::set('success', "Checked {$checked} images: {$broken} broken, {$replaced} auto-replaced.");
        } catch (\Throwable $e) {
            Flash::set('error', 'Image check failed: ' . $e->getMessage());
        }

        return $this->redirect('/admin/crawler/seo');
    }

    /** Export SEO audit issues as CSV. */
    public function exportCsv(string $id): Response
    {
        $audit = SeoAudit::queryOne("SELECT * FROM seo_audits WHERE id = :id", [':id' => (int)$id]);
        if (!$audit) { Flash::set('error', 'Audit not found.'); return $this->redirect('/admin/crawler/seo'); }
        $issues = SeoIssue::forAudit((int)$id);
        $csv = "Severity,Check,Page URL,Description,Suggestion\n";
        foreach ($issues as $i) {
            $csv .= implode(',', [$i['severity'],'"'.str_replace('"','""',$i['check_name']).'"','"'.str_replace('"','""',$i['page_url']??'').'"','"'.str_replace('"','""',$i['description']).'"','"'.str_replace('"','""',$i['suggestion']??'').'"'])."\n";
        }
        $fn = 'seo-audit-' . ($audit['created_at'] ? date('Y-m-d', strtotime($audit['created_at'])) : 'export') . '.csv';
        return new Response($csv, 200, ['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$fn.'"']);
    }

    /** Export SEO audit report as PDF. */
    public function exportPdf(string $id): Response
    {
        $audit = SeoAudit::queryOne("SELECT * FROM seo_audits WHERE id = :id", [':id' => (int)$id]);
        if (!$audit) {
            Flash::set('error', 'Audit not found.');
            return $this->redirect('/admin/crawler/seo');
        }

        $issues = SeoIssue::forAudit((int)$id);
        $pdf    = SeoReportPdf::generate($audit, $issues);

        $date = $audit['created_at'] ? date('Y-m-d', strtotime($audit['created_at'])) : date('Y-m-d');
        $score = (int)($audit['health_score'] ?? 0);

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"seo-audit-{$date}-score-{$score}.pdf\"",
        ]);
    }

    /** Export image health report as CSV. */
    public function exportImagesCsv(): Response
    {
        $broken = ImageHealthLog::brokenImages(5000);
        $csv = "Article ID,Image URL,Status Code,Last Checked,Auto-Replaced\n";
        foreach ($broken as $r) {
            $csv .= implode(',', [$r['article_id']??'','"'.str_replace('"','""',$r['image_url']??'').'"',$r['status_code']??'',$r['checked_at']??'',!empty($r['replaced'])?'Yes':'No'])."\n";
        }
        return new Response($csv, 200, ['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="image-health-'.date('Y-m-d').'.csv"']);
    }

    /** Save SEO settings. */
    public function saveSettings(): Response
    {
        $req = Request::createFromGlobals();

        Setting::set('seo_audit_enabled', $req->request->get('seo_audit_enabled') ? 'true' : 'false', 'seo');
        Setting::set('seo_audit_schedule', (string)$req->request->get('seo_audit_schedule', 'weekly'), 'seo');
        Setting::set('image_health_enabled', $req->request->get('image_health_enabled') ? 'true' : 'false', 'seo');
        Setting::set('image_health_schedule', (string)$req->request->get('image_health_schedule', 'daily'), 'seo');

        $placeholder = trim((string)$req->request->get('image_placeholder_url', ''));
        if ($placeholder !== '') {
            Setting::set('image_placeholder_url', $placeholder, 'seo');
        }

        Flash::set('success', 'SEO settings saved.');
        return $this->redirect('/admin/crawler/seo');
    }
}