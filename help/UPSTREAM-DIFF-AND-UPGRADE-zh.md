<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail 源码改动与升级注意事项

本文档记录 YooMail 相对上游 [Nextcloud Mail](https://github.com/nextcloud/mail) 修改了哪些源码文件，以及后续升级、合并上游版本、重新打包前端时必须注意的点。

> [English](UPSTREAM-DIFF-AND-UPGRADE.md)

## 1. 项目结构

YooMail 基于 Nextcloud Mail 5.10.12 二次开发，整体仍沿用上游目录结构，但当前仓库以“可直接部署的应用包”为主：

```text
yoomail/
├── appinfo/          # 应用元数据(info.xml、路由)
├── lib/              # PHP 后端(命名空间 OCA\YooMail)
├── js/               # 已提交的前端构建产物(yoomail.js 等)
├── css/              # 全局样式
├── l10n/             # 翻译
├── realtime/         # 实时收信服务(Workerman + IMAP IDLE)
├── templates/        # 后台设置等模板
├── help/             # 文档
└── README.md         # 产品说明
```

说明：

- 当前仓库没有随包提交独立的 `src/` 前端源码树。
- 现阶段前端改动直接以 `js/` 中的部署产物为准。
- 如果后续重新引入源码目录，必须同步补充构建流程与升级文档。

## 2. 与上游的全局差异

| 项目 | 上游 mail | YooMail |
|------|-----------|---------|
| 应用 id | `mail` | `yoomail` |
| 命名空间 | `OCA\Mail` | `OCA\YooMail` |
| 数据库表 | `oc_mail_*` | `oc_yoomail_*` |
| occ 命令 | `mail:*` | `yoomail:*` |
| 前端 bundle | `mail.js` | `yoomail.js` |

YooMail 与上游 `mail` 应用可以并存，互不干扰。

## 3. 基于上游新增的功能

### 3.1 实时收信

位于 `realtime/`：

- `server.php` — 服务入口
- `lib/ImapIdleManager.php` — 收集账号并启动 IDLE 监听
- `lib/ImapIdleConnection.php` — IMAP IDLE 长连接
- `lib/ImapIdleChild.php` — 检测变化后触发同步并上报
- `lib/RealtimeSyncService.php` — 调用 `occ yoomail:account:sync`
- `lib/RealtimeServer.php` — 负责 WebSocket / IPC / Channel worker 编排
- `lib/WebSocketServer.php` — 认证与事件推送

流程：

- IDLE 检测到文件夹变化
- `occ yoomail:account:sync` 增量落库
- IPC/Channel 广播事件
- WebSocket 推送到浏览器
- 前端刷新邮箱列表

### 3.2 正文缓存

- `lib/Service/MessageBodyStorage.php` — 正文持久化缓存
- `lib/Controller/MessagesController.php::getBody` — 先读缓存，首次渲染后写入

### 3.3 特殊文件夹 UIDVALIDITY 修复

- `lib/Service/Sync/ImapToDbSynchronizer.php::sync` — 同步前先 SELECT 邮箱
- 避免部分中文邮箱特殊文件夹反复清缓存

## 4. 当前仓库里的关键前端改动区域

由于当前仓库直接提交 `js/` 构建产物，后续升级时最容易被覆盖的是这些 bundle 中承载的 YooMail 定制逻辑：

- `js/yoomail.js`
- `js/admin-basic-settings.js`
- `js/personal-notification-settings.js`

重点关注的功能方向：

- 应用重命名（`mail` → `yoomail`）
- realtime token / WebSocket 路径切换
- 用户时区与 12/24 小时显示
- 邮件详情区样式与布局调整
- 删除确认、列表自动刷新、通知与声音提醒
- 后台管理页与个人通知页
- 关于页版本号显示 `version + internal-version`

特别注意：

- 当前仓库没有可直接修改后再自动重建的 `src/` 源码树说明文件。
- 如果以后补回源码构建链路，要把“源码入口、打包命令、产物路径”一并更新到本文档。

## 5. 前端构建与打包

当前仓库以已提交的 `js/` 部署产物为准，因此升级时要遵循：

- 不要被上游 `mail` 的 bundle 覆盖掉 YooMail 自己的 `js/` 文件
- 任何前端修改都必须最终反映到仓库中的 `js/` 部署文件
- 发布前要确认后台设置页、通知页、主邮件页加载到的都是 YooMail 当前版本脚本

如果后续重新引入标准前端源码与构建链，再补充明确的构建命令。

## 6. 升级检查清单

后续如果合并更高版本上游 Mail，优先检查这些地方：

1. `appinfo/info.xml`
2. `appinfo/routes.php`
3. `lib/Controller/PageController.php`
4. `lib/Controller/RealtimeController.php`
5. `lib/Controller/SettingsController.php`
6. `lib/Service/MessageBodyStorage.php`
7. `lib/Service/RealtimeMessagePublisher.php`
8. `realtime/server.php`
9. `realtime/deploy/yoomail.service.template`
10. `deploy.sh`
11. `templates/settings-admin.php`
12. `templates/settings-personal-notifications.php`
13. `js/yoomail.js`
14. `js/admin-basic-settings.js`
15. `js/personal-notification-settings.js`

还要逐项确认：

1. 所有 `/apps/mail/...` 路径是否仍已改成 `/apps/yoomail/...`
2. 所有 `t('mail', ...)` / `n('mail', ...)` 是否仍已改成 `yoomail`
3. realtime token 路由是否仍存在
4. 关于页显示的是否仍是 YooMail 版本，而不是上游 `5.10.12`
5. 管理页与个人通知页是否仍正确注入初始状态
6. WebSocket 公网地址、服务模板和部署脚本是否仍是 YooMail 版本

## 7. 发版提醒

- `appinfo/info.xml` 里的 `<version>` 要和 `CHANGELOG.md` 对齐
- 每次内部发版都要同步更新 `<internal-version>`
- 打包前确认 `l10n/` 与 `js/` 已更新
- 发布包排除 `.git`、`node_modules`、`debug` 等非发布内容
