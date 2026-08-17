<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail

**🚀 高速邮件应用 for [Nextcloud](https://nextcloud.com)**

YooMail 是基于 [Nextcloud Mail](https://github.com/nextcloud/mail) 5.10.12 的二次开发产品,专注**实时收信**与**秒开阅读**体验。

> [English README](README.md) · [Changelog](CHANGELOG.md) · [部署文档](help/DEPLOYMENT-zh.md) · [源码改动与升级注意事项](help/UPSTREAM-DIFF-AND-UPGRADE-zh.md) · [代码规范](help/CODE-STANDARDS-zh.md)

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

## 与上游 Mail 的差异化对比

| 能力 | 上游 Mail | YooMail | 价值 |
|------|-----------|---------|------|
| **实时收信** | 轮询(60s + cron) | **IMAP IDLE + WebSocket**(毫秒级) | 核心差异化,企业场景感知极强 |
| **多端实时同步** | 页面级 | 服务端推送 + 列表增量合并 | 领先 |
| **删除同步** | 单端 | **双端可配置同步 + `remoteMissing` 提示** | 解决多端误删痛点 |
| **后台运维配置** | 少 | 时间格式 / 删除同步 / 清理 / 拉取范围 / 实时模式 | 企业自管需求 |
| **通知** | 基本 | 声音 + toast + 原生 + 发送反馈 | 完整 |
| **正文缓存秒开** | 每次打开重新渲染 | **持久化缓存,二次打开近秒开** | 更快的阅读体验 |

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

安装部署、realtime 服务安装、nginx 反代以及升级部署操作，统一见 [help/DEPLOYMENT-zh.md](help/DEPLOYMENT-zh.md)。

## 反馈与支持

如果你在使用过程中遇到问题或有改进建议,请在 issue 跟踪器中反馈。

## 许可证

本项目基于 [AGPL-3.0-only](https://www.gnu.org/licenses/agpl-3.0.html) 许可分发。
