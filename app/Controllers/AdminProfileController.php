<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminProfileController extends Controller
{
    public function index(): Response
    {
        $me   = Auth::user();
        $user = User::find($me['id']);

        if (!$user) {
            Auth::logout();
            return $this->redirect('/admin/login');
        }

        return $this->render('admin/profile', [
            'user'          => $user,
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    public function update(): Response
    {
        $request = Request::createFromGlobals();
        $me      = Auth::user();
        $existing = User::find($me['id']);

        if (!$existing) {
            Auth::logout();
            return $this->redirect('/admin/login');
        }

        $username  = trim((string)$request->request->get('username', $existing['username']));
        $email     = trim((string)$request->request->get('email', $existing['email']));
        $bio       = trim((string)$request->request->get('bio', ''));
        $twitter   = trim(ltrim((string)$request->request->get('twitter_handle', ''), '@'));
        $avatarUrl = trim((string)$request->request->get('avatar_url', ''));
        $facebook  = trim((string)$request->request->get('facebook_url', ''));
        $linkedin  = trim((string)$request->request->get('linkedin_url', ''));
        $instagram = trim(ltrim((string)$request->request->get('instagram_handle', ''), '@'));
        $whatsapp  = trim(ltrim((string)$request->request->get('whatsapp_number', ''), '+'));
        $website   = trim((string)$request->request->get('website_url', ''));

        if ($username === '') {
            Flash::set('error', 'Username cannot be empty.');
            return $this->redirect('/admin/profile');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('error', 'Please enter a valid email address.');
            return $this->redirect('/admin/profile');
        }

        // Check uniqueness (exclude self)
        $dupe = User::queryOne(
            "SELECT id FROM users WHERE (email = :email OR username = :username) AND id != :id LIMIT 1",
            [':email' => $email, ':username' => $username, ':id' => $me['id']]
        );
        if ($dupe) {
            Flash::set('error', 'That username or email is already taken by another account.');
            return $this->redirect('/admin/profile');
        }

        // Handle password change
        $currentPw  = (string)$request->request->get('current_password', '');
        $newPw      = (string)$request->request->get('new_password', '');
        $confirmPw  = (string)$request->request->get('confirm_password', '');
        $hashUpdate = '';
        $hashParam  = [];

        if ($newPw !== '') {
            if (!password_verify($currentPw, $existing['password_hash'])) {
                Flash::set('error', 'Current password is incorrect.');
                return $this->redirect('/admin/profile');
            }
            if (strlen($newPw) < 8) {
                Flash::set('error', 'New password must be at least 8 characters.');
                return $this->redirect('/admin/profile');
            }
            if ($newPw !== $confirmPw) {
                Flash::set('error', 'New passwords do not match.');
                return $this->redirect('/admin/profile');
            }
            $hashUpdate        = ', password_hash = :hash';
            $hashParam[':hash'] = password_hash($newPw, PASSWORD_BCRYPT);
        }

        $params = array_merge([
            ':id'        => $me['id'],
            ':username'  => $username,
            ':email'     => $email,
            ':bio'       => $bio !== '' ? $bio : null,
            ':twitter'   => $twitter !== '' ? $twitter : null,
            ':avatar'    => $avatarUrl !== '' ? $avatarUrl : null,
            ':facebook'  => $facebook !== '' ? $facebook : null,
            ':linkedin'  => $linkedin !== '' ? $linkedin : null,
            ':instagram' => $instagram !== '' ? $instagram : null,
            ':whatsapp'  => $whatsapp !== '' ? $whatsapp : null,
            ':website'   => $website !== '' ? $website : null,
        ], $hashParam);

        User::execute("
            UPDATE users
            SET username         = :username,
                email            = :email,
                bio              = :bio,
                twitter_handle   = :twitter,
                avatar_url       = :avatar,
                facebook_url     = :facebook,
                linkedin_url     = :linkedin,
                instagram_handle = :instagram,
                whatsapp_number  = :whatsapp,
                website_url      = :website,
                updated_at       = NOW()
                {$hashUpdate}
            WHERE id = :id
        ", $params);

        // Refresh session so sidebar reflects changes immediately
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['username']   = $username;
            $_SESSION['user']['email']      = $email;
            $_SESSION['user']['avatar_url'] = $avatarUrl !== '' ? $avatarUrl : null;
        }

        Flash::set('success', 'Profile updated successfully.');
        return $this->redirect('/admin/profile');
    }
}