#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2026 TigerSprite Team
# SPDX-License-Identifier: AGPL-3.0-only
#
# One-shot deployment script for the YooMail realtime service (systemd).
#
# Usage:
#   sudo bash deploy.sh [ncDir]
#
# Arguments:
#   ncDir    Nextcloud root directory. When omitted it is auto-detected:
#            - walk up from the script location (assumed to be apps-extra/yoomail)
#            - or fall back to the current working directory
#
# What it does:
#   1. Check that the script runs as root (exit with instructions otherwise)
#   2. Auto-detect the PHP runtime user/group (owner/group of config/config.php:
#      www-data / apache / custom user)
#   3. Auto-detect the PHP CLI binary
#   4. Generate /etc/systemd/system/yoomail.service (substituting path, user,
#      group and PHP binary)
#   5. Start and enable the yoomail service
#
# Optional environment variables:
#   YOOMAIL_SKIP_START=1       Only install the service unit, do not start it (debugging)
#   YOOMAIL_ASSUME_YES=1       Non-interactive environments: auto-confirm, no prompt (CI/scripts)
#   YOOMAIL_SERVICE_USER=...   Override the service User (default: owner of config/config.php)
#   YOOMAIL_SERVICE_GROUP=...  Override the service Group (default: group of config/config.php)
#   YOOMAIL_PHP_BIN=...        Override the PHP CLI path (default: auto-detected)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="yoomail"
UNIT_FILE="/etc/systemd/system/${SERVICE_NAME}.service"

# ---------- 0. Resolve the Nextcloud root directory ----------
NC_ROOT=""
if [ $# -ge 1 ]; then
	if [ -d "$1" ]; then
		NC_ROOT="$(cd "$1" && pwd)"
	fi
else
	# Try to infer it from the script location: <root>/apps-extra/yoomail
	if [[ "$SCRIPT_DIR" == *"/apps-extra/yoomail" ]]; then
		NC_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
	fi
fi
if [ -z "$NC_ROOT" ] || [ ! -f "$NC_ROOT/config/config.php" ]; then
	echo "Error: could not determine the Nextcloud root directory (config/config.php not found)."
	echo "Usage: sudo bash deploy.sh /path/to/nextcloud"
	exit 1
fi
echo "[1/5] Nextcloud root detected: $NC_ROOT"

# ---------- 1. Check root ----------
if [ "$(id -u)" -ne 0 ]; then
	echo ""
	echo "Error: this script must be run as root."
	echo "Reason: it writes a service unit into /etc/systemd/system/ and runs systemctl enable/start."
	echo ""
	echo "Run it with: sudo bash $0 ${1:+$1}"
	exit 1
fi
echo "[2/5] Running as root ✓"

# ---------- 1.5 User confirmation ----------
echo ""
echo "=========================================="
echo "  About to install the YooMail realtime systemd service"
echo "  Nextcloud directory: $NC_ROOT"
echo "=========================================="
echo ""
if [ -t 0 ]; then
	read -r -p "Continue with this directory? [y/N] " CONFIRM
else
	echo "Non-interactive environment, skipping confirmation (set YOOMAIL_ASSUME_YES=1 to auto-confirm)."
	CONFIRM="${YOOMAIL_ASSUME_YES:-N}"
fi
if [ "${CONFIRM:-N}" != "y" ] && [ "${CONFIRM:-N}" != "Y" ]; then
	echo "Cancelled. To point at a specific directory, run: sudo bash $0 /path/to/nextcloud"
	exit 1
fi
echo "Confirmed, continuing..."

# ---------- 2. Detect the service user / group ----------
# Default to the owner/group of config/config.php; works for apache, www-data
# and custom runtime users alike.
CONFIG_OWNER="$(stat -c '%U' "$NC_ROOT/config/config.php" 2>/dev/null || stat -f '%Su' "$NC_ROOT/config/config.php" 2>/dev/null)"
CONFIG_GROUP="$(stat -c '%G' "$NC_ROOT/config/config.php" 2>/dev/null || stat -f '%Sg' "$NC_ROOT/config/config.php" 2>/dev/null)"

SERVICE_USER="${YOOMAIL_SERVICE_USER:-$CONFIG_OWNER}"
SERVICE_GROUP="${YOOMAIL_SERVICE_GROUP:-$CONFIG_GROUP}"

if [ -z "$SERVICE_USER" ] || [ "$SERVICE_USER" = "UNKNOWN" ]; then
	echo "Error: could not detect the service runtime user."
	exit 1
fi
if [ -z "$SERVICE_GROUP" ] || [ "$SERVICE_GROUP" = "UNKNOWN" ]; then
	echo "Error: could not detect the service runtime group."
	exit 1
fi
if ! id "$SERVICE_USER" >/dev/null 2>&1; then
	echo "Error: detected runtime user '$SERVICE_USER' does not exist."
	exit 1
fi
if ! getent group "$SERVICE_GROUP" >/dev/null 2>&1; then
	echo "Error: detected runtime group '$SERVICE_GROUP' does not exist."
	exit 1
fi

PHP_BIN="${YOOMAIL_PHP_BIN:-$(command -v php || true)}"
if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
	echo "Error: no executable PHP CLI found. Override it with YOOMAIL_PHP_BIN=/path/to/php."
	exit 1
fi

echo "[3/5] Runtime user: $SERVICE_USER (owner of config/config.php)"
echo "      Runtime group: $SERVICE_GROUP (group of config/config.php)"
echo "      PHP CLI: $PHP_BIN"

# ---------- 3. Generate and install the service unit ----------
TEMPLATE="$SCRIPT_DIR/realtime/deploy/yoomail.service.template"
if [ ! -f "$TEMPLATE" ]; then
	echo "Error: service template not found at $TEMPLATE"
	exit 1
fi

sed \
	-e "s|__NC_ROOT__|$NC_ROOT|g" \
	-e "s|__PHP_USER__|$SERVICE_USER|g" \
	-e "s|__PHP_GROUP__|$SERVICE_GROUP|g" \
	-e "s|__PHP_BIN__|$PHP_BIN|g" \
	"$TEMPLATE" > "$UNIT_FILE"

echo "Generated $UNIT_FILE"

systemctl daemon-reload
echo "[4/5] Service unit installed (daemon-reload done)"

# ---------- 4. Enable and start ----------
if [ "${YOOMAIL_SKIP_START:-0}" = "1" ]; then
	echo "Skipped start (YOOMAIL_SKIP_START=1). Start it manually with:"
	echo "  systemctl enable --now $SERVICE_NAME"
	exit 0
fi

systemctl enable "$SERVICE_NAME"
systemctl start "$SERVICE_NAME"

echo ""
echo "✅ YooMail realtime service started"
echo ""
systemctl status "$SERVICE_NAME" --no-pager || true
echo ""
echo "Tips:"
echo "  - View logs: sudo journalctl -u $SERVICE_NAME -f"
echo "  - Restart:   sudo systemctl restart $SERVICE_NAME"
echo "  - nginx needs a /yoomail-ws reverse proxy (see realtime/deploy/nginx-yoomail-ws.conf.snippet)"
