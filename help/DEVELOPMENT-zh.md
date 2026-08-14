<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail 二次开发说明

本文档说明 YooMail 的二次开发方式,面向后续参与开发的开发者。

## 1. 项目结构

YooMail 是基于 [Nextcloud Mail](https://github.com/nextcloud/mail) 5.10.12 的二次开发产品,整体沿用上游目录结构:

```
yoomail/
├── appinfo/          # 应用元数据(info.xml、路由)
├── lib/              # PHP 后端(命名空间 OCA\YooMail)
├── src/              # 前端源码(Vue)
├── js/               # 前端构建产物(yoomail.js 等)
├── css/              # 全局样式
├── l10n/             # 翻译
├── realtime/         # 实时收信服务(Workerman + IMAP IDLE)
├── help/             # 开发文档
└── README.md         # 产品说明(英文)
```

## 2. 与上游的差异

| 项目 | 上游 mail | YooMail |
|------|-----------|---------|
| 应用 id | `mail` | `yoomail` |
| 命名空间 | `OCA\Mail` | `OCA\YooMail` |
| 数据库表 | `oc_mail_*` | `oc_yoomail_*` |
| occ 命令 | `mail:*` | `yoomail:*` |
| 前端 bundle | `mail.js` | `yoomail.js` |

重命名后与上游 `mail` 应用互不干扰,可同时安装运行。

## 3. 二次开发新增的功能

### 3.1 实时收信(Realtime)

位于 `realtime/` 目录,独立于 Nextcloud 运行:

- `server.php` — 服务入口(Workerman)
- `lib/ImapIdleManager.php` — 收集账号,启动 IDLE 监听
- `lib/ImapIdleConnection.php` — 单账号 IMAP IDLE 长连接
- `lib/ImapIdleChild.php` — 检测到变化后触发同步并上报 IPC
- `lib/RealtimeSyncService.php` — 调用 `occ yoomail:account:sync` 子进程同步
- `lib/RealtimeServer.php` — WebSocket / IPC / Channel worker 编排
- `lib/WebSocketServer.php` — WebSocket 认证与事件推送

**流程**:IDLE 检测到新邮件 → `occ yoomail:account:sync` 落库 → IPC 上报 → Channel 广播 → WebSocket 推送到浏览器 → 前端刷新列表。

### 3.2 正文缓存(MessageBodyStorage)

- `lib/Service/MessageBodyStorage.php` — 邮件正文持久化缓存
- `lib/Controller/MessagesController.php::getBody` — 先读缓存,未命中渲染后写入
- 首次渲染后存盘,二次打开秒开

### 3.3 特殊文件夹 UIDVALIDITY 修复

- `lib/Service/Sync/ImapToDbSynchronizer.php::sync` — 同步前先 SELECT 邮箱
- 修复中国部分邮箱(腾讯/QQ/网易等)对特殊文件夹(已发送/已删除/草稿箱)返回中文 UTF-7 名导致缓存反复清空的问题

## 4. 前台源码修改清单

> 前台源码位于 `src/` 目录(Vue 项目)。以下列出我们相对上游 Nextcloud Mail 修改过的**前台文件**及改动说明。

### 4.1 应用重命名相关

这些改动把应用从 `mail` 重命名为 `yoomail`,属于全局替换:

| 文件 | 改动 |
|------|------|
| `src/main.js` | `generateFilePath('mail',...)` → `'yoomail'`;`window.OCA.MailRealtime` → `OCA.YooMailRealtime`;新增 `moment.tz.setDefault()`(用户时区) |
| `src/realtime.js` | `generateUrl('/apps/mail/api/realtime/token')` → `/apps/yoomail/...`;`OCA.MailRealtime` → `OCA.YooMailRealtime` |
| `src/init.js` | `loadState('mail',...)` → `'yoomail'`;新增 `timezone`、`time-format` preference 存储 |
| `src/errors/convert.js` | 错误类型映射表 `OCA\Mail\Exception\...` → `OCA\YooMail\Exception\...`(7 处) |
| `src/router.js` | 路由 base `generateUrl('/apps/yoomail/')` |
| `src/service/MessageService.js` | `fetchThread` URL `apps/mail/api/messages/{id}/thread` → `apps/yoomail/...`(重命名遗漏修复) |
| `src/components/AppSettingsMenu.vue` | `apps/mail/compose` → `apps/yoomail/compose` |
| `src/components/NavigationAccount.vue` | `generateUrl('/apps/mail')` → `'/apps/yoomail'` |
| 全部 `src/**/*.vue`、`src/**/*.js` | `t('mail', ...)` → `t('yoomail', ...)`(1199 处)、`n('mail',...)` → `n('yoomail',...)` |

### 4.2 功能修复(Bug 修复)

| 文件 | 改动 |
|------|------|
| `src/store/mainStore/actions.js` | `addEnvelopeThreadMutation` 修复:mailbox 未加载时不再崩溃(线程打开时序 bug) |
| `src/components/Thread.vue` | 线程错误提示文案改为"该邮件可能已经被删除";新增"刷新并重试"按钮、`thread-not-found` 事件(自动返回列表) |

### 4.3 时间显示(用户时区 + 24 小时制)

| 文件 | 改动 |
|------|------|
| `src/util/userTimezone.js` | **新增**。读取用户时区(`core:timezone`)、时间格式偏好(`time-format`),提供 `formatInUserTimezone()`、`formatTimeOfDay()`、`is24Hour()` |
| `src/util/relativeDatetime.js` | 时间格式化改用用户时区 + 24 小时制(`timeOfDayFormat()`),"今天/昨天"分组边界也按用户时区计算 |
| `src/components/Moment.vue` | 时间标题用用户时区 + 24/12 小时制 |
| `src/components/ThreadEnvelope.vue` | `formattedSentAt` 用用户时区 |

### 4.4 明暗模式 + 内容间距

| 文件 | 改动 |
|------|------|
| `src/components/MessageHTMLBody.vue` | 容器背景 `#FFFFFF` → `var(--color-main-background)`(适配暗色);加 `padding` |
| `src/components/MessagePlainTextBody.vue` | `#message-container` 加 `padding-inline`(适配暗色) |

### 4.5 预留配置(后端配套)

| 后端文件 | 改动 |
|----------|------|
| `lib/Controller/PageController.php` | 通过 initial state 提供 `timezone`(用户 `core:timezone`)、`time-format`(默认 `'24'`) |

> 后续后台设置页可写入 `yoomail/time-format` 用户偏好(`'24'` 或 `'12'`)即可切换 24/12 小时制,前端自动生效。

### 4.6 关于页版本显示

邮件设置 → 关于 页显示完整的版本信息 `YooMail {version} ({internal-version})`,例如 `YooMail 0.1.0 (b-2026.08.13)`。

| 文件 | 改动 |
|------|------|
| `src/components/AppSettingsMenu.vue` | 关于节新增 `<p class="about-version">{{ versionText }}</p>`;新增 computed `versionText`(内部版本存在时显示 `YooMail {version} ({internal-version})`,否则 `YooMail {version}`);新增非 scoped 样式隐藏 `NcAppSettingsDialog` 自带 footer(默认重复显示 `YooMail 5.10.12`,`#app-settings-dialog [class*="appSettingsDialogVersion"] { display: none }`) |
| `src/init.js` | 存储 `mailVersion`(正式版本 `preferences['app-version']`)与 `internalVersion`(`internal-version` initial state)偏好 |
| `lib/Controller/PageController.php` | 通过 initial state 提供 `internalVersion`(`info.xml` 的 `internal-version`) |

> **后端解析 `info.xml` 必须用 `OC\App\InfoParser`**,不能用 `simplexml_load_file` —— Nextcloud 在 `lib/base.php` 调用了 `libxml_set_external_entity_loader()`,会导致 web 环境下 `simplexml_load_file` 返回空,`internal-version` 读不到。

## 5. 前端构建

前端是 Vue 项目。修改 `src/` 下的代码后需要重新构建:

```bash
npm ci
npm run build
```

构建产物输出到 `js/`,部署时需要同步到 Nextcloud 的 `yoomail/js/`。

> 运行 YooMail 本身不依赖 node;node 仅用于前端二次开发。

## 5. 实时服务部署

实时服务以 `config/config.php` 属主用户运行(通常为 `www-data`),通过 systemd 管理。使用一键部署脚本(以 root 运行):

```bash
sudo bash deploy.sh /path/to/nextcloud
```

脚本会检查 root、展示 Nextcloud 目录并请求确认、自动探测 PHP 运行用户(从 `config/config.php`)、安装 `/etc/systemd/system/yoomail.service` 并启动服务。

nginx 需为 WebSocket 增加 `/yoomail-ws` 反代(见 `realtime/deploy/nginx-yoomail-ws.conf.snippet`)。

## 6. 发布

- 版本号遵循 [SemVer](https://semver.org/),与 `appinfo/info.xml` 的 `<version>` 和 `CHANGELOG.md` 一致
- **内部版本号也要同步更新**:`appinfo/info.xml` 的 `<internal-version>`(形如 `b-2026.08.13`)每次发版必须同步递增,与 `<version>` 一同修改;它用于区分 YooMail 自有发布周期与上游 base 版本
- 发布前确保 `l10n/` 翻译文件已更新(`npm run build` 会打包 `l10n/*.js`)
- 发布包排除 `.git`、`node_modules`、调试文件等
