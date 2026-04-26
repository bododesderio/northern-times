<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PolicyPage;
use App\Services\Csrf;
use App\Services\Flash;
use App\Services\Slug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminPolicyController extends Controller
{
    public function index(): Response
    {
        $pages = PolicyPage::query(
            "SELECT id, title, slug, show_in_footer, sort_order, is_published, updated_at
             FROM policy_pages ORDER BY sort_order ASC, title ASC"
        );

        return $this->render('admin/policies/index', [
            'pages'         => $pages,
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    public function create(): Response
    {
        return $this->render('admin/policies/form', [
            'page' => null, 'csrf' => Csrf::token(), 'errors' => [], 'input' => [],
        ]);
    }

    public function store(): Response
    {
        $req = Request::createFromGlobals();
        [$errors, $data] = $this->validate($req);

        if ($errors) {
            return $this->render('admin/policies/form', [
                'page' => null, 'csrf' => Csrf::token(), 'errors' => $errors, 'input' => $data,
            ]);
        }

        $slug = Slug::make($data['slug'] ?: $data['title']);
        if (PolicyPage::findBySlug($slug)) {
            $slug = $slug . '-' . random_int(1000, 9999);
        }

        PolicyPage::create([
            'title'          => $data['title'],
            'slug'           => $slug,
            'content'        => $data['content'],
            'show_in_footer' => $data['show_in_footer'] ? 1 : 0,
            'sort_order'     => $data['sort_order'],
            'is_published'   => $data['is_published'] ? 1 : 0,
        ]);

        Flash::set('success', "Policy page '{$data['title']}' created.");
        return $this->redirect('/admin/policies');
    }

    public function edit(string $id): Response
    {
        $page = PolicyPage::find($id);
        if (!$page) {
            Flash::set('error', 'Policy page not found.');
            return $this->redirect('/admin/policies');
        }

        return $this->render('admin/policies/form', [
            'page' => $page, 'csrf' => Csrf::token(), 'errors' => [], 'input' => [],
        ]);
    }

    public function update(string $id): Response
    {
        $req  = Request::createFromGlobals();
        $page = PolicyPage::find($id);

        if (!$page) {
            Flash::set('error', 'Policy page not found.');
            return $this->redirect('/admin/policies');
        }

        [$errors, $data] = $this->validate($req);

        if ($errors) {
            return $this->render('admin/policies/form', [
                'page' => $page, 'csrf' => Csrf::token(), 'errors' => $errors, 'input' => $data,
            ]);
        }

        // Only update slug if user explicitly changed it
        $newSlug = trim($req->request->get('slug', ''));
        if ($newSlug === '' || $newSlug === $page['slug']) {
            $newSlug = $page['slug'];
        } else {
            $newSlug = Slug::make($newSlug);
            $dupe = PolicyPage::queryOne(
                "SELECT id FROM policy_pages WHERE slug = :slug AND id != :id",
                [':slug' => $newSlug, ':id' => $id]
            );
            if ($dupe) {
                $newSlug = $newSlug . '-' . random_int(1000, 9999);
            }
        }

        PolicyPage::update($id, [
            'title'          => $data['title'],
            'slug'           => $newSlug,
            'content'        => $data['content'],
            'show_in_footer' => $data['show_in_footer'] ? 1 : 0,
            'sort_order'     => $data['sort_order'],
            'is_published'   => $data['is_published'] ? 1 : 0,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        Flash::set('success', "Policy page updated.");
        return $this->redirect('/admin/policies');
    }

    public function bulk(): Response
    {
        $req    = Request::createFromGlobals();
        $action = $req->request->get('bulk_action', '');
        $ids    = $req->request->all('ids') ?: [];

        if (empty($ids) || !in_array($action, ['publish','unpublish','delete'])) {
            Flash::set('error', 'Invalid bulk action or no pages selected.');
            return $this->redirect('/admin/policies');
        }

        $count = 0;
        foreach ($ids as $id) {
            $id = trim((string)$id);
            if (!$id) continue;
            if ($action === 'publish') {
                PolicyPage::update($id, ['is_published' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            } elseif ($action === 'unpublish') {
                PolicyPage::update($id, ['is_published' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
            } elseif ($action === 'delete') {
                PolicyPage::delete($id);
            }
            $count++;
        }

        $label = ['publish' => 'published', 'unpublish' => 'unpublished', 'delete' => 'deleted'][$action];
        Flash::set('success', "{$count} page(s) {$label}.");
        return $this->redirect('/admin/policies');
    }

    public function delete(string $id): Response
    {
        PolicyPage::delete($id);
        Flash::set('success', 'Policy page deleted.');
        return $this->redirect('/admin/policies');
    }

    public function toggle(string $id): Response
    {
        PolicyPage::execute(
            "UPDATE policy_pages SET is_published = NOT is_published, updated_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
        Flash::set('success', 'Policy page status updated.');
        return $this->redirect('/admin/policies');
    }

    /** @return array{0: array<string,string>, 1: array<string,mixed>} */
    private function validate(Request $req): array
    {
        $data = [
            'title'          => trim($req->request->get('title', '')),
            'slug'           => trim($req->request->get('slug', '')),
            'content'        => $req->request->get('content', ''),
            'show_in_footer' => (bool) $req->request->get('show_in_footer', false),
            'sort_order'     => max(0, (int) $req->request->get('sort_order', 0)),
            'is_published'   => (bool) $req->request->get('is_published', false),
        ];

        $errors = [];
        if ($data['title'] === '') {
            $errors['title'] = 'Title is required.';
        }

        return [$errors, $data];
    }
}