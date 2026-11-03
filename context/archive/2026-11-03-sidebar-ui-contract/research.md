---
date: 2026-11-03T13:29:26+01:00
researcher: Sebastian Kostecki (Claude Opus 5.5)
git_commit: c6b9ddc
branch: sidebar-ui-contract
repository: household-treasurer
topic: "Dwukierunkowy audyt sidebara wg 10x-ui (tokeny --sidebar-*, komponenty, architektura wejścia)"
tags: [research, ui, sidebar, design-tokens, AppSidebar, BudgetYearSwitcher, NavMain]
status: complete
last_updated: 2026-11-03
last_updated_by: Sebastian Kostecki (Claude Opus 5.5)
---

# Research: dwukierunkowy audyt sidebara

**Date**: 2026-11-03T13:29:26+01:00
**Researcher**: Sebastian Kostecki (Claude Opus 5.5)
**Git Commit**: c6b9ddc (worktree `sidebar-ui-contract` od `development` @ 547d3ef; pliki sidebara identyczne z głównym checkoutem)
**Branch**: sidebar-ui-contract
**Repository**: household-treasurer

## Research Question

Dwukierunkowy audyt sidebara wg 10x-ui. Źródło→widoki: użycie tokenów `--sidebar-*` (w tym martwy `--sidebar`, `app.css:139/181`) i importy z `components/ui/sidebar`. Widok→źródło: `AppLogo.vue:12`, `UserInfo.vue:27`, `NavFooter.vue:28` (nieużywany). Architektura: `BudgetYearSwitcher` w trybie `collapsible=icon` (zrzut zwinięty i rozwinięty, desktop + 390 px, jasny/ciemny), aktywny stan `NavMain.vue:28` na `/operations/import*`, wejście bez roku budżetu (stan pusty przełącznika), zachowanie na mobile (Sheet). Brak reguły dla sidebara. Zapisać 3–5 zarzutów pod `## Charges` z plik:linia i wpływem na użytkownika.

## Summary

Sidebar składa się z prymitywów shadcn-vue: 11 z 12 sprawdzonych plików powłoki importuje z `@/components/ui/*` (wyjątek: `AppLogo.vue`, sam znak i nazwa), a skan wartości zakodowanych na sztywno daje na nich 3 trafienia (`NavFooter.vue:28`, `AppLogo.vue:12`, `UserInfo.vue:27`). Problem nie leży więc głównie w kolorach, tylko w architekturze powłoki:

1. **Przełącznik roku nie ma trybu ikon.** Po zwinięciu sidebara (`sidebar_state=false`, szerokość 3rem — `ui/sidebar/utils.ts:8`) etykieta „Rok budżetu” łamie się na dwie linie, select ma 18 px szerokości (sama strzałka), a przycisk „Dodaj rok” (22 px) wylewa się tekstem na treść strony. Potwierdzone zrzutami w obu motywach i w stanie pustym.
2. **Aktywna pozycja menu** jest liczona dokładnym porównaniem ścieżki (`NavMain.vue:28` → `useCurrentUrl.ts:46`); na `/operations/import` zrzut i DOM pokazują zero elementów `data-active=true`.
3. **Literały `dark:`** w `AppLogo.vue:12` i `UserInfo.vue:27` nadpisują tokeny. W logo literał kompensuje słaby token: ciemny `--sidebar-primary-foreground` (biały) na ciemnym `--sidebar-primary` daje ok. 3,3:1, czarny literał ok. 6,2:1 — poprawka należy do warstwy tokenów, a nie do usunięcia klasy.
4. **Źródło tokenów ma martwą i nieudokumentowaną część:** `--sidebar` (`app.css:139`, `app.css:181`) nie ma czytelnika, a w `.dark` ma inny odcień niż używany `--sidebar-background`; `.docs/design-system.md` nie wymienia żadnego tokenu `--sidebar-*`.
5. **Pozostałości startera:** osierocony `NavFooter.vue` (z klasami palety) i znak Laravel w `AppLogoIcon.vue` widoczny w nagłówku sidebara na każdym ekranie — wbrew duchowi US-31 („nie reklamować kitu Laravel”).

Działa poprawnie (sprawdzone, bez zarzutu): pierścień fokusu klawiatury na pozycjach menu (token `sidebar-ring`), Sheet na 390 px w obu motywach, rozwinięty stan pusty przełącznika („Brak roku budżetu” + „Dodaj rok”, zgodnie z US-11).

## Charges

Kolejność wg wpływu na użytkownika.

### C1 — Przełącznik roku rozsypuje się w zwiniętym sidebarze (przypadkowa architektura) — **wysoki**

- **Dowód:** `resources/js/components/BudgetYearSwitcher.vue:17-89` — brak jakiegokolwiek wariantu `group-data-[collapsible=icon]`; komponent wstawiony do `SidebarHeader` w `resources/js/components/AppSidebar.vue:79`. `SidebarHeader` (`ui/sidebar/SidebarHeader.vue:14`) nie przycina zawartości. Prymitywy menu same obsługują tryb ikon (`ui/sidebar/SidebarGroupLabel.vue:20`, `ui/sidebar/index.ts:39,50`), przełącznik — nie.
- **Pomiar (1440×900, `/accounts`):** rozwinięty: nagłówek 240 px, select 192 px, przycisk 192 px; zwinięty: nagłówek 50 px, select 18 px, przycisk 22 px z tekstem wychodzącym poza pas. Stan pusty zwinięty: ramka „Brak roku budżetu” łamie się na trzy linie, „Dodaj rok” wychodzi na treść.
- **Zrzuty:** `screenshots/before/desktop-collapsed-{light,dark}.png`, `desktop-collapsed-empty-years-light.png`.
- **Wpływ na użytkownika:** kto zwinie sidebar, nie może zmienić roku budżetu ani dodać roku, a etykieta „Dodaj rok” nachodzi na treść każdej strony.
- **Ograniczenie z historii:** US-10 wymaga kontroli roku w nagłówku sidebara „at all times” (`.docs/specs/app-shell-openship-visual-language.md:24`, także `:60`), a ADR-0031 nie pozwala jej ukrywać na Dashboardzie (`.docs/adr/0031-dashboard-always-shows-current-calendar-year.md:3,5`). Samo ukrycie w trybie ikon łamie spec — patrz *Open Questions*.

### C2 — Brak aktywnej pozycji menu na podstronach (przypadkowa architektura) — **średni**

- **Dowód:** `resources/js/components/NavMain.vue:28` woła `isCurrentUrl(item.href)`, które porównuje ścieżki równością (`resources/js/composables/useCurrentUrl.ts:46`, `startsWith=false`). Trasy `operations/import` i `operations/import/preview` (`routes/web.php:52,54`) nie są równe `/operations`. W tym samym composable istnieje `isCurrentOrParentUrl` (`useCurrentUrl.ts:61-66`).
- **Pomiar:** na `/operations/import` lista `[data-active=true]` jest pusta; na `/accounts` zawiera „Konta”.
- **Zrzut:** `screenshots/before/desktop-operations-import-light.png`.
- **Wpływ na użytkownika:** w trakcie importu CSV (dwa kroki) menu nie pokazuje, w której sekcji jest użytkownik.
- **Uwaga do planu:** przejście na dopasowanie prefiksu wymaga sprawdzenia kolizji prefiksów wśród sześciu pozycji `AppSidebar.vue:33-64` (np. `/budget` vs `/budgets/...` — trasy `budgets/*` w `routes/web.php:26-28` to POST/DELETE, nie strony, ale dopasowanie powinno być po segmencie, nie po surowym `startsWith`).

### C3 — Literały `dark:` w logo i awatarze maskują słaby token (brakujące tokeny) — **średni**

- **Dowód:**
  - `resources/js/components/AppLogo.vue:12`: `text-white dark:text-black` nadpisuje `text-sidebar-primary-foreground` ustawione na rodzicu w `AppLogo.vue:10`.
  - `resources/js/components/UserInfo.vue:27`: `text-black dark:text-white` na `AvatarFallback` (tło `bg-muted` z `ui/avatar/AvatarFallback.vue:17`).
  - Obie klasy łamią `.ai/rules/js.md:10` i `.docs/design-system.md` („Tryb ciemny”: komponent z tokenami nie potrzebuje wariantów `dark:`).
- **Pomiar kolorów (computed):** jasny — glif `rgb(255,255,255)` na `rgb(11,114,132)` (~5,6:1); ciemny — glif `rgb(0,0,0)` na `rgb(31,152,173)` (~6,2:1). Token ciemny `--sidebar-primary-foreground: hsl(0 0% 98%)` (`resources/css/app.css:176`) na `--sidebar-primary: hsl(189 70% 40%)` (`app.css:175`) dałby ~3,3:1 (obliczone z wartości computed; przybliżenie WCAG).
- **Zasięg tokenu:** `sidebar-primary` / `sidebar-primary-foreground` czytają też `resources/js/pages/Welcome.vue` i `resources/js/components/LandingAppPreview.vue` — zmiana wartości w `.dark` zmieni landing.
- **Zrzuty:** `screenshots/before/desktop-expanded-dark.png`, `mobile-sheet-dark.png`.
- **Wpływ na użytkownika:** w trybie ciemnym logo jest czarne, choć system mówi „jasny tekst na akcencie”; usunięcie literału bez poprawki tokenu obniżyłoby kontrast znaku do ~3,3:1. Inicjały awatara ignorują motyw tokenów.

### C4 — Martwy i nieudokumentowany blok tokenów sidebara (brakujące tokeny / źródło) — **niski dla użytkownika, wysoki dla kolejnego agenta**

- **Dowód:** `resources/css/app.css:139` (`--sidebar: hsl(0 0% 98%)`) i `app.css:181` (`--sidebar: hsl(240 5.9% 10%)`) — w `resources/` nie ma `var(--sidebar)`; `bg-sidebar` idzie przez `--color-sidebar: var(--sidebar-background)` (`app.css:62`), a ciemny `--sidebar-background` to `hsl(0 0% 7%)` (`app.css:173`, computed `rgb(18,18,18)` na zrzucie). `.docs/design-system.md` nie wymienia żadnego tokenu `--sidebar-*`, mimo że `.ai/rules/css.md:10` każe zapisywać tam każdą zmianę tokenu.
- **Historia:** wartości `--sidebar-primary`, `--sidebar-accent(-foreground)`, `--sidebar-ring` zmieniono celowo na turkus w commicie 9195e82 (app shell, ADR-0001); `--sidebar-background`, `--sidebar-foreground`, `--sidebar-border` i gołe `--sidebar` pochodzą ze scaffoldu d6196b1.
- **Wpływ:** użytkownik nie widzi różnicy dziś; kolejna osoba edytująca `--sidebar` (nazwa sugeruje „tło sidebara”) nie zobaczy żadnej zmiany i doda literał w komponencie.

### C5 — Pozostałości startera Laravel w powłoce (przypadkowa architektura) — **niski / decyzja produktowa** (docelowy znak produktu: **deferred**, zob. C5a)

- **Dowód:** `resources/js/components/NavFooter.vue` — brak importerów w `resources/js` (import usunięty w 9195e82), klasy palety w `NavFooter.vue:28`. `resources/js/components/AppLogoIcon.vue:16-28` — znak startera Laravel (od d6196b1), renderowany w `AppLogo.vue:12` w nagłówku sidebara i w Sheet. Brak w `.docs/` jakiejkolwiek decyzji o logo.
- **Wpływ na użytkownika:** produkt pokazuje na każdym ekranie znak kitu Laravel, czego US-31 chciał uniknąć dla linków stopki (`.docs/specs/app-shell-openship-visual-language.md:45`). `NavFooter.vue` nie ma wpływu na użytkownika, ale jest wzorcem z kolorami palety, który agent może skopiować.
- **Proponowany podział:** usunięcie `NavFooter.vue` mieści się w tej zmianie; wymiana znaku wymaga decyzji o nowym logo — kandydat na **deferred**, jeśli użytkownik nie dostarczy znaku.

### C5a — Docelowy znak produktu — **deferred**

- Część C5 (znak startera Laravel w `AppLogoIcon.vue:16-28`): w tej zmianie znak jest tymczasowo `Vault` z lucide. Prawdziwe logo to osobna zmiana, bo wymaga dostarczenia znaku.

### C6 — Kontrast tekstu na ciemnym `--primary` — **deferred**

- Biały `--primary-foreground` na `hsl(189 70% 40%)` (ciemny `--primary`) daje ok. 3,3:1 na przyciskach w całej aplikacji, poniżej 4,5:1 dla tekstu.
- Powód odroczenia: zmiana globalnego tokenu leży poza powłoką sidebara i dotknęłaby każdy widok.

### Ustalenie dla kroku „Utrwal to” (nie zarzut)

Żadna reguła agenta nie obejmuje sidebara: `.ai/skills/openship-ui-development/SKILL.md:55` wyłącza „Sidebar IA, Budget year switcher behaviour”, a `.ai/rules/index.md` mapuje `*.vue` tylko na `.ai/rules/js.md`. Regułę o trybie ikon, tokenach `--sidebar-*` i kitchen sinku trzeba dopisać w `.ai/rules/js.md` (przez `record-rule`) oraz w `.docs/design-system.md`.

## Detailed Findings

### Źródło → widoki (tokeny i komponenty)

- Tokeny sidebara są publikowane w `@theme inline` (`resources/css/app.css:62-69`), wartości w `:root` (`app.css:131-139`) i `.dark` (`app.css:173-181`).
- Czytelnicy tokenów poza `ui/sidebar` (grep `-<token>\b` po `resources/js/**/*.vue`):
  - `sidebar-primary`: `AppLogo.vue`, `Welcome.vue`, `LandingAppPreview.vue`
  - `sidebar-primary-foreground`: `AppLogo.vue`, `Welcome.vue`
  - `sidebar-accent`: `NavUser.vue`
  - `sidebar-border`: `AppSidebarHeader.vue`, `BudgetYearSwitcher.vue`, `AppHeader.vue`
  - `sidebar-foreground`, `sidebar-ring`: tylko prymitywy `ui/sidebar`
  - gołe `bg-sidebar` poza prymitywami: `LandingAppPreview.vue:42`
  - `--sidebar`: brak czytelnika
- Importy z `@/components/ui/`: po jednym lub dwóch w każdym z 12 plików powłoki (`AppSidebar`, `AppSidebarHeader`, `AppSidebarLayout`, `NavMain`, `NavFooter`, `NavUser`, `UserInfo`, `UserMenuContent`, `BudgetYearSwitcher`, `AppShell`, `AppContent`); `AppLogo.vue` nie importuje z `ui/` (to znak + nazwa).
- `BudgetYearSwitcher.vue:53-57` używa natywnego `<select class="native-select">` — zgodnie z `.ai/skills/openship-ui-development/SKILL.md` („Replacing native selects with Reka Select” poza zakresem), więc to nie jest zarzut brakującego komponentu.

### Widok → źródło (literały)

Skan wartości zakodowanych na sztywno na 12 plikach powłoki daje 3 trafienia, opisane w C3 i C5:
- `NavFooter.vue:28`
- `AppLogo.vue:12`
- `UserInfo.vue:27`

### Stany i wejścia

- **Rozwinięty, rok istnieje:** select + „Dodaj rok” (`BudgetYearSwitcher.vue:44-89`); select wyłączony podczas `processing` (`:58`) — stan disabled istnieje, brak wskaźnika ładowania poza wyłączeniem.
- **Brak roku (US-11):** ramka przerywana „Brak roku budżetu” + „Dodaj rok” (`BudgetYearSwitcher.vue:21-43`); zrzut `desktop-expanded-empty-years-light.png` — poprawny po rozwinięciu.
- **Mobile 390 px:** sidebar jako Sheet po kliknięciu `SidebarTrigger` (`AppSidebarHeader.vue:21`); zrzuty `mobile-sheet-{light,dark}.png` poprawne. Po otwarciu fokus trafia na link logo, który wygląda jak wyróżniony wiersz (tło akcentu) — kosmetyka, nie zarzut.
- **Fokus klawiatury:** 4× Tab z `/accounts` → link „Dashboard” z widocznym pierścieniem `ring-sidebar-ring` (`ui/sidebar/index.ts:39`); zrzut `desktop-focus-light.png`.
- **Wejście wylogowane:** trasy powłoki są w grupie `auth, verified` (`routes/web.php:20`) — brak zarzutu.

### Metoda zrzutów

Playwright-core 1.64.1 (z cache npx) + Chromium headless przeciw `http://localhost:8080` (Sail, główny checkout; pliki sidebara bez lokalnych zmian), konto `test@example.com`. Tryb ciemny przez cookie `appearance`, zwinięcie przez cookie `sidebar_state=false`. Stan „brak roku” wymuszony w przeglądarce przez zmianę reaktywnych `$page.props.shell.budgetYears=[]`, `budgetYear=null` — bez zapisu do bazy. Zrzuty bazowe na `/accounts`, bo `/dashboard` zwraca 500 w środowisku dev (patrz *Open Questions*).

## Code References

- `resources/js/components/AppSidebar.vue:68-91` — kompozycja powłoki, `collapsible="icon" variant="inset"`
- `resources/js/components/AppSidebar.vue:79` — `BudgetYearSwitcher` w `SidebarHeader`
- `resources/js/components/BudgetYearSwitcher.vue:17-89` — przełącznik, bez wariantu trybu ikon
- `resources/js/components/NavMain.vue:28` — `isCurrentUrl` (dokładne dopasowanie)
- `resources/js/composables/useCurrentUrl.ts:46,61-66` — porównanie i istniejące `isCurrentOrParentUrl`
- `resources/js/components/AppLogo.vue:10-12` — token na rodzicu, literał na glifie
- `resources/js/components/UserInfo.vue:27` — literał na inicjałach
- `resources/js/components/NavFooter.vue:28` — osierocony komponent z paletą
- `resources/js/components/AppLogoIcon.vue:16-28` — znak startera Laravel
- `resources/css/app.css:62-69,131-139,173-181` — publikacja i wartości tokenów sidebara
- `resources/js/components/ui/sidebar/utils.ts:6-8` — szerokości 16rem / 18rem / 3rem

## Architecture Insights

- Prymitywy `ui/sidebar` mają pełną obsługę trybu ikon i tokenów; dryf powstaje wyłącznie w komponentach domenowych dodanych do slotów (`BudgetYearSwitcher`) i w pozostałościach scaffoldu. Plan powinien więc dotykać komponentów domenowych i warstwy tokenów, nie prymitywów (`.docs/design-system.md` „Źródła prawdy”: nie edytować `ui/*` pod jeden ekran).
- Bramka wizualna: istnieje wzorzec kitchen sinka `/dev/ui/dashboard` (`app/Http/Controllers/Dev/DashboardKitchenSinkController.php`, `resources/js/pages/dev/DashboardKitchenSink.vue`) — na razie tylko w niezacommitowanym stanie zmiany `dashboard-ui-contract` w głównym checkoucie, nie na tej gałęzi.

## Historical Context (from prior changes)

- `.docs/specs/app-shell-openship-visual-language.md:24` (US-10) — kontrola roku w nagłówku sidebara „at all times”: **obowiązuje**.
- `.docs/specs/app-shell-openship-visual-language.md:25` (US-11) — stan pusty / „add year”: **obowiązuje**, zrealizowany w `BudgetYearSwitcher.vue:21-43`.
- `.docs/specs/app-shell-openship-visual-language.md:45` (US-31) — usunąć linki startera ze stopki: **zrealizowane** (9195e82), ale `NavFooter.vue` został jako sierota.
- `.docs/specs/app-shell-openship-visual-language.md:59` — lista nav z „Ustawienia”: **częściowo nieaktualne** — Ustawienia usunięto z nav w aa01fe2 (są w menu użytkownika), doszły Plan miesiąca, Lokaty (`.docs/prd.md:223`); spec nie zaktualizowany.
- `.docs/adr/0001-openship-visual-language.md:3` — turkusowy primary, „dark keeps the same tokens”: **obowiązuje**; nie nazywa tokenów `--sidebar-*`.
- `.docs/adr/0031-dashboard-always-shows-current-calendar-year.md:3,5` — kontrolka roku zostaje widoczna i steruje Budżetem/Agendą: **obowiązuje**.
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:71` — zmiana roku przekierowuje na Budżet: **obowiązuje** (zachowanie, nie wygląd).
- Tryb ikon i Sheet: brak wzmianki w `.docs/` i `context/archive/` (przeszukane: `.docs/adr`, `.docs/specs`, `.docs/prd.md`, `.docs/mvp.md`, `context/archive/**`, `context/foundation/*`, `.ai/rules/*`).

## Related Research

- `context/changes/dashboard-ui-contract/research.md` (główny checkout, niezacommitowane) — równoległa zmiana na tym samym źródle tokenów; obie edytują `resources/css/app.css`.

## Open Questions

1. **C1 — co ma pokazywać zwinięty pas?** Opcje dla planu: (a) kompaktowa kontrolka-ikona z rokiem (np. `SidebarMenuButton` z tooltipem otwierającym `DropdownMenu` lat + „Dodaj rok”) — zachowuje US-10 „at all times”; (b) ukrycie w trybie ikon — wymaga świadomej zmiany US-10. Decyzja użytkownika.
2. **C3 — wartość ciemnego `--sidebar-primary-foreground`:** ciemny tekst na turkusie (jak literał dziś) zmieni też `Welcome.vue` / `LandingAppPreview.vue`; alternatywnie przyciemnić `--sidebar-primary` w `.dark`. Wybór wpływa na landing.
3. **C5 — logo:** czy jest znak produktu do podmiany za `AppLogoIcon.vue`? Bez niego wymiana znaku idzie do deferred.
4. **Stan błędu przełącznika:** `BudgetController@selectYear` może zwrócić błąd walidacji, ale `BudgetYearSwitcher.vue:45-73` nie renderuje `InputError` — nie sprawdzono, jaki błąd jest możliwy; do weryfikacji w fazie stanów.
5. **Poza zakresem, ale znalezione:** `/dashboard` zwraca 500 w środowisku dev dla `test@example.com` — `App\Support\MonthPlanReading::__construct(): Argument #4 ($setAsideMinorUnits) must be of type int, null given`, wywołane z `app/Support/MonthPlanProposal.php:104` (`storage/logs/laravel.log`, 2026-11-03 12:24 i 12:25). Kitchen sink sidebara nie powinien zależeć od Dashboardu.
