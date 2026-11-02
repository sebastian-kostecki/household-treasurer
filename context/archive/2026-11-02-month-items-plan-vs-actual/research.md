---
date: 2026-11-02T11:52:13+01:00
researcher: Claude (claude-opus-5-5)
git_commit: df33747402bacc9df3a823f79654fef9e9aed732
branch: month-items-plan-vs-actual
repository: household-treasurer
topic: "S-04 month-items-plan-vs-actual: co strona „Plan miesiąca” już pokazuje, co trzeba dodać dla FR-006 i FR-008 i których decyzji dokumenty nie rozstrzygają"
tags: [research, codebase, month-plan, month-items, one-off, available-limit, deviation, to-spend]
status: complete
last_updated: 2026-11-02
last_updated_by: Claude (claude-opus-5-5)
---

# Research: S-04 — pozycje miesiąca: plan i wykonanie

**Date**: 2026-11-02T11:52:13+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: df33747402bacc9df3a823f79654fef9e9aed732 (gałąź `month-items-plan-vs-actual` wypchnięta na `origin`; jedyna zmiana względem `eff7a23` to `change.md`, więc kotwice `plik:linia` dotyczą też `eff7a23` na `origin/development`)
**Branch**: month-items-plan-vs-actual
**Repository**: household-treasurer

## Research Question

Użytkownik uruchomił `/10x-research month-items-plan-vs-actual` bez osobnego pytania, a `change.md` ma puste Notes. Pytanie wyprowadzono z roadmapy `context/foundation/roadmap.md:134-144` (S-04: „użytkownik może zobaczyć plan i faktyczną kwotę każdej pozycji oraz kategorie jednorazowe”; PRD FR-006, FR-008; ryzyko: „zaciemnienie widoku długą listą kategorii z zerowym planem”).

Pytanie robocze: **co strona „Plan miesiąca” zbudowana w S-01 już pokazuje, czego brakuje dla FR-006 (plan i faktyczna kwota każdej pozycji, z odchyleniem) i FR-008 (jednorazowe z resztą rocznego limitu, reszta w zwiniętej sekcji), z jakich istniejących odczytów i komponentów można skorzystać i których decyzji dokumenty nie rozstrzygają.** Badanie nie wybiera rozwiązania; to zadanie `/10x-plan`.

## Summary

1. **Plan i faktyczna kwota per pozycja już są na stronie.** `MonthPlanController::itemRow` (`app/Http/Controllers/MonthPlanController.php:173-195`) wysyła per wiersz `plan_minor_units`, `actual_minor_units`, `has_positive_amount`, `month_item_kind`, `is_one_off`, `is_archived`, `in_plan`, `can_change_kind`; `MonthPlan.vue` (480 linii, jeden plik) rysuje kolumny Kategoria | Plan | Faktycznie. **Brakuje** odchylenia, wyróżnienia „ponad plan” i znacznika realizacji. W tym wierszu nie ma pola odchylenia, wkładu w „do wydania” ani flagi ponad plan (`MonthPlanController.php:183-194`), a sprawdzone wyszukiwanie (agent badawczy) nie znalazło w kodzie odczytu plan − faktycznie względem planu miesiąca.
2. **Wszystkie niezarchiwizowane jednorazowe już są wierszami planu z kwotą 0.** Propozycja wstawia każdą kategorię niezarchiwizowaną przed 1. dniem miesiąca (`app/Support/MonthPlanProposal.php:113-119`), a jednorazowej daje 0 (`:148-150`). Zatwierdzenie wymaga dokładnie tego zbioru id (`app/Actions/ApproveMonthPlan.php:47-55`) i zapisuje też zera. Ryzyko z roadmapy („długa lista zer”) **istnieje więc już dziś**. Podział z FR-008 nie może opierać się na `in_plan`, bo dla tych wierszy jest ono zawsze `true`; musi wynikać z kwot (plan, faktycznie).
3. **Reszta rocznego limitu jest dostępna bez dodatkowego zapytania.** „Roczny limit” = Current Plan (`categories.planned_amount_minor_units`), a reszta = `planned − execution_minor_units` (`app/Support/CategoryExecutionMetrics.php:7-10`, CONTEXT Available Limit `CONTEXT.md:51-53`). `execution_minor_units` to przechowywana suma roczna aktualizowana przy każdej operacji (`app/Actions/ManageFinancialOperation.php:226-244`). `MonthPlanProposal::forBudget` już ładuje wszystkie kategorie budżetu (`MonthPlanProposal.php:42`). Kontroler potem ponownie pobiera tylko kategorie z `$rowIds` (`MonthPlanController.php:114-117`).
4. **Zwinięta sekcja ma gotowy wzorzec.** Archiwalna sekcja strony Budżet używa shadcn-vue `Collapsible` z przyciskiem, licznikiem `Badge` i obracanym `ChevronDown` (`resources/js/components/BudgetCategoryList.vue:16-20`, `:451-575`).
5. **Predykat „pozycja ponad plan” jest już zdefiniowany dla S-07** (`CONTEXT.md:80`): wydatek, którego faktyczna kwota w miesiącu przekracza plan, dowolnie, także przy planie 0 i dla jednorazowej, niezależnie od rodzaju. Wyróżnienie wiersza w S-04 powinno używać tego samego predykatu.
6. **Dokumenty nie rozstrzygają sześciu decyzji** (sekcja Open Questions): co to jest „znacznik realizacji”, definicja i znak odchylenia, kryterium „jednorazowa z planem albo wydatkiem”, która reszta limitu (sam Available Limit czy po odjęciu planu miesiąca), jak zwinięta sekcja współgra z formularzem zatwierdzenia oraz co pokazuje miniony miesiąc bez planu.

## Detailed Findings

### 1. Strona „Plan miesiąca” — stan po S-01

- Trasy: `GET month-plan` → `MonthPlanController@show` (`routes/web.php:28`), `POST budgets/{budget}/month-plans/{month}/approve` (`routes/web.php:29-31`).
- `show` (`MonthPlanController.php:28-68`) wysyła `budget`, `viewedMonth`, `viewedMonthTiming` (`past|current|future`), `currentMonth`, `monthPlan`. `monthPlan` jest `null`, gdy budżet nie jest w wykonaniu.
- `monthPlanPayload` (`:103-153`) zwraca `state` (`proposal|approved|none`), `approved_at`, `can_approve`, `to_spend_minor_units` (null przy `none`), `income_items[]`, `expense_items[]`.
- Zbiór wierszy (`:109-117`) to suma kluczy planu lub propozycji (`$reading->amounts`) i kategorii z operacjami w miesiącu (`$actuals->categoryIds()`), posortowana `ordered()`: najpierw aktywne, potem zarchiwizowane (`app/Models/Category.php:78-81`, wg agenta). Podział na dochody i wydatki idzie po `CategoryType` (`:134-138`).
- `MonthPlan.vue`:
  - siatka `grid-cols-[1fr_7rem_7rem]` z nagłówkami Kategoria | Plan | Faktycznie (`:349-359`, wg agenta),
  - plakietki wiersza: „Jednorazowa”, „Zarchiwizowane”, „Poza planem” (`:381-391`),
  - przełącznik rodzaju (PATCH `CategoryController.updateMonthItemKind`),
  - pole kwoty tylko w edytowalnej propozycji dla `in_plan`,
  - ukryte pola `items[i][category_id|amount_minor_units]` tylko dla wierszy `in_plan` (`:324-340`, wg agenta).
- Stan `none` pokazuje tylko `IndexEmptyState` (`MonthPlan.vue:279-280`). Miniony miesiąc bez planu nie pokazuje więc żadnych kwot faktycznych.
- Komponenty: `BudgetMonthPicker`, `CategoryChip`, `IndexEmptyState`, `InputError`, `ListSurface`, ui `Badge`, `Button`, `Card`, `Input`. Tłumaczenia: `lang/pl/ui.php:279-307` (`month_plan.*`; `actual` = „Faktycznie”, `one_off_badge`, `not_in_plan`, `archived_badge`). Typ `UiTranslations` w `resources/js/types/shell.ts` trzeba aktualizować razem z kluczami (S-01 `plan.md:663`, wg agenta).

### 2. Kwota faktyczna i „do wydania”

- `MonthCategoryActuals` (`app/Support/MonthCategoryActuals.php:21-27`, wg agenta) to jedno zapytanie `GROUP BY category_id` po `financial_operations` budżetu z `operation_date` w miesiącu. Zwraca:
  - sumę ze znakiem (zwroty netto, więc kwota może być ujemna),
  - flagę dodatniej kwoty (`positive_count > 0`).
  Przelewy własne i ruchy kapitału są w innych tabelach, więc nie wchodzą. Obejmuje też dochody.
- `ToSpendCalculator` (`app/Support/ToSpendCalculator.php:15-35`) liczy tak:
  - dochód: `has_positive ? actual : plan`,
  - Bill: `−(has_positive ? actual : plan)`,
  - pozostałe: `−max(plan, actual)`.
  Jednorazowa liczy się według swojego `month_item_kind` (domyślnie Running), bo `MonthPlanController.php:126-132` przekazuje rodzaj kategorii. Kalkulator zwraca tylko sumę; wkład wiersza nie jest wystawiony. Bliźniak TS: `resources/js/lib/to-spend-calculator.ts:13-32`, używany do podglądu edycji (`MonthPlan.vue:136-154`, wg agenta).
- Wniosek (nie zaobserwowane zachowanie): przeniesienie wiersza jednorazowej z planem 0 i kwotą faktyczną 0 do zwiniętej sekcji nie zmienia „do wydania”, bo jego wkład to `−max(0, 0) = 0`, o ile rodzaj to Running. Dla jednorazowej oznaczonej Bill wkład bez wydatku dodatniego to `−plan = 0`. Oba przypadki dają 0.

### 3. Kategorie jednorazowe i roczny limit

- Jednorazowa = wydatek z `is_paced = false`. Nie ma osobnej kolumny ani enumu (`database/migrations/2026_09_10_195354_add_is_paced_to_categories_table.php:12`, fabryka `oneOff()` `database/factories/CategoryFactory.php:64-70`, wg agenta). Pole `is_one_off` na stronie to `$isExpense && ! is_paced` (`MonthPlanController.php:187`). Dochód nie może być jednorazowy (walidacja `CategoryValidationRules.php:40-59`, restore `RestoreUserData.php:422-429`, wg agenta).
- `month_item_kind` to niezależna oś: jednorazowa może być Bill albo Running (`CONTEXT.md:75-77`, „Independent of the Paced / One-off flag”).
- Roczny limit i reszta:
  - reszta = `remainingMinorUnits(planned, execution)` (`CategoryExecutionMetrics.php:7-10`), a `isOverLimit` oznacza `< 0` (`:21-24`),
  - tego samego wzoru używają strona Budżet (`BudgetController.php:250-258`, wg agenta) i powiadomienie `one_off_over_limit` (`app/Support/DashboardNotices.php:106-157`, wg agenta),
  - etykiety: `budget.remaining_expense` „Pozostały limit” i `budget.formula_expense` „Plan bieżący − Wykonanie = Pozostały limit” (`lang/pl/ui.php:168,178`, wg agenta).
- Zakres `execution_minor_units`: wszystkie operacje kategorii w roku budżetu do dziś. Data operacji musi należeć do roku budżetu i nie może być przyszła (`ExpenseValidationRules.php:24,129-140`, wg agenta). Dla minionego miesiąca to więc stan na dziś, a nie na koniec tamtego miesiąca. „Reszty na koniec miesiąca M” nie liczy dziś żaden odczyt. Najbliższy odpowiednik to `BudgetMonthExecutionIndex` (`executionBeforeMonth` / `executionInMonth`, `app/Support/BudgetMonthExecutionIndex.php:71-93`). Ten indeks ładuje jednak wszystkie wiersze wydatków roku bez agregacji.
- Uwaga do testów: `FinancialOperation::factory()` nie zwiększa `execution_minor_units` (`tests/Feature/MonthPlanPageTest.php:406-414`, wg agenta). Testy reszty limitu muszą ustawić `execution_minor_units` jawnie albo iść przez akcję.
- Strona Budżet nie wyróżnia dziś jednorazowych: `BudgetCategoryList.vue:49` deklaruje `is_paced`, ale go nie renderuje. Klucze `budget.one_off_badge` i `budget.paced_badge` są zdefiniowane i nieużywane (`ui.php:236-237`, wg agenta).

### 4. Reguły domenowe istotne dla S-04

- **Month Item Kind** (`CONTEXT.md:75-77`):
  - Bill: po pierwszym wydatku z kwotą dodatnią kwota faktyczna zastępuje plan; sam zwrot tego nie robi,
  - Running: plan obowiązuje cały miesiąc,
  - dochód liczy się jak Bill.
- **Pozycja ponad plan** (`CONTEXT.md:80`, ADR-0033 `.docs/adr/0033-month-plan-beside-annual-budget.md:9`, wg agenta): wydatek z kwotą faktyczną > plan, dowolnie, także przy planie 0 i dla jednorazowej. To powiadomienie `exceeded` w S-07, a nie w S-04.
- **One-off Category** (`CONTEXT.md:43-45`): w propozycji zawsze 0, nie wchodzi do Month Pace, ma roczny Available Limit.
- **Archiwalne** (ADR-0033 `:5`, wg agenta): kategoria zostaje w miesiącu, w którym ma plan albo wydatek, i znika z kolejnych propozycji.
- **Odchylenie („deviation”)** występuje w `CONTEXT.md` jeden raz, w definicji Month Summary (`:95-97`, wg agenta), bez definicji. **„Znacznik realizacji”** jest tylko w `prd.md:44` i `shape-notes.md:65` („pozycje rozchodów z kolumnami plan i faktycznie, ze znacznikiem realizacji”), bez terminu w glosariuszu.
- **Kolory** (`.docs/design-system.md:27-29`, ADR-0032, wg agenta):
  - `success`: dochód zaksięgowany albo ponad plan,
  - `destructive`: przekroczenie,
  - `primary`: pasek realizacji w normie.
  Kolor kategorii to tożsamość, nigdy status (`.ai/rules/js.md:8-24`, wg agenta).

### 5. Reguły repozytorium dla tej zmiany

- `AGENTS.md:28-32` (wg agenta): nowe odczyty miesiąca liczy serwer i przekazuje jako propsy. Kopia TS powstaje tylko wtedy, gdy podgląd musi reagować przed wysłaniem, i plan musi to uzasadnić. Pary `ToSpendCalculator` PHP/TS zmienia się razem z testami.
- `AGENTS.md:24-26` (wg agenta): tablicę PHP propsów i typ `defineProps<>` zmienia się w jednym commicie, z `assertInertia`.
- Wycinek zmienia widok, który już się renderuje, więc `CLAUDE.md` kieruje pracę wizualną przez `/10x-ui` (kontrakt systemu projektowego, matryca stanów, bramka zrzutu ekranu).
- Testy: PostgreSQL dla zapytań i dat. Nie ma testów komponentów ani przeglądarkowych dla `MonthPlan.vue` (wg agenta). Testy strony: `tests/Feature/MonthPlanPageTest.php` z fixturą kanoniczną z czerwca 2026 (`:370-400`), w której „Prezent” to jednorazowa z planem 0 i kwotą faktyczną 300,00.

## Code References

- `app/Http/Controllers/MonthPlanController.php:103-153` — budowa `monthPlan`, zbiór wierszy, wywołanie kalkulatora
- `app/Http/Controllers/MonthPlanController.php:173-195` — kształt wiersza (`itemRow`)
- `app/Support/MonthPlanProposal.php:42` — ładowanie wszystkich kategorii budżetu
- `app/Support/MonthPlanProposal.php:113-119,148-150` — członkostwo w propozycji, jednorazowa = 0
- `app/Actions/ApproveMonthPlan.php:47-55` — zatwierdzenie wymaga dokładnie zbioru id propozycji
- `app/Support/MonthCategoryActuals.php:21-27` — agregat miesiąca (suma ze znakiem, flaga dodatniej)
- `app/Support/ToSpendCalculator.php:15-35`, `resources/js/lib/to-spend-calculator.ts:13-32` — wzór „do wydania”, para PHP/TS
- `app/Support/CategoryExecutionMetrics.php:7-24` — reszta limitu, procent, ponad limit
- `app/Actions/ManageFinancialOperation.php:226-244` — utrzymanie `execution_minor_units`
- `app/Support/DashboardNotices.php:106-157` — `one_off_over_limit` (roczny)
- `resources/js/pages/MonthPlan.vue:279-280,324-340,349-391` — stan `none`, ukryte pola, siatka, plakietki
- `resources/js/components/BudgetCategoryList.vue:451-575` — wzorzec zwiniętej sekcji (`Collapsible`)
- `lang/pl/ui.php:279-307` — klucze `month_plan.*`
- `tests/Feature/MonthPlanPageTest.php:370-400` — fixtura kanoniczna

## Architecture Insights

- Odczyty miesiąca to czyste klasy w `app/Support` (`MonthPlanProposal`, `MonthCategoryActuals`, `ToSpendCalculator`), składane w kontrolerze w płaskie propsy wiersza. Odchylenie i flaga ponad plan pasują do tego samego miejsca, czyli do `itemRow`, jako pola liczone na serwerze. S-05, S-07 i S-09 mogą wtedy korzystać z tego samego predykatu.
- Zbiór wierszy zatwierdzenia (`in_plan`) i zbiór wyświetlania to dziś to samo. FR-008 rozdziela je: zwinięta sekcja to podział prezentacji, a ADR-0033 nadal traktuje wszystkie kategorie jako pozycje planu. Formularz zatwierdzenia i tak musi wysłać ukryte pola dla jednorazowych w zwiniętej sekcji, chyba że plan zmieni kontrakt `ApproveMonthPlan`.
- Podgląd edycji propozycji działa w TS. Jeśli przynależność do sekcji albo odchylenie mają reagować na wpisaną kwotę przed zatwierdzeniem, pojawia się nowa logika po stronie klienta, a `AGENTS.md:30` wymaga dla niej uzasadnienia w planie.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:78` — S-01 świadomie zostawił dla S-04 „widok plan vs faktycznie z odchyleniami, sekcję jednorazowych z resztą rocznego limitu” (zgodne ze stanem kodu: tego nie ma).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:70` — rodzaj pozycji jest cechą kategorii, a przełącznik działa tylko dla miesiąca bieżącego i przyszłych (w kodzie: `can_change_kind`, `MonthPlanController.php:192`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:137-140` — wiersze to suma pozycji planu i kategorii z operacjami w miesiącu, z planem 0 dla tych spoza planu (w kodzie: `MonthPlanController.php:109-112,190`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:151-161` — przykład kanoniczny „do wydania” 3730,00 (z jednorazową „Prezent” 0 / 300,00) nadaje się też do testów odchyleń i ponad plan.
- `context/archive/2026-11-02-month-plan-domain-decisions/plan-brief.md:26` — „jednorazowa ponad plan: powiadomienie przy każdym przekroczeniu, także przy planie miesiąca 0” (dotyczy S-07, ale jest tym samym predykatem).

## Related Research

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md` — stan kodu przed S-01 (model danych, odczyty miesiąca, snapshot).

## Open Questions

Decyzje produktowe do rozstrzygnięcia w `/10x-plan`. Dokumenty ich nie zamykają.

1. **„Znacznik realizacji” (`prd.md:44`).** Nie ma go w glosariuszu. Najbliższy sygnał w kodzie to `has_positive_amount` (wyzwalacz Bill i dochodu). Otwarte: czy to wyliczana plakietka (np. „zapłacone” dla Bill i dochodu po pierwszej dodatniej kwocie), czy coś innego, i co oznacza dla Running. Ryzyko słownictwa: „realizacja” znaczy w UI wykonanie budżetu rocznego (`ui.php:115,120,123,154`, wg agenta).
2. **Odchylenie.** Kierunek (plan − faktycznie albo faktycznie − plan), punkt odniesienia (plan albo kwota liczona do „do wydania”) i prezentacja dla dochodu. Design system określa tylko dochód ponad plan = `success` i przekroczenie = `destructive`; „poniżej planu” nie jest określone.
3. **Kryterium „jednorazowa z planem albo wydatkiem w miesiącu” (FR-008).** Czy „z planem” znaczy kwota > 0? Czy miesiąc z samym zwrotem (kwota faktyczna < 0) liczy się jako „z wydatkiem”? Czy w edytowalnej propozycji jednorazowa ma przechodzić między listą a zwiniętą sekcją na żywo po wpisaniu kwoty, czy zwinięta sekcja ma własne pola kwot?
4. **Która „reszta rocznego limitu”.** Sam Available Limit (Current Plan − wykonanie roku do dziś, `CONTEXT.md:51`) czy dodatkowo minus plan tego miesiąca? Czy pokazywać ją tylko w zwiniętej sekcji, czy także przy jednorazowych na liście planu? Dla minionego miesiąca: stan na dziś czy na koniec miesiąca (tego drugiego nie liczy żaden istniejący odczyt)?
5. **Zwinięta sekcja a zatwierdzenie.** `ApproveMonthPlan` wymaga kluczy wszystkich jednorazowych (`ApproveMonthPlan.php:47-55`). Plan musi wybrać, czy sekcja wysyła ukryte zera, czy zmienia się kontrakt akcji (np. brakujące jednorazowe = 0). Do tego: kolejność, licznik w nagłówku, czy w zatwierdzonym i minionym planie sekcja wygląda tak samo, czy obejmuje zarchiwizowane jednorazowe bez planu i wydatku (ADR-0033 `:5` sugeruje, że nie).
6. **Miniony miesiąc bez planu (`state=none`).** Dziś nie pokazuje żadnych kwot faktycznych (`MonthPlan.vue:279-280`). FR-006 mówi o „każdej pozycji miesiąca”, a FR-011 i FR-013 zostawiają minione miesiące bez planu przy starym odczycie. Otwarte: czy S-04 ma pokazać faktyczne bez planu, czy zostawić to bez zmian.

Luki pokrycia: nie czytano szczegółowo `RebuildOperation`, `ImportFinancialOperations` ani `ConvertImportPairFinancialOperation`. To, że aktualizują `execution_minor_units`, wynika tylko z wyników grepa agenta. Część kotwic oznaczonych „wg agenta” pochodzi z raportów agentów; osobiście sprawdzono kotwice z sekcji Summary pkt 1–5 oraz `ManageFinancialOperation.php:226-244`, `CONTEXT.md:43-53,80`, `prd.md:44`, `BudgetCategoryList.vue:17-19`, `MonthPlan.vue:279-280`.
