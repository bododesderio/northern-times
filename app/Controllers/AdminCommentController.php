<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Comment;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class AdminCommentController extends Controller
{
    public function index(): Response
    {
        $request = Request::createFromGlobals();

        $filter = $request->query->get('filter', 'all');
        $search = trim((string)$request->query->get('q', ''));
        $page   = max(1, (int)$request->query->get('page', 1));
        $limit  = 25;

        // Build WHERE
        $where  = [];
        $params = [];

        if (in_array($filter, ['hidden', 'deleted', 'visible'], true)) {
            $where[] = "c.status = '{$filter}'";
        }
        if ($search !== '') {
            $where[] = "(c.author_name ILIKE :q OR c.author_email ILIKE :q OR c.content ILIKE :q OR a.title ILIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }

        $whereSql = !empty($where) ? implode(' AND ', $where) : '1=1';

        $result = Comment::paginate(
            page: $page,
            perPage: $limit,
            whereSql: $whereSql,
            params: $params,
            orderBy: 'c.created_at DESC',
            selectSql: 'c.*, a.title AS article_title, a.slug AS article_slug',
            fromSql: 'comments c JOIN articles a ON a.id = c.article_id'
        );

        $counts = Comment::statusCounts();
        $counts['all'] = array_sum($counts);

        return $this->render('admin/comments/index', [
            'comments' => $result['rows'], 'counts' => $counts, 'total' => $result['total'],
            'page' => $result['page'], 'pages' => $result['totalPages'], 'filter' => $filter, 'search' => $search,
            'csrf' => Csrf::token(), 'flash_success' => Flash::get('success'), 'flash_error' => Flash::get('error'),
        ]);
    }

    public function hide(string $id): Response
    {
        Comment::setStatus($id, 'hidden');
        Flash::set('success', 'Comment hidden from readers.');
        return new RedirectResponse($this->returnUrl(Request::createFromGlobals()));
    }

    public function show(string $id): Response
    {
        Comment::setStatus($id, 'visible');
        Flash::set('success', 'Comment restored.');
        return new RedirectResponse($this->returnUrl(Request::createFromGlobals()));
    }

    public function softDelete(string $id): Response
    {
        Comment::setStatus($id, 'deleted');
        Flash::set('success', 'Comment soft-deleted.');
        return new RedirectResponse($this->returnUrl(Request::createFromGlobals()));
    }

    public function destroy(string $id): Response
    {
        // Delete child comments first, then the comment itself
        Comment::execute("DELETE FROM comments WHERE parent_id = :id", [':id' => $id]);
        Comment::destroy($id);
        Flash::set('success', 'Comment permanently deleted.');
        return new RedirectResponse($this->returnUrl(Request::createFromGlobals()));
    }

    public function bulk(): Response
    {
        $r = Request::createFromGlobals();
        $action = $r->request->get('bulk_action', '');
        $ids = array_filter(
            $r->request->all('comment_ids') ?: [],
            fn($id) => preg_match('/^[0-9a-f-]{36}$/i', (string)$id)
        );

        if (empty($ids)) {
            Flash::set('error', 'No comments selected.');
            return new RedirectResponse('/admin/comments');
        }

        Comment::bulkAction($ids, $action);
        Flash::set('success', count($ids) . ' comment(s) updated.');
        return new RedirectResponse('/admin/comments');
    }

    private function returnUrl(Request $r): string
    {
        $f = $r->request->get('return_filter', '');
        return '/admin/comments' . ($f ? '?filter=' . urlencode($f) : '');
    }
}