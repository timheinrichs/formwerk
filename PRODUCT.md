# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

WordPress-Plugin. PHP ≥ 8.1 (Namespace `Formwerk\`, PSR-4), Admin-Oberfläche in React mit `@wordpress/scripts` und
`@wordpress/components`, Frontend in Vanilla JS als Progressive Enhancement.

## Users

- **Kund*innen und Redakteur*innen im WordPress-Backend (Hauptnutzer*innen, bestätigt am 2026-11-12).** Menschen
  ohne Technik-Hintergrund, die ihre eigene Website pflegen. Sie bauen neue Formulare selbst, ändern bestehende,
  lesen Einsendungen und exportieren sie. Sie tun das selten, oft Monate nach dem letzten Mal, und haben niemanden
  neben sich, der Begriffe wie „Platzhalter“ oder „Reply-To“ erklärt.
- **Entwickler*in bzw. Betreuer*in der Website.** Installiert das Plugin, richtet SMTP ein, legt die ersten
  Formulare an und übersetzt sie. Kennt WordPress gut, will bei Mailproblemen schnell die Ursache sehen.
- **Besucher*innen der Website.** Füllen ein Formular aus, zum Beispiel die Anmeldung zu einer Fortbildung. Kommen
  mit beliebigem Gerät, teils mit Tastatur oder Screenreader, teils mit abgeschaltetem JavaScript.

## Product Purpose

formwerk verschickt alle Mails einer WordPress-Website zuverlässig über SMTP und lässt Formulare in einem eigenen
Builder anlegen. Anlass war ein Formular auf einer Kundenwebsite, das Anmeldungen mit rohen Platzhaltern an die
falsche Adresse schickte: die Daten waren weg, die Mail lag im Spam.

Erfolg heißt: Jede abgeschickte Einsendung ist danach im Backend zu finden, auch wenn die Mail scheitert. Mails
kommen im Posteingang an, nicht im Spam. Eine Person ohne Technik-Hintergrund baut ein Formular, ohne dass dabei ein
Zustand entsteht, in dem Daten verloren gehen können.

## Positioning

formwerk macht Datenverlust durch Fehlkonfiguration unmöglich, statt davor zu warnen:

- Feldnamen sind immer eindeutig und entstehen aus dem Label.
- Die Standard-Mailvorlage gibt alle Felder aus (`{alle_felder}`).
- Unbekannte Platzhalter blockieren das Speichern; sie gehen nie roh in eine Mail.
- Die Einsendung wird gespeichert, bevor die Mail verschickt wird. Fehlgeschlagene Mails sind markiert und lassen
  sich erneut senden.

## Operating Context

- Läuft in mehreren eigenen WordPress-Projekten, zuerst auf einer Website mit WPML (Deutsch/Englisch) auf
  klassischem Shared Hosting.
- Die SMTP-Einstellung gilt für alle Mails der Website, auch für andere Plugins.
- Formulare werden per Shortcode `[formwerk id="…"]` oder Block in Seiten eingebunden. Seiten können im Cache
  liegen.
- Installation und Updates kommen über GitHub-Releases, nicht über das WordPress-Plugin-Verzeichnis.
- Einsendungen enthalten personenbezogene Daten (Name, E-Mail, Telefon, Freitext).

## Capabilities and Constraints

- **Feldtypen:** Text, E-Mail, Telefon, Zahl, Datum, Textbereich, Auswahl, Radio, Checkboxen, Einwilligung,
  Zwischenüberschrift/Hinweistext.
- **Builder:** Feldliste mit Drag-and-drop, Einstellungen pro Feld, Live-Vorschau. Bereiche: Felder,
  Benachrichtigung, Bestätigung, Meldungen.
- **Absenden:** mit JavaScript ohne Neuladen und mit Fehlern am Feld; ohne JavaScript über einen normalen
  Seitenaufruf. Validiert wird immer auf dem Server.
- **Spamschutz ohne Captcha:** Honeypot, signierter Zeitstempel, Rate-Limit, optionales Link-Limit. Spam wird
  verworfen und nur gezählt.
- **Einsendungen:** Liste mit Filter pro Formular, Detailansicht, Löschen, CSV-Export, erneutes Senden.
  Aufbewahrungsfrist einstellbar (Standard 180 Tage), Anbindung an die WordPress-Datenschutzwerkzeuge.
- **SMTP:** Host, Port, Verschlüsselung, Anmeldung, Absender, Test-Mail mit Protokoll. Das Passwort liegt
  verschlüsselt in der Datenbank; Konstanten in `wp-config.php` haben Vorrang.
- **Sprache:** Quelltexte auf Englisch, Deutsch als mitgelieferte Übersetzung (bestätigt am 2026-11-12). Texte je
  Formular sind über WPML übersetzbar; ohne WPML läuft alles unverändert.
- **Frontend-Darstellung:** Standard ist, dass das Formular Schrift, Farben und Button-Stil vom Theme erbt und
  formwerk nur Struktur liefert. Ein mitgeliefertes eigenes Styling ist pro Website einschaltbar (bestätigt am
  2026-11-12).
- **Begriffe:** „Formular“, „Feld“, „Einsendung“, „Benachrichtigung“ (Mail an die Website), „Bestätigung“ (Mail an
  die einsendende Person), „Platzhalter“.
- **Nicht in v1:** Datei-Uploads, mehrseitige Formulare, bedingte Felder, Zahlungen.
- **Lizenz:** GPL-2.0-or-later (bestätigt am 2026-11-12).
- **Offen:** Welche Rolle neben Administrator*innen Formulare bearbeiten und Einsendungen sehen darf.

## Brand Commitments

- Name: **formwerk** (im Fließtext klein, im WordPress-Menü „Formwerk“).
- Keine eigene Markenfarbe im Backend. formwerk tritt als Teil von WordPress auf. Bewusste Ausnahme (seit 0.3.0):
  das Zeichen als einfarbiges Menü-Icon und das Logo klein über dem Seitentitel, damit Kund*innen das Plugin
  wiederfinden. Logo-Dateien liegen in `assets/logo/`.
- Ton: ruhig, konkret, ohne Fachjargon. Eine Meldung sagt, was passiert ist und was als Nächstes zu tun ist.

## Evidence on Hand

- `PLAN.md` (nur lokal, nicht versioniert): Auslöser, Architektur, Phasen.
- Zwei reale Formulare der ersten Website als Vorlage für den Nachbau („Kontaktformular Fortbildung“ und eine
  Fortbildungsanmeldung).
- Es gibt keine Nutzertests, keine Kundenstimmen und keine Messwerte. Nichts davon erfinden.

## Product Principles

1. **Keine Einsendung geht verloren.** Erst speichern, dann senden. Im Zweifel behalten und markieren.
2. **Fehler blockieren laut statt leise durchzurutschen.** Eine falsche Vorlage lässt sich nicht speichern; eine
   gescheiterte Mail ist im Backend sichtbar.
3. **Für Menschen, die das selten tun.** Jede Ansicht muss ohne Vorwissen und ohne Handbuch bedienbar sein. Sichere
   Voreinstellungen ersetzen Erklärtexte.
4. **Sieht aus und verhält sich wie WordPress.** Kein eigener Bedienstil, den man neu lernen muss.
5. **Funktioniert für alle Besucher*innen.** Tastatur, Screenreader, schmale Bildschirme und abgeschaltetes
   JavaScript sind Normalfälle.

## Accessibility & Inclusion

- Ziel: WCAG 2.2 AA für Frontend-Formulare und Backend (Annahme, nicht ausdrücklich bestätigt).
- Fehler am Feld mit `aria-invalid` und `aria-describedby`, dazu eine Fehlerübersicht, die den Fokus erhält.
- Der Builder ist vollständig per Tastatur bedienbar, auch das Umsortieren der Felder.
- Deutsche Texte in gendersensibler Sprache.
