<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail Upstream Diff And Upgrade Notes

This document records which source files YooMail changed relative to upstream [Nextcloud Mail](https://github.com/nextcloud/mail), plus the upgrade points that must be checked when rebasing or replacing frontend build output.

> [中文版本](UPSTREAM-DIFF-AND-UPGRADE-zh.md)

## 1. Project layout

YooMail is a fork of Nextcloud Mail 5.10.12 and keeps the upstream directory structure:

```text
yoomail/
├── appinfo/          # App metadata (info.xml, routes)
├── lib/              # PHP backend (namespace OCA\YooMail)
├── src/              # Frontend source (vue components, store, service) - rebuildable
├── js/               # Frontend build output (yoomail.js etc.)
├── css/              # Global styles
├── img/              # Icons and image assets
├── l10n/             # Translations
├── realtime/         # Realtime service (Workerman + IMAP IDLE)
├── help/             # Docs
├── package.json      # Frontend deps and build scripts
├── webpack.*.js      # Build config
├── tsconfig.json     # TS config
└── README.md         # Product README
```

The repository now ships the frontend source `src/` and build toolchain, so frontend
changes must be made in source and rebuilt (see section 5). The standalone scripts
(`yoomail-notifications.js`, `yoomail-realtime-delta.js`, `yoomail-list-cache.js`,
`personal-notification-settings.js`) are referenced directly by PHP templates and are
NOT part of the webpack build - do not let build output overwrite them.
The admin basic settings page is not standalone anymore: `js/admin-basic-settings.js`
must be generated from `src/admin-basic-settings.js` through webpack.
The global site notification runtime is also a webpack output:
`yoomail-site-runtime-v5.js` is generated from `src/global-notifier.js`.

## 2. Global differences from upstream

| Item | Upstream mail | YooMail |
|------|---------------|---------|
| App id | `mail` | `yoomail` |
| Namespace | `OCA\Mail` | `OCA\YooMail` |
| DB tables | `oc_mail_*` | `oc_yoomail_*` |
| occ commands | `mail:*` | `yoomail:*` |
| Frontend bundle | `mail.js` | `yoomail.js` |

YooMail runs independently from the upstream `mail` app and can coexist beside it.

## 3. Added features on top of upstream

### 3.1 Realtime email delivery

Located in `realtime/`:

- `server.php` — service entry
- `lib/ImapIdleManager.php` — collects accounts, starts IDLE listeners
- `lib/ImapIdleConnection.php` — IMAP IDLE long connection
- `lib/ImapIdleChild.php` — triggers sync and reports changes
- `lib/RealtimeSyncService.php` — runs `occ yoomail:account:sync`
- `lib/RealtimeServer.php` — orchestrates WebSocket / IPC / Channel workers
- `lib/WebSocketServer.php` — auth and event push

Flow:

- IDLE detects mailbox change
- `occ yoomail:account:sync` persists the delta
- IPC/Channel broadcasts the event
- WebSocket pushes to browser
- frontend refreshes the mailbox list

### 3.2 Message body caching

- `lib/Service/MessageBodyStorage.php` — persistent body cache
- `lib/Controller/MessagesController.php::getBody` — reads cache first, writes after first render

### 3.3 Special-folder UIDVALIDITY fix

- `lib/Service/Sync/ImapToDbSynchronizer.php::sync` — SELECTs mailbox before syncing
- avoids repeated cache wipe on some Chinese mail providers using localized special folders

### 3.4 Global notifications and top app icon marker

- `lib/Listener/GlobalNotifierAssetsListener.php` injects `yoomail-site-runtime-v5` into authenticated user pages.
- `src/global-notifier.js` keeps one same-origin WebSocket leader tab alive and broadcasts notification events to visible same-site tabs.
- `lib/Service/NotificationSettingsService.php` stores user notification channel preferences.
- `templates/settings-personal.php` renders the personal notification controls.
- `img/yoomail.svg` and `img/yoomail-dark.svg` provide the shared YooMail app icon identity.

See `help/GLOBAL-NOTIFICATIONS.md` for behavior, browser limitations, and security notes.

## 4. Frontend source modifications

These are the main frontend-level changes compared to upstream.

### 4.1 App rename (mail → yoomail)

| File | Change |
|------|--------|
| Frontend bundle | Mail app ids / routes renamed from `mail` to `yoomail`; realtime globals renamed to `OCA.YooMailRealtime`; timezone and time-format handling added |
| Error conversion layer | Backend exception names updated from `OCA\Mail\Exception\...` to `OCA\YooMail\Exception\...` |
| Message fetch routes | Thread/body/message URLs moved from `/apps/mail/...` to `/apps/yoomail/...` |
| UI strings | Frontend translations switched from `t('mail', ...)` / `n('mail', ...)` to `t('yoomail', ...)` / `n('yoomail', ...)` |

### 4.2 Bug fixes

| File | Change |
|------|--------|
| Thread state handling | fixed the race when opening a thread before its mailbox state was hydrated |
| Deleted-thread UX | added a safer retry flow and a dedicated `thread-not-found` event path |

### 4.2.1 Delete notification fix (b-2026.08.13)

The backend already distinguished delete outcomes:

- `{ remoteMissing: true, message: '...' }` - local deleted, remote likely gone (HTTP 200)
- `{ missing: true }` - no longer present locally (HTTP 200)
- `{ status: 'error', message: 'Could not delete "subject". Reason: ... Please contact your administrator.' }` (HTTP 500 + `x-mail-response` header)

Backend entry points: `lib/Controller/MessagesController.php::destroy`, `lib/Controller/ThreadController.php::delete`.

The frontend now consumes these:

- `src/store/mainStore/actions.js`: `deleteMessage` / `deleteThread` read the return value, detect `remoteMissing` and return `{ remoteMissing, message }`; on failure the backend `error.response.data.message` is surfaced.
- `src/service/ThreadService.js`: `deleteThread` returns `.data` (matching `MessageService.deleteMessage`).
- Components show a `showWarning` toast "remote message may already have been deleted" on success-with-`remoteMissing`, and otherwise display the backend's full reason (subject + reason + contact admin):
  - `src/components/ThreadEnvelope.vue` (`onDelete`)
  - `src/components/Envelope.vue` (`onDelete`)
  - `src/components/EnvelopeList.vue` (batch delete)
  - `src/components/Mailbox.vue` (keyboard-shortcut delete branch)

### 4.2.2 Time-format (12/24h) fix (b-2026.08.17)

Background: the admin can set a default time format (`time_format_default`, 12/24) and
`PageController` already exposes it via the `time-format` initial state (user preference
first, falling back to the admin default). But the frontend displayed mail timestamps with
moment's `LT`/`LLL`/`lll`, which are driven by the **browser locale** — the `time-format`
preference was never consumed, so changing the admin setting had no visible effect.

Fix (`src/main.js`):

- Added `applyTimeFormat()`: reads `loadState('yoomail', 'time-format', '24')` and calls
  `updateLocale` on the **`@nextcloud/moment`** instance, overriding the `longDateFormat`
  entries `LT`/`LLL`/`lll`/`LTS`:
  - `24` → `HH:mm`; `12` → `h:mm A`
- Note: `@nextcloud/moment` (moment-with-locales) and `moment-timezone` are **separate
  singletons** (verified with `import ncMoment from '@nextcloud/moment'`). The time format
  must be applied to the `@nextcloud/moment` instance; `moment.tz.setDefault` only sets the
  timezone on the moment-timezone instance.
- Module order: `import` hoisting ensures `@nextcloud/moment` sets its locale first, then
  `applyTimeFormat()` runs right after — correct ordering.

### 4.3 Time display

| File | Change |
|------|--------|
| Relative time formatting | now uses the user's Nextcloud timezone instead of the browser timezone |
| Time-format preference | 12/24h display now actually follows the `time-format` preference (`applyTimeFormat()` in `src/main.js`, see 4.2.2) |

### 4.4 Theme / spacing

| File | Change |
|------|--------|
| Message detail styling | dark-mode compatible message background and tighter body spacing |

### 4.5 Version display

| File | Change |
|------|--------|
| About panel | shows `YooMail {version} ({internal-version})` instead of the upstream footer version |
| Initial state | exposes both `mailVersion` and `internalVersion` to the frontend |

Important:

- parse `appinfo/info.xml` with `OC\App\InfoParser`
- do not use `simplexml_load_file` in web context for this field

## 5. Frontend build and packaging

The repository ships `src/` and the build toolchain (`package.json`, `webpack.common.js`,
`tsconfig.json`, `babel.config.js`), matching the upstream mail build (entry `src/main.js`,
output to `js/`, chunks named `yoomail.[name].[contenthash].js`).

```bash
cd <yoomail checkout root>
npm install
npm run build          # NODE_ENV=production webpack --config webpack.prod.js
```

Build output:

- `js/yoomail.js` (main entry) + `js/yoomail.<id>.<hash>.js` (lazy chunks)
- `js/oauthpopup.js`, `js/settings.js`, `js/htmlresponse.js`
- `js/admin-basic-settings.js` from `src/admin-basic-settings.js`
- `js/yoomail-site-runtime-v5.js` from `src/global-notifier.js`

Verify before building that `src/main.js` keeps the yoomail customizations
(`moment.tz.setDefault(loadState('yoomail', 'timezone', 'UTC'))`,
`applyTimeFormat()` for the 12/24h preference, and
`generateFilePath('yoomail', '', 'js/')`).

When deploying, replace only the webpack output (`yoomail.js`, `yoomail.*.js`,
`oauthpopup.js`, `settings.js`, `htmlresponse.js`, `admin-basic-settings.js`,
`yoomail-site-runtime-v5.js`
and their `.map`/`.LICENSE.txt`),
and keep the standalone scripts listed in section 1.

If the source tree is lost, it can be rebuilt from the `sourcesContent` of the deployed
`js/*.map` files - prefer the version containing `<template>` for `.vue` files over the
vue-loader compiled `var render = function` variant. `src/` for `b-2026.08.13` was
extracted this way (232 files).

## 6. Upgrade checklist

When rebasing onto a newer upstream Mail version, check these areas first:

1. `js/yoomail.js` and related split chunks
2. `lib/Controller/PageController.php`
3. `lib/Controller/RealtimeController.php`
4. `lib/Service/Sync/ImapToDbSynchronizer.php`
5. `lib/Service/MessageBodyStorage.php`
6. `appinfo/routes.php`
7. `src/main.js` (timezone, `applyTimeFormat` 12/24h, app-rename init logic)
8. `src/global-notifier.js` (global notification runtime and top app icon marker)
9. `lib/Listener/GlobalNotifierAssetsListener.php`
10. `templates/settings-personal.php`
11. `src/admin-basic-settings.js` / `js/admin-basic-settings.js` (admin basic settings and Gmail OAuth settings)

Also verify:

1. all `/apps/mail/...` paths are still renamed to `/apps/yoomail/...`
2. all `t('mail', ...)` / `n('mail', ...)` calls are still under `yoomail`
3. realtime token route still exists
4. the About page still shows the YooMail version, not upstream `5.10.12`
5. any externally rebuilt frontend assets were copied back into `js/`

## 7. Release reminders

- keep `<version>` in `appinfo/info.xml` aligned with `CHANGELOG.md`
- bump the `internal-version` comment in `appinfo/info.xml` on every internal release
- create official tags and App Store release archives from the merged `public` branch
- make sure `l10n/` and `js/` are up to date before packaging
- exclude `.git`, `node_modules`, and debug files from release packages
