<!--
  - SPDX-FileCopyrightText: 2026 TigerSprite Team
  - SPDX-License-Identifier: AGPL-3.0-only
-->
# YooMail Global Notifications

> [中文版本](GLOBAL-NOTIFICATIONS-zh.md)

This document records the current global notification implementation for `b-2026.08.30`.

## Scope

- Notifications are scoped to the current Nextcloud origin.
- YooMail does not listen across wildcard domains. For example, `office.example.com` and `beta.office.example.com` are independent browser origins.
- Notifications work only while at least one authenticated page from the same Nextcloud site is open in the browser.
- If all tabs for this Nextcloud site are closed, browser-side sound, toast, and top app icon markers cannot be shown immediately.

## User Channels

- Native browser notification for new mail.
- Sound notification for new mail, send success, and send failure.
- YooMail toast in the bottom-right corner.
- Top app icon marker in the Nextcloud header.

The top app icon marker is intentionally a dot marker, not a numeric unread badge. This avoids a second unread-count source of truth and keeps the feature stable.

## Runtime Design

- `lib/Listener/GlobalNotifierAssetsListener.php` injects `yoomail-site-runtime-v5` on authenticated user pages.
- `webpack.common.js` builds the runtime from `src/global-notifier.js`.
- The runtime elects one same-origin browser tab as the WebSocket leader.
- The leader connects to `/apps/yoomail/api/realtime/token`, then opens the configured `wsUrl`.
- Only realtime payloads for `mailboxRole=inbox` can trigger new-mail notifications.
- New mail notifications are deduplicated by mailbox/message identifiers for a short time window.

## Cross-Tab Behavior

- One tab owns the WebSocket connection.
- The leader broadcasts notification payloads to other same-origin tabs through `localStorage` events.
- Broadcast entries are removed shortly after writing to avoid keeping sender/subject metadata in persistent browser storage.
- The top app icon marker stores only a boolean active state and timestamp.

## Security Notes

- The realtime token endpoint remains CSRF-protected and same-origin.
- The runtime does not expose tokens in `localStorage`.
- The visible notification body contains sender and subject because that is the user-facing feature; persistent storage should not keep those details longer than necessary.
- Personal notification initial state is rendered as JSON with `JSON_HEX_*` escaping to prevent script-tag breakouts.

## Theme And UI

- The top app icon marker uses Nextcloud CSS variables such as `--color-primary-element`, `--color-main-background`, and `--color-main-text`.
- The marker is a small dot with a subtle pulse and supports reduced-motion preferences.
- `img/yoomail.svg` and `img/yoomail-dark.svg` share the same YooMail envelope identity and are used consistently by the top app menu and settings views.

## Testing

1. Open any authenticated page on the target Nextcloud site, such as Files.
2. Ensure the personal YooMail notification settings enable the desired channels.
3. Send a new message to an INBOX watched by realtime sync.
4. Confirm the enabled channels trigger only once per new message batch.
5. Confirm the top app icon marker clears after opening or clicking YooMail.

## Known Limitations

- No numeric unread badge is implemented.
- Browser sound playback depends on browser autoplay policy and may require prior user interaction with the site.
- Native browser notifications require explicit browser permission.
- Background-tab throttling is browser-controlled; the design keeps one leader tab alive but cannot override browser or OS power policies.
