# Gmail Integration

This document records the two supported Gmail connection paths for YooMail, so future testing, deployment, and user-facing copy stay aligned.

## Summary

YooMail can connect to Gmail in two ways:

- End users can use a Google app password.
- Administrators can configure Google OAuth, then users connect by authorizing YooMail with Google.

After either method is configured successfully, mail receiving, sending, folders, and attachments still work through Gmail IMAP/SMTP. The main difference is the authentication mechanism.

## Option 1: Google App Password

This is the most practical self-service path for regular users.

Users do not need Google Cloud and do not need to create an OAuth client.

Basic flow:

1. The user signs in to their Google account.
2. The user enables 2-Step Verification.
3. The user opens Google's app passwords page.
4. The user generates an app password for a mail client.
5. The user adds the Gmail account in YooMail.
6. The email address is the Gmail address, and the password is the generated app password, not the normal Gmail login password.

Direct entry:

```text
https://myaccount.google.com/apppasswords
```

Notes:

- Google's menu location can vary by account type and language, so user-facing copy should avoid relying on one fixed menu path.
- Workspace administrators may disable app passwords.
- Accounts enrolled in Advanced Protection may not be able to use app passwords.
- If the user enters the normal Gmail login password, Google will usually reject IMAP/SMTP login and YooMail may show an incorrect username or password message.

## Option 2: Google OAuth

This provides a more modern user experience, but the setup complexity belongs to the administrator.

The administrator creates an OAuth client in Google Cloud Console, then saves the Client ID and Client secret in the YooMail admin settings.

YooMail admin entry:

```text
/settings/admin/yoomail
```

Google Cloud Console configuration:

- OAuth client type: Web application.
- Authorized JavaScript origins should contain only the site origin, for example:

```text
https://beta.office.ebf.cc
```

- Authorized redirect URIs should contain the YooMail callback URL, for example:

```text
https://beta.office.ebf.cc/apps/yoomail/integration/google-auth
```

Notes:

- JavaScript origins must not contain paths.
- The redirect URI must exactly match the URL displayed by YooMail, including protocol, host, and path.
- If the OAuth app is in Testing mode, the Gmail account must be added to Test users.
- Public use with unrestricted users may require Google's OAuth app verification process.

## Current Implementation

YooMail now uses its own app route for the Google OAuth callback:

```text
/apps/yoomail/integration/google-auth
```

Backend route name:

```text
yoomail.googleIntegration.oauthRedirect
```

The Microsoft OAuth callback route was also aligned to the YooMail route namespace:

```text
yoomail.microsoftIntegration.oauthRedirect
```

The current admin page is maintained in `src/admin-basic-settings.js` and compiled by webpack into `js/admin-basic-settings.js`. Gmail OAuth settings are available on that page and include:

- Redirect URI display.
- Client ID input.
- Client secret input.
- Save.
- Unlink.

## Product Guidance

For regular users, the default help text should first explain Google app passwords. If the administrator has configured OAuth, YooMail should prefer the Google authorization flow.

Do not imply that regular users must configure Google Cloud Console. OAuth client setup is an administrator-level capability, not a regular user workflow.
