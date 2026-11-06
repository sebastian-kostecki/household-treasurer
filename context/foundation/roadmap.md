---
project: 'Household Treasurer'
version: 2
status: draft
created: 2026-11-02
updated: 2026-11-04
prd_version: 2
main_goal: market-feedback
top_blocker: decisions
milestone_id: period-reports-and-irregular-expenses
milestone_seq: 2
milestone_status: open
---

# Roadmap: Household Treasurer

> Derived from `context/foundation/prd-v2.md` (v2) + auto-researched codebase baseline.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.
> Identyfikatory kontynuują numerację M-1 (F-01, S-01…S-10 są w `## Done`), więc M-2 zaczyna od F-02 i S-11.

## Milestone

**M-2: Raporty okresu i wydatki nieregularne** — Status: open

- **Intent:** Użytkownik odczytuje w aplikacji, ile i na co wydaje oraz zarabia w miesiącu i w roku, także w kolejnych miesiącach roku. Wydatek nieregularny (np. prąd) ma w planie miesiąca stałą miesięczną kwotę i widoczne „zebrane”, bez odkładania pieniędzy na osobne konto.
- **Source materials:** `context/foundation/prd-v2.md` (v2), z blokiem przekazania dla mapy drogowej z `context/foundation/shape-notes.md`.
- **Done when:** każdy element F-NN i S-NN poniżej ma status `done`, a użytkownik przeszedł pięć kroków głównego kryterium sukcesu z PRD v2.
- **Scope anchors:** FR-001 do FR-014, US-01 do US-04.

## Vision recap

Aplikacja nie odpowiada dziś na pytanie, ile i na co wydaję oraz zarabiam w wybranym okresie, ani jak to się zmienia w czasie, więc do analizy wstecz użytkownik wraca do arkusza. Raporty mają sens tylko na uporządkowanej liście kategorii, dlatego porządek kategorii idzie razem z nimi. Wydatki płacone kilka razy w roku (prąd, ubezpieczenie auta, wakacje) dostają w planie miesiąca stałą miesięczną kwotę i „zebrane” zamiast ręcznego odkładania na inne konto.

## North star

**S-11: użytkownik może otworzyć „Raporty”, wybrać miesiąc albo rok i zobaczyć przychody, wydatki, wynik oraz kategorie z kwotą i udziałem %** — ten odczyt na ~1000 prawdziwych operacjach 2026 najszybciej pokaże, gdzie pieniądze płyną inaczej, niż użytkownik sądził, a to jest cel kolejności tego kamienia milowego.

> Gwiazda przewodnia (north star) to najmniejszy wycinek od początku do końca, którego dostarczenie dowodzi, że zmiana działa. Stoi tak wcześnie, jak pozwalają warunki wstępne, bo reszta ma sens tylko wtedy, gdy on działa.

## At a glance

| ID   | Change ID                         | Outcome (user can …)                                                                                   | Prerequisites | PRD refs                                             | Status   |
| ---- | --------------------------------- | ------------------------------------------------------------------------------------------------------ | ------------- | ---------------------------------------------------- | -------- |
| F-02 | irregular-expense-domain-decisions | (foundation) ADR i wpisy w `CONTEXT.md` opisują wydatek nieregularny, „zebrane” i regułę końca roku     | —             | Constraints & Compatibility, Business Logic Changes  | ready    |
| S-11 | period-report-by-category         | użytkownik może zobaczyć przychody, wydatki, wynik i kategorie z udziałem % za wybrany miesiąc albo rok | —             | US-01, FR-001, FR-002, FR-003                        | planning |
| S-12 | category-trend-by-month           | użytkownik może zobaczyć kwoty kategorii i sumy w kolejnych miesiącach roku                            | S-11          | US-02, FR-005                                        | proposed |
| S-13 | report-to-operations              | użytkownik może przejść z kategorii w raporcie do jej operacji w wybranym okresie                      | S-11          | FR-004                                               | proposed |
| S-14 | irregular-expense-in-month-plan   | użytkownik może oznaczyć wydatek jako nieregularny i widzieć jego miesięczną kwotę oraz „zebrane”       | F-02          | US-03, US-04, FR-010, FR-011, FR-012, FR-014         | proposed |
| S-15 | irregular-collected-year-end      | użytkownik może zobaczyć, że niewydane „zebrane” na koniec roku obniża plan roczny kategorii           | F-02, S-14    | FR-011, FR-012                                       | blocked  |
| S-16 | category-identity-across-years    | użytkownik może zmienić nazwę kategorii i ręcznie powiązać ją z kategorią z innego roku bez utraty ciągłości | —        | FR-007, FR-008, FR-009, FR-013, FR-014               | ready    |
| S-17 | year-over-year-comparison         | użytkownik może porównać okres raportu z tym samym okresem rok wcześniej                               | S-11, S-16    | FR-006                                               | proposed |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                    | Chain                       | Note                                                                                   |
| ------ | ------------------------ | --------------------------- | -------------------------------------------------------------------------------------- |
| A      | Raporty                  | `S-11` → `S-12` → `S-13`    | Najszybszy odczyt na prawdziwych danych; `S-12` i `S-13` są od siebie niezależne.       |
| B      | Wydatki nieregularne     | `F-02` → `S-14` → `S-15`    | Jedyna zmiana reguły „do wydania”; niezależny od A.                                     |
| C      | Kategoria przez lata     | `S-16` → `S-17`             | Elementy opcjonalne (nice-to-have); `S-17` dołącza do A przy `S-11`.                    |

## Baseline

What's already in place in the codebase as of `2026-11-04` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — strony Inertia/Vue w `resources/js/pages`. Nie ma biblioteki wykresów (AGENTS.md wymaga ADR przed jej dodaniem) ani strony „Raporty”.
- **Backend / API:** present — trasy sesyjne w `routes/web.php`. Strona Operacje filtruje już po nazwie i typie kategorii oraz po `date_from` / `date_to` (`app/Http/Requests/IndexOperationsRequest.php`).
- **Data:** present — 20 migracji, PostgreSQL. `MonthItemKind` ma tylko `bill` i `running`. Odczyty `BudgetMonthResult`, `CategoryExecutionMetrics` i `MonthCategoryActuals` są w `app/Support`. Snapshot jest w wersji 5. `DemoHouseholdSeeder` tworzy tylko bieżący rok; mocka 2025 nie ma.
- **Auth:** present — Fortify, 2FA, passkeys, polityki w `app/Policies`, bez samodzielnej rejestracji.
- **Deploy / infra:** present — `docker-compose.prod.yml`, ręczne `scripts/deploy.sh`; CI tylko testuje.
- **Observability:** partial — log plikowy, `/up`, `backup:monitor`. Wzorem testu wydajności raportu jest `tests/Feature/Nfr/NfrPerf01Test.php`.

Różnice względem dokumentów, istotne dla wycinków: Align działa dla pojedynczej kategorii, ale Year Close nie jest zaimplementowany, więc reguła końca roku z PRD nie ma dziś mechanizmu, który by ją uruchomił. Lata łączy nazwa i typ kategorii w kilku miejscach (kopiowanie budżetu, podpowiedzi Importu, porównanie planów miesięcy z rokiem, Earmark).

## Foundations

### F-02: Decyzje domenowe wydatku nieregularnego

- **Outcome:** (foundation) nowy ADR i wpisy w `CONTEXT.md` opisują trzeci rodzaj pozycji planu miesiąca („nieregularny”), „zebrane”, jego wpływ na „do wydania” i Propozycję planu oraz regułę końca roku.
- **Change ID:** irregular-expense-domain-decisions
- **PRD refs:** Constraints & Compatibility (Kompatybilność wsteczna), Business Logic Changes, Rozstrzygnięcia domenowe
- **Unlocks:** S-14 (AGENTS.md i PRD wymagają ADR oraz wpisów w `CONTEXT.md` przed zmianą reguły To Spend), S-15 (rozstrzyga niewiadomą o mechanizmie końca roku).
- **Prerequisites:** —
- **Parallel with:** S-11, S-12, S-13, S-16, S-17
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Stoi przed kodem, bo zmienia regułę To Spend i dodaje termin obok Month Item Kind; musi też jasno powiedzieć, czym „zebrane” różni się od Set-aside, Reserve i Earmark.
- **Status:** ready

## Slices

### S-11: Raport okresu według kategorii

- **Outcome:** użytkownik może zobaczyć przychody, wydatki, wynik i kategorie z udziałem % za wybrany miesiąc albo rok
- **Change ID:** period-report-by-category
- **PRD refs:** US-01, FR-001, FR-002, FR-003
- **Prerequisites:** —
- **Parallel with:** F-02, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Biblioteka wykresów czy wykres bez biblioteki? Decyzja wymaga ADR według AGENTS.md. — Owner: user. Block: no.
- **Risk:** Tu powstaje widok raportów i decyzja o wykresach, z których korzysta reszta strumienia A. Sumy muszą być co do grosza równe wykonaniu w budżecie rocznym i Wynikowi miesiąca, a raport roku musi się otwierać w 2 s przy 10 000 operacji.
- **Status:** planning

### S-12: Trend kategorii w miesiącach roku

- **Outcome:** użytkownik może zobaczyć kwoty kategorii i sumy w kolejnych miesiącach roku
- **Change ID:** category-trend-by-month
- **PRD refs:** US-02, FR-005
- **Prerequisites:** S-11
- **Parallel with:** S-13, F-02, S-14, S-15, S-16, S-17
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Wynik każdego miesiąca musi równać się Wynikowi miesiąca bez zapytań w pętli po miesiącach (Sloppy). Niepełny bieżący miesiąc i skoki wydatków nieregularnych łatwo odczytać jako alarm.
- **Status:** proposed

### S-13: Z raportu do operacji kategorii

- **Outcome:** użytkownik może przejść z kategorii w raporcie do jej operacji w wybranym okresie
- **Change ID:** report-to-operations
- **PRD refs:** FR-004
- **Prerequisites:** S-11
- **Parallel with:** S-12, F-02, S-14, S-15, S-16, S-17
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Element opcjonalny (nice-to-have). Strona Operacje filtruje już po kategorii i datach, więc ryzyko to tylko rozjazd między tym, co raport liczy (zwroty netto, bez transferów własnych), a tym, co lista pokazuje.
- **Status:** proposed

### S-14: Wydatek nieregularny w planie miesiąca

- **Outcome:** użytkownik może oznaczyć wydatek jako nieregularny i widzieć jego miesięczną kwotę oraz „zebrane” w planie miesiąca i w Podsumowaniu miesiąca, a płatność najpierw zmniejsza „zebrane”
- **Change ID:** irregular-expense-in-month-plan
- **PRD refs:** US-03, US-04, FR-010, FR-011, FR-012, FR-014
- **Prerequisites:** F-02
- **Parallel with:** S-11, S-12, S-13, S-16, S-17
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Zbieranie i płatność to jedna reguła, której nie da się dostarczyć osobno bez podwójnego obniżenia „do wydania” w miesiącu płatności. Zmiana obejmuje dwie kopie kalkulatorów (PHP i TS), podglądy wpisu i Importu oraz snapshot w wersji 6. Budżet roczny i minione miesiące nie mogą się zmienić.
- **Status:** proposed

### S-15: „Zebrane” na koniec roku

- **Outcome:** użytkownik może zobaczyć, że niewydane „zebrane” na koniec roku obniża plan roczny kategorii i przechodzi do wyniku roku
- **Change ID:** irregular-collected-year-end
- **PRD refs:** FR-011, FR-012
- **Prerequisites:** F-02, S-14
- **Parallel with:** S-11, S-12, S-13, S-16, S-17
- **Blockers:** —
- **Unknowns:**
  - Co uruchamia obniżenie planu na koniec roku, skoro Year Close nie istnieje: akcja użytkownika w stylu Align, automat po 31 grudnia czy tylko odczyt? Rozstrzyga F-02. — Owner: user. Block: yes.
- **Risk:** To jedyny wyjątek od zasady „budżet roczny bez zmian” (FR-012). Stoi osobno, bo wymaga mechanizmu, którego w kodzie nie ma, a 2026 kończy się wcześnie w życiu tej funkcji.
- **Status:** blocked

### S-16: Kategoria rozpoznawana między latami

- **Outcome:** użytkownik może zmienić nazwę kategorii i ręcznie powiązać ją 1:1 z kategorią z innego roku, a nowy budżet kopiuje kategorie razem z powiązaniem
- **Change ID:** category-identity-across-years
- **PRD refs:** FR-007, FR-008, FR-009, FR-013, FR-014
- **Prerequisites:** —
- **Parallel with:** F-02, S-11, S-12, S-13, S-14, S-15
- **Blockers:** —
- **Unknowns:**
  - Gdzie użytkownik widzi i poprawia powiązanie startowe ustawione po nazwie i typie? — Owner: user. Block: no.
- **Risk:** Element opcjonalny (nice-to-have). Zmienia termin oznaczony _Avoid_ w `CONTEXT.md` („a Category identity that spans years”), więc ADR musi być przed kodem. Łączenie po nazwie i typie działa w kilku miejscach, w tym w podpowiedziach Importu, których trafność trzeba zmierzyć przed i po zmianie; porządkowanie kategorii w danych kończy się przed pomiarem „przed”. Snapshot podnosi też S-14, więc numery wersji trzeba uzgodnić. Tu powstaje też mock budżetu 2025 z operacjami (tylko dev), bo to pierwszy wycinek, który potrzebuje dwóch lat.
- **Status:** ready

### S-17: Rok do roku

- **Outcome:** użytkownik może porównać okres raportu z tym samym okresem rok wcześniej
- **Change ID:** year-over-year-comparison
- **PRD refs:** FR-006
- **Prerequisites:** S-11, S-16
- **Parallel with:** F-02, S-12, S-13, S-14, S-15
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Element opcjonalny (nice-to-have). Stoi po S-16, żeby porównanie nie powstało na łączeniu po nazwie, które S-16 zastępuje. Raport pokazuje nazwę kategorii z najnowszego roku w okresie.
- **Status:** proposed

## Backlog Handoff

| Roadmap ID | Change ID                          | Suggested issue title                                             | Ready for `/10x-plan` | Notes                                           |
| ---------- | ---------------------------------- | ----------------------------------------------------------------- | --------------------- | ----------------------------------------------- |
| F-02       | irregular-expense-domain-decisions | ADR i `CONTEXT.md`: wydatek nieregularny, „zebrane”, koniec roku   | yes                   | Run `/10x-plan irregular-expense-domain-decisions` |
| S-11       | period-report-by-category          | Raporty: przychody, wydatki, wynik i kategorie za miesiąc albo rok | yes                   | Run `/10x-plan period-report-by-category`       |
| S-12       | category-trend-by-month            | Raporty: trend kategorii w miesiącach roku                         | no                    | Czeka na S-11                                   |
| S-13       | report-to-operations               | Raporty: przejście z kategorii do operacji                         | no                    | Czeka na S-11                                   |
| S-14       | irregular-expense-in-month-plan    | Wydatek nieregularny i „zebrane” w planie miesiąca                 | no                    | Czeka na F-02                                   |
| S-15       | irregular-collected-year-end       | Niewydane „zebrane” na koniec roku                                 | no                    | Zablokowane: mechanizm końca roku (F-02)        |
| S-16       | category-identity-across-years     | Kategoria rozpoznawana między latami                               | yes                   | Opcjonalne; Run `/10x-plan category-identity-across-years` |
| S-17       | year-over-year-comparison          | Raporty: porównanie rok do roku                                    | no                    | Czeka na S-11 i S-16                            |

This table is the clean handoff to Jira/Linear or any MCP-backed backlog. Include one row for every `F-NN` and `S-NN`. It should be compact enough to copy into issues, but it must not duplicate the detailed roadmap body.

## Open Roadmap Questions

Brak otwartych pytań. Rozstrzygnięte przez użytkownika 2026-11-04:

1. **Kiedy uporządkować listę kategorii w danych** — po pierwszym odczycie raportu (S-11), przed rozpoczęciem S-16. Pierwszy raport na obecnych danych pokaże, co poprawić, a porządkowanie kończy się przed pomiarem trafności podpowiedzi Importu „przed zmianą” w S-16, więc oba pomiary (FR-013) idą na tych samych danych.

## Parked

- **Grupy kategorii i domyślna lista kategorii** — Why parked: PRD §Non-Goals; lista kategorii to dane użytkownika.
- **Porządkowanie listy kategorii** — Why parked: PRD „Poza aplikacją”; praca na danych użytkownika, nie wycinek.
- **Oznaczanie wyjazdów (tagi ponad kategorię)** — Why parked: PRD §Non-Goals; koszt wakacji to kategoria Wakacje.
- **Własny zakres dat i eksport raportu** — Why parked: PRD §Non-Goals; okres to miesiąc albo rok, bez CSV i PDF.
- **Pełna dostępność wykresu** — Why parked: PRD §Non-Goals; dostępną formą tych samych danych jest tabela.
- **Wydruk raportu** — Why parked: PRD §Non-Goals, spójnie z brakiem eksportu.
- **Podział i scalanie kategorii między latami** — Why parked: PRD FR-009 (Socratic); powiązanie tylko 1:1, podział zrywa porównanie.
- **Year Close i Reserve** — Why parked: niezaimplementowane na `main` (AGENTS.md) i spoza PRD v2; S-15 nie buduje ich przy okazji.
- **Śledzenie błędów i metryki** — Why parked: obserwowalność jest częściowa, ale PRD v2 jej nie wymaga.

## Milestone History

- **M-1: Kontrola miesiąca bez arkusza** (`month-control-without-spreadsheet`) — closed 2026-11-03. Wszystkie elementy F-01 i S-01 do S-10 mają status `done`; warunek „pełny miesiąc w aplikacji” (kryterium sukcesu PRD) do potwierdzenia w użyciu.

## Done

(Pusta przy pierwszym wygenerowaniu. Wpisy dodaje `/10x-archive`.)

- **F-01: (foundation) nowy ADR zastąpił ADR-0024 w części o „drugim budżecie miesięcznym”, a nowe terminy oraz progi powiadomień o wadze `warning` są w `CONTEXT.md`.** — Archived 2026-11-02 → `context/archive/2026-11-02-month-plan-domain-decisions/`. Lesson: —.
- **S-01: użytkownik może zobaczyć propozycję planu, poprawić ją, zatwierdzić i odczytać „do wydania”** — Archived 2026-11-02 → `context/archive/2026-11-02-month-plan-and-to-spend/`. Lesson: —.
- **S-02: użytkownik może wpisać w planie kwotę „odkładam / dobieram” i zobaczyć jej wpływ na „do wydania”** — Archived 2026-11-02 → `context/archive/2026-11-02-set-aside-and-draw/`. Lesson: —.
- **S-03: użytkownik może zmienić zatwierdzony plan w trakcie miesiąca** — Archived 2026-11-02 → `context/archive/2026-11-02-approved-plan-changes/`. Lesson: —.
- **S-04: użytkownik może zobaczyć plan i faktyczną kwotę każdej pozycji oraz kategorie jednorazowe** — Archived 2026-11-02 → `context/archive/2026-11-02-month-items-plan-vs-actual/`. Lesson: —.
- **S-05: użytkownik może zobaczyć tempo wydatków względem upływu dni miesiąca** — Archived 2026-11-03 → `context/archive/2026-11-03-month-spending-pace/`. Lesson: —.
- **S-06: użytkownik może odczytać „do wydania” na Dashboardzie i stronie Budżet, także w miesiącu bez planu** — Archived 2026-11-02 → `context/archive/2026-11-02-to-spend-replaces-remaining/`. Lesson: —.
- **S-07: użytkownik może zobaczyć na Dashboardzie powiadomienia związane z planem miesiąca** — Archived 2026-11-03 → `context/archive/2026-11-03-month-plan-notices/`. Lesson: —.
- **S-08: użytkownik może zobaczyć przy operacji i w podglądzie Importu ostrzeżenia liczone z planu** — Archived 2026-11-03 → `context/archive/2026-11-02-plan-based-entry-warnings/`. Lesson: —.
- **S-09: użytkownik może zobaczyć podsumowanie miesiąca, w tym odkładanie zamierzone i realne** — Archived 2026-11-03 → `context/archive/2026-11-03-month-summary/`. Lesson: —.
- **S-10: użytkownik może porównać plany miesięcy z budżetem rocznym** — Archived 2026-11-03 → `context/archive/2026-11-03-month-plans-vs-year/`. Lesson: —.
