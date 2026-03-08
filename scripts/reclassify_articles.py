"""
Re-categorize all crawled articles using the AI classifier.

Reads articles from PostgreSQL, sends each through the /classify-category
endpoint, and updates category_id where the AI disagrees.

Usage: docker-compose exec extractor python /scripts/reclassify_articles.py
  Or:  python scripts/reclassify_articles.py  (from host with DB access)
"""

import json
import os
import sys
import time
import httpx
import psycopg2
from psycopg2.extras import RealDictCursor

# ── Config ──────────────────────────────────────────────────
DB_CONFIG = {
    "host": os.environ.get("DB_HOST", "db"),
    "port": int(os.environ.get("DB_PORT", 5432)),
    "dbname": os.environ.get("DB_NAME", "northern_times"),
    "user": os.environ.get("DB_USER", "northern"),
    "password": os.environ.get("DB_PASS", ""),
}
CLASSIFIER_URL = "http://localhost:5000/classify-category"
CONFIDENCE_THRESHOLD = 35  # Accept AI result if >= 35%
BATCH_SIZE = 10

# ── Main ────────────────────────────────────────────────────

def main():
    conn = psycopg2.connect(**DB_CONFIG)
    cur = conn.cursor(cursor_factory=RealDictCursor)

    # Load categories
    cur.execute("SELECT id, name, slug FROM categories ORDER BY name")
    categories = cur.fetchall()
    slug_to_id = {c["slug"]: c["id"] for c in categories}
    id_to_slug = {c["id"]: c["slug"] for c in categories}
    id_to_name = {c["id"]: c["name"] for c in categories}
    system_slugs = list(slug_to_id.keys())

    # Load all crawled articles
    cur.execute("""
        SELECT a.id, a.title, a.excerpt, a.source_url, a.category_id,
               LEFT(a.content, 1000) as content_snippet
        FROM articles a
        WHERE a.is_crawled = TRUE
        ORDER BY a.created_at DESC
    """)
    articles = cur.fetchall()
    total = len(articles)

    print(f"\n{'='*70}")
    print(f"  AI Re-categorization of {total} crawled articles")
    print(f"  Confidence threshold: {CONFIDENCE_THRESHOLD}%")
    print(f"{'='*70}\n")

    changed = 0
    kept = 0
    errors = 0
    changes_log = []

    client = httpx.Client(timeout=60.0)

    for i, article in enumerate(articles, 1):
        title = article["title"] or ""
        content = article["content_snippet"] or article["excerpt"] or ""
        current_cat_id = article["category_id"]
        current_slug = id_to_slug.get(current_cat_id, "unknown")
        current_name = id_to_name.get(current_cat_id, "Unknown")

        # Strip HTML tags from content for classification
        import re
        clean_content = re.sub(r'<[^>]+>', ' ', content)
        clean_content = re.sub(r'\s+', ' ', clean_content).strip()

        try:
            resp = client.post(CLASSIFIER_URL, json={
                "title": title,
                "content": clean_content[:800],
                "rss_categories": [],
                "url": article["source_url"] or "",
                "system_categories": system_slugs,
            })
            result = resp.json()
        except Exception as e:
            errors += 1
            print(f"  [{i}/{total}] ERROR: {title[:60]}... - {e}")
            continue

        ai_slug = result.get("category_slug", "")
        ai_confidence = result.get("confidence", 0)
        conflict_guard = result.get("conflict_guard", False)

        if ai_confidence < CONFIDENCE_THRESHOLD:
            kept += 1
            if i % 50 == 0:
                print(f"  [{i}/{total}] Processing... ({changed} changed so far)")
            continue

        if ai_slug == current_slug:
            kept += 1
            continue

        # AI disagrees with current category
        new_cat_id = slug_to_id.get(ai_slug)
        if not new_cat_id:
            kept += 1
            continue

        new_name = id_to_name.get(new_cat_id, ai_slug)
        guard_flag = " [CONFLICT GUARD]" if conflict_guard else ""

        change_entry = {
            "title": title[:80],
            "old": current_name,
            "new": new_name,
            "confidence": ai_confidence,
            "conflict_guard": conflict_guard,
        }
        changes_log.append(change_entry)

        # Update in DB
        cur.execute(
            "UPDATE articles SET category_id = %s WHERE id = %s",
            (new_cat_id, article["id"])
        )
        changed += 1

        print(f"  [{i}/{total}] CHANGED: \"{title[:60]}...\"")
        print(f"           {current_name} -> {new_name} (confidence: {ai_confidence}%){guard_flag}")

    conn.commit()
    client.close()

    # Summary
    print(f"\n{'='*70}")
    print(f"  RESULTS")
    print(f"{'='*70}")
    print(f"  Total articles:  {total}")
    print(f"  Changed:         {changed}")
    print(f"  Kept:            {kept}")
    print(f"  Errors:          {errors}")
    print(f"{'='*70}\n")

    if changes_log:
        print(f"  CHANGES BY CATEGORY MOVEMENT:")
        print(f"  {'-'*66}")
        movements = {}
        for c in changes_log:
            key = f"{c['old']} -> {c['new']}"
            movements[key] = movements.get(key, 0) + 1
        for movement, count in sorted(movements.items(), key=lambda x: -x[1]):
            print(f"    {movement}: {count} articles")

        print(f"\n  CONFLICT GUARD ACTIVATIONS:")
        guard_count = sum(1 for c in changes_log if c["conflict_guard"])
        print(f"    {guard_count} articles had conflict guard triggered")

    cur.close()
    conn.close()


if __name__ == "__main__":
    main()
