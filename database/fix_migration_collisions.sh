#!/usr/bin/env bash
# ============================================================
# fix_migration_collisions.sh
#
# Resolves the 9-file migration numbering collision identified
# in the system audit. The 'b' suffix files are exact duplicates
# of their originals (verified via diff). This script removes
# the duplicates so the migration runner never double-applies them.
#
# Run ONCE from the project root:
#   bash database/fix_migration_collisions.sh
#
# Safe to run even if already partially resolved — checks existence
# before removing.
# ============================================================

set -e
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/migrations" && pwd)"

echo "[fix_migrations] Working in: $DIR"

remove_if_exists() {
    local f="$DIR/$1"
    if [ -f "$f" ]; then
        rm "$f"
        echo "  ✓ Removed duplicate: $1"
    else
        echo "  – Already gone: $1"
    fi
}

echo ""
echo "[fix_migrations] Removing exact-duplicate 'b' suffix files..."
remove_if_exists "0037b_breaking_news_engine.sql"
remove_if_exists "0038b_add_full_page_scrape.sql"
remove_if_exists "0039b_masthead_ad_slots.sql"
remove_if_exists "0041b_system_admin_expansion.sql"
remove_if_exists "0042b_visitor_coordinates.sql"

echo ""
echo "[fix_migrations] Renaming ambiguous same-number files to sequential order..."

# 0037: three files share prefix — assign clear sequential numbers
# Keep: 0037_add_download_images.sql (alphabetically first = runs first)
# Rename: 0037_breaking_news_engine.sql → 0037b_breaking_news_engine.sql  [actually remove - already handled above]
# These two were both numbered 0037. Since they use IF NOT EXISTS guards,
# we just ensure download_images runs at 0037 and breaking_news at a safe slot.
# Check if 0037_breaking_news_engine conflicts with 0037_add_download_images:
if [ -f "$DIR/0037_breaking_news_engine.sql" ] && [ -f "$DIR/0037_add_download_images.sql" ]; then
    mv "$DIR/0037_breaking_news_engine.sql" "$DIR/0037a_breaking_news_engine.sql"
    echo "  ✓ Renamed: 0037_breaking_news_engine.sql → 0037a_breaking_news_engine.sql"
fi

# 0038: two files — keep add_full_page_scrape, rename category_sidebar
if [ -f "$DIR/0038_category_sidebar.sql" ] && [ -f "$DIR/0038_add_full_page_scrape.sql" ]; then
    mv "$DIR/0038_category_sidebar.sql" "$DIR/0038a_category_sidebar.sql"
    echo "  ✓ Renamed: 0038_category_sidebar.sql → 0038a_category_sidebar.sql"
fi

# 0039: two files — keep masthead_ad_slots, rename new_categories
if [ -f "$DIR/0039_new_categories.sql" ] && [ -f "$DIR/0039_masthead_ad_slots.sql" ]; then
    mv "$DIR/0039_new_categories.sql" "$DIR/0039a_new_categories.sql"
    echo "  ✓ Renamed: 0039_new_categories.sql → 0039a_new_categories.sql"
fi

# 0041: two files — keep article_reading_time, rename system_admin_expansion
if [ -f "$DIR/0041_system_admin_expansion.sql" ] && [ -f "$DIR/0041_article_reading_time.sql" ]; then
    mv "$DIR/0041_system_admin_expansion.sql" "$DIR/0041a_system_admin_expansion.sql"
    echo "  ✓ Renamed: 0041_system_admin_expansion.sql → 0041a_system_admin_expansion.sql"
fi

# 0042: two files — keep tags, rename visitor_coordinates
if [ -f "$DIR/0042_visitor_coordinates.sql" ] && [ -f "$DIR/0042_tags.sql" ]; then
    mv "$DIR/0042_visitor_coordinates.sql" "$DIR/0042a_visitor_coordinates.sql"
    echo "  ✓ Renamed: 0042_visitor_coordinates.sql → 0042a_visitor_coordinates.sql"
fi

echo ""
echo "[fix_migrations] Final migration list (numeric prefix sorted):"
ls "$DIR"/*.sql | xargs -I{} basename {} | sort

echo ""
echo "[fix_migrations] Done. Run 'php database/migrate.php' to apply any pending migrations."
