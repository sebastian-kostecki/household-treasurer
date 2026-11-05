# Tokeny design systemu „C · Atrament i morski” — Plan Brief

> Full plan: `context/changes/design-tokens-atrament/plan.md`
> Research: `context/changes/design-tokens-atrament/research.md`

## What & Why

Aplikacja przechodzi na wybrany 2026-11-05 kierunek „C · Atrament i morski”: atramentowa akcja główna, jeden morski akcent, granatowy sidebar w obu motywach, Inter i JetBrains Mono. Ta zmiana wdraża tylko warstwę kontraktu: tokeny, kroje i dokumenty. Do tego dochodzą poprawki miejsc, które same tokeny by zepsuły. Widoki przejdą potem przez `/10x-ui` na gotowych tokenach.

## Starting Point

`resources/css/app.css` ma turkusowe `primary`, jasny sidebar, `--radius` 12 px i Geist. 34 z 43 ról systemu już istnieje, brakuje 9. ADR-0001/0003/0023, `design-system.md`, reguły `.ai/rules` i skill `openship-ui-development` wprost opisują turkus i Geist.

## Desired End State

Tokeny w obu motywach mają wartości z `tokens.json` systemu, nowe role (`brand`, `brand-text`, `scrim`, `sidebar-strong` itd.) są dostępne jako utility, a interfejs jest w Interze. Sidebar jest granatowy, przylega do treści, a aktywna pozycja jest miętowa. Logo, awatar, etykiety i linki są czytelne w obu motywach. ADR-0035 i dokumenty opisują nowy stan.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres | Punkty README 1, 2, 4, 6 (tokeny, atramentowe `primary`, kroje, ciemny sidebar) | Reszta to zmiany widoków | Research |
| Regresje kontrastu | Naprawiamy wszystkie 6 (Welcome, awatar, etykieta grupy, linia nagłówka, kropki landingu, pasek Inertii) | Zmiana tokenów nie może wypuścić nieczytelnych elementów | Plan |
| Sidebar | Aktywna pozycja na `sidebar-primary` (także pod kursorem), `sidebar-strong` dla nazwy, użytkownika i roku, stan aktywny w kitchen sinku | Bez tego aktywnej pozycji nie widać (1,15:1) | Plan |
| Ramka wokół treści | `variant="sidebar"` zamiast `inset` | README nie przewiduje granatowej ramki | Plan |
| Przepięcia na nowe role | Tylko linki → `brand-text` | Scrim, pigułki `*-soft`, paski i chipy to decyzje widoków | Plan |
| `--font-mono` | Token i krój bez preloadu, kwoty zostają w Interze | Kwoty przejdą na mono razem ze skalą typograficzną per widok | Plan |
| Numer ADR | Tokeny ADR-0035; plan raportu 0035 → 0036 z notką o `--chart-1` | Plan raportu i tak trzeba przejrzeć wobec nowej palety | Plan |
| `chart-1..5` | Bez zmian | Kolory wykresów ustala zmiana raportu | Plan |
| Format wartości | Hex/rgba 1:1 z `tokens.json` | Diff da się porównać z plikiem systemu | Plan |
| Kontrast `border`/`input` | Bez zmian wartości, ADR zapisuje 1,37/1,32:1 jako dekoracyjne | README deklaruje 3:1, liczby tego nie potwierdzają | Research |
| Dostawca krojów | Bunny przez `bunny()` | README mówi Google Fonts, ale projekt już używa Bunny | Plan |

## Scope

**In scope:**
- wartości `:root`/`.dark`, 9 nowych ról, `--radius: 10px`, `--font-sans` (Inter), `--font-mono`, `bunny()` dla obu krojów;
- `FontStackTest` przepisany, nowy `DesignTokensTest`;
- pierwsze malowanie w `app.blade.php`, pasek Inertii;
- 6 poprawek regresji, sidebar, kitchen sink, linki;
- ADR-0035, zastąpienia w ADR-0001/0003/0023, `design-system.md`, reguły, skill, notka w specu, numer ADR w planie raportu.

**Out of scope:**
- karty `rounded-2xl`/`border/60`, kontrolki 32 px, `table-header`, kolor wydatku, wiersze dnia, paleta ⌘K;
- kwoty w mono i skala typograficzna;
- przepięcia na `scrim`, `*-soft`, `track`; znacznik paska, chipy filtrów;
- `chart-*`, `shadow`/`spacing`/`layout` z `tokens.json`;
- martwy `AppHeader`.

## Architecture / Approach

Tokeny trafiają do komponentów przez Tailwind v4 `@theme inline` jako zmienne CSS, bez `dark:`, więc jedna edycja `app.css` zmienia całą aplikację. Edycje poza `app.css` są tylko tam, gdzie kod używał `primary` jako „turkusu” albo dziedziczył kolor, który na granacie staje się nieczytelny. Dokumenty idą przed kodem, jak wymaga README systemu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzja na piśmie | ADR-0035, zastąpienia, design-system.md, reguły, skill, spec, numer w planie raportu | Rozjazd liczb kontrastu między ADR a design-system.md |
| 2. Warstwa tokenów i kroje | Nowe wartości i role w `app.css`, Inter + JetBrains Mono, testy źródła, pierwsze malowanie, pasek Inertii | Między Fazą 2 a 3 gałąź ma znane regresje |
| 3. Konsumenci tokenów | 6 regresji, sidebar, kitchen sink, linki, zrzuty w obu motywach | Kolejność wariantów `hover`/`data-[active]` w Tailwind v4 |

**Prerequisites:** działający Sail; dostęp do artefaktu systemu (wartości są też w tabeli w planie).
**Estimated effort:** ok. 2–3 sesje w 3 fazach.

## Open Risks & Assumptions

- `progress.color: 'var(--brand-text)'` zakłada, że Inertia wkleja kolor do CSS; jeśli nie, wpisujemy hex.
- Ciemny przycisk `destructive` (`text-white`, `hover:bg-destructive/90` vs `dark:bg-destructive/60`) nie był renderowany; przy pełnym kolorze biały daje 2,52:1.
- `variant="sidebar"` zmienia układ każdej strony (brak karty-wnęki); oceniamy na zrzutach.
- Do zmian widoków część elementów (paski, chipy aktywnych filtrów, `Skeleton`) traci rozróżnienie od `foreground`; to świadome.

## Success Criteria (Summary)

- W obu motywach aplikacja ma kolory i kroje systemu, a żaden z naprawianych elementów nie spada poniżej 4,5:1 (tekst) ani 3:1 (grafika).
- Aktywną pozycję menu widać od razu, sidebar przylega do treści.
- Testy i gate CI są zielone, a dokumenty i reguły nie przeczą ADR-0035.
