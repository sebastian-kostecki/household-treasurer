# Poprawki po 1.4.3: ciemny motyw, pickery i Plan miesiąca — Implementation Plan

## Overview

Plan wykonuje pięć zarzutów audytu `/10x-ui` (C1–C5 w `research.md`) według decyzji D1–D4 z researchu i czterech decyzji z planowania (P1–P4 niżej). Kolejność faz idzie za `/10x-ui`: najpierw warstwa tokenów, potem widoki, na końcu macierz stanów i bramka.

- **Ciemny motyw:**
  - `color-scheme` przełącza natywne kontrolki (C1).
  - Kafelek kategorii i próbka koloru biorą wartości z pary tokenów, z kontrastem ikony ≥ 3:1 dla 24 barw w obu motywach (D4).
  - Logo banku stoi na jasnej płytce (D3).
- **Pickery:**
  - Selecty lokat pokazują `AccountAvatar` (D3).
  - Filtr kategorii Operacji to `CategorySelect` w kolejności listy, z grupami, sekcjami typu, opcją „Wszystkie kategorie” i sufiksem archiwalnej (D2).
- **Plan miesiąca:**
  - Kwoty układają się 2×2 poniżej `sm`, etykieta zawsze stoi nad wartością, a pole planu startuje z separatorem tysięcy (C5a).
  - Odchylenie ma kolor wg statusu w wierszu i w karcie podsumowania (D1).

Każda zmiana zapisanej decyzji ma ADR. Wszystkie cztery powstają w fazie 1, przed pierwszym kodem, w kolejności numerów: ADR-0092 (Plan miesiąca), ADR-0093 (filtr), ADR-0094 (znaki banków i lokaty), ADR-0095 (kolory kategorii).

## Current State Analysis

Audyt jest w `research.md`, z dowodami z przeglądarki w `screenshots/research-*.png`.

- **C1:**
  - `color-scheme` nie występuje w `resources/css/app.css` (`:root` :115-189, `.dark` :191-242) ani w `resources/views/app.blade.php:22-30`.
  - W ciemnym motywie ikona kalendarza znika w 10 polach `type="date"`, a 13 `.native-select` otwiera listę w schemacie jasnym (to ostatnie jest wnioskiem, nieobserwowane w headless).
- **C2:** `categoryTileStyle` (`resources/js/lib/category-appearance.ts:310-318`) dokleja alfę `26` do koloru z danych.
  - Ikona ma ≥ 3:1 dla 9/24 barw w jasnym i 23/24 w ciemnym motywie.
  - Próbki w `CategoryColorPicker.vue:29-48` są w ciemnym motywie nieodróżnialne (1,08–1,33:1 wobec karty).
  - Konsumenci: `CategoryChip.vue:46`, `CategoryColorPicker.vue:39`, `CategoryIconPicker.vue:35`.
  - Paleta przychodzi z serwera (`app/Support/Categories/CategoryColors.php:12-37`). Testu `category-appearance` nie ma.
- **C3:**
  - Filtr to natywny select z optgroup Dochód/Wydatek (`resources/js/components/OperationsFilters.vue:438-478`).
  - `OperationsFilterOptions::categories` (`app/Support/OperationsFilterOptions.php:32-81`) zwraca `{id, name, type, icon, color}` posortowane `usort` po typie i bajtowo po nazwie (:78), bez grupy i bez archiwum.
  - `CategorySelect.vue` nie ma opcji „wszystkie”, sekcji typu ani sufiksu archiwalnej; wzór jest w `AccountSelect.vue:26-69, 94-105` (`'__all__'`, `archivedSuffix`).
  - Kolejność listy liczy `CategoryList/CategoryListOrder::tree()` (`app/Support/CategoryList/CategoryListOrder.php:55`, wejście buduje `ReportCategoryTree::ofType`, `app/Support/ReportCategoryTree.php:29`). `BudgetLine::scopeOrdered` przenosi zarchiwizowane linie na koniec (`app/Models/BudgetLine.php:105-116`), więc do filtra się nie nadaje.
  - ADR-0069:13 i `.ai/rules/reports.md` każą filtrowi zostać płaskim.
- **C4:**
  - `AccountSelect` pokazuje awatary.
  - Trzy natywne selecty lokat (`OperationsFilters.vue:509-525`, `CapitalMovementFormDialog.vue:358-375`, `ImportPreviewRow.vue:558-578`) zostały natywne z decyzji ADR-0054:13. Ich payloady nie mają `bank`/`mark_url`:
    - `OperationsController.php:149-158` `{id, name, opening_date, current_balance_minor_units}`;
    - `OperationsFilterOptions.php:101-112` `{id, name, is_archived}`;
    - `CsvImportController.php:~481-495` `{id, name, opening_date}`.
  - `TermDeposit` to osobny model z niepustym `bank` (enum) i bez `type`. `AccountIdentity::markUrl(?Bank)` (`app/Support/AccountIdentity.php:10-15`) pasuje, a `payload(Account)` nie.
  - Kafelek `AccountAvatar.vue:48-52` to `bg-muted` dla każdej gałęzi, więc logo Revolut ma w ciemnym motywie ok. 1,35:1.
  - ADR-0054 odrzucił „białą płytkę z nowym tokenem” i twierdzi, że kontrast sprawdzono w obu motywach.
- **C5a:**
  - Poniżej `xl` `.month-plan-grid` to `repeat(4, minmax(0,1fr))` z `gap-x-3` (`app.css:857-870`). Przy 390 px kolumna ma ok. 75 px, więc `-123 299,98` się łamie, a pole planu ucina `123456,78`.
  - Komórki „Zatwierdzony”/„Faktycznie” to flex-wrap z etykietą w linii (`app.css:890-903`), a „Plan”/„Odchylenie” mają etykietę `block` (`MonthPlanItemRow.vue:240, 284`).
  - Pole planu startuje z `minorUnitsToMoneyInput` bez separatora (`resources/js/lib/format.ts:78-88`). Wartość pól ustawiają:
    - `changeModeAmountInputs` (`resources/js/lib/month-plan-match-actual.ts:31`): start trybu zmian, wywoływany z `MonthPlan.vue:157` i `MonthPlanSink.vue:215`;
    - `MonthPlan.vue:104` (start odczytu i propozycji, reset :138, :150, :177), `:121` (pole odkładania), `:171` („Ustaw plan na wydatki”);
    - `pages/dev/MonthPlanSink.vue:155`, `:219`, `:236`, `:520`.
  - `MonthPlan.vue:199` to fallback wewnątrz `parsedAmount()`, czytany tylko przez parser i niewidoczny. Wykrywanie zmian i stan „raised” porównują sparsowane grosze (`MonthPlan.vue:218, 438`, `month-plan-match-actual.ts:63-67`), nie teksty.
  - ADR-0060 twierdzi, że cztery kolumny mieszczą się przy 390 px.
- **C5b:**
  - `deviationClass` (`MonthPlanItemRow.vue:116-130`): `null` → muted, `overPlan` → destructive, dochód > 0 → success, reszta bez koloru.
  - `MonthSummaryCard.vue:75-80` koloruje tylko „gorzej niż plan”.
  - Znak odchylenia to faktycznie − plan (`app/Support/MonthItemDeviation.php:40`).
  - **Fiksury sinka mają znak odwrotny:** `resources/js/lib/month-plan-fixtures.ts:48` `runningExpense` ma +120 000 przy planie 200 000 i fakcie 80 000; tak samo `evenAnnualLeft` :133, `evenAnnualUsedUp` :156, `evenAnnualOver` :178, `outsidePlanExpense` :332, `overPlanExpense` :346 i `longNameExpense` ~:357. `waitingIncome` :108 jest poprawny.
- **Bramka:**
  - `toHaveScreenshot` nie istnieje w repo. Bramką są kitchen sinki `/dev/month-plan`, `/dev/operations`, `/dev/ui/accounts`, `/dev/ui/dialogs`, `/dev/import-preview` oraz strażnicy `npm run check:ui` (`scripts/check-ui-literals.mjs`, odrzuca też każde `dark:`) i `tests/Unit/*HardcodedUiTest.php`.
- **Baza:** worktree stoi na `development` c64ccd6. `development` ma już 4 commity rhythm-hints (do `7465d36 chore(archive): close rhythm-hints`), które dotykają m.in. `tests/e2e/irregular-collected.spec.ts`, `DialogsKitchenSink.vue`, `CategoryListFormDialog.vue` i `lang/pl/ui.php`. Lokalny `development` ma niewypchnięte commity wobec `origin/development`.
- **ADR:** ADR-0090 zajmuje niezmergowana gałąź `worktree-collected-coverage`, ADR-0091 jest na `development`, więc wolne są numery od 0092.

## Desired End State

- W ciemnym motywie:
  - Ikona kalendarza w każdym polu daty jest jasna.
  - Lista natywnego selecta otwiera się w schemacie ciemnym.
  - 24 próbki koloru kategorii pokazują pełny kolor.
  - Ikona na kafelku kategorii ma ≥ 3:1 dla każdej barwy palety w obu motywach (sprawdza to test).
  - Logo Revolut i Lokaty Facto stoją na jasnej płytce i są widoczne.
- Lokaty w filtrze Operacji, w oknie ruchu kapitału i w wierszu importu wybiera się z listy z `AccountAvatar`, tak jak konta. Chip aktywnego filtra lokaty pokazuje awatar `xs`.
- Filtr kategorii Operacji pokazuje:
  - „Wszystkie kategorie”, a pod nią sekcje Dochody i Wydatki;
  - w sekcjach kategorie w kolejności listy Kategorii, z nagłówkami grup i `CategoryChip`;
  - kategorię z sufiksem „(zarchiwizowana)”, gdy jej linia w budżecie wybranego roku jest zarchiwizowana. Bez roku liczy się najnowsza linia, a bez linii kategoria listy.
- Plan miesiąca:
  - Przy 390 px kwoty stoją 2×2 (Zatwierdzony | Plan, Faktycznie | Odchylenie), od `sm` do `xl` w czterech kolumnach, a etykieta stoi zawsze nad wartością.
  - Pole planu startuje z `8 500,00`.
  - Wydatek poniżej planu bez przekroczenia ma odchylenie `success`, wiersz przekroczony `destructive`.
  - Karta podsumowania minionego miesiąca koloruje różnicę wg korzyści.

Weryfikacja: każdy stan jest widoczny w swoim sinku, zrzuty jasny/ciemny 1440 i 390 px leżą w `context/changes/v1-4-3-fixes/screenshots/`, a `composer ci:check` przechodzi na PostgreSQL razem z `vendor/bin/sloppy ci`.

### Key Discoveries:

- `CategoryAppearance::group()` (`app/Support/CategoryAppearance.php:35-41`) już daje `{id, name}|null` dla `CategorySelect` w oknach operacji. Filtr bierze grupę stąd.
- Sufiks archiwalnej w oknach operacji jest doklejany do `name` przez `withSelectedCategory` (`resources/js/lib/operations-category-options.ts:14-37`). Filtr dostaje flagę `is_archived`, a sufiks dokleja komponent. `name` z serwera zostaje czystą nazwą, bo test `OperationsFilterTest.php:481-482` i chip aktywnego filtra jej oczekują.
- `parseMoneyInputToMinorUnits` usuwa wszystkie białe znaki (`format.ts:91`), więc wartość z separatorem odczytuje się bez zmian w parserze.
- Testy `OperationsFilterTest` używają nieścisłego `where`, więc nowe klucze ich nie łamią. Tylko `:237-239` zależy od kolejności.
- Specy E2E na `/operations` szukają `combobox` „Kategoria wydatkowa”. Filtr „Kategoria” nie pasuje do tej nazwy (dopasowanie po podciągu działa w drugą stronę).
- Precedens `color-mix` z `var()` jest w `app.css:1042-1070` (`.quiet-scrollbar`). Nigdzie nie ustawia się zmiennej CSS przez `:style`; `--category-color` będzie pierwsza.

## What We're NOT Doing

- Etykieta „Odchylenie” zostaje. Bez „zostało / przekroczono” i bez zmian w `glossary.md` (D1).
- Paleta poleceń zostaje płaska. ADR-0093 zmienia ADR-0069 tylko dla filtra Operacji.
- Sufiksu archiwalnej nie ma w chipie aktywnego filtra kategorii ani lokaty. Nie ma też awatara konta w wierszu listy Operacji (ADR-0054 bez zmian w tym punkcie).
- Logo dla ING, Pekao i Santander nie dochodzi (`Bank::markPath()` bez zmian).
- Pole planu nie przeformatowuje tekstu przy wyjściu z pola (P3): wpisane „12000” zostaje do zapisu.
- `aria-label` próbek koloru (dziś numer) zostaje bez nazw kolorów.
- Bez `toHaveScreenshot` i bez nowych zależności. Bramka to sinki i zrzuty.
- Kontrast kafelka liczony jest wobec `card`. Inne powierzchnie (popover, wiersz `muted`) sprawdza się tylko wzrokowo w sinkach.
- Pozostałe natywne selecty (rok, mapowanie kolumn importu) zostają natywne. C1 naprawia je przez `color-scheme`.

## Implementation Approach

Decyzje z planowania (dopisane do D1–D4 z researchu):

- **P1 (archiwum w filtrze):** znacznik z linii budżetu wybranego roku. Bez roku z najnowszej linii, a gdy kategoria nie ma linii (wybrana bez operacji), z `categories.archived_at`. To reguła raportów (ADR-0074, raport 12 miesięcy). Kategoria zostaje na swoim miejscu listy, bez osobnej sekcji archiwum.
- **P2 (układ kwot):** 2×2 poniżej `sm`, cztery kolumny od `sm` do `xl`, układ desktopowy od `xl` bez zmian. Etykieta stoi nad wartością we wszystkich czterech komórkach.
- **P3 (separator):** każda wartość, którą strona wpisuje do pola w kolumnie Plan (start, fallback, „Ustaw plan na wydatki”, kwota odkładania), idzie przez jeden formatter z separatorem. Tekst wpisany ręcznie zostaje taki, jak go wpisano.
- **P4 (karta podsumowania):** ta sama reguła korzyści co D1. Jeden moduł w `lib` daje ton wiersza i ton karty.

Tokeny idą pierwsze (faza 1), bo fazy 2–4 z nich korzystają (płytka znaku w selectach lokat, kafelek kategorii w filtrze). Faza 1 zapisuje też wszystkie cztery ADR-y, więc numery idą po kolei, a każda faza implementuje decyzję zapisaną wcześniej. Każda faza dopisuje swoje stany do sinka. Faza 5 tylko sprawdza macierz, robi zrzuty i utrwala reguły.

## Critical Implementation Details

**Rebase przed pierwszą zmianą.** `development` ma 4 commity więcej (rhythm-hints, w tym `irregular-collected.spec.ts`, `DialogsKitchenSink.vue`, `CategoryListFormDialog.vue` i `lang/pl/ui.php`, które ta zmiana też dotyka). Wg pamięci projektu nie pushujemy, dopóki `development` ma niewypchnięte commity.

**Płytka znaku tylko pod logo.** Jasna płytka w `.dark` dotyczy wyłącznie gałęzi logo (`mark_url` bez błędu). Skrót `mark-label` ma `text-foreground`, w ciemnym motywie jasny, więc na jasnej płytce zniknąłby. Gałęzie skrótu, `Coins` i `Building2` zostają na `muted`.

**Siatka 2×2 a wiersze bez czterech kwot.** Wiersz grupy kategorii (`MonthPlanGroupRow.vue:37-40`, tylko Plan i Faktycznie) i wiersz odkładania (`MonthPlanTable.vue:381-454`: kategoria, rodzaj tylko w edycji, Plan, status) dzielą `.month-plan-grid`. Wiersze sum (`MonthPlanTable.vue:287, 352`) to flex `.month-plan-total-row` i siatki nie dotyczą. Wiersz nagłówka (`MonthPlanTable.vue:251-272`) jest widoczny dopiero od `xl` (`app.css:920-928`), więc 2×2 go nie dotyczy. Przy obszarach `'approved plan' / 'actual deviation'` Plan grupy stanąłby u góry po prawej, a Faktycznie u dołu po lewej. Te wiersze potrzebują własnych obszarów poniżej `sm` (np. `'plan actual'`) i trzeba je sprawdzić w sinku przy 390 px.

**Mieszanie kolorów w `srgb`.** Test kontrastu liczy kolory dokładnie tylko wtedy, gdy CSS używa `color-mix(in srgb, …)`. W `srgb` mieszanie to liniowa interpolacja składowych zakodowanych gammą. Mieszanie z `transparent` daje alfę, którą test składa na `--card`. `oklab` dałby inne liczby, niż policzy test.

## Phase 1: Tokeny motywu

### Overview

Rebase, cztery ADR-y (0092–0095) i warstwa tokenów dla C1, D4 i części D3. Po tej fazie ciemny motyw przełącza natywne kontrolki, kafelek i próbka kategorii biorą wartości z tokenów, a logo banku ma płytkę.

### Changes Required:

#### 1. Rebase

**File**: gałąź `worktree-v1-4-3-fixes`

**Intent**: Wyrównać bazę z `development` przed pierwszą edycją kodu.

**Contract**:
- Najpierw commit planu i briefu (`docs(v1-4-3-fixes): plan`, z `change.md` i ewentualną roadmapą), bo rebase wymaga czystego drzewa.
- Potem `git rebase development`. Commity dokumentacji zmiany zostają nad `development`.

#### 2. ADR-0092–0095

**File**:
- `context/foundation/adr/0092-month-plan-deviation-tone-and-amounts-below-sm.md`
- `0093-operations-category-filter-like-the-picker.md`
- `0094-bank-mark-plate-and-term-deposit-avatars.md`
- `0095-category-colour-in-both-themes.md`

**Intent**: Zapisać wszystkie cztery decyzje przed pierwszym kodem, w kolejności numerów. Fazy 3 i 4 implementują ADR-0093 i ADR-0092 i aktualizują tylko reguły i stronę design systemu.

**Contract**:
- **ADR-0092** i **ADR-0093**: treść jak w fazie 4, sekcja 1, i fazie 3, sekcja 1.
- **ADR-0094** zastępuje w części ADR-0054:
  - zdanie o selectach lokat („stay native…”);
  - odrzucenie „a white tile with a new token”;
  - zdanie „Contrast: … with no known limitation”, podważone pomiarem Revolut ok. 1,35:1.
  
  Ustala token płytki logo w obu motywach i to, że płytka jest tylko pod logo. Selecty lokat zostają reka `Select` z `AccountAvatar` `sm`, z payloadem `bank` i `mark_url`, a chip filtra lokaty dostaje awatar `xs`.
- **ADR-0095:**
  - kolor kategorii z pary tokenów (`--category-tile-tint`, `--category-icon-tint`) mieszanych w `srgb`;
  - ikona ≥ 3:1 wobec kafelka na `card` dla 24 barw w obu motywach, pilnowane testem;
  - próbka w `CategoryColorPicker` w pełnym kolorze;
  - kolor kategorii nadal tylko tożsamość (ADR-0071, `design-system.md:66`).
  
- Numery: następne wolne w chwili zapisu. Jeśli 0092–0095 ktoś już zajął na `development`, przesuń wszystkie cztery i popraw odwołania w planie.
- Lista ADR w nagłówku `design-system.md` dostaje 0092–0095.

#### 3. `color-scheme` (C1)

**File**: `resources/css/app.css`, `resources/views/app.blade.php`

**Intent**: Przeglądarka rysuje kontrolki UA (kalendarz, lista selecta, paski przewijania) w schemacie motywu.

**Contract**:
- `color-scheme: light` w `:root`, `color-scheme: dark` w `.dark`.
- Ta sama para w stylu inline `app.blade.php` (`html` / `html.dark`), żeby nie było błysku przed CSS.
- Klasy komponentów bez zmian.
- Asercja w `resources/js/ui-contract.test.ts`, że oba bloki tokenów mają `color-scheme`.

#### 4. Kafelek i próbka kategorii (D4)

**File**: `resources/css/app.css`, `resources/js/lib/category-appearance.ts`, `resources/js/components/CategoryChip.vue`, `CategoryColorPicker.vue`, `CategoryIconPicker.vue`

**Intent**: Usunąć literał alfy `26` i dać obu motywom własne wartości kafelka i ikony. Próbka pokazuje pełny kolor.

**Contract**:
- `categoryTileStyle(color)` zwraca tylko `{ '--category-color': color }`.
- Nazwane klasy w `@layer components`:
  - `.category-tile`: tło `color-mix(in srgb, var(--category-color) var(--category-tile-tint), transparent)`, kolor ikony `color-mix(in srgb, var(--category-color) var(--category-icon-tint), var(--foreground))`;
  - `.category-swatch`: tło `var(--category-color)`.
- `--category-tile-tint` i `--category-icon-tint` mają wartości w `:root` i `.dark`, dobrane tak, żeby test przeszedł.
- `CategoryChip` i `CategoryIconPicker` używają `.category-tile`, a `CategoryColorPicker` `.category-swatch`. Stan wybrany i fokus próbki zostają.

#### 5. Test kontrastu kafelka

**File**: `tests/Unit/CategoryTileContrastTest.php` (nowy, `php artisan make:test --pest --unit`)

**Intent**: Regresja D4. Pilnuje, że żadna barwa palety nie ma ikony poniżej 3:1 w żadnym motywie.

**Contract**:
- Czyta barwy z `CategoryColors`, a `--card`, `--foreground` i oba procenty z bloków `:root` / `.dark` w `app.css`.
- Liczy tło (kolor kategorii z alfą = tint, złożony na `card`) i ikonę (mieszanina w `srgb` z `foreground`).
- Asercja: kontrast WCAG 2.x ≥ 3:1 dla 48 par, a komunikat błędu nazywa barwę i motyw.
- Przed zmianą tokenów test musi być czerwony dla obecnych wartości (`26` = 15%, ikona 100%): jasny `#eab308` 1,74:1, ciemny `#be185d` 2,69:1.

#### 6. Płytka logo banku (D3, tokeny)

**File**: `resources/css/app.css`, `resources/js/components/AccountAvatar.vue`

**Intent**: Logo z ciemnymi znakami jest widoczne w ciemnym motywie.

**Contract**:
- Token `--bank-mark-plate` w `:root` (wartość jak `--muted`) i w `.dark` (jasna powierzchnia), opublikowany w `@theme inline` jako `bg-bank-mark-plate`.
- `AccountAvatar` ustawia go tylko w gałęzi logo; pozostałe gałęzie zostają na `bg-muted`.
- Kontrast logo Revolut (`#000000`) i Lokaty Facto (`#00569D`) na płytce ≥ 3:1 w obu motywach, z liczbami zapisanymi w ADR-0094.

#### 7. Design system

**File**: `context/foundation/design-system.md`

**Intent**: Nowe tokeny i znaczenia na stronie systemu.

**Contract**:
- `:66`: „tło 15%” zastąpione opisem tokenów kafelka i próbki, z ADR-0095.
- `## Tryb ciemny`: zdanie o `color-scheme`.
- Nowy wiersz płytki znaku przy `AccountAvatar` w „Gotowe elementy” (ADR-0094).

### Success Criteria:

#### Automated Verification:

- `CategoryTileContrastTest` jest czerwony przed zmianą tokenów i zielony po niej: `php artisan test --compact tests/Unit/CategoryTileContrastTest.php`
- `npm test` (w tym asercja `color-scheme` w `ui-contract.test.ts`) przechodzi
- `npm run check:ui` i `npm run types:check` przechodzą
- `php artisan test --compact tests/Unit/AccountsHardcodedUiTest.php tests/Unit/MonthPlanHardcodedUiTest.php` przechodzi

#### Manual Verification:

- W ciemnym motywie na `/operations` (filtry rozwinięte) ikona kalendarza w polach daty jest jasna, a lista roku otwiera się ciemna (Chrome/Edge na Windows)
- `/dev/ui/dialogs?open=category-list-new` w ciemnym motywie pokazuje 24 różne pełne kolory, a w jasnym chipy z żółtą barwą mają czytelną ikonę
- `/dev/ui/accounts`, sekcja „Znaki banków”: Revolut i Lokaty Facto widoczne w ciemnym motywie, skróty ING/Pekao/Santander i `Coins` bez zmian

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Lokaty z awatarem

### Overview

Reszta D3: payloady lokat dostają `bank` i `mark_url`, trzy natywne selecty lokat stają się `AccountSelect`, a chip filtra lokaty pokazuje awatar.

### Changes Required:

#### 1. Payload lokaty

**File**: `app/Support/AccountIdentity.php`, `app/Http/Controllers/OperationsController.php`, `app/Support/OperationsFilterOptions.php`, `app/Http/Controllers/CsvImportController.php`

**Intent**: Jedno miejsce buduje identyfikację lokaty dla pickerów, zamiast trzech kopii kluczy.

**Contract**:
- `AccountIdentity::termDepositIdentity(TermDeposit $termDeposit): array{bank: string, mark_url: string|null}`.
- Trzy payloady (`termDeposits` w Operacjach, `filterTermDeposits`, `termDeposits` w imporcie) dostają te dwa klucze obok obecnych.
- Kolejność, filtry archiwum i pozostałe klucze bez zmian.

#### 2. Typy i wybór lokaty

**File**: `resources/js/lib/account-identity.ts`, `resources/js/components/OperationsFilters.vue`, `CapitalMovementFormDialog.vue`, `ImportPreviewRow.vue`, `resources/js/lib/import-preview-rows.ts`

**Intent**: Lokatę wybiera się tym samym komponentem co konto, z awatarem.

**Contract**:
- Funkcja `termDepositPickerOptions(deposits)` mapuje lokaty na `AccountPickerOption` z `type: 'bank'` (wzór: `pages/TermDeposits.vue:222-227`).
- Typy `OperationsFilterTermDeposit`, `CapitalMovementFormTermDeposit`, `TermDepositRow` dostają `bank` i `mark_url`, a filtr także `is_archived`, który serwer już wysyła.
- Trzy natywne selecty → `AccountSelect`:
  - filtr: `all-option-label` „Wszystkie lokaty” i `archived-suffix` jak w filtrze kont;
  - ruch kapitału: `name="term_deposit_id"`, `required` i placeholder; domyślnie pierwsza lokata jak dziś;
  - wiersz importu: `name="rows[i][term_deposit_id]"`, `aria-label` z `controlLabel`, `disabled` przy braku kwalifikujących się lokat, placeholder `emptyTermDepositLabel()`.
- Nazwy pól i wybrane wartości bez zmian. Wysyłka zmienia się w dwóch miejscach, bo `AccountSelect` wysyła wartość przez `<input type="hidden">` (`AccountSelect.vue:123-129`), tak jak dziś pole konta:
  - przeglądarka nie sprawdza `required` na hidden inpucie, więc pusty ruch kapitału zatrzymuje dopiero walidacja serwera (błąd pola `term_deposit_id`);
  - wyłączony wiersz importu wysyła `rows[i][term_deposit_id]=''` zamiast pominąć pole; `StoreCsvImportRequest.php:28,54` to przyjmuje.

#### 3. Chip filtra lokaty

**File**: `resources/js/components/OperationsActiveFilterChips.vue`

**Intent**: Chip lokaty wygląda jak chip konta.

**Contract**: `ChipTermDeposit` dostaje `bank` i `mark_url`, a `FilterChip` pole `termDeposit?`, renderowane jako `AccountAvatar` `xs` `type="bank"` przed nazwą.

#### 4. Fiksury sinków

**File**: `resources/js/lib/operations-fixtures.ts`, `operation-dialogs-fixtures.ts`, `import-preview-fixtures.ts`

**Intent**: Sinki pokazują lokaty z logo, ze skrótem i po błędzie obrazu.

**Contract**: lokaty fiksur mają `bank` i `mark_url`: Lokaty Facto z logo, Revolut z logo, ING bez logo, a w filtrze także jedna zarchiwizowana.

#### 5. Testy payloadów

**File**: `tests/Feature/OperationsFilterTest.php` (`filterTermDeposits`), `tests/Feature/CsvImportTest.php` (`termDeposits` importu), `tests/Feature/CapitalMovementTest.php` (`termDeposits` strony Operacji; dziś żaden test tego propa nie sprawdza)

**Intent**: Prop zmienia się po obu stronach w jednym commicie, z `assertInertia` (`.ai/rules/inertia-props.md`).

**Contract**:
- Asercje `bank` i `mark_url` dla lokaty banku z logo i bez logo (`mark_url` = `null`) w trzech propach.
- Każda asercja jest czerwona przed zmianą payloadu.
- Asercje wysyłki, które pilnują zmiany z sekcji 2 (zielone przed zmianą i po niej):
  - ruch kapitału bez `term_deposit_id` → błąd pola `term_deposit_id` (`CapitalMovementTest.php`);
  - import z pustym `rows[i][term_deposit_id]` dla wiersza bez kwalifikujących się lokat zapisuje się jak dziś (`CsvImportTest.php`).

### Success Criteria:

#### Automated Verification:

- Nowe asercje payloadów są czerwone przed zmianą i zielone po niej: `php artisan test --compact tests/Feature/OperationsFilterTest.php tests/Feature/CsvImportTest.php tests/Feature/CapitalMovementTest.php`
- `npm run types:check`, `npm test`, `npm run check:ui` przechodzą
- `php artisan test --compact tests/Unit/ImportPreviewHardcodedUiTest.php` przechodzi
- `vendor/bin/pint --dirty --format agent` i `vendor/bin/sloppy ci` bez nowych znalezisk
- Asercje wysyłki lokat (ruch kapitału bez lokaty, import bez kwalifikujących się lokat) przechodzą przed zamianą selectów i po niej

#### Manual Verification:

- `/dev/operations`: filtr lokat z awatarami, „Wszystkie lokaty”, zarchiwizowana z sufiksem; chip lokaty z awatarem `xs`
- `/dev/ui/dialogs?open=capital-new` i `capital-errors`: lista lokat z awatarami, błąd pola przy braku wyboru
- `/dev/import-preview`: wiersz z lokatą, wiersz bez kwalifikujących się lokat (wyłączony) i nawigacja klawiaturą po wierszu
- Prawdziwy zapis ruchu kapitału i importu z lokatą w podglądzie worktree kończy się jak przed zmianą

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Filtr kategorii Operacji

### Overview

D2 z regułą archiwum P1. Serwer wysyła kategorie filtra w kolejności listy, z grupą i flagą archiwum, a filtr to `CategorySelect` z „Wszystkie kategorie”, sekcjami typu i sufiksem.

### Changes Required:

#### 1. ADR-0093 (zapisany w fazie 1) i reguły

**File**: `context/foundation/adr/0093-operations-category-filter-like-the-picker.md` (treść), `.ai/rules/reports.md`, `context/foundation/design-system.md`

**Intent**: Zmiana zapisanej decyzji „filtr Operacji płaski”. ADR powstaje w fazie 1, przed kodem; tu jest jego treść i aktualizacja reguł.

**Contract**:
- ADR-0093 zastępuje w ADR-0069:13 tylko część o filtrze Operacji; paleta poleceń zostaje płaska.
- Opisuje: `CategorySelect`, opcję „Wszystkie kategorie”, sekcje Dochody i Wydatki, w nich kolejność listy z nagłówkami grup, oraz sufiks „(zarchiwizowana)” wg linii roku (P1), z przykładem „Transport” 2025/2026.
- `reports.md`: „the Operations filter stays flat (ADR-0069)” → „the command palette stays flat; the Operations filter follows ADR-0093”.

#### 2. Opcje filtra na serwerze

**File**: `app/Support/OperationsFilterOptions.php`

**Intent**: Dane filtra w kolejności listy Kategorii, z grupą i stanem archiwum zamiast sortowania po nazwie.

**Contract**:
- `categories(User, ?int $year, ?int $selectedCategoryId)` zwraca `list<array{id: int, name: string, type: string, icon: string, color: string, group: array{id: int, name: string}|null, is_archived: bool}>`.
- Kolejność: najpierw dochody, potem wydatki, a w typie kolejność drzewa z `ReportCategoryTree::ofType(CategoryType, $categories, $groups)` (`app/Support/ReportCategoryTree.php:29`), spłaszczona i zawężona do kategorii w wyniku. Ten czytnik buduje już wejście `CategoryListOrder::tree()` (`id`, `group_id`, `sort_order`, `name_key`) i pomija puste grupy; nie budujemy trzeciej kopii. `CategoryListStore::state()` się nie nadaje (`lockForUpdate`, ścieżka zapisu).
- `is_archived` (P1): `archived_at` wybranej linii (linia roku, a bez roku najnowsza). Dla wybranej kategorii bez operacji `categories.archived_at`.
- Grupa przez `CategoryAppearance::group()`, która czyta bieżącą grupę listy (`category->group`, `CategoryAppearance.php:37-38`); linia budżetu nie ma własnej grupy, więc kolejność i nagłówki się zgadzają.
- Eager loading: zapytanie linii dostaje `with('category.group')` (`BudgetLine::$with` ładuje tylko `category`), a wybrana kategoria bez operacji (`OperationsFilterOptions.php:45`) ładuje `group`. Grupy użytkownika do drzewa to jedno zapytanie.
- Liczba zapytań stała względem liczby kategorii (Sloppy). `usort` po nazwie znika.

#### 3. `CategorySelect` dla filtra

**File**: `resources/js/components/CategorySelect.vue`, `resources/js/lib/category-appearance.ts`, `resources/js/lib/category-tree.ts` (lub nowy `resources/js/lib/category-filter-options.ts`)

**Intent**: Współdzielony picker dostaje to, czego filtr potrzebuje, na wzór `AccountSelect`. Okna operacji działają jak dziś.

**Contract**:
- Nowe opcjonalne propsy `allOptionLabel?: string` (sentinel jak `AccountSelect.vue:39-56`: pusty wybór emituje `''`), `archivedSuffix?: string` (dokleja się, gdy `is_archived`) i `sectionLabels?: { income: string; expense: string }` (sekcje po `type`, w nich bloki `groupSelectOptions`).
- `CategoryPickerOption` dostaje opcjonalne `type` i `is_archived`.
- Czysta funkcja budująca sekcje i bloki z testem vitest (sekcje, kolejność serwera, grupa, sufiks).
- Bez nowych propsów render jest identyczny jak dziś.

#### 4. Filtr w Operacjach

**File**: `resources/js/components/OperationsFilters.vue`, `resources/js/components/OperationsListPanel.vue`, `resources/js/lib/operations-fixtures.ts`

**Intent**: Natywny select kategorii zastąpiony `CategorySelect`.

**Contract**:
- `id="operations-filter-category"`, wartość `filters.category_id ?? ''`, zmiana przez `applyFilters({ category_id })` jak dziś (parametr `category_id`, liczba).
- Etykiety sekcji Dochody i Wydatki z `lang/pl/ui.php` (istniejące klucze w liczbie mnogiej albo nowe).
- `OperationsFilterCategory` dostaje `group` i `is_archived`.
- `categoryValue`, `incomeCategories`, `expenseCategories` i `onCategoryChange` znikają.
- Fiksura `filterCategoriesFixture` dostaje dwie grupy, kategorię bez grupy, polską nazwę na „Ż” przed „A” w kolejności listy i jedną zarchiwizowaną.

#### 5. Testy filtra

**File**: `tests/Feature/OperationsFilterTest.php`

**Intent**: Nowe reguły jako testy czerwone przed zmianą serwera.

**Contract**: nowe testy:
- kolejność listy wygrywa z nazwą („Żywność” `sort_order` 0 przed „Auto” 1);
- kategorie grupy stoją za nią, a kategoria bez grupy między grupami;
- payload `group`;
- `is_archived` dla „Transport” zarchiwizowanej w 2026: `year=2025` → `false`, `year=2026` → `true`, bez roku → `true`;
- wybrana kategoria bez operacji bierze `is_archived` z listy.

Asercja indeksu w `:237-239` dostaje kolejność z nowej reguły. Wymaganie kolejności się zmieniło (D2), więc to nie jest dopasowanie testu do kodu.

### Success Criteria:

#### Automated Verification:

- Nowe testy filtra są czerwone przed zmianą serwera i zielone po niej na PostgreSQL: `docker run … -e DB_DATABASE=testing_v1_4_3_fixes … artisan test --compact tests/Feature/OperationsFilterTest.php`
- Test vitest funkcji sekcji przechodzi: `npm test`
- `npm run types:check` i `npm run check:ui` przechodzą
- Specy E2E otwierające `/operations` przechodzą: `npx playwright test tests/e2e/categories.spec.ts tests/e2e/command-palette.spec.ts tests/e2e/seed.spec.ts`
- `vendor/bin/pint --dirty --format agent` i `vendor/bin/sloppy ci` bez nowych znalezisk

#### Manual Verification:

- `/dev/operations`: filtr z „Wszystkie kategorie”, sekcje Dochody i Wydatki, nagłówki grup, `CategoryChip`, sufiks archiwalnej; chip aktywnego filtra bez zmian
- Na `/operations` w podglądzie worktree wybór kategorii filtruje listę, „Wszystkie kategorie” czyści filtr, a link z palety (`?category_id=`) zaznacza kategorię w filtrze
- Okna wydatku i dochodu oraz pozostali konsumenci `CategorySelect` bez nowych propsów wyglądają jak przed zmianą: okno scalania kategorii (`CategoryMergeDialog.vue:79`, bloki grup z `lib/category-merge.ts:26`), okno zmiany kategorii operacji (`OperationCategoryDialog.vue:207`) i wiersz podglądu importu (`ImportPreviewRow.vue:606, 627`)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Phase 4: Plan miesiąca

### Overview

C5a z P2 i P3 oraz D1 z P4: układ 2×2 poniżej `sm`, etykiety nad wartościami, separator w polu planu, ton odchylenia wg statusu w wierszu i karcie podsumowania. Do tego poprawka znaku w fiksurach sinka.

### Changes Required:

#### 1. ADR-0092 (zapisany w fazie 1) i design system

**File**: `context/foundation/adr/0092-month-plan-deviation-tone-and-amounts-below-sm.md` (treść), `context/foundation/design-system.md`, `.ai/rules/js.md`, `.ai/rules/js-month-plan.md`

**Intent**: Nowe znaczenie `success` i zmiana zdania ADR-0060. ADR powstaje w fazie 1, przed kodem; tu jest jego treść oraz wpisy w design systemie i regułach.

**Contract**:
- ADR-0092:
  - ton odchylenia wiersza wg statusu (tabela z sekcji 3), etykieta „Odchylenie” bez zmian, glosariusz bez zmian;
  - karta podsumowania wg korzyści (P4);
  - 2×2 poniżej `sm` i etykieta nad wartością (P2), co zastępuje w ADR-0060 zdanie „the four-column layout fits at 390 px, so the 2 x 2 grid fallback … was not needed”;
  - separator w wartości startowej pola (P3);
  - odrzucone opcje z D1 i P2.
- `design-system.md:55` `success` dopisuje „wydatek poniżej planu bez przekroczenia w Planie miesiąca i różnica korzystna w podsumowaniu miesiąca (ADR-0092)”. `:78` nazywa helper tonu odchylenia jako drugi, obok `amountToneClass`, z uzasadnieniem (znak nie mówi o korzyści dla wydatku).
- `js.md` (zdanie o `minorUnitsToMoneyInput`) i `js-month-plan.md` (siatka 2×2, ton odchylenia z helpera) zaktualizowane.

#### 2. Fiksury sinka

**File**: `resources/js/lib/month-plan-fixtures.ts`, `resources/js/pages/dev/MonthPlanSink.vue`

**Intent**: Odczyt w sinku ma znak serwera (faktycznie − plan), tak jak tryb edycji. Bez tego reguła D1 malowałaby w sinku odwrotnie.

**Contract**:
- `deviation_minor_units` w `runningExpense`, `evenAnnualLeft`, `evenAnnualUsedUp`, `evenAnnualOver`, `outsidePlanExpense`, `overPlanExpense`, `longNameExpense` i w każdej innej fiksurze poprawione na faktycznie − plan (nieregularne: nadwyżka wg `CollectedCalculator`).
- Nowe stany:
  - wiersz z długimi kwotami (`123 456,78`, odchylenie `-123 299,98`);
  - wydatek poniżej planu;
  - roczna z dodatnim odchyleniem miesiąca bez przekroczenia;
  - roczna przekroczona rokiem z ujemnym odchyleniem miesiąca;
  - karta podsumowania z różnicą korzystną, niekorzystną i zerową;
  - ramka 390 px dla tabeli (nazwana klasa w `app.css`, wzór `.report-phone-frame`).

#### 3. Ton odchylenia

**File**: `resources/js/lib/month-plan-deviation-tone.ts` (nowy) z `month-plan-deviation-tone.test.ts`, `resources/js/components/MonthPlanItemRow.vue`, `resources/js/components/MonthSummaryCard.vue`

**Intent**: Jeden moduł daje ton wiersza (D1) i karty (P4). Lokalne `deviationClass` w obu komponentach znikają.

**Contract**: `monthItemDeviationTone({ deviation, overPlan, type, isArchived })` i `monthSummaryDeviationTone(key, minorUnits)` zwracają klasę tokenu.

Tabelę wiersza czyta się z góry na dół: wygrywa pierwszy pasujący przypadek. Zarchiwizowana linia budżetu (`is_archived`, `MonthPlanItemRow.php:45, 62`) jest więc bez tonu także przy `overPlan`. Dziś taka linia jest czerwona, bo `deviationClass` ignoruje `is_archived`, a zmiana jest zamierzona i trafia do ADR-0092.

| Przypadek (wiersz) | Ton |
| --- | --- |
| `deviation === null` | `text-muted-foreground` |
| wiersz zarchiwizowany | bez tonu |
| `overPlan` (także roczna przekroczona rokiem przy ujemnym odchyleniu miesiąca, nieregularna z nadwyżką) | `text-destructive` |
| wydatek, nie `overPlan`, `deviation < 0` | `text-success` |
| wydatek, nie `overPlan`, `deviation ≥ 0` (np. roczna +21 667) | bez tonu |
| dochód, `deviation > 0` | `text-success` |
| dochód, `deviation ≤ 0` | bez tonu |

Karta: wydatek `< 0` → `text-success`, `> 0` → `text-destructive`; dochód i odkładanie `> 0` → `text-success`, `< 0` → `text-destructive`; `0` bez tonu.

Każdy wiersz obu tabel ma test vitest, czerwony przed powstaniem modułu, plus przypadek „zarchiwizowany + `overPlan`” → bez tonu.

#### 4. Układ kwot (P2)

**File**: `resources/css/app.css`, `resources/js/components/MonthPlanItemRow.vue`, `MonthPlanGroupRow.vue`, `MonthPlanTable.vue`

**Intent**: Kwoty pięcio- i sześciocyfrowe mieszczą się przy 390 px, a etykiety stoją równo.

**Contract**:
- `.month-plan-grid` i `-no-kind` poniżej `sm`: dwie kolumny, obszary `'category status' / 'kind kind' / 'approved plan' / 'actual deviation'` (bez `kind` w `-no-kind`).
- Od `sm`: dzisiejsze cztery kolumny. Od `xl` bez zmian.
- We wszystkich czterech komórkach poniżej `xl` linia etykiety stoi nad wartością. W liniach etykiety „Zatwierdzony” i „Faktycznie” zostają strzałka (ADR-0060) i `MonthPlanMatchActual` (ADR-0062).
- Wiersze grupy i odkładania dostają obszary pasujące do swoich komórek poniżej `sm` (Critical Implementation Details). Wiersze sum (flex) bez zmian.
- Tylko nazwane klasy w `app.css`, bez wartości arbitralnych.

#### 5. Separator w polu planu (P3)

**File**: `resources/js/lib/format.ts` z testem, `resources/js/lib/month-plan-match-actual.ts`, `resources/js/pages/MonthPlan.vue`, `resources/js/pages/dev/MonthPlanSink.vue`

**Intent**: Pole planu startuje z `8 500,00` jak sąsiednie kwoty.

**Contract**:
- `minorUnitsToGroupedMoneyInput(minorUnits: number): string`: ten sam separator grup co `formatMoneyAmount`, przecinek dziesiętny, bez znaku waluty.
- Używają go:
  - `changeModeAmountInputs` (`month-plan-match-actual.ts:31`), czyli start trybu zmian;
  - `MonthPlan.vue:104` (start), `:121` (kwota odkładania), `:171` („Ustaw plan na wydatki”);
  - `MonthPlanSink.vue:155`, `:219`, `:236`, `:520`.
- `MonthPlan.vue:199` zostaje przy `minorUnitsToMoneyInput`: to fallback parsera, niewidoczny w polu.
- `month-plan-match-actual.test.ts:82` oczekuje `'150,00'`; kwoty poniżej 1 000 zł nie dostają separatora, więc test zostaje bez zmian.
- Vitest: `parseMoneyInputToMinorUnits(minorUnitsToGroupedMoneyInput(x)) === x` dla 0, 50, 850 000, 12 345 678.
- Operacje i dialogi zostają przy `minorUnitsToMoneyInput`.

### Success Criteria:

#### Automated Verification:

- Testy `month-plan-deviation-tone.test.ts` są czerwone przed modułem i zielone po nim. Test round-trip formattera przechodzi: `npm test`
- `php artisan test --compact tests/Unit/MonthPlanHardcodedUiTest.php tests/Feature/MonthPlanPageTest.php` przechodzi
- `npm run check:ui` i `npm run types:check` przechodzą
- Specy E2E Planu miesiąca przechodzą: `npx playwright test tests/e2e/irregular-collected.spec.ts tests/e2e/month-plan-groups.spec.ts`

#### Manual Verification:

- `/dev/month-plan` w ramce 390 px: długie kwoty w 2×2 bez ucięcia i bez łamania, etykiety nad wartościami na jednej wysokości, wiersz grupy i odkładania czytelne
- `/dev/month-plan` przy 1024 px: cztery kolumny w jednym rzędzie, a przy 1440 px układ jak przed zmianą
- Odczyt i edycja w sinku dają ten sam kolor dla tego samego wiersza: „Żywność” poniżej planu zielona, przekroczona czerwona, roczna +21 667 bez koloru
- W podglądzie worktree tryb zmian startuje z `8 500,00`, „Ustaw plan na wydatki” wpisuje kwotę z separatorem, a zapis daje te same kwoty co przed zmianą

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 5.

---

## Phase 5: Stany i bramka wizualna

### Overview

Kontrakt `/10x-ui`: macierz 7 stanów dla każdego dotkniętego widoku, zrzuty, ponowny skan wartości na sztywno, reguły na następną zmianę i pełna bramka.

### Changes Required:

#### 1. Macierz 7 stanów

**File**: `resources/js/pages/dev/MonthPlanSink.vue`, `OperationsSink.vue`, `AccountsKitchenSink.vue`, `DialogsKitchenSink.vue`, `ImportPreviewSink.vue`

**Intent**: Każdy stan widoczny albo oznaczony N/A z powodem.

**Contract**: dla filtra kategorii i lokaty (Operacje), selecta lokaty (ruch kapitału, wiersz importu), `AccountAvatar` z płytką, `CategoryChip`/`CategoryColorPicker` i wiersza Planu miesiąca pokazane są:
- default, hover, focus-visible (token `ring`);
- disabled (lokata w imporcie bez kwalifikujących się, wiersz zarchiwizowany);
- error (ruch kapitału bez lokaty, pole planu nieczytelne);
- empty (filtr bez kategorii, brak lokat);
- loading (lista Operacji w trakcie wizyty).

Brakujące komórki dochodzą do sinków; reszta dostaje N/A z powodem w tabeli macierzy sinka.

#### 2. Zrzuty i skan

**File**: `context/changes/v1-4-3-fixes/screenshots/`

**Intent**: Dowód do przeglądu i porównanie z `research-*.png`.

**Contract**:
- Zrzuty `plan-*.png` w jasnym i ciemnym motywie przy 1440 i 390 px: Plan miesiąca (z długimi kwotami), filtry Operacji rozwinięte (kategoria i lokata), „Znaki banków”, okno nowej kategorii.
- Skan wartości na sztywno z `/10x-ui` na plikach z researchu i nowych plikach; wynik (liczba trafień nie większa niż 1, czyli `DEFAULT_CATEGORY_COLOR`) zapisany w `research.md`.

#### 3. Utrwal to

**File**: `.ai/rules/js.md`, `.ai/rules/js-operations.md`, `.ai/rules/index.md` (jeśli zmienia się zakres ścieżek), `context/changes/v1-4-3-fixes/research.md`

**Intent**: Następny agent trzyma się kontraktu.

**Contract**:
- `js.md`:
  - kolor kategorii przez `categoryTileStyle` + `.category-tile` / `.category-swatch`, nigdy alfa doklejana do hex;
  - `AccountAvatar` z płytką tylko pod logo;
  - lokata w pickerze przez `AccountSelect` + `termDepositPickerOptions`;
  - natywny select dla wyliczeń bez ikon dostaje motyw z `color-scheme`.
- `js-operations.md`: filtr kategorii jako `CategorySelect` (ADR-0093).
- `php artisan boost:index-rules` po zmianie reguł.
- `research.md` `## Charges`: przy C1–C5 adnotacja „addressed in Phase N”, bez odroczonych.

#### 4. Pełna bramka

**File**: —

**Intent**: Zmiana przechodzi bramkę CI na PostgreSQL.

**Contract**: `composer ci:check` w obrazie `sail-8.5/app` na `testing_v1_4_3_fixes` oraz `vendor/bin/sloppy ci` na hoście.

### Success Criteria:

#### Automated Verification:

- Pełna bramka na PostgreSQL przechodzi: `docker run … -e DB_DATABASE=testing_v1_4_3_fixes … --entrypoint composer sail-8.5/app ci:check`
- `vendor/bin/sloppy ci` bez nowych znalezisk
- Skan wartości na sztywno: ≤ 1 trafienie (`DEFAULT_CATEGORY_COLOR`) w plikach widoków zmiany
- Pełny zestaw E2E przechodzi: `npx playwright test`

#### Manual Verification:

- Każda komórka macierzy 7 stanów jest pokazana w sinku albo ma N/A z powodem
- Zrzuty jasny/ciemny 1440 i 390 px w `screenshots/plan-*.png` potwierdzają C1–C5 wobec `research-*.png`
- Lista natywnego selecta w ciemnym motywie otwiera się ciemna w Chrome/Edge na Windows (luka researchu, nieobserwowalna w headless)

**Implementation Note**: Po tej fazie: `/10x-impl-review v1-4-3-fixes`.

---

## Testing Strategy

### Unit Tests:

- `CategoryTileContrastTest` (Pest): 24 barwy × 2 motywy ≥ 3:1, wartości z `app.css` i `CategoryColors`.
- `month-plan-deviation-tone.test.ts`: każdy przypadek tabeli wiersza i karty, w tym roczna przekroczona rokiem z ujemnym odchyleniem i roczna +21 667 bez przekroczenia.
- `format.test.ts`: round-trip `minorUnitsToGroupedMoneyInput` → `parseMoneyInputToMinorUnits`.
- Funkcja sekcji `CategorySelect`: sekcje, kolejność serwera, grupy, sufiks, opcja „wszystkie”.
- `ui-contract.test.ts`: `color-scheme` w `:root` i `.dark`.

### Integration Tests:

- `OperationsFilterTest` na PostgreSQL: kolejność listy, grupa, `is_archived` wg roku, wybrana kategoria bez operacji.
- Payload lokaty (`bank`, `mark_url`) w trzech propach z `assertInertia`.
- E2E: `categories`, `command-palette`, `seed`, `irregular-collected`, `month-plan-groups`, potem pełny zestaw.

### Manual Testing Steps:

1. Ciemny motyw, `/operations`, filtry rozwinięte: kalendarz widoczny, lista roku ciemna, filtr kategorii z sekcjami i grupami, filtr lokat z awatarami.
2. „Nowa kategoria” w ciemnym motywie: 24 pełne kolory; zapis i chip z czytelną ikoną.
3. `/month-plan` przy 390 px w trybie zmian: wpisz `123456,78`, sprawdź 2×2 i odchylenie; „Ustaw plan na wydatki” wpisuje kwotę z separatorem.
4. Ruch kapitału i import z lokatą: zapis kończy się jak przed zmianą.

## Performance Considerations

`OperationsFilterOptions::categories` dokłada odczyt grup i kategorii listy jednego użytkownika. Dwa zapytania bez pętli, a lista ma dziesiątki pozycji.

## Migration Notes

Brak migracji i zmian danych. Nowe klucze w propach są addytywne, a wartości wysyłane z formularzy się nie zmieniają.

## References

- Research: `context/changes/v1-4-3-fixes/research.md` (C1–C5, D1–D4)
- ADR-0054, ADR-0060, ADR-0062, ADR-0069, ADR-0071, ADR-0074
- Wzór „wszystkie” i sufiksu: `resources/js/components/AccountSelect.vue:26-69, 94-105`
- Kolejność listy: `app/Support/CategoryList/CategoryListOrder.php:55`, `app/Support/ReportCategoryTree.php:29`
- Awatar lokaty: `resources/js/pages/TermDeposits.vue:222-227`, `app/Http/Controllers/TermDepositController.php:167-168`
- Precedens `color-mix`: `resources/css/app.css:1042-1070`
- Kontrakt `/10x-ui`: `.claude/skills/10x-ui/SKILL.md` (router, macierz 7 stanów, bramka, „Utrwal to”)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tokeny motywu

#### Automated

- [x] 1.1 CategoryTileContrastTest czerwony przed zmianą tokenów i zielony po niej — 0adbaf3
- [x] 1.2 npm test z asercją color-scheme w ui-contract.test.ts przechodzi — 0adbaf3
- [x] 1.3 npm run check:ui i npm run types:check przechodzą — 0adbaf3
- [x] 1.4 AccountsHardcodedUiTest i MonthPlanHardcodedUiTest przechodzą — 0adbaf3

#### Manual

- [x] 1.5 Ciemny motyw na /operations: jasna ikona kalendarza, ciemna lista roku (Chrome/Edge na Windows) — 0adbaf3
- [x] 1.6 Okno nowej kategorii: 24 pełne kolory w ciemnym, czytelna żółta ikona w jasnym — 0adbaf3
- [x] 1.7 /dev/ui/accounts: Revolut i Lokaty Facto widoczne w ciemnym, skróty i Coins bez zmian — 0adbaf3

### Phase 2: Lokaty z awatarem

#### Automated

- [x] 2.1 Asercje payloadów lokat czerwone przed zmianą i zielone po niej — de9763a
- [x] 2.2 npm run types:check, npm test i npm run check:ui przechodzą — de9763a
- [x] 2.3 ImportPreviewHardcodedUiTest przechodzi — de9763a
- [x] 2.4 Pint i sloppy ci bez nowych znalezisk — de9763a
- [x] 2.5 Asercje wysyłki lokat przechodzą przed zamianą selectów i po niej — de9763a

#### Manual

- [x] 2.6 /dev/operations: filtr lokat z awatarami, „Wszystkie lokaty”, sufiks, chip z awatarem — de9763a
- [x] 2.7 /dev/ui/dialogs capital-new i capital-errors: lokaty z awatarami i błąd pola — de9763a
- [x] 2.8 /dev/import-preview: lokata, wiersz wyłączony, nawigacja klawiaturą — de9763a
- [x] 2.9 Zapis ruchu kapitału i importu z lokatą jak przed zmianą — de9763a

### Phase 3: Filtr kategorii Operacji

#### Automated

- [x] 3.1 Nowe testy filtra czerwone przed zmianą serwera i zielone po niej na PostgreSQL — 78656b3
- [x] 3.2 Test vitest funkcji sekcji przechodzi — 78656b3
- [x] 3.3 npm run types:check i npm run check:ui przechodzą — 78656b3
- [x] 3.4 Specy E2E categories, command-palette i seed przechodzą — 78656b3
- [x] 3.5 Pint i sloppy ci bez nowych znalezisk — 78656b3

#### Manual

- [x] 3.6 /dev/operations: „Wszystkie kategorie”, sekcje, grupy, chipy, sufiks — 78656b3
- [x] 3.7 /operations: wybór filtruje, „Wszystkie kategorie” czyści, link z palety zaznacza kategorię — 78656b3
- [x] 3.8 Okna wydatku i dochodu, scalania, zmiany kategorii i wiersz importu wyglądają jak przed zmianą — 78656b3

### Phase 4: Plan miesiąca

#### Automated

- [x] 4.1 Testy tonu odchylenia czerwone przed modułem i zielone po nim, round-trip formattera przechodzi
- [x] 4.2 MonthPlanHardcodedUiTest i MonthPlanPageTest przechodzą
- [x] 4.3 npm run check:ui i npm run types:check przechodzą
- [ ] 4.4 Specy E2E irregular-collected i month-plan-groups przechodzą

#### Manual

- [x] 4.5 /dev/month-plan 390 px: długie kwoty 2×2 bez ucięcia, równe etykiety, wiersz grupy i odkładania czytelne
- [x] 4.6 /dev/month-plan 1024 i 1440 px: cztery kolumny i układ desktopowy bez zmian
- [x] 4.7 Odczyt i edycja w sinku dają ten sam kolor odchylenia
- [x] 4.8 Tryb zmian startuje z separatorem, „Ustaw plan na wydatki” też, zapis bez zmian kwot

### Phase 5: Stany i bramka wizualna

#### Automated

- [ ] 5.1 composer ci:check na PostgreSQL przechodzi
- [ ] 5.2 sloppy ci bez nowych znalezisk
- [ ] 5.3 Skan wartości na sztywno: najwyżej 1 trafienie
- [ ] 5.4 Pełny zestaw E2E przechodzi

#### Manual

- [ ] 5.5 Macierz 7 stanów pokazana albo N/A z powodem w każdym sinku
- [ ] 5.6 Zrzuty jasny/ciemny 1440 i 390 px potwierdzają C1–C5
- [ ] 5.7 Lista natywnego selecta ciemna w Chrome/Edge na Windows
