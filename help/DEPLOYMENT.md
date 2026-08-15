<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail Deployment

This document covers installation, realtime service deployment, reverse proxy, and upgrade/deployment operations.

> [中文版本](DEPLOYMENT-zh.md)

## 1. Install the app

Manual enable:

```bash
# Debian / Ubuntu
sudo -u www-data php occ app:enable yoomail

# CentOS / RHEL
sudo -u apache php occ app:enable yoomail
```

Notes:

- `occ` must run as the owner of `config/config.php`
- first enable creates independent `yoomail_*` tables

## 2. Realtime dependencies

```bash
cd apps-extra/yoomail/realtime
composer install --no-dev
```

## 3. Install the systemd service

Run from the app root:

```bash
sudo bash deploy.sh /path/to/nextcloud
```

The script will:

1. verify root privileges
2. confirm the detected Nextcloud root
3. auto-detect service user/group from `config/config.php`
4. auto-detect PHP CLI path
5. render and install `/etc/systemd/system/yoomail.service`
6. enable and start the service

Optional overrides:

- `YOOMAIL_ASSUME_YES=1`
- `YOOMAIL_SKIP_START=1`
- `YOOMAIL_SERVICE_USER=...`
- `YOOMAIL_SERVICE_GROUP=...`
- `YOOMAIL_PHP_BIN=...`

## 4. nginx reverse proxy

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

## 5. Common operations

```bash
sudo systemctl status yoomail
sudo journalctl -u yoomail -f
sudo systemctl restart yoomail
```

## 6. Config keys

Realtime config is stored in app config:

| Key | Default | Description |
|-----|---------|-------------|
| `yoomail.realtime.ws_host` | `127.0.0.1` | WebSocket host |
| `yoomail.realtime.ws_port` | `8789` | WebSocket port |
| `yoomail.realtime.ipc_port` | `8790` | IPC port |
| `yoomail.realtime.channel_port` | `2207` | Internal channel port |
| `yoomail.realtime.ws_public_url` | *(empty)* | public `wss://.../yoomail-ws` if proxied |
| `yoomail.realtime.idle_refresh_seconds` | `1500` | IMAP IDLE refresh interval |

After changes:

```bash
sudo systemctl restart yoomail
```

## 7. Upgrade / deploy checklist

When upgrading upstream Mail or re-deploying:

1. confirm `appinfo/routes.php` still contains the realtime token route
2. confirm `lib/Controller/RealtimeController.php` is still present
3. confirm frontend artifacts in `js/` are the YooMail build, not overwritten by upstream output
4. confirm `deploy.sh` and `realtime/deploy/yoomail.service.template` are still the YooMail versions
5. re-check nginx `/yoomail-ws` proxy after server migration

## 8. Current limits

- IDLE currently watches `INBOX`, `Trash`, and `Sent`
- other folders still rely on list refresh/sync
- password-auth accounts are the main supported target
- each watched mailbox consumes one IMAP long connection
