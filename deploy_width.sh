#!/bin/bash
# ══════════════════════════════════════════════════════════════
# Northern Times — Width + Masthead Color Deployment
# ══════════════════════════════════════════════════════════════
#
# WHAT THIS FIXES:
# ────────────────
# Problem 1: Homepage has huge dead-space margins on 1920px monitors
#   Root cause: 3-layer width bottleneck
#     Layer A: DB theme_max_width = 1180px → injected as --max by get_theme_css()
#     Layer B: .container { max-width: var(--max) } → caps ALL page content at 1180px
#     Layer C: <main class="container"> in layout.php → wraps homepage inside 1180px box
#     Result:  section.home's --home-max: 1920px can NEVER exceed its 1180px parent
#
# Problem 2: Masthead "The Northern Times" doesn't change color with theme
#   Root cause: .masthead-title hardcoded to color: var(--ink)
#     Needs a separate --mast-color variable so it can differ from body text
#
# FILES MODIFIED (5 total):
# ─────────────────────────
# 1. app.css         → .container-home class, --mast-color on masthead
# 2. layout.php      → Homepage <main> gets container-home class
# 3. helpers.php     → get_theme_css() outputs --mast-color variable
# 4. DB              → theme_max_width → 1600px, add theme_mast_color setting
# 5. dark-mode.css   → --mast-color override for dark mode
#
# HOW TO RUN:
#   chmod +x deploy_width.sh
#   ./deploy_width.sh
# ══════════════════════════════════════════════════════════════

set -e

echo "═══════════════════════════════════════════════════"
echo "  Northern Times — Width + Masthead Color Deploy"
echo "═══════════════════════════════════════════════════"

# ── CONFIG ──────────────────────────────────────────────────
# Adjust these if your setup differs
CONTAINER="northern-times-app-1"  # Docker container name
APP_DIR="/var/www/html"           # App root inside container
DB_CONTAINER="northern-times-db-1"
DB_NAME="northern_times"
DB_USER="nt_user"
DB_PASS="nt_pass"

echo ""
echo "Step 1/5: Updating app.css..."
echo "────────────────────────────"

# Copy the updated app.css
docker cp app.css "$CONTAINER:$APP_DIR/public/assets/app.css"
echo "  ✓ app.css deployed (container-home class + --mast-color)"

echo ""
echo "Step 2/5: Updating layout.php..."
echo "─────────────────────────────────"

# Copy the updated layout.php
docker cp layout.php "$CONTAINER:$APP_DIR/app/Views/frontend/layout.php"
echo "  ✓ layout.php deployed (homepage gets container-home class)"

echo ""
echo "Step 3/5: Patching helpers.php (adding --mast-color to get_theme_css)..."
echo "────────────────────────────────────────────────────────────────────────"

# Patch helpers.php inside the container
# We add --mast-color variable to the CSS output in get_theme_css()
docker exec "$CONTAINER" sh -c "
cd $APP_DIR/app/Support

# Add mast-color variable extraction (after accentText line)
if ! grep -q 'theme_mast_color' helpers.php; then
  sed -i \"/accentText.*=.*theme_accent_text/a\\    \\\\\\$mastColor  = \\\\\\$c('theme_mast_color',  '');\" helpers.php
  echo '  ✓ Added \$mastColor extraction'
else
  echo '  ○ \$mastColor extraction already present'
fi

# Add --mast-color to CSS output (after --accent-text line in the heredoc)
if ! grep -q 'mast-color' helpers.php; then
  sed -i '/--accent-text:.*accentText/a\\  --mast-color: {\\$mastColor};' helpers.php
  echo '  ✓ Added --mast-color to CSS output'
else
  echo '  ○ --mast-color already in CSS output'
fi
"

# Now fix: if mast-color is empty, it should fall back to --ink
# We need the CSS to handle empty value gracefully
# The app.css already uses var(--mast-color, var(--ink)) so empty string = fallback works

echo "  ✓ helpers.php patched"

echo ""
echo "Step 4/5: Updating database..."
echo "──────────────────────────────"

docker exec -e PGPASSWORD="$DB_PASS" "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
  -- Update container width: 1180px → 1600px
  UPDATE site_settings SET setting_value = '1600px' WHERE setting_key = 'theme_max_width';

  -- Add masthead color setting (empty = inherits --ink)
  INSERT INTO site_settings (setting_key, setting_value, setting_group)
  VALUES ('theme_mast_color', '', 'theme')
  ON CONFLICT (setting_key) DO NOTHING;

  -- Bust cache
  UPDATE site_settings SET setting_value = EXTRACT(EPOCH FROM NOW())::TEXT
  WHERE setting_key = 'theme_cache_bust';
"
echo "  ✓ theme_max_width → 1600px"
echo "  ✓ theme_mast_color setting added"
echo "  ✓ Cache busted"

echo ""
echo "Step 5/5: Patching dark-mode for masthead color..."
echo "──────────────────────────────────────────────────"

# Append masthead dark-mode override to dark-mode.css
docker exec "$CONTAINER" sh -c "
cd $APP_DIR/public/assets
if ! grep -q 'mast-color' dark-mode.css 2>/dev/null; then
  cat >> dark-mode.css << 'DARKEOF'

/* Masthead color in dark mode — follows --ink unless overridden */
html[data-theme=\"dark\"] .masthead-title,
html[data-theme=\"dark\"] .footer-masthead {
  color: var(--mast-color, var(--ink, #f0f0f0));
}
DARKEOF
  echo '  ✓ Dark mode masthead color added'
else
  echo '  ○ Dark mode masthead color already present'
fi
"

echo ""
echo "═══════════════════════════════════════════════════"
echo "  DEPLOYMENT COMPLETE"
echo "═══════════════════════════════════════════════════"
echo ""
echo "  Width changes:"
echo "    Homepage:    1920px (was 1780px — edge-to-edge on 1080p)"
echo "    Other pages: 1600px (was 1180px from DB)"
echo "    Ticker gap:  16px  (was 8px)"
echo ""
echo "  Masthead color:"
echo "    Set via Admin → Settings → Theme → 'Masthead Color'"
echo "    Leave empty = follows --ink (body text color)"
echo "    Set a value like '#cc0000' for accent-colored masthead"
echo "    Works in both light and dark mode"
echo ""
echo "  Hard refresh: Ctrl+Shift+R to see changes"
echo ""