---
project: 'Household Treasurer'
version: 4
status: draft
created: 2026-11-02
updated: 2026-11-14
prd_version: —
main_goal: quality
top_blocker: decisions
milestone_id: categories-across-years-and-atrament-views
milestone_seq: 4
milestone_status: open
---

# Roadmap: Household Treasurer

> Derived from the user's description (2026-11-14) and the cards `PageReportsYears`, `PageReports`, `PageCategories` and `Sidebar` in the Atrament design system (claude.ai artifact „Domowy Skarbnik”), + codebase baseline re-checked in the repo.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.
> Identyfikatory kontynuują numerację: M-1 to F-01 i S-01…S-10, porzucony M-2 to F-02 i S-11…S-17, M-3 to F-03…F-06 i S-18…S-33 (w `## Done` i `## Milestone History`), więc M-4 zaczyna od F-07 i S-34.

## Milestone

**M-4: Kategorie przez lata i widoki Atramentu** — Status: open

- **Intent:** Użytkownik widzi, jak każda kategoria zmieniała się od 2020 roku, porównując lata do tego samego dnia, i dochodzi od raportu roku do szczegółu jednej kategorii. Przy okazji Raporty, Kategorie i menu roku dostają układ wybrany w design systemie Atrament.
- **Source materials:** opis użytkownika z 2026-11-14 („w ogóle brakuje mi widoku porównania kategorii na przestrzeni lat; jest tylko porównanie lat sąsiednich, a nie ma większych trendów”) i jego wybory w design systemie: `PageReportsYears` (wariant A), `PageReports` (C), `PageCategories` (B), menu roku w `Sidebar` (B). Bez PRD.
- **Done when:** każdy element F-NN i S-NN poniżej ma status `done`, a użytkownik na produkcji porównał co najmniej jedną kategorię przez lata 2020–2026 w widoku „Lata” i doszedł z niej do panelu tej kategorii.
- **Scope anchors:**
  - MS-01: Raporty mają piątą pozycję okresu „Lata”: tabela kategorie × lata 2020–2026, każdy rok liczony do tego samego dnia (przełącznik „Pełne lata”), zmiana od 2020 (albo od pierwszego roku kategorii), mini-wykres w wierszu, grupy z sumami, zarchiwizowane kategorie, zakładki Wydatki | Przychody (`PageReportsYears`).
  - MS-02: Raport roku pokazuje kategorie od największych, z paskiem udziału, różnicą do poprzedniego roku i panelem wybranej kategorii (2026, 2025, różnica, udział, miesiące roku, przejście do Operacji); przełącznik Kategorie | Grupy (`PageReports`).
  - MS-03: Panel kategorii w Raporcie ma zakładkę „Lata” z latami 2020–2026 tej kategorii, a nazwa kategorii w tabeli „Lata” otwiera ją w tym panelu.
  - MS-04: „Kategorie” mają zakładki Wydatki | Przychody | Archiwum z licznikami, szukajkę po nazwie i opisie, filtr rytmu i lata w budżecie jako pasek komórek (`PageCategories`).
  - MS-05: Menu „Rok budżetu” w sidebarze pokazuje stan roku (bieżący, szkic), dzieli lata na Ostatnie i Starsze i podpisuje „Dodaj rok N” jako szkic z planem roku N−1 (`Sidebar`).

## Vision recap

Raporty porównują dziś tylko okres z tym samym okresem rok wcześniej (ADR-0074), a Trend pokazuje miesiące jednego roku (ADR-0077). Od wczytania arkusza (ADR-0085, ADR-0096) aplikacja ma budżety 2020–2027 na jednej liście kategorii (ADR-0063), ale nie ma miejsca, w którym widać dłuższy trend kategorii, np. że Wakacje urosły prawie czterokrotnie, a Czynsz o 29%. Ten kamień dodaje taki odczyt i łączy go z raportem roku, a przy okazji wdraża w aplikacji projekty Kategorii i menu roku, które użytkownik wybrał w design systemie.

## North star

**S-34: użytkownik może otworzyć Raporty → „Lata” i porównać każdą kategorię w latach 2020–2026, licząc każdy rok do tego samego dnia** — to dokładnie brak, który użytkownik nazwał, i pierwszy odczyt w aplikacji obejmujący więcej niż dwa lata, więc przy celu „jakość” stoi zaraz po decyzjach, na których się opiera.

> Gwiazda przewodnia (north star) to najmniejszy wycinek od początku do końca, którego dostarczenie dowodzi, że kamień milowy działa. Stoi tak wcześnie, jak pozwalają warunki wstępne, bo reszta ma sens tylko wtedy, gdy on działa.

## At a glance

| ID   | Change ID                          | Outcome (user can …)                                                                                                   | Prerequisites | PRD refs            | Status   |
| ---- | ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------- | ------------- | ------------------- | -------- |
| F-07 | report-redesign-decisions          | (foundation) ADR i słownik opisują odczyt „Lata”, „Pełne lata”, ranking zamiast wykresu słupkowego i kolory wykresów Raportów | —             | MS-01, MS-02, MS-03 | done        |
| S-34 | report-categories-across-years     | użytkownik może porównać każdą kategorię w latach 2020–2026 w widoku Raportów „Lata”                                   | F-07          | MS-01               | proposed |
| S-35 | report-ranking-and-category-panel  | użytkownik może zobaczyć raport roku jako ranking kategorii i otworzyć panel jednej kategorii z jej miesiącami          | F-07          | MS-02               | proposed |
| S-36 | report-panel-years-tab             | użytkownik może przejść z tabeli „Lata” do panelu kategorii i zobaczyć w nim jej lata 2020–2026                         | S-34, S-35    | MS-03               | proposed |
| S-37 | categories-tabs-and-filters        | użytkownik może przeglądać „Kategorie” w zakładkach z archiwum, szukać ich, filtrować po rytmie i widzieć lata w budżecie | —             | MS-04               | done        |
| S-38 | budget-year-menu-state             | użytkownik może zobaczyć w menu „Rok budżetu”, który rok jest bieżący, a który jest szkicem                            | —             | MS-05               | done |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                    | Chain                                      | Note                                                                                                   |
| ------ | ------------------------ | ------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| A      | Raporty przez lata       | `F-07` → `S-34` → `S-35` → `S-36`          | Przy celu „jakość” najpierw decyzje; `S-34` i `S-35` mogą iść równolegle, `S-36` łączy oba.             |
| B      | Kategorie                | `S-37`                                     | Samodzielny widok bez warunków wstępnych; decyzja o archiwum (ADR-0067) zapada w planie wycinka.       |
| C      | Menu roku                | `S-38`                                     | Samodzielny; zmienia wspólny props sidebara, więc najlepiej nie w tym samym czasie co inne zmiany `shell`. |

## Baseline

What's already in place in the codebase as of `2026-11-14` (re-checked in the repo; zastępuje stan z 2026-11-08 z M-3).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — strony Inertia/Vue, w tym `Categories.vue` i `Reports.vue` (Rok, Miesiąc, 12 miesięcy, Trend), wykresy przez shadcn-vue chart i Unovis (ADR-0071), menu roku `BudgetYearSwitcher.vue` (ADR-0087), design system Atrament z kartami docelowych widoków.
- **Backend / API:** present — `ReportController` z `PeriodReport`, `ReportTrend`, `ReportTwelveMonthWindow` i `ReportComparisonPeriod`, który już liczy okres do tego samego dnia rok wcześniej (ADR-0074).
- **Data:** present — jedna lista kategorii przez lata (ADR-0063), scalanie z przeniesieniem historii (ADR-0078), budżety 2020–2027 wczytane z arkusza (ADR-0085, ADR-0096), wzorzec migracji danych z kopią przed wdrożeniem (ADR-0064).
- **Auth:** present — Fortify, 2FA, passkeys, polityki w `app/Policies`.
- **Deploy / infra:** partial — ręczne `scripts/deploy.sh` z kopią bazy; CI testuje na PostgreSQL, nie wdraża i nie uruchamia E2E.
- **Observability:** partial — log plikowy, `/up`, `backup:monitor`. Ten kamień nic tu nie wymaga.

## Foundations

### F-07: Decyzje widoków Raportów

- **Outcome:** (foundation) ADR opisuje piątą pozycję okresu „Lata” (lata liczone do tego samego dnia, „Pełne lata” z rokiem w toku, zmiana od pierwszego roku kategorii), ranking z panelem kategorii w miejsce wykresu słupkowego z ADR-0071 i ADR-0084 oraz kolory wykresów (tokeny `chart-1`/`chart-2` albo `brand-text`/`success` w design systemie), a `context/foundation/glossary.md` nazywa nowy odczyt po polsku i angielsku.
- **Change ID:** report-redesign-decisions
- **PRD refs:** MS-01, MS-02, MS-03
- **Unlocks:** S-34, S-35 (i przez nie S-36); rozstrzyga pytania 1 i 2 z `## Open Roadmap Questions`.
- **Prerequisites:** —
- **Parallel with:** S-37, S-38
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Stoi przed widokami, bo AGENTS.md wymaga wpisu w słowniku i ADR przed kodem, a `S-34` i `S-35` dzielą kolory i przełącznik okresu; bez wspólnej decyzji dwa równoległe plany rozstrzygnęłyby je inaczej. Tylko dokumenty, bez kodu.
- **Status:** done

## Slices

### S-34: Kategorie przez lata

- **Outcome:** użytkownik może otworzyć Raporty → „Lata” i porównać każdą kategorię i grupę w latach 2020–2026, licząc każdy rok do tego samego dnia albo w pełnych latach, ze zmianą od 2020 i mini-wykresem, dla wydatków i przychodów
- **Change ID:** report-categories-across-years
- **PRD refs:** MS-01
- **Prerequisites:** F-07
- **Parallel with:** S-35, S-37, S-38
- **Blockers:** —
- **Unknowns:**
  - Czy wczytane lata 2020–2021 (ADR-0096) mają kategorie i grupy na tyle spójne, że „—” oznacza brak pozycji, a nie lukę po wczytaniu? — Owner: user. Block: no.
- **Risk:** Pierwszy odczyt przez więcej niż dwa lata: suma roku w tabeli musi równać się wydatkom tego roku w raporcie Rok, a liczenie siedmiu lat nie może iść zapytaniami w pętli po latach (Sloppy). Zarchiwizowane i scalone kategorie łatwo zgubić albo policzyć podwójnie.
- **Status:** proposed

### S-35: Ranking kategorii i panel kategorii

- **Outcome:** użytkownik może zobaczyć raport roku jako listę kategorii od największych z paskiem udziału i różnicą do poprzedniego roku, przełączyć na grupy i otworzyć panel jednej kategorii z kwotą 2026 i 2025, różnicą, udziałem, miesiącami roku i przejściem do Operacji
- **Change ID:** report-ranking-and-category-panel
- **PRD refs:** MS-02
- **Prerequisites:** F-07
- **Parallel with:** S-34, S-37, S-38
- **Blockers:** —
- **Unknowns:**
  - Czy ranking i panel dotyczą też widoków Miesiąc i 12 miesięcy, czy tylko Roku? — Owner: user. Block: no. — Resolved by ADR-0097 (F-07): ranking w Roku, Miesiącu i 12 miesiącach; panel tylko w Roku.
- **Risk:** Zastępuje wykres słupkowy i tabelę z S-28/S-29, więc nie może zgubić niczego, co raport dziś pokazuje (grupy, zarchiwizowane, porównanie do tego samego dnia, przejście do Operacji z S-31); panel potrzebuje miesięcy kategorii w widoku Rok i wyboru kategorii w adresie (zmiana propsów z testem `assertInertia`).
- **Status:** proposed

### S-36: Zakładka „Lata” w panelu kategorii

- **Outcome:** użytkownik może kliknąć kategorię w tabeli „Lata” i zobaczyć w panelu kategorii Raportu zakładkę „Lata” z jej latami 2020–2026
- **Change ID:** report-panel-years-tab
- **PRD refs:** MS-03
- **Prerequisites:** S-34, S-35
- **Parallel with:** S-37, S-38
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Łączy dwa wycinki, więc stoi na końcu strumienia; lata w panelu muszą pochodzić z tego samego odczytu co tabela „Lata”, inaczej liczby rozjadą się między dwoma miejscami.
- **Status:** proposed

### S-37: Kategorie w zakładkach z filtrami

- **Outcome:** użytkownik może przeglądać „Kategorie” w zakładkach Wydatki, Przychody i Archiwum z licznikami, szukać po nazwie i opisie, filtrować wydatki po rytmie i widzieć lata w budżecie jako pasek komórek
- **Change ID:** categories-tabs-and-filters
- **PRD refs:** MS-04
- **Prerequisites:** —
- **Parallel with:** F-07, S-34, S-35, S-36, S-38
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Archiwum jako zakładka zmienia ADR-0067 (sekcja archiwum pod listą), a przeciąganie musi zniknąć przy włączonym filtrze lub szukaniu, inaczej zapis kolejności z częściowej listy przestawi kategorie (reorder grup z S-22).
- **Status:** done

### S-38: Stan roku w menu „Rok budżetu”

- **Outcome:** użytkownik może zobaczyć w menu „Rok budżetu”, który rok jest bieżący, a który jest szkicem, z latami w grupach Ostatnie i Starsze i podpisem „szkic z planem roku N−1” przy „Dodaj rok N”
- **Change ID:** budget-year-menu-state
- **PRD refs:** MS-05
- **Prerequisites:** —
- **Parallel with:** F-07, S-34, S-35, S-36, S-37
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Rozwija ADR-0087 i zmienia współdzielony props `shell.budgetYears` (lata z fazą), który czyta każda strona i paleta ⌘K; ciemne menu to klasa na `DropdownMenuContent`, bez edycji `components/ui`.
- **Status:** done

## Backlog Handoff

| Roadmap ID | Change ID                         | Suggested issue title                                                  | Ready for `/10x-plan` | Notes                                                |
| ---------- | --------------------------------- | ---------------------------------------------------------------------- | --------------------- | ---------------------------------------------------- |
| F-07       | report-redesign-decisions         | ADR i słownik: widok „Lata”, ranking z panelem, kolory wykresów         | yes                   | Run `/10x-plan report-redesign-decisions`            |
| S-34       | report-categories-across-years    | Raporty: kategorie przez lata 2020–2026 do tego samego dnia             | no                    | Po F-07; karta `PageReportsYears`                    |
| S-35       | report-ranking-and-category-panel | Raporty: ranking kategorii i panel kategorii                           | no                    | Po F-07; karta `PageReports`                         |
| S-36       | report-panel-years-tab            | Raporty: zakładka „Lata” w panelu kategorii                            | no                    | Po S-34 i S-35                                       |
| S-37       | categories-tabs-and-filters       | Kategorie: zakładki z archiwum, szukajka, filtr rytmu, pasek lat        | yes                   | Run `/10x-ui` → `/10x-plan categories-tabs-and-filters`; karta `PageCategories` |
| S-38       | budget-year-menu-state            | Sidebar: stan roku w menu „Rok budżetu”                                | yes                   | Run `/10x-plan budget-year-menu-state`; karta `Sidebar` |

This table is the clean handoff to Jira/Linear or any MCP-backed backlog. Include one row for every `F-NN` and `S-NN`. It should be compact enough to copy into issues, but it must not duplicate the detailed roadmap body.

## Open Roadmap Questions

1. **Kolory wykresów Raportów.** Aplikacja ma tokeny `chart-1` i `chart-2`, design system Atrament ich nie ma, a karty Raportów używają `brand-text`, `success` i `foreground`. Dopisać tokeny wykresu do design systemu czy zastąpić je w aplikacji? — Owner: user. Block: rozstrzyga F-07; gates S-34, S-35. — Resolved by ADR-0097 (F-07): zostają tokeny aplikacji; wypełnienie wydatku to `chart-1`, przychodu `success`, rok bieżący i najwyższy miesiąc `foreground`, rok bez pozycji `track`; `chart-1` i `chart-2` trafiają do `tokens.json` Atramentu, a karty malują przez `chart-1`, nie `brand-text`.
2. **Nazwa odczytu „Lata” w słowniku.** Odczyt wielu lat do tego samego dnia potrzebuje terminu po polsku i angielsku obok „Rok do roku” (S-29) i Trendu (S-30). — Owner: user. Block: rozstrzyga F-07; gates S-34. — Resolved by ADR-0097 (F-07): akapit „Years” we wpisie Period Report, adres `?view=years`, segment „Lata”; terminy Same-day Cut, Full Years, Change since First Year, Average Yearly Growth, Fastest Growing.

## Parked

- **Wykres zmiany od 2020 dla wybranych kategorii i kafelki kategorii** — Why parked: warianty B i C widoku „Lata” odrzucone przy wyborze w design systemie (2026-11-14); kolor kategorii na liniach wykresu łamał zasadę „kolor kategorii to tylko tożsamość”.
- **Lata dla okresów Miesiąc i 12 miesięcy** — Why parked: opis mówi o latach całych albo do tego samego dnia; porównanie miesiąca przez lata nie było prośbą.
- **Plan na grupie i więcej niż dwa poziomy** — Why parked: PRD v3 §Non-Goals; grupa tylko sumuje kategorie.
- **Liczniki mediów, wynagrodzenia** — Why parked: PRD v3 §Non-Goals.
- **Cele długoterminowe i nazwane pozycje odkładania** — Why parked: PRD v3 §Non-Goals; „odkładam / dobieram” zostaje jedną kwotą.
- **Własny zakres dat, eksport i wydruk raportu** — Why parked: poza zakresem; okresy to Rok, Miesiąc, 12 miesięcy, Trend i Lata.
- **Year Close i Reserve** — Why parked: niezaimplementowane na `main` (AGENTS.md).
- **Pozostałe karty Atramentu czekające na `/10x-ui`** (Dashboard, Konta, Lokaty, import, ustawienia, strona powitalna, ekrany wejścia) — Why parked: użytkownik wybrał do M-4 tylko Raporty, Kategorie i menu roku.
- **Automatyczne wdrożenie i E2E w CI** — Why parked: poza zakresem; do czasu E2E w CI plan każdego wycinka zmieniającego widoki dopisuje `npx playwright test` do weryfikacji.
- **Śledzenie błędów i metryki** — Why parked: obserwowalność częściowa, ten kamień jej nie wymaga.

## Milestone History

- **M-1: Kontrola miesiąca bez arkusza** (`month-control-without-spreadsheet`) — closed 2026-11-03. Wszystkie elementy F-01 i S-01 do S-10 mają status `done`; warunek „pełny miesiąc w aplikacji” (kryterium sukcesu PRD) do potwierdzenia w użyciu.
- **M-2: Raporty okresu i wydatki nieregularne** (`period-reports-and-irregular-expenses`) — abandoned 2026-11-08. Zastąpiony przez PRD v3 (`context/foundation/prd-v3.md`), który przebudował zakres: jedna lista kategorii zamiast powiązania 1:1, rytmy zamiast flag, raporty po grupach. Żaden z elementów F-02, S-11…S-17 nie był `done`; zmianę `period-report-by-category` usunięto w sesji shape 2026-11-07.
- **M-3: Jedna lista kategorii, rytmy i raporty** (`category-list-rhythms-reports`) — closed 2026-11-12. Wszystkie elementy F-03…F-06 i S-18…S-33 mają status `done`; kod na produkcji, pary kategorii scalone (S-21), a pięć kroków głównego kryterium sukcesu PRD v3 użytkownik przeszedł na produkcji.

## Done

(Wpisy dodaje `/10x-archive`.)

- **F-01: (foundation) nowy ADR zastąpił ADR-0024 w części o „drugim budżecie miesięcznym”, a nowe terminy oraz progi powiadomień o wadze `warning` są w `context/foundation/glossary.md`.** — Archived 2026-11-02 → `context/archive/2026-11-02-month-plan-domain-decisions/`. Lesson: —.
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
- **F-03: (foundation) nowy ADR zastępuje ADR-0014 i zmienia ADR-0016 i ADR-0034, a wpisy w `context/foundation/glossary.md` opisują kategorię przez lata (bez terminu _Avoid_ „a Category identity that spans years”), grupę kategorii, archiwum globalne i regułę migracji (nazwa + typ → jedna kategoria listy).** — Archived 2026-11-09 → `context/archive/2026-11-09-category-list-domain-decisions/`. Lesson: —.
- **F-04: (foundation) wdrożenie robi kopię bazy przed migracją, przywrócenie z niej jest raz sprawdzone na kopii lokalnej, a reguły migracji danych, tożsamości kategorii i konwersji starszego snapshotu (poprawki 1 i 2 z health-check) ładują się agentowi z `.ai/rules`.** — Archived 2026-11-09 → `context/archive/2026-11-09-safe-first-data-migration/`. Lesson: —.
- **S-18: użytkownik może otworzyć „Kategorie” i zobaczyć jedną listę kategorii przez lata z sekcją archiwum, a budżet 2026, ~1 000 operacji, Budżet roczny, plan miesiąca, Import i `user:backup` / `user:restore` działają na tej liście bez ręcznej pracy i bez zmiany kwot** — Archived 2026-11-10 → `context/archive/2026-11-09-category-list-after-migration/`. Lesson: —.
- **F-05: (foundation) nowy ADR zmienia ADR-0024 i ADR-0033: cztery rytmy zastępują flagi, mapowanie flag na rytm przy migracji jest ustalone, a wpisy w `context/foundation/glossary.md` nazywają stawkę i „zebrane” (po polsku i angielsku), regułę „do wydania” dla wydatku nieregularnego i przejście zebranego przez 31 grudnia.** — Archived 2026-11-10 → `context/archive/2026-11-09-rhythm-and-irregular-decisions/`. Lesson: —.
- **S-19: użytkownik może utworzyć kategorię i edytować jej nazwę, ikonę, kolor, kolejność, typ i opis, zarchiwizować ją i przywrócić; archiwalna znika z wyboru w operacjach i planie, a na istniejącej operacji zostaje widoczna w selektorze** — Archived 2026-11-10 → `context/archive/2026-11-10-category-edit-and-archive/`. Lesson: —.
- **S-20: użytkownik może utworzyć nowy budżet, który bierze aktywne kategorie z listy i podpowiada kwoty planu z planu aktualnego poprzedniego roku** — Archived 2026-11-10 → `context/archive/2026-11-10-new-budget-from-category-list/`. Lesson: —.
- **S-22: użytkownik może utworzyć, nazwać, uporządkować i usunąć grupę (przychodów albo wydatków), przypisać do niej kategorie i widzieć kategorie w grupach z sumą grupy w „Kategoriach”, w Budżecie rocznym i w selektorze kategorii operacji** — Archived 2026-11-10 → `context/archive/2026-11-10-category-groups/`. Lesson: —.
- **S-28: użytkownik może otworzyć „Raporty”, wybrać miesiąc albo rok i zobaczyć przychody, wydatki, wynik oraz grupy z rozwinięciem do kategorii, z kwotą i udziałem % wydatków, jako wykres i tabelę** — Archived 2026-11-10 → `context/archive/2026-11-10-period-report-by-group/`. Lesson: —.
- **S-24: użytkownik może zobaczyć rytm nadany istniejącym kategoriom z dzisiejszych flag, poprawić go, a propozycja planu miesiąca liczy kwotę z rytmu (stała opłata, bieżąca, równomierna roczna z odczytem „zostało X na Y mies.”)** — Archived 2026-11-10 → `context/archive/2026-11-10-category-rhythm/`. Lesson: —.
- **S-29: użytkownik może porównać okres raportu z tym samym okresem rok wcześniej (kwota, różnica) na tej samej kategorii listy** — Archived 2026-11-11 → `context/archive/2026-11-11-year-over-year-report/`. Lesson: —.
- **S-26: użytkownik może oznaczyć kategorię jako nieregularną i podać zebrane startowe, widzieć w planie miesiąca i w Podsumowaniu miesiąca stawkę oraz „zebrane”, a rachunek zużywa zebrane i obniża „do wydania” tylko o nadwyżkę; „odkładam / dobieram” działa jak dotąd** — Archived 2026-11-11 → `context/archive/2026-11-11-irregular-expense-collected/`. Lesson: —.
- **S-27: użytkownik może zobaczyć w nowym budżecie zebrane startowe kategorii nieregularnej przeniesione ze stanu na 31 grudnia, a stawka nowego roku wynika z nowego planu rocznego** — Archived 2026-11-11 → `context/archive/2026-11-11-collected-carry-over-new-year/`. Lesson: —.
- **S-30: użytkownik może zobaczyć kwoty grup i kategorii w kolejnych miesiącach roku, domyślnie po grupach** — Archived 2026-11-11 → `context/archive/2026-11-11-report-trend-by-month/`. Lesson: —.
- **S-21: użytkownik może scalić dwie kategorie listy w jedną po podglądzie liczb (ile operacji, jakie plany), a operacje, plany i zebrane przechodzą na kategorię docelową.** — Archived 2026-11-11 → `context/archive/2026-11-11-category-merge/`. Lesson: —. Scalenie par na produkcji po F-06 i wdrożeniu.
- **S-31: użytkownik może przejść z kategorii w raporcie do jej operacji w wybranym okresie** — Archived 2026-11-12 → `context/archive/2026-11-12-report-to-operations/`. Lesson: —.
- **S-25: użytkownik może zobaczyć w planie miesiąca kategorie w zwijanych grupach z sumą grupy i kategorie bez grupy osobno, a przekroczenie kategorii widać też na wierszu zwiniętej grupy** — Archived 2026-11-12 → `context/archive/2026-11-12-month-plan-groups/`. Lesson: —.
- **F-06: (foundation) `user:restore` wczytuje snapshot także na użytkownika z danymi: id konta w tożsamości importu idzie przez mapę kont, a digest jest liczony od nowa (ADR-0080).** — Archived 2026-11-12 → `context/archive/2026-11-12-restore-import-identity-accounts/`. Lesson: —.
- **S-32: użytkownik może wybrać okres raportu „ostatnie 12 miesięcy”** — Archived 2026-11-12 → `context/archive/2026-11-12-rolling-twelve-month-report/`. Lesson: —.
- **S-33: użytkownik może ustawić domyślny procent planowanych przychodów, który propozycja planu wpisuje jako „odkładam”** — Archived 2026-11-12 → `context/archive/2026-11-12-default-set-aside-percent/`. Lesson: —.
- **S-23: użytkownik może importować operacje z podpowiedziami wskazującymi kategorię listy, trafnymi co najmniej tak jak przed zmianą** — Archived 2026-11-12 → `context/archive/2026-11-12-import-suggestions-on-category-list/`. Lesson: —.
- **S-37: użytkownik może przeglądać „Kategorie” w zakładkach Wydatki, Przychody i Archiwum z licznikami, szukać po nazwie i opisie, filtrować wydatki po rytmie i widzieć lata w budżecie jako pasek komórek** — Archived 2026-11-14 → `context/archive/2026-11-14-categories-tabs-and-filters/`. Lesson: —.
- **F-07: (foundation) ADR opisuje piątą pozycję okresu „Lata”, ranking z panelem kategorii w miejsce wykresu słupkowego i kolory wykresów (ADR-0097), a słownik nazywa nowy odczyt** — Archived 2026-11-14 → `context/archive/2026-11-14-report-redesign-decisions/`. Lesson: —.
- **S-38: użytkownik może zobaczyć w menu „Rok budżetu”, który rok jest bieżący, a który jest szkicem, z latami w grupach Ostatnie i Starsze i podpisem „szkic z planem roku N−1” przy „Dodaj rok N”** — Archived 2026-11-14 → `context/archive/2026-11-14-budget-year-menu-state/`. Lesson: —.
