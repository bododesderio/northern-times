<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\DB;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminUserController extends Controller
{
    private const PER_PAGE = 20;

    private function allowedRoles(): array
    {
        try {
            return array_column(Role::dropdown(), 'slug');
        } catch (\Throwable) {
            return ['super_admin','editor','author'];
        }
    }

    private function roleList(): array
    {
        try { return Role::all(); } catch (\Throwable) { return []; }
    }

    public function index(): Response
    {
        $request = Request::createFromGlobals();
        $q       = trim((string)$request->query->get('q', ''));
        $role    = trim((string)$request->query->get('role', ''));
        $page    = max(1, (int)$request->query->get('page', 1));

        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]      = "(username ILIKE :q OR email ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($role !== '' && in_array($role, $this->allowedRoles(), true)) {
            $where[]          = "role = :role";
            $params[':role']  = $role;
        }

        $whereSql = $where ? implode(' AND ', $where) : '1=1';

        $result = User::paginate(
            page: $page,
            perPage: self::PER_PAGE,
            whereSql: $whereSql,
            params: $params,
            orderBy: 'created_at DESC',
            selectSql: 'id, username, email, role, is_active, created_at, last_login, avatar_url'
        );

        return $this->render('admin/users/index', [
            'users'         => $result['rows'],
            'roles'         => $this->roleList(),
            'q'             => $q,
            'roleFilter'    => $role,
            'page'          => $result['page'],
            'totalPages'    => $result['totalPages'],
            'total'         => $result['total'],
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
            'csrf'          => Csrf::token(),
        ]);
    }

    public function create(): Response
    {
        return $this->render('admin/users/form', [
            'mode'        => 'create',
            'user'        => [],
            'roles'       => $this->roleList(),
            'csrf'        => Csrf::token(),
            'flash_error' => Flash::get('error'),
        ]);
    }

    public function store(): Response
    {
        $request = Request::createFromGlobals();

        $username = trim((string)$request->request->get('username', ''));
        $email    = trim((string)$request->request->get('email', ''));
        $password = (string)$request->request->get('password', '');
        $role     = (string)$request->request->get('role', 'author');

        if ($username === '' || $email === '' || $password === '') {
            Flash::set('error', 'Username, email, and password are all required.');
            return $this->redirect('/admin/users/create');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('error', 'Please enter a valid email address.');
            return $this->redirect('/admin/users/create');
        }
        if (strlen($password) < 8) {
            Flash::set('error', 'Password must be at least 8 characters.');
            return $this->redirect('/admin/users/create');
        }
        if (!in_array($role, $this->allowedRoles(), true)) $role = 'author';

        // Check uniqueness
        $dupe = User::queryOne(
            "SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1",
            [':email' => $email, ':username' => $username]
        );
        if ($dupe) {
            Flash::set('error', 'A user with that email or username already exists.');
            return $this->redirect('/admin/users/create');
        }

        User::createUser([
            'username' => $username,
            'email'    => $email,
            'password' => $password,
            'role'     => $role,
        ]);

        Flash::set('success', "User '{$username}' created successfully.");
        return $this->redirect('/admin/users');
    }

    public function edit(string $id): Response
    {
        $user = User::find($id);
        if (!$user) {
            Flash::set('error', 'User not found.');
            return $this->redirect('/admin/users');
        }

        return $this->render('admin/users/form', [
            'mode'        => 'edit',
            'user'        => $user,
            'roles'       => $this->roleList(),
            'csrf'        => Csrf::token(),
            'flash_error' => Flash::get('error'),
        ]);
    }

    public function update(string $id): Response
    {
        $request  = Request::createFromGlobals();
        $existing = User::find($id);

        if (!$existing) {
            Flash::set('error', 'User not found.');
            return $this->redirect('/admin/users');
        }

        $username  = trim((string)$request->request->get('username', $existing['username']));
        $email     = trim((string)$request->request->get('email', $existing['email']));
        $role      = (string)$request->request->get('role', $existing['role']);
        $isActive  = (bool)(int)$request->request->get('is_active', 1);
        $bio       = trim((string)$request->request->get('bio', ''));
        $twitter   = trim(ltrim((string)$request->request->get('twitter_handle', ''), '@'));
        $password  = (string)$request->request->get('password', '');

        $me = Auth::user();
        if ($id === $me['id'] && $role !== 'super_admin') {
            Flash::set('error', 'You cannot change your own role.');
            return $this->redirect('/admin/users/' . $id . '/edit');
        }

        if (!in_array($role, $this->allowedRoles(), true)) $role = 'author';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('error', 'Please enter a valid email address.');
            return $this->redirect('/admin/users/' . $id . '/edit');
        }

        $dupe = User::queryOne(
            "SELECT id FROM users WHERE (email = :email OR username = :username) AND id != :id LIMIT 1",
            [':email' => $email, ':username' => $username, ':id' => $id]
        );
        if ($dupe) {
            Flash::set('error', 'Another user already has that email or username.');
            return $this->redirect('/admin/users/' . $id . '/edit');
        }

        $data = [
            'username'       => $username,
            'email'          => $email,
            'role'           => $role,
            'is_active'      => $isActive,
            'bio'            => $bio !== '' ? $bio : null,
            'twitter_handle' => $twitter !== '' ? $twitter : null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        if ($password !== '') {
            if (strlen($password) < 8) {
                Flash::set('error', 'New password must be at least 8 characters.');
                return $this->redirect('/admin/users/' . $id . '/edit');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        User::update($id, $data);

        Flash::set('success', "User '{$username}' updated.");
        return $this->redirect('/admin/users');
    }

    public function delete(string $id): Response
    {
        $me = Auth::user();
        if ($id === $me['id']) {
            Flash::set('error', 'You cannot delete your own account.');
            return $this->redirect('/admin/users');
        }

        $user = User::find($id);
        if (!$user) {
            Flash::set('error', 'User not found.');
            return $this->redirect('/admin/users');
        }

        $pdo = DB::pdo();

        // Reassign articles to current admin
        try {
            $pdo->prepare("UPDATE articles SET author_id = :admin WHERE author_id = :uid")
                ->execute([':admin' => $me['id'], ':uid' => $id]);
        } catch (\Throwable) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable) {}
            $pdo = DB::reconnect();
        }

        // Reassign media to current admin
        try {
            $pdo->prepare("UPDATE media_library SET uploaded_by = :admin WHERE uploaded_by = :uid")
                ->execute([':admin' => $me['id'], ':uid' => $id]);
        } catch (\Throwable) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable) {}
            $pdo = DB::reconnect();
        }

        // Handle other FK references dynamically
        try {
            $fks = $pdo->query("
                SELECT tc.table_name, kcu.column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                  ON kcu.constraint_name = tc.constraint_name AND kcu.table_schema = tc.table_schema
                JOIN information_schema.constraint_column_usage ccu
                  ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND ccu.table_name = 'users' AND ccu.column_name = 'id'
                  AND tc.table_name NOT IN ('users','articles','media_library')
            ")->fetchAll() ?: [];

            foreach ($fks as $fk) {
                $tbl = preg_replace('/[^a-z0-9_]/', '', $fk['table_name']);
                $col = preg_replace('/[^a-z0-9_]/', '', $fk['column_name']);
                try {
                    $stmt = $pdo->prepare("DELETE FROM {$tbl} WHERE {$col} = :uid");
                    $stmt->execute([':uid' => $id]);
                } catch (\Throwable) {
                    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable) {}
                    $pdo = DB::reconnect();
                    try {
                        $stmt = $pdo->prepare("DELETE FROM {$tbl} WHERE {$col} = :uid");
                        $stmt->execute([':uid' => $id]);
                    } catch (\Throwable) {}
                }
            }
        } catch (\Throwable) {
            $pdo = DB::reconnect();
        }

        // Delete the user
        try {
            $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
        } catch (\Throwable $e) {
            try {
                $pdo = DB::reconnect();
                $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
            } catch (\Throwable $e2) {
                Flash::set('error', 'Could not delete user: ' . $e2->getMessage());
                return $this->redirect('/admin/users');
            }
        }

        Flash::set('success', "User '{$user['username']}' deleted. Their content was reassigned to you.");
        return $this->redirect('/admin/users');
    }

    public function toggle(string $id): Response
    {
        $me = Auth::user();
        if ($id === $me['id']) {
            Flash::set('error', 'You cannot deactivate your own account.');
            return $this->redirect('/admin/users');
        }

        $user = User::find($id);
        if (!$user) {
            Flash::set('error', 'User not found.');
            return $this->redirect('/admin/users');
        }

        User::toggleActive($id);

        $label = $user['is_active'] ? 'deactivated' : 'activated';
        Flash::set('success', "User '{$user['username']}' {$label}.");
        return $this->redirect('/admin/users');
    }
}