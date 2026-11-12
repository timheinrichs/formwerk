# Formwerk

Forms and SMTP for WordPress.

- **SMTP for all site mail.** Configure host, port, encryption and sender in the backend. Send a test mail and read
  the SMTP log when something fails.
- **A form builder that cannot lose data.** Every field has a unique name, the default mail template prints all
  fields, and unknown placeholders block saving instead of being sent out raw.
- **Submissions are stored before the mail is sent.** Failed mails are marked and can be sent again.
- **Spam protection without a captcha**, WPML support, and updates straight from GitHub releases.

> Status: in development. Nothing is released yet.

## Requirements

- WordPress 6.6 or newer
- PHP 8.1 or newer with the `sodium` extension

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

To try the plugin, mount or symlink this folder into `wp-content/plugins/formwerk` of a local WordPress install.

## Configuration in wp-config.php

Every SMTP setting can be fixed with a constant. A constant wins over the backend and locks the field there:
`FORMWERK_SMTP_HOST`, `FORMWERK_SMTP_PORT`, `FORMWERK_SMTP_ENCRYPTION` (`tls`, `ssl` or `none`),
`FORMWERK_SMTP_AUTH`, `FORMWERK_SMTP_USER`, `FORMWERK_SMTP_PASSWORD`, `FORMWERK_SMTP_FROM_EMAIL`,
`FORMWERK_SMTP_FROM_NAME`, `FORMWERK_SMTP_FORCE_FROM`.

## Data and privacy

- The SMTP password is stored encrypted. The key is derived from the security keys in `wp-config.php`.
- Failed emails and test results are kept in a log: time, recipient addresses, subject and error message, at most
  50 entries, for at most 30 days. The log takes part in the WordPress tool "Erase Personal Data".
- On a multisite network only network administrators can see and change the email settings.
- Deleting the plugin removes its settings and the log on every site.

## License

GPL-2.0-or-later, see `LICENSE`.
