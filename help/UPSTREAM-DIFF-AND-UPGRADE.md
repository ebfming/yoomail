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

### 4.3 Time display

| File | Change |
|------|--------|
| Relative time formatting | now uses the user's Nextcloud timezone instead of the browser timezone |
| Time-format preference | supports a user-selectable 24h / 12h display preference |

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

At the moment this repository does not carry a checked-in `src/` tree or local
frontend build toolchain metadata. The deployed source of truth is therefore
the committed output under `js/`.

Practical rule:

- `js/` is currently the deployed frontend source in this repository
- when frontend source modules are maintained outside this checkout, upgrades
  must still regenerate and replace the matching `js/` assets here
- changing only PHP/CSS without verifying the related `js/` chunk can leave
  the browser on stale behavior

## 6. Upgrade checklist

When rebasing onto a newer upstream Mail version, check these areas first:

1. `js/yoomail.js` and related split chunks
2. `lib/Controller/PageController.php`
3. `lib/Controller/RealtimeController.php`
4. `lib/Service/Sync/ImapToDbSynchronizer.php`
5. `lib/Service/MessageBodyStorage.php`
6. `appinfo/routes.php`

Also verify:

1. all `/apps/mail/...` paths are still renamed to `/apps/yoomail/...`
2. all `t('mail', ...)` / `n('mail', ...)` calls are still under `yoomail`
3. realtime token route still exists
4. the About page still shows the YooMail version, not upstream `5.10.12`
5. any externally rebuilt frontend assets were copied back into `js/`

## 7. Release reminders

- keep `<version>` in `appinfo/info.xml` aligned with `CHANGELOG.md`
- bump `<internal-version>` on every internal release
- make sure `l10n/` and `js/` are up to date before packaging
- exclude `.git`, `node_modules`, and debug files from release packages
