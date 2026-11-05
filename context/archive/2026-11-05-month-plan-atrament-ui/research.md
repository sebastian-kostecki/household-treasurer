---
date: 2026-11-05T20:18:38+01:00
researcher: Claude (Opus 5.5) dla Sebastiana Kostecki
git_commit: 7f54b7b329a3fc3d7e138de8654f84bcbccbee83
branch: worktree-month-plan-atrament-ui
repository: household-treasurer
topic: "Dwukierunkowy audyt widoku Plan miesiąca (MonthPlan.vue) względem design systemu „C · Atrament i morski”"
tags: [research, codebase, month-plan, design-system, atrament, ui]
status: complete
last_updated: 2026-11-05
last_updated_by: Claude (Opus 5.5)
---

# Research: audyt widoku Plan miesiąca względem systemu „C · Atrament i morski”

**Date**: 2026-11-05T20:18:38+01:00
**Git Commit**: 7f54b7b (gałąź z `development` 9b8c2ba + `change.md`; czyste drzewo)
**Branch**: worktree-month-plan-atrament-ui
**Wzorzec**: artefakt `https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA`, wersja 1793905230-035b. Przeczytane: `project/README.md`, `tokens.json`, README komponentów MonthStrip, StatPanel, DataTable, Field, EditBar, Notice, Badge, MockupCAtrament; `bundle.css` w całości; podglądy MonthStrip, EditBar, Notice, Field; z `MockupCAtrament/preview.html` widok Plan miesiąca (linie 747–790), stan miesiąca (`monthState` :443), próg tempa (:416), handlery edycji (:594–625), CSS mockupu (:9–237). Artefakt to wzorzec, nie źródło wartości: wartości żyją w `resources/css/app.css`.

## Research Question

Dwukierunkowy audyt `MonthPlan.vue` i komponentów z `MonthPlanHardcodedUiTest` względem Atramentu (MockupCAtrament → Plan miesiąca; MonthStrip, StatPanel, DataTable, Field/`ht-cell-input`, EditBar, Notice/`ht-note`, Badge). Potwierdzić zarzuty 1–5 z plikiem i linią. Sprawdzić, czy propsy mają stan wszystkich 12 miesięcy dla kropek MonthStrip. Sprawdzić, czy Dochody i Wydatki do StatPanel da się wziąć z istniejących odczytów (bez nowej logiki TS poza parami z AGENTS.md). Rozstrzygnąć los `MonthSummaryCard` i `.section-label`. Wypisać komórki macierzy 7 stanów, których brakuje w `/dev/month-plan`.

## Summary

- **Literały kolorów:** skan z `/10x-ui` (hex, funkcje kolorów, klasy palety, wartości arbitralne) na 10 plikach widoku (lista z `tests/Unit/MonthPlanHardcodedUiTest.php:7-18` plus `PlanChangedHint.vue`) daje 0 trafień. Dryf jest w typografii, promieniach i ramkach sprzed Atramentu: ok. 50 trafień `text-3xl/lg/sm/xs`, `uppercase tracking-wider`, `rounded-2xl/xl`, `border-border/60`, `opacity-60`, które test literałów przepuszcza.
- **Zarzuty 1–5 potwierdzone** z plikiem i linią (C1–C5). Research dokłada C6 (`MonthSummaryCard`) i C7 (kitchen sink nie renderuje widoku).
- **Kropki MonthStrip:** żaden props nie niesie stanu 12 miesięcy. Stan da się policzyć bez dodatkowych zapytań z `MonthPlanProposal`, który kontroler już ma (`$toSpend->proposal`, `MonthToSpend.php:34`). Potrzebny nowy props, a z nim kontrakt propsów z AGENTS.md (kontroler + `defineProps` + `assertInertia` w jednym commicie).
- **Dochody i Wydatki:** w mockupie to **składniki Do wydania**, nie sumy planu. Podpisy: „dochody − wydatki − odkładanie” i „rachunki wg faktu, bieżące wg wyższej” (`preview.html:777,779`). Żaden props ani computed ich nie niesie, a `toSpendMinorUnits` zwraca tylko wynik netto (`to-spend-calculator.ts:16-39`). Pokazanie ich wymaga zmiany pary To Spend (PHP + TS + oba testy) albo innej etykiety miar. To decyzja do planu (D1).
- **`.section-label`:** usunąć. Wszystkie 4 użycia w widoku mapują się na istniejące style (`label-caps`, `section-title`). Regułę w `.ai/rules/js.md:56` i opis w `.docs/design-system.md:19` poprawić w tej zmianie.
- **`MonthSummaryCard`:** przebudować na precedens Budżetu (`ListSurface`, siatka z nazwaną klasą, `table-header`, `amount`, stopka „Kwoty w zł”). Mockup pokazuje go jako jeden panel „Podsumowanie miesiąca” z czterema kolumnami kwot (`preview.html:752-759`).
- **Kitchen sink `/dev/month-plan`** renderuje wiersze, pasek edycji i stany puste. Nie renderuje paska miesięcy, karty Do wydania, tempa, odkładania, podsumowania minionego miesiąca, sum roku ani zwijanych jednorazowych. Listę braków podaje sekcja „Macierz 7 stanów”.

## Charges

### C1. Brakujące tokeny: typografia, promień, ramki sprzed Atramentu

Nazwane style istnieją w `app.css:245-283` (`page-title`, `section-title`, `table-header`, `label-caps`, `meta`, `kpi-lg`, `kpi`, `amount`, `amount-sum`). Widok ich nie używa, poza `.column-header` (`app.css:398-400` → `table-header`) i `StatPanel` (nieużywany tutaj).

- `pages/MonthPlan.vue:516`: tytuł `text-lg font-semibold capitalize` zamiast `page-title`. Tekst „Listopad 2026” (`viewedMonthLabel` :326-335). Mockup: „Plan miesiąca · listopad” (`preview.html:774`).
- `pages/MonthPlan.vue:563`: Do wydania `text-3xl font-semibold tabular-nums` (Inter 30 px) zamiast `kpi-lg` (mono 26/32).
- `components/MonthPlanEditBar.vue:31`: `border-border/60 … md:rounded-2xl md:border` (przepis sprzed ADR-0037). `:38` kwota `text-lg font-semibold`.
- `components/MonthPlanItemRow.vue:173,195,208`: kwoty `text-sm tabular-nums` przez `formatMoney` z „zł” zamiast `amount` + `formatMoneyAmount` i stopki „Kwoty w zł” (`.ai/rules/js.md:16`, ADR-0039:5: „ADR-0037 still holds for the other tables (Budget, Month plan)”).
- `components/BudgetMonthPicker.vue:54`: `border-border/60 bg-muted/40 … rounded-xl` zamiast panelu `card` + `border` + `rounded-lg`. `:64` przyciski `rounded-lg text-xs`.
- Linie między wierszami `border-border/60` zamiast `border-soft`: `MonthPlan.vue:725,787,839`, `MonthPlanItemRow.vue:66`. Wiersze mają `py-3` zamiast `--row-height` (34 px).
- `opacity-60` jako znak „nieaktualne”: `MonthPlan.vue:848`, `MonthPlanItemRow.vue:107`. Wzorzec zabrania przezroczystości dla stanów wyciszonych (README: „bez przezroczystości”); wyciszenie to `muted-foreground`.
- `MonthPaceMeter.vue:49` tor paska `bg-muted` zamiast `track`. `:62,69,74,77` tekst `text-sm/xs`.

Wpływ na użytkownika: Plan miesiąca wygląda jak stara aplikacja obok przestylowanego Budżetu i Operacji. Kwoty nie mają kroju mono i „zł” powtarza się w każdej komórce, więc kolumny groszy nie stoją w linii.

### C2. Brakujący współdzielony komponent: StatPanel zamiast jednej karty

- `pages/MonthPlan.vue:553-602`: jedna `Card` z etykietą Do wydania, kwotą `text-3xl`, akapitem podpowiedzi (:572-584) i pod kreską blokiem tempa (`MonthPaceMeter`, :586-601).
- Wzorzec (`preview.html:777-782`): `StatPanel` z 4 miarami: „Do wydania” (w edycji „Do wydania · podgląd na żywo”, podpis „dochody − wydatki − odkładanie”), „Dochody” (kolor `success`), „Wydatki” (podpis „rachunki wg faktu, bieżące wg wyższej”), „Tempo” (np. „31%”, podpis „upływ dni 5 z 30 (17%)”; w przyszłym miesiącu „—” i „miesiąc jeszcze się nie zaczął”). Bez paska tempa.
- `components/StatPanel.vue:3-19` istnieje (Budget, Dashboard przez `BudgetPlanSummaryStrip`). Przyjmuje `{label, value: string, caption?: string, toneClass?}` i 2–4 miary, bez slotu.
- Różnica przepisu: podpis w `StatPanel` to `meta` (11 px), a `ht-stat__sub` w `bundle.css` ma 13 px. Repo zostaje przy `meta`, bo precedens z `budget-atrament-ui/plan.md:274-278` już tak ustalił.
- Podpowiedź stanu (:572-584) przenosi się pod tytuł jako linia `meta`, tak jak w mockupie (`preview.html:761-762`).

Wpływ: odczyty miesiąca są rozrzucone w pionie, trzeba przewijać, a układ różni się od Budżetu i Dashboardu.

### C3. Brakujące warianty Badge: ręczna pigułka i kolory stanu

Warianty `ok`, `brand`, `neutral`, `warning`, `destructive-soft` istnieją (`components/ui/badge/index.ts:19-28`).

- `pages/MonthPlan.vue:519-527`: stan planu `secondary` (zatwierdzony) / `outline` (propozycja). Wzorzec (`preview.html:755,774`): „Zatwierdzony” `brand`, „Niezatwierdzony” `warning`, minione miesiące „Końcowy” zwykła (z ramką). Dziś miniony zatwierdzony miesiąc pokazuje „Zatwierdzony” (`stateLabel` :337-341), a tekstu „Końcowy” nie ma.
- `components/MonthPlanItemRow.vue:93-100`: „Wpłynęło” zbudowane ręcznie (`variant="outline" class="text-success border-success/40"`) zamiast `ok`. `:85-92` „Zapłacone” `secondary` zamiast `ok`. Wzorzec Badge: „Nie buduj pigułki ręcznie z utility”.
- `MonthPlanItemRow.vue:76-84`: „Jednorazowa” i „Poza planem” `outline`, „Zarchiwizowane” `secondary` → `neutral`.
- `pages/MonthPlan.vue:799`: licznik jednorazowych bez ruchu `secondary` → `neutral`.
- Wzorzec ma w tabeli kolumnę **Status** (`preview.html:763-772`): „Wpłynęło”/„czeka” (dochód), „Zapłacone”/„do zapłaty” (rachunek), „ponad plan” (`destructive-soft`) / „N% planu” (`neutral`) (bieżący). Dziś status stoi obok nazwy, a „czeka”, „do zapłaty”, „N% planu”, „ponad plan” nie istnieją. Danych nie brakuje: `is_settled`, `month_item_kind`, `type`, `is_over_plan`, `plan`/`actual` są w wierszu (`types/month-plan.ts:20-42`).

Wpływ: kolor stanu nie znaczy tego samego co w reszcie aplikacji, a status pozycji ginie przy nazwie.

### C4. Przypadkowa architektura: tempo, pasek miesięcy, pasek edycji, odkładanie

- **Tempo** (`MonthPlan.vue:586-601`, `MonthPaceMeter.vue:48-79`) siedzi w karcie Do wydania, a ostrzeżenie to zwykły `text-warning text-xs` (`MonthPaceMeter.vue:69-71`). Wzorzec: miara „Tempo” w `StatPanel` plus notatka `ht-note` (`warning-soft`, ikona) pod panelem, tylko gdy tempo wyprzedza upływ dni (`preview.html:783`, warunek :416). Tekst już jest: `pace_ahead` = „Wydatki bieżące wyprzedzają upływ dni o co najmniej 10 pkt proc.” (`lang/pl/ui.php:334`). Próg liczony całkowicie (`MonthPaceCalculator.php:55-62`, TS `month-pace-calculator.ts:136-150`). Repo nie ma komponentu `ht-note`; najbliższy jest `Alert variant="warning"` (`components/ui/alert/index.ts:16`), który zgodnie z `.ai/rules/js.md:20` (`bg-warning/10` lub `Alert variant="warning"`) jest regułą dla ostrzeżeń nieblokujących.
- **Pasek miesięcy** (`BudgetMonthPicker.vue:68-72`): kropka tylko przy bieżącym miesiącu (`bg-primary`). Wzorzec MonthStrip: kropka stanu każdego miesiąca (`muted-foreground` podsumowanie, `brand-text` zatwierdzony, `warning` propozycja), wybrany `primary` (`bundle.css` `ht-months`; mockup :751). Brak danych: patrz „Propsy”.
- **Pasek edycji** (`MonthPlanEditBar.vue:28-78`, użyty `MonthPlan.vue:868-881`): przyklejony u dołu, `bg-card`, kwota Do wydania, licznik niepoprawnych, „Anuluj” `outline`, „Zapisz zmiany”/„Zatwierdź” `primary`; pokazywany w trybie propozycji i zmiany. Wzorzec (`preview.html:775`, `bundle.css` `ht-editbar`): pas `primary` z `primary-foreground` pod paskiem miesięcy, „Edycja planu” (dziś tylko `aria-label`, `ui.php:367`), zdanie skutku, „Anuluj” `ghost`, „Zapisz zmiany” `brand`; tylko w trybie zmiany. „Zatwierdź” propozycji stoi w wierszu tytułu (`brand`). Repo nie ma wariantu Button `brand` (`components/ui/button/index.ts:11-27`).
- **Odkładanie** (`MonthPlan.vue:615-694`): osobna karta z `SegmentedControl`, `Input w-40`, podpowiedzią i ostrzeżeniem o dobieraniu. Wzorzec: ostatni wiersz grupy tabeli „Odkładam / dobieram” z komórką planu i statusem „zmniejsza Do wydania” (`preview.html:773,788`), bez przełącznika kierunku. Aplikacja ma tu więcej zachowania niż mockup (kierunek, ostrzeżenie `drawExceedsSpendableLiquidity`), więc przeniesienie do tabeli nie może go zgubić.
- **Dwie tabele** (`MonthPlan.vue:714-866`): osobne `ListSurface` „Dochody” i „Wydatki”. Wzorzec: jeden panel, wiersze grup `muted` „Dochody”, „Wydatki”, „Odkładam / dobieram”, kolumny „Kategoria | Rodzaj | Plan | Faktycznie | Odchylenie | Status” (`preview.html:784-789`). Aplikacja ma dodatki, których mockup nie pokazuje: plakietki jednorazowa/zarchiwizowana/poza planem, linia „W roku …”, „Zatwierdzono: …”, zwijane jednorazowe bez ruchu (:784-831), stopkę „Razem w roku” (:833-865).
- **Komórka planu** (`MonthPlanItemRow.vue:162-181`): shadcn `Input` 32 px, Inter, z `InputError` pod spodem. Wzorzec `ht-cell-input`: 28 px, mono 12 px do prawej, `radius-sm`, focus pierścień, błąd jako ramka `destructive` (`bundle.css`; `Field` README „Komórka planu w tabeli”). Repo nie ma takiej klasy.

Wpływ: ekran odzwierciedla kolejność dodawania funkcji (karta Do wydania, potem tempo, odkładanie, podsumowanie, sumy roku), a nie hierarchię: miary, ostrzeżenie, tabela.

### C5. Reguła agenta utrwala stary przepis `.section-label`

- `app.css:402-404`: `.section-label` = `text-muted-foreground text-xs font-medium tracking-wider uppercase`, czyli 12/16 px wersalikami. Uwaga: `change.md` podaje 11 px, a faktycznie to 12 px (`text-xs`). Prawie duplikat `label-caps` (`app.css:258-261`, 11/14, 0.06em).
- Użycia (cały `resources/js`, grep): `MonthPlan.vue:555` (etykieta nad miarą Do wydania), `:589` („Tempo”), `:617` (nagłówek panelu „Odkładanie”), `MonthPlanEditBar.vue:36` (etykieta Do wydania), `dev/MonthPlanSink.vue:179,188,197` (podpisy deweloperskie). Ten sam przepis jako utility bez klasy: `MonthSummaryCard.vue:32,104` (nagłówki sekcji).
- Reguła: `.ai/rules/js.md:56` wymienia `.section-label` wśród nazwanych klas do użycia. `.docs/design-system.md:19` też („etykieta sekcji `.section-label`”). ADR-0037 i `js.md:42` mówią, że wersaliki (`label-caps`) są tylko nad miarami i w sidebarze.

Wpływ: kolejny agent czyta regułę i wstawia etykietę wersalikami jako nagłówek panelu, odtwarzając dryf.

### C6. `MonthSummaryCard` ma własną tabelę (nowy zarzut z researchu)

- `components/MonthSummaryCard.vue:28-173`: `Card` z tytułem `text-xs uppercase tracking-wider` (:32), własna `<table class="text-sm">` (:38-99) z nagłówkami `text-xs font-medium` (:40-51), `border-border/60` (:60), `formatMoney` z „zł”, odchylenie bez koloru; `dl` odkładania (:102-158), „Dobrane od początku roku” (:160-167), podpowiedź (:169-171).
- Dane i konsumenci: `MonthSummary` (`types/month-plan.ts:44-61`), wysyłany tylko dla minionych zatwierdzonych miesięcy (`MonthPlanController.php:222`). Jedyny konsument: `MonthPlan.vue:604-607`.
- Wzorzec (`preview.html:752-759`): panel „Podsumowanie miesiąca”, meta „Liczone na żywo z operacji”, wiersze Dochody / Wydatki / Odkładanie × „Zatwierdzony | Końcowy | Faktycznie | Odchylenie”. Dla minionego miesiąca wzorzec nie pokazuje miar ani tabeli pozycji, a aplikacja pokazuje oba.
- Precedens konwersji: Budżet (`BudgetCategoryList.vue:197-214,372-374`, `BudgetAmountCell.vue:34-40`): `ListSurface`, siatka z nazwaną klasą, `table-header`, `amount`/`amount-sum`, stopka „Kwoty w zł”. `.list-surface-table*` (`app.css:447-469`) nie ma konsumentów w `resources/js`.
- Pułapka: odchylenia wydatków nie wolno barwić przez `amountToneClass` (dodatnie = przekroczenie, a funkcja dałaby zielony). Wiersze pozycji mają własną logikę (`MonthPlanItemRow.vue:126-140`).

Wpływ: miniony miesiąc ma trzeci, niezgodny wygląd tabeli i wersaliki jako nagłówek.

### C7. Kitchen sink nie renderuje widoku (nowy zarzut z researchu)

`resources/js/pages/dev/MonthPlanSink.vue` składa wycinki (importy :4-12): wiersze, pasek edycji, `SegmentedControl` z ogólnymi opcjami, stany puste. Nie importuje `BudgetMonthPicker`, `MonthPaceMeter`, `MonthSummaryCard`, `MonthYearSummary`, `ColumnHeaderHint`, `Collapsible`. Eksportowany `buildMonthPlanReading` (`month-plan-fixtures.ts:134-152`) nie ma konsumentów, a żadna fikstura nie ustawia `year_comparison`, `approved_minor_units`, `month_pace` ani `summary`. Typografia sinka sama jest sprzed Atramentu (`text-lg font-semibold`, `text-base font-medium`, `section-label`). Precedens: `BudgetKitchenSink.vue` z tablicą `stateMatrix` `{state, where}` (:215-244), listą na górze (:261-271), sekcjami `h2.section-title` z `aria-labelledby` i prawdziwymi komponentami listy.

Wpływ: bramka wizualna nie widzi większości regionów widoku, więc regres w karcie Do wydania, tempie czy podsumowaniu przejdzie niezauważony.

## Decisions

Fakty ustalone przez research są w Charges. Poniżej wybory, które należą do planu albo do użytkownika.

- **D1. Co pokazują miary Dochody i Wydatki.** Trzy drogi:
  1. Składniki Do wydania, jak w mockupie (podpisy `preview.html:777,779`). Wymaga, by `ToSpendCalculator.php` i `to-spend-calculator.ts` zwracały składniki obok wyniku netto, ze zmianą obu testów (`tests/Unit/ToSpendCalculatorTest.php`, `to-spend-calculator.test.ts`) zgodnie z AGENTS.md „Month logic lives twice”. Konsumenci TS kopii (`entry-to-spend-warning.ts`, `import-to-spend-warnings.ts`) muszą dostać ten sam wynik netto.
  2. Sumy planu (sumowanie `plan_minor_units` po sekcji, w edycji `parsedAmount`), z etykietą „Dochody (plan)” itp. Bez nowej logiki, ale Dochody − Wydatki − odkładanie ≠ Do wydania, więc podpis „dochody − wydatki − odkładanie” byłby fałszywy.
  3. Dwie miary zamiast czterech (Do wydania, Tempo).
  Rekomendacja researchu: 1, bo tylko ona daje równanie z podpisu. Rozszerzenie zwracanego kształtu nie zmienia reguły z `CONTEXT.md`, więc nie wymaga ADR domenowego.
- **D2. Kolor `brand` dla „Zatwierdzony” i przycisków „Zatwierdź” / „Zapisz zmiany”.** ADR-0035:3 i `.ai/rules/js.md:11`: `brand` = tożsamość i nawigacja, nigdy status. Ale `Budget.vue:94` już używa `brand` dla odznaki fazy „Realizacja”, a README systemu wymienia `brand` dla „Zatwierdź” i odznaki „Realizacja”; Badge README zabrania go tylko dla statusu pieniędzy. Stan planu to stan dokumentu jak faza budżetu, więc Badge `brand` ma precedens. Przycisk `brand` to nowy wariant `ui/button`: decyzja o drugiej akcji głównej w kolorze marki potrzebuje ADR-0042 (następny wolny numer; 0036 zarezerwowany przez `context/changes/period-report-by-category`).
- **D3. Gdzie stoi pasek edycji i „Zatwierdź”.** Mockup: pas `primary` pod paskiem miesięcy tylko w trybie zmiany, „Zatwierdź” w wierszu tytułu. Dziś pasek przyklejony u dołu w obu trybach z kwotą Do wydania. Przy długiej tabeli przyklejony pasek u dołu trzyma „Zapisz” w zasięgu; pas u góry nie. Do rozstrzygnięcia w planie (np. pas `primary` przyklejony u dołu).
- **D4. Jedna tabela z wierszami grup czy dwie.** Mockup łączy Dochody, Wydatki i Odkładanie w jeden panel. To zmienia `.month-plan-grid` (dziś 3–5 kolumn, `app.css:373-396`) na 6 kolumn z Statusem i przenosi odkładanie z karty do wiersza. Odkładanie traci wtedy miejsce na przełącznik kierunku, podpowiedź i ostrzeżenie o dobieraniu; trzeba je zachować (np. komórka planu przyjmuje kwotę ujemną jak w `Field` README: „Odkładanie przyjmuje też kwotę ujemną (dobieram)”, a ostrzeżenie idzie pod tabelę jako notatka).
- **D5. Kropki MonthStrip.** Nowy props (np. `monthStates: list<'summary'|'approved'|'proposal'|'none'>`). Rekomendacja: dodać w tej zmianie, bo koszt to pętla bez zapytań i jeden test `assertInertia`.
- **D6. „Dodaj operację” w wierszu tytułu.** Mockup ma tu akcję główną; aplikacja ma ją tylko w Operacjach (`OperationsPageActions.vue:98`). Rekomendacja: odroczyć (inny widok, dialogi operacji, skrót `N` to osobna zmiana według README mockupu).

## Detailed Findings

### Propsy kontrolera i stan 12 miesięcy

- `MonthPlanController::show` (`app/Http/Controllers/MonthPlanController.php:64-76`) wysyła `budget {id, year, phase}`, `viewedMonth`, `viewedMonthTiming` (`'past'|'current'|'future'`, :314-324), `currentMonth` (null poza rokiem budżetu) i `monthPlan` (null poza fazą realizacji; inaczej `monthPlanPayload()` :157-224). Gałąź bez budżetu :45-51.
- TS: `defineProps` w `MonthPlan.vue:46-56`, `MonthPlanReading` w `types/month-plan.ts:63-79`.
- Każdy props opisuje tylko oglądany miesiąc. Stan miesiąca liczy `MonthPlanProposal::reading($m)->state` (`app/Support/MonthPlanProposal.php:62`, memo :76-81) w pamięci: `forBudget()` (:40-60) ładuje `monthPlans.items`, `categories` i budżet poprzedniego roku stałą liczbą zapytań. Miniony miesiąc bez planu daje `'none'`, nigdy `'proposal'` (:107-109). Pętle po 12 miesiącach już istnieją: `MonthSummary.php:69-75`, `EntryToSpendInputs.php:64-79`.
- Mapowanie na kropki: miniony i `approved` → `summary` (to dokładnie warunek niepustego `MonthSummary::forMonth`, `MonthSummary.php:35`); nie miniony `approved` → `approved`; `proposal` → `proposal`; `none` → brak kropki.
- `BudgetMonthPicker` używa tylko `MonthPlan.vue:539-544`; jest na liście skanu `BUDGET_VIEW_FILES` w `resources/js/ui-contract.test.ts:22` i w `MonthPlanHardcodedUiTest.php:15`. Nazwa `BudgetMonthStrip.vue` z README wzorca nie istnieje (komponent usunięty w budget-atrament-ui).

### Miary: Do wydania, Dochody, Wydatki, Tempo

- Do wydania: odczyt `monthPlan.to_spend_minor_units`, w edycji `toSpendMinorUnits(items, setAside)` (`MonthPlan.vue:283-302`, `to-spend-calculator.ts:16-39`). Reguła: dochód i rachunek wg faktu, gdy jest kwota dodatnia, inaczej wg planu; bieżący `max(plan, actual)`. Funkcja zwraca jedną liczbę.
- Dochody/Wydatki: brak propsa i computed. Źródła: wiersze `income_items`/`expense_items` z `plan_minor_units`, `actual_minor_units` (`types/month-plan.ts:20-42`), `parsedAmount(row)` w edycji (`MonthPlan.vue:175-184`), `summary.*` tylko dla minionych (`MonthSummary.php:53-62`). `year_totals` to narastająco od początku roku, nie miesięcznie.
- Suma faktów z wierszy pochodzi z `MonthCategoryActuals` (po kategorii), a `summary.*.actual` z `BudgetMonthResult` (po typie operacji, `BudgetMonthResult.php:20-35`). Są równe, gdy typ operacji zawsze zgadza się z typem kategorii. Tego warunku research nie zweryfikował do końca (patrz Open Questions). Przy D1 wariant 1 problem znika, bo składniki liczy ten sam kalkulator co Do wydania.
- Tempo: `monthPlan.month_pace: MonthPace | null` (`types/month-plan.ts:3-11`; `{as_of, elapsed_day, days_in_month, pool_plan_minor_units, pool_actual_minor_units, percent, is_ahead}`), budowane w `MonthPace.php:82-119`, null dla przyszłego miesiąca lub stanu `none` (kontroler :214-216). Pula: wydatki bieżące, nie jednorazowe (`MonthPaceCalculator.php:20-37`). `percent` null przy planie ≤ 0. Podgląd w edycji: `month-pace-preview.ts:57-82`, wołany w `MonthPlan.vue:304-324`. Teksty: `lang/pl/ui.php:332-336` (`pace`, `pace_reading`, `pace_ahead`, `pace_none`, `pace_days`). Tekstu „miesiąc jeszcze się nie zaczął” w `lang/pl/ui.php` nie ma (grep „nie zaczął”, „not_started”: 0 trafień), więc plan musi dodać klucz.
- `StatPanel` przyjmuje stringi; tempo jako miara mieści się w `value` („31%”) i `caption` („upływ dni 5 z 30 (17%)”), więc nie trzeba rozszerzać API. Pasek tempa znika, zgodnie z mockupem.

### Macierz 7 stanów: czego brakuje w `/dev/month-plan`

Pokazane dziś (`MonthPlanSink.vue`): wiersze odczytu (:68-109), wiersze edycji (:111-124), tytuł z odznakami (:126-149), pasek edycji w obu trybach i z ujemnym Do wydania (:151-168), kontrakt `SegmentedControl` (:170-209), kontrakt siatki (:211-298), trzy stany puste bez akcji (:300-317), sekcje macierzy (:319-480): hover, focus, disabled (Zatwierdź z `invalid-count`, kind pending), error (komórka, kind error, set-aside `Input`, pasek z błędem serwera), loading (spinner w trybie zmiany).

Brakujące komórki (✗) i N/A:

| Region | Brakuje | N/A (powód) |
| --- | --- | --- |
| Pasek miesięcy | default, kropki stanu (nowe), hover, focus-visible, loading (`pendingMonth`, `aria-busy`, `BudgetMonthPicker.vue:15-16,36-43,53`) | disabled, error, empty: nie ma wyłączonego miesiąca; zmiana to nawigacja GET |
| „Zmień plan” | focus-visible | disabled: przycisk znika przy `!can_change` (`MonthPlan.vue:530-536`) |
| „Zatwierdź” | loading (spinner pokazany tylko w trybie zmiany) | — |
| „Zapisz zmiany” | disabled przy braku zmienionych wierszy (`submittedAmounts.length === 0`, `MonthPlan.vue:873-877`) | — |
| Pasek edycji | pozycja przyklejona (sink: nie przyklejony, :153-155) | — |
| Komórka planu | focus w wierszu (dziś tylko goły `Input`, :374-380), linia „Zatwierdzono: X” (brak `approved_minor_units` w fiksturach) | disabled: pole nie ma stanu wyłączonego |
| Odkładanie | default z prawdziwymi opcjami, odczyt „Odkładam X” (:654-669), błąd serwera `errors.set_aside_minor_units` (:676-678), ostrzeżenie o dobieraniu (:684-692) | — |
| Rodzaj | układ pod nazwą poniżej `sm` (widoczny tylko na 390 px) | — |
| Jednorazowe bez ruchu (`Collapsible`) | otwarte/zamknięte, focus, auto-otwarcie przy błędnej kwocie (`MonthPlan.vue:467-478`) | — |
| Pusta sekcja | w `ListSurface` z nagłówkiem (dziś luźny `<p>`, :314-316) | — |
| Stany puste strony | akcja „Przejdź do budżetu” (`MonthPlan.vue:490-496,504-510`) | — |
| Karta/miary Do wydania | wszystkie: default, ujemne, cztery warianty podpowiedzi, podgląd na żywo | hover, focus, disabled: nieinteraktywne |
| Tempo | w normie, przed upływem dni (`warning`), `percent === null` (`pace_none`), przyszły miesiąc | hover, focus, disabled, error: pasek `aria-hidden`, nieinteraktywny |
| Podsumowanie minionego miesiąca | cały `MonthSummaryCard`, „Do wydania (końcowe)”, `approved_past_hint` | — |
| Sumy roku | linia „W roku” i jej stan nieaktualny (`MonthPlanItemRow.vue:103-117`), stopka `MonthYearSummary` i stan nieaktualny (`MonthPlan.vue:833-865`), hover/focus `ColumnHeaderHint` (`<button>` z podpowiedzią, `ColumnHeaderHint.vue:19-23`) | — |
| Notatka zakresu rodzaju | opis `ListSurface` (`MonthPlan.vue:718-722`) | — |
| Tytuł miesiąca bez planu | wiersz tytułu bez odznaki (stan `none`) | — |

Stan `loading` całej strony: dane przychodzą z nawigacją Inertia, więc dla strony to N/A (jak w Budżecie, `BudgetKitchenSink.vue:215-244`); dla paska miesięcy i przycisków zapisu nie.

### Pliki do aktualizacji poza widokiem

- `.ai/rules/js.md:56` (usunąć `.section-label`, dodać nowe nazwane klasy), `:11` jeśli D2 zmieni regułę `brand`.
- `.docs/design-system.md:19` (`.section-label`, `.list-surface-table`), `:25` (kafelki `text-3xl` w widokach nieprzeniesionych: Plan miesiąca przestaje być wyjątkiem), `:96` (przepis zrzutu `/dev/month-plan`).
- `tests/Unit/MonthPlanHardcodedUiTest.php:7-18`: dopisać nowe pliki (np. komponent notatki, `MonthPlanSink.vue` jeśli ma być pilnowany). Test nie łapie `text-3xl`, `uppercase tracking-wider`, `rounded-2xl`, `border-border/60`, `opacity-60`; to kandydat na kontrolę z sekcji „Utrwal to” w `/10x-ui`.

## Code References

- `resources/js/pages/MonthPlan.vue:516` – tytuł bez `page-title`
- `resources/js/pages/MonthPlan.vue:519-527` – odznaka stanu `secondary`/`outline`
- `resources/js/pages/MonthPlan.vue:553-602` – karta Do wydania z tempem
- `resources/js/pages/MonthPlan.vue:615-694` – karta odkładania
- `resources/js/pages/MonthPlan.vue:714-866` – dwie tabele `ListSurface`, zwijane jednorazowe, stopka roku
- `resources/js/pages/MonthPlan.vue:868-881` – użycie `MonthPlanEditBar`
- `resources/js/components/MonthPlanItemRow.vue:76-100` – plakietki, ręczna „Wpłynęło”
- `resources/js/components/MonthPlanItemRow.vue:162-208` – komórka planu, kwoty `text-sm`
- `resources/js/components/MonthPlanEditBar.vue:28-78` – pasek edycji
- `resources/js/components/BudgetMonthPicker.vue:50-74` – pasek miesięcy
- `resources/js/components/MonthPaceMeter.vue:48-79` – pasek i tekst tempa
- `resources/js/components/MonthSummaryCard.vue:28-173` – podsumowanie minionego miesiąca
- `resources/js/components/StatPanel.vue:3-45` – panel miar
- `resources/js/components/ui/badge/index.ts:19-28` – warianty Badge
- `resources/js/components/ui/button/index.ts:11-27` – warianty Button (bez `brand`)
- `resources/css/app.css:245-283` – nazwane style typografii
- `resources/css/app.css:373-404` – `.month-plan-grid`, `.column-header`, `.section-label`
- `resources/js/lib/to-spend-calculator.ts:16-39` – kalkulator Do wydania (tylko wynik netto)
- `app/Support/MonthPlanProposal.php:40-109` – stan miesiąca w pamięci
- `app/Http/Controllers/MonthPlanController.php:64-76,157-224` – propsy widoku
- `resources/js/pages/dev/MonthPlanSink.vue` – kitchen sink
- `resources/js/pages/dev/BudgetKitchenSink.vue:215-271` – precedens macierzy stanów
- `tests/Unit/MonthPlanHardcodedUiTest.php:7-26` – lista plików i wzorce skanu

## Architecture Insights

- Kontrakt systemu projektowego jest kompletny po stronie wartości (tokeny i style w `app.css`, ADR-0035, ADR-0037), więc ta zmiana to wariant „istniejący system”: bez nowych tokenów kolorów. Nowe mogą być tylko klasy komponentów (notatka, komórka planu, pas edycji, kropka miesiąca, siatka podsumowania) i ewentualnie wariant Button `brand` (D2).
- Test literałów (`MonthPlanHardcodedUiTest`) pilnuje kolorów i wartości arbitralnych, ale nie stylów sprzed Atramentu. Dlatego widok przeszedł wcześniejsze zmiany Atramentu z czystym skanem i starą typografią.
- Para To Spend (AGENTS.md) jest jedynym miejscem, gdzie D1 wariant 1 dotyka logiki; reszta zmiany to warstwa widoku plus jeden props (D5).

## Historical Context (from prior changes)

- `context/archive/2026-11-03-month-plan-ui-contract/plan.md:187,243` – utworzenie `.section-label` i `MonthPlanHardcodedUiTest`; `research.md:71` liczył 8 kopii przepisu, przeniesiono 3 z `MonthPlan.vue` (MonthSummaryCard pominięty). Trafne co do liczby w tamtym czasie; dziś 4 użycia klasy w widoku plus 2 kopie w `MonthSummaryCard`.
- `context/archive/2026-11-03-month-plan-ui-contract/plan.md:384-400` – udokumentowana lista stanów sinka; nadal aktualna dla wierszy i paska edycji, nie obejmuje regionów dodanych później (podsumowanie, tempo, sumy roku).
- `context/archive/2026-11-03-month-summary/plan.md:206-229` – `MonthSummaryCard` jako `Card` z nagłówkiem `text-xs uppercase`: decyzja stylistyczna sprzed Atramentu, bez ADR.
- `context/archive/2026-11-05-budget-atrament-ui/plan.md:49-50` – StatPanel i typografia innych widoków odłożone do „własnego /10x-ui”; `MonthPaceMeter` bez zmian w tamtej zmianie. To notatka o zakresie, nie zakaz.
- `context/archive/2026-11-05-budget-atrament-ui/plan.md:274-278` – przepis `StatPanel` (2–4 miary, `label-caps`, `kpi-lg`/`kpi`, `meta`, `amountToneClass`, `formatMoney` z „zł”).
- `.docs/adr/0039*.md:5` – tabele Budżetu i Planu miesiąca trzymają jednostkę w stopce.
- ADR Atramentu: 0035, 0037, 0038, 0039, 0040, 0041. Najwyższy 0041; 0036 zarezerwowany; następny wolny 0042.

## Related Research

- `context/archive/2026-11-05-budget-atrament-ui/research.md` – ten sam audyt dla Budżetu (format `## Charges`, decyzje o `StatPanel`, `BudgetMonthPicker`).
- `context/archive/2026-11-03-month-plan-ui-contract/research.md` – pierwszy kontrakt UI planu miesiąca.

## Open Questions

- Czy typ operacji zawsze równa się typowi jej kategorii (warunek równości sum faktów z wierszy i `BudgetMonthResult`). Nie zweryfikowano `ManageFinancialOperation`. Znaczenie tylko przy D1 wariant 2.
- Wartości `--space-*` z `bundle.css` pochodzą z `tokens.css` artefaktu, którego nie otwierano; repo ma własną skalę Tailwind i precedens Budżetu, więc to nie blokuje planu.
