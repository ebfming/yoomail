<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail Development Notes

This document describes how YooMail is developed on top of [Nextcloud Mail](https://github.com/nextcloud/mail), for developers who want to contribute.

> [中文开发文档](DEVELOPMENT-zh.md)

## 1. Project layout

YooMail is a fork of [Nextcloud Mail](https://github.com/nextcloud/mail) 5.10.12 and keeps the upstream directory structure:

```
yoomail/
├── appinfo/          # App metadata (info.xml, routes)
├── lib/              # PHP backend (namespace OCA\YooMail)
├── src/              # Frontend source (Vue)
├── js/               # Frontend build output (yoomail.js etc.)
├── css/              # Global styles
├── l10n/             # Translations
├── realtime/         # Realtime service (Workerman + IMAP IDLE)
├── help/             # Development docs
└── README.md         # Product README (English)
```

## 2. Differences from upstream

| Item | Upstream mail | YooMail |
|------|---------------|---------|
| App id | `mail` | `yoomail` |
| Namespace | `OCA\Mail` | `OCA\YooMail` |
| DB tables | `oc_mail_*` | `oc_yoomail_*` |
| occ commands | `mail:*` | `yoomail:*` |
| Frontend bundle | `mail.js` | `yoomail.js` |

YooMail runs independently from the upstream `mail` app — both can be installed side by side.

## 3. New features added on top of upstream

### 3.1 Realtime email delivery

Located in `realtime/`, runs independently of Nextcloud:

- `server.php` — service entry (Workerman)
- `lib/ImapIdleManager.php` — collects accounts, starts IDLE listeners
- `lib/ImapIdleConnection.php` — single-account IMAP IDLE long connection
- `lib/ImapIdleChild.php` — triggers sync and reports to IPC on change
- `lib/RealtimeSyncService.php` — runs `occ yoomail:account:sync` subprocess
- `lib/RealtimeServer.php` — orchestrates WebSocket / IPC / Channel workers
- `lib/WebSocketServer.php` — WebSocket auth and event push

**Flow**: IDLE detects a new message → `occ yoomail:account:sync` persists it → IPC report → Channel broadcast → WebSocket push → frontend refreshes the list.

### 3.2 Message body caching (MessageBodyStorage)

- `lib/Service/MessageBodyStorage.php` — persistent body cache
- `lib/Controller/MessagesController.php::getBody` — reads cache first, writes after first render
- First render persists the body; reopening is near-instant

### 3.3 Special-folder UIDVALIDITY fix

- `lib/Service/Sync/ImapToDbSynchronizer.php::sync` — SELECTs the mailbox before syncing
- Fixes some Chinese mail providers (Tencent/QQ/NetEase) whose special-use folders (Sent/Deleted/Drafts) use Chinese UTF-7 names, which previously caused the cache to be wiped repeatedly

## 4. Modified frontend source files

> The frontend lives in `src/` (Vue). Below are the files we changed compared to upstream Nextcloud Mail, with a short description.

### 4.1 App rename (mail → yoomail)

Global replacements to rename the app:

| File | Change |
|------|--------|
| `src/main.js` | `generateFilePath('mail',...)` → `'yoomail'`; `window.OCA.MailRealtime` → `OCA.YooMailRealtime`; added `moment.tz.setDefault()` (user timezone) |
| `src/realtime.js` | `generateUrl('/apps/mail/api/realtime/token')` → `/apps/yoomail/...`; `OCA.MailRealtime` → `OCA.YooMailRealtime` |
| `src/init.js` | `loadState('mail',...)` → `'yoomail'`; added `timezone` and `time-format` preferences |
| `src/errors/convert.js` | error type map `OCA\Mail\Exception\...` → `OCA\YooMail\Exception\...` (7 places) |
| `src/router.js` | router base `generateUrl('/apps/yoomail/')` |
| `src/service/MessageService.js` | `fetchThread` URL `apps/mail/api/messages/{id}/thread` → `apps/yoomail/...` (rename miss fix) |
| `src/components/AppSettingsMenu.vue` | `apps/mail/compose` → `apps/yoomail/compose` |
| `src/components/NavigationAccount.vue` | `generateUrl('/apps/mail')` → `'/apps/yoomail'` |
| All `src/**/*.vue`, `src/**/*.js` | `t('mail', ...)` → `t('yoomail', ...)` (1199 places), `n('mail',...)` → `n('yoomail',...)` |

### 4.2 Bug fixes

| File | Change |
|------|--------|
| `src/store/mainStore/actions.js` | `addEnvelopeThreadMutation` no longer crashes when the mailbox is not loaded yet (thread open race condition) |
| `src/components/Thread.vue` | thread error message changed to "This email may have been deleted"; added "Refresh and retry" button and `thread-not-found` event (auto-navigates back to the list) |

### 4.3 Time display (user timezone + 24h)

| File | Change |
|------|--------|
| `src/util/userTimezone.js` | **New**. Reads the user timezone (`core:timezone`) and time-format preference (`time-format`); provides `formatInUserTimezone()`, `formatTimeOfDay()`, `is24Hour()` |
| `src/util/relativeDatetime.js` | time formatting uses the user timezone and 24h (`timeOfDayFormat()`); Today/Yesterday grouping also uses the user timezone |
| `src/components/Moment.vue` | time title uses the user timezone and 24/12h |
| `src/components/ThreadEnvelope.vue` | `formattedSentAt` uses the user timezone |

### 4.4 Dark mode + content spacing

| File | Change |
|------|--------|
| `src/components/MessageHTMLBody.vue` | container background `#FFFFFF` → `var(--color-main-background)` (dark mode); added `padding` |
| `src/components/MessagePlainTextBody.vue` | `#message-container` added `padding-inline` (dark mode) |

### 4.5 Reserved configuration (backend counterpart)

| Backend file | Change |
|--------------|--------|
| `lib/Controller/PageController.php` | provides `timezone` (user `core:timezone`) and `time-format` (default `'24'`) via initial state |

> A future settings page can write the `yoomail/time-format` user preference (`'24'` or `'12'`) to switch between 24h/12h display; the frontend picks it up automatically.

### 4.6 About-page version display

Mail settings → About shows the full version as `YooMail {version} ({internal-version})`, e.g. `YooMail 0.1.0 (b-2026.08.13)`.

| File | Change |
|------|--------|
| `src/components/AppSettingsMenu.vue` | About section got a `<p class="about-version">{{ versionText }}</p>` row; new computed `versionText` (renders `YooMail {version} ({internal-version})` when an internal version exists, else `YooMail {version}`); added a non-scoped style hiding `NcAppSettingsDialog`'s built-in footer (which would otherwise repeat `YooMail 5.10.12`, via `#app-settings-dialog [class*="appSettingsDialogVersion"] { display: none }`) |
| `src/init.js` | stores `mailVersion` (release version from `preferences['app-version']`) and `internalVersion` (`internal-version` initial state) preferences |
| `lib/Controller/PageController.php` | provides `internalVersion` (`internal-version` from `info.xml`) via initial state |

> **The backend must parse `info.xml` with `OC\App\InfoParser`, not `simplexml_load_file`** — Nextcloud calls `libxml_set_external_entity_loader()` in `lib/base.php`, which makes `simplexml_load_file` return empty in a web context, so `internal-version` would not be readable.

## 5. Frontend build

The frontend is a Vue project. After changing code under `src/`, rebuild:

```bash
npm ci
npm run build
```

Build output goes to `js/` and must be synced to the Nextcloud `yoomail/js/` directory.

> Running YooMail does not require node; node is only needed for frontend development.

## 5. Deploying the realtime service

The realtime service runs as the owner of `config/config.php` (usually `www-data`), managed by systemd. Use the one-click deploy script (run as root):

```bash
sudo bash deploy.sh /path/to/nextcloud
```

The script checks for root, asks for confirmation with the detected Nextcloud directory, auto-detects the PHP user from `config/config.php`, installs `/etc/systemd/system/yoomail.service` and starts the service.

nginx needs a `/yoomail-ws` reverse proxy for WebSocket (see `realtime/deploy/nginx-yoomail-ws.conf.snippet`).

## 6. Releasing

- Version follows [SemVer](https://semver.org/) and must match between `appinfo/info.xml` and `CHANGELOG.md`
- **The internal version must be bumped too**: update `<internal-version>` in `appinfo/info.xml` (e.g. `b-2026.08.13`) on every release, alongside `<version>`; it distinguishes YooMail's own release cycle from the upstream base version
- Ensure `l10n/` translations are up to date (`npm run build` bundles `l10n/*.js`)
- Exclude `.git`, `node_modules`, debug files from the release package
