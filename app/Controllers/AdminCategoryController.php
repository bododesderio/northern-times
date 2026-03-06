<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Services\Csrf;
use App\Services\Flash;
use App\Services\Slug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminCategoryController extends Controller
{
    public function index(): Response
    {
        $req = Request::createFromGlobals();
        $q   = trim((string)$req->query->get('q', ''));

        $where  = '1=1';
        $params = [];
        if ($q !== '') {
            $where        = "(name ILIKE :q OR slug ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        $categories = Category::query(
            "SELECT id, name, slug, description, sort_order, show_in_nav, show_in_sidebar, created_at, updated_at
             FROM categories WHERE {$where}
             ORDER BY show_in_nav DESC, sort_order ASC, name ASC LIMIT 500",
            $params
        );

        return $this->render('admin/categories/index', [
            'categories'    => $categories,
            'q'             => $q,
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    public function create(): Response
    {
        return $this->render('admin/categories/form', [
            'mode'     => 'create',
            'category' => [
                'name' => '', 'slug' => '', 'description' => '',
                'sort_order' => 50, 'show_in_nav' => 1, 'show_in_sidebar' => 1,
            ],
            'csrf'        => Csrf::token(),
            'flash_error' => Flash::get('error'),
        ]);
    }

    public function store(): Response
    {
        $req = Request::createFromGlobals();

        $name   = trim((string)$req->request->get('name', ''));
        $slugIn = trim((string)$req->request->get('slug', ''));
        $desc   = trim((string)$req->request->get('description', ''));
        $sort   = (int)$req->request->get('sort_order', 50);
        $show   = (int)$req->request->get('show_in_nav', 0) === 1 ? 1 : 0;
        $showSb = (int)$req->request->get('show_in_sidebar', 1) === 1 ? 1 : 0;

        if ($name === '') {
            Flash::set('error', 'Name is required.');
            return $this->redirect('/admin/categories/create');
        }

        $slug = $slugIn !== '' ? Slug::make($slugIn) : Slug::make($name);

        // Ensure unique slug
        if (Category::findBySlug($slug)) {
            $slug = $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        Category::create([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc !== '' ? $desc : null,
            'sort_order'  => $sort,
            'show_in_nav' => $show,
            'show_in_sidebar' => $showSb,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        Flash::set('success', 'Category created.');
        return $this->redirect('/admin/categories');
    }

    public function edit(string $id): Response
    {
        $category = Category::find($id);
        if (!$category) {
            return new Response("404 Not Found", 404);
        }

        return $this->render('admin/categories/form', [
            'mode'        => 'edit',
            'category'    => $category,
            'csrf'        => Csrf::token(),
            'flash_error' => Flash::get('error'),
        ]);
    }

    public function update(string $id): Response
    {
        $req = Request::createFromGlobals();

        $name   = trim((string)$req->request->get('name', ''));
        $slugIn = trim((string)$req->request->get('slug', ''));
        $desc   = trim((string)$req->request->get('description', ''));
        $sort   = (int)$req->request->get('sort_order', 50);
        $show   = (int)$req->request->get('show_in_nav', 0) === 1 ? 1 : 0;
        $showSb = (int)$req->request->get('show_in_sidebar', 1) === 1 ? 1 : 0;

        if ($name === '') {
            Flash::set('error', 'Name is required.');
            return $this->redirect('/admin/categories/' . $id . '/edit');
        }

        $existing = Category::find($id);
        if (!$existing) {
            return new Response("404 Not Found", 404);
        }

        $slug = $existing['slug'];
        if ($slugIn !== '') {
            $slug = Slug::make($slugIn);
        } elseif ($name !== (string)$existing['name']) {
            $slug = Slug::make($name);
        }

        // Unique slug excluding self
        $dupe = Category::queryOne(
            "SELECT 1 FROM categories WHERE slug = :s AND id <> :id LIMIT 1",
            [':s' => $slug, ':id' => $id]
        );
        if ($dupe) {
            $slug = $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        Category::update($id, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc !== '' ? $desc : null,
            'sort_order'  => $sort,
            'show_in_nav' => $show,
            'show_in_sidebar' => $showSb,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        Flash::set('success', 'Category updated.');
        return $this->redirect('/admin/categories');
    }

    public function delete(string $id): Response
    {
        // Block delete if used by articles
        $articleCount = (int)Category::queryColumn(
            "SELECT COUNT(*) FROM articles WHERE category_id = :id",
            [':id' => $id]
        );

        if ($articleCount > 0) {
            Flash::set('error', 'Cannot delete: category has articles. Move articles to another category first.');
            return $this->redirect('/admin/categories');
        }

        Category::delete($id);
        Flash::set('success', 'Category deleted.');
        return $this->redirect('/admin/categories');
    }
}