---
date: 2026-11-05T11:20:00+01:00
researcher: Sebastian Kostecki (sesja Claude Code)
git_commit: 58bcb11d88a1f32dbc4d74b68b58381c17c1d30b
branch: development
repository: household-treasurer
topic: "Wdrożenie tokenów design systemu C · Atrament i morski (tylko warstwa tokenów)"
tags: [research, design-tokens, app.css, sidebar, fonts, contrast, adr]
status: complete
last_updated: 2026-11-05
last_updated_by: Sebastian Kostecki (sesja Claude Code)
---

# Research: Wdrożenie tokenów design systemu C · Atrament i morski

**Date**: 2026-11-05T11:20:00+01:00
**Git Commit**: 58bcb11d88a1f32dbc4d74b68b58381c17c1d30b (gałąź `development`, w working tree niezatwierdzone: `context/changes/design-tokens-atrament/`, `period-report-by-category/`, zmieniony `roadmap.md`)

## Research Question

Zakres: tylko warstwa tokenów z artefaktu „C · Atrament i morski” (`project/README.md`, „Zmiany względem obecnej aplikacji” pkt 1, 2, 4, 6, oraz `project/tokens.json`, sha256 `dde5b632…dbdaf`):
wartości `:root` i `.dark`, 9 nowych ról, `--radius: 10px`, Inter + JetBrains Mono przez `bunny()`, ADR-0035 i aktualizacja `.docs/design-system.md`. Poza zakresem: `rounded-2xl` kart, wysokość kontrolek, `column-caption`, kolor wydatku w Operacjach, wiersz dnia, paleta ⌘K.
Do sprawdzenia: gdzie zmieni się znaczenie `primary` / `sidebar-*` po zamianie turkusu na atrament (kafelek logo, `Welcome.vue`, `LandingAppPreview.vue`) i jak wygląda kontrast w obu motywach.

## Summary

1. **Warstwa tokenów jest mała i mechaniczna.** 34 z 43 nazw ról kolorów z `tokens.json` już istnieje w `:root` w `resources/css/app.css` (brakuje dokładnie dziewięciu nowych) (`:root` od `:101`, `.dark` od `:145`, mapowanie `@theme inline` od `:10`). Jedyne rozjazdy nazw: `chart-1..5` są w `app.css`, a nie ma ich w `tokens.json` (0 użyć w `.vue`/`.ts`), oraz `tokens.json` zapisuje wartości jako hex/rgba, a `app.css` jako `hsl()`.
2. **Zamiana `primary` na atrament odwraca wygląd w ciemnym motywie**: domyślny `Button` (ok. 113 tagów bez `variant`, poza `components/ui`) staje się jasnym przyciskiem z ciemnym tekstem. To zamierzone w systemie, i zamyka odroczoną pozycję C6 z `sidebar-ui-contract` (biały tekst na ciemnym `primary` 3,27:1 → 14,70:1).
3. **Kilka miejsc używa `primary` jako koloru odróżnialnego od `foreground`** i po zamianie traci rozróżnienie: linki `text-primary` (3 miejsca), pasek `MonthPaceMeter`/`CategoryMeterRow` (znacznik `bg-foreground` i wypełnienie `bg-primary` będą tym samym kolorem), aktywne chipy `variant="secondary"` w filtrach Operacji, `Skeleton` (`bg-primary/10`). README systemu zakłada, że role te przejmują `foreground` / `brand-text`, ale sama zamiana tokenów ich nie przepina.
4. **Sidebar: samo przepięcie tokenów daje ciemny granat w obu motywach, ale aktywna pozycja menu nie dostaje morskiego wypełnienia**, bo `ui/sidebar/index.ts:39` koloruje aktywną pozycję tokenem `sidebar-accent` (tym samym co hover), nie `sidebar-primary`. Dwa miejsca z `text-white` na kafelku logo (`Welcome.vue:109,324`) zrobią się niewidoczne (biały na mięcie 1,79:1).
5. **Kontrast tekstów w nowych tokenach jest dobry** (33 sprawdzone pary tekst/tło w każdym motywie mają ≥ 5,03:1 (jasny; najniższa to `destructive` na `destructive-soft`) i ≥ 6,05:1 (ciemny; `muted-foreground` na `muted`)). **Dwa twierdzenia README nie zgadzają się z wartościami**: „ramki pól co najmniej 3:1” (`input`/`border` na `card`: 1,37:1 jasny, 1,32:1 ciemny) i „obecny turkus aplikacji” dla `brand-text` (`#0b6a7b`, a ADR-0001 i spec podają `#0b7285`).
6. **Czcionki**: Bunny serwuje oba kroje z podzbiorem `latin-ext` (sprawdzone zapytaniem `css2`). `tests/Unit/FontStackTest.php` złamie się na zamianie (4 asercje o Geist/`bunny('Geist'`), a ADR-0023 wprost odrzucił „osobny krój mono dla kwot”, więc ADR-0035 musi to zastąpić.
7. **Numer ADR**: 0035 jest następnym wolnym (najwyższy to 0034; luka 0025–0028 i podwójne 0008 istnieją od dawna).

## Detailed Findings

### A. Obecna warstwa tokenów i diff względem `tokens.json`

- `app.css` ma `@theme inline` (`:10-72`), blok `@layer utilities` z drugą definicją `--font-sans` na `body, html` (`:92-98`), `:root` (`:101-143`) i `.dark` (`:145-186`). Wartości są w `hsl()`.
- Radius: `--radius-lg: var(--radius)`, `md = radius − 2px`, `sm = radius − 4px` (`:15-17`), `--radius: 0.75rem` (`:133`). Po zmianie na `10px` wychodzi 10/8/6 px, jak w `tokens.json` (`radius-lg/md/sm`). `rounded-xl` (0,75rem) i `rounded-2xl` (1rem) to domyślne wartości Tailwinda (`node_modules/tailwindcss/theme.css:401-402`) i `--radius` ich nie rusza; `app.css` nie definiuje `--radius-xl`.
- Wpływ radiusa (tylko `rounded-lg|md|sm` w `resources/js` i `resources/css`, wzorzec `grep -rhoE` z granicą słowa): `rounded-lg` 27 wystąpień w 21 plikach, `rounded-md` 32/29, `rounded-sm` 8/8. Z tego w `components/ui`: lg 1 plik, md 15 plików, sm 6 plików. Zmiana: lg 12→10, md 10→8, sm 8→6 px.
- Nowe role z README pkt 1: `brand`, `brand-foreground`, `brand-text`, `warning-soft`, `destructive-soft`, `track`, `border-soft`, `scrim`, `sidebar-strong`. W `app.css` nie istnieje żadna z nich (potwierdzone czytaniem `:10-186`). Wzorzec dodania: `.ai/rules/css.md` („`--name` w `:root` i `.dark` plus `--color-name` w `@theme inline`”). `sidebar-strong` potrzebuje wpisu `--color-sidebar-strong`, inaczej nie powstanie utility `text-sidebar-strong` (blok sidebara w `@theme`: `:63-71`).
- **Żadna z nowych ról nie ma dziś konsumenta.** Odpowiedniki są zrobione inaczej: nakładki dialogu/sheetu to `bg-black/40` i `bg-black/80` (`ui/dialog/DialogOverlay.vue:17`, `ui/sheet/SheetOverlay.vue:16`), pigułki to `bg-destructive/10` i `bg-warning/10` (`ui/badge/index.ts:21-22`). Samo dodanie tokenów publikuje utility, ale niczego nie przepina; to osobna praca per widok/komponent.
- `chart-1..5` (`:57-61`, `:128-132`, `:172-176`) są turkusowo-zielone, nie występują w `tokens.json` i nie mają użyć w kodzie (`grep -rnE "chart-[0-9]"` w `resources/js`, `resources/views`: 0 trafień). Pytanie, czy je zostawić, jest otwarte (sekcja Open Questions).
- `secondary-foreground` dziś jest turkusem (`hsl(189 85% 22%)`, `:111`); nowy token to `foreground`. `accent` dziś `hsl(189 30% 94%)`, nowy `#dcf2ee`.
- `tokens.json` zawiera też tokeny poza zakresem zadania: `shadow-*`, `spacing`, `layout` (sidebar-width, row-height, control-height) i skalę typograficzną. Zadanie wymienia tylko kolory, radius i kroje, więc te pozostają niewdrożone.

### B. Co zmienia znaczenie po zamianie `primary` (turkus → atrament)

Zakres sprawdzenia: `grep -rnE "primary|chart-[0-9]"`, `accent|secondary`, `ring-*`, `var(--…)`, warianty `variant="default|secondary|link"` w `resources/js`, `resources/views`, `resources/css/app.css` (bez `sidebar-primary`). Liczby przycisków to rozbiór regexem, ±1.

**Liczby.** Dosłowne `bg-/text-/border-/ring-primary` i `primary-foreground` (bez `sidebar-primary`): 21 trafień w 11 plikach. `<Button>` bez `variant` poza `components/ui`: ok. 113; z `variant`: ok. 173. `<Badge>` bez `variant`: 0 z 62. `variant="secondary"`/`'secondary'`: 45 linii w 33 plikach (9 na `Badge`). Żaden `dark:` nie dotyczy `primary` ani `accent`; wszystko przełącza się samym tokenem.

**1. Komponenty `components/ui` (domyślne, widoczne wszędzie)**
- `ui/button/index.ts:12` default `bg-primary text-primary-foreground hover:bg-primary/90`; `:21` wariant `link` `text-primary` (jedyne użycie: `pages/Dashboard.vue:221`); `:17-18` `secondary` = `bg-secondary text-secondary-foreground` (dziś turkusowy tekst, po zmianie `foreground`).
- `ui/badge/index.ts:12` default na `primary`; `:14` secondary.
- `ui/checkbox/Checkbox.vue:23` zaznaczony `bg-primary … border-primary`.
- `ui/input/Input.vue:27` i `app.css:208` (`.native-select`): `selection:bg-primary selection:text-primary-foreground` (zaznaczenie tekstu będzie atramentowe w jasnym, prawie białe w ciemnym).
- `ui/skeleton/Skeleton.vue:15` `bg-primary/10` (jedyny „tint” primary poza `hover:/90`): z turkusowego na szaro-granatowy/jasnoszary.
- Konsumenci `accent` (hover/otwarte): `DropdownMenu*Item`, `SelectItem`, `NavigationMenu*`, `DialogContent.vue:46`; pozostają „miękkim morskim”.

**2. Użycia znaczeniowe**
- Wypełnienie „w normie” na paskach: `lib/dashboard-categories.ts:98` i `:148` (`return 'bg-primary'`), `MonthPaceMeter.vue:54` (`isAhead ? 'bg-warning' : 'bg-primary'`), `LandingAppPreview.vue:135`. README mówi, że „w normie” = `foreground` (atrament), a morska kreska to `brand-text`, więc kolor atramentowy jest zgodny z systemem; rozdzielenie `primary` od „wypełnienia” zrobi dopiero zmiana widoków.
- `SegmentedControl` ton `primary` (domyślny): `app.css:252-254`; w ciemnym zaznaczony element to jasny chip.
- Wybrany miesiąc: `BudgetMonthPicker.vue:69` (`'default'` vs `'ghost'`), kropka bieżącego miesiąca `:78` `bg-primary`.
- Filtry Operacji: `OperationsFilters.vue:420,428,445`, aktywny chip to `variant="secondary"`. Granica wypełnienia `secondary` do `card`: 1,09:1 (jasny), 1,07:1 (ciemny) (obliczenie własne), więc aktywny stan będzie słabo odróżnialny od `outline` (wniosek z liczb; nie renderowałem).
- `AppHeader.vue:246` `focus-within:ring-primary`. `AppHeader.vue` jest importowany tylko przez `layouts/app/AppHeaderLayout.vue`, a ten przez nic (`grep -rn AppHeaderLayout resources tests app routes`: brak), więc jest martwy.

**3. `primary` jako kolor tekstu lub cienka kreska na jasnym tle** (zmieni się z turkusu na prawie czerń, więc traci „wygląd linku”): `BudgetMonthStrip.vue:108` (`text-primary … hover:underline`), `DashboardCategorySection.vue:192` (`text-primary hover:text-primary`), `Dashboard.vue:221` (`variant="link"`). README przypisuje „linki w tabelach” do `brand-text`.

**4. Miejsca zakładające, że `primary` ≠ `foreground`**: `MonthPaceMeter.vue:54-58` i `CategoryMeterRow.vue:107` rysują znacznik `bg-foreground` (+ `ring-1 ring-background`) na wypełnieniu `bg-primary`; po zamianie oba mają ten sam kolor i oddziela je tylko obwódka tła.

**5. Inwersja w ciemnym motywie**: każda para `bg-primary text-primary-foreground` staje się jasnym tłem z ciemnym tekstem (`#e6ecf3` / `#111a27`, 14,70:1, wcześniej 3,27:1). Dotyczy domyślnych przycisków, domyślnej odznaki, zaznaczonego checkboxa, chipa `SegmentedControl`, wybranego miesiąca. Pierścień focusu jest wtedy miętowy (`ring` dark `#5fd4c4`) na jasnym chipie.
- Przyciski i odznaki `destructive` mają na stałe `text-white` (`ui/button/index.ts:14`, `ui/badge/index.ts:16`), nie `destructive-foreground`. Nowy ciemny `destructive` `#ff7a80`: biały na pełnym kolorze 2,52:1, ale przycisk ma `dark:bg-destructive/60`, a biały na tym miesza się z `card` jak 5,38:1 (obliczenie własne). Stan hover (`hover:bg-destructive/90`) może w ciemnym przebić `dark:`; kolejność wariantów nie sprawdzona.

**6. Guardy**: `scripts/check-ui-literals.mjs` skanuje stałą listę 47 plików widoków i nie flaguje tokenów (`bg-primary` przechodzi), ani nie skanuje `app.css`, więc zmiana tokenów nie zepsuje `npm run check:ui`. Niezabezpieczone: cały `components/ui/*`, `Welcome.vue`, `LandingAppPreview.vue`, `AppHeader.vue`, `MonthPaceMeter.vue` i inne (raport pracownika, lista imienna).

### C. Sidebar, `Welcome.vue`, `LandingAppPreview.vue`

Zakres sprawdzenia: `resources/js`, `resources/views`, `resources/css`, `.docs/design-system.md`, `.ai/rules/js.md`, `scripts/check-ui-literals.mjs`, `routes/web.php`, `tests/Feature/SidebarKitchenSinkTest.php`. Nie renderowałem aplikacji, wnioski „wygląda” pochodzą z klas i wartości tokenów.

- **Tło**: `Sidebar.vue:27,39,90` `bg-sidebar`; `SidebarProvider.vue:76` `has-data-[variant=inset]:bg-sidebar` maluje też „ramkę” wokół karty treści (aplikacja używa `variant="inset"`, `AppSidebar.vue:75`). Po zmianie ramka będzie granatowa z trzech stron w obu motywach; w ciemnym karta (`background` `#0a111c`) i ramka (`#070c14`) są niemal równie ciemne. To decyzja wizualna do podjęcia.
- **Aktywna pozycja menu**: `ui/sidebar/index.ts:39` ma `data-[active=true]:bg-sidebar-accent … text-sidebar-accent-foreground`, czyli wygląd jak hover. System wymaga `sidebar-primary` (mięta) dla aktywnej. Przy samych tokenach aktywna pozycja będzie `#18273d` na `#0f1b2d` (1,15:1 jasny, 1,12:1 ciemny, obliczenie własne) z jaśniejszym tekstem; wyróżnia ją praktycznie tylko `font-medium` i kolor tekstu. Edycja to trzy klasy w jednym pliku.
- **Kafelek logo** `AppLogo.vue:10`: `bg-sidebar-primary text-sidebar-primary-foreground`, ikona dziedziczy kolor. Po zamianie mięta z ciemnym glifem (`#062a26` na `#5fd4c4`: 8,56:1), działa bez edycji. Dziś: biały na turkusie 5,36:1 (jasny), 3,27:1 (ciemny).
- **`Welcome.vue:109` i `:324`**: `<AppLogoIcon class="… text-white" />` na `bg-sidebar-primary`; po zamianie biały glif na mięcie = 1,79:1 (praktycznie niewidoczny). Wymaga usunięcia `text-white`. Plik nie jest w `check:ui`; to samo skan flaguje `text-white`.
- **`LandingAppPreview.vue:42-53`**: pasek `bg-sidebar` (granat w obu motywach, na białej karcie w jasnym, prawie płasko w ciemnym), kafelek `bg-sidebar-primary` (mięta), nieaktywne kropki `bg-muted-foreground/25` mają na granacie 1,22:1 (jasny) i 1,51:1 (ciemny). `text-sidebar-primary` jako tekst nie występuje nigdzie (`grep`), więc „mięta jako tekst na jasnym” dziś nie ma konsumenta.
- **Teksty na granacie bez jawnego koloru**: nazwa aplikacji (`AppLogo.vue`), imię użytkownika (`UserInfo.vue`), numer roku (`BudgetYearSwitcher.vue:99,128`) dziedziczą `sidebar-foreground` (`#9eacc0`); system przewiduje `sidebar-strong` dla tych czterech (nazwa, użytkownik, pozycja pod kursorem, wybrany rok). Bez edycji komponentów `sidebar-strong` nie ma konsumenta poza `sidebar-accent-foreground`.
- **Awatar**: `ui/avatar/AvatarFallback.vue:17` `bg-muted`, inicjały dziedziczą `sidebar-foreground`: `#9eacc0` na nowym `muted` `#e9edf2` = 1,96:1 (jasny), czyli praktycznie nieczytelne. Dziś tekst był ciemny na jasnym.
- **Nagłówek treści** `AppSidebarHeader.vue:18`: `border-sidebar-border/70` na jasnej karcie; po zmianie `#26364f` przy 70% daje 4,85:1 względem karty (ciężka granatowa linia zamiast delikatnej).
- **`SidebarGroupLabel.vue:19`** `text-sidebar-foreground/70`: 4,34:1 (jasny) i 3,87:1 (ciemny), poniżej 4,5:1; `/80` daje 5,26:1 i 4,73:1.
- **Mobilny `Sheet`** (`Sidebar.vue:39`): obwódka `border-r` z globalnego `border`, więc jasna kreska na granacie (raport pracownika, nie sprawdzone wizualnie).
- **Gdzie `dark:` łączy się z sidebarem**: nigdzie; żaden plik sidebara nie zawiera `dark:` (zgodne z `.ai/rules/js.md`, `.docs/design-system.md:93`).
- **Mechanika ciemnego motywu**: klasa `dark` na `<html>` (`app.css:7`, `HandleAppearance`, `app.blade.php:2,8-19`, `composables/useAppearance.ts`). Wstawka `<style>` w `app.blade.php:23-30` maluje tło przed CSS (`oklch(1 0 0)` / `oklch(0.145 0 0)`) i już dziś nie zgadza się z `--background` (`hsl(0 0% 96.5%)` / `hsl(0 0% 3.9%)`); zmiana sidebara jej nie wymaga.
- **Kitchen sink** `/dev/ui/sidebar` (`routes/web.php:27`, `pages/dev/SidebarKitchenSink.vue`, 4 ramki) nie pokazuje aktywnej pozycji (żaden element nie pasuje do URL), ani mobilnego sheetu, ani stanów hover/otwartego menu; to luka w weryfikacji, jeśli zmiana sidebara ma być sprawdzana zrzutem.
- Dodatkowo twardy kolor poza guardem: `resources/js/app.ts:25` `progress: { color: '#0b7285' }` (pasek postępu Inertia) — turkus sprzed zmiany.

### D. Kontrast (obliczenia własne)

Metoda: WCAG 2.x, luminancja względna z hex (skrypt w `$CLAUDE_JOB_DIR/tmp/contrast.mjs`, wartości z `tokens.json` o sha `dde5b632…`), przy przezroczystości mieszanie z tłem. Dla bieżącej aplikacji konwersja `hsl()`→sRGB.

| Para (tekst/tło) | Jasny | Ciemny | Obecnie jasny/ciemny |
| --- | --- | --- | --- |
| `foreground` / `card` | 17,28 | 14,70 | – |
| `muted-foreground` / `card` | 6,70 | 6,83 | 4,74 / – |
| `muted-foreground` / `muted` | 5,69 | 6,05 | – |
| `primary-foreground` / `primary` | 17,28 | 14,70 | 5,36 / 3,27 |
| `brand-foreground` / `brand` | 8,56 | 8,56 | – |
| `brand-text` / `card` | 6,25 | 10,65 | `primary` 5,60 / 5,50 |
| `accent-foreground` / `accent` | 5,35 | 8,86 | – |
| `success` / `card` | 6,52 | 9,99 | – |
| `warning` / `card`; na `warning-soft` | 5,78; 5,20 | 9,87; 9,08 | – |
| `destructive` / `card`; na `destructive-soft` | 5,90; 5,03 | 6,95; 6,82 | – |
| `transfer` / `card` | 5,98 | 8,38 | – |
| `ring` / `card` | 6,25 | 9,74 | – |
| `sidebar-foreground` / `sidebar-background` | 7,50 | 6,86 | 9,99 / 17,14 |
| `sidebar-strong` / `sidebar-background` | 14,69 | 16,48 | – |
| `sidebar-primary-foreground` / `sidebar-primary` | 8,56 | 8,56 | 5,36 / 3,27 |

Tabela jest wyborem; skrypt sprawdził łącznie 33 pary tekstowych w każdym motywie (z `*-foreground` na pełnych `success`/`warning`/`destructive`), minimum 5,03:1 (jasny) i 6,05:1 (ciemny). Pozostałe sprawdzone pary to elementy nietekstowe, niżej.

**Elementy nietekstowe (WCAG 1.4.11, próg 3:1)**:
- `border` i `input` na `card`: 1,37 (jasny), 1,32 (ciemny); `track` na `card`: 1,24 / 1,32. Zdanie README „kreski, ramki pól i focus co najmniej 3:1” jest więc prawdziwe tylko dla focusu (`ring` 6,25 / 9,74). Obecna aplikacja ma gorzej (`border` na `card` 1,25 / 1,24, `input` 1,22 jasny), więc to nie regres, ale też nie spełnienie deklaracji README.
- `brand` na `card`: 1,79 (jasny), nigdy jako tekst (zgodnie z opisem tokenu); w ciemnym 9,74.
- `ring/50` (3 px `focus-visible:ring-ring/50` w `button`, `badge`, `input`): efektywnie 2,26:1 (jasny), 3,39:1 (ciemny) względem `card`. README zakłada pełny pierścień 2 px; to różnica komponentowa poza zakresem.
- Kafelek logo na jasnej stronie (`Welcome.vue`): mięta `#5fd4c4` na `background` `#edf0f4` 1,57:1 (kontener słaby; mark niesie ciemny glif 8,56:1).
- Pasek atramentowy na torze `muted` (jasny): 14,70:1; sama `track`/`muted` względem karty ~1,1–1,2:1 (dekoracja toru).

### E. Czcionki

- `laravel-vite-plugin` 3.2.0 (`node_modules/laravel-vite-plugin/dist/fonts/index.js:45-66`): `bunny(family, options)`; domyślne `alias` = slug rodziny (`inter`, `jetbrains-mono`), `variable` = `--font-{alias}`, `weights` [400], `subsets` ['latin'], `preload` true. Wiele rodzin to kolejne wpisy w `fonts: [...]` (dziś jeden, `vite.config.ts:15-20`).
- Żądanie do Bunny (`dist/index.js:1003-1019`): `https://fonts.bunny.net/css2?family=<Rodzina+Z+Plusem>:wght@400;500;600&display=swap&subset=latin,latin-ext`. Sprawdziłem to zapytanie bezpośrednio dla obu rodzin: Inter zwrócił 21 bloków `@font-face`, JetBrains Mono 18, z zakresami unicode obejmującymi łaciński rozszerzony (`U+0100-02BA`, w którym leżą ą ć ę ł ń ś ź ż) i `U+2212` (minus) w podzbiorze `latin`. Zatem `weights: [400, 500, 600]`, `subsets: ['latin','latin-ext']` jak dla Geist jest wykonalne; wynik buildu nie był uruchamiany.
- `@fonts` (`resources/views/app.blade.php:37`) renderuje preloady i `<style>` dla wszystkich rodzin z manifestu (`Illuminate\Foundation\Vite::fonts()`, `vendor/laravel/framework/src/Illuminate/Foundation/Vite.php:1084-1115`); dodanie drugiej rodziny dodaje jej preloady (wpływ na ilość preloadów nie mierzony).
- `--font-sans` jest zdefiniowane **dwa razy** w `app.css`: w `@theme inline` (`:11-13`, bez cudzysłowu) i w `@layer utilities body, html` (`:93-98`, z cudzysłowem, relikt scaffoldu startera, zmieniony razem z Geist w 86e2a66). Obie trzeba zmienić albo zlać. `--font-mono` nie istnieje; `font-mono` używa dziś domyślnego stosu Tailwinda w `TwoFactorRecoveryCodes.vue:96` i w 4 plikach kitchen sink (`DashboardKitchenSink`, `SidebarKitchenSink`, `TermDepositsKitchenSink`, `AccountsKitchenSink`), więc one przełączą się na JetBrains Mono.
- Wszystkie wystąpienia „Geist” (poza `node_modules`, `vendor`, wygenerowanym `public/fonts-manifest.dev.json`): `vite.config.ts:18`, `app.css:12,96`, `.docs/design-system.md:3,24`, `.docs/adr/0023-…md`, `.ai/skills/openship-ui-development/SKILL.md:49,64`, `tests/Unit/FontStackTest.php:3,6,11,14`. W `context/archive` i `context/changes` brak.
- **`tests/Unit/FontStackTest.php` złamie się**: wymaga `bunny('Geist'` i `subsets` z `latin-ext` w `vite.config.ts`, `Geist` i braku `Instrument` w `app.css`. Do przepisania na Inter/JetBrains Mono z zachowaniem asercji `latin-ext`.
- Kwoty: `tabular-nums` w 23 plikach / 60 wystąpień w `resources/js` (wzorzec `grep -rn "tabular-nums" resources/js`), plus 8 miejsc w `app.css` (m.in. `:283` `.tabular-amount` bez użycia, `:563` `.operations-cell-amount`, `:618` `.import-preview-cell-amount`). Wspólne punkty: `StatTile.vue:29`, `BudgetAmountCell.vue` (bez `tabular-nums`, dostaje go z klas komórki), `amountToneClass` zwraca tylko kolor. Zdefiniowanie `--font-mono` nie dodaje `font-mono` do żadnego z nich. README opisuje `kpi`/`amount` jako JetBrains Mono, więc zastosowanie na kwotach to osobna zmiana widoków albo część tej (patrz Open Questions).

### F. Dokumentacja, ADR-y, reguły

- **Numeracja**: ADR-y 0001–0024 i 0029–0034 (luka 0025–0028, dwa pliki 0008). Następny: **0035**.
- **Format**: bez frontmattera i statusu; `# Tytuł` + 1–3 akapity (kontekst, decyzja, powód, „We rejected …”). Reguła zapisana w `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:14`.
- **Zastępowanie częściowe**: dwa precedensy. ADR-0023 zastąpił klauzulę ADR-0001 bez edycji ADR-0001 (`0023…md:3`). ADR-0024 dostał dopisaną linię „Superseded in part by ADR-0033” (`0024…md:7`). Obie konwencje istnieją; decyzja, którą stosować dla 0001 i 0023, jest otwarta.
- **Co ADR-0035 częściowo zastępuje**: ADR-0001 („teal primary `#0b7285`”, „fonts stay on the starter stack”), ADR-0003 („Teal stays the brand and action colour”, „does not … adopt ink/near-black primary buttons”), ADR-0023 („a second monospace family is unnecessary”, odrzucone „dedicated mono for amounts”). Pkt 3 README (`rounded-2xl`/`border/60`, ADR-0003) jest poza zakresem tej zmiany, więc ADR-0003 jest zastępowany tylko w klauzuli o kolorze `primary`.
- **`.docs/design-system.md` do przepisania w zakresie**: `:3` (lista ADR), `:24` (Geist → Inter + JetBrains Mono, kwoty mono), `:28-41` tabela znaczeń (`primary (turkus)` → atrament; nowe role brand/soft/track), `:43` zasada 4,5:1 (zapisana w kategoriach red-600/amber-700), `:61-72` tabela i uwagi Sidebara (`sidebar-primary` kafelek/aktywna pozycja, uwaga „3,3:1” `:71` staje się nieaktualna, bo glif i tekst są teraz 8,56:1), `:78-84` „Zmiana systemu”. Fragmenty o `rounded-2xl`, `h-9`, `column-caption`, `border/60` (`:14-19`) zostają, bo są poza zakresem.
- **Reguły i skill**: `.ai/rules/css.md:10` (4,5:1; wymienia red-600/amber-700), `.ai/rules/js.md:10-12` („primary = action and on-track meter”), `:48-51` (sidebar), `.ai/skills/openship-ui-development/SKILL.md:15-18` („Teal exception … `--primary`, `--ring` and sidebar primary stay teal `#0b7285`”), `:49` („UI sans is Geist”), `:56` („no ink primary”), `:62-64` (ADR-0001/0003/0023). Skill wprost zabrania tego, co zmiana wprowadza.
- `.docs/specs/app-shell-openship-visual-language.md:9,17,42,55` opisuje turkus `#0b7285` jako wymaganie historyczne; nie sprawdzano, czy jest traktowany jako żywa specyfikacja.
- `CONTEXT.md`, `README.md`, `AGENTS.md`, `CLAUDE.md`, `.docs/prd.md` nie zawierają wzmianek o turkusie, Geist, radiusie ani rodzinie kroju. Reguła `AGENTS.md` „Domain changes come before code” dotyczy reguł domenowych (`CONTEXT.md`), a nie wyglądu; wymóg zapisu ADR i `design-system.md` przed zmianą kodu pochodzi z README systemu (pkt „Zmiany względem obecnej aplikacji”) i z `.docs/design-system.md:78-84`.

### G. Wzorzec z poprzednich zmian

- `context/archive/2026-11-03-sidebar-ui-contract/research.md:85-88` (C6): „Biały `--primary-foreground` na `hsl(189 70% 40%)` … ok. 3,3:1 … **deferred**”, powód: globalny token poza powłoką sidebara. `plan.md:43-44` powtarza odroczenie i decyzję, że glif na `--sidebar-primary` to grafika (1.4.11, 3:1) i zostaje. Nowe tokeny (8,56:1 w obu motywach) zamykają obie rzeczy.
- `sidebar-ui-contract/plan-brief.md:41` i `plan.md:46`: `Welcome.vue`, `LandingAppPreview.vue` i layouty auth zostały celowo poza kontraktem (z literałami `text-white` w `Welcome.vue:109,324`, `AuthSplitLayout.vue:27`).
- `dashboard-ui-contract/research.md:149`: warstwa tokenów uznana za spójną z `design-system.md` przed tą zmianą; dryf Dashboardu to kompozycja.
- `month-plan-ui-contract/research.md:211-212`: `FontStackTest.php` i `LandingPageSourceTest.php` to precedens testów czytających źródło przez `file_get_contents`.

## Code References

- `resources/css/app.css:10-72` — `@theme inline` (czcionka, radius, mapowanie kolorów i sidebara)
- `resources/css/app.css:92-98` — druga definicja `--font-sans`
- `resources/css/app.css:101-143`, `:145-186` — `:root` i `.dark`; `--radius` `:133`
- `resources/js/components/ui/sidebar/index.ts:39` — aktywna pozycja menu na `sidebar-accent`
- `resources/js/components/ui/button/index.ts:12,14,17-18,21` — default/destructive/secondary/link
- `resources/js/components/ui/skeleton/Skeleton.vue:15` — `bg-primary/10`
- `resources/js/lib/dashboard-categories.ts:98,148`, `components/MonthPaceMeter.vue:54-58`, `components/CategoryMeterRow.vue:107` — paski i znacznik
- `resources/js/pages/Welcome.vue:109,324`; `components/LandingAppPreview.vue:42-53`
- `resources/js/components/UserInfo.vue:25-28`, `ui/avatar/AvatarFallback.vue:17`
- `resources/js/components/AppSidebarHeader.vue:18`, `ui/sidebar/SidebarGroupLabel.vue:19`
- `resources/js/app.ts:25` — `#0b7285` (pasek postępu Inertia)
- `vite.config.ts:15-20` — `bunny('Geist', …)`; `tests/Unit/FontStackTest.php:3-15`
- `resources/views/app.blade.php:23-30,37,44` — pierwszy kolor tła, `@fonts`, `font-sans`
- `.docs/design-system.md:3,24,28-43,61-84`; `.ai/skills/openship-ui-development/SKILL.md:15-18,49,56`

## Architecture Insights

- Zmiana tokenów w `app.css` przechodzi przez Tailwind v4 `@theme inline` jako zmienne CSS, więc komponenty zmieniają wygląd bez edycji; w ciemnym motywie nie ma `dark:` na `primary`/`accent`/`sidebar-*`. Dlatego zarówno zysk (kontrast), jak i ryzyko (inwersja) są szerokie, a jedyne miejsca „semantycznego” wypadnięcia to te, gdzie `primary` było używane zamiast `foreground`/`brand-text`.
- Guardy (`check:ui`, `ui-contract.test.ts`, `MonthPlanHardcodedUiTest`) pilnują literałów w widokach, nie nazw tokenów i nie `app.css`; jedyny test powiązany z tą zmianą to `FontStackTest.php`. CI uruchamia `npm run check:ui`, `npm test`, `composer ci:check` (`.github/workflows/tests.yml`).
- Nowe role bez konsumentów to pierwsza zmiana „najpierw kontrakt, potem piksele” w modelu `/10x-ui`: warstwa tokenów jest samodzielna, a przepinanie komponentów na `brand-text`, `track`, `scrim` itd. jest kolejną zmianą.

## Historical Context (from prior changes)

- `context/archive/2026-11-03-sidebar-ui-contract/research.md:85-88` — odroczone C6 (kontrast `primary` w ciemnym): **zamyka się** tą zmianą (3,27 → 14,70:1 dla `primary`, 3,27 → 8,56:1 dla `sidebar-primary`).
- `…/sidebar-ui-contract/plan.md:44` — „glif to grafika, 3:1 wystarcza”: **przestaje być potrzebne** (osiem i pół do jednego).
- `…/sidebar-ui-contract/plan.md:46` — landing i auth poza kontraktem: **nadal prawda**, a konsekwencją jest `text-white` w `Welcome.vue` (sekcja C).
- `…/dashboard-ui-contract/research.md:149` — tokeny spójne z `design-system.md`: dotyczyło stanu sprzed zamiany, nie ocenia nowych wartości.
- `.docs/adr/0023-…md` — „second monospace family is unnecessary”: **do zastąpienia** w ADR-0035.

## Related Research

- `context/changes/period-report-by-category/` — osobna zmiana (raport okresu), niezależna; nie sprawdzano zależności.
- Brak innego `research.md` o tokenach w `context/changes` i `context/archive` (wyszukiwanie fonts/tokens w `*ui-contract*`).

## Open Questions

Decyzje do podjęcia w `/10x-plan` (nie rozstrzygnięte tu):

1. **Aktywna pozycja menu**: czy jednolinijkowa edycja `ui/sidebar/index.ts:39` (`sidebar-primary` dla `data-[active=true]`) należy do tej zmiany? Bez niej aktywna pozycja jest niemal nieodróżnialna od hovera.
2. **Poprawki konsumentów, które tokeny psują lub unieważniają** (po jednej na wiersz; każda to edycja poza `app.css`): `text-white` w `Welcome.vue:109,324` (glif niewidoczny); inicjały awatara w sidebarze (1,96:1); `AppSidebarHeader.vue:18`; kropki w `LandingAppPreview.vue:52`; `SidebarGroupLabel` `/70`; `progress.color` w `app.ts:25`. Czy zakres „tylko warstwa tokenów” je obejmuje, czy wychodzą do osobnych zmian per widok?
3. **Przepięcie `text-primary` na `text-brand-text`** w linkach (`BudgetMonthStrip.vue:108`, `DashboardCategorySection.vue:192`, `Dashboard.vue:221`) oraz aktywnych chipów `secondary` (`OperationsFilters.vue:420,428,445`): w tej zmianie czy w zmianie widoku?
4. **`--font-mono` dla kwot**: samo zdefiniowanie tokenu, czy też dodanie `font-mono` w 23 plikach z `tabular-nums` (i klasach `app.css`)? README wymaga, ale zadanie mówi „`--font-mono` dla kwot”.
5. **`chart-1..5`**: zostawić turkusowe wartości (0 użyć), przeliczyć na paletę systemu, czy usunąć? `tokens.json` ich nie definiuje.
6. **Format wartości**: hex/rgba z `tokens.json` wpisane literalnie (jedyny plik z kolorami, `app.css` nie jest skanowany przez `check:ui`) czy przeliczone na `hsl()` jak dziś. Zmiana formatu nie wpływa na kontrast; dotyczy diffu i ręcznej weryfikacji.
7. **Nowe role bez konsumentów**: czy zmiana ma też przepiąć istniejące `bg-black/40|80` (nakładki), `bg-warning/10` i `bg-destructive/10` (pigułki) na `scrim`/`*-soft`, czy tylko opublikować tokeny.
8. **Konwencja zastąpienia ADR**: dopisać linię „Superseded in part by ADR-0035” do ADR-0001/0003/0023 (precedens ADR-0024) czy zostawić je bez zmian (precedens ADR-0023 → ADR-0001).
9. **Rozjazdy README ↔ tokeny**: „ramki pól ≥ 3:1” nie zachodzi dla `border`/`input` (1,37 / 1,32); czy ADR-0035 i `design-system.md` powtarzają to twierdzenie, czy je poprawiają (np. dokumentują `border` jako dekoracyjny)? Podobnie „brand-text to obecny turkus”: ADR-0001 podaje `#0b7285`, tokeny `#0b6a7b` (6,25:1 na `card` względem 5,60:1).
10. **`SidebarProvider.vue:76`**: granatowa ramka wokół karty treści w `variant="inset"` (wprost skutek `bg-sidebar`); akceptujemy czy neutralizujemy?
11. **Weryfikacja wizualna**: kitchen sink sidebara nie pokazuje aktywnej pozycji ani mobilnego sheetu; czy rozszerzyć go w tej zmianie (zgodnie z regułą „add a new state there”)?
12. **Niezweryfikowane w tym badaniu** (wymagają `npm run build` i podglądu): kolejność wariantów `hover:bg-destructive/90` i `dark:bg-destructive/60` w ciemnym motywie (przy pełnym kolorze biały tekst daje 2,52:1); realny wygląd aktywnego chipa `secondary`; zachowanie kolejności `hover:`/`data-[active=true]:` w Tailwind v4; wpływ drugiej rodziny na preloady; wygląd pierwszego malowania (`app.blade.php:23-30`).
13. `.docs/specs/app-shell-openship-visual-language.md` (wymóg `--primary` `#0b7285`) i martwy `AppHeader.vue`/`AppHeaderLayout.vue`: aktualizować, zostawić jako historię, czy usunąć? Nie badano, czy spec jest żywym dokumentem.
