<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# 安全政策

YooMail 是基于 Nextcloud Mail 的二次开发产品。安全是整个 Nextcloud 生态的重中之重——感谢您帮助我们维护安全。

> [English](SECURITY.md)

## 支持的版本

安全修复应用于最新发布版本，并在可行的情况下应用于最新的内部构建。只有最新发布版本保证获得安全更新。

## 报告漏洞

**请勿为安全漏洞创建公开 issue。**

请通过私密渠道报告漏洞：如果应用已公开分发，可通过 Nextcloud HackerOne 项目；否则直接通过私密渠道（邮箱 / 私有仓库 issue）联系维护者。

报告时请包含：

- 受影响的 YooMail 版本（`appinfo/info.xml` 中的 `<version>` / `<internal-version>`）与 Nextcloud 版本；
- 漏洞描述及其影响；
- 复现步骤；
- 您拥有的任何概念验证（请脱敏凭据）。

维护者会确认收到报告、着手修复，并与您协调披露时间线。

## 本项目中的安全实践

- 所有用户输入均经过校验与清理；数据库访问走参数化查询 / ORM mapper。
- HTML 渲染经过净化（`OCA\YooMail\Service\HtmlPurify`）。
- 账号密码使用 Nextcloud 的 `ICrypto` 加密。
- 密钥（私钥、证书、密码）严禁提交到仓库或写入日志。
- 遵循 Nextcloud 安全指南：https://docs.nextcloud.com/server/latest/developer_manual/
