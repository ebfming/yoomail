<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# Security Policy

YooMail is a secondary development (fork) of Nextcloud Mail. Security is a
priority for the whole Nextcloud ecosystem — thank you for helping us keep it
safe.

## Supported versions

Security fixes are applied to the latest release and, where feasible, to the
most recent internal build. Only the latest release is guaranteed to receive
security updates.

## Reporting a vulnerability

**Do not open a public issue for security vulnerabilities.**

Please report vulnerabilities privately to the Nextcloud HackerOne program
(if the app is publicly distributed) or directly to the maintainers via a
private channel (email / private repository issue).

When reporting, include:

- affected YooMail version (`<version>` / `<internal-version>` from
  `appinfo/info.xml`) and Nextcloud version,
- a description of the vulnerability and its impact,
- steps to reproduce,
- any proof-of-concept you have (redact credentials).

The maintainers will acknowledge your report, work on a fix, and coordinate a
disclosure timeline with you.

## Security practices in this project

- All user input is validated and sanitized; database access goes through
  parameterized queries / ORM mappers.
- HTML rendering is purified (`OCA\YooMail\Service\HtmlPurify`).
- Account passwords are encrypted with Nextcloud's `ICrypto`.
- Secrets (private keys, certificates, passwords) must never be committed to
  the repository or written to logs.
- Follow the Nextcloud security guidelines:
  https://docs.nextcloud.com/server/latest/developer_manual/
