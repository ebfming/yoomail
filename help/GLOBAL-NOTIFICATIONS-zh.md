<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail 全局通知

> [English](GLOBAL-NOTIFICATIONS.md)

本文档记录 `b-2026.08.30` 当前全局通知实现。

## 作用范围

- 通知只作用于当前 Nextcloud 站点的同源页面。
- YooMail 不做泛域名监听。例如 `office.example.com` 和 `beta.office.example.com` 是两个独立浏览器 origin。
- 只要浏览器中打开了任意一个当前 Nextcloud 站点的已登录页面,通知即可工作。
- 如果当前 Nextcloud 站点的所有标签页都关闭,浏览器侧声音、右下角弹窗和顶部图标标记都无法立即展示。

## 用户通知通道

- 新邮件浏览器原生通知。
- 新邮件、发送成功、发送失败声音提醒。
- 右下角 YooMail 弹窗提醒。
- Nextcloud 顶部 YooMail app 图标点标记。

顶部 app 图标有意只做“点标记”,不做 `1-n` 数字徽章。这样可以避免再维护一套未读数口径,稳定性更好。

## 运行时设计

- `lib/Listener/GlobalNotifierAssetsListener.php` 在已登录用户页面注入 `yoomail-site-runtime-v5`。
- `webpack.common.js` 从 `src/global-notifier.js` 构建该运行时。
- 运行时会在同源浏览器标签中选出一个 WebSocket leader。
- leader 调用 `/apps/yoomail/api/realtime/token` 获取连接信息,然后连接配置好的 `wsUrl`。
- 只有 `mailboxRole=inbox` 的实时 payload 会触发新邮件通知。
- 新邮件通知按 mailbox/message 标识做短时间去重,避免多个标签或重复同步造成重复提醒。

## 跨标签行为

- 同一浏览器同一站点只保留一个 WebSocket 连接。
- leader 通过 `localStorage` 事件把通知 payload 广播给其他同源标签页。
- 广播数据写入后会很快清理,避免发件人/标题等元数据长期保留在浏览器持久存储中。
- 顶部 app 图标标记只保存布尔状态和时间戳,不保存邮件内容。

## 安全说明

- realtime token 接口仍受同源和 CSRF token 保护。
- 运行时不会把 WebSocket token 写入 `localStorage`。
- 通知内容会展示发件人和标题,这是用户可见功能本身;但这些信息不应长期保存在持久存储里。
- 个人通知设置 initial state 输出 JSON 时使用 `JSON_HEX_*` 转义,避免 script 标签逃逸风险。

## 主题与 UI

- 顶部 app 图标标记使用 Nextcloud 主题变量,包括 `--color-primary-element`、`--color-main-background`、`--color-main-text`。
- 点标记较小,带轻微脉冲,并支持浏览器 reduced-motion 偏好。
- `img/yoomail.svg` 与 `img/yoomail-dark.svg` 使用同一套 YooMail 信封识别,顶部 app 菜单与设置页保持一致。

## 测试方式

1. 打开目标 Nextcloud 站点任意已登录页面,例如 Files。
2. 确认个人 YooMail 通知设置中打开需要测试的通道。
3. 向 realtime 监听的 INBOX 发送一封新邮件。
4. 确认开启的通知通道只触发一次。
5. 确认打开或点击 YooMail 后顶部 app 图标点标记会清除。

## 已知边界

- 当前不实现数字未读徽章。
- 声音播放受浏览器自动播放策略限制,通常需要用户先与当前站点交互一次。
- 浏览器原生通知需要用户授权。
- 后台标签页节流由浏览器控制;当前设计会尽量保持一个 leader 标签连接,但不能绕过浏览器或系统省电策略。
