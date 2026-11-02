# S-08: Ostrzeżenia przy operacjach liczone z planu — Plan Brief

> Full plan: `context/changes/plan-based-entry-warnings/plan.md`
> Research: `context/changes/plan-based-entry-warnings/research.md`

## What & Why

Gdy miesiąc daty operacji ma plan miesiąca, także niezatwierdzoną propozycję, ręczny formularz i wiersz podglądu Importu ostrzegają, że po tej operacji „do wydania” będzie ujemne i niższe niż przed nią. Komunikat podaje kwotę, np. „Po tej operacji do wydania wyniesie −130,00 zł (listopad 2026).” Miesiące bez planu zachowują ostrzeżenia `Pozostało w miesiącu`. Tak każe FR-014; FR-017 wymaga, żeby nic nie blokowało zapisu i nie dochodził żaden krok.

## Starting Point

Dziś ostrzeżenie miesiąca to tylko `Pozostało w miesiącu`: wydatek w kategorii równomiernej w `ExpenseFormDialog` i zaznaczone wiersze wydatków w podglądzie Importu. Liczy je klient w TS. S-01 dał wzór „do wydania” (`ToSpendCalculator` z kopią TS) i odczyty planu (`MonthPlanProposal`, `MonthCategoryActuals`), ale czyta je tylko strona „Plan miesiąca”. Serwerowy `ImportMonthRemainingWarningAnnotator` liczy flagi, których UI nie czyta.

## Desired End State

Każdy wpis dochodu, wydatku albo zwrotu, w każdej kategorii, jest sprawdzany według miesiąca swojej daty. W miesiącu z planem ostrzega z planu, a ostrzeżenia `Pozostało w miesiącu` znikają. W miesiącu bez planu wszystko działa jak dziś. Edycja sprawdza stary i nowy miesiąc. Wiersze Importu kumulują się w kolejności pliku. Martwy annotator i jego test nie istnieją, a FR-TRX-08, `js.md:24` i `CONTEXT.md` opisują nowe ostrzeżenie.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Które wpisy ostrzegają | Każdy dochód, wydatek i zwrot, w każdej kategorii (także jednorazowej), w obu formularzach i w Imporcie; edycja sprawdza stary i nowy miesiąc | Wzór „do wydania” spada także od pierwszego dochodu mniejszego od planu i od wydatku jednorazowego, a FR-017 mówi o samej liczbie | Plan |
| Warunek | `po < 0` i `po < przed` | Ostrzega tylko wtedy, gdy ten wpis pogarsza miesiąc; wydatek Running w granicach planu przy ujemnym miesiącu nie hałasuje | Plan |
| Treść | Zdanie z kwotą „do wydania” po operacji i miesiącem, w liście „Uwaga” | Użytkownik widzi skalę bez otwierania planu, a tekst pasuje i do przejścia przez zero, i do pogłębienia | Plan |
| Kolor | `<Alert variant="warning">` | Reguła `.ai/rules/js.md:24` dla ostrzeżeń bez blokady | Research |
| Martwy annotator | Usunąć z jego testem i polami `ImportRow`; asercję `monthRemainingContext` przenieść do `CsvImportTest` | Usuwa czwartą kopię logiki miesiąca, zanim obok powstanie logika z planu | Plan |
| Kumulacja w Imporcie | Narastająco w kolejności indeksu wiersza, per miesiąc daty | Ten sam wzorzec co dziś; każdy wiersz pokazuje własny skutek (PRD: „każdy wiersz według miesiąca swojej daty”) | Plan |
| Gdzie liczyć | Serwer daje surowe wejścia (`entryToSpend`), klient liczy w czystych funkcjach TS z testami | Podgląd musi reagować przed zapisem (warunek `AGENTS.md`), a wzór ma już kopię TS | Research |
| Zbiór miesięcy | Miesiące budżetów w wykonaniu z planem (stan ≠ `none`) do dziś włącznie | Daty operacji nie mogą być przyszłe; zapisane miesiące mogą mieć luki | Research |

## Scope

**In scope:**
- `MonthCategoryActuals::positiveCount` i `forMonths` (jedno zapytanie na budżet)
- `EntryToSpendInputs` i prop `entryToSpend` na stronach Operacje i podgląd Importu
- `entry-to-spend-warning.ts` i `import-to-spend-warnings.ts` z testami
- `ExpenseFormDialog`, `IncomeFormDialog`, `OperationsImportPreview`, `ImportPreviewRow`
- usunięcie annotatora; klucz `operations.to_spend_negative_warning`; FR-TRX-08, `.docs/prd.md:345`, `js.md:24`, `CONTEXT.md`

**Out of scope:**
- zmiany wzoru „do wydania” i Month Remaining
- ostrzeżenia przy przelewie własnym, ruchu kapitału i odsetkach w Imporcie (odsetki tylko zasilają kumulację), usuwanie operacji
- stały blok „przed → po”, jakakolwiek blokada albo potwierdzenie
- ADR-0033, `design-system.md`, FR-REP-13, Dashboard (S-06/S-07)

## Architecture / Approach

`EntryToSpendInputs::forUser` składa dla każdego budżetu w wykonaniu kategorie (`type`, `month_item_kind`) i miesiące z planem z pozycjami `{plan, actual, positive_amount_count}`, tak jak strona planu. Klient zamienia stan formularza albo zaznaczonych wierszy na zmiany `add`/`remove` w (kategoria, data). `previewToSpendWarnings` liczy „przed” i „po” każdego dotkniętego miesiąca z planem przez `toSpendMinorUnits`. Obecność miesiąca w `entryToSpend` rozstrzyga, czy pokazać ostrzeżenie z planu, czy stare `Pozostało w miesiącu`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Wejścia serwera i usunięcie annotatora | Prop `entryToSpend` (PHP i typy TS), `forMonths`, `positiveCount`, brak martwego kodu | Liczba zapytań strony; utrata asercji `monthRemainingContext` przy usuwaniu testu |
| 2. Czysta logika TS | `previewToSpendWarnings`, `monthHasPlan`, klucz tłumaczenia, testy | Niemonotoniczność wzoru (Bill, dochód) i licznik przy `remove` |
| 3. Ręczna operacja | Ostrzeżenie w obu formularzach, stary i nowy miesiąc, wygaszanie Month Remaining | Fałszywe ostrzeżenia przy niepełnym formularzu |
| 4. Import i dokumenty | Kumulacja per wiersz, wygaszanie Month Remaining w wierszu, FR-TRX-08, `js.md`, `CONTEXT.md` | Rozjazd z dzisiejszą kumulacją Month Remaining |

**Prerequisites:** S-01 zakończony (jest na `main`); dla testów PostgreSQL i podglądu działający stack Sail i kroki „Fresh checkout or worktree” z `AGENTS.md`.
**Estimated effort:** ~2–3 sesje w 4 fazach (fazy 1–2 bez UI, 3–4 z ręcznym potwierdzeniem w podglądzie).

## Open Risks & Assumptions

- Zakładamy, że daty operacji pozostaną nieprzyszłe (`before_or_equal:today`). Gdyby to się zmieniło, zbiór miesięcy w `entryToSpend` trzeba rozszerzyć o przyszłe propozycje.
- Set-aside jest dziś równe 0 (S-02). Gdy S-02 doda je do wzoru, `entryToSpend` musi podawać je per miesiąc.
- Kumulacja w kolejności wiersza może pokazać ostrzeżenie, które dalszy wiersz (np. wypłata) znosi. To świadomy wybór.

## Success Criteria (Summary)

- Wpis, który pogarsza ujemne albo sprowadza poniżej zera „do wydania” miesiąca z planem, pokazuje ostrzeżenie z kwotą zgodną z późniejszym odczytem strony „Plan miesiąca”, a zapis działa bez dodatkowego kroku.
- Miesiące bez planu zachowują dzisiejsze ostrzeżenia `Pozostało w miesiącu`.
- Pełna bramka CI na PostgreSQL i `npm test` przechodzą; martwy annotator nie istnieje.
