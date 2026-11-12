---
name: formwerk
description: Formulare und SMTP für WordPress – im Backend ein Teil von WordPress, im Frontend ein Teil des Themes.
---

<!-- SEED: vor der Implementierung festgelegt (2026-11-12). Sobald Oberflächen existieren, mit `/impeccable document` die echten Tokens und Komponenten nachtragen. -->

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
- Ein eigenes Frontend-Styling ist pro Website einschaltbar. Sein Aussehen ist noch nicht festgelegt.
- Fehler sind die auffälligsten Elemente jeder Ansicht. Alles andere bleibt ruhig.

## Colors

formwerk definiert im Backend keine eigenen Farben.

- **Akzent im Backend:** die Admin-Farbe der jeweiligen Person über `var(--wp-admin-theme-color)` und ihre
  Varianten. Nie einen festen Blauwert setzen.
- **Statusfarben im Backend:** die von `Notice`, `Snackbar` und den wp-admin-Hinweisen (Erfolg, Warnung, Fehler,
  Info). Keine eigenen Statusfarben.
- **Frontend, Standard:** `currentColor` und geerbte Werte. Rahmen und Hilfetexte leiten sich aus der Textfarbe ab
  (`color-mix` mit `currentColor`), damit sie auf hellen und dunklen Themes funktionieren.
- **Frontend, Fehlerfarbe:** ein einziger eigener Wert, als CSS-Variable `--formwerk-error` überschreibbar.
  `[Wert wird bei der Implementierung festgelegt; muss auf Weiß und auf Schwarz mindestens 4,5:1 erreichen oder je
  Farbschema einen eigenen Wert bekommen]`
- **Frontend, mitgeliefertes Styling:** `[Palette wird in Phase 3 festgelegt]`

**The Borrowed Color Rule.** Jede Farbe kommt von WordPress oder vom Theme. Die einzige Ausnahme ist die
Fehlerfarbe im Frontend.

**The Never Color Alone Rule.** Ein Zustand wird nie nur über Farbe gezeigt. Fehler haben immer Text und ein Symbol,
Pflichtfelder immer ein Wort oder Zeichen im Label.

## Typography

- **Backend:** die Systemschrift von wp-admin, Größen und Gewichte aus `@wordpress/components`. Keine eigenen
  Schriftgrößen außerhalb dieser Skala.
- **Frontend:** `font: inherit` für alle Felder und Buttons. Labels in der Fließtextgröße des Themes, Hilfetexte
  und Fehlermeldungen relativ dazu (`[Faktor wird bei der Implementierung festgelegt, nicht kleiner als 0.875em]`).
- **Frontend, mitgeliefertes Styling:** `[wird in Phase 3 festgelegt]`

**The 16px Floor Rule.** Eingabefelder im Frontend haben auf schmalen Bildschirmen mindestens 16px Schriftgröße,
damit iOS beim Fokus nicht zoomt.

## Layout

- **Backend, Builder:** das Muster des Block-Editors. Arbeitsfläche mit Feldliste und Vorschau, Einstellungen des
  gewählten Felds in einer Seitenleiste rechts, Bereiche (Felder, Benachrichtigung, Bestätigung, Meldungen) als
  Tabs. Speichern oben rechts, immer an derselben Stelle.
- **Backend, Listen und Einstellungen:** Standardseiten von wp-admin (`.wrap`, Seitentitel, `WP_List_Table`).
- **Backend, schmal:** Unter der wp-admin-Schwelle von 782px rückt die Seitenleiste unter die Feldliste oder wird
  zum einblendbaren Panel. Nichts scrollt horizontal.
- **Frontend:** ein Raster mit zwei Spalten für die Feldbreiten „voll“ und „halb“. Unterhalb einer Breite des
  Formular-Containers (Container Query, `[Schwelle wird bei der Implementierung festgelegt]`) sind alle Felder voll
  breit. Reihenfolge immer Label, Hilfetext, Feld, Fehlermeldung.
- **Abstände:** im Backend das 4px-Raster von WordPress (`[Stufen werden aus den verwendeten Komponenten
  übernommen]`), im Frontend in `em`, damit sie mit der Schriftgröße des Themes mitwachsen.

## Elevation & Depth

Flach. Im Backend entsteht Tiefe nur dort, wo WordPress-Komponenten sie mitbringen (`Popover`, `Modal`,
`Snackbar`). Im Frontend gibt es keine Schatten; das mitgelieferte Styling entscheidet das in Phase 3 für sich.

## Shapes

- **Backend:** Radien und Rahmen der WordPress-Komponenten, unverändert.
- **Frontend, Standard:** formwerk setzt keinen Radius und keinen Rahmenstil, wenn das Theme Felder bereits
  gestaltet. Fehlt ein Theme-Stil, gilt ein schlichter 1px-Rahmen aus der Textfarbe.
- **Fokus:** immer sichtbar. Im Backend der Fokusring von WordPress, im Frontend der des Themes; fehlt er, ein
  Umriss von mindestens 2px mit Abstand zum Feld.

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

- **Don't** ein eigenes Logo, eine Markenfarbe oder eigene Schrift ins Backend bringen.
- **Don't** WordPress-Komponenten per CSS umgestalten oder nachbauen.
- **Don't** im Standard-Frontend feste Farben, Schriftfamilien oder Button-Stile setzen.
- **Don't** `!important` im Frontend-CSS verwenden.
- **Don't** Platzhaltertext als Ersatz für ein Label benutzen.
- **Don't** einen Zustand nur über Farbe zeigen.
- **Don't** Erfolg melden, bevor die Einsendung gespeichert ist.
