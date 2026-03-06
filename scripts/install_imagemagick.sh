#!/bin/sh
# install_imagemagick.sh — Install ImageMagick with full format support
# Run: docker exec -it northern_times_app sh /var/www/html/scripts/install_imagemagick.sh

set -e

echo "Installing ImageMagick with AVIF, WebP, HEIC support..."

# Install ImageMagick + all format delegates
apk add --no-cache \
    imagemagick \
    imagemagick-heic \
    imagemagick-svg \
    imagemagick-webp \
    libavif \
    libheif \
    2>/dev/null || {
    # Fallback for minimal Alpine — try just imagemagick
    apk add --no-cache imagemagick 2>/dev/null || true
}

# Verify
echo ""
echo "=== ImageMagick installed ==="
convert -version 2>/dev/null | head -3 || magick -version 2>/dev/null | head -3 || echo "WARNING: ImageMagick not found"

echo ""
echo "=== Supported formats ==="
convert -list format 2>/dev/null | grep -iE "avif|webp|heic|heif|tiff|svg|png|jpeg|gif|bmp|jp2|jxl" | head -20 || echo "Could not list formats"

echo ""
echo "Done! ImageMagick ready for thumbnail creation."