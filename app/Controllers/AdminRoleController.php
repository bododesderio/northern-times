<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Role;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminRoleController extends Controller
{
    public function index(): Response
    {
        return $this->render('admin/roles/index', [
            'roles'         => Role::allWithUserCounts(),
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    public function create(): Response
    {
        return $this->render('admin/roles/form', [
            'mode' => 'create',
            'role' => [],
            'csrf' => Csrf::token(),
            'flash_error' => Flash::get('error'),
        ]);
    }

    public function store(): Response
    {
        $r = Request::createFromGlobals();

        $label = trim((string)$r->request->get('label', ''));
        $slug  = trim((string)$r->request->get('slug', ''));
        $desc  = trim((string)$r->request->get('description', ''));
        $color = trim((string)$r->request->get('color', '#666'));
        $sort  = (int)$r->request->get('sort_order', 10);
        $perms = $r->request->all('permissions') ?: [];
        if (!is_array($perms)) $perms = [];
        $permArr = array_filter(array_map('trim', $perms));

        if ($label === '' || $slug === '') {
            Flash::set('error', 'Label and slug are required.');
            return $this->redirect('/admin/roles/create');
        }

        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($slug));

        if (Role::findBySlug($slug)) {
            Flash::set('error', "Role slug '{$slug}' already exists.");
            return $this->redirect('/admin/roles/create');
        }

        Role::create([
            'slug'        => $slug,
            'label'       => $label,
            'description' => $desc,
            'permissions' => json_encode($permArr),
            'color'       => $color,
            'sort_order'  => $sort,
            'is_system'   => false,
        ]);

        Flash::set('success', "Role '{$label}' created.");
        return $this->redirect('/admin/roles');
    }

    public function edit(string $id): Response
    {
        $role = Role::find($id);
        if (!$role) {
            Flash::set('error', 'Role not found.');
            return $this->redirect('/admin/roles');
        }

        return $this->render('admin/roles/form', [
            'mode' => 'edit',
            'role' => $role,
            'csrf' => Csrf::token(),
            'flash_error' => Flash::get('error'),
        ]);
    }

    public function update(string $id): Response
    {
        $r = Request::createFromGlobals();

        $existing = Role::find($id);
        if (!$existing) {
            Flash::set('error', 'Role not found.');
            return $this->redirect('/admin/roles');
        }

        $label = trim((string)$r->request->get('label', ''));
        $desc  = trim((string)$r->request->get('description', ''));
        $color = trim((string)$r->request->get('color', '#666'));
        $sort  = (int)$r->request->get('sort_order', 10);
        $perms = $r->request->all('permissions') ?: [];
        if (!is_array($perms)) $perms = [];
        $permArr = array_filter(array_map('trim', $perms));

        $data = [
            'label'       => $label ?: $existing['label'],
            'description' => $desc,
            'color'       => $color,
            'sort_order'  => $sort,
        ];

        // For system roles: can edit label, desc, color — NOT permissions
        if (!$existing['is_system']) {
            $data['permissions'] = json_encode($permArr);
        }

        Role::update($id, $data);

        Flash::set('success', "Role '{$label}' updated.");
        return $this->redirect('/admin/roles');
    }

    /* ================================================================
       UPDATE COLOR — quick inline color patch from index page
       ================================================================ */
    public function updateColor(string $id): Response
    {
        $r     = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        $color = trim((string)$r->request->get('color', '#666666'));

        // Validate hex color
        if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $color)) {
            return new \Symfony\Component\HttpFoundation\Response(
                json_encode(['ok' => false, 'message' => 'Invalid color']), 422,
                ['Content-Type' => 'application/json']
            );
        }

        $role = Role::find($id);
        if (!$role) {
            return new \Symfony\Component\HttpFoundation\Response(
                json_encode(['ok' => false, 'message' => 'Role not found']), 404,
                ['Content-Type' => 'application/json']
            );
        }

        Role::update($id, ['color' => $color]);

        return new \Symfony\Component\HttpFoundation\Response(
            json_encode(['ok' => true, 'color' => $color]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    public function delete(string $id): Response
    {
        $role = Role::find($id);
        if (!$role) {
            Flash::set('error', 'Role not found.');
            return $this->redirect('/admin/roles');
        }

        if ($role['is_system']) {
            Flash::set('error', 'System roles cannot be deleted.');
            return $this->redirect('/admin/roles');
        }

        // Reassign users with this role to 'author'
        Role::execute("UPDATE users SET role = 'author' WHERE role = :slug", [':slug' => $role['slug']]);
        Role::delete($id);

        Flash::set('success', "Role '{$role['label']}' deleted. Users reassigned to Author.");
        return $this->redirect('/admin/roles');
    }
}