<h1>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo/formwerk-logo-dark.svg">
    <img src="assets/logo/formwerk-logo.svg" alt="Formwerk" width="279" height="64">
  </picture>
</h1>

Forms and SMTP for WordPress.

Formwerk sends all email of a WordPress site through a real mailbox, so it arrives in the inbox instead of the spam
folder. A form builder that cannot lose submissions is in development.

| | Status |
| --- | --- |
| SMTP delivery, test email, email log | Available since 0.1.0 |
| Form builder, submissions, spam protection | Planned for 0.2.0 |
| Confirmation email, CSV export, retention, WPML | Planned for 0.3.0 |
| Updates from within WordPress | Planned for 1.0.0 |

## Requirements

- WordPress 6.6 or newer
- PHP 8.1 or newer with the `sodium` extension (part of every standard PHP build)
- A mailbox with SMTP access. Your email provider gives you server name, port, username and password.

## Installation

1. Download `formwerk-<version>.zip` from the [latest release](https://github.com/timheinrichs/formwerk/releases/latest).
   Use this file, not "Source code": only the release ZIP contains the compiled files.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**, choose the ZIP and activate the plugin.
3. Open **Formwerk → Email** in the menu.

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
- **Email log.** Keeps failed emails and test results: time, recipient addresses, subject and error message. At most
  50 entries, for at most 30 days. The log takes part in the WordPress tool "Erase Personal Data", and it can be
  cleared on the settings page.
- **Who can change the settings.** Administrators. On a multisite network only network administrators, because the
  mail settings also decide where password reset emails go.
- **Uninstalling.** Deleting the plugin removes its settings and the log, on every site of a network.

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

Layout: `formwerk.php` boots the plugin, PHP classes live in `src/` (namespace `Formwerk\`, PSR-4), the admin screen
in `assets/src/` (React with `@wordpress/components`, compiled to `build/`), unit tests in `tests/Unit/`.

### Releasing

1. Set the new version in `formwerk.php`, `src/Plugin.php` and `package.json`. A unit test fails if they differ.
2. Commit, then tag the commit on `main` as `v<version>` and push the tag.
3. The release workflow runs the tests, builds the ZIP and attaches it to a new GitHub release. It refuses a tag that
   does not match the plugin version.

## License

GPL-2.0-or-later, see `LICENSE`.
