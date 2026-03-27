#!/usr/bin/env bash
#
# Package theypaid-payments for WordPress.org distribution.
# Usage: ./build.sh  (run from plugin root or any directory)
#
# Produces: theypaid-payments-{version}.zip
# The zip extracts to theypaid-payments/ — ready for WP upload or SVN commit.

set -euo pipefail

# Always run from the directory containing this script (the plugin root)
cd "$(dirname "$0")"

PLUGIN_SLUG="theypaid-payments"
VERSION=$(grep "Version:" "${PLUGIN_SLUG}.php" 2>/dev/null | head -1 | sed 's/.*Version:[[:space:]]*\([0-9.]*\).*/\1/' || echo "1.0.0")
BUILD_DIR="build"
DIST_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "==> Building ${PLUGIN_SLUG} v${VERSION}"

# Clean previous build
rm -rf "${BUILD_DIR}"
mkdir -p "${DIST_DIR}"

# Copy plugin files (no trailing slash on directories to preserve structure)
echo "    Copying plugin files..."
cp theypaid-payments.php "${DIST_DIR}/"
cp readme.txt "${DIST_DIR}/"
cp -R includes "${DIST_DIR}/includes"
cp -R assets "${DIST_DIR}/assets"
cp -R templates "${DIST_DIR}/templates"

# Copy languages directory if it exists
if [ -d "languages" ]; then
  cp -R languages "${DIST_DIR}/languages"
fi

# Remove dev-only files that may have been copied
echo "    Removing dev files..."
DEV_FILES=(
  "TESTING.md"
  "ASSETS_NEEDED.md"
  "build.sh"
  "Makefile"
  ".wordpress-org"
  ".git"
  ".gitignore"
  ".editorconfig"
  ".phpcs.xml"
  ".phpcs.xml.dist"
  "phpcs.xml"
  "phpcs.xml.dist"
  "composer.json"
  "composer.lock"
  "package.json"
  "package-lock.json"
  "node_modules"
  ".DS_Store"
)

for f in "${DEV_FILES[@]}"; do
  rm -rf "${DIST_DIR}/${f}"
done

# Remove any .DS_Store files nested in subdirectories
find "${DIST_DIR}" -name ".DS_Store" -delete 2>/dev/null || true

# Create zip (-X excludes macOS resource forks for better WP compatibility)
echo "    Creating ${ZIP_NAME}..."
cd "${BUILD_DIR}"
zip -rqX "../${ZIP_NAME}" "${PLUGIN_SLUG}/"
cd ..

# Clean up build dir
rm -rf "${BUILD_DIR}"

echo "==> Done! Created ${ZIP_NAME}"
echo "    Install: WordPress Admin → Plugins → Add New → Upload Plugin"
