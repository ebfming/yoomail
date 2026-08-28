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
- 支持同行图片模式,图片可以和文字处于同一行。
- 同行图片默认与相邻文字做垂直居中对齐。
- 支持图片左 / 右浮动,文字围绕图片排版。
- 保存草稿或发送邮件前,对 HTML 正文中的图片对齐样式做归一化。

本期未实现:

- 图片与周围文字的上 / 中 / 下垂直对齐。
- 类似桌面邮件客户端的高级图文混排模式。

## 实现说明

YooMail 的写信窗口和签名设置共用 `TextEditor` 组件。当前启用
CKEditor `ImageStyle` 插件中有限范围的图片排版按钮:

- `imageStyle:inline`
- `imageStyle:alignLeft`
- `imageStyle:alignRight`
- `imageStyle:alignBlockLeft`
- `imageStyle:block`
- `imageStyle:alignBlockRight`

CKEditor 会把图片样式保存为 `figure.image` 上的 CSS class。YooMail 在
保存草稿或发送 HTML 正文前,通过 `normalizeImageAlignment()` 将关键
对齐效果补充为内联 `style`,降低其他邮件客户端对 CKEditor CSS 的依赖。

## 兼容性说明

邮件客户端对 CSS 的支持并不一致。本期刻意不处理图片与文字的上 / 中 /
下垂直对齐,因为这些能力需要在 Outlook、Gmail、QQ 邮箱、移动端 IMAP
客户端等环境中单独验证。

如果后续要实现图片与文字的上 / 中 / 下垂直对齐,应独立于编辑器稳定性
修复单独开发和测试。
