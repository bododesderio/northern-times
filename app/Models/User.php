<?php
declare(strict_types=1);

namespace App\Models;

/**
 * User model — users table.
 */
final class User extends BaseModel
{
    protected static string $table   = 'users';
    protected static string $orderBy = 'created_at DESC';

    /**
     * Find user by email (for auth).
     */
    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', $email);
    }

    /**
     * Find user by username.
     */
    public static function findByUsername(string $username): ?array
    {
        return self::findBy('username', $username);
    }

    /**
     * Create a new user with hashed password.
     */
    public static function createUser(array $data): ?array
    {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        return self::create([
            'username'      => $data['username'],
            'email'         => $data['email'],
            'password_hash' => $hash,
            'role'          => $data['role'] ?? 'author',
            'is_active'     => $data['is_active'] ?? true,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update password for a user.
     */
    public static function updatePassword(string $id, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        self::update($id, [
            'password_hash' => $hash,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Toggle active status.
     */
    public static function toggleActive(string $id): void
    {
        self::execute(
            "UPDATE users SET is_active = NOT is_active, updated_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Paginated user listing for admin.
     */
    public static function adminList(int $page = 1, int $perPage = 20, string $q = ''): array
    {
        $where  = '1=1';
        $params = [];

        if ($q !== '') {
            $where        = "(username ILIKE :q OR email ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        return self::paginate(
            page: $page,
            perPage: $perPage,
            whereSql: $where,
            params: $params,
            orderBy: 'created_at DESC'
        );
    }

    /**
     * Active user count (dashboard).
     */
    public static function activeCount(): int
    {
        return self::count(['is_active' => true]);
    }

    /**
     * Find a single user by primary key.
     * Returns all profile columns needed for the article author card.
     */
    public static function findById(string $id): ?array
    {
        return self::queryOne(
            "SELECT id, username, display_name, role, bio, avatar_url,
                    twitter_handle, facebook_url, linkedin_url,
                    instagram_handle, whatsapp_number, website_url
               FROM users
              WHERE id = :id
              LIMIT 1",
            [':id' => $id]
        );
    }

    /**
     * Fetch the super_admin user — used as the author for all crawled articles
     * so the profile always reflects the live DB record regardless of white-label config.
     * Falls back to the oldest admin if no super_admin row exists.
     */
    public static function getSuperAdmin(): ?array
    {
        return self::queryOne(
            "SELECT id, username, display_name, role, bio, avatar_url,
                    twitter_handle, facebook_url, linkedin_url,
                    instagram_handle, whatsapp_number, website_url
               FROM users
              WHERE role = 'super_admin'
              ORDER BY created_at ASC
              LIMIT 1"
        ) ?? self::queryOne(
            "SELECT id, username, display_name, role, bio, avatar_url,
                    twitter_handle, facebook_url, linkedin_url,
                    instagram_handle, whatsapp_number, website_url
               FROM users
              WHERE role = 'admin'
              ORDER BY created_at ASC
              LIMIT 1"
        );
    }
}