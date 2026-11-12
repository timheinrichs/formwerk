<h1>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo/formwerk-logo-dark.svg">
    <img src="assets/logo/formwerk-logo.svg" alt="Formwerk" width="279" height="64">
  </picture>
</h1>

Forms and SMTP for WordPress.

Formwerk sends all email of a WordPress site through a real mailbox, so it arrives in the inbox instead of the spam
folder, and lets you build forms that cannot lose a submission.

| | Status |
| --- | --- |
| SMTP delivery, test email, email log | Available since 0.1.0 |
| Form builder, submissions, spam protection | Coming with 0.2.0 |
| Confirmation email, CSV export, retention, WPML | Planned for 0.3.0 |
| Updates from within WordPress | Planned for 1.0.0 |

## Requirements

- WordPress 6.6 or newer
- PHP 8.1 or newer with the `sodium` and `mbstring` extensions (part of almost every PHP setup)
- A mailbox with SMTP access. Your email provider gives you server name, port, username and password.

## Installation

1. Download `formwerk-<version>.zip` from the [latest release](https://github.com/timheinrichs/formwerk/releases/latest).
   Use this file, not "Source code": only the release ZIP contains the compiled files.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**, choose the ZIP and activate the plugin.
3. Open **Formwerk → Email** in the menu and set up the mail server (see below).

To update, upload the ZIP of a newer release the same way. WordPress asks whether to replace the installed version;
settings are kept.

## Setting up email delivery

1. **Mail server.** Enter the server name of your provider, for example `smtp.example.com`. Choose the encryption;
   the port follows it (STARTTLS 587, SSL/TLS 465).
2. **Login.** Enter the username and password of the mailbox. The username is often the full email address.
3. **Sender.** Enter the address of the same mailbox and a name recipients will recognise. Mail servers often reject
   other sender addresses or mark them as spam.
4. **Save**, then **send a test email**. The page tells you whether the mail server accepted it. Check the inbox
   and the spam folder of the address you sent it to.

From then on every email of the site goes through this mail server, including email from other plugins such as
contact forms or shops.

"Use this sender for every email" also replaces senders that other plugins set themselves. Switch it on if their
emails are rejected.

Leaving the mail server field empty switches SMTP off. WordPress then sends email through the web server as it does
without the plugin.

### When something goes wrong

- **The test fails.** The message names the likely cause (server not reachable, login refused, encryption, sender
  address) and what to try. "Conversation with the mail server" shows the technical details with passwords and the
  email text hidden; you can copy them for your provider or developer.
- **Emails stop going through the mail server.** If saved settings become unusable, for example because the password
  can no longer be read after the site was moved, Formwerk falls back to the web server and says so: on the settings
  page, as a notice on every admin page, and in the log. Enter the password again and save.
- **A single email fails.** The email log on the settings page lists failed emails with recipient, subject and the
  reason the mail server gave.

## Building a form

1. Open **Formwerk → Forms** and choose **Add form**. Give it a name only you see, for example "Course registration".
2. **Fields.** Add fields with **Add field**. Select a field to change its label, help text, options and width.
   Move fields by dragging the handle, with the arrow buttons, or with the keyboard (Space, arrow keys, Space).
   **Preview** shows the form the way visitors see it.
3. **Notification.** Enter who receives the submissions. The email text starts with `{all_fields}`, which lists
   every field with its label; keep it, and no answer can be missing from the email. Replies to the email go to the
   address the visitor entered.
4. **Messages and spam protection.** Change the text on the button and the message shown after sending.
5. **Save form**, then add the block **Formwerk form** to a page, or paste the shortcode `[formwerk id="…"]`.

Saving is blocked while something would break the form: a missing name or recipient, a field without label or
options, or a placeholder that matches no field. The message names each point and takes you to it.

### Placeholders

| Placeholder | Becomes |
| --- | --- |
| `{all_fields}` | Every field with its label and answer |
| `{field:name}` | The answer to one field; the builder shows the exact name for each field |
| `{form}` | Name of the form |
| `{page}` | Address of the page the form was sent from |
| `{date}` | Date and time the submission arrived |
| `{site}` | Name of the website |

Field names are created from the label and never change once the form is saved, so placeholders keep working when
you rename or reorder fields.

### What visitors get

- A form in the look of your theme. Under **Formwerk → Forms → Look of the forms** you can switch to Formwerk's own,
  complete design instead.
- Errors next to each field and a summary at the top that links to them, for screen readers and keyboard users too.
- The form works without JavaScript; with JavaScript it is sent without reloading the page.

### Submissions

Every submission is stored before its email is sent. **Formwerk → Submissions** lists them by form, shows each one
in full, and marks those whose email did not go out. You can send that email again from there. If emails fail, a
notice on the Formwerk pages and a number next to **Submissions** say so.

### Spam protection

There is no captcha. Formwerk rejects submissions that arrive within three seconds of loading the page, fill in a
hidden field, or contain more links than you allow, and it limits how many messages come from one connection
(10 per 10 minutes) and per form (60 per 10 minutes). Visitors who are rejected see why and keep their entries.

Behind a proxy or CDN every visitor may share one address. Return the visitor's real address with the filter
`formwerk_client_address`; the limits can be changed with `formwerk_rate_limits`.

## Configuration in wp-config.php

Every setting can be fixed with a constant. A constant wins over the backend, and its field is shown there as locked.
This keeps the password out of the database and lets you manage several sites the same way.

| Constant | Value |
| --- | --- |
| `FORMWERK_SMTP_HOST` | Server name, IPv4 address, or IPv6 address |
| `FORMWERK_SMTP_PORT` | Port, for example `587` |
| `FORMWERK_SMTP_ENCRYPTION` | `tls` (STARTTLS), `ssl` (SSL/TLS) or `none` |
| `FORMWERK_SMTP_AUTH` | `true` if the server requires a login |
| `FORMWERK_SMTP_USER` | Username of the mailbox |
| `FORMWERK_SMTP_PASSWORD` | Password of the mailbox |
| `FORMWERK_SMTP_FROM_EMAIL` | Sender address |
| `FORMWERK_SMTP_FROM_NAME` | Sender name |
| `FORMWERK_SMTP_FORCE_FROM` | `true` to use the sender for every email |

```php
define( 'FORMWERK_SMTP_HOST', 'smtp.example.com' );
define( 'FORMWERK_SMTP_PORT', 587 );
define( 'FORMWERK_SMTP_ENCRYPTION', 'tls' );
define( 'FORMWERK_SMTP_AUTH', true );
define( 'FORMWERK_SMTP_USER', 'mailbox@example.com' );
define( 'FORMWERK_SMTP_PASSWORD', '…' );
```

## Data and privacy

- **Password.** Stored encrypted in the database. The key is derived from the security keys in `wp-config.php`, so
  a database dump alone does not reveal it. The password is never sent back to the browser.
- **Submissions.** All answers, the form's name, the page address and the language are stored in the table
  `{prefix}formwerk_submissions` until you delete them. Deleting a form keeps its submissions. Connection addresses
  are not stored; for the rate limit they are kept as salted hashes for ten minutes. A retention period and export
  per person will follow in 0.3.0.
- **Email log.** Keeps failed emails and test results: time, recipient addresses, subject and error message. At most
  50 entries, for at most 30 days. The log takes part in the WordPress tool "Erase Personal Data", and it can be
  cleared on the settings page.
- **Who can change the settings.** Administrators. On a multisite network only network administrators, because the
  mail settings also decide where password reset emails go.
- **Uninstalling.** Deleting the plugin removes its settings, the log, all forms and all submissions, on every site
  of a network.

## Languages

English and German (`de_DE`). Translations live in `languages/`.

## Development

```bash
composer install
npm install
npm run build
```

| Task | Command |
| --- | --- |
| PHP unit tests | `composer test` |
| PHP coding standards | `composer lint` (`composer lint:fix` to autofix) |
| Build / watch assets | `npm run build` / `npm start` |
| Lint JS / CSS | `npm run lint:js` / `npm run lint:css` |
| Build the release ZIP locally | `bash bin/build-zip.sh` (writes `dist/`, removes Composer dev packages; run `composer install` afterwards) |

To try the plugin, mount or symlink this folder into `wp-content/plugins/formwerk` of a local WordPress install.
A local mail catcher such as [Mailpit](https://mailpit.axllent.org/) is handy as the SMTP server.

Layout: `formwerk.php` boots the plugin, PHP classes live in `src/` (namespace `Formwerk\`, PSR-4), the admin
screens, the block and the form script in `assets/src/` (compiled to `build/`), unit tests in `tests/Unit/`.

### Releasing

1. Set the new version in `formwerk.php`, `src/Plugin.php` and `package.json`. A unit test fails if they differ.
2. Commit, then tag the commit on `main` as `v<version>` and push the tag.
3. The release workflow runs the tests, builds the ZIP and attaches it to a new GitHub release. It refuses a tag that
   does not match the plugin version.

## License

GPL-2.0-or-later, see `LICENSE`.
