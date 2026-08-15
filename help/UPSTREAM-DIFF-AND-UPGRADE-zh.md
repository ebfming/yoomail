<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail 源码改动与升级注意事项

本文档记录 YooMail 相对上游 [Nextcloud Mail](https://github.com/nextcloud/mail) 修改了哪些源码文件，以及后续升级、合并上游版本、重新打包前端时必须注意的点。

## 1. 项目结构

YooMail 基于 Nextcloud Mail 5.10.12 二次开发，整体仍沿用上游目录结构：

```text
yoomail/
├── appinfo/          # 应用元数据(info.xml、路由)
├── lib/              # PHP 后端(命名空间 OCA\YooMail)
├── src/              # 前端源码(Vue)
├── js/               # 前端构建产物(yoomail.js 等)
├── css/              # 全局样式
├── l10n/             # 翻译
├── realtime/         # 实时收信服务(Workerman + IMAP IDLE)
├── help/             # 文档
└── README.md         # 产品说明
```

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

## 4. 前端源码修改清单

这部分是后续升级时最容易被覆盖的区域。

### 4.1 应用重命名（mail → yoomail）

| 文件 | 改动 |
|------|------|
| `src/main.js` | `generateFilePath('mail',...)` → `'yoomail'`；`window.OCA.MailRealtime` → `OCA.YooMailRealtime`；增加 `moment.tz.setDefault()` |
| `src/realtime.js` | `/apps/mail/api/realtime/token` → `/apps/yoomail/api/realtime/token`；`OCA.MailRealtime` → `OCA.YooMailRealtime` |
| `src/init.js` | `loadState('mail',...)` → `'yoomail'`；增加 `timezone` 与 `time-format` preference |
| `src/errors/convert.js` | `OCA\Mail\Exception\...` → `OCA\YooMail\Exception\...` |
| `src/router.js` | 路由 base 改为 `generateUrl('/apps/yoomail/')` |
| `src/service/MessageService.js` | `fetchThread` API 路径改为 `/apps/yoomail/...` |
| `src/components/AppSettingsMenu.vue` | `apps/mail/compose` → `apps/yoomail/compose` |
| `src/components/NavigationAccount.vue` | `generateUrl('/apps/mail')` → `generateUrl('/apps/yoomail')` |
| 全部 `src/**/*.vue`、`src/**/*.js` | `t('mail', ...)` / `n('mail', ...)` 改为 `yoomail` |

### 4.2 Bug 修复

| 文件 | 改动 |
|------|------|
| `src/store/mainStore/actions.js` | 修复 mailbox 尚未加载时的线程打开 race condition |
| `src/components/Thread.vue` | 调整“邮件可能已删除”的线程错误处理与重试流程 |

### 4.3 时间显示

| 文件 | 改动 |
|------|------|
| `src/util/userTimezone.js` | 新增：统一处理用户时区与 24/12 小时制 |
| `src/util/relativeDatetime.js` | 相对时间与分组边界改用用户时区 |
| `src/components/Moment.vue` | tooltip 与显示时间改用用户时区 |
| `src/components/ThreadEnvelope.vue` | `formattedSentAt` 改用用户时区 |

### 4.4 主题与间距

| 文件 | 改动 |
|------|------|
| `src/components/MessageHTMLBody.vue` | 暗色兼容背景与 padding |
| `src/components/MessagePlainTextBody.vue` | 补充正文 padding |

### 4.5 关于页版本显示

| 文件 | 改动 |
|------|------|
| `src/components/AppSettingsMenu.vue` | 关于页显示 `YooMail {version} ({internal-version})`，并隐藏上游 footer 版本 |
| `src/init.js` | 存储 `mailVersion` 与 `internalVersion` |
| `lib/Controller/PageController.php` | 通过 initial state 暴露 `internalVersion` |

特别注意：

- 读取 `appinfo/info.xml` 必须使用 `OC\App\InfoParser`
- 不要在 web 环境里用 `simplexml_load_file` 读取内部版本号

## 5. 前端构建与打包

只要修改了 `src/` 下任何文件，就必须重新构建：

```bash
npm ci
npm run build
```

规则要记住：

- `src/` 才是源码
- `js/` 是部署产物
- 只改 `src/` 不重新生成 `js/`，线上不会生效

## 6. 升级检查清单

后续如果合并更高版本上游 Mail，优先检查这些地方：

1. `src/main.js`
2. `src/realtime.js`
3. `src/init.js`
4. `src/router.js`
5. `src/service/MessageService.js`
6. `src/store/mainStore/actions.js`
7. `src/components/Thread.vue`
8. `src/components/AppSettingsMenu.vue`
9. `lib/Controller/PageController.php`
10. `lib/Controller/RealtimeController.php`
11. `appinfo/routes.php`

还要逐项确认：

1. 前端 bundle 还能正常构建
2. `js/` 构建产物已经重新生成并替换
3. 所有 `/apps/mail/...` 是否仍已改成 `/apps/yoomail/...`
4. 所有 `t('mail', ...)` / `n('mail', ...)` 是否仍已改成 `yoomail`
5. realtime token 路由是否仍存在
6. 关于页显示的是否仍是 YooMail 版本，而不是上游 `5.10.12`

## 7. 发版提醒

- `appinfo/info.xml` 里的 `<version>` 要和 `CHANGELOG.md` 对齐
- 每次内部发版都要同步更新 `<internal-version>`
- 打包前确认 `l10n/` 与 `js/` 已更新
- 发布包排除 `.git`、`node_modules`、`debug` 等非发布内容
