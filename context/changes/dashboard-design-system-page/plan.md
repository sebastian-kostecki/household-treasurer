# Dashboard według strony design systemu (ADR-0045) — plan implementacji

## Overview

Widok Dashboard (`resources/js/pages/Dashboard.vue`) wyrównujemy do strony `PageDashboard` design systemu „C · Atrament i morski”, tak jak zapisał ADR-0045. Zmieniają się trzy rzeczy:

- panel miar ma trzy miary (bez „Postęp roku”);
- panel realizacji z zakładkami, wierszami grup i kolumną Status zastępuje blok „Budżet <rok>”: nagłówek ze `Switch` „Postęp roku” i „Otwórz budżet”, miary planu, dwie tabele Dochody i Wydatki z wierszem sumy;
- karta alertu dostaje etykietę rodzaju na górze i wartość po prawej w kolorze wagi.

ADR-0045 zostawił zmianie wdrażającej etykiety rodzajów alertów, wartość dla rodzajów bez kwoty, układ szkicu i telefonu oraz wagę tożsamości. Dlatego najpierw powstaje ADR-0050, potem kod. Payload Inertii, PHP i testy Feature się nie zmieniają.

## Current State Analysis

Pełny audyt: `context/changes/dashboard-design-system-page/research.md` (zarzuty C1–C5, wszystkie do naprawy). Najważniejsze:

- `DashboardStatPanel.vue:116-123` dokłada czwartą miarę „Postęp roku”, a `:71-87` buduje jej podpis. `app.css:1229-1233` nadaje pierwszej kolumnie `1.5fr`.
- `DashboardExecutionPanel.vue` ma `SegmentedControl` z trzema zakładkami (`:28-34,81-88`), wiersze grup z sumami i `Badge` (`:112-145`), kolumny `Plan` i `Status` (`:95-97,107-109`). Siatka w `app.css:1057-1148` ma sześć obszarów, a na telefonie ukrywa Plan i Realizację.
- Kreska Year Elapsed jest zawsze włączona: `Dashboard.vue:283` i `DashboardExecutionPanel.vue:160` przekazują `year_elapsed_percent` bezwarunkowo. Nic nie czyta ani nie zapisuje `dashboard-year-elapsed`.
- „Otwórz budżet” stoi w wierszu tytułu strony (`Dashboard.vue:131-155`). `BudgetPlanSummaryStrip` jest tylko w szkicu (`:259`), choć `planSummary` jest w payloadzie w obu fazach (`DashboardController.php:87-89`).
- Karta alertu (`DashboardNotices.vue:363-388`) ma kafelek ikony, tytuł i zdanie; `noticeTitle` / `noticeText` (`:155-224`) budują teksty per rodzaj. Klasy `.dashboard-notice*` (`app.css:1176-1208`) nie mają etykiety ani wartości po prawej.
- `CategoryMeterRow` ma jednego importera (`DashboardExecutionPanel.vue:3`), więc jego zmiana nie dotknie innych stron. `ExecutionMeter` ukrywa kreskę, gdy `yearElapsedPercent === null` (`ExecutionMeter.vue:14,53`), a `execution_footer_no_mark` już opisuje stopkę bez kreski.
- Jedyny dotychczasowy odczyt `localStorage` to `composables/useAppearance.ts`, bez try/catch, więc nie jest wzorem. Testy JS to czyste funkcje w `resources/js/lib/*.test.ts` (Vitest przez `vp test`); `@vue/test-utils` nie ma w projekcie.
- Straże nazywające pliki Dashboardu: `scripts/check-ui-literals.mjs` (lista `FILES`) i `tests/Unit/DashboardHardcodedUiTest.php` (`DASHBOARD_VIEW_FILES`, brakujący plik oblewa test). Nowy plik trzeba dopisać do obu.
- Dokumenty są już przed kodem: `design-system.md:77,83,90,98`, hasło Year Elapsed w glosariuszu (`:59-60`). Do poprawy zostaje `.ai/rules/js.md:47` (opis karty z ADR-0043).

## Desired End State

- **Panel miar:** Do wydania (z „Plan miesiąca →”), Płynność dysponowalna, Łączne środki. Przy braku planu miesiąca dwie miary, jak dziś.
- **Blok „Budżet <rok>”** (tylko gdy `budget !== null`): wiersz `h2.section-title` „Budżet 2026” po lewej, po prawej `Switch` „Postęp roku” (tylko w fazie `execution`) i „Otwórz budżet” (`outline`). Pod nim jako rodzeństwo: `BudgetPlanSummaryStrip` (Planowane dochody, Planowane wydatki, Bilans, w obu fazach) i dwie tabele `ListSurface` Dochody i Wydatki. Zero `Card` w `Card`. W szkicu: nagłówek bez Switcha, miary planu i karta stanu pustego. Bez budżetu: sama karta stanu pustego jak dziś.
- **Tabela:** kolumny Kategoria | Plan bieżący | Realizacja | Pozostało | Wykonanie; wiersz sumy „Suma dochodów” / „Suma wydatków” na końcu (miernik bez kreski, `amount-sum`); „przekroczono” (`Badge destructive-soft`) obok procentu przekroczonej kategorii i przekroczonej sumy wydatków. Stopka „Kwoty w zł · kreska = postęp roku (n%) · kliknij wiersz…” pod tabelą Wydatki. Na telefonie (390 px) wiersz to kategoria + Pozostało i miernik z procentem (+ „przekroczono”), a Plan i Realizacja są ukryte jak dziś.
- **Switch „Postęp roku”:** domyślnie włączony; pokazuje i ukrywa kreskę na miernikach, które mogą ją mieć (`showsYearElapsedMark`); stan w `localStorage` pod `dashboard-year-elapsed`, odczyt po zamontowaniu, odczyt i zapis w try/catch z logiem przyczyny. Ukryta kreska → `null` do `CategoryMeterRow` i stopka `execution_footer_no_mark`.
- **Karta alertu:** kafelek ikony w kolorze wagi, nad tożsamością etykieta rodzaju, tożsamość w `font-medium`, po prawej wartość w kolorze wagi (`white-space: nowrap`). Link, kolejność, limit sześciu kart i pigułki bez zmian. Kolor tylko z `severity`.

| Rodzaj | Etykieta | Tożsamość | Wartość po prawej |
| --- | --- | --- | --- |
| `negative_account`, `negative_term_deposit` | Saldo ujemne | nazwa konta / lokaty | saldo ze znakiem |
| `month_to_spend_negative` | Do wydania poniżej zera | miesiąc | kwota ze znakiem |
| `month_item_over_plan` | Ponad plan | `category_name` | kwota ze znakiem |
| `one_off_over_limit` | Ponad limit | `category_name` | kwota ze znakiem |
| `month_plan_not_approved` | Plan niezatwierdzony | miesiąc | brak (pusty slot) |
| `month_pace_ahead` | Tempo przed upływem dni | miesiąc | „31% · upływ dni 17%” |

Weryfikacja: `npm run test`, `npm run types:check`, `npm run check:ui`, `DashboardHardcodedUiTest`, `composer ci:check`, `vendor/bin/sloppy ci`, zrzuty sinka i widoku przy 390 i 1280 px w obu motywach.

### Key Discoveries:

- `DashboardController.php:73-89,140-144` już wysyła `planSummary`, `incomeTotals`, `expenseTotals`, `year_elapsed_as_of` i `year_elapsed_percent` w fazie `execution` i `draft`, więc nie ma zmiany po stronie PHP ani propsów (reguła „obie strony propsa” nie wchodzi w grę).
- Stan Switcha należy do przeglądarki, nie do serwera. Dlatego trzyma go `Dashboard.vue` (composable), a blok dostaje go jako `v-model`; sink może wtedy pokazać blok w obu stanach bez dotykania `localStorage`.
- `BudgetPlanSummaryStrip` używa też `Budget.vue:237`; jego domyślne zachowanie zostaje. Dashboard nie przekazuje `yearElapsedPercent`, więc strip ma trzy miary.
- Karta nie ma reguł mobilnych (`bundle.css`: 0 `@media`), więc układ telefonu rozstrzyga ADR-0050 w ramach zasady z `.ai/rules/js.md`: jedna struktura na `grid-template-areas`, bez drugiego drzewa.
- Rozbieżności artefaktu z ADR-0045 rozstrzyga ADR (reguła w `design-system.md:3`): `Notice/README.md` bez wartości po prawej, `StatPanel/README.md` z czterema miarami, „Blisko limitu” w `Notice/preview.html` (nie istnieje w domenie, ADR-0037), waga 600 tożsamości (ADR mówi „medium”).

## What We're NOT Doing

- `AccountMarkCompact` i `AccountIdentityBlock` w panelach Konta i Lokaty (`DashboardHoldings`): osobne zmiany widoków.
- Zmiany w PHP: payload, rodzaje, wagi, kolejność i limit sześciu kart (`DashboardNotices`) oraz glosariusz (rodzaje alertów już istnieją, hasło Year Elapsed jest aktualne).
- Rodzaj alertu „Blisko limitu” i jakikolwiek stan „blisko limitu” (ADR-0037).
- Wartość po prawej dla `month_plan_not_approved` (payload jej nie ma; pusty slot zamiast wymyślonego słowa stanu).
- Przeróbka `StatPanel`, `ListSurface` ani `BudgetPlanSummaryStrip` pod blok „jedna ramka”: wybrano nagłówek i panele obok siebie.
- Switch w szkicu budżetu (nie ma w nim mierników).
- Druga struktura mobilna dla tabel; podpisy Plan i Realizacja na telefonie.
- Edycja README ani stron w artefakcie claude.ai (to treść artefaktu, nie repo); ADR-0050 tylko odnotowuje sprzeczności.
- Wspólny helper straży „hardcoded UI” i spec Playwright dla Dashboardu (bramką wizualną jest sink).

## Implementation Approach

Decyzja idzie przed kodem (`AGENTS.md`). Faza 1 zapisuje ADR-0050 i dokumenty. Faza 2 jest czystą logiką test-first: zapis Switcha i prezentacja alertów (etykieta, tożsamość, wartość) jako funkcje testowane w Vitest, plus nowe teksty. Faza 3 przebudowuje górę strony: trzy miary i kartę alertu na funkcjach z fazy 2. Faza 4 buduje blok „Budżet <rok>”, tabele, Switch i straże. Faza 5 rozszerza sink o oba stany Switcha i szkic, sprząta osierocone teksty i zamyka zmianę zrzutami oraz pełną bramką. Numer ADR 0050 wybrano w `change.md` (0046, 0048, 0049, 0051 są zarezerwowane przez równoległe zmiany; spodziewaj się konfliktów w `design-system.md` i `js.md`).

## Critical Implementation Details

- **Kolejność emisji alertów jest kontraktem.** Prezentacja per rodzaj to funkcja bez zmiany kolejności i liczby kart; test dla siedmiu rodzajów w `dashboard-fixtures.ts` (`all-notice-kinds`) zostaje i musi przejść.
- **Logowanie zamiast połykania.** Helper `localStorage` łapie błąd odczytu i zapisu, ale loguje go razem z przyczyną (`console.warn` z `cause`); żadnego pustego `catch` (reguła z `CLAUDE.local.md`). Przy błędzie odczytu Switch jest włączony.
- **Odczyt tylko po zamontowaniu.** `onMounted` czyta zapis, żeby pierwszy render po SSR i po hydracji dawał ten sam markup (SSR nie został zweryfikowany jako wyłączony w produkcji, `build:ssr` istnieje).
- **Zapis tylko przy zmianie użytkownika.** Composable zapisuje w handlerze `update:modelValue`, nie w `watch`, żeby odczyt domyślnej wartości po zamontowaniu niczego nie zapisywał.

## Faza 1: Decyzje i dokumenty

### Overview

ADR-0050 zapisuje to, co ADR-0045 zostawił zmianie wdrażającej, i odejścia od karty `PageDashboard`. `design-system.md` i reguła w `js.md` mają opisywać kod, który powstanie, żeby następny agent nie wrócił do zakładek ani zdania w karcie.

### Changes Required:

#### 1. ADR-0050

**File**: `context/foundation/adr/0050-dashboard-notice-labels-and-budget-block.md`

**Intent**: Zapisać decyzje, których ADR-0045 i karta nie rozstrzygają, oraz to, czym ta zmiana odchodzi od karty.

**Contract**: Styl i struktura jak w ADR-0047 (proza po angielsku, akapity tematyczne, na końcu „supersedes” i odrzucone opcje). Treść:
- tabela siedmiu rodzajów alertów z etykietą, tożsamością i wartością (tabela z „Desired End State”); `month_pace_ahead` mapuje na `percent` i `monthElapsedPercent(elapsed_day, days_in_month)`, `month_plan_not_approved` ma pusty prawy slot;
- kwoty alertów ze znakiem z `formatMoney`; tożsamość to `category_name` dla `month_item_over_plan` i `one_off_over_limit`; waga tożsamości `font-medium` (nie 600 z karty);
- „Blisko limitu” z `Notice/preview.html` nie wchodzi (brak progu w glosariuszu, ADR-0037);
- blok „Budżet <rok>” to nagłówek i panele obok siebie (miary planu, dwie tabele), nie jedna ramka, żeby uniknąć karty w karcie i zmiany `BudgetPlanSummaryStrip` (używa go też Budżet); „jeden panel” z ADR-0045 czytamy jako jedna strefa;
- miary planu w obu fazach; Switch tylko w fazie `execution`; brak budżetu bez zmian;
- układ telefonu: Kategoria, Pozostało, miernik z procentem i „przekroczono”; Plan i Realizacja ukryte poniżej `md`; stopka z jednostką pod tabelą Wydatki;
- Switch: domyślnie włączony, klucz `dashboard-year-elapsed`, wartości `'true'` / `'false'`, odczyt po zamontowaniu, błąd storage logowany z przyczyną;
- odnotowane sprzeczności artefaktu z ADR (`Notice/README.md`, `StatPanel/README.md`); ADR wygrywa;
- „Supersedes in part”: ADR-0043 (karta ostrzeżenia, panel z zakładkami, cztery miary; już zastąpione przez ADR-0045). Odrzucone: jedna ramka bloku, podpisy Plan i Realizacja na telefonie, słowo stanu dla planu niezatwierdzonego, tylko dwie etykiety z karty.
- Do ADR-0045 jedno zdanie zwrotne „Completed by ADR-0050” (precedens F5 z `2026-11-06-accounts-design-system-page`).

#### 2. Strona design systemu

**File**: `context/foundation/design-system.md`

**Intent**: Dopisać ADR-0050 i etykiety rodzajów alertów oraz układ bloku, żeby strona opisywała kod.

**Contract**: (a) akapit wstępny (`:3`) dostaje ADR-0050; (b) „Alerty” (`:77`): lista siedmiu etykiet, tożsamość `category_name`, pusty slot dla planu niezatwierdzonego, wartość tempa; (c) opis panelu „Budżet <rok>” (`:90`, `:98`): nagłówek i panele obok siebie, Switch tylko w realizacji, układ telefonu; (d) opis sinka (`:98`) o oba stany Switcha, szkic i brak budżetu.

#### 3. Reguła dla widoków

**File**: `.ai/rules/js.md`, potem `php artisan boost:index-rules`

**Intent**: Poprawić zdanie o karcie ostrzeżenia z ADR-0043 (`:47`) i dopisać regułę trzymania stanu Switcha.

**Contract**: Karta to `.dashboard-notice` z kafelkiem ikony w kolorze wagi, etykietą rodzaju, tożsamością i wartością po prawej w kolorze wagi (ADR-0045, ADR-0050), a nowy rodzaj alertu dopisuje etykietę w jednej funkcji prezentacji w `@/lib/dashboard-notice-presentation`, nie nowy markup. Zdanie o Switchu: ustawienie widoku zapamiętywane w przeglądarce ma helper z try/catch w `@/lib`, a stan trzyma strona, nie komponent panelu. Indeks po `boost:index-rules` bez innych różnic.

### Success Criteria:

#### Automated Verification:

- ADR-0050 istnieje i jest jedynym plikiem `0050-*` w `context/foundation/adr/`
- `php artisan boost:index-rules` kończy się bez błędu, a `git diff` pokazuje tylko zamierzone zmiany w regułach
- `grep -rn "severity only in the icon tile" .ai/rules` nie zwraca trafień

#### Manual Verification:

- ADR-0050 zawiera tabelę siedmiu rodzajów, regułę znaku i tożsamości, układ bloku, szkic bez Switcha, układ telefonu, regułę storage, sprzeczności artefaktu i odrzucone opcje
- `design-system.md` i `.ai/rules/js.md` nie przeczą ADR-0050 (brak zakładek, czterech miar i dwulinijkowej karty)

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie treści ADR, zanim zaczniesz kod.

---

## Faza 2: Logika kliencka test-first i teksty

### Overview

Dwa elementy czystej logiki z testami Vitest pisanymi najpierw (czerwone), potem implementacja, oraz nowe teksty w `lang/pl/ui.php`. Żaden widok się jeszcze nie zmienia.

### Changes Required:

#### 1. Zapis stanu Switcha

**File**: `resources/js/lib/year-elapsed-mark-storage.ts`, `resources/js/lib/year-elapsed-mark-storage.test.ts`

**Intent**: Jedno miejsce, które czyta i zapisuje ustawienie „Postęp roku”, odporne na niedostępny `localStorage` (tryb prywatny, zablokowane dane, SSR), bez ukrywania błędu.

**Contract**:
- `export const YEAR_ELAPSED_MARK_KEY = 'dashboard-year-elapsed'`;
- `readYearElapsedMark(storage?: Pick<Storage, 'getItem'> | null): boolean` zwraca `false` tylko dla zapisanego `'false'`; brak wpisu, inna wartość, brak storage albo wyjątek dają `true`; wyjątek jest logowany przez `console.warn` z przyczyną;
- `writeYearElapsedMark(value: boolean, storage?: Pick<Storage, 'setItem'> | null): void` zapisuje `'true'` / `'false'`; wyjątek (np. pełny storage) jest logowany z przyczyną i nie przerywa działania;
- domyślny storage to `window.localStorage`, o ile istnieje.
- Testy (najpierw czerwone): brak wpisu → `true`; `'false'` → `false`; `'true'` → `true`; śmieciowa wartość → `true`; `getItem` rzuca → `true` i `console.warn` wywołane z błędem; `setItem` rzuca → brak wyjątku na zewnątrz i `console.warn` z błędem; zapis `false` ustawia `'false'`.

#### 2. Prezentacja alertów

**File**: `resources/js/lib/dashboard-notice-presentation.ts`, `resources/js/lib/dashboard-notice-presentation.test.ts`

**Intent**: Przenieść decyzję „jaka etykieta, jaka tożsamość, jaka wartość” z `switch` w komponencie do czystej funkcji, którą testuje Vitest. Komponent tylko tłumaczy klucze na teksty.

**Contract**:
- `noticePresentation(notice: DashboardNotice)` zwraca `{ labelKey, identity, value }`, gdzie `labelKey` to klucz tekstu etykiety (`negative_balance`, `month_to_spend_negative`, `over_plan`, `over_limit`, `plan_not_approved`, `pace_ahead`), `identity` to `{ type: 'name', name }` albo `{ type: 'month', month }`, a `value` to `{ type: 'money', minorUnits }`, `{ type: 'pace', percent, daysPercent }` albo `null`;
- tożsamość `name` dla kont i lokat bierze `name`, a dla `month_item_over_plan` i `one_off_over_limit` bierze `category_name`;
- `daysPercent` liczy istniejące `monthElapsedPercent(elapsed_day, days_in_month)`;
- typ `DashboardNotice` jest już zdefiniowany w `DashboardNotices.vue:22-76` (zgodny z typami strony); przenieś go do pliku w `resources/js/types` albo `lib`, tam gdzie są typy Dashboardu, i zaimportuj z obu miejsc, żeby funkcja nie importowała z `.vue`.
- Testy (najpierw czerwone): jeden przypadek na każdy z siedmiu rodzajów (etykieta, tożsamość, wartość), kwoty zostają ze znakiem (ujemna zostaje ujemna), `month_plan_not_approved` ma `value === null`, a `month_pace_ahead` zwraca `percent` z payloadu i `daysPercent` z dnia miesiąca.

#### 3. Teksty

**File**: `lang/pl/ui.php`

**Intent**: Dodać teksty z uzgodnionych decyzji. Usuwanie osieroconych kluczy robi faza 5, po tym jak widoki przestaną ich używać.

**Contract**: w `dashboard`: `notice_label_negative_balance` „Saldo ujemne”, `notice_label_month_to_spend_negative` „Do wydania poniżej zera”, `notice_label_over_plan` „Ponad plan”, `notice_label_over_limit` „Ponad limit”, `notice_label_plan_not_approved` „Plan niezatwierdzony”, `notice_label_pace_ahead` „Tempo przed upływem dni”, `notice_value_pace` „:percent% · upływ dni :days_percent%”, `income_sum` „Suma dochodów”, `expense_sum` „Suma wydatków”; `execution_panel_title` zmienia wartość na „Budżet :year”. Do ponownego użycia bez zmian: `budget.current_plan`, `budget.category`, `dashboard.remaining`, `dashboard.percent_complete`, `budget.over_limit_badge`, `budget.amounts_in_zl`, `dashboard.year_elapsed` (etykieta Switcha), `budget.planned_income`, `budget.planned_expense`, `budget.planned_balance`.

### Success Criteria:

#### Automated Verification:

- Nowe testy najpierw były czerwone, teraz przechodzą: `npm run test`
- Typy przechodzą: `npm run types:check`
- Lint i formatowanie przechodzą: `npm run check`; po zmianach PHP `vendor/bin/pint --dirty --format agent`
- Istniejący `dashboard-fixtures.test.ts` (siedem rodzajów alertów) przechodzi bez edycji

#### Manual Verification:

- Diff testów pokazuje przypadek na każdy z siedmiu rodzajów i dwa przypadki awarii storage (odczyt, zapis)

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie testów, zanim przejdziesz do widoków.

---

## Faza 3: Trzy miary i karta alertu

### Overview

Góra strony: panel miar bez „Postęp roku” i karta alertu z etykietą oraz wartością po prawej, oparta na funkcji z fazy 2.

### Changes Required:

#### 1. Panel miar

**File**: `resources/js/components/DashboardStatPanel.vue`, `resources/js/pages/Dashboard.vue`

**Intent**: Usunąć czwartą miarę, jej podpis i propsy, które służyły tylko jej (zarzut C1).

**Contract**: znika wpis `year-elapsed` z `metrics`, computed `yearElapsedCaption` i propsy `yearElapsedAsOf` i `yearElapsedPercent` z `DashboardStatPanel`; `Dashboard.vue` przestaje je przekazywać (`:224-225`). `year_elapsed_as_of` zostaje w wierszu tytułu strony (`metaLine`). `app.css:1229-1233` (pierwsza kolumna `1.5fr`) zostaje, jeśli sink pokaże, że etykieta „Do wydania · miesiąc” ze znacznikiem nadal tego wymaga; w przeciwnym razie reguła znika razem z komentarzem.

#### 2. Karta alertu

**File**: `resources/js/components/DashboardNotices.vue`, `resources/css/app.css`

**Intent**: Karta według `ht-notice`: kafelek ikony w kolorze wagi, etykieta rodzaju, tożsamość i wartość po prawej (zarzut C5).

**Contract**: komponent buduje etykietę, tożsamość i wartość z `noticePresentation` i tekstów z fazy 2; `noticeTitle` i `noticeText` znikają. Markup `.dashboard-notice`: ikona, `.dashboard-notice-body` z dwiema liniami (`.dashboard-notice-kind` w `meta text-muted-foreground`, `.dashboard-notice-identity` w `text-sm font-medium` z `truncate`), po prawej `.dashboard-notice-value` w stylu `amount` z kolorem z `severity` (`text-destructive` dla `exceeded`, `text-warning` dla `warning`) i `white-space: nowrap`. Gdy `value === null`, slotu po prawej nie ma. Znika `.dashboard-notice-title` i `.dashboard-notice-text`; ikony, `Spinner`, link, `:data`, `:as`, stany `aria-busy`, pigułki, limit sześciu kart i „Pokaż wszystkie” zostają bez zmian. Kolor z `severity`, nie z rodzaju (`.ai/rules/support.md`, ADR-0032). Wartość tempa to `notice_value_pace`. Bez literałów kolorów i px w szablonie.

### Success Criteria:

#### Automated Verification:

- Testy JS i typy przechodzą: `npm run test`, `npm run types:check`
- Skan literałów przechodzi: `npm run check:ui`
- Straż Dashboardu przechodzi: `vendor/bin/sail artisan test --compact tests/Unit/DashboardHardcodedUiTest.php` (w worktree wariant `docker run … artisan test` z `AGENTS.md`)
- `grep -rn "yearElapsedCaption\|notice-title\|notice-text" resources` nie zwraca trafień

#### Manual Verification:

- W `/dev/ui/dashboard` (scenariusz `all-notice-kinds`) siedem kart ma etykietę, tożsamość i wartość po prawej tam, gdzie jest; `month_plan_not_approved` nie ma wartości, tempo pokazuje „n% · upływ dni m%”
- Panel miar ma trzy miary (dwie bez planu miesiąca), układ nie jest przechylony
- Wartość i ikona mają kolor wagi w obu motywach i kontrast tekstu na karcie jest czytelny

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie wyglądu, zanim przejdziesz do bloku budżetu.

---

## Faza 4: Blok „Budżet <rok>”

### Overview

Najważniejsza faza: nagłówek bloku ze Switchem i „Otwórz budżet”, miary planu w obu fazach, dwie tabele z wierszem sumy bez zakładek i kolumny Status, stan Switcha w `Dashboard.vue`, straże.

### Changes Required:

#### 1. Stan Switcha

**File**: `resources/js/composables/useYearElapsedMark.ts`

**Intent**: Stan „Postęp roku” trzyma strona, nie panel; panel i sink dostają go jako wartość.

**Contract**: `useYearElapsedMark()` zwraca `{ showYearElapsedMark: Ref<boolean>, setYearElapsedMark(value: boolean): void }`. Domyślnie `true`; w `onMounted` odczytuje `readYearElapsedMark()`; `setYearElapsedMark` ustawia ref i woła `writeYearElapsedMark` (Critical Implementation Details: zapis tylko przy zmianie użytkownika, nie w `watch`). Bez własnego testu Vitest (logika jest w helperze z fazy 2).

#### 2. Blok „Budżet <rok>”

**File**: `resources/js/components/DashboardBudgetBlock.vue` (nowy), `resources/js/pages/Dashboard.vue`

**Intent**: Wynieść wiersz nagłówka bloku, miary planu i panel realizacji z `Dashboard.vue` do jednego komponentu, który sink może wyrenderować w obu stanach Switcha (zarzut C4).

**Contract**:
- Propsy: dane z payloadu potrzebne blokowi (`budget`, `budgetYear`, `planSummary`, kategorie i sumy przychodów i wydatków, `yearElapsedPercent`, `canSelectCurrentYearInShell`) oraz `v-model:showYearElapsedMark`; emity `start`, `finish`, `error` dla formularza `selectYear`, tak jak `DashboardStatPanel`.
- Wiersz nagłówka: `h2.section-title` „Budżet <rok>” (`execution_panel_title`); po prawej kontener `flex flex-wrap items-center gap-3` z grupą [`Switch` + `Label` „Postęp roku”, tylko gdy `budget.phase === 'execution'`] i „Otwórz budżet” (`outline`). Formularz `BudgetController.selectYear.form()` (gdy `canSelectCurrentYearInShell`) albo `CreateBudgetDialog` z własnym `ref` otwarcia i `year-input-id` `dashboard-open-budget-year`, przeniesione z wiersza tytułu strony.
- Pod nagłówkiem, jako rodzeństwo w `space-y-3`: `BudgetPlanSummaryStrip` (gdy `planSummary` istnieje, w obu fazach, bez `yearElapsedPercent`), w szkicu karta `IndexEmptyState` (bez zmian treści), w realizacji `DashboardExecutionPanel`.
- Do panelu realizacji idzie `showYearElapsedMark ? yearElapsedPercent : null`.
- `Dashboard.vue`: usuwa „Otwórz budżet” z wiersza tytułu, zachowuje `yearSelectionPending`, nasłuch błędu wyboru roku i `onYearSelection*` (blok emituje `start`/`finish`/`error` do tych samych handlerów), używa `useYearElapsedMark()` i `v-model` do bloku. Gdy `budget === null`, wciąż sama karta ze stanem pustym i `CreateBudgetDialog` jak dziś. Gałąź `v-else` w `<section>` zastąpiona użyciem bloku.

#### 3. Dwie tabele

**File**: `resources/js/components/DashboardExecutionPanel.vue`, `resources/js/components/CategoryMeterRow.vue`, `resources/css/app.css`

**Intent**: Dwie tabele `ListSurface` zamiast zakładek, wierszy grup i kolumny Status (zarzut C2).

**Contract**:
- `DashboardExecutionPanel` renderuje dwa `ListSurface flush` (Dochody, Wydatki) jako rodzeństwo w `space-y-3`. Nagłówek każdego to `h3.section-title`. Wiersz nagłówka kolumn: Kategoria | Plan bieżący (`budget.current_plan`) | Realizacja (`dashboard.executed` zamienia się na „Realizacja”, patrz niżej) | Pozostało | Wykonanie; wiersze `CategoryMeterRow`; pusty typ pokazuje `no_categories`; na końcu wiersz sumy „Suma dochodów” / „Suma wydatków” z `amount-sum` i miernikiem bez kreski; stopka (`execution_footer` lub `execution_footer_no_mark`) tylko pod tabelą Wydatki. Znikają `SegmentedControl`, `activeTab`, `groups`, `tabOptions` i propsy niepotrzebne po zmianie.
- Nagłówek kolumny „Realizacja”: dodaj klucz `dashboard.execution_column` „Realizacja” (wartość `executed` „Zrealizowano” przestaje być używana).
- `CategoryMeterRow`: znika komórka `category-meter-cell-status`; „przekroczono” (`Badge destructive-soft`) stoi w komórce miernika obok procentu (komórka jest `flex items-center gap-2`).
- `app.css` (`:1057-1148`): siatka ma pięć obszarów; od `md` `category plan execution remaining meter`, poniżej `md` dwie linie `category remaining` / `meter meter`; `.category-meter-group-row` zastępuje `.category-meter-sum-row` (tło `muted`, `font-semibold`, `border-t`, ostatni wiersz tabeli); znika `.category-meter-cell-status` i obszar `status`; komentarz nad siatką aktualizowany. Plan i Realizacja nadal ukryte poniżej `md`. Bez literałów px w szablonach; klasy nazwane w `@layer components`.

#### 4. Straże

**File**: `scripts/check-ui-literals.mjs`, `tests/Unit/DashboardHardcodedUiTest.php`

**Intent**: Nowy plik widoku jest pod obiema strażami.

**Contract**: `resources/js/components/DashboardBudgetBlock.vue` dopisany do `FILES` i do `DASHBOARD_VIEW_FILES`. Plików composable i `lib` nie skanujemy (nie ma w nich markupu).

### Success Criteria:

#### Automated Verification:

- Testy JS i typy przechodzą: `npm run test`, `npm run types:check` (po zmianie importów uruchom wcześniej `npm run build`, jeśli zmienią się trasy)
- Skan literałów przechodzi z nowym plikiem: `npm run check:ui`
- Straż Dashboardu przechodzi z nowym plikiem: `tests/Unit/DashboardHardcodedUiTest.php`
- Testy Feature Dashboardu przechodzą bez edycji asercji: `tests/Feature/DashboardTest.php`
- Lint i formatowanie przechodzą: `npm run check`
- `grep -rn "category-meter-cell-status\|category-meter-group-row\|execution_tab_all" resources` nie zwraca trafień

#### Manual Verification:

- W realizacji nagłówek ma „Budżet <rok>” po lewej i Switch z „Otwórz budżet” po prawej; w szkicu jest „Otwórz budżet” bez Switcha, miary planu i karta stanu pustego
- Dwie tabele Dochody i Wydatki są jednocześnie widoczne, z wierszem „Suma …” na końcu; przekroczona kategoria ma „przekroczono” obok procentu
- Przełączenie Switcha ukrywa i pokazuje kreskę na miernikach dochodów i wydatków rozłożonych w czasie; po odświeżeniu strony wybór zostaje; w trybie prywatnym nic się nie psuje, a w konsoli jest ostrzeżenie
- „Otwórz budżet” w bloku nadal wybiera rok i przy błędzie pokazuje toast
- Przy 390 px wiersz to kategoria, Pozostało i miernik z procentem; Plan i Realizacja są ukryte, a poziomego przewijania strony nie ma

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie widoku, zanim przejdziesz do sinka.

---

## Faza 5: Sink i bramka wizualna

### Overview

`/dev/ui/dashboard` pokazuje nowy układ we wszystkich stanach, w tym oba stany Switcha. Osierocone teksty znikają, a zmianę zamykają zrzuty w obu motywach i pełna bramka.

### Changes Required:

#### 1. Kitchen sink

**File**: `resources/js/pages/dev/DashboardKitchenSink.vue`, `resources/js/pages/dev/dashboard-fixtures.ts` (jeśli potrzeba)

**Intent**: Pokazać stany, które w scenariuszach całej strony nie są widoczne, bo Switch żyje w przeglądarce, i uaktualnić opis stanów.

**Contract**: (a) nowa sekcja `data-section="year-mark-switch"`: `DashboardBudgetBlock` z danych scenariusza `overrun` wyrenderowany dwa razy z lokalnymi `ref` ustawionymi na `true` i `false` (bez `localStorage`), z podpisami „Switch wł.” i „Switch wył.”; (b) `stateMatrix`: wiersz `default` zamiast „Status „przekroczono”” mówi o „przekroczono” obok procentu i o sumach, `focus-visible` wymienia kolejność Tab (Switch, „Otwórz budżet”, wiersze kategorii, karty alertów), `hover` i `focus-visible` dotyczą wiersza kategorii i karty alertu, a nie zakładek; (c) scenariusze `draft` i `no-budget` w opisie `empty`. Fiksury danych bez zmian; nowy scenariusz tylko, jeśli sekcja Switcha nie da się zbudować z istniejących danych.

#### 2. Sprzątanie

**File**: `lang/pl/ui.php`, `resources/css/app.css`

**Intent**: Usunąć teksty i style, które po fazach 3 i 4 nie mają użytkowników.

**Contract**: każdy z kluczy `execution_tab_all`, `execution_tabs_aria`, `execution_status`, `planned`, `executed`, `year_elapsed_as_of`, `month_pace_percent`, `month_pace_none`, `notice_title_*` i `notice_text_*` usuwamy dopiero po `grep` wszystkich użyć w `resources/` i `app/` (brak trafień). `.segmented-control-tabs` (`app.css:538-540`) usuwamy tylko wtedy, gdy `grep` pokazuje, że używają go tylko zakładki Dashboardu; według audytu mogą go używać Operacje, i wtedy zostaje.

#### 3. Zrzuty

**File**: `context/changes/dashboard-design-system-page/screenshots/`

**Intent**: Bramka wizualna z `/10x-ui`: widok i sink w obu szerokościach i motywach.

**Contract**: zrzuty `/dev/ui/dashboard` (scenariusze `default`, `overrun`, `all-notice-kinds`, `draft` i sekcja Switcha) przy 390 i 1280 px, w jasnym i ciemnym motywie, zrobione przez `playwright-cli` albo podgląd z `context/foundation/agents/worktree-preview.md` (po `npm run build`).

### Success Criteria:

#### Automated Verification:

- Pełna bramka przechodzi: `composer ci:check` (w worktree wariant `docker run … ci:check` z `AGENTS.md`) oraz `vendor/bin/sloppy ci` na hoście
- Test sinka przechodzi: `tests/Feature/DashboardKitchenSinkTest.php`
- `dashboard-fixtures.test.ts` przechodzi bez edycji asercji
- Zrzuty 390/1280 × jasny/ciemny są w `context/changes/dashboard-design-system-page/screenshots/`

#### Manual Verification:

- Sekcja „year-mark-switch” pokazuje blok z kreską i bez kreski, z Switchem w odpowiednim stanie
- Zrzuty przy 390 i 1280 px w obu motywach: bez ucinania tekstu w kartach alertów i bez poziomego przewijania; pusty tor miernika jest czytelny na `muted` (kontrast sprawdzony po usunięciu wierszy grup)
- Widok wygląda jak karta `PageDashboard` poza odejściami zapisanymi w ADR-0050

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie zrzutów.

---

## Testing Strategy

### Unit Tests:

- `year-elapsed-mark-storage.test.ts`: odczyt (brak wpisu, `'true'`, `'false'`, wartość śmieciowa, wyjątek z logiem), zapis (wartość, wyjątek z logiem).
- `dashboard-notice-presentation.test.ts`: siedem rodzajów (etykieta, tożsamość, wartość), znak kwoty, `null` dla planu niezatwierdzonego, tempo z `daysPercent`.
- `DashboardHardcodedUiTest.php`: `DashboardBudgetBlock.vue` bez literałów i bez stylów sprzed Atramentu.

### Integration Tests:

- Payload się nie zmienia, więc `tests/Feature/DashboardTest.php` (`assertInertia`, siedem alertów) i `DashboardKitchenSinkTest.php` przechodzą bez edycji asercji (część `composer ci:check`).
- Spec Playwright dla Dashboardu nie istnieje i nie powstaje; bramką wizualną jest sink.

### Manual Testing Steps:

1. `/dev/ui/dashboard` przy 1280 i 390 px, jasny i ciemny motyw: miary, alerty, blok „Budżet”, szkic, brak budżetu, sekcja Switcha.
2. Prawdziwy Dashboard z budżetem w realizacji: Switch, odświeżenie strony, zachowanie wyboru; „Otwórz budżet”; klik wiersza kategorii i karty alertu; nawigacja Tab.
3. Tryb prywatny przeglądarki: Switch działa w trakcie wizyty, w konsoli ostrzeżenie zamiast błędu.

## Performance Considerations

Brak. Odczyt `localStorage` jest jednorazowy po zamontowaniu; liczba wierszy i zapytań się nie zmienia.

## Migration Notes

Brak migracji danych. Klucz `dashboard-year-elapsed` mógł być użyty przez usunięty wcześniej checkbox; format wartości nie został zweryfikowany, więc każda wartość inna niż `'false'` oznacza „włączone”.

## References

- Research: `context/changes/dashboard-design-system-page/research.md`
- Decyzja nadrzędna: `context/foundation/adr/0045-dashboard-accounts-and-cancel-follow-design-system-pages.md` (akapity „Dashboard, budget execution” i „Dashboard, notices”)
- Wzorzec tej samej pętli: `context/archive/2026-11-06-accounts-design-system-page/plan.md`, `plan-brief.md`, ADR-0047 (szablon), `reviews/impl-review.md` (F3, F4, F5)
- Poprzednia zmiana Dashboardu: `context/archive/2026-11-05-dashboard-atrament-ui/`, ADR-0043, ADR-0032, ADR-0037
- Wzorzec Switcha: `resources/js/pages/Budget.vue:249`, `resources/js/pages/Accounts.vue:16`
- Design system (podgląd, nie źródło wartości): https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, strona `PageDashboard`, komponenty `Notice`, `StatPanel`, `Switch`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0050 istnieje i jest jedynym plikiem `0050-*` w `context/foundation/adr/` — 8bd1946
- [x] 1.2 `php artisan boost:index-rules` kończy się bez błędu, a `git diff` pokazuje tylko zamierzone zmiany w regułach — 8bd1946
- [x] 1.3 `grep -rn "severity only in the icon tile" .ai/rules` nie zwraca trafień — 8bd1946

#### Manual

- [ ] 1.4 ADR-0050 zawiera tabelę siedmiu rodzajów, regułę znaku i tożsamości, układ bloku, szkic bez Switcha, układ telefonu, regułę storage, sprzeczności artefaktu i odrzucone opcje
- [ ] 1.5 `design-system.md` i `.ai/rules/js.md` nie przeczą ADR-0050

### Phase 2: Logika kliencka test-first i teksty

#### Automated

- [x] 2.1 Nowe testy najpierw były czerwone, teraz przechodzą: `npm run test` — 4a2ddff
- [x] 2.2 Typy przechodzą: `npm run types:check` — 4a2ddff
- [x] 2.3 Lint i formatowanie przechodzą: `npm run check`, po zmianach PHP `vendor/bin/pint --dirty --format agent` — 4a2ddff
- [x] 2.4 Istniejący `dashboard-fixtures.test.ts` przechodzi bez edycji — 4a2ddff

#### Manual

- [ ] 2.5 Diff testów pokazuje przypadek na każdy z siedmiu rodzajów i dwa przypadki awarii storage

### Phase 3: Trzy miary i karta alertu

#### Automated

- [x] 3.1 Testy JS i typy przechodzą: `npm run test`, `npm run types:check` — d555f82
- [x] 3.2 Skan literałów przechodzi: `npm run check:ui` — d555f82
- [x] 3.3 Straż Dashboardu przechodzi: `tests/Unit/DashboardHardcodedUiTest.php` — d555f82
- [x] 3.4 `grep -rn "yearElapsedCaption\|notice-title\|notice-text" resources` nie zwraca trafień — d555f82

#### Manual

- [ ] 3.5 Siedem kart w `all-notice-kinds` ma etykietę, tożsamość i wartość po prawej tam, gdzie jest
- [ ] 3.6 Panel miar ma trzy miary (dwie bez planu miesiąca), układ nie jest przechylony
- [ ] 3.7 Wartość i ikona mają kolor wagi w obu motywach, kontrast czytelny

### Phase 4: Blok „Budżet <rok>”

#### Automated

- [x] 4.1 Testy JS i typy przechodzą: `npm run test`, `npm run types:check` — 547565f
- [x] 4.2 Skan literałów przechodzi z nowym plikiem: `npm run check:ui` — 547565f
- [x] 4.3 Straż Dashboardu przechodzi z nowym plikiem: `tests/Unit/DashboardHardcodedUiTest.php` — 547565f
- [x] 4.4 `tests/Feature/DashboardTest.php` przechodzi bez edycji asercji — 547565f
- [x] 4.5 Lint i formatowanie przechodzą: `npm run check` — 547565f
- [x] 4.6 `grep -rn "category-meter-cell-status\|category-meter-group-row\|execution_tab_all" resources` nie zwraca trafień — 547565f

#### Manual

- [ ] 4.7 Nagłówek bloku: „Budżet <rok>” po lewej, Switch i „Otwórz budżet” po prawej; w szkicu bez Switcha, z miarami planu i kartą stanu pustego
- [ ] 4.8 Dwie tabele jednocześnie z wierszem „Suma …” i „przekroczono” obok procentu
- [ ] 4.9 Switch ukrywa i pokazuje kreskę, wybór zostaje po odświeżeniu, tryb prywatny bez błędu
- [ ] 4.10 „Otwórz budżet” w bloku wybiera rok i przy błędzie pokazuje toast
- [ ] 4.11 Przy 390 px: kategoria, Pozostało i miernik z procentem, bez poziomego przewijania

### Phase 5: Sink i bramka wizualna

#### Automated

- [x] 5.1 Pełna bramka przechodzi: `composer ci:check` oraz `vendor/bin/sloppy ci` — 58ecb69
- [x] 5.2 Test sinka przechodzi: `tests/Feature/DashboardKitchenSinkTest.php` — 58ecb69
- [x] 5.3 `dashboard-fixtures.test.ts` przechodzi bez edycji asercji — 58ecb69
- [x] 5.4 Zrzuty 390/1280 × jasny/ciemny są w `screenshots/` — 58ecb69

#### Manual

- [ ] 5.5 Sekcja „year-mark-switch” pokazuje blok z kreską i bez kreski
- [ ] 5.6 Zrzuty bez ucinania tekstu i bez poziomego przewijania; tor miernika czytelny na `muted`
- [ ] 5.7 Widok wygląda jak `PageDashboard` poza odejściami z ADR-0050
