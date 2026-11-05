# Tokeny design systemu „C · Atrament i morski” — plan implementacji

## Overview

Warstwa tokenów aplikacji przechodzi na system „C · Atrament i morski” (artefakt Design System na claude.ai: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, `project/tokens.json` sha256 `dde5b6328d7a4da2dbbf3d604166e643b0ede6c459b6f86ddec940864a6dbdaf`). Zmieniają się wartości `:root`/`.dark`, dochodzi 9 nowych ról, `--radius` ma 10 px, a kroje to Inter i JetBrains Mono. Decyzję zapisujemy w ADR-0035 i w dokumentach przed zmianą kodu. Zmiana obejmuje też poprawki konsumentów, które same tokeny by zepsuły, sidebar zgodny z systemem i przepięcie linków na `brand-text`. Realizuje punkty 1, 2, 4 i 6 z sekcji „Zmiany względem obecnej aplikacji” w README systemu.

## Current State Analysis

Pełne ustalenia: `context/changes/design-tokens-atrament/research.md`. Najważniejsze:

- `resources/css/app.css`: `@theme inline` `:10-72`, druga definicja `--font-sans` w `@layer utilities` `:92-98`, `:root` `:101-143`, `.dark` `:145-186`, `--radius: 0.75rem` `:133`. Wartości są w `hsl()`. 34 z 43 ról z `tokens.json` już istnieje; brakuje `brand`, `brand-foreground`, `brand-text`, `warning-soft`, `destructive-soft`, `track`, `border-soft`, `scrim`, `sidebar-strong`.
- Komponenty czytają tokeny przez `@theme inline` bez `dark:`, więc zamiana wartości zmienia całą aplikację. W ciemnym motywie domyślne `Button` (ok. 113 użyć) robią się jasne z ciemnym tekstem; to zamierzone i zamyka odroczone C6 z `sidebar-ui-contract` (3,27 → 14,70:1).
- Elementy, które tokeny psują: `Welcome.vue:109,324` (`text-white` na mięcie, 1,79:1), inicjały awatara w sidebarze (dziedziczą `sidebar-foreground` na `bg-muted`, 1,96:1), `SidebarGroupLabel.vue:19` `/70` (4,34 i 3,87:1), `AppSidebarHeader.vue:18` `border-sidebar-border/70` (ciężka granatowa linia na jasnej treści), kropki `LandingAppPreview.vue:52` `bg-muted-foreground/25` na granacie (1,22:1), `app.ts:25` `progress.color '#0b7285'`.
- Sidebar: aktywna pozycja menu używa `sidebar-accent` (`ui/sidebar/index.ts:39`), tego samego co hover. Nazwa aplikacji (`AppLogo.vue`), użytkownik (`NavUser.vue` → `UserInfo.vue`) i wybrany rok (`BudgetYearSwitcher.vue`) dziedziczą `sidebar-foreground`. `AppSidebar.vue:75` ma `variant="inset"`, więc `bg-sidebar` maluje ramkę wokół treści (`SidebarProvider.vue:76`).
- Linki na `text-primary` (po zmianie atrament, bez wyglądu linku): `BudgetMonthStrip.vue:108`, `DashboardCategorySection.vue:192`, wariant `link` w `ui/button/index.ts:21` (jedno użycie: `Dashboard.vue:221`).
- Kroje: `vite.config.ts:18` `bunny('Geist', { weights: [400, 500, 600], subsets: ['latin', 'latin-ext'] })`; `laravel-vite-plugin` 3.2.0 przyjmuje kolejne wpisy w `fonts: [...]` i opcję `preload` (domyślnie `true`). `tests/Unit/FontStackTest.php` sprawdza Geist i przestanie przechodzić.
- `chart-1..5` nie ma w `tokens.json` ani w kodzie, ale plan `period-report-by-category` (status `plan_reviewed`) używa `--chart-1` dla wydatków i rezerwuje **ADR-0035** na wykresy (9 odwołań w `plan.md` i `plan-brief.md`).
- Pierwsze malowanie `resources/views/app.blade.php:23-30` (`oklch(1 0 0)` / `oklch(0.145 0 0)`) już dziś różni się od `--background`.
- Dokumenty sprzeczne z nowym systemem: ADR-0001 (turkusowe `primary`), ADR-0003 („teal stays”, „no ink primary”), ADR-0023 (Geist, „mono unnecessary”), `.docs/design-system.md`, `.ai/rules/css.md:10`, `.ai/rules/js.md` (primary, Sidebar), `.ai/skills/openship-ui-development/SKILL.md:15-18,49,51,56,62-64`, `.docs/specs/app-shell-openship-visual-language.md:17,55`.

## Desired End State

- `app.css` ma wartości z tabeli „Wartości tokenów” niżej w obu motywach, 9 nowych ról jest opublikowanych jako utility (`bg-brand`, `text-brand-text`, `bg-scrim`, `text-sidebar-strong` itd.), a `--radius` ma `10px` (lg/md/sm = 10/8/6 px).
- Interfejs jest pisany Interem (400/500/600, `latin` + `latin-ext`). `--font-mono` wskazuje JetBrains Mono, który jest ładowany bez preloadu; kwoty jeszcze go nie używają.
- Sidebar jest granatowy w obu motywach i przylega do treści, która leży na `background`. Aktywna pozycja jest miętowa (`sidebar-primary`), także pod kursorem. Nazwa aplikacji, użytkownik i wybrany rok są w `sidebar-strong`. Kitchen sink `/dev/ui/sidebar` pokazuje pozycję aktywną.
- Żaden z sześciu wymienionych elementów nie spada poniżej progu: tekst ≥ 4,5:1, grafika ≥ 3:1. Linki w treści są morskie (`brand-text`).
- ADR-0035 opisuje decyzję, a ADR-0001/0003/0023 mają linię „Superseded in part by ADR-0035”. `design-system.md`, reguły `.ai/rules`, skill i spec są z nią zgodne. Plan raportu wskazuje ADR-0036 i ma notkę o kolorze `--chart-1`.
- Weryfikacja: testy `FontStackTest` i `DesignTokensTest` przechodzą, `composer ci:check`, `npm run check`, `npm run check:ui`, `npm run types:check` i `npm test` są zielone, a zrzuty w obu motywach leżą w `context/changes/design-tokens-atrament/screenshots/`.

### Key Discoveries:

- Wzorzec dodania koloru: `--name` w `:root` i `.dark` plus `--color-name` w `@theme inline` (`.ai/rules/css.md`). `sidebar-strong` potrzebuje `--color-sidebar-strong`, inaczej nie powstanie `text-sidebar-strong` (blok sidebara `app.css:63-71`).
- `UserInfo.vue` jest renderowany w sidebarze (`NavUser.vue:34`) i w białym menu użytkownika (`UserMenuContent.vue:32`). Kolor `sidebar-strong` musi więc przyjść z `NavUser`, a inicjały awatara potrzebują jawnego koloru tekstu, żeby nie dziedziczyły jasnego.
- `AppLogo.vue` jest używany tylko w sidebarze (`AppSidebar.vue:81`) i w martwym `AppHeader.vue:150`.
- `variant="inset"` występuje tylko w `AppSidebar.vue:75`. Kitchen sink i testy go nie ustawiają.
- Precedens testu czytającego źródło: `tests/Unit/FontStackTest.php` (`file_get_contents`).
- Konwencja zastąpienia ADR: dopisana linia „Superseded in part by ADR-NNNN” (`0024-…md:7`). ADR-y są po angielsku, bez frontmattera: `# Tytuł` i 1–3 akapity z „We rejected …”.

## What We're NOT Doing

- Punktów 3, 5, 7 i 8 z README systemu (`rounded-2xl`/`border/60` kart, kontrolki 32 px, `column-caption` → `table-header`, kolor wydatku w Operacjach, wiersze dnia) ani palety ⌘K.
- Kwot w JetBrains Mono (`font-mono` przy `tabular-nums`) i skali typograficznej (`kpi`, `amount`, `page-title`): przejdą przez `/10x-ui` per widok.
- Przepięcia nakładek na `scrim` (`DialogOverlay`, `SheetOverlay`), pigułek i alertów na `warning-soft`/`destructive-soft`, znacznika tempa/postępu na `brand-text` (`MonthPaceMeter`, `CategoryMeterRow`, `dashboard-categories.ts`) ani aktywnych chipów w `OperationsFilters`. Nowe role zostają opublikowane bez tych konsumentów.
- Zmian `chart-1..5`: zostają w obecnych wartościach, kolory wykresów ustala zmiana raportu.
- Tokenów `shadow-*`, `spacing`, `layout`, `radius-xs`/`radius-xl` z `tokens.json`.
- Podnoszenia kontrastu `border`/`input` do 3:1: wartości systemu bierzemy bez zmian, a ADR zapisuje zmierzony kontrast.
- Usuwania martwego `AppHeader.vue`/`AppHeaderLayout.vue` i bloku `@layer utilities` z `--font-sans` (zmieniamy tylko jego wartość).
- Google Fonts: kroje zostają na Bunny przez `bunny()`, jak dziś.

## Implementation Approach

Najpierw dokumenty (Faza 1), bo README systemu i `.docs/design-system.md` („Zmiana systemu”) wymagają zapisu decyzji przed kodem. Potem jedna zamiana warstwy tokenów i krojów z testami źródła (Faza 2). Na koniec konsumenci: regresje, sidebar i linki, a weryfikacja zrzutami obejmuje cały wynik (Faza 3). Wartości wpisujemy jako hex/rgba 1:1 z `tokens.json`, żeby diff dało się porównać z plikiem systemu. Po Fazie 2, a przed Fazą 3, gałąź ma znane regresje z listy wyżej; nie scalamy jej do `main` między fazami.

## Wartości tokenów

Źródło: `tokens.json` (sha256 jak w Overview). `{x}` oznacza tę samą wartość co token `x`; w CSS wpisz `var(--x)` albo tę samą wartość.

| Token | Jasny | Ciemny |
| --- | --- | --- |
| `background` | `#edf0f4` | `#0a111c` |
| `foreground` | `#0f1b2d` | `#e6ecf3` |
| `card` | `#ffffff` | `#111a27` |
| `card-foreground`, `popover-foreground`, `secondary-foreground` | `{foreground}` | `{foreground}` |
| `popover` | `{card}` | `{card}` |
| `muted` | `#e9edf2` | `#19243a` |
| `muted-foreground` | `#4f5d71` | `#95a3b7` |
| `secondary` | `#f3f5f8` | `#16202f` |
| `primary` | `#0f1b2d` | `#e6ecf3` |
| `primary-foreground` | `#ffffff` | `#111a27` |
| `brand` (nowy) | `#5fd4c4` | `#5fd4c4` |
| `brand-foreground` (nowy) | `#062a26` | `#062a26` |
| `brand-text` (nowy) | `#0b6a7b` | `#6fdccd` |
| `accent` | `#dcf2ee` | `#0f2e2a` |
| `accent-foreground` | `{brand-text}` | `{brand-text}` |
| `success` | `#1f6b3a` | `#7fd69b` |
| `success-foreground` | `#ffffff` | `#0a111c` |
| `warning` | `#a14f00` | `#ffb35c` |
| `warning-soft` (nowy) | `#fff1e0` | `#2b1f10` |
| `warning-foreground` | `#ffffff` | `#0a111c` |
| `destructive` | `#c0262d` | `#ff7a80` |
| `destructive-soft` (nowy) | `#fde8e9` | `#2d1416` |
| `destructive-foreground` | `#ffffff` | `#0a111c` |
| `refund` | `{warning}` | `{warning}` |
| `transfer` | `#2f5fbf` | `#8fb3ff` |
| `pair` | `#3b9fd8` | `#5bb6ea` |
| `track` (nowy) | `#e2e7ee` | `#223047` |
| `border` | `#d7dde6` | `#223047` |
| `border-soft` (nowy) | `#e9edf2` | `#19243a` |
| `input` | `{border}` | `{border}` |
| `ring` | `#0b6a7b` | `#5fd4c4` |
| `scrim` (nowy) | `rgba(15, 27, 45, 0.45)` | `rgba(0, 0, 0, 0.6)` |
| `sidebar-background` | `#0f1b2d` | `#070c14` |
| `sidebar-foreground` | `#9eacc0` | `#8b9ab0` |
| `sidebar-strong` (nowy) | `#e8edf4` | `#e6ecf3` |
| `sidebar-accent` | `#18273d` | `#111a27` |
| `sidebar-accent-foreground` | `{sidebar-strong}` | `{sidebar-strong}` |
| `sidebar-primary` | `{brand}` | `{brand}` |
| `sidebar-primary-foreground` | `{brand-foreground}` | `{brand-foreground}` |
| `sidebar-border` | `#26364f` | `#1f2c40` |
| `sidebar-ring` | `{brand}` | `{brand}` |

`chart-1..5` zostają bez zmian. Kroje: `--font-sans: Inter, system-ui, sans-serif` (plus obecne fallbacki emoji), `--font-mono: "JetBrains Mono", ui-monospace, monospace`.

## Critical Implementation Details

- **Kolejność wariantów w sidebarze.** Aktywna pozycja musi zostać miętowa także pod kursorem. Sam `data-[active=true]:bg-sidebar-primary` obok `hover:bg-sidebar-accent` zależy od kolejności wariantów w Tailwind v4, której research nie sprawdził. Dodaj też `data-[active=true]:hover:` dla tła i tekstu, i sprawdź to na zrzucie.
- **Inicjały awatara.** Po dodaniu `text-sidebar-strong` na przycisk w `NavUser` inicjały odziedziczyłyby jasny kolor na `bg-muted` (prawie biały na prawie białym). `AvatarFallback` w `UserInfo.vue` dostaje więc jawny `text-foreground`.

## Faza 1: Decyzja na piśmie

### Overview

ADR-0035 i dokumenty opisują nowy system, zanim zmieni się kod. Plan raportu dostaje nowy numer ADR.

### Changes Required:

#### 1. ADR-0035

**File**: `.docs/adr/0035-atrament-tokens-inter-jetbrains-mono.md` (nowy)

**Intent**: Zapisać wybór kierunku „C · Atrament i morski” (2026-11-05) w zakresie tej zmiany: atramentowe `primary`, miętowy `brand` z `brand-text` `#0b6a7b` do tekstu i kresek, 9 nowych ról, `--radius` 10 px, Inter + JetBrains Mono przez Bunny z `latin-ext` (mono przeznaczony dla kwot, wdrażany per widok), sidebar ciemny w obu motywach z aktywną pozycją w `sidebar-primary`, przylegający do treści (bez `inset`).

**Contract**: Format jak w innych ADR-ach: angielski, `# Tytuł` i 1–3 akapity bez frontmattera. Treść ma zawierać:
- źródło wartości: artefakt i sha `tokens.json`;
- zmierzony kontrast: tekst ≥ 5,03:1 w jasnym i ≥ 6,05:1 w ciemnym; `ring` 6,25 i 9,74:1; `border`/`input` na `card` 1,37 i 1,32:1, czyli linie dekoracyjne, a nie 3:1, choć lepiej niż dziś (1,25/1,24);
- informację, że `brand-text` (`#0b6a7b`) różni się od turkusu ADR-0001 (`#0b7285`);
- zakres: punkty README 3, 5, 7 i 8 wymagają osobnych ADR-ów;
- akapit „We rejected …”: turkusowe `primary` (3,27:1 w ciemnym), Google Fonts (projekt już ładuje kroje z Bunny przez `bunny()`), Geist z `tnum` zamiast mono.

#### 2. Zastąpienia w starszych ADR-ach

**File**: `.docs/adr/0001-openship-visual-language.md`, `.docs/adr/0003-wallet-master-surface-recipe.md`, `.docs/adr/0023-geist-typeface-with-latin-ext.md`

**Intent**: Dopisać na końcu każdego jedną linię „Superseded in part by ADR-0035: …”, która nazywa zastąpioną klauzulę (0001: turkusowe `primary`; 0003: „teal stays the brand and action colour”, odrzucenie atramentowego `primary`; 0023: Geist i „second monospace family is unnecessary”).

**Contract**: Precedens `0024-…md:7`. Reszta treści bez zmian.

#### 3. `.docs/design-system.md`

**File**: `.docs/design-system.md`

**Intent**: Strona zasad ma opisywać nowy system w zakresie tej zmiany.

**Contract**:
- `:3`: lista ADR-ów z ADR-0035, a wzorcem wizualnym jest artefakt „C · Atrament i morski”;
- „Typografia” `:24`: Inter, a JetBrains Mono (`--font-mono`) jest dla kwot, wdrażany per widok, do tego czasu kwoty w Interze z `tabular-nums`;
- tabela „Kolor znaczy coś…” `:28-41`: `primary` (atrament), nowe wiersze `brand`/`brand-foreground`, `brand-text`, `accent`, `warning-soft`/`destructive-soft`, `track`, `border-soft`, `scrim`, z rolami z README systemu;
- zasada kontrastu `:43` bez red-600/amber-700, z liczbami z ADR-0035 i zdaniem, że `border`/`input` są dekoracyjne;
- tabela Sidebara `:61-69`: aktywna pozycja `sidebar-primary`, hover i przełącznik roku `sidebar-accent`, `sidebar-strong` dla nazwy, użytkownika i wybranego roku, ciemny w obu motywach, sidebar przylega do treści;
- usunąć uwagę „ok. 3,3:1” `:71`, bo glif ma teraz 8,56:1.

Fragmenty o `rounded-2xl`, `h-9`, `column-caption`, `border/60` zostają (poza zakresem).

#### 4. Reguły agentów

**File**: `.ai/rules/css.md`, `.ai/rules/js.md`

**Intent**: Reguły nie mogą przeczyć ADR-0035.

**Contract**:
- `css.md`: zdanie o 4,5:1 bez red-600/amber-700 (wartości pochodzą z systemu, sprawdzaj kontrast w obu motywach);
- `js.md`, „Colours come from tokens…”: `primary` = akcja (atrament), `brand` = tożsamość i nawigacja, nigdy status, `brand-text` = linki i kreski;
- `js.md`, „Sidebar”: aktywna pozycja `sidebar-primary`, `sidebar-strong` dla nazwy, użytkownika i roku, bez `variant="inset"`.

Zapis przez `record-rule` z Boost, a gdy narzędzia nie ma, edycja plików wprost. Nagłówki i globy w `.ai/rules/index.md` bez zmian.

#### 5. Skill i spec

**File**: `.ai/skills/openship-ui-development/SKILL.md`, `.docs/specs/app-shell-openship-visual-language.md`

**Intent**: Skill wprost zakazuje tego, co zmiana wprowadza, więc trzeba go przepisać. Spec zostaje jako historia z notką.

**Contract**:
- skill: sekcja „Teal exception” (`:15-18`) → atramentowe `primary`, `brand`/`brand-text` (ADR-0035); „Typography” (`:49-51`) → Inter, JetBrains Mono dla kwot per widok, `latin-ext` zostaje; wiersz „Ink primary” w „Out of scope” (`:56`) usunąć; „References” (`:62-64`) z ADR-0035;
- spec: na górze jedna linia, że kolory i kroje zastępuje ADR-0035.

#### 6. Plan raportu

**File**: `context/changes/period-report-by-category/plan.md`, `plan-brief.md`, `change.md`

**Intent**: ADR wykresów dostaje numer 0036, a zmiana raportu wie, że `--chart-1` to jeszcze stary turkus, którego kolor trzeba ustalić wobec palety z ADR-0035.

**Contract**: Wszystkie odwołania ADR-0035 → ADR-0036, także nazwa pliku `0035-report-charts-shadcn-vue-chart-unovis.md` → `0036-…`. Notka w `change.md` (Notes) i jeden punkt w „Open Risks” w `plan-brief.md`. Bez innych zmian w treści planu i bez zmiany statusu.

### Success Criteria:

#### Automated Verification:

- W `context/changes/period-report-by-category/` nie ma odwołań do ADR-0035 poza notką o tokenach: `grep -rn "0035" context/changes/period-report-by-category/`
- W skillu i regułach nie ma „Geist”, „teal” ani „ink primary” jako zakazu: `grep -rniE "geist|teal exception|ink primary" .ai/`
- Formatowanie i lint przechodzą: `npm run check`

#### Manual Verification:

- ADR-0035 i zmieniony `.docs/design-system.md` opisują decyzje z tego planu

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na potwierdzenie ręcznej weryfikacji.

---

## Faza 2: Warstwa tokenów i kroje

### Overview

`app.css` i `vite.config.ts` dostają wartości i kroje systemu, a testy źródła je pilnują.

### Changes Required:

#### 1. Tokeny kolorów i promień

**File**: `resources/css/app.css`

**Intent**: Wpisać wartości z tabeli „Wartości tokenów” w `:root` i `.dark`, dodać 9 nowych ról i ustawić `--radius: 10px`.

**Contract**: Każda nowa rola to `--name` w `:root` i `.dark` plus `--color-name: var(--name)` w `@theme inline`; `sidebar-strong` w bloku sidebara obok `--color-sidebar-accent-foreground`. Wartości to hex/rgba, a tokeny `{x}` są pisane jako `var(--x)`. `chart-1..5` bez zmian. Komentarze o `--sidebar-background` zostają.

#### 2. Kroje w CSS

**File**: `resources/css/app.css`

**Intent**: `--font-sans` wskazuje Inter w obu miejscach, w których jest dziś zdefiniowany, a nowy `--font-mono` wskazuje JetBrains Mono.

**Contract**: `@theme inline` `:11-13` i `@layer utilities` `:93-98` mają ten sam stos z Interem (fallbacki emoji zostają). `--font-mono` jest w `@theme inline`, więc `font-mono` (dziś używany przez `TwoFactorRecoveryCodes.vue:96` i kitchen sinki) przełącza się na JetBrains Mono.

#### 3. Ładowanie krojów

**File**: `vite.config.ts`

**Intent**: Bunny ładuje Inter i JetBrains Mono z polskimi znakami, a mono nie jest preloadowany, dopóki kwoty go nie używają.

**Contract**: `fonts: [bunny('Inter', { weights: [400, 500, 600], subsets: ['latin', 'latin-ext'] }), bunny('JetBrains Mono', { weights: [400, 500, 600], subsets: ['latin', 'latin-ext'], preload: false })]`.

#### 4. Testy źródła

**File**: `tests/Unit/FontStackTest.php`, `tests/Unit/DesignTokensTest.php` (nowy, Pest, `php artisan make:test --pest --unit DesignTokensTest`)

**Intent**:
- `FontStackTest` sprawdza teraz Inter i JetBrains Mono z `latin-ext` w `vite.config.ts`, Inter i JetBrains Mono w `app.css` oraz brak Geist i Instrument;
- `DesignTokensTest` pilnuje reguły z `css.md`: każde `--color-x: var(--y)` w `@theme inline` ma `--y` w `:root` i w `.dark`, 9 nowych ról jest obecnych, a `--radius` ma `10px`.

**Contract**: Testy czytają pliki przez `file_get_contents`, jak dziś `FontStackTest`. Brak tokenu w jednym motywie ma dać czytelny błąd z nazwą tokenu.

#### 5. Pierwsze malowanie i pasek Inertii

**File**: `resources/views/app.blade.php`, `resources/js/app.ts`

**Intent**: Tło przed załadowaniem CSS zgadza się z `background`, a pasek postępu Inertii nie jest starym turkusem.

**Contract**: `app.blade.php:23-30`: `html` `#edf0f4`, `html.dark` `#0a111c`. `app.ts:25`: `progress.color` przyjmuje `'var(--brand-text)'`, bo Inertia wkleja kolor do wstrzykiwanego CSS. Jeśli zrzut pokaże, że to nie działa, wpisz `#0b6a7b` i zapisz to w ADR-0035.

### Success Criteria:

#### Automated Verification:

- Testy krojów i tokenów przechodzą: `./vendor/bin/sail artisan test --compact tests/Unit/FontStackTest.php tests/Unit/DesignTokensTest.php`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Build generuje kroje i assety: `npm run build`
- Lint i typy frontendu przechodzą: `npm run check` i `npm run types:check`
- Testy frontendu i strażnik literałów przechodzą: `npm test` i `npm run check:ui`
- Pełny gate przechodzi: `./vendor/bin/sail composer ci:check`

#### Manual Verification:

- W zakładce Network widać Inter z podzbiorem latin-ext, a polskie znaki (ą ć ę ł ń ś ź ż) renderują się Interem bez zmiany kroju w środku słowa
- W ciemnym motywie domyślny przycisk jest jasny z ciemnym tekstem, a pierwsze malowanie nie miga innym tłem

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na potwierdzenie ręcznej weryfikacji.

---

## Faza 3: Konsumenci tokenów

### Overview

Poprawki miejsc, które nowe tokeny psują, sidebar zgodny z systemem, linki na `brand-text` i zrzuty całości.

### Changes Required:

#### 1. Regresje kontrastu

**File**:
- `resources/js/pages/Welcome.vue:109,324`
- `resources/js/components/UserInfo.vue:27`
- `resources/js/components/ui/sidebar/SidebarGroupLabel.vue:19`
- `resources/js/components/AppSidebarHeader.vue:18`
- `resources/js/components/LandingAppPreview.vue:52`

**Intent**: Przywrócić czytelność elementom, które same tokeny psują.

**Contract**:
- Welcome: usunąć `text-white` z `AppLogoIcon`, glif dziedziczy `sidebar-primary-foreground`;
- `UserInfo`: `AvatarFallback` z `text-foreground`;
- `SidebarGroupLabel`: `/70` → `/80` (5,26 i 4,73:1);
- `AppSidebarHeader`: obramowanie z `border-border`, bo nagłówek leży na treści, nie na sidebarze;
- `LandingAppPreview`: nieaktywne kropki na tokenie sidebara z kontrastem ≥ 3:1 do `sidebar-background` w obu motywach (np. `bg-sidebar-foreground/60`, policz przed wyborem).

(Pasek Inertii jest w Fazie 2.)

#### 2. Sidebar

**File**:
- `resources/js/components/ui/sidebar/index.ts:39`
- `resources/js/components/AppLogo.vue`
- `resources/js/components/NavUser.vue`
- `resources/js/components/BudgetYearSwitcher.vue`
- `resources/js/components/AppSidebar.vue:75`

**Intent**: Aktywna pozycja jest miętowa, teksty tożsamości mocne, a sidebar przylega do treści.

**Contract**:
- `sidebarMenuButtonVariants`: `data-[active=true]` na `bg-sidebar-primary text-sidebar-primary-foreground`, także z `hover:` (patrz Critical Implementation Details);
- `text-sidebar-strong`: nazwa w `AppLogo.vue`, przycisk w `NavUser.vue` (nie `UserInfo`) i wartości roku oraz „Dodaj rok” w `BudgetYearSwitcher.vue`;
- `AppSidebar.vue`: `variant="inset"` → `variant="sidebar"`.

Bez `dark:` i literałów: pliki są na liście `check:ui`.

#### 3. Stan aktywny w kitchen sinku

**File**: `resources/js/pages/dev/SidebarKitchenSink.vue`

**Intent**: Zrzut pokazuje pozycję aktywną obok spoczynkowej, bo żadna pozycja nie pasuje do URL `/dev/ui/sidebar`.

**Contract**:
- nowa ramka z prawdziwym `SidebarMenuButton`: jeden w spoczynku, jeden z `:is-active="true"`, wewnątrz `SidebarProvider` i `bg-sidebar`;
- wiersz „active” w `stateMatrix` wskazuje tę ramkę;
- `tests/Feature/SidebarKitchenSinkTest.php` przechodzi bez zmian.

#### 4. Linki na `brand-text`

**File**: `resources/js/components/BudgetMonthStrip.vue:108`, `resources/js/components/DashboardCategorySection.vue:192`, `resources/js/components/ui/button/index.ts:21`

**Intent**: Linki w treści zostają morskie zamiast atramentowych.

**Contract**: `text-primary` → `text-brand-text` (w `DashboardCategorySection` także `hover:text-brand-text`). Wariant `link` Buttona → `text-brand-text`.

### Success Criteria:

#### Automated Verification:

- Strażnik literałów przechodzi na plikach sidebara: `npm run check:ui`
- Lint, typy i testy frontendu przechodzą: `npm run check`, `npm run types:check`, `npm test`
- Build przechodzi: `npm run build`
- Test kitchen sinka sidebara przechodzi: `./vendor/bin/sail artisan test --compact tests/Feature/SidebarKitchenSinkTest.php`
- Pełny gate przechodzi: `./vendor/bin/sail composer ci:check`

#### Manual Verification:

- Zrzuty `/dev/ui/sidebar`, `/dashboard`, `/operations` i `/` w obu motywach przy 1280 i 390 px leżą w `context/changes/design-tokens-atrament/screenshots/`
- Aktywna pozycja menu jest miętowa z ciemnym tekstem, także pod kursorem; nazwa, użytkownik i rok są jasne na granacie
- Inicjały awatara są czytelne w sidebarze i w menu użytkownika
- Glif logo na `/` jest widoczny w nagłówku i stopce; kropki podglądu odróżniają się od granatu
- Sidebar przylega do treści bez granatowej ramki; mobilny sheet nie ma jasnej kreski na krawędzi
- Linki „Plan miesiąca” i rozwinięcie kategorii na Dashboardzie są morskie
- W ciemnym motywie przycisk `destructive` (spoczynek i hover) ma czytelny biały tekst; pasek postępu Inertii jest morski

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na potwierdzenie ręcznej weryfikacji.

---

## Testing Strategy

### Unit Tests:

- `FontStackTest`: Inter i JetBrains Mono z `latin-ext` w `vite.config.ts`, brak Geist i Instrument w `vite.config.ts` i `app.css`.
- `DesignTokensTest`: każdy `--color-*` z `@theme inline` ma wartość w `:root` i `.dark`; obecność 9 nowych ról; `--radius: 10px`. Przypadek błędu: rola tylko w jednym motywie daje komunikat z nazwą.

### Integration Tests:

- `SidebarKitchenSinkTest` bez zmian (strona renderuje się lokalnie, 404 poza local).

### Manual Testing Steps:

1. `npm run build` i podgląd przez Sail (`test@example.com` / `password` z `DemoHouseholdSeeder`).
2. Przełącz motyw jasny i ciemny; obejrzyj sidebar (rozwinięty, zwinięty, mobilny), Dashboard, Operacje, stronę `/`.
3. Najedź na aktywną pozycję menu i na przycisk `destructive` w ciemnym motywie.
4. Zrób zrzuty jednorazowym skryptem Playwright (bez commitu skryptu) do `screenshots/`.

## Performance Considerations

Druga rodzina krojów nie dokłada preloadów (`preload: false`); JetBrains Mono jest pobierany dopiero wtedy, gdy strona go użyje (dziś kody odzyskiwania 2FA i kitchen sinki). Przy przejściu kwot na mono w zmianach widoków trzeba rozważyć włączenie preloadu.

## Migration Notes

Brak danych i migracji. Wycofanie to revert commitów fazy. Gałąź nie trafia do `main` między Fazą 2 a 3.

## References

- Research: `context/changes/design-tokens-atrament/research.md`
- System: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (`project/README.md`, `project/tokens.json`)
- Wzorzec testu źródła: `tests/Unit/FontStackTest.php`
- Wzorzec zastąpienia ADR: `.docs/adr/0024-month-remaining-is-not-available-limit.md:7`
- Odroczone C6: `context/archive/2026-11-03-sidebar-ui-contract/research.md:85-88`
- Zależna zmiana: `context/changes/period-report-by-category/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzja na piśmie

#### Automated

- [x] 1.1 W context/changes/period-report-by-category/ nie ma odwołań do ADR-0035 poza notką o tokenach
- [x] 1.2 W skillu i regułach nie ma „Geist”, „teal” ani „ink primary” jako zakazu
- [x] 1.3 Formatowanie i lint przechodzą (npm run check)

#### Manual

- [x] 1.4 ADR-0035 i zmieniony .docs/design-system.md opisują decyzje z tego planu

### Phase 2: Warstwa tokenów i kroje

#### Automated

- [ ] 2.1 Testy krojów i tokenów przechodzą
- [ ] 2.2 Formatowanie PHP (Pint)
- [ ] 2.3 Build generuje kroje i assety
- [ ] 2.4 Lint i typy frontendu przechodzą
- [ ] 2.5 Testy frontendu i strażnik literałów przechodzą
- [ ] 2.6 Pełny gate przechodzi (composer ci:check)

#### Manual

- [ ] 2.7 Inter z latin-ext ładuje się, polskie znaki bez zmiany kroju
- [ ] 2.8 W ciemnym motywie domyślny przycisk jest jasny, pierwsze malowanie nie miga

### Phase 3: Konsumenci tokenów

#### Automated

- [ ] 3.1 Strażnik literałów przechodzi na plikach sidebara
- [ ] 3.2 Lint, typy i testy frontendu przechodzą
- [ ] 3.3 Build przechodzi
- [ ] 3.4 Test kitchen sinka sidebara przechodzi
- [ ] 3.5 Pełny gate przechodzi (composer ci:check)

#### Manual

- [ ] 3.6 Zrzuty w obu motywach przy 1280 i 390 px w screenshots/
- [ ] 3.7 Aktywna pozycja menu miętowa także pod kursorem; nazwa, użytkownik i rok jasne
- [ ] 3.8 Inicjały awatara czytelne w sidebarze i w menu użytkownika
- [ ] 3.9 Glif logo na / widoczny; kropki podglądu odróżniają się od granatu
- [ ] 3.10 Sidebar przylega do treści; mobilny sheet bez jasnej kreski
- [ ] 3.11 Linki na Dashboardzie są morskie
- [ ] 3.12 Ciemny przycisk destructive czytelny w spoczynku i hover; pasek Inertii morski
