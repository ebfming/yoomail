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
├── src/              # 前端源码(vue 组件、store、service 等,可重新构建)
├── js/               # 已提交的前端构建产物(yoomail.js 等)
├── css/              # 全局样式
├── img/              # 图标与图片资源
├── l10n/             # 翻译
├── realtime/         # 实时收信服务(Workerman + IMAP IDLE)
├── templates/        # 后台设置等模板
├── help/             # 文档
├── package.json      # 前端依赖与构建脚本
├── webpack.*.js      # 构建配置
├── tsconfig.json     # TS 配置
└── README.md         # 产品说明
```

说明：

- 仓库已随包提交前端源码 `src/` 与构建配置，前端改动必须从源码修改后重新构建，再提交 `js/` 产物。
- `src/` 是从部署的 `js/*.map` 的 `sourcesContent` 提取的 YooMail 定制完整源码（含 `<template>` 的 .vue 原始文件），与上游 mail 5.10.12 的差异主要是 `mail` → `yoomail` 应用 id/翻译键替换及少量定制逻辑。
- 独立脚本（`yoomail-notifications.js`、`yoomail-realtime-delta.js`、`yoomail-list-cache.js`、`admin-basic-settings.js`、`personal-notification-settings.js`）不在 webpack 构建范围内，由 PHP 模板直接引用，改动后直接提交即可，不要被 webpack 构建产物覆盖。
- 全局站点通知运行时不属于上述独立脚本：`yoomail-site-runtime-v5.js` 是从 `src/global-notifier.js` 构建出的 webpack 产物。

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

### 3.4 全局通知与顶部 app 图标标记

- `lib/Listener/GlobalNotifierAssetsListener.php` 在已登录用户页面注入 `yoomail-site-runtime-v5`。
- `src/global-notifier.js` 在同源浏览器标签中保持一个 WebSocket leader,并把通知事件广播给同站点可见标签页。
- `lib/Service/NotificationSettingsService.php` 保存用户级通知通道偏好。
- `templates/settings-personal.php` 渲染个人通知设置。
- `img/yoomail.svg` 与 `img/yoomail-dark.svg` 提供统一的 YooMail app 图标识别。

行为边界、浏览器限制与安全说明见 `help/GLOBAL-NOTIFICATIONS-zh.md`。

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

- 前端修改必须从 `src/` 源码开始，按第 5 节重新构建后提交 `js/` 产物；不要直接改 `js/` 里的压缩产物。
- 从部署 `js/*.map` 提取 `src/` 的方法见第 5.2 节，适用于丢失源码时的重建。

### 4.1 删除提示修复（b-2026.08.13）

后端已区分删除场景并返回结构化响应：

- `{ remoteMissing: true, message: '...' }`：本地已删除、远程可能已删除（HTTP 200）
- `{ missing: true }`：本地已不存在（HTTP 200）
- 其他失败：`{ status: 'error', message: 'Could not delete "主题". Reason: 原因. Please contact your administrator.' }`（HTTP 500 + `x-mail-response` 头）

对应的后端入口：

- `lib/Controller/MessagesController.php::destroy`
- `lib/Controller/ThreadController.php::delete`

前端消费逻辑（`src/`）：

- `src/store/mainStore/actions.js`：`deleteMessage` / `deleteThread` 不再忽略返回值，识别 `remoteMissing` 并返回 `{ remoteMissing, message }`；失败时把后端 `error.response.data.message` 透出。
- `src/service/ThreadService.js`：`deleteThread` 返回 `.data`（与 `MessageService.deleteMessage` 一致）。
- 组件在删除成功但 `remoteMissing` 时用 `showWarning` 提示“远程邮件可能已被删除”；失败时优先显示后端返回的完整原因（主题 + 原因 + 联系管理员）：
  - `src/components/ThreadEnvelope.vue`（`onDelete`）
  - `src/components/Envelope.vue`（`onDelete`）
  - `src/components/EnvelopeList.vue`（批量删除）
  - `src/components/Mailbox.vue`（快捷键删除分支）

### 4.2 时间格式 12/24 小时制修复（b-2026.08.17）

背景：后台可配置默认时间格式（`time_format_default`，12/24），PageController 已通过 initial state `time-format` 下发（用户偏好优先，回退后台默认），但前端邮件时间显示走 moment 的 `LT`/`LLL`/`lll`（由浏览器 locale 决定 12/24），**从未消费 `time-format` 偏好**，导致改后台设置前台不变。

修改（`src/main.js`）：

- 新增 `applyTimeFormat()`：读取 `loadState('yoomail', 'time-format', '24')`，对 **`@nextcloud/moment`** 实例调用 `updateLocale` 覆盖 `longDateFormat` 的 `LT`/`LLL`/`lll`/`LTS`：
  - `24` → `HH:mm`；`12` → `h:mm A`
- 注意：`@nextcloud/moment`（moment-with-locales）与 `moment-timezone` **不是同一个单例**（`import ncMoment from '@nextcloud/moment'` 验证过），时间格式必须作用于 `@nextcloud/moment` 实例；`moment.tz.setDefault` 只负责时区（moment-timezone 实例）。
- 模块加载顺序：`import` 提升保证 `@nextcloud/moment` 先设置好 locale，`applyTimeFormat()` 紧随其后执行，顺序正确。

## 5. 前端构建与打包

仓库随包提交 `src/` 源码与构建配置（`package.json`、`webpack.common.js`、`tsconfig.json`、`babel.config.js` 等），构建链路与上游 mail 一致（入口 `src/main.js`，产物写入 `js/`，chunk 命名为 `yoomail.[name].[contenthash].js`）。

### 5.1 构建命令

```bash
cd <yoomail 仓库根目录>
npm install                       # 首次需安装依赖
npm run build                     # 生产构建,产物写入 js/
```

- 构建脚本：`NODE_ENV=production webpack --config webpack.prod.js`
- 产物：`js/yoomail.js`（主入口）+ `js/yoomail.<id>.<hash>.js`（懒加载 chunk）+ `js/oauthpopup.js` / `js/settings.js` / `js/htmlresponse.js` / `js/yoomail-site-runtime-v5.js`
- 构建前确认 `src/main.js` 中的 `moment.tz.setDefault(loadState('yoomail', 'timezone', 'UTC'))`、`applyTimeFormat()`（12/24 小时制，作用于 `@nextcloud/moment`）与 `generateFilePath('yoomail', '', 'js/')` 存在（yoomail 定制，上游没有）。
- 部署时**只替换 webpack 产物**（`yoomail.js`、`yoomail.*.js`、`oauthpopup.js`、`settings.js`、`htmlresponse.js`、`yoomail-site-runtime-v5.js` 及 `.map`），**不要动独立脚本**：`yoomail-notifications.js`、`yoomail-realtime-delta.js`、`yoomail-list-cache.js`、`admin-basic-settings.js`、`personal-notification-settings.js`。

### 5.2 从部署产物重建 src（源码丢失时）

部署的 `js/*.map` 的 `sourcesContent` 里同时含 `.vue` 原始源码（带 `<template>`）与 vue-loader 编译产物（`var render = function`）。重建时对每个源文件**优先取含 `<template>` 的版本**，脚本见 `/tmp/yoomail-src/extract_src.py`。当前 `src/` 即由此方法从 `b-2026.08.13` 部署产物提取（232 个文件）。

### 5.3 部署注意事项

- 不要被上游 `mail` 的 bundle 覆盖掉 YooMail 自己的 `js/` 文件
- 任何前端修改都必须先从 `src/` 重新构建，最终反映到仓库中的 `js/` 部署文件
- 发布前要确认后台设置页、通知页、主邮件页加载到的都是 YooMail 当前版本脚本

## 6. 升级检查清单

后续如果合并更高版本上游 Mail，优先检查这些地方：

1. `appinfo/info.xml`
2. `appinfo/routes.php`
3. `lib/Controller/PageController.php`
4. `lib/Controller/RealtimeController.php`
5. `lib/Controller/SettingsController.php`
6. `lib/Controller/MessagesController.php`（删除响应 `remoteMissing`/`missing`/error）
7. `lib/Controller/ThreadController.php`（删除响应同上）
8. `lib/Service/MessageBodyStorage.php`
9. `lib/Service/RealtimeMessagePublisher.php`
10. `realtime/server.php`
11. `realtime/deploy/yoomail.service.template`
12. `deploy.sh`
13. `templates/settings-admin.php`
14. `templates/settings-personal-notifications.php`
15. `src/`（前端源码，含删除提示逻辑）
16. `src/main.js`（时间格式 `applyTimeFormat`、时区、应用重命名等初始化定制）
17. `src/global-notifier.js`（全局通知运行时与顶部 app 图标点标记）
18. `lib/Listener/GlobalNotifierAssetsListener.php`
19. `templates/settings-personal.php`
20. `js/yoomail.js`（由 `src/` 重新构建）
21. `js/yoomail-site-runtime-v5.js`（由 `src/global-notifier.js` 重新构建）
22. `js/admin-basic-settings.js`
23. `js/personal-notification-settings.js`

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
