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
- `tests/Unit/`: PHPUnit ohne WordPress. Logik (Validierung, Platzhalter, Spam-Token, Verschlüsselung) so
  schreiben, dass sie ohne WP-Funktionen testbar ist; WP-Aufrufe in dünne Klassen am Rand legen.
- `.distignore`: was nicht ins Release-ZIP kommt.

## Regeln

- PHP ≥ 8.1, WordPress ≥ 6.4. `declare( strict_types=1 )` in jeder Datei unter `src/`.
- Quelltexte (Strings, Kommentare, Commits) auf Englisch, Textdomain `formwerk`. Deutsch kommt als Übersetzung
  in `languages/`.
- Präfixe: Optionen, Hooks, Meta-Keys, Tabellen, REST-Namespace immer `formwerk`.
- Jede Ausgabe escapen, jede Eingabe validieren und bereinigen, jede Admin-Aktion mit Capability-Check und Nonce.
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

Das Plugin wird per Docker-Mount in ein lokales WordPress-Projekt eingebunden (http://localhost:8407),
Mails landen in Mailpit (http://localhost:8025). Details in `PLAN.md` → „Lokale Entwicklung“.
