<!--
  - SPDX-FileCopyrightText: 2026 YooMail contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Rich Text Image Alignment

## Scope

This document records the `b-2026.08.21` rich-text editor change for image
alignment.

Implemented:

- Block image alignment in the shared CKEditor toolbar.
- Supported choices: left, center, and right.
- Outgoing HTML normalization before saving drafts or sending messages.

Not implemented in this phase:

- Inline image and text flow on the same line.
- Vertical alignment between images and surrounding text.
- Advanced image wrapping modes comparable to desktop mail clients.

## Implementation Notes

YooMail uses the shared `TextEditor` component for the composer and signature
settings. The editor now enables CKEditor's `ImageStyle` plugin with only the
minimal block-image style buttons:

- `imageStyle:alignBlockLeft`
- `imageStyle:block`
- `imageStyle:alignBlockRight`

CKEditor stores these styles as CSS classes on `figure.image`. Before the HTML
body is saved or sent, YooMail normalizes those classes into critical inline
styles through `normalizeImageAlignment()`. This keeps outgoing messages less
dependent on CKEditor CSS being available in other mail clients.

## Compatibility Notes

Email clients have uneven CSS support. This phase intentionally avoids text
wrapping and vertical alignment because those features require broader testing
across clients such as Outlook, Gmail, QQ Mail, and mobile IMAP clients.

If future work adds inline images or wrapping, it should be implemented and
tested separately from editor stability fixes.
