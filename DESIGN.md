---
name: formwerk
description: Formulare und SMTP für WordPress – im Backend ein Teil von WordPress, im Frontend ein Teil des Themes.
---

<!-- Stand: v0.2.0 (2026-11-12). Die Werte unten sind die umgesetzten; bei Änderungen am Code hier nachziehen. -->

# Design System: formwerk

## Overview

**Creative North Star: „Der unsichtbare Handwerker“**

formwerk hat zwei Oberflächen und in keiner davon eine eigene Bühne. Im Backend ist es ein Teil von WordPress: Wer
den Block-Editor kennt, kennt formwerk. Im Frontend ist es ein Teil der jeweiligen Website: Das Formular sieht aus,
als hätte es das Theme selbst gebaut.

Die Hauptnutzer*innen im Backend sind Kund*innen ohne Technik-Hintergrund, die selten und ohne Hilfe arbeiten.
Gestaltung dient hier der Orientierung: eine klare Hauptaktion pro Ansicht, sichtbarer Zustand (gespeichert,
ungespeichert, Fehler), Erklärung dort, wo die Entscheidung fällt.

**Key Characteristics:**

- Backend ausschließlich aus `@wordpress/components` und den Mustern von wp-admin gebaut.
- Frontend erbt Schrift, Farben und Button-Stil vom Theme; formwerk liefert Struktur und Zustände.
- Ein eigenes Frontend-Styling („Formwerk’s own look“) ist pro Website einschaltbar: schlicht, ein Akzent, runde
  Ecken, alles über `--formwerk-*` anpassbar.
- Fehler sind die auffälligsten Elemente jeder Ansicht. Alles andere bleibt ruhig.

## Colors

formwerk definiert im Backend keine eigenen Farben.

- **Akzent im Backend:** die Admin-Farbe der jeweiligen Person über `var(--wp-admin-theme-color)` und ihre
  Varianten. Nie einen festen Blauwert setzen.
- **Statusfarben im Backend:** die von `Notice`, `Snackbar` und den wp-admin-Hinweisen (Erfolg, Warnung, Fehler,
  Info). Keine eigenen Statusfarben.
- **Frontend, Standard:** `currentColor` und geerbte Werte. Rahmen und Hilfetexte leiten sich aus der Textfarbe ab
  (`color-mix` mit `currentColor`), damit sie auf hellen und dunklen Themes funktionieren.
- **Frontend, Fehlerfarbe:** `--formwerk-error: color-mix(in srgb, #e0002b 65%, currentcolor)`. Rot gemischt mit
  der Textfarbe des Themes: dunkelrot auf hellen, hellrot auf dunklen Themes (auf Weiß gemessen 8,8:1). Nicht vom
  Farbschema des Systems abhängig machen; es sagt nichts über das Theme.
- **Frontend, Hilfslinien:** `--formwerk-muted` (Textfarbe 72 %) für Hilfetexte und „(required)“,
  `--formwerk-line` (Textfarbe 45 %) für Rahmen.
- **Frontend, eigenes Styling:** `--formwerk-accent: #2b4acb` (Button, Kontrollkästchen, Fokusring am Button),
  `--formwerk-on-accent: #fff`, `--formwerk-surface` (Textfarbe 4 %) als Feldhintergrund.
- **Backend, Fehlerrot:** `#cc1818`, das Rot der WordPress-Notice. Nur für Fehlertext, Fehlerrahmen und
  „Not sent“.

**The Borrowed Color Rule.** Jede Farbe kommt von WordPress oder vom Theme. Ausnahmen sind die Fehlerfarbe und das
einschaltbare eigene Styling.

**The Never Color Alone Rule.** Ein Zustand wird nie nur über Farbe gezeigt. Fehler haben immer Text und ein Symbol,
Pflichtfelder immer ein Wort oder Zeichen im Label.

## Typography

- **Backend:** die Systemschrift von wp-admin, Größen und Gewichte aus `@wordpress/components`. Keine eigenen
  Schriftgrößen außerhalb dieser Skala.
- **Frontend:** `font: inherit` für alle Felder und Buttons. Labels in der Fließtextgröße des Themes, Hilfetexte
  und Fehlermeldungen in `0.9em`, Labels und Fehlermeldungen halbfett (600).
- **Frontend, eigenes Styling:** ebenfalls `font: inherit`; nur der Button wird halbfett.

**The 16px Floor Rule.** Eingabefelder im Frontend haben auf schmalen Bildschirmen mindestens 16px Schriftgröße,
damit iOS beim Fokus nicht zoomt.

## Layout

- **Backend, Builder:** eine Leiste oben („← All forms“ links, Status und „Save form“ rechts) bleibt beim Scrollen
  unter der Admin-Leiste stehen. Darunter der Name, dann Tabs (Felder, Benachrichtigung, Meldungen und Spamschutz).
  Im Tab Felder links die Feldliste, rechts eine mitlaufende Spalte, umschaltbar zwischen Feldeinstellungen und
  Vorschau.
- **Backend, Listen und Einstellungen:** Standardseiten von wp-admin (`.wrap`, Seitentitel, `WP_List_Table`).
- **Backend, schmal:** Unter 782px rückt die Seitenspalte unter die Feldliste; die Auswahl eines Felds scrollt zu
  seinen Einstellungen. Tabellen werden zu gestapelten Karten mit Spaltennamen. Nichts scrollt horizontal, außer
  dem Mail-Protokoll in seinem eigenen, per Tastatur erreichbaren Rahmen.
- **Frontend:** ein Raster mit zwei Spalten für die Feldbreiten „voll“ und „halb“. Unterhalb einer Breite des
  Formular-Containers von 480px (Container Query) sind alle Felder voll breit. Reihenfolge immer Label, Hilfetext,
  Feld, Fehlermeldung.
- **Abstände:** im Backend das 4px-Raster von WordPress (8, 12, 16, 20, 24, 32px), im Frontend in `em`
  (`--formwerk-gap: 1.25em` zwischen Feldern), damit sie mit der Schriftgröße des Themes mitwachsen.

## Elevation & Depth

Flach. Im Backend entsteht Tiefe nur dort, wo WordPress-Komponenten sie mitbringen (`Popover`, `Modal`,
`Snackbar`) und an der mitlaufenden Builder-Leiste (eine Trennlinie, kein Schatten). Im Frontend gibt es keine
Schatten, auch nicht im eigenen Styling.

## Shapes

- **Backend:** Radien und Rahmen der WordPress-Komponenten, unverändert.
- **Frontend, Standard:** formwerk setzt keinen Radius und keinen Rahmenstil, wenn das Theme Felder bereits
  gestaltet. Fehlt ein Theme-Stil, gilt ein schlichter 1px-Rahmen aus `--formwerk-line` (per `:where()`, also
  schwächer als jede Theme-Regel). Eigenes Styling: `--formwerk-radius: 6px` für Felder, Button und Meldungen.
- **Fokus:** immer sichtbar. Im Backend der Fokusring von WordPress, im Frontend der des Themes; fehlt er, ein
  Umriss von mindestens 2px mit Abstand zum Feld. Am Button des eigenen Stylings in der Akzentfarbe, weil seine
  Textfarbe weiß ist.

## Do's and Don'ts

### Do:

- **Do** jede Backend-Ansicht aus `@wordpress/components` bauen (`Button`, `TextControl`, `SelectControl`,
  `ToggleControl`, `TabPanel`, `Notice`, `Snackbar`, `Modal`, `Panel`).
- **Do** `var(--wp-admin-theme-color)` für Akzente verwenden, damit das gewählte Admin-Farbschema gilt.
- **Do** im Frontend `inherit` und `currentColor` verwenden und alle eigenen Werte als `--formwerk-*`-Variablen
  überschreibbar machen.
- **Do** Frontend-CSS mit niedriger Spezifität schreiben (`:where(.formwerk-form) …`), damit das Theme gewinnt.
- **Do** Fehler direkt am Feld und zusätzlich in einer Übersicht oben zeigen, die den Fokus erhält.
- **Do** jede Ansicht bei 320px Breite, mit 200 % Zoom und nur mit Tastatur prüfen.
- **Do** `prefers-reduced-motion` beachten; Bewegung zeigt nur Zustandswechsel.

### Don't:

- **Don't** eine Markenfarbe oder eigene Schrift ins Backend bringen. Ausnahme mit Absicht: das Formwerk-Zeichen
  als einfarbiges Menü-Icon (färbt sich mit dem Admin-Farbschema) und das Logo klein (20px hoch, `alt=""`) über
  dem Seitentitel der Formwerk-Seiten. Es ist eine leise Signatur, kein zweiter Titel; der Titel bleibt Text.
- **Don't** WordPress-Komponenten per CSS umgestalten oder nachbauen.
- **Don't** im Standard-Frontend feste Farben, Schriftfamilien oder Button-Stile setzen.
- **Don't** `!important` im Frontend-CSS verwenden.
- **Don't** Platzhaltertext als Ersatz für ein Label benutzen.
- **Don't** einen Zustand nur über Farbe zeigen.
- **Don't** Erfolg melden, bevor die Einsendung gespeichert ist.
