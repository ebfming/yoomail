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
├── src/              # Frontend source (Vue)
├── js/               # Frontend build output (yoomail.js etc.)
├── css/              # Global styles
├── l10n/             # Translations
├── realtime/         # Realtime service (Workerman + IMAP IDLE)
├── help/             # Docs
└── README.md         # Product README
```

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

## 4. Frontend source modifications

These are the main source-level changes compared to upstream.

### 4.1 App rename (mail → yoomail)

| File | Change |
|------|--------|
| `src/main.js` | `generateFilePath('mail',...)` → `'yoomail'`; `window.OCA.MailRealtime` → `OCA.YooMailRealtime`; added `moment.tz.setDefault()` |
| `src/realtime.js` | `/apps/mail/api/realtime/token` → `/apps/yoomail/api/realtime/token`; `OCA.MailRealtime` → `OCA.YooMailRealtime` |
| `src/init.js` | `loadState('mail',...)` → `'yoomail'`; added `timezone` and `time-format` preference storage |
| `src/errors/convert.js` | `OCA\Mail\Exception\...` → `OCA\YooMail\Exception\...` |
| `src/router.js` | router base → `generateUrl('/apps/yoomail/')` |
| `src/service/MessageService.js` | `fetchThread` URL moved from `apps/mail/...` to `apps/yoomail/...` |
| `src/components/AppSettingsMenu.vue` | `apps/mail/compose` → `apps/yoomail/compose` |
| `src/components/NavigationAccount.vue` | `generateUrl('/apps/mail')` → `generateUrl('/apps/yoomail')` |
| all `src/**/*.vue`, `src/**/*.js` | `t('mail', ...)` → `t('yoomail', ...)`; `n('mail',...)` → `n('yoomail',...)` |

### 4.2 Bug fixes

| File | Change |
|------|--------|
| `src/store/mainStore/actions.js` | fixed thread open race condition when mailbox is not loaded yet |
| `src/components/Thread.vue` | changed deleted-thread UX: warning text, retry flow, `thread-not-found` event |

### 4.3 Time display

| File | Change |
|------|--------|
| `src/util/userTimezone.js` | new helper for user timezone + 24/12h preference |
| `src/util/relativeDatetime.js` | relative time and day grouping use user timezone |
| `src/components/Moment.vue` | tooltip and display use user timezone |
| `src/components/ThreadEnvelope.vue` | `formattedSentAt` uses user timezone |

### 4.4 Theme / spacing

| File | Change |
|------|--------|
| `src/components/MessageHTMLBody.vue` | dark-mode compatible background and padding |
| `src/components/MessagePlainTextBody.vue` | added content padding |

### 4.5 Version display

| File | Change |
|------|--------|
| `src/components/AppSettingsMenu.vue` | About section shows `YooMail {version} ({internal-version})`; hides upstream footer version |
| `src/init.js` | stores `mailVersion` and `internalVersion` |
| `lib/Controller/PageController.php` | exposes `internalVersion` via initial state |

Important:

- parse `appinfo/info.xml` with `OC\App\InfoParser`
- do not use `simplexml_load_file` in web context for this field

## 5. Frontend build and packaging

After changing anything under `src/`:

```bash
npm ci
npm run build
```

Build output goes to `js/`.

Practical rule:

- `src/` is the real source of truth
- `js/` is the deployed artifact
- modifying `src/` without rebuilding `js/` means production will not get the change

## 6. Upgrade checklist

When rebasing onto a newer upstream Mail version, check these areas first:

1. `src/main.js`
2. `src/realtime.js`
3. `src/init.js`
4. `src/router.js`
5. `src/service/MessageService.js`
6. `src/store/mainStore/actions.js`
7. `src/components/Thread.vue`
8. `src/components/AppSettingsMenu.vue`
9. `lib/Controller/PageController.php`
10. `lib/Controller/RealtimeController.php`
11. `appinfo/routes.php`

Also verify:

1. frontend bundle still builds cleanly
2. `js/` was regenerated and replaced
3. all `/apps/mail/...` paths are still renamed to `/apps/yoomail/...`
4. all `t('mail', ...)` / `n('mail', ...)` calls are still under `yoomail`
5. realtime token route still exists
6. About page still shows the YooMail version, not upstream `5.10.12`

## 7. Release reminders

- keep `<version>` in `appinfo/info.xml` aligned with `CHANGELOG.md`
- bump `<internal-version>` on every internal release
- make sure `l10n/` and `js/` are up to date before packaging
- exclude `.git`, `node_modules`, and debug files from release packages
