# Plan miesiąca w grupach — plan implementacji

## Overview

S-25 z roadmapy M-3 (`context/foundation/prd-v3.md`, FR-008, must-have): plan miesiąca pokazuje kategorie w zwijanych grupach kategorii. Wiersz grupy ma sumę Planu i Faktycznie oraz sygnał „ponad plan”, gdy którakolwiek jego kategoria przekracza plan, także gdy grupa jest zwinięta. Kategorie bez grupy stoją w tej samej kolejności typu co w „Kategoriach”, Budżecie i Raportach (ADR-0069). Zmiana działa w odczycie i w trybie edycji (propozycja do zatwierdzenia, zmiana zatwierdzonego planu), gdzie sumy i sygnał nadążają za wpisywaną kwotą.

## Current State Analysis

- **Plan miesiąca nie zna grup.** Wiersz `MonthPlanItemRow::forLine` (`app/Support/MonthPlanItemRow.php:53-99`, typ phpstan `MonthPlanRow` `:12-35`) ma `category_id` i `type`, ale nie ma `group_id` ani `sort_order`. Payload `monthPlanPayload` (`app/Http/Controllers/MonthPlanController.php:158-203`) nie ma listy grup. S-22 celowo zostawił plan miesiąca płaski (`context/archive/2026-11-10-category-groups/plan.md:36`).
- **Kolejność płaskiej listy.** Wiersze idą z `MonthToSpend::forMonth` przez `BudgetLine::scopeOrdered` (zarchiwizowane na końcu, potem `categories.sort_order`, `name_key`, id). W trybie zmiany linie bez pozycji wracają z `categoriesWithJoinCandidates` (`MonthPlanController.php:209-236`, też `ordered()`). Kategorie listy bez linii budżetu (`MonthPlanJoinCandidates::rows`, `app/Support/MonthPlanJoinCandidates.php:48-58`) są doklejane na końcu (`MonthPlanController.php:174-178`). Wiersz kandydata buduje `MonthPlanJoinCandidates::row` (`:73-101`) i musi mieć te same klucze w tej samej kolejności co `forLine` (test `tests/Feature/MonthPlanPageTest.php:544`).
- **Testy na indeksach.** `MonthPlanPageTest` sprawdza `monthPlan.expense_items.N` (np. `:37`, `:883`, `:914`), więc kolejność płaskiej listy nie może się zmienić.
- **Odczyt wiersza na żywo.** `rowState` / `rowReading` w `resources/js/pages/MonthPlan.vue:381-471`: w odczycie `overPlan` i odchylenie z serwera, w edycji z TS (`isMonthItemOverPlan`, `monthItemDeviationMinorUnits`). Błędne pole daje `planMinorUnits: null` i `overPlan: false`. Sygnał przekroczenia już rozróżnia rytm: stała i bieżąca wobec miesiąca, równomierna roczna wobec roku, nieregularna przy nadwyżce ponad zebrane (`app/Support/MonthItemDeviation.php:56-78`, ADR-0066).
- **Podział wierszy na stronie.** `incomeRows`, `expenseRows` (bez bezczynnych równomiernych rocznych) i `idleRows` (`MonthPlan.vue:475-486`). Sekcja bezczynnych to `Collapsible` w `MonthPlanTable.vue:223-268`. Rozwija się sama, gdy ma błędne pole (`MonthPlan.vue:494-505`). Stan edycji resetuje się przy zmianie miesiąca i budżetu (`:118-135`).
- **Tabela.** `MonthPlanTable.vue` ma jeden `ListSurface flush`: nagłówek 7 kolumn, pasmo „Dochody”, wiersze, „Razem w roku”, pasmo „Wydatki”, wiersze, sekcja bezczynnych, „Razem w roku”, „Odkładam”, stopka. Blok bindowania `MonthPlanItemRow` powtarza się trzy razy (`:150`, `:203`, `:247`).
- **Kolizja nazwy.** `.month-plan-group-row` (`resources/css/app.css`, ok. `:940`) to pasmo sekcji typu (Dochody / Wydatki / Odkładam, ADR-0042), a nie grupa kategorii.
- **Siatka.** `.month-plan-grid` ma na telefonie 4 kolumny w obszarach `category … status` / `kind` / `approved plan actual deviation`, a od `xl` 7 kolumn w jednej linii. `.month-plan-grid-no-kind` pomija obszar `kind` (`app.css:864-890`).
- **Wzorzec grupy z Budżetu (ADR-0069).** Helper `buildCategoryTree` (`resources/js/lib/category-tree.ts:41`) układa grupy i kategorie bez grupy w jednej kolejności. Zachowuje puste grupy, a kategorię z nieznaną grupą traktuje jak bez grupy. Wiersz grupy w Budżecie (`BudgetCategoryGroupRow.vue`) ma przycisk `.budget-category-group-toggle` z `aria-expanded`, `aria-controls` i `aria-label` („Rozwiń/Zwiń grupę :name”), `ChevronDown` `-rotate-90` po zwinięciu, `Badge` `neutral` z liczbą kategorii i `Badge` `destructive-soft` „ponad plan” z podpowiedzią. Ciało grupy to `v-show` z `id`. Stan zwinięcia żyje tylko na czas wizyty (`BudgetCategoryList.vue:100-118`, `:219-226`).
- **Grupy w bazie.** `category_groups` (`id`, `user_id`, `name`, `name_key`, `type`, `sort_order`), `categories.category_group_id` nullable (`database/migrations/2026_11_10_090000_create_category_groups_table.php`). Kolejność grup: `sort_order`, `name_key`, id (`app/Support/BudgetPlanSummary.php:142-178`).
- **Kitchen sink i strażnicy.** `/dev/month-plan` (`resources/js/pages/dev/MonthPlanSink.vue`) powiela logikę `rowState` (`readingOf`, `stateOf`, `:255-338`) i podział wierszy (`splitRows`, `:97-111`). Fixtures to `resources/js/lib/month-plan-fixtures.ts`, z `item()` (`:9-35`) bez pól grupy. Strażnikami są `tests/Unit/MonthPlanHardcodedUiTest.php:7-24` i `scripts/check-ui-literals.mjs:133-144`.
- **Teksty.** `lang/pl/ui.php` ma klucze grupy w sekcji Budżetu (`group_over_plan_badge`, `group_over_plan_hint`, `group_collapse`, `group_expand`, `group_category_count`, `:256-260`) i `month_plan.status_over_plan` („ponad plan”, `:459`).
- **E2E.** `tests/e2e/irregular-collected.spec.ts` przechodzi przez plan miesiąca i ma helpery `addExpense`, `deleteExpense` i `openCurrentMonthPlan`. Użytkownik E2E ma dom demo (`demo:seed-household`).
- **ADR.** Najwyższy numer to 0078.

## Desired End State

W planie miesiąca każda sekcja typu (Dochody, Wydatki) pokazuje grupy i kategorie bez grupy w kolejności listy:

```
Wydatki
▾ Mieszkanie [2]                    2 650   3 100          ponad plan
    Czynsz            2 500         2 500   2 500       0  zapłacone
    Prąd                150           150     600     150  ponad plan
  Kredyt              1 200         1 200   1 200       0  zapłacone
▸ Codzienne [2]                     2 200   2 260          ponad plan
  Siłownia (zarch.)                     0     120     120  ponad plan
▸ Równomierne roczne bez planu i wydatków [3]
  Razem w roku …
```

- Wiersz grupy: przycisk zwijania z nazwą, `Badge` z liczbą kategorii, suma Planu, suma Faktycznie i `Badge` `destructive-soft` „ponad plan” w kolumnie Status, gdy którakolwiek kategoria grupy ma `overPlan`. Kolumny Zatwierdzony i Odchylenie są puste. Każda pokazana suma to dosłowna suma tej kolumny w wierszach grupy.
- Grupa startuje rozwinięta. Zwinięcie trzyma się do przeładowania albo zmiany miesiąca lub budżetu. W trybie edycji grupa z błędnym polem kwoty sama się rozwija, a jej suma Planu pokazuje „—”, dopóki pole jest nieczytelne.
- Kategoria bez grupy to zwykły wiersz bez wcięcia, między grupami w kolejności listy. Wiersze w grupie są wcięte.
- Wiersze zarchiwizowane stoją na końcu sekcji bez grupy. Bezczynne równomierne roczne zostają w swojej zwiniętej sekcji. Żadne z nich nie wchodzi do sumy, liczby ani sygnału grupy. Grupa bez pokazanych wierszy nie ma wiersza.
- Kandydaci do dołączenia (tryb zmiany i edytowalna propozycja) stoją w swoich grupach i liczą się do sum jak inne pokazane wiersze.
- Grupy przychodów działają tak samo. Przychód nigdy nie jest „ponad plan”, więc ich wiersz nie ma badge.
- To Spend, „Razem w roku”, panel miar i odczyt płaskiej listy na serwerze się nie zmieniają.

Weryfikacja: testy `assertInertia` pól grup, testy Vitest drzewa i sum, stany grup w `/dev/month-plan`, test E2E zwinięcia z „ponad plan”, zrzuty 1280 i 390 px w `context/changes/month-plan-groups/screenshots/`.

### Key Discoveries:

- `buildCategoryTree` (`resources/js/lib/category-tree.ts:41-96`) daje kolejność ADR-0069 bez nowej logiki w PHP. Reguła remisu to `sort_order`, potem grupa przed kategorią, potem pozycja na wejściu, a płaska lista z serwera już jest w porządku (`sort_order`, `name_key`, id).
- Sumy grupy muszą być liczone z `rowState` (`MonthPlan.vue:448-471`), a nie z pól serwera, bo w edycji plan i `overPlan` pochodzą z wpisanej kwoty. Serwerowe sumy jak w Budżecie (`BudgetPlanSummary::groupsOfType`) nadążałyby tylko w odczycie.
- Suma odchyleń wierszy nie jest „Faktycznie − Plan” grupy: nieregularna wnosi nadwyżkę ponad zebrane, a niezapłacona stała opłata ma `null` (`MonthItemDeviation.php:23-40`). Dlatego Odchylenie w wierszu grupy jest puste.
- Wiersz kandydata i wiersz linii muszą mieć identyczne klucze w tej samej kolejności (`MonthPlanPageTest.php:544`).

## What We're NOT Doing

- Zapamiętywania zwinięcia między wizytami (localStorage, serwer). Stan trzyma się tylko na czas wizyty, jak w Budżecie.
- Sumy Zatwierdzonego, sumy odchyleń, „Faktycznie − Plan” grupy ani statusu innego niż „ponad plan” na wierszu grupy.
- Bloku „Bez grupy” na końcu albo na początku sekcji (ADR-0069 go odrzucił).
- Wierszy zarchiwizowanych i bezczynnych równomiernych rocznych w grupach, w tym drugiej sekcji bezczynnych wewnątrz grupy.
- Zmian w Budżecie, Raportach, Pulpicie i „Kategoriach”. Pulpit dalej nie ma grup (`.ai/rules/js.md`).
- Planu, limitu ani ustawień na grupie (PRD Non-Goals) i podgrup.
- Zmian w regułach To Spend, Month Pace, Collected, odchylenia i „ponad plan” wiersza, w tym w parach kalkulatorów PHP/TS.
- Zmiany nazwy istniejącej klasy `.month-plan-group-row` (pasma sekcji typu).
- Zmiany kolejności `income_items` / `expense_items` w payloadzie.

## Implementation Approach

Serwer dokłada tylko dane: `group_id` i `sort_order` kategorii listy do każdego wiersza, a do payloadu listy `income_groups` / `expense_groups` (`{id, name, sort_order}`) z jednego zapytania. Drzewo, sumy, liczbę i sygnał buduje czysta funkcja TS w `resources/js/lib/month-plan-groups.ts` na `buildCategoryTree`, z odczytu każdego wiersza podanego przez stronę (`rowState`). Dzięki temu ta sama ścieżka działa w odczycie i w edycji, a grupa nie dostaje własnej reguły ani pary kalkulatorów. Wiersz grupy to nowy komponent `MonthPlanGroupRow.vue` na siatce `.month-plan-grid month-plan-grid-no-kind` z własną nazwaną klasą. Przycisk zwijania kopiuje dostępność z `BudgetCategoryGroupRow`. Stan zwinięcia należy do strony (`v-model` do tabeli), tak jak `idleOpen`, bo strona już resetuje stan przy zmianie miesiąca i umie rozwinąć sekcję z błędnym polem.

## Phase 1: Decyzja w dokumentach

### Overview

Przed kodem zapisujemy regułę wiersza grupy w planie miesiąca: ADR-0079, wpis w słowniku, design system i regułę ścieżki widoku. AGENTS.md wymaga, by zmiana reguły słownika zaczynała się od wpisu i ADR.

### Changes Required:

#### 1. ADR-0079

**File**: `context/foundation/adr/0079-category-group-row-in-the-month-plan.md`

**Intent**: Zapisać, jak plan miesiąca pokazuje grupę kategorii, i powiązać to z ADR-0042 (układ planu miesiąca), ADR-0060 (Zatwierdzony pusty w wierszach sum), ADR-0066 (sygnał według rytmu), ADR-0069 (kolejność, wiersz grupy w Budżecie) i ADR-0072 (sekcja bezczynnych).

**Contract**: Format jak `0078-category-merge.md` (tytuł-zdanie, proza po angielsku). Treść:

- Ungrouped Categories stand in the one order of their type.
- What the group row shows: Plan and Actual sums of its shown rows, the count, „ponad plan” when any row is over plan by its Rhythm. Approved and Deviation stay empty, and the ADR says why, with the example Prąd 150 / 600 / surplus 150 in group Mieszkanie.
- Which rows a group holds: active, not idle Even Annual. Archived rows stand at the end of the type without a group, idle Even Annual rows stay in their section, and join candidates are in their group.
- A group without shown rows has no row.
- The sums are computed in the client from the row readings, so they follow the typed plan in edit mode. An unreadable field gives „—” and opens the group.
- Groups start expanded, and the state is kept for the visit only.
- Rejected alternatives: the sum of deviations, „Faktycznie − Plan”, a „Bez grupy” block, archived rows in the group, groups starting collapsed, remembering the collapse.

#### 2. Słownik

**File**: `context/foundation/glossary.md` (wpis **Category Group**, ok. `:44`)

**Intent**: Doprecyzować, co grupa sumuje w Month Plan, obok istniejących zdań o Period Report i Budżecie.

**Contract**: Jedno zdanie z odwołaniem do ADR-0079, np. „In the Month Plan a group row sums Plan and Actual of its active Categories that are not idle Even Annual, shows „ponad plan” when any of them is over plan, and starts expanded (ADR-0079).” Sekcja _Avoid_ bez zmian.

#### 3. Design system i reguła widoku

**File**: `context/foundation/design-system.md` (lista wierszy `.month-plan-grid`, ok. `:19`, i opis kitchen sinka `/dev/month-plan`, ok. `:125`); `.ai/rules/js-month-plan.md`

**Intent**: Opisać nowy wiersz grupy i jego klasy, żeby kolejny agent nie pomylił go z pasmem sekcji typu i nie dodał wartości arbitralnych.

**Contract**:

- Nowe nazwy klas: `.month-plan-category-group-row` (wiersz grupy), `.month-plan-category-group-body` (ciało z `id` dla `aria-controls`) i `.month-plan-row-nested` (wcięcie wiersza w grupie).
- Wiersz grupy nie jest pasmem `muted`, bo pasmo zostaje dla sekcji typu. Ma nazwę z `ChevronDown` w przycisku `.budget-category-group-toggle`, sumy w stylu `amount-sum` bez „zł” i badge.
- `.ai/rules/js-month-plan.md`, akapit o klasach siatki: dopisać te trzy klasy i zdanie, że `.month-plan-group-row` to pasmo typu.
- Nowy akapit: drzewo i sumy grup pochodzą z `monthPlanGroupTree` (`lib/month-plan-groups.ts`) nad `rowState`, a nie z serwera.
- Akapit o kitchen sinku: dopisać stany grup.

### Success Criteria:

#### Automated Verification:

- ADR-0079 istnieje i jest jedynym plikiem z numerem 0079: `ls context/foundation/adr | grep -c '^0079-'` zwraca 1
- Wpis Category Group w słowniku odwołuje się do ADR-0079: `grep -n 'ADR-0079' context/foundation/glossary.md`

#### Manual Verification:

- ADR-0079 i wpis w słowniku opisują dokładnie decyzje z tego planu (kolumny wiersza grupy, kolejność, wiersze poza grupą, start rozwinięty)

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie treści ADR przed kodem.

---

## Phase 2: Dane grup w propsach

### Overview

Serwer wysyła przy każdym wierszu planu miesiąca grupę i miejsce kategorii na liście oraz listy grup obu typów. Kolejność płaskiej listy się nie zmienia.

### Changes Required:

#### 1. Wiersz planu miesiąca i wiersz kandydata

**File**: `app/Support/MonthPlanItemRow.php`; `app/Support/MonthPlanJoinCandidates.php`

**Intent**: Dodać do wiersza dwie liczby z kategorii listy, potrzebne klientowi do drzewa. Kandydat musi mieć te same klucze w tej samej kolejności.

**Contract**:

- Nowe klucze `group_id: int|null` (`categories.category_group_id`) i `sort_order: int` (`categories.sort_order`), wstawione zaraz po `type` w `forLine` i w `MonthPlanJoinCandidates::row`.
- Typ phpstan `MonthPlanRow` rozszerzony o oba klucze.
- `forLine` czyta je z już załadowanej kategorii linii (`$category->category`), bez dodatkowego zapytania na wiersz.

#### 2. Listy grup w payloadzie

**File**: `app/Http/Controllers/MonthPlanController.php` (`monthPlanPayload`, docblock tablicy)

**Intent**: Dać klientowi nazwy i kolejność grup użytkownika, osobno dla przychodów i wydatków.

**Contract**:

- Nowe klucze `income_groups` i `expense_groups`: `list<array{id: int, name: string, sort_order: int}>`.
- Grupy użytkownika danego typu, w kolejności `sort_order`, `name_key`, id. Wysyłane są wszystkie grupy typu, bo klient i tak pomija grupy bez pokazanych wierszy.
- Jedno zapytanie na żądanie, poza pętlą (Sloppy).
- Klucze w payloadzie stoją obok `income_items` / `expense_items`.

#### 3. Typy TS

**File**: `resources/js/types/month-plan.ts`

**Intent**: Lustro PHP w tym samym commicie (`.ai/rules/inertia-props.md`).

**Contract**:

- `MonthPlanItem` dostaje `group_id: number | null` i `sort_order: number`.
- Nowy typ `MonthPlanGroup = { id: number; name: string; sort_order: number }`.
- `MonthPlanReading` dostaje `income_groups: MonthPlanGroup[]` i `expense_groups: MonthPlanGroup[]`.
- `item()` w `resources/js/lib/month-plan-fixtures.ts` dostaje domyślne `group_id: null`, `sort_order: 0`, a `buildMonthPlanReading` puste listy grup, żeby `npm run types:check` przechodził.

#### 4. Testy strony

**File**: `tests/Feature/MonthPlanPageTest.php`

**Intent**: Udowodnić kształt propsów (`assertInertia`) i to, że płaska kolejność się nie zmieniła.

**Contract**: Nowe testy:

- wiersz kategorii w grupie ma `group_id` i `sort_order` z kategorii listy, a wiersz bez grupy ma `group_id` null;
- `income_groups` i `expense_groups` zawierają tylko grupy swojego typu, w kolejności `sort_order`, z `id`, `name` i `sort_order`;
- kandydat do dołączenia w trybie zmiany ma pole `group_id` swojej kategorii, a istniejący test równości kluczy (`:544`) dalej przechodzi;
- przypisanie kategorii z `canonicalBudget()` do grupy nie zmienia kolejności `expense_items`.

Istniejące testy na indeksach przechodzą bez zmian asercji.

### Success Criteria:

#### Automated Verification:

- Testy strony planu miesiąca przechodzą na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --compact tests/Feature/MonthPlanPageTest.php` (polecenie worktree z AGENTS.md)
- Testy zatwierdzania, zmiany i propozycji przechodzą: `… artisan test --compact tests/Feature/ApproveMonthPlanTest.php tests/Feature/ChangeMonthPlanTest.php tests/Feature/MonthPlanProposalTest.php`
- Kontrola typów przechodzi: `npm run types:check`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych zgłoszeń: `vendor/bin/sloppy ci`

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie przed kolejną fazą (tu brak kroków ręcznych; wystarczy zielony zestaw).

---

## Phase 3: Drzewo i sumy grup w TS

### Overview

Czysta funkcja buduje z wierszy sekcji i list grup drzewo z sumami i sygnałem na podstawie odczytu każdego wiersza. Faza nadaje się do TDD.

### Changes Required:

#### 1. Drzewo grup planu miesiąca

**File**: `resources/js/lib/month-plan-groups.ts`

**Intent**: Jedno miejsce, które składa sekcję typu w kolejności ADR-0069, odkłada wiersze zarchiwizowane na koniec i liczy wiersz grupy z odczytów wierszy, tak że ten sam kod obsługuje odczyt i edycję.

**Contract**: Sygnatura, od której zależy faza 4:

```ts
export type MonthPlanRowReading = { planMinorUnits: number | null; overPlan: boolean };

export type MonthPlanGroupEntry = {
    kind: 'group';
    group: MonthPlanGroup;
    rows: MonthPlanItem[];
    /** Sum of the shown plan; null while any row's plan is unreadable. */
    planMinorUnits: number | null;
    actualMinorUnits: number;
    overPlan: boolean;
    hasUnreadablePlan: boolean;
};

export type MonthPlanTreeEntry =
    | MonthPlanGroupEntry
    | { kind: 'category'; row: MonthPlanItem };

export function monthPlanGroupTree(
    groups: MonthPlanGroup[],
    rows: MonthPlanItem[],
    readRow: (row: MonthPlanItem) => MonthPlanRowReading,
): MonthPlanTreeEntry[];
```

Reguły:

- `rows` to pokazane wiersze sekcji bez bezczynnych.
- Wiersze nie zarchiwizowane idą przez `buildCategoryTree` (`id` = `category_id`). Wiersze zarchiwizowane trafiają na koniec jako `kind: 'category'`, w kolejności wejścia, także gdy mają `group_id`.
- Grupa bez wierszy jest pomijana.
- `planMinorUnits` to suma `readRow(row).planMinorUnits`, a `null`, gdy którakolwiek jest `null`.
- `actualMinorUnits` to suma `actual_minor_units`.
- `overPlan` to „którykolwiek `readRow(row).overPlan`”.
- Liczba kategorii to `rows.length`.

#### 2. Testy

**File**: `resources/js/lib/month-plan-groups.test.ts`

**Intent**: Przypadki brzegowe z decyzji, na liczbach z planu.

**Contract**:

- **Kolejność:** grupa, kategoria bez grupy i grupa przeplatają się według `sort_order`; remis grupa/kategoria rozstrzyga grupa.
- **Zarchiwizowana kategoria z `group_id`:** stoi na końcu, a grupa jej nie liczy (Codzienne 2 200 / 2 260 bez Siłowni 0 / 120).
- **Grupy bez wierszy:** grupa bez wierszy i grupa z samymi zarchiwizowanymi nie mają wpisu.
- **Sumy:** Mieszkanie ma Plan 2 650 i Faktycznie 3 100 z Czynszu 2 500 / 2 500 i Prądu nieregularnego 150 / 600.
- **„Ponad plan”:** gdy jeden wiersz ma `overPlan`, grupa ma `overPlan` niezależnie od rytmu, bo rytm rozstrzyga `readRow`. Przypadek równomiernej rocznej z `overPlan` wobec roku przy miesiącu w planie. Grupa bez przekroczeń ma `false`.
- **Nieczytelne pole:** `planMinorUnits: null` daje sumę `null` i `hasUnreadablePlan: true`.
- **Nieznana grupa:** wiersz z `group_id` spoza listy stoi jak bez grupy.
- **Przychody:** grupa przychodów sumuje tak samo.

### Success Criteria:

#### Automated Verification:

- Testy Vitest drzewa przechodzą: `npx vitest run resources/js/lib/month-plan-groups.test.ts`
- Cały zestaw Vitest przechodzi: `npx vitest run`
- Kontrola typów przechodzi: `npm run types:check`

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie przed kolejną fazą (tu brak kroków ręcznych).

---

## Phase 4: Wiersz grupy w tabeli i kitchen sinku

### Overview

Strona i tabela pokazują drzewo z fazy 3. Dochodzi nowy wiersz grupy, zwijanie z auto-rozwijaniem, stany w kitchen sinku, strażnicy UI, test E2E i zrzuty.

### Changes Required:

#### 1. Klasy siatki

**File**: `resources/css/app.css` (blok `.month-plan-*`, ok. `:864-955`)

**Intent**: Nazwane klasy nowego wiersza zamiast wartości arbitralnych (`.ai/rules/css.md`, `.ai/rules/js-month-plan.md`).

**Contract**:

- `.month-plan-category-group-row` to wiersz na `.month-plan-grid month-plan-grid-no-kind`. Ma obramowanie i odstępy jak `.month-plan-row`, nie ma pasma `muted`.
- `.month-plan-category-group-body` to ciało grupy.
- `.month-plan-row-nested` wcina komórkę kategorii wiersza w grupie (tylko tokeny odstępów).
- Na telefonie wiersz grupy ma nazwę i status w pierwszej linii, a sumy w obszarach `plan` / `actual` w drugiej.

#### 2. Wiersz grupy

**File**: `resources/js/components/MonthPlanGroupRow.vue` (nowy)

**Intent**: Wiersz grupy w kolumnach planu miesiąca, z dostępnością przycisku jak w Budżecie.

**Contract**:

- Propsy: `name`, `count`, `planMinorUnits: number | null`, `actualMinorUnits`, `overPlan`, `open`, `controls` (id ciała). Emituje `toggle`.
- Przycisk `.budget-category-group-toggle` z `aria-expanded`, `aria-controls` i `aria-label` z nowych tekstów `month_plan.group_expand` / `group_collapse`.
- Ikona `ChevronDown` z `-rotate-90`, gdy zwinięta.
- `Badge` `neutral` z liczbą (`aria-label` `month_plan.group_category_count`).
- Sumy przez `formatMoneyAmount`, styl `amount-sum`; `null` daje „—”.
- `Badge` `destructive-soft` z `t.month_plan.status_over_plan` i podpowiedzią `month_plan.group_over_plan_hint`, gdy `overPlan`.
- Komórki Zatwierdzony i Odchylenie puste.

#### 3. Tabela

**File**: `resources/js/components/MonthPlanTable.vue`

**Intent**: Sekcje Dochody i Wydatki renderują drzewo zamiast płaskiej listy. Sekcja bezczynnych, sumy roku i „Odkładam” bez zmian.

**Contract**:

- Nowe propsy `incomeGroups: MonthPlanGroup[]` i `expenseGroups: MonthPlanGroup[]`.
- Nowy model `collapsedGroupIds` (`defineModel<number[]>`, domyślnie `[]`).
- Drzewa liczone przez `monthPlanGroupTree(groups, rows, rowState)`.
- Wiersze grupy w `.month-plan-category-group-body` z `v-show` i `id`, z klasą wcięcia.
- Powtarzany blok bindowania `MonthPlanItemRow` zebrany w jedno miejsce, żeby nie powtarzał się pięć razy.
- Pusta sekcja („empty_list”) działa jak dziś.

#### 4. Strona

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Strona trzyma stan zwinięcia i rozwija grupę z błędnym polem, tak jak sekcję bezczynnych.

**Contract**:

- `collapsedGroupIds` (`ref<number[]>`) przekazywane do tabeli jako `v-model:collapsed-group-ids`.
- Reset do `[]` w istniejącym `watch` na zmianę miesiąca i budżetu (`:128-135`).
- `watch` na id grup, w których `hasUnreadablePlan` w trybie edycji, usuwa je z `collapsedGroupIds`. Używa tego samego drzewa co tabela (wspólny `computed` albo funkcja z `lib`), bez drugiej reguły.
- Przekazanie `monthPlan.income_groups` / `expense_groups`.

#### 5. Teksty

**File**: `lang/pl/ui.php` (sekcja `month_plan`)

**Intent**: Własne klucze planu miesiąca, jak w Budżecie i Raportach.

**Contract**:

- `group_expand` „Rozwiń grupę :name”
- `group_collapse` „Zwiń grupę :name”
- `group_category_count` „Liczba kategorii w grupie”
- `group_over_plan_hint` „Co najmniej jedna kategoria w grupie przekroczyła plan”

#### 6. Kitchen sink, fixtures i strażnicy

**File**: `resources/js/lib/month-plan-fixtures.ts`; `resources/js/pages/dev/MonthPlanSink.vue`; `tests/Unit/MonthPlanHardcodedUiTest.php`; `scripts/check-ui-literals.mjs`

**Intent**: Każdy stan grupy widoczny w `/dev/month-plan`, a nowy komponent pod strażnikami.

**Contract**:

- Fixtures: grupy Mieszkanie i Codzienne (wydatki) oraz Wynagrodzenia (przychody), z `group_id` i `sort_order` na istniejących wierszach.
- Nowe stany w macierzy:
  - odczyt z grupami: jedna zwinięta z „ponad plan”, kategoria bez grupy między grupami, zarchiwizowana na końcu;
  - edycja z błędnym polem w grupie: grupa rozwinięta, suma „—”;
  - grupa przychodów;
  - telefon 390 px.
- Sink przekazuje grupy i `collapsedGroupIds` do `MonthPlanTable` (z możliwością startu ze zwiniętą grupą).
- `MonthPlanGroupRow.vue` dopisany do `MONTH_PLAN_VIEW_FILES` i do listy w `check-ui-literals.mjs`.

#### 7. Test E2E

**File**: `tests/e2e/month-plan-groups.spec.ts` (nowy)

**Intent**: Chroni ryzyko #7 z `test-plan.md`: zwinięta grupa ukrywa przekroczenie albo nie da się jej rozwinąć, choć testy HTTP i Vitest przechodzą.

**Contract**: Jak `irregular-collected.spec.ts`:

1. Test tworzy w „Kategoriach” grupę wydatków i przypisuje do niej własną kategorię bieżącą z planem 0.
2. Księguje wydatek na tę kategorię.
3. W planie bieżącego miesiąca sprawdza wiersz grupy z sumą Faktycznie i „ponad plan”.
4. Zwija grupę: wiersz kategorii znika, „ponad plan” zostaje na wierszu grupy.
5. Rozwija grupę: kategoria wraca.
6. Na koniec usuwa operację, archiwizuje kategorię i usuwa grupę.

Helpery `addExpense`, `deleteExpense` i `openCurrentMonthPlan` skopiowane albo wydzielone, jeśli istnieje wspólny moduł.

### Success Criteria:

#### Automated Verification:

- Strażnik UI planu miesiąca przechodzi: `… artisan test --compact tests/Unit/MonthPlanHardcodedUiTest.php`
- Kontrola literałów UI przechodzi: `npm run check:ui`
- Kontrola typów i lint przechodzą: `npm run types:check` oraz lint z `composer ci:check`
- Cały zestaw Vitest przechodzi: `npx vitest run`
- Pełna bramka na PostgreSQL przechodzi: `docker run … --entrypoint composer sail-8.5/app ci:check` oraz `vendor/bin/sloppy ci`
- Nowy test E2E przechodzi: `npx playwright test tests/e2e/month-plan-groups.spec.ts`
- Istniejący E2E planu miesiąca przechodzi: `npx playwright test tests/e2e/irregular-collected.spec.ts`

#### Manual Verification:

- `/dev/month-plan` pokazuje wszystkie stany grup, a zrzuty 1280 i 390 px leżą w `context/changes/month-plan-groups/screenshots/`
- W prawdziwym planie bieżącego miesiąca grupa zwija się i rozwija z klawiatury (Tab, Enter), a czytnik ekranu ogłasza stan przycisku
- W trybie zmiany wpisanie kwoty w grupie zmienia sumę Planu grupy, a błędna kwota w zwiniętej grupie ją rozwija
- Wiersz grupy jest wyraźnie inny niż pasmo sekcji Dochody / Wydatki, a wiersze w grupie są czytelnie wcięte

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie wyglądu i zachowania przed zamknięciem zmiany.

---

## Testing Strategy

### Unit Tests:

- `month-plan-groups.test.ts` testuje kolejność, sumy, sygnał, wiersze zarchiwizowane, puste grupy, nieczytelne pole, nieznaną grupę i przychody (faza 3).
- `MonthPlanHardcodedUiTest` obejmuje nowy komponent.

### Integration Tests:

- `MonthPlanPageTest`: `assertInertia` pól `group_id`, `sort_order`, `income_groups` i `expense_groups`, równość kluczy wiersza kandydata i niezmieniona kolejność płaskiej listy.
- E2E `month-plan-groups.spec.ts` sprawdza zwinięcie z „ponad plan” w działającej aplikacji, a `irregular-collected.spec.ts` brak regresji.

### Manual Testing Steps:

1. Otwórz `/dev/month-plan` i przejrzyj stany grup w 1280 i 390 px.
2. Na devie przypisz w „Kategoriach” dwie kategorie do grupy i otwórz plan bieżącego miesiąca. Sprawdź sumy z wierszami pod grupą i zwiń grupę z przekroczoną kategorią.
3. Wejdź w „Zmień plan”, wpisz kwotę w grupie, potem błędną kwotę w zwiniętej grupie. Grupa ma się rozwinąć, a suma pokazać „—”.
4. Przełącz miesiąc i wróć. Grupy mają być znów rozwinięte.

## Performance Considerations

Jedno dodatkowe zapytanie o grupy na żądanie strony. Drzewo w TS jest liniowe względem liczby wierszy (kilkadziesiąt).

## Migration Notes

Bez migracji i bez zmiany snapshotu: `category_groups` i `categories.category_group_id` istnieją od S-22.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-25)
- PRD: `context/foundation/prd-v3.md:179-180` (FR-008), `:274` (grupa nie ma reguły)
- ADR-0042, ADR-0060, ADR-0066, ADR-0069, ADR-0070, ADR-0072 w `context/foundation/adr/`
- Wzorzec wiersza grupy: `resources/js/components/BudgetCategoryGroupRow.vue`, `resources/js/components/BudgetCategoryList.vue:100-226`
- Drzewo: `resources/js/lib/category-tree.ts:41`
- Odczyt wiersza: `resources/js/pages/MonthPlan.vue:381-505`
- Payload: `app/Http/Controllers/MonthPlanController.php:158-267`, `app/Support/MonthPlanItemRow.php`, `app/Support/MonthPlanJoinCandidates.php`
- Odłożone z S-22: `context/archive/2026-11-10-category-groups/plan.md:36`, `:43`
- E2E wzorzec: `tests/e2e/irregular-collected.spec.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzja w dokumentach

#### Automated

- [x] 1.1 ADR-0079 istnieje i jest jedynym plikiem z numerem 0079 — 25ede1b
- [x] 1.2 Wpis Category Group w słowniku odwołuje się do ADR-0079 — 25ede1b

#### Manual

- [x] 1.3 ADR-0079 i wpis w słowniku opisują dokładnie decyzje z tego planu — 25ede1b

### Phase 2: Dane grup w propsach

#### Automated

- [x] 2.1 Testy strony planu miesiąca przechodzą na PostgreSQL
- [x] 2.2 Testy zatwierdzania, zmiany i propozycji przechodzą
- [x] 2.3 Kontrola typów przechodzi
- [x] 2.4 Formatowanie PHP
- [x] 2.5 Sloppy bez nowych zgłoszeń

### Phase 3: Drzewo i sumy grup w TS

#### Automated

- [ ] 3.1 Testy Vitest drzewa przechodzą
- [ ] 3.2 Cały zestaw Vitest przechodzi
- [ ] 3.3 Kontrola typów przechodzi

### Phase 4: Wiersz grupy w tabeli i kitchen sinku

#### Automated

- [ ] 4.1 Strażnik UI planu miesiąca przechodzi
- [ ] 4.2 Kontrola literałów UI przechodzi
- [ ] 4.3 Kontrola typów i lint przechodzą
- [ ] 4.4 Cały zestaw Vitest przechodzi
- [ ] 4.5 Pełna bramka na PostgreSQL przechodzi
- [ ] 4.6 Nowy test E2E przechodzi
- [ ] 4.7 Istniejący E2E planu miesiąca przechodzi

#### Manual

- [ ] 4.8 `/dev/month-plan` pokazuje wszystkie stany grup, a zrzuty 1280 i 390 px leżą w folderze zmiany
- [ ] 4.9 Grupa zwija się i rozwija z klawiatury, a czytnik ogłasza stan przycisku
- [ ] 4.10 W trybie zmiany suma Planu grupy nadąża za wpisem, a błędna kwota rozwija zwiniętą grupę
- [ ] 4.11 Wiersz grupy różni się od pasma sekcji, a wiersze w grupie są wcięte
