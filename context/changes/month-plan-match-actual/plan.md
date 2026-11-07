# month-plan-match-actual — plan implementacji

## Overview

W trybie zmian zatwierdzonego Planu miesiąca łatwo ustawić plan pozycji na obecną sumę wydatków:

- **automatycznie** — przy wejściu w tryb zmian pozycja wydatku w planie, której wydatki przekraczają Bieżący plan, startuje z planem równym wydatkom;
- **przyciskiem „plan = wydatki”** — w komórce „Faktycznie”, widocznym, gdy wydatki są większe niż kwota w polu;
- **znacznikiem** — neutralna ikona w tym samym miejscu potwierdza, że plan wiersza równa się wydatkom ponad zapisany plan.

Wszystko jest widoczne przed zapisem, nic nie zapisuje się samo, „Anuluj” zrzuca podniesienia. To zmiana reguły domenowej (sygnał „pozycja ponad plan”), więc najpierw glosariusz i ADR-0062, potem kod. Serwer, propsy i baza się nie zmieniają.

## Current State Analysis

Pełne ustalenia: `context/changes/month-plan-match-actual/research.md`. Najważniejsze:

- **Tryb zmian istnieje tylko dla planu zatwierdzonego** (`can_change`: `state === 'approved' && timing !== 'past'`, `MonthPlanController.php:164`). W propozycji pola są edytowalne od razu, bez trybu zmian (`isEditable`, `MonthPlan.vue:72-77`).
- **Pola to tekst, odczyt to `parsedAmount`.** `amountInputs: Record<number, string>` (`MonthPlan.vue:88`) budowane z `plan_minor_units` przez `initialAmountInputs()` (`:79-86`); `startChanging()` (`:133`) i `stopChanging()` (`:139`) przebudowują je z propsów. Z `parsedAmount` (`:156`) korzystają To Spend (`:252`), tempo (`:282`), odchylenie i „ponad plan” (`rowReading`, `:401`), status i strzałka (`rowState`, `:441`) oraz wysyłka (`submittedAmounts`, `:191`, tylko wiersze `changed`).
- **Wiersz:** komórka „Faktycznie” (`MonthPlanItemRow.vue:214-221`) to podpis `meta` (`xl:sr-only`) i kwota; nie ma w niej pola. Komórka „Zatwierdzony” (`:155-185`) pokazuje wzorzec: `.month-plan-cell-approved` jest flexem z zawijaniem (`app.css:897-900`), a strzałka `PlanChangeArrow` stoi przed kwotą i poniżej `xl` w linii podpisu. Siatka: poniżej `xl` cztery równe kolumny `approved plan actual deviation`, od `xl` „Faktycznie” ma 7 rem (`app.css:864-885`).
- **Kanał stanu wiersza:** typ `MonthPlanRowState` (`MonthPlanTable.vue:26-35`), wyliczany przez `rowState()` w stronie i przez `stateOf()` w kitchen sinku (`pages/dev/MonthPlanSink.vue:199-223`). Tabela przekazuje zdarzenia wiersza w trzech grupach: dochody, wydatki, jednorazowe zwinięte (`MonthPlanTable.vue:170-182`, `:223-235`, `:267-279`).
- **Wydatki** = `actual_minor_units`, suma netto (zwroty pomniejszają; może być ujemna). Kwota planu ma walidację `0…1e12` (`UpdateMonthPlanRequest.php:306-310`, `MAX_PLAN_AMOUNT_MINOR_UNITS` w `lib/month-plan-amount.ts:4`).
- **Skutki podniesienia** (z kodu): To Spend bez zmian (Running = `max(plan, actual)`, Bill = `actual` po pierwszej dodatniej operacji, `ToSpendCalculator.php:76-80`); odchylenie → 0, „ponad plan” i plakietka znikają; tempo maleje; strzałka w „Zatwierdzony” pokazuje wzrost w `destructive`. Po zapisie znika powiadomienie Dashboardu `month_item_over_plan` (`DashboardNotices.php:122-160`) i zeruje się odchylenie pozycji w Podsumowaniu miesiąca.
- **Brak testów komponentów Vue:** testy JS to `lib/*.test.ts` (`vite-plus/test`). Brak E2E Planu miesiąca w `tests/e2e/`.

## Desired End State

- Po „Zmień” pole planu wiersza wydatku w planie z `actual > plan` pokazuje kwotę wydatków; „Zapisz zmiany” jest od razu aktywne; podglądy (To Spend, tempo, odchylenie, status, strzałka) liczą z podniesionej kwoty. W komórce „Faktycznie” stoi neutralna ikona `Equal` z tooltipem i `aria-label` „Plan podniesiony do wydatków”.
- Gdy użytkownik obniży pole poniżej wydatków (albo w kandydacie do dołączenia z wydatkami > 0), w komórce „Faktycznie” pojawia się przycisk „Ustaw plan na wydatki”; kliknięcie wpisuje wydatki do pola, przenosi fokus na pole, a w miejscu przycisku pojawia się znacznik.
- „Anuluj” przywraca zapisane kwoty. Zapis zmienia tylko Bieżący plan (Zatwierdzony nigdy).
- Glosariusz („Current Month Plan”, „To Spend”) i ADR-0062 opisują regułę i jej skutki; `design-system.md` wymienia nowy komponent.
- Kitchen sink `/dev/month-plan` pokazuje nowe stany; `composer ci:check` i `vendor/bin/sloppy ci` przechodzą.

### Key Discoveries:

- Jedno miejsce zaczepienia: wpisanie kwoty do `amountInputs` w `startChanging` (`MonthPlan.vue:133`) zasila wszystkie podglądy i wysyłkę bez zmian w kalkulatorach (reguła `.ai/rules/month-calculators.md`: bez nowego odczytu nie ma nowej kopii PHP/TS).
- Przycisk działa tylko w górę, więc przycisk i znacznik nigdy nie występują razem — jedno miejsce w komórce „Faktycznie” wystarcza.
- Znacznik wynika ze stanu pola, bez nowego stanu strony: wydatek, `pole === actual` i `actual > plan_minor_units`.
- `IconAction` ma 26 px (`--control-height-sm`) i rozepchałby linię podpisu poniżej `xl`; wzorzec to goły `<button>` z `PlanChangeArrow.vue`, z polem trafienia powiększonym nazwaną klasą.
- Zmiana rodzaju pozycji (`router.patch` z `preserveState`, `MonthPlan.vue:361-388`) nie przebudowuje `amountInputs`, więc podniesienia przetrwają przełączenie Bill/Running.

## What We're NOT Doing

- **Propozycja (stan `proposal`):** ani automatu, ani przycisku — tylko tryb zmian planu zatwierdzonego.
- **Automat dla kandydatów do dołączenia:** kandydat dostaje tylko przycisk; automat nigdy po cichu nie dołącza kategorii do planu.
- **Obniżanie planu do wydatków jednym kliknięciem:** przycisk tylko podnosi; przy wydatkach ≤ kwocie w polu (także ujemnych) go nie ma.
- **Dochody i odkładanie:** poza zakresem („ponad plan” dotyczy tylko wydatku, `MonthItemDeviation.php:46-49`; odkładanie nie ma wydatków).
- **Zmiany serwera:** bez nowego propsa, kolumny, migracji ani zmian w `DashboardNotices`; bez zapisu śladu „podniesiony automatycznie”.
- **Liczenie „ponad plan” względem Zatwierdzonego:** odrzucone; sygnał dalej porównuje z Bieżącym planem.
- **Nowy kolor lub znaczenie koloru:** znacznik jest w `muted-foreground`.
- **Podsumowanie podniesień w pasku edycji lub w podpowiedzi** (np. „podniesiono N pozycji”): ikona w wierszu wystarcza.
- **Ponowne podnoszenie przy późnym przypływie wydatków** w trakcie trybu zmian (bez obserwatora propsów).
- **E2E:** brak gotowego stanu „plan zatwierdzony z przekroczeniem” w danych E2E; można dodać później przez `/10x-e2e`.
- **Ochrona przed opuszczeniem strony z niezapisanymi zmianami:** dziś jej nie ma i ta zmiana jej nie dodaje.

## Implementation Approach

Reguła żyje w jednej czystej funkcji w `resources/js/lib/month-plan-match-actual.ts` z testami obok: kwoty startowe trybu zmian, oferta przycisku i znacznik. Strona woła ją w `startChanging` i w `rowState`; wiersz tylko renderuje wynik i emituje kliknięcie. Kolejność faz: glosariusz i ADR (twarda reguła AGENTS.md), potem logika ze stroną (podniesienie działa w polach), potem przycisk i znacznik w UI z kitchen sinkiem i listami stróżującymi.

## Critical Implementation Details

**Fokus po kliknięciu.** Kliknięcie przycisku zamienia go na znacznik, więc element z fokusem znika. Wiersz po emisji przenosi fokus na swoje pole planu (`nextTick`), żeby użytkownik klawiatury nie wypadał na `body` i od razu mógł skorygować kwotę.

**Pole trafienia przycisku.** Ikona ma `size-3.5` jak strzałka, ale przycisk jest akcją: nazwana klasa w `app.css` daje mu pole trafienia co najmniej 24 px (WCAG 2.2, 2.5.8) bez podnoszenia linii podpisu poniżej `xl` (np. ujemny margines). Wymiar sprawdzić w przeglądarce przy 390 px i przy `xl`.

## Phase 1: Glosariusz i ADR-0062

### Overview

Zapisuje regułę domenową, zanim powstanie kod: kiedy plan startuje od wydatków, co robi przycisk i jakie są skutki dla sygnału „ponad plan”.

### Changes Required:

#### 1. Glosariusz

**File**: `context/foundation/glossary.md`

**Intent**: Wpis „Current Month Plan” zyskuje regułę podniesienia do wydatków; wpis „To Spend” zyskuje zdanie, że zapisany plan podniesiony do wydatków kończy sygnał „item over plan” dla tej pozycji.

**Contract**: Wpis **Current Month Plan** (dziś `glossary.md:71-73`) dopisuje po angielsku:

- w trybie zmian pozycja wydatku w planie, której actual amount w miesiącu przekracza Current Month Plan, startuje z planem równym actual amount (ADR-0062);
- akcja „match actual” ustawia plan wiersza wydatku (także kategorii spoza planu, która może do niego dołączyć) na actual amount, tylko gdy jest on większy niż wpisana kwota;
- użytkownik może obniżyć kwotę; nic nie zapisuje się przed „Zapisz zmiany”, a „Anuluj” odrzuca podniesienia;
- Approved Month Plan się nie zmienia;
- polski tekst UI: „Ustaw plan na wydatki”, „Plan podniesiony do wydatków”.

Wpis **To Spend** (`:79-81`) dopisuje zdanie: zapisany plan podniesiony do actual amount kończy dla tej pozycji powiadomienie „item over plan”, a przekroczenie widać tylko jako wzrost względem Approved Month Plan (ADR-0062). Żaden termin _Avoid_ się nie zmienia.

#### 2. ADR-0062

**File**: `context/foundation/adr/0062-month-plan-change-mode-raises-plan-to-actual.md`

**Intent**: Nowy ADR (po angielsku, w formacie sąsiednich ADR-ów: tytuł, kontekst, decyzja w akapitach, na końcu odrzucone alternatywy) z decyzjami tej zmiany.

**Contract**: Treść obejmuje:

- **Reguła:** podniesienie raz przy wejściu w tryb zmian (`startChanging`) dla wierszy wydatku w planie (`in_plan`), niezarchiwizowanych, z polem kwoty i `actual > Current Month Plan`; wpisana kwota = actual (o ile ≤ limitu 1e12). Zarchiwizowana pozycja nie jest podnoszona automatycznie (Dashboard ignoruje zarchiwizowane kategorie, więc podniesienie nic by nie dało), ale może dostać przycisk.
- **Przycisk:** „Ustaw plan na wydatki” w każdym wierszu wydatku z polem kwoty (także kandydat do dołączenia), gdy kwota w polu jest czytelna i actual > kwota w polu; wpisuje actual; tylko w górę, więc przy actual ≤ kwocie (w tym ujemnym) go nie ma; nie ma go też przy actual powyżej limitu 1e12, bo pole nie przyjmie takiej kwoty.
- **Ręczna decyzja wygrywa:** żaden mechanizm nie cofa obniżenia przed zapisem.
- **Zapis:** tylko Bieżący plan po „Zapisz zmiany”; „Anuluj” zrzuca podniesienia.
- **Zakres:** tylko tryb zmian planu zatwierdzonego; nie propozycja, nie dochody, nie odkładanie.
- **Znacznik:** ikona `Equal` w `muted-foreground` z tooltipem i `aria-label` „Plan podniesiony do wydatków”, gdy wiersz wydatku ma kwotę w polu równą actual i actual > zapisany plan (po automacie, po kliknięciu i po ręcznym wpisaniu tej samej kwoty); bez nowego znaczenia koloru, ton niesie strzałka z ADR-0060.
- **Miejsce:** przycisk i znacznik (nigdy razem) w komórce „Faktycznie” przed kwotą, poniżej `xl` w linii podpisu, jak strzałka w „Zatwierdzony”.
- **Skutki:** To Spend bez zmian; odchylenie 0, brak „ponad plan” i plakietki; tempo maleje; po zapisie brak powiadomienia `month_item_over_plan` dla pozycji i odchylenie 0 w Podsumowaniu miesiąca; ślad wzrostu zostaje jako strzałka w górę względem Zatwierdzonego. ADR-0033 dalej obowiązuje: „ponad plan” to fakt względem Bieżącego planu, a ten ADR opisuje świadome podniesienie Bieżącego planu.
- **Odrzucone:**
  - podnoszenie przy każdej edycji lub przy blur (uniemożliwia obniżenie);
  - warstwa „efektywnego planu” w `parsedAmount` (pole pokazuje co innego niż to, co się liczy);
  - automat dla kandydatów (cicho dołącza kategorie);
  - przycisk w obie strony (obniża plan Running w środku miesiąca);
  - działanie w propozycji (zmienia regułę propozycji z ADR-0033);
  - „ponad plan” względem Zatwierdzonego i zapis śladu podniesienia (zmiany serwera bez potrzeby);
  - kolor `accent` (ADR-0056 ogranicza go do Alert/Badge `ok`);
  - miejsce w komórce „Plan” lub szersza kolumna (ściska pole, ADR-0060; zabiera miejsce kategorii, ADR-0061).
- Nie dodaje propsa Inertii ani tokenu.

### Success Criteria:

#### Automated Verification:

- Plik ADR-0062 istnieje, a glosariusz odwołuje się do niego w obu wpisach: `ls context/foundation/adr/0062-*.md && grep -c "ADR-0062" context/foundation/glossary.md` (≥ 2)

#### Manual Verification:

- Użytkownik akceptuje brzmienie wpisów w glosariuszu i ADR-0062 (reguła, zakres, skutki, odrzucone alternatywy)

**Implementation Note**: Po tej fazie zatrzymaj się na potwierdzenie brzmienia przez użytkownika. Commit: `docs(month-plan-match-actual): glosariusz i ADR-0062 o planie podniesionym do wydatków`.

---

## Phase 2: Reguła w lib i podłączenie w stronie

### Overview

Czysta funkcja z testami i jej użycie w `MonthPlan.vue`: po tej fazie podniesienie działa w polach i podglądach, a `rowState` niesie stan przycisku i znacznika (jeszcze nierenderowany).

### Changes Required:

#### 1. Reguła „plan = wydatki”

**File**: `resources/js/lib/month-plan-match-actual.ts` (nowy), `resources/js/lib/month-plan-match-actual.test.ts` (nowy)

**Intent**: Jedno miejsce reguły z ADR-0062, testowane na literałach, z którego korzysta strona i kitchen sink.

**Contract**:

```ts
export type MatchActualState = 'offer' | 'raised' | null;

/** Niezarchiwizowany wiersz wydatku w planie, którego wydatki przekraczają zapisany plan (i mieszczą się w limicie). */
export function raisesToActualOnChange(row: MonthPlanItem): boolean;

/** Kwoty startowe trybu zmian: jak dotąd z plan_minor_units, z wyjątkiem wierszy z raisesToActualOnChange → actual. */
export function changeModeAmountInputs(rows: MonthPlanItem[]): Record<number, string>;

/** 'offer': wydatek z polem, kwota w polu czytelna, actual > kwota i actual ≤ MAX_PLAN_AMOUNT_MINOR_UNITS (większej kwoty pole nie przyjmie); 'raised': wydatek, kwota w polu === actual i actual > plan_minor_units; inaczej null. */
export function matchActualState(row: MonthPlanItem, fieldAmount: number | null, hasAmountField: boolean): MatchActualState;
```

`rows` dla `changeModeAmountInputs` to wiersze z polem kwoty (strona przekazuje `planRows`). Kwota w polu powstaje z `minorUnitsToMoneyInput`. Testy na literałach (wzór `plan-change-direction.test.ts`):

- podniesienie wydatku w planie z `actual > plan`;
- brak podniesienia przy `actual === plan`, przy dochodzie, przy kandydacie (`in_plan: false`) i przy zarchiwizowanej pozycji w planie (`is_archived`, `actual > plan`); `offer` dla niej zostaje;
- brak podniesienia przy `actual > MAX_PLAN_AMOUNT_MINOR_UNITS`;
- `offer` po ręcznym obniżeniu i dla kandydata z `actual > 0`;
- brak `offer` przy kwocie w polu nieczytelnej (`null`), przy `actual ≤` kwoty (także ujemnym), przy `actual > MAX_PLAN_AMOUNT_MINOR_UNITS`, dla dochodu i dla wiersza bez pola;
- `raised` po podniesieniu i po wpisaniu tej samej kwoty;
- brak `raised` przy `actual ≤ plan_minor_units`.

#### 2. Strona Planu miesiąca

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Wejście w tryb zmian korzysta z kwot podniesionych; strona ma funkcję obsługi przycisku i przekazuje stan do wiersza.

**Contract**:

- `startChanging()` ustawia `amountInputs` z `changeModeAmountInputs(planRows.value)`. Przy tym `isChanging` jest już `true`, więc `planRows` obejmuje kandydatów; funkcja ich nie podnosi. `stopChanging()` i obserwatory zostają przy `initialAmountInputs()`.
- Nowa funkcja `matchActual(categoryId: number)` wpisuje `minorUnitsToMoneyInput(row.actual_minor_units)` do `amountInputs` dla wiersza o tym id, tylko gdy `rowState(row).matchActual === 'offer'`.
- `rowState()` zwraca nowe pole `matchActual: MatchActualState`: `matchActualState(row, parsedAmount(row), isEditableRow(row))`, gdy `isChanging`; poza trybem zmian `null`.
- `MonthPlanRowState` w `MonthPlanTable.vue` zyskuje pole `matchActual: MatchActualState`, a `stateOf()` w `pages/dev/MonthPlanSink.vue` zwraca na razie `null`, żeby typy się zgadzały (stany sinka w fazie 3).

### Success Criteria:

#### Automated Verification:

- Testy reguły przechodzą: `npm test -- resources/js/lib/month-plan-match-actual.test.ts`
- Cały zestaw JS przechodzi: `npm test`
- Typy przechodzą: `npm run types:check`
- Lint i format: `npm run check`

#### Manual Verification:

- W przeglądarce po „Zmień” wiersz wydatku z wydatkami ponad plan ma w polu kwotę wydatków, strzałka w „Zatwierdzony” i odchylenie reagują, „Zapisz zmiany” jest aktywne; „Anuluj” przywraca zapisany plan

**Implementation Note**: Po tej fazie zatrzymaj się na potwierdzenie sprawdzenia w przeglądarce. Commit: `feat(month-plan-match-actual): podniesienie planu do wydatków przy wejściu w tryb zmian (p2)`.

---

## Phase 3: Przycisk i znacznik w komórce „Faktycznie”

### Overview

Renderuje stan `matchActual` w wierszu: przycisk „Ustaw plan na wydatki” albo znacznik, z tekstami, klasą w `app.css`, listami stróżującymi, kitchen sinkiem i wpisem w design systemie.

### Changes Required:

#### 1. Komponent przycisku i znacznika

**File**: `resources/js/components/MonthPlanMatchActual.vue` (nowy)

**Intent**: Jedno miejsce na przycisk albo znacznik, we wzorcu `PlanChangeArrow.vue` (Tooltip + goły `<button type="button">` z ikoną `size-3.5`, `aria-label` = tekst tooltipu).

**Contract**:

- Props: `state: 'offer' | 'raised'`, `offerLabel: string`, `raisedLabel: string`.
- Emit: `match` (tylko z `offer`).
- `offer`: przycisk z ikoną `ArrowLeftToLine` (plan stoi po lewej od „Faktycznie” na każdej szerokości), ton `muted-foreground`, przy najechaniu i fokusie `foreground`, fokus `ring` jak strzałka.
- `raised`: ikona `Equal` w `muted-foreground` w elemencie z fokusem tylko dla tooltipu (jak strzałka), bez akcji.
- Klasa pola trafienia w `app.css` (patrz Critical Implementation Details).

#### 2. Wiersz i tabela

**File**: `resources/js/components/MonthPlanItemRow.vue`, `resources/js/components/MonthPlanTable.vue`

**Intent**: Wiersz pokazuje komponent przed kwotą w „Faktycznie” i przekazuje kliknięcie w górę; tabela przekazuje stan i emit we wszystkich trzech grupach wierszy.

**Contract**:

- `MonthPlanItemRow`: nowy prop `matchActual: MatchActualState` (domyślnie `null`) i emit `match-actual`. Komórka `.month-plan-cell-actual` układa się jak `.month-plan-cell-approved`: podpis, komponent, kwota. Po emisji wiersz przenosi fokus na swoje pole planu (`ref` na `<input>`, `nextTick`).
- `MonthPlanTable`: `:match-actual="rowState(row).matchActual"` i emit `'match-actual': [categoryId: number]` w grupach dochodów, wydatków i jednorazowych (`:170-182`, `:223-235`, `:267-279`).
- `MonthPlan.vue`: `@match-actual="matchActual"` na `MonthPlanTable`.

#### 3. Style

**File**: `resources/css/app.css`

**Intent**: Komórka „Faktycznie” jako flex jak „Zatwierdzony” oraz nazwana klasa przycisku.

**Contract**: `.month-plan-cell-actual` dostaje układ z `.month-plan-cell-approved` (`flex min-w-0 flex-wrap items-baseline justify-end gap-x-1.5 text-right`, kwota `w-full xl:w-auto`, podpis `xl:sr-only`). Nowa klasa, np. `.month-plan-match-actual`, w `@layer components` daje pole trafienia ≥ 24 px. Bez wartości dowolnych w szablonie.

#### 4. Teksty

**File**: `lang/pl/ui.php`

**Intent**: Dostępne nazwy przycisku i znacznika.

**Contract**: W tablicy `month_plan`: `'match_actual' => 'Ustaw plan na wydatki'` i `'raised_to_actual' => 'Plan podniesiony do wydatków'`. Typ `month_plan: Record<string, string>` nie wymaga zmiany.

#### 5. Listy stróżujące

**File**: `tests/Unit/MonthPlanHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`

**Intent**: Nowy komponent Planu miesiąca jest pilnowany jak pozostałe (`.ai/rules/js-month-plan.md`).

**Contract**: `resources/js/components/MonthPlanMatchActual.vue` dopisany do obu list obok `PlanChangeArrow.vue`.

#### 6. Kitchen sink

**File**: `resources/js/pages/dev/MonthPlanSink.vue`, ewentualnie `resources/js/lib/month-plan-fixtures.ts`

**Intent**: Nowe stany widoczne w `/dev/month-plan`.

**Contract**:

- `stateOf()` liczy `matchActual` przez `matchActualState` z `config.amountInputs`, gdy `config.editable`. Dziś `stateOf()` bierze `deviation`, `overPlan` i `status` z wiersza serwera (`row.deviation_minor_units`, `row.is_over_plan`, `row.plan_minor_units`), więc podniesiony wiersz pokazałby plakietkę „ponad plan” i stare odchylenie. Gdy `config.editable`, `stateOf()` liczy je z kwoty w polu tak jak `rowReading`/`rowState` na stronie (`monthItemDeviationMinorUnits`, `isMonthItemOverPlan`, `monthItemStatus` z `planMinorUnits` z pola); bez `editable` zostaje odczyt z serwera.
- Nowy `tableConfig` „Tryb zmian: plan = wydatki” na `approvedReading` z `amountInputs` z `changeModeAmountInputs(...)`. Pokazuje:
  - `overPlanExpense` podniesiony ze znacznikiem;
  - kandydata `outsidePlanExpense` z przyciskiem;
  - wiersz z ręcznie obniżoną kwotą z przyciskiem (nadpisanie `amountInputs`);
  - wiersze bez kontrolki (dochód, `actual ≤` planu).
- Kliknięcie przycisku w sinku wpisuje kwotę do `config.amountInputs`.
- Nowy wiersz w `stateMatrix`.

#### 7. Design system

**File**: `context/foundation/design-system.md`

**Intent**: Lista „Gotowe elementy” (`:92`) wymienia nowy komponent.

**Contract**: Wpis `MonthPlanMatchActual` obok `PlanChangeArrow`: przycisk „Ustaw plan na wydatki” (`ArrowLeftToLine`) albo znacznik „Plan podniesiony do wydatków” (`Equal`) w `muted-foreground` w komórce „Faktycznie” Planu miesiąca w trybie zmian, z podpowiedzią i `aria-label`; ADR-0062. Bez zmian w tabeli kolorów.

#### 8. Reguła ścieżkowa

**File**: `.ai/rules/js-month-plan.md`

**Intent**: Następny agent edytujący Plan miesiąca zna nowy komponent i jego konwencje.

**Contract**: Akapit w regule: komórka „Faktycznie” (`.month-plan-cell-actual`) układa się jak „Zatwierdzony”; `MonthPlanMatchActual` (przycisk albo znacznik, nigdy razem) stoi przed kwotą, poniżej `xl` w linii podpisu; pole trafienia z nazwanej klasy w `app.css`; po kliknięciu wiersz przenosi fokus na pole planu; stan pochodzi z `matchActualState` (`lib/month-plan-match-actual.ts`), nie ze stanu strony; ADR-0062. Po edycji `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- Bramka przechodzi: `composer ci:check` (przez Sail), w tym `npm run check:ui`, `npm run types:check`, `npm test` i `MonthPlanHardcodedUiTest`
- Sloppy przechodzi: `vendor/bin/sloppy ci`
- Pint po zmianie PHP: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- Kitchen sink `/dev/month-plan` pokazuje stany: podniesiony ze znacznikiem, kandydat z przyciskiem, ręcznie obniżony z przyciskiem, bez kontrolki
- W przeglądarce przy 390 px i przy `xl` przycisk i znacznik mieszczą się w komórce „Faktycznie” bez podnoszenia wiersza i bez ściskania kwoty; tooltipy i `aria-label` są poprawne
- Klawiatura: Tab dochodzi do przycisku, Enter wpisuje wydatki, fokus przechodzi na pole planu, a w miejscu przycisku jest znacznik
- Zapis zmian z podniesionym wierszem zmienia Bieżący plan (Zatwierdzony bez zmian); po zapisie wiersz nie ma plakietki „ponad plan”, a strzałka w „Zatwierdzony” pokazuje wzrost

**Implementation Note**: Po tej fazie zatrzymaj się na potwierdzenie sprawdzenia ręcznego. Commit: `feat(month-plan-match-actual): przycisk i znacznik „plan = wydatki” w wierszu planu (p3)`.

---

## Testing Strategy

### Unit Tests:

- `month-plan-match-actual.test.ts`: `raisesToActualOnChange`, `changeModeAmountInputs`, `matchActualState`. Przypadki brzegowe:
  - `actual === plan`;
  - plan 0 z wydatkami (One-off, kandydat);
  - wydatki ujemne;
  - kwota nieczytelna;
  - dochód;
  - wiersz bez pola;
  - `actual > MAX`;
  - ręczne wpisanie kwoty równej wydatkom.
- Bez nowych testów PHP: serwer i propsy się nie zmieniają (`MonthPlanPageTest`, `ChangeMonthPlanTest` zostają zielone w bramce).

### Integration Tests:

- Brak nowego E2E (patrz „What We're NOT Doing”); listy stróżujące pilnują nowego komponentu.

### Manual Testing Steps:

1. Bieżący miesiąc z zatwierdzonym planem i pozycją wydatku z wydatkami ponad plan → „Zmień” → pole = wydatki, znacznik `Equal`, odchylenie 0, strzałka w górę, „Zapisz zmiany” aktywne.
2. Obniż pole poniżej wydatków → pojawia się przycisk; kliknij → pole = wydatki, fokus na polu, znacznik.
3. Kandydat do dołączenia z wydatkami → przycisk bez automatu; kliknij → po zapisie kategoria dołącza z zatwierdzonym 0.
4. „Anuluj” → zapisane kwoty wracają, brak znaczników.
5. Zmień rodzaj pozycji (Bill/Running) w trybie zmian → podniesienia zostają w polach.
6. Po zapisie Dashboard nie pokazuje „pozycja ponad plan” dla podniesionej pozycji.

## Performance Considerations

`matchActualState` jest wołana w `rowState` przy renderze dla każdego wiersza, jak dotychczasowe odczyty: stała praca na wiersz, bez wpływu na wydajność.

## Migration Notes

Brak: bez zmian schematu i danych. Wycofanie = revert commitów fazy 2 i 3; glosariusz i ADR wymagają wtedy wpisu „Superseded”.

## References

- Research: `context/changes/month-plan-match-actual/research.md`
- Odłożenie funkcji: `context/archive/2026-11-07-ui-fixes-2/plan.md:67`
- Wzorzec strzałki: `resources/js/components/PlanChangeArrow.vue`, ADR-0060
- Sygnał „ponad plan”: ADR-0033, `app/Support/DashboardNotices.php:122-160`, `glossary.md` („To Spend”)
- Stan trybu zmian: `resources/js/pages/MonthPlan.vue:79-195,401-461`
- Reguły: `.ai/rules/js-month-plan.md`, `.ai/rules/js.md`, `.ai/rules/month-calculators.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Glosariusz i ADR-0062

#### Automated

- [x] 1.1 Plik ADR-0062 istnieje, a glosariusz odwołuje się do niego w obu wpisach — 38b0784

#### Manual

- [x] 1.2 Użytkownik akceptuje brzmienie wpisów w glosariuszu i ADR-0062 — 38b0784

### Phase 2: Reguła w lib i podłączenie w stronie

#### Automated

- [ ] 2.1 Testy reguły przechodzą
- [ ] 2.2 Cały zestaw JS przechodzi
- [ ] 2.3 Typy przechodzą
- [ ] 2.4 Lint i format

#### Manual

- [ ] 2.5 Po „Zmień” wiersz z wydatkami ponad plan ma w polu kwotę wydatków, podglądy reagują, „Anuluj” przywraca plan

### Phase 3: Przycisk i znacznik w komórce „Faktycznie”

#### Automated

- [ ] 3.1 Bramka composer ci:check przechodzi
- [ ] 3.2 Sloppy przechodzi
- [ ] 3.3 Pint po zmianie PHP

#### Manual

- [ ] 3.4 Kitchen sink pokazuje nowe stany
- [ ] 3.5 Przycisk i znacznik mieszczą się przy 390 px i przy xl, tooltipy i aria-label poprawne
- [ ] 3.6 Klawiatura: przycisk, wpisanie wydatków, fokus na polu, znacznik
- [ ] 3.7 Zapis zmienia Bieżący plan, po zapisie brak „ponad plan” i strzałka w górę
