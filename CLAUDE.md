# formwerk

WordPress-Plugin: Formular-Builder + SMTP. Plan und Phasen stehen in `PLAN.md` (nur lokal, nicht versioniert), Produkt- und Designvorgaben in
`PRODUCT.md` und `DESIGN.md`.

## Befehle

| Zweck | Befehl |
| --- | --- |
| PHP-Tests | `composer test` |
| PHP-Lint (WPCS) / Autofix | `composer lint` / `composer lint:fix` |
| JS bauen / Watch | `npm run build` / `npm start` |
| JS-/CSS-Lint | `npm run lint:js` / `npm run lint:css` |

Vor jedem Commit müssen `composer test`, `composer lint`, `npm run lint:js` und `npm run build` grün sein.

## Aufbau

- `formwerk.php`: Plugin-Header und Start. Muss mit altem PHP parsebar bleiben (zeigt sonst keinen Hinweis).
- `src/`: PHP, Namespace `Formwerk\`, PSR-4. Dateiname = Klassenname.
- `assets/src/`: JS/SCSS-Quellen (React, `@wordpress/components`). `src/` gehört PHP, darum liegt JS hier.
  Einstiegspunkte stehen in `webpack.config.js`, Ausgabe nach `build/` (nicht versioniert).
- `src/Forms/`: reine Logik (Schema, Validator, Placeholders, SpamGuard) plus FormRepository (Post-Type
  `formwerk_form`, JSON in Post-Meta). `src/Submissions/`: Store (eigene Tabelle), Handler (einziger Weg jeder
  Einsendung: Token → Validierung → Spam → Dedupe → Limits → speichern → Benachrichtigung → Bestätigung mit
  eigener Link-Prüfung und Grenzen), Notifier, RateLimiter, Retention (Cron), Privacy, CsvExport.
  `src/I18n/Wpml.php`: String-Pakete pro Formular, ohne WPML ein No-op.
  `src/Frontend/`: Renderer (HTML, ohne JS lauffähig) und Controller (Shortcode, Block, Weg ohne JS).
  Platzhalter heißen englisch: `{all_fields}`, `{field:name}`, `{form}`, `{page}`, `{date}`, `{site}`.
- `tests/Unit/`: PHPUnit ohne WordPress. Die wenigen benötigten WP-Funktionen sind in `tests/stubs.php` nachgebaut
  (Optionen im Speicher, Zustand über `Formwerk\Tests\WpState`). Logik (Validierung, Platzhalter, Spam-Token, Verschlüsselung) so
  schreiben, dass sie ohne WP-Funktionen testbar ist; WP-Aufrufe in dünne Klassen am Rand legen.
- `.distignore`: was nicht ins Release-ZIP kommt.

## Regeln

- PHP ≥ 8.1, WordPress ≥ 6.6. `declare( strict_types=1 )` in jeder Datei unter `src/`.
- Quelltexte (Strings, Kommentare, Commits) auf Englisch, Textdomain `formwerk`. Deutsch kommt als Übersetzung
  in `languages/`.
- Präfixe: Optionen, Hooks, Meta-Keys, Tabellen, REST-Namespace immer `formwerk`.
- Jede Ausgabe escapen, jede Eingabe validieren und bereinigen, jede Admin-Aktion mit Capability-Check und Nonce.
  Die Capability kommt immer aus `Plugin::capability()` (auf Multisite nur Netzwerk-Admins, weil die
  Mail-Einstellungen auch Passwort-Reset-Mails umleiten können).
  Öffentliches Absenden nutzt das signierte Token statt Nonce (Seiten-Cache).
- Die Version steht an drei Stellen: Header in `formwerk.php`, `Plugin::VERSION`, `package.json`.
  `VersionTest` schlägt fehl, wenn sie auseinanderlaufen.
- Frontend ohne jQuery und ohne Build-Abhängigkeit zur Laufzeit; das Formular funktioniert auch ohne JS.

## Git und Releases

- SemVer. Pro Phase/Feature ein Branch (`feat/…`, `fix/…`, `chore/…`), bei parallelen Arbeiten als Worktree;
  Merge nach `main`.
- Release = Tag `vX.Y.Z` auf `main`. Die GitHub-Action baut das ZIP (ab Phase 5).
- Repo ist öffentlich: keine Zugangsdaten, keine Kundendaten, keine Live-Hostnamen in Code, Tests oder Commits.

## Lokal testen

Die Testinstanz liegt außerhalb dieses Repos in `~/Repos/formwerk-dev` (eigener Docker-Stack, nicht versioniert):
`bash ~/Repos/formwerk-dev/setup.sh` startet WordPress unter http://localhost:8480 und
Mailpit unter http://localhost:8481. Dieses Repo ist dort als Plugin eingebunden. WP-CLI:
`docker compose run --rm cli wp …` im Ordner `formwerk-dev`. Kein anderes WordPress-Projekt zum Testen benutzen.

## Übersetzungen

Nach Textänderungen erst `npm run build`, dann im Ordner `formwerk-dev` (`P=wp-content/plugins/formwerk`):

1. `wp i18n make-pot $P $P/languages/formwerk.pot --exclude=vendor,node_modules,tests,assets/src`
2. `wp i18n update-po $P/languages/formwerk.pot $P/languages`, danach neue Texte in `formwerk-de_DE.po` übersetzen
3. `wp i18n make-mo $P/languages` und `wp i18n make-json $P/languages --no-purge`
4. Die erzeugten JSON-Dateien nach dem Script-Handle benennen: Quelle `build/admin.js` (bzw. `build/a.js`) →
   `formwerk-de_DE-formwerk-admin.json`, Quelle `build/block.js` → `formwerk-de_DE-formwerk-block.json`. Der
   Hash-Name von `make-json` passt nicht zu den Handles. Das Frontend-Script hat keine eigenen Texte.

Deutsch ist die du-Form (`de_DE`), gendersensibel mit Stern.
