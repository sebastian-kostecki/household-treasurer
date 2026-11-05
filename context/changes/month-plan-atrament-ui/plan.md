# Plan miesiąca w systemie „C · Atrament i morski” — plan implementacji

## Overview

Widok `/month-plan` (`resources/js/pages/MonthPlan.vue` i komponenty z `tests/Unit/MonthPlanHardcodedUiTest.php`) przechodzi na system „C · Atrament i morski” według mockupu `MockupCAtrament` → Plan miesiąca (artefakt https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, wersja 1793905230-035b; wzorzec, nie źródło wartości). Zmiana zamyka zarzuty C1–C7 z `research.md`. Dochodzą: `StatPanel` z czterema miarami (Do wydania, jego składniki Dochody i Wydatki, Tempo), notatka tempa, jedna tabela z kolumną Status i wierszem „Odkładam / dobieram”, pas edycji `primary` z przyciskiem `brand`, kropki stanu miesięcy, przebudowane podsumowanie minionego miesiąca i kitchen sink z macierzą 7 stanów. Decyzje zapisujemy w ADR-0042 przed kodem.

## Current State Analysis

Pełne ustalenia z kotwicami są w `context/changes/month-plan-atrament-ui/research.md` (zarzuty C1–C7, decyzje D1–D6). Uzupełnienia z planowania:

- Skan literałów kolorów daje 0 trafień, ale widok ma ok. 50 stylów sprzed Atramentu (`text-3xl/lg`, `uppercase tracking-wider`, `rounded-2xl/xl`, `border-border/60`, `opacity-60`, `.section-label`), których nie łapie ani `MonthPlanHardcodedUiTest`, ani `scripts/check-ui-literals.mjs` (ten drugi nie obejmuje plików Planu miesiąca poza `BudgetMonthPicker`, `SegmentedControl`, `StatPanel`, `InputError`).
- `ToSpendCalculator::itemMinorUnits()` (`app/Support/ToSpendCalculator.php:46-58`) już liczy kwotę pozycji według reguły To Spend. TS kopia (`resources/js/lib/to-spend-calculator.ts:16-39`) zwraca tylko wynik netto. `CONTEXT.md:80` definiuje To Spend jako „dochody − rachunki − bieżące − odkładanie”, więc pokazanie składników nie zmienia reguły.
- `MonthToSpend` (`app/Support/MonthToSpend.php:55-75`) jest jedynym miejscem, które składa wiersze do kalkulatora. Kontroler (`app/Http/Controllers/MonthPlanController.php:157-224`) ma w `monthPlanPayload()` obiekt `$toSpend->proposal`, którego `reading($m)->state` (`app/Support/MonthPlanProposal.php:62-81`, memo) daje stan każdego miesiąca bez nowych zapytań.
- `MonthPaceMeter` ma jednego konsumenta (`MonthPlan.vue:592`). `BudgetMonthPicker` też (`MonthPlan.vue:539`).
- `ui/button/index.ts` nie ma wariantu `brand`. `ui/alert/index.ts` ma `default`, `destructive`, `warning` (karta + `text-warning`), bez tła `warning-soft`. Token `--warning-soft` istnieje w obu motywach (`app.css:139,202`). Badge ma `ok`, `brand`, `neutral`, `warning`, `destructive-soft`.
- Klasa z `@layer components` przegrywa z utility komponentu `ui/Input`, więc komórka planu z mockupu (`ht-cell-input`, 28 px, mono 12 px do prawej) nie może być klasą dopisaną do `<Input>`.
- Klucze „Kwoty w zł” (`budget.amounts_in_zl`) i „Końcowy” (`month_plan.summary_final`) istnieją. Brakuje m.in. „miesiąc jeszcze się nie zaczął”, „czeka”, „do zapłaty”, „ponad plan” i podpisów miar. Jest tylko `lang/pl`.
- Kitchen sink `/dev/month-plan` (`routes/web.php:46`, `pages/dev/MonthPlanSink.vue`) renderuje wycinki: wiersze, pasek edycji, stany puste. Strona `MonthPlan.vue` (887 linii) trzyma tabelę i kartę Do wydania w szablonie, więc sink nie może ich pokazać bez wydzielenia komponentów.
- ADR: najwyższy 0041, 0036 zarezerwowany, następny wolny 0042. ADR-0035:3 mówi „`brand` … never status”; `Budget.vue` używa Badge `brand` dla fazy „Realizacja” (stan dokumentu).
- `context/foundation/roadmap.md` nie ma pozycji z tym Change ID.

## Desired End State

- `/month-plan` dla bieżącego miesiąca: tytuł `page-title` „Plan miesiąca · {miesiąc} {rok}” z odznaką stanu: `brand` „Zatwierdzony”, `warning` „Niezatwierdzony”, `outline` „Końcowy” dla minionego zatwierdzonego. Pod tytułem jedno zdanie stanu w `muted-foreground`. Po prawej najwyżej jedna akcja: `brand` „Zatwierdź” (propozycja, z licznikiem błędnych kwot obok) albo zarysowany „Zmień plan” z ikoną.
- Pasek miesięcy: panel `card` z `border` i `rounded-lg`; każdy miesiąc ma kropkę stanu (`muted-foreground` podsumowanie, `brand-text` zatwierdzony, `warning` propozycja, brak kropki bez planu), a nazwa dostępna mówi o stanie.
- `StatPanel`: „Do wydania” (w edycji „Do wydania · podgląd na żywo”, w minionym „Do wydania (końcowe)”, podpis „dochody − wydatki − odkładanie”, `destructive` poniżej zera), „Dochody” (`success`), „Wydatki” (podpis „rachunki wg faktu, bieżące wg wyższej”), „Tempo” („31%” i podpis „Upływ dni 5 z 30 (17%)”; w przyszłym miesiącu „—” i „miesiąc jeszcze się nie zaczął”; bez planu bieżących „—” i `pace_none`). Pod panelem notatka Alert `note`, gdy tempo wyprzedza upływ dni. Paska tempa nie ma.
- Jedna tabela (panel `card`): nagłówek kolumn `table-header` „Kategoria | Rodzaj | Plan | Faktycznie | Odchylenie | Status”, wiersze grup `muted` „Dochody”, „Wydatki”, „Odkładam / dobieram”. Kwoty JetBrains Mono bez „zł” (`amount`), stopka „Kwoty w zł”, linie `border-soft`, wiersze na `--row-height`. Status: „Wpłynęło” `ok` / „czeka” `neutral` (dochód), „Zapłacone” `ok` / „do zapłaty” `neutral` (rachunek), „ponad plan” `destructive-soft` (każdy wydatek ponad plan; wygrywa z „Zapłacone”), bieżąca w planie bez plakietki. „Jednorazowa”, „Zarchiwizowane”, „Poza planem” jako `neutral` przy nazwie. Wiersz odkładania: w edycji kompaktowy `SegmentedControl` kierunku i komórka kwoty, w odczycie kwota ze znakiem; w kolumnie Status meta „zmniejsza / zwiększa Do wydania”. Pod tabelą notatka `note` o dobieraniu ponad płynność dysponowalną. Wiersz „Razem w roku” zamyka grupę Dochody i Wydatki; nieaktualna wartość jest w `muted-foreground` z podpowiedzią, bez przezroczystości.
- Komórka planu w edycji: `cell-input` (28 px, mono, do prawej, `radius-sm`, focus `outline` 2 px `ring`, błąd = ramka `destructive` + `InputError` pod spodem powiązany `aria-describedby`).
- Tryb zmiany: pas `primary` przyklejony u dołu ekranu z „Edycja planu”, zdaniem skutku, licznikiem błędów i błędem serwera, „Anuluj” `ghost` i `brand` „Zapisz zmiany” (spinner, wyłączony bez zmian lub przy błędach). W trybie propozycji pasa nie ma.
- Miniony zatwierdzony miesiąc: ten sam układ do odczytu: StatPanel z wartościami końcowymi, panel „Podsumowanie miesiąca” (siatka Zatwierdzony | Końcowy | Faktycznie | Odchylenie, `amount`, „Kwoty w zł”) i tabela pozycji.
- `/dev/month-plan` pokazuje każdy region widoku w macierzy 7 stanów albo „N/A” z powodem.
- `MonthPaceMeter.vue` i `.section-label` nie istnieją. `MonthPlanHardcodedUiTest` odrzuca style sprzed Atramentu, a `check-ui-literals.mjs` obejmuje pliki widoku.
- ADR-0042, `.ai/rules/js.md` i `.docs/design-system.md` opisują nowe zasady.
- Weryfikacja: `composer ci:check` (obraz Sail), testy Planu miesiąca na PostgreSQL, `vendor/bin/sloppy ci`, `npm run check`, `npm run check:ui`, `npm run types:check`, `npm test` zielone; zrzuty w obu motywach przy 1280 i 390 px w `context/changes/month-plan-atrament-ui/screenshots/`.

### Key Discoveries:

- Precedens całej zmiany: `context/archive/2026-11-05-budget-atrament-ui/plan.md` (kolejność faz, ADR przed kodem, kitchen sink, zrzuty z podglądu worktree).
- Precedens sinka: `resources/js/pages/dev/BudgetKitchenSink.vue:215-271` (`stateMatrix` `{state, where}`, lista na górze, sekcje `h2.section-title` z `aria-labelledby`, prawdziwe komponenty).
- Precedens tabeli z jednostką w stopce: `BudgetCategoryList.vue:197-214,372-374`, `BudgetAmountCell.vue:34-40`.
- Konwencja ADR: angielski, `# Tytuł` + 1–3 akapity z „We rejected …”, częściowe zastąpienie jako „Superseded in part by ADR-NNNN” w starym i „This supersedes in part …” w nowym (`.docs/adr/0037-*.md`).
- Testy strony zamrażają datę przez `$this->travelTo(...)` (`tests/Feature/MonthPlanPageTest.php:26`).
- Para To Spend (AGENTS.md „Month logic lives twice”): zmiana `ToSpendCalculator.php` i `to-spend-calculator.ts` razem z `tests/Unit/ToSpendCalculatorTest.php` i `to-spend-calculator.test.ts`; konsumenci TS: `entry-to-spend-warning.ts`, `import-to-spend-warnings.ts`.

## What We're NOT Doing

- „Dodaj operację” w wierszu tytułu (dialogi operacji i skrót `N` to osobna zmiana).
- Statusu „N% planu” dla pozycji bieżących (nowa liczba w parze logiki); bieżąca w planie nie ma plakietki.
- Zmiany reguły To Spend, progu tempa ani innej logiki domenowej; rozbicie To Spend to tylko zwrócenie składników, które reguła już liczy.
- Osobnego komponentu `Notice` obok `Alert`.
- Przenoszenia „Zatwierdź” lub pasa edycji na górę strony (mockup); pas jest u dołu.
- Zmiany innych widoków poza tym, co dziedziczą z nowych wariantów (Button `brand`, Alert `note` są addytywne).
- Testu pozytywnego renderu kitchen sinka w `local` (istniejąca luka, jak w Budżecie).
- Klucza tłumaczeń w innych językach (repo ma tylko `lang/pl`).

## Implementation Approach

Faza 1 zapisuje decyzje, bo ADR-0035 zabrania `brand` dla statusu, a `.ai/rules/js.md` każe używać `.section-label` i `Alert warning`. Kod sprzeczny z tymi regułami bez ADR cofnąłby kolejny agent. Faza 2 dodaje addytywne warianty i klasy bez konsumentów, więc nic się wizualnie nie zmienia. Faza 3 zmienia logikę i kontrakt propsów po obu stronach w jednym commicie (AGENTS.md „Inertia props contract”, „Month logic lives twice”); UI jeszcze ich nie czyta poza typami. Faza 4 przebudowuje widok na tych podstawach i wydziela `MonthPlanStatPanel` i `MonthPlanTable`, żeby sink mógł je renderować. Faza 5 buduje macierz stanów i bramkę wizualną. Między fazami 3 a 5 gałąź działa; nie scalamy do `development` przed Fazą 5.

## Critical Implementation Details

**Formularz obejmuje wiersz tytułu.** „Zatwierdź” stoi w wierszu tytułu, a potrzebuje `processing` i `errors` ze slotu `<Form>`. `<Form>` z `MonthPlan.vue:609` ma więc obejmować cały widok miesiąca (wiersz tytułu, pasek miesięcy, miary, tabelę, pas), a przyciski paska miesięcy mają mieć `type="button"`, żeby nie wysyłały formularza. Ukryte pola `items[...]` i `set_aside_minor_units` zostają w stronie.

**Brak mutacji propsów w wydzielonych komponentach.** Stan edycji (`amountInputs`, `touchedAmounts`, kierunek i kwota odkładania) zostaje w `MonthPlan.vue`. `MonthPlanTable` dostaje odczyty przez propsy i oddaje zmiany zdarzeniami albo `defineModel`; nie zapisuje do obiektu przekazanego w propsie.

## Faza 1: Decyzje i dokumenty

### Overview

ADR-0042 i zgodne z nim reguły oraz dokumenty, zanim zmieni się kod.

### Changes Required:

#### 1. ADR-0042

**File**: `.docs/adr/0042-month-plan-layout-brand-approval-and-note-alert.md`

**Intent**: Zapisać decyzje tej zmiany wraz z odrzuconymi opcjami, żeby reguły `brand` i ostrzeżeń miały źródło.

**Contract**: Treść po angielsku, format jak ADR-0037. Musi zawierać:
- (a) `brand` oznacza też stan dokumentu i akcję, która go zatwierdza. Badge `brand` dla „Zatwierdzony” (jak faza „Realizacja” Budżetu), wariant Button `brand` tylko dla akcji zatwierdzającej lub zapisującej plan („Zatwierdź”, „Zapisz zmiany”), najwyżej jeden na widok, nigdy dla statusu pieniędzy. Częściowo zastępuje ADR-0035 („never status” oznacza status pieniędzy).
- (b) Wariant Alert `note`: tło `warning-soft`, ikona `warning`, tekst `foreground`, bez tytułu. Służy do jednozdaniowej notatki o odczycie pokazanym na tej samej stronie, pod panelem miar albo pod tabelą (tempo, dobieranie). Alert `warning` zostaje dla pozostałych ostrzeżeń nieblokujących.
- (c) Układ Planu miesiąca: StatPanel z Do wydania, jego składnikami (dochody i wydatki według reguły To Spend, `CONTEXT.md:80`, reguła bez zmian) i Tempem, bez paska tempa; jedna tabela z wierszami grup i kolumną Status (bez „N% planu”); wiersz odkładania z przełącznikiem kierunku; pas edycji `primary` przyklejony u dołu tylko w trybie zmiany, „Zatwierdź” w wierszu tytułu; miniony miesiąc w pełnym widoku do odczytu z odznaką „Końcowy”; kropki stanu miesięcy.
- „We rejected”: sum planu jako miar Dochody/Wydatki (równanie z podpisu przestaje się zgadzać), pasa u góry jak w mockupie (zapis poza zasięgiem przy długiej tabeli), osobnego komponentu Notice (dwa komponenty ostrzeżeń), statusu „N% planu” (nowa liczba w parze logiki), minionego miesiąca z samym podsumowaniem (utrata widoku kategorii) i „Dodaj operację” w tym widoku (osobna zmiana).

#### 2. Adnotacja ADR-0035

**File**: `.docs/adr/0035-atrament-tokens-inter-jetbrains-mono.md`

**Intent**: Wzajemny odsyłacz częściowego zastąpienia.

**Contract**: Linia „Superseded in part by ADR-0042 (`brand` for document state and the approval action).” ADR-0042 ma zdanie „This supersedes in part ADR-0035 …”.

#### 3. Reguły agenta

**File**: `.ai/rules/js.md`

**Intent**: Reguły mówią to samo co ADR-0042 i nie odsyłają do `.section-label`.

**Contract**:
- `:11`: `brand` = tożsamość, nawigacja, stan dokumentu i jedna akcja zatwierdzająca (ADR-0042), nigdy status pieniędzy.
- `:22-24`: Alert `note` dla notatki pod panelem miar lub tabelą; `warning` dla pozostałych.
- `:42`: Badge `brand` dla stanu dokumentu.
- `:56`: z listy klas znika `.section-label`, dochodzą `.cell-input`, `.month-plan-editbar`, `.month-strip-dot*`, `.month-plan-summary-grid`.
- `:58`: jedna tabela z kolumną Status, komórka planu `cell-input` (nie `ui/Input`), opis sinka zostaje do Fazy 5.

#### 4. Design system

**File**: `.docs/design-system.md`

**Intent**: Dokument opisuje nowe warianty i to, że Plan miesiąca jest przeniesiony.

**Contract**:
- `:19`: bez `.section-label` (i bez `.list-surface-table`, jeśli nadal nie ma konsumentów).
- `:25`: Plan miesiąca dopisany do widoków wdrożonych w mono.
- `:42` (`brand`): dozwolony stan dokumentu i akcja zatwierdzenia (ADR-0042).
- „Gotowe elementy”: Button `brand`, Alert `note`, `.cell-input`.
- Lista ADR, jeśli dokument ją prowadzi.

### Success Criteria:

#### Automated Verification:

- ADR-0042 istnieje, a ADR-0035 do niego odsyła: `grep -l "ADR-0042" .docs/adr/0035-*`
- `grep -n "section-label" .ai/rules/js.md .docs/design-system.md` nic nie zwraca
- `npm run check` przechodzi

#### Manual Verification:

- Użytkownik akceptuje treść ADR-0042 i zmienione reguły `brand` i Alert

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Faza 2: Warstwa wspólna

### Overview

Addytywne warianty komponentów bazowych i nazwane klasy w `app.css`, jeszcze bez konsumentów w widoku.

### Changes Required:

#### 1. Wariant Button `brand`

**File**: `resources/js/components/ui/button/index.ts`

**Intent**: Akcja zatwierdzająca plan w kolorze marki (ADR-0042).

**Contract**: Nowy wariant `brand`: `bg-brand text-brand-foreground hover:bg-brand/90` plus `disabledSolid`, jak `default`. Pozostałe warianty bez zmian. Edycja `components/ui/*` jest tu zamierzona (wariant systemu, nie jednego ekranu).

#### 2. Wariant Alert `note`

**File**: `resources/js/components/ui/alert/index.ts`

**Intent**: Notatka z mockupu (`ht-note`) jako wariant istniejącego prymitywu.

**Contract**: Nowy wariant `note`: tło `bg-warning-soft`, `border-transparent`, tekst `text-foreground`, ikona `[&>svg]:text-warning`, opis w `foreground` (nie `muted`). Bez zmian w `default`, `destructive`, `warning`.

#### 3. Klasy w `app.css`

**File**: `resources/css/app.css` (`@layer components`)

**Intent**: Nazwane klasy dla elementów mockupu, których nie da się zbudować z istniejących stylów bez wartości arbitralnych.

**Contract**:
- `.cell-input`: komórka planu na zwykłym `<input>`. Wysokość 28 px (`h-7`), pełna szerokość kolumny, styl `amount` (mono, `tabular-nums`) w 12 px albo najbliższy nazwany odpowiednik, `text-right`, `px-2`, `rounded-sm`, `border` (`border` w kolorze `input`), tło `card`. Focus: `focus-visible:outline-2 outline-solid outline-ring outline-offset-2` jak Button. `aria-invalid:border-destructive`.
- `.month-plan-editbar`: pas `bg-primary text-primary-foreground rounded-lg`, `sticky bottom-0 z-10`, `flex flex-wrap items-center gap-3`, `px-4 py-2`. Przycisk `ghost` wewnątrz w `primary-foreground` z hoverem czytelnym na `primary`.
- `.month-strip-dot`: kropka 4 px pod etykietą miesiąca, `rounded-full`. Modyfikatory `-summary` (`bg-muted-foreground`), `-approved` (`bg-brand-text`), `-proposal` (`bg-warning`).
- `.month-plan-summary-grid`: siatka panelu podsumowania (etykieta + 4 kolumny kwot), na wąskim ekranie bez poziomego przewijania strony.

`.month-plan-grid` zmienia się dopiero w Fazie 4 razem z wierszami, które go używają.

### Success Criteria:

#### Automated Verification:

- `npm run build`, potem `npm run types:check`, `npm run check` i `npm run check:ui` przechodzą
- `npm test` przechodzi
- `composer ci:check` przechodzi w obrazie Sail

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Faza 3: Logika i kontrakt propsów

### Overview

Para To Spend zwraca składniki obok wyniku netto. Serwer wysyła je wraz ze stanem 12 miesięcy, a typy TS przyjmują nowe pola w tym samym commicie.

### Changes Required:

#### 1. Rozbicie To Spend w PHP

**File**: `app/Support/ToSpendCalculator.php`

**Intent**: Zwrócić dochody i wydatki, z których powstaje To Spend, bez zmiany reguły.

**Contract**: Nowa `public static function breakdown(array $items, int $setAsideMinorUnits): array{income_minor_units: int, expense_minor_units: int, to_spend_minor_units: int}`. `income_minor_units` = suma `itemMinorUnits` pozycji dochodowych, `expense_minor_units` = suma `itemMinorUnits` pozycji wydatkowych, `to_spend_minor_units` = dochody − wydatki − odkładanie. `toSpendMinorUnits()` zwraca `breakdown(...)['to_spend_minor_units']`. PHPDoc klasy wspomina rozbicie i jego kopię w TS.

#### 2. Rozbicie To Spend w TS

**File**: `resources/js/lib/to-spend-calculator.ts`

**Intent**: Ta sama liczba w podglądzie na żywo; konsumenci ostrzeżeń dostają ten sam wynik netto.

**Contract**: `export function toSpendBreakdown(items: ToSpendItem[], setAsideMinorUnits: number): { incomeMinorUnits: number; expenseMinorUnits: number; toSpendMinorUnits: number }`. Istniejąca `toSpendMinorUnits(items, setAside)` zwraca `toSpendBreakdown(...).toSpendMinorUnits`, z tą samą sygnaturą. Komentarz o parze obejmuje rozbicie.

#### 3. Testy pary

**File**: `tests/Unit/ToSpendCalculatorTest.php`, `resources/js/lib/to-spend-calculator.test.ts`

**Intent**: Obie kopie dają te same składniki.

**Contract**: W obu plikach te same przypadki:
- rozbicie przykładu kanonicznego (3730,00) z oczekiwanymi dochodami i wydatkami;
- rachunek tylko ze zwrotem liczy się w wydatkach wg planu;
- bieżąca wg większej z kwot;
- pusta lista daje trzy zera;
- tożsamość dochody − wydatki − odkładanie = wynik przy odkładaniu i przy dobieraniu.

Istniejące przypadki bez zmian. `entry-to-spend-warning.test.ts` i `import-to-spend-warnings.test.ts` przechodzą bez zmian.

#### 4. Odczyt miesiąca

**File**: `app/Support/MonthToSpend.php`

**Intent**: Jedno miejsce składa wiersze do kalkulatora także dla składników.

**Contract**: `toSpendBreakdown(): ?array` (null przy stanie `none`) buduje pozycje tak jak dziś i woła `ToSpendCalculator::breakdown`. `toSpendMinorUnits()` zwraca jego `to_spend_minor_units`. `summary()` bez zmian.

#### 5. Kontroler

**File**: `app/Http/Controllers/MonthPlanController.php`

**Intent**: Propsy składników i stanu miesięcy dla miar i paska.

**Contract**: W `monthPlanPayload()` nowe klucze:
- `to_spend_income_minor_units: int|null` i `to_spend_expense_minor_units: int|null` z `toSpendBreakdown()`, null przy stanie `none`.
- `month_states: list<'summary'|'approved'|'proposal'|null>` (12 pozycji, styczeń pierwszy), z `$toSpend->proposal->reading($m)->state` i `monthTiming($budget->year, $m, $today)`. Zatwierdzony i miniony → `summary`, zatwierdzony → `approved`, `proposal` → `proposal`, `none` → `null`.

Bez nowych zapytań, bo `MonthPlanProposal` trzyma memo. PHPDoc kształtu payloadu zaktualizowany.

#### 6. Typy i fikstury

**File**: `resources/js/types/month-plan.ts`, `resources/js/lib/month-plan-fixtures.ts`

**Intent**: Kontrakt propsów po stronie TS w tym samym commicie.

**Contract**: `export type MonthStripState = 'summary' | 'approved' | 'proposal'`. `MonthPlanReading` dostaje `to_spend_income_minor_units: number | null`, `to_spend_expense_minor_units: number | null`, `month_states: (MonthStripState | null)[]`. Fikstury uzupełnione, żeby `types:check` przechodził.

#### 7. Testy strony

**File**: `tests/Feature/MonthPlanPageTest.php`

**Intent**: Przypiąć nowy kontrakt przez `assertInertia`.

**Contract**: Nowe testy z zamrożoną datą:
- (a) bieżąca propozycja: `to_spend_income_minor_units` i `to_spend_expense_minor_units` mają oczekiwane wartości, a dochody − wydatki − `set_aside_minor_units` = `to_spend_minor_units`;
- (b) `month_states` dla budżetu z zatwierdzonym minionym miesiącem (`summary`), zatwierdzonym bieżącym (`approved`), przyszłymi (`proposal`) i minionym bez planu (`null`);
- (c) miesiąc w stanie `none` ma oba składniki `null`.

Istniejące testy bez zmian.

### Success Criteria:

#### Automated Verification:

- Testy pary i strony przechodzą na PostgreSQL w obrazie Sail: `… artisan test --compact tests/Unit/ToSpendCalculatorTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/MonthToSpendTest.php`
- `npm test` przechodzi (w tym `to-spend-calculator.test.ts`, `entry-to-spend-warning.test.ts`, `import-to-spend-warnings.test.ts`)
- `npm run build`, potem `npm run types:check` i `npm run check` przechodzą
- `composer ci:check` przechodzi w obrazie Sail
- `vendor/bin/sloppy ci` na hoście bez nowych znalezisk
- `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia

#### Manual Verification:

- `/month-plan` w podglądzie z worktree renderuje się jak przed zmianą (UI jeszcze nie czyta nowych pól)

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Faza 4: Widok Planu miesiąca

### Overview

Przebudowa `/month-plan` na nowych wariantach, klasach i propsach: wiersz tytułu, pasek miesięcy z kropkami, panel miar z notatką, jedna tabela z Statusem i odkładaniem, pas edycji, podsumowanie minionego miesiąca i rozszerzona straż stylów.

### Changes Required:

#### 1. Teksty

**File**: `lang/pl/ui.php` (grupa `month_plan`)

**Intent**: Nowe etykiety z mockupu.

**Contract**:
- Nowe klucze: `title_with_month` („Plan miesiąca · :month”), `state_final` („Końcowy”), `to_spend_live` („podgląd na żywo”), `to_spend_caption` („dochody − wydatki − odkładanie”), `income_metric` („Dochody”), `expense_metric` („Wydatki”), `expense_caption` („rachunki wg faktu, bieżące wg wyższej”), `pace_not_started` („miesiąc jeszcze się nie zaczął”), `status_column` („Status”), `status_waiting` („czeka”), `status_due` („do zapłaty”), `status_over_plan` („ponad plan”), `set_aside_lowers` („zmniejsza Do wydania”), `set_aside_raises` („zwiększa Do wydania”), `edit_bar_effect` („Kwoty zatwierdzone zostają zapisane do podsumowania miesiąca.”), `month_state_summary`, `month_state_approved`, `month_state_proposal` (dopisek do nazwy dostępnej miesiąca).
- `changing_hint` traci zdanie przeniesione do `edit_bar_effect`.
- Klucze bez konsumenta po zmianie usunąć (sprawdź `grep`).
- `shell.ts` typuje grupę jako `Record<string, string>`, więc typów nie trzeba dopisywać.

#### 2. Status pozycji

**File**: nowy `resources/js/lib/month-plan-status.ts`, `resources/js/lib/month-plan-status.test.ts`

**Intent**: Jedno miejsce mapuje flagi wiersza na plakietkę Statusu. To mapowanie istniejących flag, nie nowa liczba, więc nie ma kopii w PHP.

**Contract**: `monthItemStatus(row: Pick<MonthPlanItem, 'type' | 'month_item_kind' | 'is_settled'>, overPlan: boolean): { key: 'settled_income' | 'status_waiting' | 'settled_bill' | 'status_due' | 'status_over_plan'; variant: 'ok' | 'neutral' | 'destructive-soft' } | null`. Kolejność: wydatek z `overPlan` → `status_over_plan`; dochód → `settled_income` / `status_waiting`; rachunek → `settled_bill` / `status_due`; bieżąca → `null`. Testy dla każdej gałęzi, w tym zapłacony rachunek ponad planem (wygrywa „ponad plan”). W edycji `overPlan` pochodzi z `rowReading` (para `MonthItemDeviation`).

#### 3. Panel miar

**File**: nowy `resources/js/components/MonthPlanStatPanel.vue`; usunięty `resources/js/components/MonthPaceMeter.vue`

**Intent**: README StatPanel i notatka tempa w jednym komponencie, który sink może wyrenderować.

**Contract**:
- Propsy: `toSpendMinorUnits`, `incomeMinorUnits`, `expenseMinorUnits` (wszystkie `number`), `toSpendLabel: 'live' | 'final' | 'default'`, `pace: { percent: number | null; isAhead: boolean; elapsedDay: number; daysInMonth: number } | null` (null = przyszły miesiąc).
- Renderuje `StatPanel` z 4 miarami. Kwoty przez `formatMoney`. Do wydania `text-destructive` poniżej zera, Dochody `text-success`, Wydatki bez tonu. Tempo: „N%” albo „—”; podpis z `pace_days` (procent upływu przez `monthElapsedPercent`), `pace_none` albo `pace_not_started`.
- Pod panelem `<Alert variant="note">` z ikoną i `pace_ahead`, gdy `pace?.isAhead`.
- Jeden element główny.

#### 4. Tabela

**File**: nowy `resources/js/components/MonthPlanTable.vue`, `resources/js/components/MonthPlanItemRow.vue`, `resources/css/app.css` (`.month-plan-grid*`)

**Intent**: Jedna tabela z mockupu (DataTable README), która zachowuje całe dzisiejsze zachowanie wierszy, odkładania, jednorazowych i sum roku.

**Contract**:
- `MonthPlanTable`: jeden panel (`ListSurface`). Ma wiersz nagłówka `table-header` (6 kolumn od `lg`, na węższym ekranie jak `.budget-category-grid`: jeden markup, układ przez `grid-template-areas`) i notkę `kind_scope_note` w `meta`, gdy któryś wiersz może zmienić rodzaj. Dalej idą grupy.
  - „Dochody” i „Wydatki”: wiersz grupy `bg-muted section-title`, wiersze `MonthPlanItemRow`, przy wydatkach `Collapsible` jednorazowych bez ruchu (licznik Badge `neutral`, auto-otwarcie przy błędnej kwocie zostaje w stronie), wiersz „Razem w roku” (`ColumnHeaderHint` + `MonthYearSummary` w `meta`; nieaktualny = `muted-foreground` i `year_stale_hint`, bez `opacity`), pusta grupa jako wiersz `muted-foreground` z `empty_list`.
  - „Odkładam / dobieram”: etykieta z `ColumnHeaderHint` (`set_aside_hint`); w edycji `SegmentedControl` kierunku i `<input class="cell-input">` w kolumnie Plan (z `InputError` dla błędu klienta i `errors.set_aside_minor_units`); w odczycie kwota ze znakiem przez `formatMoneyAmount` + `amount`; w kolumnie Status `meta` `set_aside_lowers` / `set_aside_raises`. Bez wiersza odkładania, gdy `summary !== null`, jak dziś.
  - Stopka `meta` „Kwoty w zł” (`t.budget.amounts_in_zl` albo przeniesiony klucz).
- Zdarzenia zamiast mutacji propsów (patrz Critical Implementation Details).
- `MonthPlanItemRow`: wiersz `min-h` `--row-height`, linia `border-soft`, hover `secondary`. Nazwa z `CategoryChip`. Plakietki `one_off_badge`, `archived_badge`, `not_in_plan` w `neutral`. Status w kolumnie Status przez `monthItemStatus` (bez ręcznej pigułki `text-success border-success/40`). Komórka planu w edycji to `<input class="cell-input">` z dotychczasowymi `aria-*` i `InputError`. Kwoty planu, faktu i odchylenia przez `formatMoneyAmount` / `formatSignedMoney` bez jednostki w stylu `amount`; kolor odchylenia bez zmian logiki. Linie „W roku”, „Zatwierdzono: X” i „Pozostały limit roczny” w `meta`, z `formatMoney`, bo to zdania. Rodzaj przez `MonthPlanKindCell` w kolumnie Rodzaj (na wąskim ekranie pod nazwą przez obszar siatki, bez drugiego markupu). Bez `opacity-60`.
- `.month-plan-grid` przepisane na 6 kolumn od `lg` z obszarami; `.month-plan-grid-kind` usunięte, jeśli zbędne. Na 390 px strona nie przewija się w poziomie, a Rodzaj, Plan, Faktycznie, Odchylenie i Status są widoczne.

#### 5. Pasek miesięcy

**File**: `resources/js/components/BudgetMonthPicker.vue`

**Intent**: MonthStrip README: panel i kropka stanu każdego miesiąca.

**Contract**:
- Kontener: `card` z `border` i `rounded-lg` (bez `bg-muted/40`, `border-border/60`, `rounded-xl`).
- Przyciski `type="button"`, wybrany `default` (primary), pozostałe `ghost`, bez `rounded-lg text-xs`; etykieta w nazwanym stylu (`meta` albo `table-header`, według README).
- Nowy prop `monthStates?: (MonthStripState | null)[]`. Kropka `.month-strip-dot` z modyfikatorem stanu (`aria-hidden`) zastępuje kropkę bieżącego miesiąca, a nazwa dostępna miesiąca dostaje dopisek stanu.
- `aria-busy` i `pendingMonth` bez zmian.

#### 6. Pas edycji

**File**: `resources/js/components/MonthPlanEditBar.vue`

**Intent**: Pas z mockupu, tylko w trybie zmiany.

**Contract**:
- Propsy: `processing`, `canSubmit`, `error?`, `invalidCount?`. Znikają `toSpendMinorUnits` i `mode`.
- `role="region"` z `aria-label` `edit_bar_label`. Kontener `.month-plan-editbar` z widocznym „Edycja planu” (`font-semibold`), `edit_bar_effect`, licznikiem `invalid_amounts` i `InputError` błędu serwera (czytelnym na `primary`).
- „Anuluj” `ghost` emituje `cancel`; „Zapisz zmiany” `brand`, `type="submit"`, ze spinnerem, wyłączony przy `processing || !canSubmit`.

#### 7. Podsumowanie minionego miesiąca

**File**: `resources/js/components/MonthSummaryCard.vue`

**Intent**: Panel „Podsumowanie miesiąca” z mockupu, zgodny z tabelami Budżetu (zarzut C6).

**Contract**:
- Panel (`ListSurface`): `section-title` `summary_title` i `meta` `summary_hint`. Siatka `.month-plan-summary-grid` z nagłówkiem `table-header` (Zatwierdzony | Końcowy | Faktycznie | Odchylenie) i wierszami Dochody, Wydatki, Odkładanie (Zatwierdzone | Końcowe | Faktycznie (Wynik) | Różnica). Kwoty `formatMoneyAmount` + `amount`, odchylenie ze znakiem.
- Kolor odchylenia jawnie, nie przez `amountToneClass`: `destructive`, gdy dochód lub odkładanie < 0 albo wydatek > 0.
- „Dobrane od początku roku” w `meta`, stopka „Kwoty w zł”.
- Bez `text-xs uppercase tracking-wider` i `border-border/60`.

#### 8. Strona

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Złożyć widok w kolejności mockupu i usunąć przepisy sprzed Atramentu.

**Contract**:
- `<Form>` obejmuje cały widok miesiąca (Critical Implementation Details).
- Wiersz tytułu: `h1.page-title` z `title_with_month` (nazwa miesiąca i rok). Odznaka: `warning` `state_proposal`, `brand` `state_approved` dla niemijającego, `outline` `state_final` dla minionego zatwierdzonego, brak przy `none`. Zdanie stanu w `muted-foreground` (dotychczasowe klucze podpowiedzi).
- Akcje: przy `state === 'proposal' && can_approve` Button `brand` „Zatwierdź” (ikona `Check`, spinner, `disabled` przy `processing || !allAmountsValid`, `aria-describedby` na licznik błędnych kwot obok) oraz `InputError` dla `errors.items`. Przy `can_change && !isChanging` Button `outline` „Zmień plan” z ikoną.
- `BudgetMonthPicker` z `:month-states="monthPlan.month_states"`.
- `MonthPlanStatPanel`: poza edycją z propsów serwera, w edycji z `toSpendBreakdown` (ten sam computed zastępuje `toSpendPreviewMinorUnits`) i `monthPacePreview`. `toSpendLabel` `final` przy `summary`, `live` w edycji.
- Kolejność pod miarami: `MonthSummaryCard` (miniony), `MonthPlanTable`, `<Alert variant="note">` o dobieraniu (zastępuje Alert `warning` z tytułem), `MonthPlanEditBar` tylko przy `isChanging`.
- Usunięte: karta Do wydania, karta odkładania, dwie `ListSurface`, `section-label`, `text-3xl`, `border-border/60`, `opacity-60`.
- Logika formularza (`parsedAmount`, `submittedAmounts`, ukryte pola, watchery) bez zmian zachowania.

#### 9. Sprzątanie i straże

**File**: `resources/css/app.css`, `resources/js/pages/dev/MonthPlanSink.vue`, `tests/Unit/MonthPlanHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`

**Intent**: Usunąć stary przepis i nie dopuścić do jego powrotu.

**Contract**:
- `.section-label` usunięta z `app.css`. Sink dostosowany tylko tak, żeby kompilował się z nowymi propsami `MonthPlanEditBar` i bez `.section-label` (pełna przebudowa w Fazie 5).
- `MonthPlanHardcodedUiTest`:
  - lista plików: bez `MonthPaceMeter.vue`, z `MonthPlanStatPanel.vue`, `MonthPlanTable.vue` i `StatPanel.vue`;
  - nowe wzorce `MONTH_PLAN_FORBIDDEN_PATTERNS` dla stylów sprzed Atramentu: `\btext-(?:lg|xl|2xl|3xl)\b`, `\btracking-wider\b`, `\brounded-(?:xl|2xl)\b`, `\bborder-border\/\d+`, `\bopacity-\d+\b`, `\bsection-label\b`;
  - test detektora z przykładem dla każdego nowego wzorca.
- `check-ui-literals.mjs` `FILES`: `pages/MonthPlan.vue`, `MonthPlanItemRow`, `MonthPlanKindCell`, `MonthPlanTable`, `MonthPlanStatPanel`, `MonthPlanEditBar`, `MonthSummaryCard`, `MonthYearSummary`, `ColumnHeaderHint`.

### Success Criteria:

#### Automated Verification:

- `npm run build`, potem `npm run types:check`, `npm run check`, `npm run check:ui` (z nowymi plikami) i `npm test` (w tym `month-plan-status.test.ts`) przechodzą
- `composer ci:check` przechodzi w obrazie Sail (w tym rozszerzony `MonthPlanHardcodedUiTest`), a `tests/Feature/MonthPlanPageTest.php` i `tests/Feature/ApproveMonthPlanTest.php` przechodzą na PostgreSQL
- `grep -rn "section-label\|MonthPaceMeter" resources/ tests/` nic nie zwraca
- `vendor/bin/sloppy ci` na hoście bez nowych znalezisk
- `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia

#### Manual Verification:

- W podglądzie z worktree (`DemoHouseholdSeeder`) `/month-plan` dla bieżącego zatwierdzonego, przyszłej propozycji, trybu zmiany i minionego miesiąca wygląda jak mockup w obu motywach przy 1280 i 390 px; zrzuty w `screenshots/`
- Zatwierdzenie propozycji przyciskiem w wierszu tytułu działa myszą i klawiaturą; błędna kwota pokazuje ramkę, komunikat i licznik, a „Zatwierdź” jest wyłączony
- Zmiana planu: pas u dołu trzyma „Zapisz zmiany” w zasięgu przy przewiniętej tabeli; „Anuluj” przywraca kwoty; zmiana rodzaju pozycji działa jak przedtem
- Dobieranie ponad płynność dysponowalną pokazuje notatkę pod tabelą; Do wydania, Dochody i Wydatki reagują na wpisywanie
- Na 390 px strona nie przewija się w poziomie

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Faza 5: Stany i bramka wizualna

### Overview

Kitchen sink `/dev/month-plan` pokazuje każdy region widoku w macierzy 7 stanów z prawdziwych komponentów. Reguła opisuje sink, a zrzuty zamykają bramkę.

### Changes Required:

#### 1. Fikstury

**File**: `resources/js/lib/month-plan-fixtures.ts`

**Intent**: Dane dla każdego stanu z tabeli „Macierz 7 stanów” w `research.md`.

**Contract**: Odczyty: bieżący zatwierdzony (z `year_comparison`, `approved_minor_units` różnym od planu, `month_pace` w normie i wyprzedzającym, `percent: null`), propozycja, przyszły miesiąc (`month_pace: null`), miniony z `summary`, `month_states` z wszystkimi czterema wartościami, jednorazowe bez ruchu, ujemne Do wydania, dobieranie ponad płynność. `buildMonthPlanReading` ma konsumenta.

#### 2. Kitchen sink

**File**: `resources/js/pages/dev/MonthPlanSink.vue`

**Intent**: Bramka wizualna widoku według precedensu `BudgetKitchenSink.vue`.

**Contract**: Tablica `stateMatrix` `{state, where}` z listą na górze, sekcje `h2.section-title` z `aria-labelledby`, typografia sinka w nazwanych stylach. Komórki macierzy (pokazane albo „N/A” z powodem):
- pasek miesięcy: default, kropki wszystkich stanów, hover, focus-visible, loading `aria-busy` albo N/A, jeśli wymaga propsa tylko dla sinka;
- wiersz tytułu: cztery odznaki, „Zatwierdź” default, disabled z licznikiem, loading, focus; „Zmień plan” focus;
- `MonthPlanStatPanel`: default, ujemne, podgląd na żywo, końcowe, tempo w normie, wyprzedzające z notatką, `percent: null`, przyszły miesiąc;
- `MonthPlanTable` w odczycie i edycji: hover wiersza, focus `cell-input`, błąd komórki, błąd klienta i serwera odkładania, notatka o dobieraniu, `Collapsible` otwarty i zamknięty, pusta grupa, linia „W roku” aktualna i nieaktualna, „Zatwierdzono: X”, rodzaj pending i error, wszystkie warianty Statusu;
- `MonthPlanEditBar`: przyklejony w przewijanym pudełku, Zapisz disabled bez zmian, błąd serwera, processing;
- `MonthSummaryCard`;
- stany puste strony z akcją „Przejdź do budżetu”.

Stan `loading` całej strony: N/A (dane przychodzą z nawigacją Inertia).

#### 3. Straże i reguły

**File**: `scripts/check-ui-literals.mjs`, `tests/Unit/MonthPlanHardcodedUiTest.php`, `.ai/rules/js.md`, `.docs/design-system.md`

**Intent**: Sink jest pilnowany jak widok, a reguła mówi, co w nim jest.

**Contract**: `pages/dev/MonthPlanSink.vue` na obu listach plików. `js.md:58` wymienia stany sinka (jak opis Budżetu w `:37`). `design-system.md:96` opisuje nowy sink i przepis zrzutów.

#### 4. Zrzuty

**File**: `context/changes/month-plan-atrament-ui/screenshots/`

**Intent**: Dowód bramki wizualnej.

**Contract**: Jednorazowy skrypt Playwright (bez commitu skryptu), podgląd z worktree według AGENTS.md „Preview from a worktree”: `/dev/month-plan` oraz `/month-plan` (bieżący, przyszła propozycja, tryb zmiany, miniony) w jasnym i ciemnym motywie przy 1280 i 390 px, porównane z mockupem.

### Success Criteria:

#### Automated Verification:

- `npm run build`, potem `npm run types:check`, `npm run check`, `npm run check:ui` (z sinkiem na liście) i `npm test` przechodzą
- `composer ci:check` przechodzi w obrazie Sail (w tym `DevMonthPlanSinkTest` i `MonthPlanHardcodedUiTest` z sinkiem)
- `vendor/bin/sloppy ci` na hoście bez nowych znalezisk

#### Manual Verification:

- Każda komórka macierzy w `/dev/month-plan` jest pokazana albo ma „N/A” z powodem; zrzuty sinka i widoku w obu motywach przy 1280 i 390 px leżą w `screenshots/` i odpowiadają mockupowi
- Focus-visible jest widoczny na `cell-input`, przyciskach paska miesięcy i przyciskach pasa edycji (na `primary`) w obu motywach; tekst na Button `brand` i na pasie jest czytelny w obu motywach
- Użytkownik akceptuje widok względem mockupu

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Testing Strategy

### Unit Tests:

- `ToSpendCalculatorTest.php` i `to-spend-calculator.test.ts`: te same przypadki rozbicia (przykład kanoniczny, rachunek ze zwrotem, bieżąca wg wyższej, pusta lista, tożsamość przy odkładaniu i dobieraniu).
- `month-plan-status.test.ts`: każda gałąź statusu, w tym zapłacony rachunek ponad planem.
- `MonthPlanHardcodedUiTest`: detektor łapie każdy nowy wzorzec stylu sprzed Atramentu; pliki widoku są czyste.
- Bez zmian, ale muszą przejść: `entry-to-spend-warning.test.ts`, `import-to-spend-warnings.test.ts`, `month-pace-preview.test.ts`, `month-item-deviation.test.ts`.

### Integration Tests:

- `MonthPlanPageTest`: `to_spend_income_minor_units` / `to_spend_expense_minor_units` i ich tożsamość z `to_spend_minor_units`, `month_states` dla czterech stanów, null przy `none`. Na PostgreSQL.
- `ApproveMonthPlanTest`, `ChangeMonthPlanTest`: bez zmian; przechodzą, bo payload formularza się nie zmienia.
- `DevMonthPlanSinkTest`: 404 poza `local` bez zmian.

### Manual Testing Steps:

1. Otwórz `/month-plan` dla bieżącego zatwierdzonego miesiąca: tytuł, odznaka `brand`, kropki miesięcy, 4 miary, tabela z Statusem, stopka „Kwoty w zł”.
2. Kliknij „Zmień plan”, zmień kwotę i przewiń: pas u dołu, miary reagują, linia „W roku” wyciszona z podpowiedzią; „Zapisz zmiany” zapisuje, „Anuluj” przywraca.
3. Przejdź do przyszłego miesiąca: „Niezatwierdzony”, Tempo „—” z „miesiąc jeszcze się nie zaczął”, „Zatwierdź” w wierszu tytułu; wpisz błędną kwotę i sprawdź blokadę.
4. Ustaw dobieranie ponad płynność: notatka pod tabelą.
5. Przejdź do minionego miesiąca: „Końcowy”, podsumowanie w siatce, tabela do odczytu.
6. Powtórz w ciemnym motywie i przy 390 px.

## Performance Considerations

`month_states` czyta 12 odczytów z `MonthPlanProposal`, który ładuje plany i kategorie stałą liczbą zapytań i trzyma memo. Rozbicie To Spend liczy się w tej samej pętli co dzisiejszy wynik. Brak nowych zapytań.

## Migration Notes

Bez migracji danych. Propsy tylko przybywają; formularze zatwierdzenia i zmiany wysyłają ten sam payload.

## References

- Research: `context/changes/month-plan-atrament-ui/research.md`
- Wzorzec: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (MockupCAtrament → Plan miesiąca `preview.html:747-790`, `monthReading` `:407-418`; MonthStrip, StatPanel, DataTable, Field, EditBar, Notice, Badge)
- Precedens: `context/archive/2026-11-05-budget-atrament-ui/plan.md`, `resources/js/pages/dev/BudgetKitchenSink.vue:215-271`
- Para To Spend: `app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts`, `app/Support/MonthToSpend.php:55-75`
- Stan miesiąca: `app/Support/MonthPlanProposal.php:40-109`, `app/Http/Controllers/MonthPlanController.php:157-224,314-324`
- Reguła To Spend: `CONTEXT.md:80`; ADR-0035, ADR-0037, ADR-0039

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0042 istnieje, a ADR-0035 do niego odsyła — ac69acf
- [x] 1.2 grep section-label w js.md i design-system.md nic nie zwraca — ac69acf
- [x] 1.3 npm run check przechodzi — ac69acf

#### Manual

- [x] 1.4 Użytkownik akceptuje ADR-0042 i zmienione reguły brand i Alert — ac69acf

### Phase 2: Warstwa wspólna

#### Automated

- [x] 2.1 build, types:check, check i check:ui przechodzą
- [x] 2.2 npm test przechodzi
- [x] 2.3 composer ci:check przechodzi w obrazie Sail

### Phase 3: Logika i kontrakt propsów

#### Automated

- [ ] 3.1 Testy pary i strony przechodzą na PostgreSQL w obrazie Sail
- [ ] 3.2 npm test przechodzi z testami pary i ostrzeżeń
- [ ] 3.3 build, types:check i check przechodzą
- [ ] 3.4 composer ci:check przechodzi w obrazie Sail
- [ ] 3.5 sloppy ci bez nowych znalezisk
- [ ] 3.6 pint --dirty bez zmian do zrobienia

#### Manual

- [ ] 3.7 /month-plan renderuje się jak przed zmianą

### Phase 4: Widok Planu miesiąca

#### Automated

- [ ] 4.1 build, types:check, check, check:ui z nowymi plikami i npm test przechodzą
- [ ] 4.2 composer ci:check w obrazie Sail i testy Planu miesiąca na PostgreSQL przechodzą
- [ ] 4.3 grep section-label i MonthPaceMeter nic nie zwraca
- [ ] 4.4 sloppy ci bez nowych znalezisk
- [ ] 4.5 pint --dirty bez zmian do zrobienia

#### Manual

- [ ] 4.6 /month-plan w czterech stanach zgodny z mockupem w obu motywach przy 1280 i 390 px
- [ ] 4.7 Zatwierdzenie z wiersza tytułu działa myszą i klawiaturą, błędna kwota blokuje
- [ ] 4.8 Pas edycji u dołu, Zapisz i Anuluj działają, zmiana rodzaju działa
- [ ] 4.9 Notatka o dobieraniu i miary reagują na wpisywanie
- [ ] 4.10 Na 390 px brak poziomego przewijania strony

### Phase 5: Stany i bramka wizualna

#### Automated

- [ ] 5.1 build, types:check, check, check:ui z sinkiem i npm test przechodzą
- [ ] 5.2 composer ci:check przechodzi w obrazie Sail
- [ ] 5.3 sloppy ci bez nowych znalezisk

#### Manual

- [ ] 5.4 Macierz 7 stanów w sinku kompletna, zrzuty w obu motywach przy 1280 i 390 px
- [ ] 5.5 Focus-visible i kontrast na brand i pasie w obu motywach
- [ ] 5.6 Użytkownik akceptuje widok względem mockupu
