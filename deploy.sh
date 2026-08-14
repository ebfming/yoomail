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
#   2. 自动探测运行用户(config/config.php 的属主:www-data / apache 等)
#   3. 生成 /etc/systemd/system/yoomail.service(自动替换路径和用户)
#   4. 启动并启用 yoomail 服务
#
# 可选环境变量:
#   YOOMAIL_SKIP_START=1       只安装服务单元,不启动(调试用)
#   YOOMAIL_ASSUME_YES=1       非交互环境自动确认,不提示(CI/脚本用)

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
echo "[1/4] 探测到 Nextcloud 根目录: $NC_ROOT"

# ---------- 1. 检查 root ----------
if [ "$(id -u)" -ne 0 ]; then
	echo ""
	echo "错误: 必须以 root 运行本脚本。"
	echo "原因: 需要向 /etc/systemd/system/ 写入服务单元,并执行 systemctl 启用/启动服务。"
	echo ""
	echo "请用: sudo bash $0 ${1:+$1}"
	exit 1
fi
echo "[2/4] 已以 root 运行 ✓"

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

# ---------- 2. 探测运行用户 ----------
# occ / 实时服务必须以 config/config.php 属主运行
CONFIG_OWNER="$(stat -c '%U' "$NC_ROOT/config/config.php" 2>/dev/null || stat -f '%Su' "$NC_ROOT/config/config.php" 2>/dev/null)"
if [ -z "$CONFIG_OWNER" ] || [ "$CONFIG_OWNER" = "UNKNOWN" ]; then
	CONFIG_OWNER="www-data"
fi
# 校验用户存在
if ! id "$CONFIG_OWNER" >/dev/null 2>&1; then
	echo "错误: 探测到的运行用户 '$CONFIG_OWNER' 不存在。"
	exit 1
fi
echo "[3/4] 运行用户: $CONFIG_OWNER(config/config.php 属主)"

# ---------- 3. 生成并安装服务单元 ----------
TEMPLATE="$SCRIPT_DIR/realtime/deploy/yoomail.service.template"
if [ ! -f "$TEMPLATE" ]; then
	echo "错误: 未找到服务模板 $TEMPLATE"
	exit 1
fi

sed \
	-e "s|__NC_ROOT__|$NC_ROOT|g" \
	-e "s|__PHP_USER__|$CONFIG_OWNER|g" \
	"$TEMPLATE" > "$UNIT_FILE"

echo "已生成 $UNIT_FILE"

systemctl daemon-reload
echo "[4/4] 服务单元已安装(daemon-reload 完成)"

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
