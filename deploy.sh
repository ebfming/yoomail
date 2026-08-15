#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2026 TigerSprite Team
# SPDX-License-Identifier: AGPL-3.0-only
#
# YooMail 实时服务(systemd)一键部署脚本
#
# 用法:
#   sudo bash deploy.sh [nc目录]
#
# 参数:
#   nc目录   Nextcloud 根目录。不传时自动探测:
#            - 脚本所在目录向上找(假定在 apps-extra/yoomail 内)
#            - 或使用当前目录
#
# 功能:
#   1. 检查是否 root 运行(不是则退出并说明)
#   2. 自动探测运行用户/组(config/config.php 的属主/属组:www-data / apache 等)
#   3. 自动探测 PHP CLI 路径
#   4. 生成 /etc/systemd/system/yoomail.service(自动替换路径、用户、组、PHP)
#   5. 启动并启用 yoomail 服务
#
# 可选环境变量:
#   YOOMAIL_SKIP_START=1       只安装服务单元,不启动(调试用)
#   YOOMAIL_ASSUME_YES=1       非交互环境自动确认,不提示(CI/脚本用)
#   YOOMAIL_SERVICE_USER=...   手动指定 service User(默认取 config/config.php 属主)
#   YOOMAIL_SERVICE_GROUP=...  手动指定 service Group(默认取 config/config.php 属组)
#   YOOMAIL_PHP_BIN=...        手动指定 PHP CLI 路径(默认自动探测)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="yoomail"
UNIT_FILE="/etc/systemd/system/${SERVICE_NAME}.service"

# ---------- 0. 确定 Nextcloud 根目录 ----------
NC_ROOT=""
if [ $# -ge 1 ]; then
	if [ -d "$1" ]; then
		NC_ROOT="$(cd "$1" && pwd)"
	fi
else
	# 尝试从脚本位置推断:<root>/apps-extra/yoomail
	if [[ "$SCRIPT_DIR" == *"/apps-extra/yoomail" ]]; then
		NC_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
	fi
fi
if [ -z "$NC_ROOT" ] || [ ! -f "$NC_ROOT/config/config.php" ]; then
	echo "错误: 无法确定 Nextcloud 根目录(未找到 config/config.php)。"
	echo "用法: sudo bash deploy.sh /path/to/nextcloud"
	exit 1
fi
echo "[1/5] 探测到 Nextcloud 根目录: $NC_ROOT"

# ---------- 1. 检查 root ----------
if [ "$(id -u)" -ne 0 ]; then
	echo ""
	echo "错误: 必须以 root 运行本脚本。"
	echo "原因: 需要向 /etc/systemd/system/ 写入服务单元,并执行 systemctl 启用/启动服务。"
	echo ""
	echo "请用: sudo bash $0 ${1:+$1}"
	exit 1
fi
echo "[2/5] 已以 root 运行 ✓"

# ---------- 1.5 用户确认 ----------
echo ""
echo "=========================================="
echo "  即将为 YooMail 安装 systemd 实时服务"
echo "  Nextcloud 目录: $NC_ROOT"
echo "=========================================="
echo ""
if [ -t 0 ]; then
	read -r -p "确认使用该目录继续? [y/N] " CONFIRM
else
	echo "非交互环境,跳过确认(YOOMAIL_ASSUME_YES=1 可自动确认)。"
	CONFIRM="${YOOMAIL_ASSUME_YES:-N}"
fi
if [ "${CONFIRM:-N}" != "y" ] && [ "${CONFIRM:-N}" != "Y" ]; then
	echo "已取消。如需指定目录,请用: sudo bash $0 /path/to/nextcloud"
	exit 1
fi
echo "已确认,继续部署..."

# ---------- 2. 探测运行用户 / 组 ----------
# 默认取 config/config.php 的属主/属组,兼容 apache / www-data / 自定义用户
CONFIG_OWNER="$(stat -c '%U' "$NC_ROOT/config/config.php" 2>/dev/null || stat -f '%Su' "$NC_ROOT/config/config.php" 2>/dev/null)"
CONFIG_GROUP="$(stat -c '%G' "$NC_ROOT/config/config.php" 2>/dev/null || stat -f '%Sg' "$NC_ROOT/config/config.php" 2>/dev/null)"

SERVICE_USER="${YOOMAIL_SERVICE_USER:-$CONFIG_OWNER}"
SERVICE_GROUP="${YOOMAIL_SERVICE_GROUP:-$CONFIG_GROUP}"

if [ -z "$SERVICE_USER" ] || [ "$SERVICE_USER" = "UNKNOWN" ]; then
	echo "错误: 无法探测 service 运行用户。"
	exit 1
fi
if [ -z "$SERVICE_GROUP" ] || [ "$SERVICE_GROUP" = "UNKNOWN" ]; then
	echo "错误: 无法探测 service 运行组。"
	exit 1
fi
if ! id "$SERVICE_USER" >/dev/null 2>&1; then
	echo "错误: 探测到的运行用户 '$SERVICE_USER' 不存在。"
	exit 1
fi
if ! getent group "$SERVICE_GROUP" >/dev/null 2>&1; then
	echo "错误: 探测到的运行组 '$SERVICE_GROUP' 不存在。"
	exit 1
fi

PHP_BIN="${YOOMAIL_PHP_BIN:-$(command -v php || true)}"
if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
	echo "错误: 未找到可执行的 PHP CLI。可通过 YOOMAIL_PHP_BIN=/path/to/php 指定。"
	exit 1
fi

echo "[3/5] 运行用户: $SERVICE_USER(config/config.php 属主)"
echo "      运行组:   $SERVICE_GROUP(config/config.php 属组)"
echo "      PHP CLI:  $PHP_BIN"

# ---------- 3. 生成并安装服务单元 ----------
TEMPLATE="$SCRIPT_DIR/realtime/deploy/yoomail.service.template"
if [ ! -f "$TEMPLATE" ]; then
	echo "错误: 未找到服务模板 $TEMPLATE"
	exit 1
fi

sed \
	-e "s|__NC_ROOT__|$NC_ROOT|g" \
	-e "s|__PHP_USER__|$SERVICE_USER|g" \
	-e "s|__PHP_GROUP__|$SERVICE_GROUP|g" \
	-e "s|__PHP_BIN__|$PHP_BIN|g" \
	"$TEMPLATE" > "$UNIT_FILE"

echo "已生成 $UNIT_FILE"

systemctl daemon-reload
echo "[4/5] 服务单元已安装(daemon-reload 完成)"

# ---------- 4. 启用并启动 ----------
if [ "${YOOMAIL_SKIP_START:-0}" = "1" ]; then
	echo "已跳过启动(YOOMAIL_SKIP_START=1)。手动启动:"
	echo "  systemctl enable --now $SERVICE_NAME"
	exit 0
fi

systemctl enable "$SERVICE_NAME"
systemctl start "$SERVICE_NAME"

echo ""
echo "✅ YooMail 实时服务已启动"
echo ""
systemctl status "$SERVICE_NAME" --no-pager || true
echo ""
echo "提示:"
echo "  - 查看日志: sudo journalctl -u $SERVICE_NAME -f"
echo "  - 重启服务: sudo systemctl restart $SERVICE_NAME"
echo "  - nginx 需配置 /yoomail-ws 反代(见 realtime/deploy/nginx-yoomail-ws.conf.snippet)"
