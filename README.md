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

- WordPress 6.4 or newer
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

## License

GPL-2.0-or-later, see `LICENSE`.
