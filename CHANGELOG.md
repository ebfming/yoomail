<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# Changelog

YooMail is a fork of [Nextcloud Mail](https://github.com/nextcloud/mail).
All releases in this repository currently build on top of upstream
[5.10.12](https://github.com/nextcloud/mail/releases/tag/v5.10.12).

## [Unreleased]

## [0.2.0] - 2026-09-06

### Added

- Added same-site global new-mail notifications with browser notification,
  sound, YooMail toast, and top app icon marker channels.
- Added YooMail admin settings for realtime mode, time format defaults, delete
  synchronization, cleanup policy, fetch range, and OAuth integration.
- Added richer composer image layout controls, including block alignment,
  inline image mode, and text-wrapping styles.

### Changed

- Hardened realtime delivery with signed local IPC messages, safer reconnects,
  improved worker recovery, and runtime logs under the Nextcloud data
  directory.
- Aligned Gmail and Microsoft OAuth callbacks to YooMail routes for standalone
  distribution.
- Updated release metadata, README, deployment notes, code standards, security
  notes, and upgrade documentation for public App Store preparation.

### Fixed

- Fixed notification settings persistence, initialization, and cross-tab
  delivery edge cases.
- Fixed stale message, stale folder, mailbox subscribe/unsubscribe, remote
  deletion, and initial sync failure cases.
- Fixed attachment links that still pointed to upstream Mail routes and could
  break downloads from cached message bodies.
- Fixed signature editor crashes and YooMail route HTTP 500 errors introduced
  during realtime hardening.

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

[0.2.0]: https://github.com/tigersprite/yoomail/releases/tag/v0.2.0
[0.1.0]: https://github.com/tigersprite/yoomail/releases/tag/v0.1.0
