<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\DB;
use PDO;
use PDOStatement;

/**
 * BaseModel — lightweight Active-Record-style query builder.
 *
 * Each child class sets $table (and optionally $primaryKey, $orderBy).
 * Provides: find, where, all, create, update, delete, count, paginate,
 *           plus raw query helpers for complex joins.
 */
abstract class BaseModel
{
    /** Table name — MUST be set by child */
    protected static string $table = '';

    /** Primary key column */
    protected static string $primaryKey = 'id';

    /** Default ordering */
    protected static string $orderBy = 'created_at DESC';

    // ── Finders ──────────────────────────────────────────────────

    /**
     * Find a single record by primary key.
     */
    public static function find(string $id): ?array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = :id LIMIT 1";
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find a single record by a column value.
     */
    public static function findBy(string $column, mixed $value): ?array
    {
        $col = self::sanitizeColumn($column);
        $sql = "SELECT * FROM " . static::$table . " WHERE {$col} = :val LIMIT 1";
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([':val' => $value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all records (with optional ordering override).
     */
    public static function all(?string $orderBy = null): array
    {
        $order = $orderBy ?? static::$orderBy;
        $sql = "SELECT * FROM " . static::$table . " ORDER BY {$order}";
        return DB::pdo()->query($sql)->fetchAll() ?: [];
    }

    /**
     * Simple WHERE query returning multiple rows.
     *
     * @param array $conditions ['column' => value, ...] (AND logic)
     */
    public static function where(array $conditions, ?string $orderBy = null, ?int $limit = null): array
    {
        $clauses = [];
        $params  = [];
        $i = 0;
        foreach ($conditions as $col => $val) {
            $safeCol = self::sanitizeColumn($col);
            $placeholder = ":w{$i}";
            if ($val === null) {
                $clauses[] = "{$safeCol} IS NULL";
            } else {
                $clauses[] = "{$safeCol} = {$placeholder}";
                $params[$placeholder] = $val;
            }
            $i++;
        }

        $order = $orderBy ?? static::$orderBy;
        $sql = "SELECT * FROM " . static::$table
             . " WHERE " . implode(' AND ', $clauses)
             . " ORDER BY {$order}";

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    // ── CRUD ─────────────────────────────────────────────────────

    /**
     * Insert a new record. Returns the full inserted row.
     *
     * @param array $data ['column' => value, ...]
     */
    public static function create(array $data): ?array
    {
        if (empty($data)) return null;

        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $col => $val) {
            $safeCol = self::sanitizeColumn($col);
            $columns[] = $safeCol;
            $ph = ":{$safeCol}";
            $placeholders[] = $ph;
            $params[$ph] = $val;
        }

        $sql = "INSERT INTO " . static::$table
             . " (" . implode(', ', $columns) . ")"
             . " VALUES (" . implode(', ', $placeholders) . ")"
             . " RETURNING *";

        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Update a record by primary key. Returns the updated row.
     *
     * @param string $id   Primary key value
     * @param array  $data ['column' => value, ...]
     */
    public static function update(string $id, array $data): ?array
    {
        if (empty($data)) return null;

        $sets   = [];
        $params = [':pk' => $id];

        foreach ($data as $col => $val) {
            $safeCol = self::sanitizeColumn($col);
            $ph = ":s_{$safeCol}";
            $sets[] = "{$safeCol} = {$ph}";
            $params[$ph] = $val;
        }

        $sql = "UPDATE " . static::$table
             . " SET " . implode(', ', $sets)
             . " WHERE " . static::$primaryKey . " = :pk"
             . " RETURNING *";

        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Delete a record by primary key. Returns true if a row was deleted.
     */
    public static function delete(string $id): bool
    {
        $sql = "DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = :id";
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Aggregates ───────────────────────────────────────────────

    /**
     * Count rows, optionally filtered.
     *
     * @param array $conditions ['column' => value, ...]
     */
    public static function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            return (int)DB::pdo()->query("SELECT COUNT(*) FROM " . static::$table)->fetchColumn();
        }

        $clauses = [];
        $params  = [];
        $i = 0;
        foreach ($conditions as $col => $val) {
            $safeCol = self::sanitizeColumn($col);
            if ($val === null) {
                $clauses[] = "{$safeCol} IS NULL";
            } else {
                $ph = ":c{$i}";
                $clauses[] = "{$safeCol} = {$ph}";
                $params[$ph] = $val;
            }
            $i++;
        }

        $sql = "SELECT COUNT(*) FROM " . static::$table . " WHERE " . implode(' AND ', $clauses);
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Paginate results with optional WHERE clauses.
     *
     * @return array{rows: array, total: int, page: int, totalPages: int, perPage: int}
     */
    public static function paginate(
        int $page = 1,
        int $perPage = 20,
        string $whereSql = '',
        array $params = [],
        ?string $orderBy = null,
        string $selectSql = '*',
        string $fromSql = ''
    ): array {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $order  = $orderBy ?? static::$orderBy;
        $from   = $fromSql ?: static::$table;
        $whereClause = $whereSql !== '' ? "WHERE {$whereSql}" : '';

        // Count
        $countSql = "SELECT COUNT(*) FROM {$from} {$whereClause}";
        $countStmt = DB::pdo()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));

        // Rows
        $sql = "SELECT {$selectSql} FROM {$from} {$whereClause} ORDER BY {$order} LIMIT :lim OFFSET :off";
        $stmt = DB::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        return compact('rows', 'total', 'page', 'totalPages', 'perPage');
    }

    // ── Raw helpers ──────────────────────────────────────────────

    /**
     * Execute a raw query and return all rows.
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Execute a raw query and return one row.
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Execute a raw query and return a single column value.
     */
    public static function queryColumn(string $sql, array $params = []): mixed
    {
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute a raw statement (INSERT/UPDATE/DELETE) — returns row count.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get the PDO instance (for transactions, etc.)
     */
    public static function pdo(): PDO
    {
        return DB::pdo();
    }

    // ── Security ─────────────────────────────────────────────────

    /**
     * Sanitize a column name to prevent SQL injection.
     * Only allows alphanumeric + underscores.
     */
    private static function sanitizeColumn(string $col): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
            throw new \InvalidArgumentException("Invalid column name: {$col}");
        }
        return $col;
    }
}