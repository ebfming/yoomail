<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail

**🚀 A high-performance mail app for [Nextcloud](https://nextcloud.com)**

YooMail is a secondary development (fork) of [Nextcloud Mail](https://github.com/nextcloud/mail) 5.10.12, focused on **realtime delivery** and **instant reading** of emails.

> [中文版 README](README-zh.md) · [Changelog](CHANGELOG.md) · [Development notes](help/DEVELOPMENT.md)

## Highlights

### ⚡ Realtime email delivery
- Built-in **Workerman + IMAP IDLE** daemon, deeply integrated with Nextcloud
- New emails are pushed **within seconds** — no manual refresh or polling required
- The frontend automatically updates the mailbox list via WebSocket

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

## Version info

| Item | Value |
|------|-------|
| YooMail version | 0.1.0 |
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

### 2. Deploy the realtime service (optional, enables realtime delivery)

The realtime service is a systemd unit. Use the one-click deploy script (run as root):

```bash
sudo bash deploy.sh                  # auto-detect the Nextcloud directory
sudo bash deploy.sh /path/to/nextcloud   # or specify it explicitly
```

The script will:
1. Check that it is running as root
2. Show the detected Nextcloud directory and ask for confirmation
3. Auto-detect the PHP user (owner of `config/config.php`, e.g. `www-data`/`apache`)
4. Generate and install `/etc/systemd/system/yoomail.service`
5. Enable and start the service

For non-interactive (CI) use: `YOOMAIL_ASSUME_YES=1 sudo bash deploy.sh <nc_dir>`

### 3. Configure the nginx reverse proxy (WebSocket)

Add the following to your nginx config (see `realtime/deploy/nginx-yoomail-ws.conf.snippet`):

```nginx
location /yoomail-ws {
    proxy_pass http://127.0.0.1:8789;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 3600;
    proxy_send_timeout 3600;
}
```

### 4. Realtime service configuration (optional)

Realtime service settings are stored as app configuration (in the `oc_appconfig` database table) and can be changed via the admin settings page. The keys and their defaults:

| Key | Default | Description |
|-----|---------|-------------|
| `yoomail.realtime.ws_host` | `127.0.0.1` | WebSocket listen host |
| `yoomail.realtime.ws_port` | `8789` | WebSocket port |
| `yoomail.realtime.ipc_port` | `8790` | IPC port |
| `yoomail.realtime.channel_port` | `2207` | Internal channel port |
| `yoomail.realtime.ws_public_url` | *(empty)* | If proxied via a separate domain, e.g. `wss://mail.example.com/yoomail-ws` |
| `yoomail.realtime.idle_refresh_seconds` | `1500` | IMAP IDLE refresh interval |

> Changes take effect after restarting the realtime service: `sudo systemctl restart yoomail`

## Feedback & support

If you run into any issues or have suggestions for improvement, please report them via the issue tracker.

## License

This project is distributed under the [AGPL-3.0-only](https://www.gnu.org/licenses/agpl-3.0.html) license.
