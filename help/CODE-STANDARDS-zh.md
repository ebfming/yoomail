<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail 代码规范

本文档定义 YooMail 项目的代码规范。所有贡献都必须遵守以下规则，除非在相关 issue/PR 讨论中达成特殊约定。

> [English](CODE-STANDARDS.md) · [贡献指南](../CONTRIBUTING-zh.md)

## 1. 语言

- **所有代码注释、提交信息、PR 描述和代码评审意见必须使用英文。** 这保证项目对国际贡献者友好，并与上游 Nextcloud 生态保持一致。
- **所有面向用户的文案必须以英文源字符串书写**，并走多语言（l10n）机制（见 §4）。禁止在用户可见消息中硬编码中文（或任何其他语言）。
- 文档采用**中英双语成对维护**（`*-zh.md` + `*.md`）。英文版为权威版本，中文版为忠实翻译。

## 2. PHP 编码风格

- 遵循 **PSR-12** 及上游 Nextcloud Mail 代码库的整体风格。
- 每个文件以 `declare(strict_types=1);` 和 SPDX 头开头（见 §7）。
- 使用 `OCA\YooMail` 命名空间，禁止使用 `OCA\Mail` 命名空间。
- 尽量使用**类型化属性和参数**；注入的服务优先使用构造器属性提升（`private readonly ...`）。
- 优先使用提前返回和守卫子句；方法保持短小聚焦。
- 不使用已弃用 API；优先使用 `OCP\` 公共接口而非 `OC\` 内部实现（例如 `OCP\IConfig`、`OCP\Server`、`Psr\Log\LoggerInterface`）。
- 静态分析：保持代码无明显 Psalm/PHPStan 错误。

## 3. 前端（Vue/JS）

- 组件位于 `src/`，构建产物提交到 `js/`。
- 使用 Nextcloud Vue 组件与设计系统（如 `var(--color-main-background)` 变量、默认网格基线等）。
- 所有用户可见文案必须使用全局 `t('yoomail', ...)` / `n('yoomail', ...)` 辅助函数——禁止硬编码字符串。
- Store 逻辑位于 `src/store/`；mutation 保持同步、action 异步。禁止在临时脚本中 patch store 方法。
- 优先使用公共 API（`OCA.YooMailRealtime`），避免使用私有全局变量。

## 4. 多语言（l10n）

- PHP 端：在控制器/服务中注入 `\OCP\IL10N` 并使用 `$l10n->t(...)`。
- JS 端：使用全局 `t()` / `n()` 函数，app id 为 `'yoomail'`。
- 翻译文件位于 `l10n/`（`*.json` 供 PHP 使用，`*.js` 供 JS bundle 使用）。
- 新增文案后，重新生成 JS 翻译文件：

  ```bash
  sudo -u www-data php occ l10n:createjs yoomail
  ```

- 新增源字符串时，同步在 `l10n/zh_CN.json` 中添加中文翻译，保证中文用户看到的文案不倒退。
- 不要把用户可见文案只放在日志消息里：日志消息不参与翻译，但必须使用英文书写。

## 5. 日志

- 使用注入的 `Psr\Log\LoggerInterface`——禁止在应用代码中使用 `error_log()`、`var_dump()` 或 `print_r()`。
- 始终传入携带有用标识的**上下文数组**（例如 `['accountId' => ..., 'mailboxId' => ...]`）。禁止记录密码、令牌或邮件内容。
- 级别约定：
  - `debug` — 详细内部状态；
  - `info` — 正常生命周期事件；
  - `warning` — 可恢复的异常（例如邮件服务器拒绝可选的 IMAP 命令）；
  - `error` — 破坏功能特性的失败。

## 6. 错误与异常

- 使用本应用的异常类：
  - `OCA\YooMail\Exception\ClientException` — 请求级、用户可恢复的错误；
  - `OCA\YooMail\Exception\ServiceException` — 后端失败；
  - 已有领域异常（`MailboxLockedException`、`IncompleteSyncException` 等）。
- 可能失败的控制器应使用 `#[TrapError]` 特性并返回正确的 HTTP 状态码。
- **优雅降级**：当可选的 IMAP 功能（例如邮箱 SUBSCRIBE/UNSUBSCRIBE）被服务器拒绝时，记录 warning 日志并继续，而不是让整个请求失败（参见 `MailManager::updateSubscription`）。

## 7. SPDX 头

每个源文件必须携带 SPDX 头：

```php
/**
 * SPDX-FileCopyrightText: 2026 TigerSprite Team
 * SPDX-License-Identifier: AGPL-3.0-only
 */
```

Shell 脚本使用 `#` 注释，XML 使用 `<!-- ... -->`，JS 使用块注释形式。

## 8. Git 工作流

- 分支命名：`feature/<short-name>`、`fix/<short-name>`、`chore/<short-name>`。
- 提交信息格式（英文）：

  ```
  <type>: <short summary>

  <可选的长描述，说明做了什么和为什么，而不是怎么做>
  ```

  类型：`feat`、`fix`、`refactor`、`docs`、`chore`、`test`、`style`、`perf`。
- 每个提交保持单一逻辑变更，不要混入无关修改。
- 禁止提交密钥、证书、私钥、`node_modules/`、`.git/` 或 `debug/` 产物。

## 9. 文档

- 面向用户/开发者的文档采用**中英双语成对**：`NAME.md`（英文）和 `NAME-zh.md`（中文）。
- `CHANGELOG.md` 遵循 [Keep a Changelog](https://keepachangelog.com/)；版本号必须与 `appinfo/info.xml` 一致。
- 文档索引：
  - 部署 / 实时服务：`help/DEPLOYMENT.md`
  - 上游差异与升级注意事项：`help/UPSTREAM-DIFF-AND-UPGRADE.md`
  - 代码规范（本文档）：`help/CODE-STANDARDS.md`

## 10. 应用元数据（`appinfo/info.xml`）

- `id` 必须为小写 ASCII + 下划线，与目录名一致。
- `version` 必须遵循 SemVer 并与 `CHANGELOG.md` 一致。
- 保持 `bugs`、`website`、`repository`、`dependencies` 及时更新。
- 发版时同时递增 `<version>` 和 `<internal-version>`（例如 `b-2026.08.13`），并在 `CHANGELOG.md` 中新增条目。

## 11. 安全

- 遵循上游 Nextcloud 安全指南（[Security](https://docs.nextcloud.com/server/latest/developer_manual/)）。
- 校验并清理所有用户输入；使用参数化查询（ORM mapper）以及 `purify` 处理 HTML 渲染。
- 禁止明文记录或存储凭据；账号密码使用 Nextcloud 的 `ICrypto`。
- 漏洞请私密报告（见 `SECURITY.md`），切勿公开在 issue 中。
