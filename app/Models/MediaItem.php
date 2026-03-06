<?php
declare(strict_types=1);

namespace App\Models;

/**
 * MediaItem model — media_library table.
 *
 * Schema columns:
 *   id, media_type, source_type, title, description, folder, tags,
 *   file_path, public_url, webp_url, thumbnail_url,
 *   original_name, mime_type, file_size,
 *   sha256, width, height, uploaded_by, created_at
 */
final class MediaItem extends BaseModel
{
    protected static string $table   = 'media_library';
    protected static string $orderBy = 'created_at DESC';

    /**
     * Paginated listing with optional folder filter + search.
     */
    public static function adminList(
        int $page = 1,
        int $perPage = 24,
        string $folder = '',
        string $q = ''
    ): array {
        $where  = [];
        $params = [];

        if ($folder !== '') {
            $where[]           = "folder = :folder";
            $params[':folder'] = $folder;
        }
        if ($q !== '') {
            $where[]      = "(title ILIKE :q OR original_name ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        $whereSql = $where ? implode(' AND ', $where) : '1=1';

        return self::paginate(
            page: $page,
            perPage: $perPage,
            whereSql: $whereSql,
            params: $params,
            orderBy: 'created_at DESC'
        );
    }

    /**
     * Picker listing for article form modal (images only).
     */
    public static function pickerList(string $q = '', string $folder = '', int $page = 1, int $perPage = 24): array
    {
        $where  = "media_type = 'image'";
        $params = [];

        if ($q !== '') {
            $where       .= " AND (title ILIKE :q OR original_name ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        if ($folder !== '') {
            $where           .= " AND folder = :folder";
            $params[':folder'] = $folder;
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
     * Find duplicate by SHA256 hash.
     */
    public static function findByHash(string $sha256): ?array
    {
        return self::queryOne(
            "SELECT id, public_url, webp_url, thumbnail_url FROM media_library WHERE sha256 = :sha LIMIT 1",
            [':sha' => $sha256]
        );
    }

    /**
     * Get distinct folder names with counts.
     */
    public static function folders(): array
    {
        return self::query(
            "SELECT folder, COUNT(*) AS cnt FROM media_library WHERE folder IS NOT NULL GROUP BY folder ORDER BY folder ASC"
        );
    }

    /**
     * Insert a new media record.
     * Columns match actual DB schema: media_type, source_type, title, folder,
     * file_path, public_url, webp_url, thumbnail_url, original_name, mime_type,
     * file_size, sha256, width, height, uploaded_by.
     */
    public static function insertMedia(array $data): ?array
    {
        return self::queryOne("
            INSERT INTO media_library
            (media_type, source_type, title, folder,
            file_path, public_url, webp_url, thumbnail_url,
            original_name, mime_type, file_size,
            sha256, width, height, uploaded_by, created_at)
            VALUES

            (:media_type, :source_type, :title, :folder,
            :file_path, :public_url, :webp_url, :thumbnail_url,
            :original_name, :mime_type, :file_size,
            :sha256, :width, :height, :uploaded_by, NOW())
            RETURNING *
            
        ", [
            ':media_type'    => $data['media_type'] ?? 'image',
            ':source_type'   => $data['source_type'] ?? 'upload',
            ':title'         => $data['title'] ?? '',
            ':folder'        => $data['folder'] ?? 'Articles',
            ':file_path'     => $data['file_path'] ?? null,
            ':public_url'    => $data['public_url'] ?? null,
            ':webp_url'      => $data['webp_url'] ?? null,
            ':thumbnail_url' => $data['thumbnail_url'] ?? null,
            ':original_name' => $data['original_name'] ?? '',
            ':mime_type'     => $data['mime_type'] ?? '',
            ':file_size'     => $data['file_size'] ?? 0,
            ':sha256'        => $data['sha256'] ?? '',
            ':width'         => $data['width'] ?? null,
            ':height'        => $data['height'] ?? null,
            ':uploaded_by'   => $data['uploaded_by'] ?? null,
        ]);
    }

    /**
     * Dashboard: total media count.
     */
    public static function totalCount(): int
    {
        return self::count();
    }
}