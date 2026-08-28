<!--
  - SPDX-FileCopyrightText: 2026 YooMail contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# 富文本图片对齐

## 范围

本文记录 `b-2026.08.21` 对富文本编辑器图片对齐能力的调整。

本期已实现:

- 共享 CKEditor 工具栏支持块级图片对齐。
- 支持左对齐、居中、右对齐。
- 保存草稿或发送邮件前,对 HTML 正文中的图片对齐样式做归一化。

本期未实现:

- 图片和文字在同一行环绕排版。
- 图片与周围文字的上 / 中 / 下垂直对齐。
- 类似桌面邮件客户端的高级图文混排模式。

## 实现说明

YooMail 的写信窗口和签名设置共用 `TextEditor` 组件。当前只启用
CKEditor `ImageStyle` 插件中最小范围的块级图片按钮:

- `imageStyle:alignBlockLeft`
- `imageStyle:block`
- `imageStyle:alignBlockRight`

CKEditor 会把图片样式保存为 `figure.image` 上的 CSS class。YooMail 在
保存草稿或发送 HTML 正文前,通过 `normalizeImageAlignment()` 将关键
对齐效果补充为内联 `style`,降低其他邮件客户端对 CKEditor CSS 的依赖。

## 兼容性说明

邮件客户端对 CSS 的支持并不一致。本期刻意不处理文字环绕和垂直对齐,
因为这些能力需要在 Outlook、Gmail、QQ 邮箱、移动端 IMAP 客户端等环境
中单独验证。

如果后续要实现图文同行或环绕排版,应独立于编辑器稳定性修复单独开发和
测试。
