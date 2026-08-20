#!/usr/bin/env bash
#
# Sign the YooMail appstore build with the official Nextcloud certificate.
# occ drops privileges to www-data automatically, so the key/cert must be
# readable by www-data.
# Run as root:  sudo bash build/sign-yoomail.sh
set -euo pipefail

APP_DIR="/web/nextcloud/apps-extra/yoomail"
BUILD_DIR="$APP_DIR/build/appstore/yoomail"
KEY_SRC="/home/adam/.nextcloud/certificates/yoomail.key"
CRT_SRC="/home/adam/.nextcloud/certificates/yoomail.crt"
TMP_DIR="/tmp/yoomail-sign"
KEY="$TMP_DIR/yoomail.key"
CRT="$TMP_DIR/yoomail.crt"

if [ "$(id -u)" -ne 0 ]; then
	echo "请用 root 运行: sudo bash $0"
	exit 1
fi

echo "[1/4] 准备临时密钥（授权给 www-data，因为 occ 会自动降权）"
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"
cp -f "$KEY_SRC" "$KEY"
cp -f "$CRT_SRC" "$CRT"
chown www-data:www-data "$KEY" "$CRT"
chmod 600 "$KEY" "$CRT"

echo "[2/4] 授予 www-data 对构建目录的写权限"
chown -R www-data:www-data "$BUILD_DIR"

echo "[3/4] 运行 occ integrity:sign-app"
php /web/nextcloud/occ integrity:sign-app \
	--privateKey="$KEY" \
	--certificate="$CRT" \
	--path="$BUILD_DIR"

echo "[4/4] 还原属主并清理临时密钥"
chown -R adam:adam "$BUILD_DIR"
rm -rf "$TMP_DIR"

echo ""
echo "完成。签名文件: $BUILD_DIR/appinfo/signature.json"
ls -la "$BUILD_DIR/appinfo/signature.json"