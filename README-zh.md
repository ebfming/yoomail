<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail

**🚀 高速邮件应用 for [Nextcloud](https://nextcloud.com)**

YooMail 是基于 [Nextcloud Mail](https://github.com/nextcloud/mail) 5.10.12 的二次开发产品,专注**实时收信**与**秒开阅读**体验。

> [English README](README.md) · [Changelog](CHANGELOG.md) · [开发文档](help/DEVELOPMENT-zh.md)

## 产品特色

### ⚡ 实时收信(Realtime)
- 内置 **Workerman + IMAP IDLE** 常驻服务,与 Nextcloud 深度集成
- 新邮件到达 **秒级推送**,无需手动刷新或等待轮询
- 前端通过 WebSocket 实时感知邮件变化,列表自动更新

### 🚀 正文缓存秒开
- 邮件正文首次渲染后**持久化缓存**
- 二次打开近乎**秒开**,大幅降低重复解析开销

### 📨 兼容中国部分邮箱
- 针对中国部分邮箱(腾讯企业邮箱/QQ邮箱、网易邮箱/网易企业邮箱等)特殊文件夹(已发送/已删除/草稿箱)的 **UIDVALIDITY 兼容修复**
- 修复了特殊文件夹反复清空缓存的问题

### 📥 完整的邮件功能(继承自 Nextcloud Mail)
- **多账号支持** — 个人与企业邮箱统一收件箱,支持任意 IMAP 账号
- **邮件线程** — 按主题分组
- **邮箱管理** — 增删改子邮箱
- **加密支持** — Mailvelope 与 S/MIME
- **深度集成** — Contacts、Calendar、Files

## 版本说明

| 项目 | 值 |
|------|-----|
| YooMail 版本 | 0.1.0 |
| 基于 Nextcloud Mail | 5.10.12 |
| 适用 Nextcloud | 32 – 35 |

## 安装与部署

### 1. 安装应用

**大多数用户**:直接在 Nextcloud 管理面板的"应用"页面搜索 **YooMail** 一键安装即可(应用上架 App Store 后),无需手动操作。

**手动安装**(自托管/离线环境):将 `yoomail` 目录放入 Nextcloud 的 `apps-extra/`(或 `apps/`)目录,然后在管理面板启用,或执行:

```bash
# Debian / Ubuntu
sudo -u www-data php occ app:enable yoomail

# CentOS / RHEL
sudo -u apache php occ app:enable yoomail
```

> 提示:`occ` 必须以 `config/config.php` 的属主用户运行。Debian/Ubuntu 通常为 `www-data`,CentOS/RHEL 通常为 `apache`,具体可用 `ls -l config/config.php` 确认。
>
> 首次启用会自动创建 `yoomail_*` 数据库表(独立于原 `mail_*` 表)。

### 2. 部署实时服务(可选,启用实时收信)

实时服务以 systemd 单元管理,使用一键部署脚本(以 root 运行):

```bash
sudo bash deploy.sh                          # 自动探测 Nextcloud 目录
sudo bash deploy.sh /path/to/nextcloud       # 或手动指定目录
```

脚本会:
1. 检查是否以 root 运行
2. 展示探测到的 Nextcloud 目录并请求确认
3. 自动探测 PHP 运行用户(`config/config.php` 属主,如 `www-data`/`apache`)
4. 生成并安装 `/etc/systemd/system/yoomail.service`
5. 启用并启动服务

非交互(CI)环境可用:`YOOMAIL_ASSUME_YES=1 sudo bash deploy.sh <nc目录>`

### 3. 配置 nginx 反向代理(WebSocket)

在 nginx 配置中增加(见 `realtime/deploy/nginx-yoomail-ws.conf.snippet`):

```nginx
location /yoomail-ws {
    proxy_pass http://127.0.0.1:8789;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 3600;
    proxy_send_timeout 3600;
}
```

### 4. 实时服务配置(可选)

实时服务配置存储为应用配置(数据库 `oc_appconfig` 表),可在后台设置页修改。配置项及默认值:

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `yoomail.realtime.ws_host` | `127.0.0.1` | WebSocket 监听地址 |
| `yoomail.realtime.ws_port` | `8789` | WebSocket 端口 |
| `yoomail.realtime.ipc_port` | `8790` | IPC 端口 |
| `yoomail.realtime.channel_port` | `2207` | 内部 Channel 端口 |
| `yoomail.realtime.ws_public_url` | *(空)* | 如使用独立域名反代,可填写 `wss://mail.example.com/yoomail-ws` |
| `yoomail.realtime.idle_refresh_seconds` | `1500` | IMAP IDLE 刷新间隔 |

> 修改后需重启实时服务生效:`sudo systemctl restart yoomail`

## 反馈与支持

如果你在使用过程中遇到问题或有改进建议,请在 issue 跟踪器中反馈。

## 许可证

本项目基于 [AGPL-3.0-only](https://www.gnu.org/licenses/agpl-3.0.html) 许可分发。
