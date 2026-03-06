<?php
declare(strict_types=1);

namespace App\Services;

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $pdo  = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (empty($user['is_active'])) {
            return false;
        }

        // FIX (Audit S-05): Regenerate the session ID on successful login.
        // This prevents session fixation attacks where an attacker pre-sets
        // a known session ID and waits for the victim to authenticate.
        // delete_old_session=true ensures the old session file is removed.
        session_regenerate_id(true);

        // FIX (Audit S-05): Rotate the CSRF token after login.
        // The old token is no longer valid once the session ID changes.
        // Any CSRF token embedded in the pre-login page is now invalid.
        unset($_SESSION['_csrf']);
        Csrf::token(); // generate a fresh token immediately

        // Store fuller user data in session for admin panel display
        // (display_name and avatar_url power admin_avatar_html())
        $_SESSION['user'] = [
            'id'           => $user['id'],
            'email'        => $user['email'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'] ?? $user['username'] ?? '',
            'avatar_url'   => $user['avatar_url']   ?? '',
            'role'         => $user['role'],
        ];

        // Track active session
        try {
            \App\Models\ActiveSession::touch(session_id(), $user['id']);
        } catch (\Throwable) {
            // Silently fail — session tracking must never block login
        }

        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function logout(): void
    {
        // Remove active session record before destroying data
        try {
            \App\Models\ActiveSession::forceLogout(session_id());
        } catch (\Throwable) {}

        // Clear all session data, rotate CSRF, regenerate ID
        $_SESSION = [];
        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /admin/login');
            exit;
        }
    }
}