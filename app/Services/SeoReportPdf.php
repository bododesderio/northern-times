<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * SEO Audit PDF Report Generator
 *
 * Renders an HTML report of an SEO audit and converts it to PDF via dompdf.
 */
final class SeoReportPdf
{
    /**
     * Generate a PDF string from audit data.
     *
     * @param  array  $audit   Row from seo_audits table
     * @param  array  $issues  Rows from seo_issues for this audit
     * @return string          Raw PDF binary
     */
    public static function generate(array $audit, array $issues): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');

        $html = self::buildHtml($audit, $issues);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    private static function buildHtml(array $audit, array $issues): string
    {
        $siteName = function_exists('get_site_setting')
            ? \get_site_setting('site_title', 'Site')
            : 'Site';

        $score    = (int)($audit['health_score'] ?? 0);
        $pages    = (int)($audit['pages_scanned'] ?? 0);
        $critical = (int)($audit['critical_count'] ?? 0);
        $warnings = (int)($audit['warning_count'] ?? 0);
        $passed   = (int)($audit['passed_count'] ?? 0);
        $total    = (int)($audit['issues_found'] ?? count($issues));
        $date     = isset($audit['created_at'])
            ? date('F j, Y \a\t g:i A', strtotime($audit['created_at']))
            : date('F j, Y');
        $duration = isset($audit['duration_ms'])
            ? number_format((int)$audit['duration_ms'] / 1000, 1) . 's'
            : '';

        $scoreColor = $score >= 80 ? '#22c55e' : ($score >= 60 ? '#f59e0b' : '#dc3545');
        $scoreLabel = $score >= 80 ? 'Healthy' : ($score >= 60 ? 'Needs Attention' : 'Critical');

        // Build issue rows
        $issueRows = '';
        foreach ($issues as $issue) {
            $sevBg = match ($issue['severity'] ?? 'info') {
                'critical' => '#fef2f2', 'warning' => '#fffbeb', default => '#f0f9ff'
            };
            $sevColor = match ($issue['severity'] ?? 'info') {
                'critical' => '#dc2626', 'warning' => '#d97706', default => '#2563eb'
            };
            $sevLabel = match ($issue['severity'] ?? 'info') {
                'critical' => 'CRITICAL', 'warning' => 'WARNING', default => 'INFO'
            };

            $checkName   = htmlspecialchars(str_replace('_', ' ', $issue['check_name'] ?? ''));
            $description = htmlspecialchars($issue['description'] ?? '');
            $suggestion  = htmlspecialchars($issue['suggestion'] ?? '');
            $pageUrl     = htmlspecialchars(mb_substr($issue['page_url'] ?? '', 0, 60));

            $issueRows .= '<tr>'
                . '<td style="text-align:center;padding:8px 6px">'
                . '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:700;background:' . $sevBg . ';color:' . $sevColor . '">' . $sevLabel . '</span>'
                . '</td>'
                . '<td style="padding:8px 6px;font-weight:600">' . $checkName . '</td>'
                . '<td style="padding:8px 6px">' . $description . '</td>'
                . '<td style="padding:8px 6px;color:#666;font-style:italic;font-size:10px">' . $suggestion . '</td>'
                . '<td style="padding:8px 6px;font-size:9px;color:#888;word-break:break-all">' . $pageUrl . '</td>'
                . '</tr>';
        }

        if (empty($issueRows)) {
            $issueRows = '<tr><td colspan="5" style="padding:30px;text-align:center;color:#888">No issues found. Perfect score!</td></tr>';
        }

        $durationLine = $duration ? " | Duration: {$duration}" : '';

        return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.5; padding: 30px; }
    h1 { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
    h2 { font-size: 14px; font-weight: 700; margin: 20px 0 10px; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; }
    .subtitle { font-size: 11px; color: #888; margin-bottom: 20px; }
    .cards { width: 100%; margin-bottom: 20px; }
    .cards td { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; text-align: center; width: 20%; }
    .cards .num { font-size: 24px; font-weight: 900; }
    .cards .lbl { font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
    .issues { width: 100%; border-collapse: collapse; font-size: 10px; }
    .issues th { background: #f8f9fa; padding: 8px 6px; text-align: left; font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; color: #666; border-bottom: 2px solid #e5e7eb; }
    .issues td { padding: 8px 6px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
    .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #aaa; text-align: center; }
  </style>
</head>
<body>

  <h1>SEO Audit Report</h1>
  <div class="subtitle">' . htmlspecialchars($siteName) . ' | ' . $date . $durationLine . '</div>

  <table class="cards">
    <tr>
      <td>
        <div class="num" style="color:' . $scoreColor . '">' . $score . '</div>
        <div class="lbl">Health Score</div>
        <div style="font-size:10px;color:' . $scoreColor . ';font-weight:600;margin-top:2px">' . $scoreLabel . '</div>
      </td>
      <td>
        <div class="num">' . $pages . '</div>
        <div class="lbl">Pages Scanned</div>
      </td>
      <td>
        <div class="num" style="color:#dc2626">' . $critical . '</div>
        <div class="lbl">Critical</div>
      </td>
      <td>
        <div class="num" style="color:#d97706">' . $warnings . '</div>
        <div class="lbl">Warnings</div>
      </td>
      <td>
        <div class="num" style="color:#22c55e">' . $passed . '</div>
        <div class="lbl">Passed</div>
      </td>
    </tr>
  </table>

  <h2>Issues (' . $total . ')</h2>
  <table class="issues">
    <thead>
      <tr>
        <th style="text-align:center;width:70px">Severity</th>
        <th style="width:120px">Check</th>
        <th>Description</th>
        <th style="width:150px">Suggestion</th>
        <th style="width:110px">Page</th>
      </tr>
    </thead>
    <tbody>
      ' . $issueRows . '
    </tbody>
  </table>

  <div class="footer">
    Generated by ' . htmlspecialchars($siteName) . ' SEO Audit Engine | ' . $date . '
  </div>

</body>
</html>';
    }
}