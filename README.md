<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail

**🚀 A high-performance mail app for [Nextcloud](https://nextcloud.com)**

YooMail is a secondary development (fork) of [Nextcloud Mail](https://github.com/nextcloud/mail) 5.10.12, focused on **realtime delivery** and **instant reading** of emails.

> [中文版 README](README-zh.md) · [Changelog](CHANGELOG.md) · [Deployment](help/DEPLOYMENT.md) · [Gmail integration](help/GMAIL-INTEGRATION.md) · [Global notifications](help/GLOBAL-NOTIFICATIONS.md) · [Upstream diff & upgrade notes](help/UPSTREAM-DIFF-AND-UPGRADE.md) · [Code standards](help/CODE-STANDARDS.md) · [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md)

## Highlights

### ⚡ Realtime email delivery
- Built-in **Workerman + IMAP IDLE** daemon, deeply integrated with Nextcloud
- New emails are pushed **within seconds** — no manual refresh or polling required
- The frontend automatically updates the mailbox list via WebSocket
- Optional site-wide browser notifications, sound, bottom-right toast, and a themed top app icon marker while any page from the same Nextcloud site is open

### 🚀 Instant body caching
- Message bodies are **persistently cached** after first render
- Reopening a message is nearly **instant**, greatly reducing repeated parsing overhead

### 📨 Compatibility with some Chinese mail providers
- **UIDVALIDITY compatibility fix** for special-use folders (Sent / Deleted / Drafts) on some Chinese mail providers (Tencent ExMail/QQ Mail, NetEase Mail / NetEase Enterprise Mail, etc.)
- Fixes the issue where special folders repeatedly get their cache wiped

### 📥 Full mail features (inherited from Nextcloud Mail)
- **Multiple accounts** — unified inbox for personal and work mail, connect any IMAP account
- **Message threads** — proper grouping by subject
- **Mailbox management** — create, edit, delete and manage submailboxes
- **Encryption support** — Mailvelope and S/MIME
- **Deep integration** — Contacts, Calendar, Files

## Differentiation vs upstream Mail

| Capability | Upstream Mail | YooMail | Value |
|------------|---------------|---------|-------|
| **Realtime delivery** | Polling (60s + cron) | **IMAP IDLE + WebSocket** (millisecond-level) | Core differentiator; highly visible in enterprise use |
| **Multi-client realtime sync** | Per-page level | Server push + list delta merge | Ahead of upstream |
| **Delete synchronization** | Single client | **Configurable two-way sync + `remoteMissing` hints** | Solves accidental multi-client deletion |
| **Admin operations & config** | Minimal | Time format / delete sync / cleanup / fetch range / realtime mode | Enterprise self-management needs |
| **Notifications** | Basic | Sound + toast + native + send feedback | Complete |
| **Body cache instant-open** | Renders on each open | **Persistent cache, near-instant reopen** | Faster reading experience |

## Version info

| Item | Value |
|------|-------|
| YooMail version | 0.2.0 |
| Internal version | b-2026.08.30 |
| Based on Nextcloud Mail | 5.10.12 |
| Supported Nextcloud | 32 – 35 |

## Installation

### 1. Install the app

**Most users**: search for **YooMail** in the Nextcloud "Apps" page and install it with one click (once the app is published on the App Store) — no manual steps required.

**Manual installation** (self-hosted / offline): place the `yoomail` directory into Nextcloud's `apps-extra/` (or `apps/`) directory, then enable it in the admin panel, or run:

```bash
# Debian / Ubuntu
sudo -u www-data php occ app:enable yoomail

# CentOS / RHEL
sudo -u apache php occ app:enable yoomail
```

> Note: `occ` must run as the owner of `config/config.php`. On Debian/Ubuntu this is usually `www-data`, on CentOS/RHEL usually `apache` — confirm with `ls -l config/config.php`.
>
> On first enable, the app automatically creates its `yoomail_*` database tables (separate from the original `mail_*` tables).

Deployment, realtime service installation, nginx reverse proxy, and upgrade/deploy operations are documented in [help/DEPLOYMENT.md](help/DEPLOYMENT.md).

## Feedback & support

If you run into any issues or have suggestions for improvement, please report them via the issue tracker.

## License

This project is distributed under the [AGPL-3.0-only](https://www.gnu.org/licenses/agpl-3.0.html) license.
