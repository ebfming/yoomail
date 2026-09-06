#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2026 TigerSprite Team
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Build a signed app-store release tarball for YooMail.
#
# Usage:
#   bash build/release.sh
#
# Environment (optional):
#   NC_ROOT      Nextcloud root dir          (default: /web/nextcloud)
#   NC_USER      User to run occ as          (default: owner of config/config.php)
#   CERT_DIR     Directory with signing key  (default: $HOME/.nextcloud/certificates)
#   SKIP_SIGN=1  Build unsigned tarball only (useful before the cert is approved)

set -euo pipefail

APP_NAME="yoomail"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
NC_ROOT="${NC_ROOT:-/web/nextcloud}"
APP_OWNER="$(stat -c '%U' "$APP_DIR")"
APP_GROUP="$(stat -c '%G' "$APP_DIR")"
if [ -z "${NC_USER:-}" ]; then
	NC_USER="$(stat -c '%U' "$NC_ROOT/config/config.php")"
fi
if [ -z "${CERT_DIR:-}" ] && [ -n "${SUDO_USER:-}" ] && [ "$SUDO_USER" != "root" ]; then
	CERT_DIR="$(eval echo "~$SUDO_USER")/.nextcloud/certificates"
else
	CERT_DIR="${CERT_DIR:-$HOME/.nextcloud/certificates}"
fi
PRIVATE_KEY="$CERT_DIR/$APP_NAME.key"
CERTIFICATE="$CERT_DIR/$APP_NAME.crt"

cd "$APP_DIR"

if [ ! -f appinfo/info.xml ]; then
	echo "Error: appinfo/info.xml not found in $APP_DIR" >&2
	exit 1
fi
VERSION="$(python3 -c "import xml.etree.ElementTree as ET; print(ET.parse('appinfo/info.xml').getroot().find('version').text)")"
echo "Building YooMail $VERSION"

rm -rf build/appstore build/artifacts
mkdir -p build/appstore build/artifacts

echo "[1/3] Copying distribution files"
rsync -a \
	--exclude '.git' \
	--exclude '.github' \
	--exclude 'node_modules' \
	--exclude 'build' \
	--exclude 'debug' \
	--exclude 'src' \
	--exclude 'tests' \
	--exclude 'babel.config.js' \
	--exclude 'webpack.*.js' \
	--exclude 'tsconfig.json*' \
	--exclude 'stylelint.config.js' \
	--exclude 'package-lock.json' \
	--exclude 'patches.json' \
	--exclude 'deploy.sh' \
	--exclude '*.js.map' \
	./ "build/appstore/$APP_NAME/"

if [ "${SKIP_SIGN:-0}" != "1" ]; then
	if [ ! -f "$PRIVATE_KEY" ] || [ ! -f "$CERTIFICATE" ]; then
		echo "Error: signing key/certificate not found in $CERT_DIR (SKIP_SIGN=1 to build unsigned)." >&2
		exit 1
	fi
	if [ "$(id -u)" -ne 0 ]; then
		echo "Error: signed releases must be built as root so the signing key can be shared with $NC_USER temporarily." >&2
		echo "Run: sudo CERT_DIR='$CERT_DIR' bash build/release.sh" >&2
		exit 1
	fi
	echo "[2/3] Signing app"
	TMP_SIGN_DIR="$(mktemp -d)"
	trap 'rm -rf "$TMP_SIGN_DIR"' EXIT
	TMP_PRIVATE_KEY="$TMP_SIGN_DIR/$APP_NAME.key"
	TMP_CERTIFICATE="$TMP_SIGN_DIR/$APP_NAME.crt"
	cp -f "$PRIVATE_KEY" "$TMP_PRIVATE_KEY"
	cp -f "$CERTIFICATE" "$TMP_CERTIFICATE"
	chown "$NC_USER:$NC_USER" "$TMP_SIGN_DIR"
	chmod 700 "$TMP_SIGN_DIR"
	chown "$NC_USER:$NC_USER" "$TMP_PRIVATE_KEY" "$TMP_CERTIFICATE"
	chmod 600 "$TMP_PRIVATE_KEY"
	chmod 644 "$TMP_CERTIFICATE"
	chown -R "$NC_USER:$NC_USER" "build/appstore/$APP_NAME"
	su "$NC_USER" -s /bin/sh -c "cd '$NC_ROOT' && php occ integrity:sign-app --privateKey='$TMP_PRIVATE_KEY' --certificate='$TMP_CERTIFICATE' --path='$APP_DIR/build/appstore/$APP_NAME'"
	if [ ! -f "build/appstore/$APP_NAME/appinfo/signature.json" ]; then
		echo "Error: app signing finished without appinfo/signature.json" >&2
		exit 1
	fi
	chown -R "$APP_OWNER:$APP_GROUP" "build/appstore/$APP_NAME" 2>/dev/null || true
fi

echo "[3/3] Packaging"
tar -czf "build/artifacts/$APP_NAME-$VERSION.tar.gz" -C build/appstore "$APP_NAME"

echo ""
echo "Done: build/artifacts/$APP_NAME-$VERSION.tar.gz"
echo "Release archive signature:"
openssl dgst -sha512 -sign "$PRIVATE_KEY" "build/artifacts/$APP_NAME-$VERSION.tar.gz" | openssl base64
