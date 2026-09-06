<!--
  - SPDX-FileCopyrightText: 2026 YooMail contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Rich Text Image Alignment

## Scope

This document records the `b-2026.08.30` rich-text editor change for image
alignment.

Implemented:

- Block image alignment in the shared CKEditor toolbar.
- Supported choices: left, center, and right.
- Inline image mode so images can stay in the same text line.
- Inline images are vertically centered with adjacent text by default.
- Text wrapping around images aligned left or right.
- Outgoing HTML normalization before saving drafts or sending messages.

Not implemented in this phase:

- Vertical alignment between images and surrounding text.
- Advanced image wrapping modes comparable to desktop mail clients.

## Implementation Notes

YooMail uses the shared `TextEditor` component for the composer and signature
settings. The editor now enables CKEditor's `ImageStyle` plugin with a limited
set of image layout buttons:

- `imageStyle:inline`
- `imageStyle:alignLeft`
- `imageStyle:alignRight`
- `imageStyle:alignBlockLeft`
- `imageStyle:block`
- `imageStyle:alignBlockRight`

CKEditor stores these styles as CSS classes on `figure.image`. Before the HTML
body is saved or sent, YooMail normalizes those classes into critical inline
styles through `normalizeImageAlignment()`. This keeps outgoing messages less
dependent on CKEditor CSS being available in other mail clients.

## Compatibility Notes

Email clients have uneven CSS support. This phase intentionally avoids text
vertical alignment because it requires broader testing across clients such as
Outlook, Gmail, QQ Mail, and mobile IMAP clients.

If future work adds top / middle / bottom vertical image alignment, it should be
implemented and tested separately from editor stability fixes.
