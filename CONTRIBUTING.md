<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# Contributing to YooMail

Thank you for considering contributing to YooMail! 🚀

YooMail is a secondary development (fork) of [Nextcloud Mail](https://github.com/nextcloud/mail).
This guide explains how to contribute effectively.

> [中文版](CONTRIBUTING-zh.md) · [Code Standards](help/CODE-STANDARDS.md) · [Changelog](CHANGELOG.md)

## Maintainers

- adam <dev@ebf.hk>

## Code of conduct

Be respectful and constructive. This project follows the
[Nextcloud Code of Conduct](https://nextcloud.com/community/code-of-conduct/).

## Reporting bugs

1. **Search first** — check existing issues and this repository's docs
   (`help/`) to avoid duplicates.
2. Create an issue with:
   - YooMail version (`appinfo/info.xml` `<version>` + `<internal-version>`),
   - Nextcloud version,
   - mail provider / IMAP server (e.g. Tencent ExMail, QQ Mail, NetEase, ...),
   - steps to reproduce,
   - expected vs. actual behavior,
   - relevant log excerpts from `data/nextcloud.log` (redact credentials).

> ⚠️ **Security vulnerabilities**: do **not** open a public issue. Report
> privately as described in [SECURITY.md](SECURITY.md).

## Development workflow

### 1. Set up

- Deploy a local Nextcloud (see the [official dev env docs](https://docs.nextcloud.com/server/latest/developer_manual/getting_started/devenv.html)).
- Place this app under `apps-extra/yoomail` (or `apps/yoomail`) and enable it:

  ```bash
  sudo -u www-data php occ app:enable yoomail
  ```

- The realtime service is optional for frontend work; see `help/DEPLOYMENT.md`.

### 2. Branch & commit

- Create a branch off the current development branch:
  `feature/<short-name>` or `fix/<short-name>`.
- Write **English** commit messages following
  [Conventional Commits](https://www.conventionalcommits.org/) (see
  `help/CODE-STANDARDS.md` §8).

### 3. Code style

Read `help/CODE-STANDARDS.md` before writing code. Highlights:

- English comments and commit messages,
- `OCA\YooMail` namespace, PSR-12, `declare(strict_types=1)`,
- user-facing strings via l10n (`$l10n->t()` / `t('yoomail', ...)`) with
  Chinese translation added to `l10n/zh_CN.json`,
- SPDX headers on every file,
- no hard-coded credentials or sensitive data in logs.

### 4. Build the frontend (only if you changed `src/`)

```bash
npm ci
npm run build
```

The bundles are committed to `js/`. Do not commit `node_modules/`.

### 5. Test

- Test against at least one IMAP server (ideally both a "standard" one and a
  Chinese provider such as Tencent ExMail / QQ Mail to cover the
  compatibility fixes).
- Run `php -l` on changed PHP files and `bash -n` on changed shell scripts.
- Verify the realtime flow if you touched `realtime/`.

### 6. Open a pull request

- Reference the issue you are fixing.
- Describe what and why (not only how).
- Keep the PR focused; split unrelated changes into separate PRs.
- Wait for review; address feedback with additional commits (no force-push
  rewrites on shared branches).

## Documentation

User- and developer-facing documents are maintained in **bilingual pairs**
(`NAME.md` English + `NAME-zh.md` Chinese). Update both when you change
behavior, deployment steps or upgrade notes.
