#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

get_version() {
    local file="$1"
    if [[ -f "$file" ]]; then
        local line
        line="$(grep -E '^APP_VERSION=' "$file" | tail -n1 || true)"
        if [[ -n "$line" ]]; then
            local value="${line#APP_VERSION=}"
            value="${value%\"}"
            value="${value#\"}"
            value="${value%\'}"
            value="${value#\'}"
            echo "$value"
            return 0
        fi
    fi
    return 1
}

VERSION="$(get_version .env || true)"
if [[ -z "$VERSION" ]]; then
    VERSION="$(get_version .env.example || true)"
fi
VERSION="${VERSION:-1.0.0}"

ZIP_NAME="monatliches-dist-v${VERSION}.zip"

BUILD_TMP_DIR="${TMPDIR:-/tmp}/monatliches-build-$$"
mkdir -p "$BUILD_TMP_DIR"
mkdir -p storage/framework/cache storage/framework/cache/data storage/framework/sessions storage/framework/views

LOG_CHANNEL=stderr LOG_STACK=stderr \
APP_PACKAGES_CACHE="${BUILD_TMP_DIR}/packages.php" \
APP_SERVICES_CACHE="${BUILD_TMP_DIR}/services.php" \
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build

mkdir -p dist
rm -f "dist/${ZIP_NAME}"

zip -r "dist/${ZIP_NAME}" . \
    -x ".git/*" \
    -x ".env" \
    -x ".env.*" \
    -x ".phpunit.result.cache" \
    -x ".DS_Store" \
    -x "*/.DS_Store" \
    -x "budget" \
    -x "database/*.sqlite" \
    -x "node_modules/*" \
    -x "tests/*" \
    -x "dist/*" \
    -x "bootstrap/cache/*.php" \
    -x "storage/app/installed.lock" \
    -x "storage/app/private/installed.lock" \
    -x "storage/app/installer.key" \
    -x "storage/app/installer.env" \
    -x "storage/app/update/*" \
    -x "storage/app/private/update/*" \
    -x "storage/logs/*" \
    -x "storage/framework/cache/*" \
    -x "storage/framework/sessions/*" \
    -x "storage/framework/views/*" \
    -x "public/hot" \
    -x ".codex/*"

# Ensure required empty storage directories exist in the dist package.
EMPTY_DIR_ROOT="${BUILD_TMP_DIR}/empty"
mkdir -p \
    "${EMPTY_DIR_ROOT}/storage/framework/cache" \
    "${EMPTY_DIR_ROOT}/storage/framework/cache/data" \
    "${EMPTY_DIR_ROOT}/storage/framework/sessions" \
    "${EMPTY_DIR_ROOT}/storage/framework/views"

(cd "${EMPTY_DIR_ROOT}" && zip -r "${ROOT_DIR}/dist/${ZIP_NAME}" storage/framework/cache storage/framework/cache/data storage/framework/sessions storage/framework/views >/dev/null)

echo "Created dist/${ZIP_NAME}"
