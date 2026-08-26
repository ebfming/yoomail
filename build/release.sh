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
#   NC_USER      User to run occ as          (default: www-data)
#   CERT_DIR     Directory with signing key  (default: $HOME/.nextcloud/certificates)
#   SKIP_SIGN=1  Build unsigned tarball only (useful before the cert is approved)

set -euo pipefail

APP_NAME="yoomail"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
NC_ROOT="${NC_ROOT:-/web/nextcloud}"
NC_USER="${NC_USER:-www-data}"
CERT_DIR="${CERT_DIR:-$HOME/.nextcloud/certificates}"
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
	echo "[2/3] Signing app"
	"$NC_ROOT/occ" integrity:sign-app \
		--privateKey="$PRIVATE_KEY" \
		--certificate="$CERTIFICATE" \
		--path="$APP_DIR/build/appstore/$APP_NAME" \
		|| su "$NC_USER" -c "cd '$NC_ROOT' && php occ integrity:sign-app --privateKey='$PRIVATE_KEY' --certificate='$CERTIFICATE' --path='$APP_DIR/build/appstore/$APP_NAME'"
fi

echo "[3/3] Packaging"
tar -czf "build/artifacts/$APP_NAME-$VERSION.tar.gz" -C build/appstore "$APP_NAME"

echo ""
echo "Done: build/artifacts/$APP_NAME-$VERSION.tar.gz"
echo "Release archive signature:"
openssl dgst -sha512 -sign "$PRIVATE_KEY" "build/artifacts/$APP_NAME-$VERSION.tar.gz" | openssl base64
