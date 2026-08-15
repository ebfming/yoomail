<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# Changelog

本项目(YooMail)是基于 [Nextcloud Mail](https://github.com/nextcloud/mail) 的二次开发产品,所有版本基于上游 [5.10.12](https://github.com/nextcloud/mail/releases/tag/v5.10.12) 构建。

## [Unreleased]

### Fixed

- 修复邮箱【订阅】/【取消订阅】操作报 500 的问题:部分邮箱服务器(腾讯企业邮箱/QQ 邮箱等)禁止 IMAP `SUBSCRIBE`/`UNSUBSCRIBE` 命令(实测 `imap.exmail.qq.com` 对所有邮箱的 UNSUBSCRIBE 均返回 `NO Not allow to unsubscribe!`)。现在降级为记录日志并同步邮箱列表后返回当前状态,不再中断请求。

### Changed

- 代码注释、脚本提示统一改为英文;新增多语言词条(同步限流提示走 l10n,`zh_CN.json` 保持原中文文案)。
- `appinfo/info.xml` 的 `description` 改为英文(应用商店元数据,便于国际发布)。
- 新增开发规范文档(中英双份):`help/CODE-STANDARDS.md`、`CONTRIBUTING.md`、`SECURITY.md` 及对应 `-zh.md`,README 文档索引同步更新。
- 项目信息落地:作者/维护者 `adam <dev@ebf.hk>`(info.xml `<author>`、AUTHORS.md、CONTRIBUTING)。
- 代码规范补充 PHPDoc 约定(`@param`/`@return`/`@throws`、兼容 PHPStan/Psalm、`@version` 使用当前 git 分支名)与严格类型模式约定;YooMail 原创 PHP 文件类注释统一标注 `@version b-2026.08.13`。

## [0.1.0] - 2026-08-13

### Added

- ⚡ **实时收信(Realtime)**:新增 `realtime/` 服务(Workerman + IMAP IDLE + WebSocket),新邮件秒级推送,前端列表自动刷新。
- 🚀 **正文缓存秒开**:新增 `MessageBodyStorage`,邮件正文首次渲染后持久化缓存,二次打开秒开。
- 📨 **中国部分邮箱兼容修复**:修复腾讯企业邮箱、QQ邮箱、网易邮箱等对特殊文件夹(已发送/已删除/草稿箱)返回中文 UTF-7 名导致 UIDVALIDITY 变化、缓存反复清空的问题。
- 📖 新增产品 README、CHANGELOG、部署文档(`help/DEPLOYMENT.md`)。

### Changed

- 应用命名为 **YooMail**,独立于上游 `mail` 应用。
- 命名空间由 `OCA\Mail` 调整为 `OCA\YooMail`。
- 数据库表前缀由 `mail_*` 调整为 `yoomail_*`(独立数据,不影响原 `mail` 应用)。
- occ 命令由 `mail:*` 调整为 `yoomail:*`(如 `yoomail:account:sync`)。
- 实时服务端口独立(WS 8789 / IPC 8790 / Channel 2207),可与原 `mail` 实时服务共存。

### Upstream base

- 基于 Nextcloud Mail **5.10.12** 构建,保留了上游全部邮件功能(多账号、线程、加密、S/MIME、Calendar/Contacts/Files 集成等)。

[0.1.0]: https://github.com/tigersprite/yoomail/releases/tag/v0.1.0
