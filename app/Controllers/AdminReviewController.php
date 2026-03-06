<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Article;
use App\Models\Notification;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editorial review queue — list pending articles, approve/reject.
 */
final class AdminReviewController extends Controller
{
    /* ================================================================
       REVIEW QUEUE — list articles pending review
       ================================================================ */
    public function index(): Response
    {
        $request = Request::createFromGlobals();
        $page    = max(1, (int)$request->query->get('page', 1));

        $result = Article::pendingReview($page, 20);

        return $this->render('admin/review/index', [
            'articles'      => $result['rows'],
            'page'          => $result['page'],
            'totalPages'    => $result['totalPages'],
            'activeNav'     => 'review',
            'pageTitle'     => 'Review Queue',
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
            'csrf'          => Csrf::token(),
        ]);
    }

    /* ================================================================
       REVIEW DETAIL — view single article + approve/reject form
       ================================================================ */
    public function show(string $id): Response
    {
        $article = Article::find($id);
        if (!$article) return new Response('404 Not Found', 404);

        return $this->render('admin/review/show', [
            'article'   => $article,
            'activeNav' => 'review',
            'pageTitle' => 'Review: ' . ($article['title'] ?? 'Article'),
            'csrf'      => Csrf::token(),
        ]);
    }

    /* ================================================================
       APPROVE — publish the article
       ================================================================ */
    public function approve(string $id): Response
    {
        $article = Article::find($id);
        if (!$article) return new Response('404 Not Found', 404);

        $request  = Request::createFromGlobals();
        $reviewer = Auth::user();
        $notes    = trim((string)$request->request->get('notes', ''));

        Article::approveArticle($id, $reviewer['id'], $notes !== '' ? $notes : null);

        // Notify the author
        if (!empty($article['author_id'])) {
            Notification::send(
                $article['author_id'],
                Notification::TYPE_ARTICLE_APPROVED,
                'Article approved: ' . $article['title'],
                $notes !== '' ? "Reviewer note: {$notes}" : 'Your article has been published.',
                '/article/' . $article['slug'],
                ['article_id' => $id, 'reviewer_id' => $reviewer['id']]
            );
        }

        Flash::set('success', "Approved and published: \"{$article['title']}\"");
        return $this->redirect('/admin/review');
    }

    /* ================================================================
       REJECT — send back to draft with notes
       ================================================================ */
    public function reject(string $id): Response
    {
        $article = Article::find($id);
        if (!$article) return new Response('404 Not Found', 404);

        $request  = Request::createFromGlobals();
        $reviewer = Auth::user();
        $notes    = trim((string)$request->request->get('notes', ''));

        if ($notes === '') {
            Flash::set('error', 'Please provide feedback when rejecting an article.');
            return $this->redirect("/admin/review/{$id}");
        }

        Article::rejectArticle($id, $reviewer['id'], $notes);

        // Notify the author
        if (!empty($article['author_id'])) {
            Notification::send(
                $article['author_id'],
                Notification::TYPE_ARTICLE_REJECTED,
                'Revision requested: ' . $article['title'],
                "Reviewer feedback: {$notes}",
                "/admin/articles/{$id}/edit",
                ['article_id' => $id, 'reviewer_id' => $reviewer['id']]
            );
        }

        Flash::set('success', "Sent back for revision: \"{$article['title']}\"");
        return $this->redirect('/admin/review');
    }

    /* ================================================================
       NOTIFICATIONS — list for current user
       ================================================================ */
    public function notifications(): Response
    {
        $user  = Auth::user();
        $notifs = Notification::forUser($user['id'], 50);

        return $this->render('admin/review/notifications', [
            'notifications' => $notifs,
            'activeNav'     => 'notifications',
            'pageTitle'     => 'Notifications',
            'csrf'          => Csrf::token(),
        ]);
    }

    /**
     * Mark all notifications as read (AJAX or POST).
     */
    public function markAllRead(): Response
    {
        $user = Auth::user();
        Notification::markAllRead($user['id']);
        Flash::set('success', 'All notifications marked as read.');
        return $this->redirect('/admin/notifications');
    }

    /**
     * Mark single notification read + redirect to link.
     */
    public function markRead(string $id): Response
    {
        $user = Auth::user();
        Notification::markRead($id, $user['id']);

        $notif = Notification::find($id);
        $link = $notif['link'] ?? '/admin';
        return $this->redirect($link);
    }
}