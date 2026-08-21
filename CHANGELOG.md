<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# Changelog

YooMail is a fork of [Nextcloud Mail](https://github.com/nextcloud/mail).
All releases in this repository currently build on top of upstream
[5.10.12](https://github.com/nextcloud/mail/releases/tag/v5.10.12).

## [Unreleased]

### Fixed

- Fixed `Subscribe` / `Unsubscribe` mailbox actions returning HTTP 500.
  Some providers (such as Tencent ExMail / QQ Mail) reject IMAP
  `SUBSCRIBE` / `UNSUBSCRIBE`. YooMail now logs a warning, refreshes the
  mailbox list, and returns the current state instead of failing the request.
- Fixed mailbox sync returning HTTP 500 / 428 when a folder was deleted on
  the server (e.g. via another IMAP/webmail client) but a stale record
  remained locally. The synchronizer now verifies the mailbox with a LIST
  call, removes the stale local record, and returns an empty result so the
  folder disappears after the next mailbox-list refresh.
- Fixed initial mailbox sync failing with `Class "OCA\YooMail\IMAP\DateTime"
  not found`. The new `fetch_range_days` initial-sync filter used an
  unqualified `new DateTime(...)` which resolved to a non-existent class in
  the `OCA\YooMail\IMAP` namespace; the global `DateTime` class is now
  imported explicitly.

### Changed

- Standardized code comments and script prompts in English, and added the
  required l10n entries for new user-facing strings.
- Switched `appinfo/info.xml` `description` to English for App Store
  publication.
- Added and updated developer-facing docs:
  `help/CODE-STANDARDS.md`, `CONTRIBUTING.md`, and `SECURITY.md`.
- Recorded maintainer metadata consistently as `adam <dev@ebf.hk>`.
- Clarified PHPDoc and strict-typing expectations, including branch-based
  `@version` tags for YooMail-specific PHP classes.

## [0.1.0] - 2026-08-13

### Added

- Added realtime delivery through the `realtime/` service
  (Workerman + IMAP IDLE + WebSocket), with automatic list refresh in the
  browser.
- Added persistent message body caching through `MessageBodyStorage` for
  near-instant reopen performance.
- Added compatibility fixes for some Chinese mail providers whose localized
  special-use folders could trigger repeated UIDVALIDITY cache resets.
- Added the product README, changelog, and deployment guide
  (`help/DEPLOYMENT.md`).

### Changed

- Renamed the app to **YooMail**, independent from the upstream `mail` app.
- Renamed the PHP namespace from `OCA\Mail` to `OCA\YooMail`.
- Renamed database tables from `mail_*` to `yoomail_*`, keeping data isolated
  from upstream Mail.
- Renamed occ commands from `mail:*` to `yoomail:*`
  (for example `yoomail:account:sync`).
- Assigned dedicated realtime service ports
  (`WS 8789 / IPC 8790 / Channel 2207`) so YooMail can coexist with the
  upstream realtime stack.

### Upstream base

- Based on Nextcloud Mail **5.10.12**, preserving upstream mail features such
  as multiple accounts, threading, encryption, S/MIME, and integration with
  Contacts, Calendar, and Files.

[0.1.0]: https://github.com/tigersprite/yoomail/releases/tag/v0.1.0
