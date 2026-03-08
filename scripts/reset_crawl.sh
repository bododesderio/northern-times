#!/bin/sh
# reset_crawl.sh — Complete crawl reset: articles, images, garbage categories, logs
# Usage: docker exec -it northern_times_app sh /var/www/html/scripts/reset_crawl.sh

set -e

DB_CMD="PGPASSWORD=${DB_PASS} psql -h ${DB_HOST:-db} -U ${DB_USER:-northern} -d ${DB_NAME:-northern_times} -t -A"

echo "========================================="
echo " FULL CRAWL RESET"
echo "========================================="

# 1. Delete crawled article dependencies
echo "[1/7] Clearing article dependencies..."
$DB_CMD -c "DELETE FROM article_view_snapshots;" 2>/dev/null || true
$DB_CMD -c "DELETE FROM comments WHERE article_id IN (SELECT id FROM articles WHERE is_crawled = TRUE);" 2>/dev/null || true
$DB_CMD -c "UPDATE articles SET breaking_score = 0, breaking_until = NULL, is_breaking_manual = FALSE WHERE is_crawled = TRUE;" 2>/dev/null || true

# 2. Count and delete crawled articles
COUNT=$($DB_CMD -c "SELECT COUNT(*) FROM articles WHERE is_crawled = TRUE;" 2>/dev/null || echo "0")
echo "[2/7] Deleting $COUNT crawled articles..."
$DB_CMD -c "DELETE FROM articles WHERE is_crawled = TRUE;"

# 3. Delete crawled media records
MEDIA_COUNT=$($DB_CMD -c "SELECT COUNT(*) FROM media_library WHERE source_type = 'crawled';" 2>/dev/null || echo "0")
echo "[3/7] Deleting $MEDIA_COUNT crawled media records..."
$DB_CMD -c "DELETE FROM media_library WHERE source_type = 'crawled';" 2>/dev/null || true

# 4. Delete garbage auto-created categories
GARBAGE=$($DB_CMD -c "SELECT COUNT(*) FROM categories WHERE description = 'Auto-created from crawled article RSS tag.';" 2>/dev/null || echo "0")
echo "[4/7] Deleting $GARBAGE auto-created garbage categories..."
# First reassign any articles still pointing to garbage categories
$DB_CMD -c "
  UPDATE articles SET category_id = (SELECT id FROM categories WHERE slug = 'world' LIMIT 1)
  WHERE category_id IN (SELECT id FROM categories WHERE description = 'Auto-created from crawled article RSS tag.');
" 2>/dev/null || true
# Then delete
$DB_CMD -c "DELETE FROM categories WHERE description = 'Auto-created from crawled article RSS tag.';" 2>/dev/null || true

# 5. Reset crawl source timestamps so all sources crawl again
echo "[5/7] Resetting crawl source timestamps..."
$DB_CMD -c "UPDATE crawl_sources SET last_crawled_at = NULL, consecutive_errors = 0;"

# 6. Delete crawled image files from disk
echo "[6/7] Deleting crawled image files..."
rm -rf /var/www/html/storage/uploads/Crawled/*
echo "    Disk cleaned."

# 7. Flush Redis cache
echo "[7/7] Flushing Redis cache..."
redis-cli -h ${REDIS_HOST:-redis} FLUSHDB 2>/dev/null || true

echo ""
echo "========================================="
echo " RESET COMPLETE"
echo "========================================="
echo " Crawled articles deleted: $COUNT"
echo " Media records deleted:    $MEDIA_COUNT"
echo " Garbage categories deleted: $GARBAGE"
echo " All source timers reset"
echo " Image files cleaned"
echo " Redis cache flushed"
echo ""
echo " Run crawl: php /var/www/html/cron/crawl.php"
echo "========================================="