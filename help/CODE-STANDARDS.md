<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail Code Standards

This document defines the coding standards for the YooMail project. Every
contribution must follow these rules unless a specific exception is agreed
upon in the related issue/PR discussion.

> [中文版](CODE-STANDARDS-zh.md) · [Contributing Guide](../CONTRIBUTING.md)

## 1. Language

- **All code comments, commit messages, PR descriptions and code review
  comments must be written in English.** This keeps the project accessible to
  international contributors and matches the upstream Nextcloud ecosystem.
- **All user-facing strings must use English source strings** and go through
  the localization (l10n) mechanism (see §4). Never hard-code Chinese (or any
  other language) in user-visible messages.
- `README*.md` and documents under `help/` are maintained in **bilingual
  pairs** (`*-zh.md` + `*.md`). The English version is the source of truth;
  the Chinese version is a faithful translation.

## 1.1 Project maintainers

- Author: **adam**
- Contact: **dev@ebf.hk**

## 2. PHP coding style

- Follow **PSR-12** and the general style of the upstream Nextcloud Mail codebase.
- Use the `OCA\YooMail` namespace. Never use the `OCA\Mail` namespace.
- Use **typed properties and parameters** where possible; rely on constructor
  property promotion (`private readonly ...`) for injected services.
- Prefer early returns and guard clauses; keep methods small and focused.
- Do not use deprecated APIs; prefer the `OCP\` public interfaces over
  `OC\` internals (e.g. `OCP\IConfig`, `OCP\Server`, `Psr\Log\LoggerInterface`).
- Static analysis: keep the code free of obvious Psalm/PHPStan errors.

### 2.1 PHPDoc / DocBlock comments

All classes, methods and property documentation **must** use PHPDoc /
DocBlock style comments.

- Use the `/** ... */` format.
- Include `@param`, `@return`, `@throws` as needed.
- Use PHPDoc syntax compatible with PHPStan / Psalm
  (e.g. `int[]`, `array<string, mixed>`, `?string`, generics).
- **`@version` must be the current git branch name**, obtained via:

  ```bash
  git branch --show-current
  ```

  Example for a class on branch `b-2026.08.21`:

  ```php
  /**
   * Orchestrates the realtime service.
   *
   * @version b-2026.08.21
   */
  final class RealtimeServer { ... }
  ```

- Keep the summary line concise; document the *what and why*, not the *how*.

### 2.2 Strict typing

- **Enable strict types in all PHP files** where applicable:

  ```php
  <?php

  declare(strict_types=1);

  namespace OCA\YooMail\...;
  ```

- `declare(strict_types=1);` must appear **immediately after `<?php`** and
  **before** `namespace`, `use`, `class`, `function` and any other code.
- Use explicit parameter types, return types and property types wherever
  possible.
- Avoid relying on PHP implicit type coercion; convert explicitly
  (e.g. `(int)`, `(string)`, `filter_var`).
- Files inherited from upstream that still lack `declare(strict_types=1)`
  may be migrated opportunistically; new code must always enable it.

## 3. Frontend (Vue/JS)

- This repository currently ships the committed frontend bundles in `js/`.
  If source modules are reintroduced later, they must regenerate the
  corresponding deployed assets in `js/`.
- Use the Nextcloud Vue components and the design system (variables such as
  `var(--color-main-background)`, default grid baselines, etc.).
- All user-facing text must use the global `t('yoomail', ...)` / `n('yoomail', ...)`
  helpers — never hard-code strings.
- Store logic goes into `src/store/`; keep mutations synchronous and actions
  async. Do not patch store methods from ad-hoc scripts.
- Prefer the public API (`OCA.YooMailRealtime`) over private globals.

## 4. Localization (l10n)

- PHP: inject `\OCP\IL10N` into your controller/service and use `$l10n->t(...)`.
- JS: use the global `t()` / `n()` functions with `'yoomail'` as app id.
- Translation files live in `l10n/` (`*.json` for PHP, `*.js` for JS bundles).
- After adding new strings, regenerate the JS translation file:

  ```bash
  sudo -u www-data php occ l10n:createjs yoomail
  ```

- When adding a new source string, also add the Chinese translation to
  `l10n/zh_CN.json` so Chinese users keep seeing localized text.
- Never put user-visible text into log messages as the only copy: log messages
  are not translated, but they must be written in English.

## 5. Logging

- Use `Psr\Log\LoggerInterface` (injected) — never `error_log()`, `var_dump()`
  or `print_r()` in application code.
- Always pass a **context array** with useful identifiers (e.g.
  `['accountId' => ..., 'mailboxId' => ...]`). Never log passwords, tokens or
  message content.
- Levels:
  - `debug` — verbose internal state,
  - `info` — normal lifecycle events,
  - `warning` — recoverable anomalies (e.g. a mail server rejecting an
    optional IMAP command),
  - `error` — failures that break a feature.

## 6. Errors & exceptions

- Use the app's exception classes:
  - `OCA\YooMail\Exception\ClientException` — request-level, user-recoverable errors,
  - `OCA\YooMail\Exception\ServiceException` — backend failures,
  - existing domain exceptions (`MailboxLockedException`, `IncompleteSyncException`, ...).
- Controllers that can fail should use the `#[TrapError]` attribute and return
  proper HTTP status codes.
- **Graceful degradation**: when an optional IMAP feature (e.g. mailbox
  SUBSCRIBE/UNSUBSCRIBE) is rejected by the server, log a warning and continue
  instead of failing the whole request (see `MailManager::updateSubscription`).

## 7. SPDX headers

Every source file must carry a SPDX header:

```php
/**
 * SPDX-FileCopyrightText: 2026 TigerSprite Team
 * SPDX-License-Identifier: AGPL-3.0-only
 */
```

Shell scripts use `#` comments, XML uses `<!-- ... -->`, JS uses the block form.

## 8. Git workflow

- Branch naming: `feature/<short-name>`, `fix/<short-name>`, `chore/<short-name>`.
- Commit message format (English):

  ```
  <type>: <short summary>

  <optional longer description, what and why, not how>
  ```

  Types: `feat`, `fix`, `refactor`, `docs`, `chore`, `test`, `style`, `perf`.
- Keep each commit focused on one logical change; do not mix unrelated edits.
- Never commit secrets, certificates, private keys, `node_modules/`, `.git/`
  or `debug/` artifacts.

## 9. Documentation

- `README*.md` and documents under `help/` are written in **bilingual pairs**.
  Other repository-level docs default to English.
- `CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/);
  version numbers must match `appinfo/info.xml`.
- Pointers:
  - Deployment / realtime service: `help/DEPLOYMENT.md`
  - Gmail integration: `help/GMAIL-INTEGRATION.md`
  - Upstream diff & upgrade notes: `help/UPSTREAM-DIFF-AND-UPGRADE.md`
  - Code standards (this file): `help/CODE-STANDARDS.md`

## 10. App metadata (`appinfo/info.xml`)

- `id` must be lowercase ASCII + underscores, matching the directory name.
- `version` must follow SemVer and match `CHANGELOG.md`.
- Keep `bugs`, `website`, `repository` and `dependencies` up to date.
- When cutting a release, bump both `<version>` and `<internal-version>`
  (e.g. `b-2026.08.13`) and add a `CHANGELOG.md` entry.

## 11. Security

- Follow the upstream Nextcloud security guidelines
  ([Security](https://docs.nextcloud.com/server/latest/developer_manual/)).
- Validate and sanitize all user input; use parameterized queries (the ORM
  mappers) and `purify` for HTML rendering.
- Never log or store credentials in plain text; Nextcloud's `ICrypto` is used
  for account passwords.
- Report vulnerabilities privately (see `SECURITY.md`), never in public issues.
