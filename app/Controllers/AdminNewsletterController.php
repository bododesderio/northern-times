<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Article;
use App\Models\NewsletterIssue;
use App\Models\Subscriber;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\Flash;
use App\Services\Mailer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Newsletter composer — select articles, preview, send, manage campaigns.
 */
final class AdminNewsletterController extends Controller
{
    /* ================================================================
       LIST — past newsletter issues
       ================================================================ */
    public function index(): Response
    {
        $request = Request::createFromGlobals();
        $page    = max(1, (int)$request->query->get('page', 1));
        $tab     = $request->query->get('tab', 'sent'); // sent | trash

        if ($tab === 'trash') {
            $result = NewsletterIssue::trash($page);
        } else {
            $result = NewsletterIssue::adminList($page);
        }

        $stats = NewsletterIssue::stats();
        $queueStats = Mailer::queueStats();
        $queueItems = Mailer::queueItems(50);

        return $this->render('admin/newsletter/index', [
            'issues'        => $result['rows'],
            'page'          => $result['page'],
            'totalPages'    => $result['totalPages'],
            'tab'           => $tab,
            'stats'         => $stats,
            'queueStats'    => $queueStats,
            'queueItems'    => $queueItems,
            'activeCount'   => Subscriber::activeCount(),
            'activeNav'     => 'newsletter',
            'pageTitle'     => 'Newsletter',
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
            'csrf'          => Csrf::token(),
        ]);
    }

    /* ================================================================
       COMPOSE — pick articles + write intro
       ================================================================ */
    public function compose(): Response
    {
        $articles = Article::query(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image, a.published_at,
                    COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') AS author,
                    c.name AS category
             FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.status = 'published'
             ORDER BY a.published_at DESC NULLS LAST
             LIMIT 50"
        );

        return $this->render('admin/newsletter/compose', [
            'articles'    => $articles,
            'activeCount' => Subscriber::activeCount(),
            'activeNav'   => 'newsletter',
            'pageTitle'   => 'Compose Newsletter',
            'csrf'        => Csrf::token(),
        ]);
    }

    /* ================================================================
       PREVIEW — show how the email will look
       ================================================================ */
    public function preview(): Response
    {
        $request = Request::createFromGlobals();

        if ($request->getMethod() === 'GET') {
            return $this->redirect('/admin/newsletter/compose');
        }

        $subject    = trim((string)$request->request->get('subject', ''));
        $intro      = trim((string)$request->request->get('intro', ''));
        $articleIds = (array)$request->request->all('article_ids');

        if ($subject === '' || empty($articleIds)) {
            Flash::set('error', 'Subject and at least one article are required.');
            return $this->redirect('/admin/newsletter/compose');
        }

        $articles = $this->fetchArticles($articleIds);
        $html     = Mailer::digestEmail($subject, $articles, 'PREVIEW_TOKEN', $intro !== '' ? $intro : null);

        return $this->render('admin/newsletter/preview', [
            'subject'      => $subject,
            'intro'        => $intro,
            'articleIds'   => $articleIds,
            'previewHtml'  => $html,
            'articleCount' => count($articles),
            'activeCount'  => Subscriber::activeCount(),
            'activeNav'    => 'newsletter',
            'pageTitle'    => 'Preview Newsletter',
            'csrf'         => Csrf::token(),
        ]);
    }

    /* ================================================================
       SEND — queue emails to all active subscribers
       ================================================================ */
    public function send(): Response
    {
        $request = Request::createFromGlobals();

        if ($request->getMethod() === 'GET') {
            return $this->redirect('/admin/newsletter/compose');
        }

        $subject    = trim((string)$request->request->get('subject', ''));
        $intro      = trim((string)$request->request->get('intro', ''));
        $articleIds = (array)$request->request->all('article_ids');

        if ($subject === '' || empty($articleIds)) {
            Flash::set('error', 'Subject and articles are required.');
            return $this->redirect('/admin/newsletter/compose');
        }

        $user        = Auth::user();
        $articles    = $this->fetchArticles($articleIds);
        $subscribers = Subscriber::activeSubscribers();

        if (empty($subscribers)) {
            Flash::set('error', 'No active subscribers to send to.');
            return $this->redirect('/admin/newsletter/compose');
        }

        // Create issue record
        $issue = NewsletterIssue::createIssue($subject, '', $articleIds, $user['id']);
        if ($issue) {
            NewsletterIssue::markSending($issue['id']);
        }

        // Queue emails for each subscriber
        $queued = 0;
        foreach ($subscribers as $sub) {
            $token = $sub['unsub_token'] ?? '';
            $html  = Mailer::digestEmail($subject, $articles, $token, $intro !== '' ? $intro : null);
            Mailer::queue($sub['email'], $subject, $html, null, $sub['name'] ?? null);
            $queued++;
        }

        // Update issue
        if ($issue) {
            NewsletterIssue::markSent($issue['id'], $queued, 0);
        }

        Flash::set('success', "Newsletter queued for {$queued} subscribers. Emails will be sent by the queue processor.");
        return $this->redirect('/admin/newsletter');
    }

    /* ================================================================
       SEND TEST — send to a single email for testing
       ================================================================ */
    public function sendTest(): Response
    {
        $request = Request::createFromGlobals();
        $email   = trim((string)$request->request->get('test_email', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('error', 'Please provide a valid email address.');
            return $this->redirect('/admin/newsletter');
        }

        $result = Mailer::sendTest($email);

        if ($result['success']) {
            Flash::set('success', "Test email sent to {$email} via {$result['driver']}.");
        } else {
            Flash::set('error', "Failed to send test email to {$email}. Check your MAIL_* settings in .env. Driver: {$result['driver']}, Host: {$result['host']}");
        }

        return $this->redirect('/admin/newsletter');
    }

    /* ================================================================
       DELETE — soft delete a newsletter issue
       ================================================================ */
    public function delete(string $id): Response
    {
        $issue = NewsletterIssue::find($id);
        if (!$issue) {
            Flash::set('error', 'Newsletter not found.');
            return $this->redirect('/admin/newsletter');
        }

        NewsletterIssue::softDelete($id);
        Flash::set('success', "Newsletter \"{$issue['subject']}\" moved to trash.");
        return $this->redirect('/admin/newsletter');
    }

    /* ================================================================
       RESTORE — restore a soft-deleted issue
       ================================================================ */
    public function restore(string $id): Response
    {
        NewsletterIssue::restore($id);
        Flash::set('success', 'Newsletter restored.');
        return $this->redirect('/admin/newsletter?tab=trash');
    }

    /* ================================================================
       PERMANENT DELETE — remove from trash permanently
       ================================================================ */
    public function permanentDelete(string $id): Response
    {
        $issue = NewsletterIssue::find($id);
        if (!$issue) {
            Flash::set('error', 'Newsletter not found.');
            return $this->redirect('/admin/newsletter?tab=trash');
        }

        NewsletterIssue::deleteIssue($id);
        Flash::set('success', "Newsletter permanently deleted.");
        return $this->redirect('/admin/newsletter?tab=trash');
    }

    /* ================================================================
       PROCESS QUEUE — manually trigger queue processing
       ================================================================ */
    public function processQueue(): Response
    {
        $result = Mailer::processQueue(50);
        Flash::set('success', "Queue processed: {$result['sent']} sent, {$result['failed']} failed.");
        return $this->redirect('/admin/newsletter');
    }

    /* ================================================================
       QUEUE ITEM ACTIONS — delete or retry individual email_queue rows
       ================================================================ */
    public function queueDelete(string $qid): Response
    {
        $pdo = \App\Models\BaseModel::pdo();
        $stmt = $pdo->prepare(
            "DELETE FROM email_queue WHERE id = :id AND status IN ('pending','failed')"
        );
        $stmt->execute([':id' => $qid]);

        if ($stmt->rowCount() > 0) {
            Flash::set('success', 'Queue item deleted.');
        } else {
            Flash::set('error', 'Item not found or already sent — cannot delete.');
        }
        return $this->redirect('/admin/newsletter');
    }

    public function queueRetry(string $qid): Response
    {
        $pdo = \App\Models\BaseModel::pdo();
        $stmt = $pdo->prepare(
            "UPDATE email_queue
             SET status = 'pending', attempts = 0, last_error = NULL, scheduled_at = NOW()
             WHERE id = :id AND status = 'failed'"
        );
        $stmt->execute([':id' => $qid]);

        if ($stmt->rowCount() > 0) {
            Flash::set('success', 'Item reset to pending — it will be sent on the next queue run.');
        } else {
            Flash::set('error', 'Item not found or not in failed state.');
        }
        return $this->redirect('/admin/newsletter');
    }

    /* ================================================================
       SCHEDULE — save and schedule for future send
       ================================================================ */
    public function schedule(): Response
    {
        $request    = Request::createFromGlobals();
        $subject    = trim((string)$request->request->get('subject', ''));
        $intro      = trim((string)$request->request->get('intro', ''));
        $articleIds = (array)$request->request->all('article_ids');
        $sendAt     = trim((string)$request->request->get('send_at', ''));

        if ($subject === '') {
            Flash::set('error', 'A subject is required.');
            return $this->redirect('/admin/newsletter/compose');
        }
        if ($sendAt === '') {
            Flash::set('error', 'Please pick a date and time to schedule the newsletter.');
            return $this->redirect('/admin/newsletter/compose');
        }
        if (empty($articleIds)) {
            Flash::set('error', 'Select at least one article.');
            return $this->redirect('/admin/newsletter/compose');
        }

        // Validate send_at is in the future
        $ts = strtotime($sendAt);
        if ($ts === false || $ts <= time()) {
            Flash::set('error', 'Scheduled time must be in the future.');
            return $this->redirect('/admin/newsletter/compose');
        }

        $user = Auth::user();
        // Convert local datetime-local value to UTC for storage
        $scheduledAt = date('Y-m-d H:i:sP', $ts);

        NewsletterIssue::scheduleIssue($subject, $articleIds, $user['id'] ?? '', $scheduledAt, $intro);

        $humanTime = date('M j, Y g:ia', $ts);
        Flash::set('success', "Newsletter scheduled for {$humanTime}. The cron job will send it automatically.");
        return $this->redirect('/admin/newsletter?tab=scheduled');
    }

    /* ================================================================
       SAVE DRAFT — save compose form without sending
       ================================================================ */
    public function saveDraft(): Response
    {
        $request    = Request::createFromGlobals();
        $subject    = trim((string)$request->request->get('subject', ''));
        $intro      = trim((string)$request->request->get('intro', ''));
        $articleIds = (array)$request->request->all('article_ids');
        $draftId    = trim((string)$request->request->get('draft_id', ''));

        if ($subject === '') {
            Flash::set('error', 'A subject is required to save a draft.');
            $back = $draftId ? "/admin/newsletter/compose?draft_id={$draftId}" : '/admin/newsletter/compose';
            return $this->redirect($back);
        }

        $user = Auth::user();

        if ($draftId !== '') {
            NewsletterIssue::updateDraft($draftId, $subject, $articleIds, $intro);
            Flash::set('success', 'Draft updated.');
            return $this->redirect("/admin/newsletter/compose?draft_id={$draftId}");
        }

        NewsletterIssue::saveDraft($subject, $articleIds, $user['id'] ?? '', $intro);
        Flash::set('success', 'Draft saved. You can continue editing it from the Drafts tab.');
        return $this->redirect('/admin/newsletter?tab=drafts');
    }

    /* ================================================================
       DELETE DRAFT — hard-delete a draft issue
       ================================================================ */
    public function deleteDraft(string $id): Response
    {
        $issue = NewsletterIssue::find($id);
        if (!$issue || $issue['status'] !== 'draft') {
            Flash::set('error', 'Draft not found.');
            return $this->redirect('/admin/newsletter?tab=drafts');
        }
        NewsletterIssue::deleteIssue($id);
        Flash::set('success', 'Draft deleted.');
        return $this->redirect('/admin/newsletter?tab=drafts');
    }

    /**
     * Fetch articles by IDs (preserving order).
     */
    private function fetchArticles(array $ids): array
    {
        if (empty($ids)) return [];

        $placeholders = [];
        $params       = [];
        foreach ($ids as $i => $id) {
            $key = ":id{$i}";
            $placeholders[] = $key;
            $params[$key]   = $id;
        }
        $in = implode(',', $placeholders);

        return Article::query(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                    COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') AS author
             FROM articles a
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.id IN ({$in})
             ORDER BY a.published_at DESC NULLS LAST",
            $params
        );
    }
}