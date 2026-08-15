<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# 参与 YooMail 开发

感谢您考虑为 YooMail 贡献代码！🚀

YooMail 是基于 [Nextcloud Mail](https://github.com/nextcloud/mail) 的二次开发产品。本指南说明如何高效地参与贡献。

> [English](CONTRIBUTING.md) · [代码规范](help/CODE-STANDARDS-zh.md) · [Changelog](CHANGELOG.md)

## 行为准则

请保持尊重与建设性。本项目遵循
[Nextcloud 行为准则](https://nextcloud.com/community/code-of-conduct/)。

## 报告 Bug

1. **先搜索** —— 检查已有 issue 和本仓库文档（`help/`），避免重复。
2. 创建 issue 时提供：
   - YooMail 版本（`appinfo/info.xml` 的 `<version>` 与 `<internal-version>`），
   - Nextcloud 版本，
   - 邮箱服务商 / IMAP 服务器（例如腾讯企业邮箱、QQ 邮箱、网易等），
   - 复现步骤，
   - 预期行为与实际行为，
   - `data/nextcloud.log` 中的相关日志片段（请脱敏凭据）。

> ⚠️ **安全漏洞**：请**不要**公开创建 issue，按 [SECURITY-zh.md](SECURITY-zh.md) 中的方式私密报告。

## 开发流程

### 1. 环境准备

- 部署本地 Nextcloud（参考[官方开发环境文档](https://docs.nextcloud.com/server/latest/developer_manual/getting_started/devenv.html)）。
- 将本应用放入 `apps-extra/yoomail`（或 `apps/yoomail`）并启用：

  ```bash
  sudo -u www-data php occ app:enable yoomail
  ```

- 实时服务对前端开发是可选依赖，见 `help/DEPLOYMENT-zh.md`。

### 2. 分支与提交

- 基于当前开发分支创建分支：`feature/<short-name>` 或 `fix/<short-name>`。
- 提交信息使用**英文**并遵循
  [Conventional Commits](https://www.conventionalcommits.org/)（见 `help/CODE-STANDARDS-zh.md` §8）。

### 3. 代码风格

动手前请阅读 `help/CODE-STANDARDS-zh.md`。要点：

- 注释与提交信息使用英文；
- `OCA\YooMail` 命名空间、PSR-12、`declare(strict_types=1)`；
- 用户可见文案走 l10n（`$l10n->t()` / `t('yoomail', ...)`），并在 `l10n/zh_CN.json` 中补充中文翻译；
- 每个文件带 SPDX 头；
- 日志中禁止硬编码凭据或敏感数据。

### 4. 构建前端（仅当修改了 `src/` 时）

```bash
npm ci
npm run build
```

构建产物提交到 `js/`。不要提交 `node_modules/`。

### 5. 测试

- 至少在一个 IMAP 服务器上测试（理想情况是"标准"服务器加一个中国邮箱服务商，如腾讯企业邮箱 / QQ 邮箱，以覆盖兼容性修复）。
- 对修改的 PHP 文件执行 `php -l`，对修改的 shell 脚本执行 `bash -n`。
- 如果改动了 `realtime/`，请验证实时收信流程。

### 6. 提交 Pull Request

- 关联你修复的 issue。
- 说明做了什么和为什么（而不只是怎么做）。
- 保持 PR 聚焦；不相关的改动拆分为独立 PR。
- 等待评审；根据反馈追加提交（共享分支上不要强制推送改写历史）。

## 文档

面向用户和开发者的文档采用**中英双语成对**维护（`NAME.md` 英文 + `NAME-zh.md` 中文）。当你改变行为、部署步骤或升级注意事项时，请同时更新两个版本。
