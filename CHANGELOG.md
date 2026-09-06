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

### Fixed

- Hardened personal notification settings initial-state rendering by escaping
  JSON with `JSON_HEX_*` flags, preventing script-tag breakout edge cases.
- Reduced persistent browser storage exposure for global notification metadata.
  Cross-tab new-mail payloads are now removed shortly after broadcast, and
  notification claim keys no longer fall back to storing serialized message
  objects.
- Fixed signature editor crashes when opening rich-text dropdowns such as text
  alignment. YooMail now guards CKEditor dropdown positioning when the editor
  cannot calculate a viewport position, and keeps empty signatures as strings
  instead of passing `null` into the shared editor component.
- Fixed YooMail page routes returning HTTP 500 after realtime hardening.
  The shared realtime auth helper now lives in the main app namespace, and
  `RealtimeController` no longer redeclares the parent controller request
  property.
- Fixed stale message thread handling when a message was deleted remotely.
  If the thread API returns `403` or an empty thread, the frontend now removes
  the stale local envelope, refreshes the mailbox with vanished-message repair,
  and returns to the message list instead of leaving an empty detail pane.
- Fixed notification settings being interpreted incorrectly when disabled
  values were submitted as booleans or common string forms. YooMail now stores
  notification switches as explicit `1` / `0` values and remains compatible
  with older `yes` / `no` settings.
- Fixed YooMail bottom-right notifications failing to initialize when the
  initial notification state was not available yet. The notification bridge now
  starts with safe defaults and refreshes user settings in the background.
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
- Fixed inline attachment URLs in HTML message bodies still pointing to the
  upstream Mail app route (`/apps/mail/api/messages/...`). YooMail now
  generates `yoomail.messages.downloadAttachment` links and automatically
  rewrites legacy cached body payloads and cached attachment metadata on read.
- Fixed attachment card downloads that could still use legacy Mail URLs from
  already-loaded browser state. The frontend now normalizes attachment URLs
  before direct downloads and calendar attachment imports.

### Changed

- Added same-site global new-mail notifications. While any authenticated page
  from the current Nextcloud site is open, YooMail can receive realtime INBOX
  events and trigger the configured browser notification, sound, toast, and top
  app icon marker channels.
- Added a themed top app icon marker for new mail. The marker is intentionally
  dot-only, follows Nextcloud theme variables, supports reduced-motion
  preferences, and clears when YooMail is opened.
- Unified the YooMail top app icon and settings icon identity with updated
  `yoomail.svg` and `yoomail-dark.svg` assets.
- Documented the global notification architecture, browser limitations,
  security boundaries, and upgrade notes in
  `help/GLOBAL-NOTIFICATIONS.md`.
- Added basic rich-text image and text-flow alignment for composed HTML
  messages. Images can now be converted between inline, text-wrapped left /
  right, and block left / center / right styles from the CKEditor toolbar, and
  YooMail inlines the critical alignment styles before saving or sending so
  outgoing mail does not depend on CKEditor CSS being present.
- Hardened realtime IPC delivery. IDLE workers and detached sync commands now
  HMAC-sign local IPC payloads, the realtime service rejects unsigned or
  expired IPC messages, and the IPC listener is bound to `127.0.0.1`.
- Improved realtime frontend reconnect behavior. The browser now refreshes the
  short-lived token before reconnecting, avoids duplicate WebSocket
  connections, and queues mailbox refreshes until the main store is available.
- Improved realtime worker resilience. IDLE workers no longer give up
  permanently after transient retry exhaustion, and repeated IDLE wake-ups for
  the same mailbox no longer fork duplicate background sync processes.
- Moved Workerman runtime and sync logs out of the app source tree. Runtime
  files now live under the Nextcloud data directory, with per-account /
  per-mailbox sync logs under `data/yoomail-realtime/sync/`.
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

[0.2.0]: https://github.com/tigersprite/yoomail/releases/tag/v0.2.0
[0.1.0]: https://github.com/tigersprite/yoomail/releases/tag/v0.1.0
