<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\CrawlSource;
use App\Models\Setting;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\DB;
use App\Services\Flash;
use App\Services\RewriterClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminRewriterController extends Controller
{
    /** Queue dashboard — stats + recent jobs. */
    public function index(): Response
    {
        $pdo = DB::pdo();

        try {
            $counts = $pdo->query(
                "SELECT rewrite_status, COUNT(*) AS n
                   FROM articles
                  WHERE rewrite_status != 'skipped'
                  GROUP BY rewrite_status"
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Throwable) {
            $counts = [];
        }

        try {
            $recent = $pdo->query(
                "SELECT id, title, rewrite_status, rewritten_at, rewriter_model,
                        rewritten_content IS NOT NULL AS has_rewrite,
                        created_at
                   FROM articles
                  WHERE rewrite_status != 'skipped'
                  ORDER BY COALESCE(rewritten_at, created_at) DESC
                  LIMIT 50"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $recent = [];
        }

        return $this->render('admin/rewriter/index', [
            'counts'          => $counts,
            'recent'          => $recent,
            'rewriterEnabled' => ($_ENV['REWRITER_ENABLED'] ?? 'false') === 'true',
            'csrf'            => Csrf::token(),
            'flash_success'   => Flash::get('success'),
            'flash_error'     => Flash::get('error'),
        ]);
    }

    /** Queue a single article for rewriting. */
    public function queueArticle(): Response
    {
        $req = Request::createFromGlobals();
        $id  = $req->attributes->get('id', '');

        DB::pdo()->prepare(
            "UPDATE articles SET rewrite_status = 'queued', updated_at = NOW()
              WHERE id = :id AND rewrite_status NOT IN ('queued','processing')"
        )->execute([':id' => $id]);

        Flash::set('success', 'Article queued for rewriting.');
        return $this->redirect('/admin/rewriter');
    }

    /** Retry a failed article. */
    public function retry(): Response
    {
        $req = Request::createFromGlobals();
        $id  = $req->attributes->get('id', '');

        DB::pdo()->prepare(
            "UPDATE articles SET rewrite_status = 'queued', updated_at = NOW()
              WHERE id = :id AND rewrite_status IN ('failed','rejected')"
        )->execute([':id' => $id]);

        Flash::set('success', 'Article re-queued for rewriting.');
        return $this->redirect('/admin/rewriter');
    }

    /** Side-by-side review of original vs rewritten content. */
    public function review(): Response
    {
        $req = Request::createFromGlobals();
        $id  = $req->attributes->get('id', '');

        $pdo     = DB::pdo();
        $stmt    = $pdo->prepare(
            "SELECT id, title, content, excerpt,
                    rewritten_title, rewritten_content, rewritten_excerpt,
                    rewrite_status, rewritten_at, rewriter_model
               FROM articles WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $article = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$article || ($article['rewrite_status'] ?? '') !== 'pending_approval') {
            Flash::set('error', 'Article not found or not pending approval.');
            return $this->redirect('/admin/rewriter');
        }

        return $this->render('admin/rewriter/review', [
            'article'       => $article,
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    /** Approve a rewrite — apply rewritten content to main columns. */
    public function approve(): Response
    {
        $req = Request::createFromGlobals();
        $id  = $req->attributes->get('id', '');
        $pdo = DB::pdo();

        $stmt = $pdo->prepare(
            "SELECT id, title, content, excerpt,
                    rewritten_title, rewritten_content, rewritten_excerpt
               FROM articles WHERE id = :id AND rewrite_status = 'pending_approval'"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['rewritten_content'])) {
            Flash::set('error', 'No pending rewrite found.');
            return $this->redirect('/admin/rewriter');
        }

        // Snapshot current content as revision before overwriting
        try {
            $userId = Auth::user()['id'] ?? null;
            ArticleRevision::createRevision($id, $userId, $row['title'], $row['content'], $row['excerpt'] ?? '');
        } catch (\Throwable) {}

        $pdo->prepare(
            "UPDATE articles SET
                title          = rewritten_title,
                content        = rewritten_content,
                excerpt        = COALESCE(rewritten_excerpt, excerpt),
                rewrite_status = 'approved',
                updated_at     = NOW()
             WHERE id = :id"
        )->execute([':id' => $id]);

        Flash::set('success', 'Rewrite approved and applied.');
        return $this->redirect('/admin/rewriter');
    }

    /** Reject a rewrite — discard rewritten content. */
    public function reject(): Response
    {
        $req = Request::createFromGlobals();
        $id  = $req->attributes->get('id', '');

        DB::pdo()->prepare(
            "UPDATE articles SET
                rewritten_title   = NULL,
                rewritten_content = NULL,
                rewritten_excerpt = NULL,
                rewrite_status    = 'rejected',
                updated_at        = NOW()
             WHERE id = :id AND rewrite_status = 'pending_approval'"
        )->execute([':id' => $id]);

        Flash::set('success', 'Rewrite rejected.');
        return $this->redirect('/admin/rewriter');
    }

    /** Revert an approved rewrite — restore from revision history. */
    public function revert(): Response
    {
        $req = Request::createFromGlobals();
        $id  = $req->attributes->get('id', '');
        $pdo = DB::pdo();

        // Get the most recent revision (the pre-rewrite snapshot)
        $rev = $pdo->prepare(
            "SELECT title, content, excerpt FROM article_revisions
              WHERE article_id = :id ORDER BY revision_number DESC LIMIT 1"
        );
        $rev->execute([':id' => $id]);
        $orig = $rev->fetch(\PDO::FETCH_ASSOC);

        if (!$orig) {
            Flash::set('error', 'No revision history found for this article.');
            return $this->redirect('/admin/rewriter');
        }

        $pdo->prepare(
            "UPDATE articles SET
                title             = :title,
                content           = :content,
                excerpt           = :excerpt,
                rewritten_title   = NULL,
                rewritten_content = NULL,
                rewritten_excerpt = NULL,
                rewrite_status    = 'skipped',
                rewritten_at      = NULL,
                rewriter_model    = NULL,
                updated_at        = NOW()
             WHERE id = :id"
        )->execute([
            ':title'   => $orig['title'],
            ':content' => $orig['content'],
            ':excerpt' => $orig['excerpt'] ?? '',
            ':id'      => $id,
        ]);

        Flash::set('success', 'Article reverted to original content.');
        return $this->redirect('/admin/rewriter');
    }

    /** Bulk-queue crawled articles by source or date. */
    public function queueBulk(): Response
    {
        $req    = Request::createFromGlobals();
        $source = $req->request->get('source_id', '0');
        $since  = $req->request->get('since', '');
        $limit  = min((int)$req->request->get('limit', 100), 500);

        $pdo   = DB::pdo();
        $where = ["rewrite_status IN ('skipped','rejected')", 'is_crawled = TRUE'];
        $params = [];

        if ($source && $source !== '0') {
            $where[]              = 'crawl_source_id = :source_id';
            $params[':source_id'] = $source;
        }
        if ($since !== '' && strtotime($since)) {
            $where[]          = 'created_at >= :since';
            $params[':since'] = date('Y-m-d H:i:s', strtotime($since));
        }

        $sql = "UPDATE articles SET rewrite_status = 'queued', updated_at = NOW()"
             . " WHERE id IN (SELECT id FROM articles WHERE " . implode(' AND ', $where)
             . " ORDER BY created_at DESC LIMIT :lim)";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            if ($k !== ':lim') $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $queued = $stmt->rowCount();

        Flash::set('success', "Queued {$queued} article(s) for rewriting.");
        return $this->redirect('/admin/rewriter');
    }

    /** Rewriter settings page. */
    public function settings(): Response
    {
        $sources = [];
        try {
            $sources = DB::pdo()->query(
                "SELECT id, name, auto_rewrite FROM crawl_sources ORDER BY name ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        return $this->render('admin/rewriter/settings', [
            'sources'      => $sources,
            'model'        => $_ENV['OLLAMA_MODEL']            ?? 'llama3:8b',
            'tolerance'    => $_ENV['REWRITER_WORD_TOLERANCE'] ?? '50',
            'batchSize'    => $_ENV['REWRITER_BATCH_SIZE']     ?? '3',
            'autoApply'    => Setting::get('rewriter_auto_apply', 'false'),
            'rules'        => Setting::get('rewriter_rules', ''),
            'maxWords'     => Setting::get('rewriter_max_words', '4000'),
            'healthy'      => RewriterClient::isHealthy(),
            'csrf'         => Csrf::token(),
            'flash_success'=> Flash::get('success'),
            'flash_error'  => Flash::get('error'),
        ]);
    }

    /** Save per-source auto_rewrite toggles. */
    public function saveSettings(): Response
    {
        $req     = Request::createFromGlobals();
        $enabled = $req->request->all('auto_rewrite') ?: [];
        $pdo     = DB::pdo();

        $pdo->exec("UPDATE crawl_sources SET auto_rewrite = FALSE");
        if (!empty($enabled)) {
            $ids = array_map('intval', $enabled);
            $ids = array_filter($ids, fn($id) => $id > 0);
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("UPDATE crawl_sources SET auto_rewrite = TRUE WHERE id IN ({$placeholders})");
                $stmt->execute(array_values($ids));
            }
        }

        Flash::set('success', 'Auto-rewrite source settings saved.');
        return $this->redirect('/admin/rewriter/settings');
    }

    /** Save rewriter rules, auto-apply toggle, max words. */
    public function saveRules(): Response
    {
        $req = Request::createFromGlobals();

        Setting::set('rewriter_auto_apply', $req->request->get('auto_apply') === '1' ? 'true' : 'false');
        Setting::set('rewriter_rules',      $req->request->get('rules', ''));
        Setting::set('rewriter_max_words',   (string)max(0, (int)$req->request->get('max_words', '4000')));

        Flash::set('success', 'Rewriter rules and settings saved.');
        return $this->redirect('/admin/rewriter/settings');
    }

    /** JSON status API for dashboard live-polling. */
    public function statusApi(): Response
    {
        $pdo = DB::pdo();

        try {
            $counts = $pdo->query(
                "SELECT rewrite_status, COUNT(*) AS n
                   FROM articles
                  WHERE rewrite_status != 'skipped'
                  GROUP BY rewrite_status"
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Throwable) {
            $counts = [];
        }

        return new Response(
            json_encode([
                'queued'           => (int)($counts['queued']           ?? 0),
                'processing'       => (int)($counts['processing']       ?? 0),
                'pending_approval' => (int)($counts['pending_approval'] ?? 0),
                'approved'         => (int)($counts['approved']         ?? 0),
                'rejected'         => (int)($counts['rejected']         ?? 0),
                'failed'           => (int)($counts['failed']           ?? 0),
                'healthy'          => RewriterClient::isHealthy(),
            ]),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
