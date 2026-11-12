---
project: 'Household Treasurer'
version: 3
status: draft
created: 2026-11-02
updated: 2026-11-12
prd_version: 3
main_goal: quality
top_blocker: decisions
milestone_id: category-list-rhythms-reports
milestone_seq: 3
milestone_status: open
---

# Roadmap: Household Treasurer

> Derived from `context/foundation/prd-v3.md` (v3) + codebase baseline from `context/foundation/health-check.md` and `context/foundation/stack-assessment.md` (2026-11-08), re-checked in the repo.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.
> Identyfikatory kontynuują numerację: M-1 to F-01 i S-01…S-10 (w `## Done`), porzucony M-2 to F-02 i S-11…S-17 (w `## Milestone History`), więc M-3 zaczyna od F-03 i S-18.

## Milestone

**M-3: Jedna lista kategorii, rytmy i raporty** — Status: open

- **Intent:** Użytkownik prowadzi jedną listę kategorii przez lata (grupy, rytm, opis, archiwum), na którą migracja przepina istniejące budżety i operacje bez zmiany kwot. Na tej liście plan miesiąca liczy wydatki płacone kilka razy w roku przez stałą stawkę i „zebrane”, a raporty pokazują, ile i na co idzie w miesiącu i w roku, z porównaniem lat.
- **Source materials:** `context/foundation/prd-v3.md` (v3), z blokiem przekazania dla mapy drogowej z `context/foundation/shape-notes.md` (sesja 2026-11-07).
- **Done when:** każdy element F-NN i S-NN poniżej ma status `done`, a użytkownik przeszedł pięć kroków głównego kryterium sukcesu z PRD v3.
- **Scope anchors:** FR-001 do FR-025, US-01 i US-02.

## Vision recap

Kategoria należy dziś do jednego roku, a lata łączy zapytanie po nazwie i typie, więc nie ma jednego miejsca, w którym użytkownik porządkuje kategorie, ani pewnego porównania lat. Arkusz Excela obok aplikacji wciąż trzyma grupy, domyślne kwoty i tabelę wydatków nieregularnych, a stawki na prąd czy OC użytkownik przelewa na osobne konto. Zmiana daje jedną zarządzaną listę kategorii używaną w całej aplikacji, rytm kategorii w planie miesiąca (ze „zebranym” dla wydatków nieregularnych, bez przelewów) i raporty okresu na tej liście.

## North star

**S-18: użytkownik może otworzyć „Kategorie” i zobaczyć jedną listę kategorii przez lata, na którą migracja przepięła istniejące budżety i operacje bez zmiany kwot** — PRD v3 nazywa ten krok najmniejszym przyrostem, który dowodzi zmiany; przy celu „jakość” to także miejsce największego ryzyka (pierwsza nieodwracalna migracja na danych produkcyjnych), więc stoi tak wcześnie, jak pozwalają kroki przygotowawcze.

> Gwiazda przewodnia (north star) to najmniejszy wycinek od początku do końca, którego dostarczenie dowodzi, że zmiana działa. Stoi tak wcześnie, jak pozwalają warunki wstępne, bo reszta ma sens tylko wtedy, gdy on działa.

## At a glance

| ID   | Change ID                            | Outcome (user can …)                                                                                              | Prerequisites | PRD refs                                 | Status   |
| ---- | ------------------------------------ | ----------------------------------------------------------------------------------------------------------------- | ------------- | ---------------------------------------- | -------- |
| F-03 | category-list-domain-decisions       | (foundation) ADR i słownik opisują kategorię przez lata, grupę kategorii i regułę migracji                         | —             | Wymogi istniejącego systemu, Decyzje strukturalne 1 i 4, FR-006 | done     |
| F-04 | safe-first-data-migration            | (foundation) wdrożenie robi kopię bazy przed migracją, a reguły migracji danych, kategorii i snapshotu są w repo   | —             | Migracja danych (wskazane przez użytkownika), Guardrails | done     |
| F-05 | rhythm-and-irregular-decisions       | (foundation) ADR i słownik opisują cztery rytmy, stawkę, „zebrane” i przejście zebranego przez 31 grudnia          | —             | Business Logic Changes, Decyzje strukturalne 2, 3 i 5–9, FR-007, FR-010 | done     |
| F-06 | restore-import-identity-accounts     | (foundation) `user:restore` wczytuje snapshot także na użytkownika z danymi (id kont w tożsamości importu)          | —             | Guardrails, FR-023                       | ready    |
| S-18 | category-list-after-migration        | użytkownik może otworzyć „Kategorie” i zobaczyć jedną listę przez lata, na którą migracja przepięła dane            | F-03, F-04    | US-01, FR-001, FR-006, FR-022            | done |
| S-19 | category-edit-and-archive            | użytkownik może edytować kategorię z opisem, zarchiwizować ją i przywrócić                                          | S-18          | FR-002, FR-003, FR-019, FR-022           | done |
| S-20 | new-budget-from-category-list        | użytkownik może utworzyć budżet z aktywnych kategorii listy z kwotami podpowiedzianymi z poprzedniego roku          | S-18          | FR-005                                   | done |
| S-21 | category-merge                       | użytkownik może scalić dwie kategorie listy po podglądzie liczb                                                     | S-18          | FR-023                                   | done |
| S-22 | category-groups                      | użytkownik może grupować kategorie i widzieć sumy grup w Budżecie i w selektorze kategorii                          | S-18          | FR-004, FR-019, FR-021, FR-022           | done |
| S-23 | import-suggestions-on-category-list  | użytkownik może importować z podpowiedziami wskazującymi kategorię listy, trafnymi co najmniej jak przed zmianą     | S-18          | FR-020                                   | ready    |
| S-24 | category-rhythm                      | użytkownik może zobaczyć i poprawić rytm kategorii, a propozycja planu miesiąca liczy kwotę z rytmu                 | F-05, S-18    | FR-007, FR-009, FR-022                   | done     |
| S-25 | month-plan-groups                    | użytkownik może zobaczyć plan miesiąca w zwijanych grupach z sumą i sygnałem przekroczenia                          | S-22, S-24    | FR-008                                   | in-progress |
| S-26 | irregular-expense-collected          | użytkownik może oznaczyć wydatek jako nieregularny i widzieć stawkę oraz „zebrane”, które zużywa rachunek           | F-05, S-24    | US-02, FR-010, FR-011, FR-013, FR-022    | done |
| S-27 | collected-carry-over-new-year        | użytkownik może zobaczyć w nowym budżecie zebrane startowe przeniesione z 31 grudnia                               | S-20, S-26    | FR-012                                   | done        |
| S-28 | period-report-by-group               | użytkownik może zobaczyć raport miesiąca albo roku po grupach i kategoriach, jako wykres i tabelę                   | S-22          | FR-014, FR-015                           | done     |
| S-29 | year-over-year-report                | użytkownik może porównać okres raportu z tym samym okresem rok wcześniej na tej samej kategorii listy               | S-28          | FR-016                                   | done     |
| S-30 | report-trend-by-month                | użytkownik może zobaczyć kwoty grup i kategorii w kolejnych miesiącach roku                                         | S-28          | FR-017                                   | done     |
| S-31 | report-to-operations                 | użytkownik może przejść z kategorii w raporcie do jej operacji w wybranym okresie                                   | S-28          | FR-018                                   | ready    |
| S-32 | rolling-twelve-month-report          | użytkownik może wybrać okres raportu „ostatnie 12 miesięcy”                                                         | S-28          | FR-025                                   | ready    |
| S-33 | default-set-aside-percent            | użytkownik może ustawić domyślny procent przychodów, który propozycja planu wpisuje jako „odkładam”                 | S-24          | FR-024                                   | ready    |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                        | Chain                                                    | Note                                                                                                                       |
| ------ | ---------------------------- | -------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| A      | Lista kategorii i migracja   | `F-03` + `F-04` → `S-18` → `S-19` → `S-20` → `S-21` + `F-06` → `S-23` | Kroki 1–2 z PRD; przy celu „jakość” najpierw siatka bezpieczeństwa migracji. `F-06` daje drogę powrotu dla scalania par na produkcji, a pomiar trafności Importu w `S-23` idzie po tych porządkach. Po `S-18` wycinki `S-19`…`S-23` są od siebie niezależne. |
| B      | Grupy i raporty              | `S-22` → `S-28` → `S-29` → `S-30` → `S-31` → `S-32`       | Dołącza do A przy `S-18`. Krok 5 z PRD; `S-30`…`S-32` to elementy opcjonalne.                                               |
| C      | Rytm i wydatki nieregularne  | `F-05` → `S-24` → `S-25` → `S-26` → `S-27` → `S-33`       | Kroki 3–4 z PRD, jedyna zmiana reguły „do wydania”. Dołącza do A przy `S-18`; `S-25` łączy się z B przy `S-22`, `S-27` z A przy `S-20`. |

## Baseline

What's already in place in the codebase as of `2026-11-08` (auto-researched in `/10x-health-check` and `/10x-stack-assess`, re-checked in the repo, user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — strony Inertia/Vue w `resources/js/pages`, design system Atrament. Nie ma widoków „Kategorie” ani „Raporty” ani biblioteki wykresów (`.ai/rules/reports.md` wymaga decyzji w planie i ADR).
- **Backend / API:** present — trasy sesyjne w `routes/web.php`. Lata łączy nazwa i typ kategorii w kopiowaniu budżetu, propozycji planu (grudzień → styczeń), filtrze Operacji i podpowiedziach Importu.
- **Data:** partial — PostgreSQL, 20 migracji, wszystkie schematowe: brak wzorca migracji danych i testów migracji. Kategoria należy do budżetu i ma flagi `is_paced` i `month_item_kind` (czytane w około 50 plikach, w tym w parach kalkulatorów PHP/TS). Snapshot w wersji 5. `DemoHouseholdSeeder` tworzy tylko bieżący rok; mocka 2025 nie ma.
- **Auth:** present — Fortify, 2FA, passkeys, polityki w `app/Policies`, bez samodzielnej rejestracji.
- **Deploy / infra:** partial — `docker-compose.prod.yml` i ręczne `scripts/deploy.sh`, które robi `migrate --force` bez kopii bazy (kopia tylko nocna o 03:00). CI testuje na PostgreSQL, nie wdraża i nie uruchamia E2E.
- **Observability:** partial — log plikowy, `/up`, `backup:monitor`. PRD v3 nic tu nie wymaga.

Różnice względem dokumentów, istotne dla wycinków: Earmark, Reserve, Year Close i Cash Expectation są opisane w słowniku, ale nie istnieją w `app/`; ich definicje opierają się na kategorii należącej do budżetu. Reguł kompensacyjnych z oceny stosu (migracje, tożsamość kategorii, konwersja snapshotu) nie ma jeszcze w `.ai/rules`.

## Foundations

### F-03: Decyzje domenowe listy kategorii

- **Outcome:** (foundation) nowy ADR zastępuje ADR-0014 i zmienia ADR-0016 i ADR-0034, a wpisy w `context/foundation/glossary.md` opisują kategorię przez lata (bez terminu _Avoid_ „a Category identity that spans years”), grupę kategorii, archiwum globalne i regułę migracji (nazwa + typ → jedna kategoria listy).
- **Change ID:** category-list-domain-decisions
- **PRD refs:** Wymogi istniejącego systemu, Decyzje strukturalne 1 i 4, FR-006
- **Unlocks:** S-18 (AGENTS.md wymaga ADR i wpisu w słowniku przed zmianą terminu _Avoid_), S-20 (zastąpienie ADR-0014), S-22 (termin grupy kategorii), S-23 (zmiana ADR-0034); rozstrzyga Otwarte pytanie 3 (Earmark, Reserve, Year Close).
- **Prerequisites:** —
- **Parallel with:** F-04, F-05
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Stoi przed kodem, bo zmienia podstawową zasadę słownika; musi jasno powiedzieć, co dzieje się z planem rocznym (zostaje per budżet) i z kategoriami archiwalnymi, inaczej migracja w S-18 utrwali złą decyzję.
- **Status:** done

### F-04: Bezpieczna pierwsza migracja danych

- **Outcome:** (foundation) wdrożenie robi kopię bazy przed migracją, przywrócenie z niej jest raz sprawdzone na kopii lokalnej, a reguły migracji danych, tożsamości kategorii i konwersji starszego snapshotu (poprawki 1 i 2 z health-check) ładują się agentowi z `.ai/rules`.
- **Change ID:** safe-first-data-migration
- **PRD refs:** Migracja danych (wskazane przez użytkownika), Guardrails
- **Unlocks:** S-18 (pierwsza nieodwracalna migracja na danych produkcyjnych; bez kopii przed migracją błąd oznacza utratę do doby zmian), S-24 (reguła przejścia z flag na rytm etapami).
- **Prerequisites:** —
- **Parallel with:** F-03, F-05
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Mały, ale jedyny element, który chroni produkcję przed nieodwracalnym scaleniem kategorii; wzorzec testu migracji na PostgreSQL zostaje dla S-18, żeby powstał na prawdziwej migracji.
- **Status:** done

### F-05: Decyzje domenowe rytmu i wydatku nieregularnego

- **Outcome:** (foundation) nowy ADR zmienia ADR-0024 i ADR-0033: cztery rytmy zastępują flagi, mapowanie flag na rytm przy migracji jest ustalone, a wpisy w `context/foundation/glossary.md` nazywają stawkę i „zebrane” (po polsku i angielsku), regułę „do wydania” dla wydatku nieregularnego i przejście zebranego przez 31 grudnia.
- **Change ID:** rhythm-and-irregular-decisions
- **PRD refs:** Business Logic Changes, Decyzje strukturalne 2, 3 i 5–9, FR-007, FR-010
- **Unlocks:** S-24 (rozstrzyga Otwarte pytanie 1, mapowanie flag), S-26 (rozstrzyga Otwarte pytanie 2, słownictwo; zmiana reguły „do wydania” wymaga ADR przed kodem), S-27 (reguła 31 grudnia).
- **Prerequisites:** —
- **Parallel with:** F-03, F-04, S-18, S-19, S-20, S-21, S-22, S-23, S-28, S-29, S-30, S-31, S-32
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Musi wyraźnie odróżnić „zebrane” od „odkładam / dobieram”, Set-aside i Reserve; nazwy z PRD („odkładam 150 · zebrane 450”) dziś zderzają się z sekcją „odkładam” na dole planu.
- **Status:** done

### F-06: Restore snapshotu na istniejącego użytkownika

- **Outcome:** (foundation) `user:restore` wczytuje snapshot także na użytkownika, który ma już dane: `import_identity_account_id` operacji oraz `source_import_identity_account_id` i `destination_import_identity_account_id` przelewów przechodzą przez mapę id kont, jak w ruchach kapitału. Test restore na bazie z danymi i operacją z importu jest czerwony przed poprawką.
- **Change ID:** restore-import-identity-accounts
- **PRD refs:** Guardrails (droga powrotu), FR-023
- **Unlocks:** scalenie par kategorii na produkcji (S-21): droga powrotu ze snapshotu zapisanego przez akcję scalenia (`user:restore`, `README.md`, „Scalanie kategorii”) działa tylko po tej poprawce. Dotyczy też snapshotów `deploy.sh`.
- **Prerequisites:** —
- **Parallel with:** S-21, S-23, S-25, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** Czy to jedyne pola z id konta w snapshocie, których restore nie mapuje (próba S-21 sprawdziła operacje i przelewy w kodzie, nie wszystkie tabele).
- **Risk:** Błąd jest w `main` (`app/Actions/RestoreUserData.php`), nie w S-21; wykryty w próbie na kopii produkcji (`context/archive/2026-11-11-category-merge/merge-trial.md`, O1): snapshot produkcji ma to pole wypełnione w 1269 z 1280 operacji, a `user:restore` na istniejącego użytkownika kończy się błędem klucza obcego (SQLSTATE 23503). Do pustej bazy restore przechodzi. Poprawka musi wejść przed wdrożeniem, bo `deploy.sh` robi snapshoty, a scalenie jest nieodwracalne w aplikacji.
- **Status:** ready

## Slices

### S-18: Jedna lista kategorii po migracji

- **Outcome:** użytkownik może otworzyć „Kategorie” i zobaczyć jedną listę kategorii przez lata z sekcją archiwum, a budżet 2026, ~1 000 operacji, Budżet roczny, plan miesiąca, Import i `user:backup` / `user:restore` działają na tej liście bez ręcznej pracy i bez zmiany kwot
- **Change ID:** category-list-after-migration
- **PRD refs:** US-01, FR-001, FR-006, FR-022
- **Prerequisites:** F-03, F-04
- **Parallel with:** F-05
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Najbardziej ryzykowny wycinek kamienia milowego: liczby kontrolne (wykonanie per budżet i kategoria, liczba operacji, salda) przed i po, próba na kopii danych produkcyjnych i snapshot w starym formacie konwertowany tą samą regułą co migracja. Tworzenie budżetu musi dalej działać (budżet 2027 powstanie w grudniu), nawet zanim S-20 zmieni jego zasadę.
- **Status:** done

### S-19: Edycja, archiwum i przywracanie kategorii

- **Outcome:** użytkownik może utworzyć kategorię i edytować jej nazwę, ikonę, kolor, kolejność, typ i opis, zarchiwizować ją i przywrócić; archiwalna znika z wyboru w operacjach i planie, a na istniejącej operacji zostaje widoczna w selektorze
- **Change ID:** category-edit-and-archive
- **PRD refs:** FR-002, FR-003, FR-019, FR-022
- **Prerequisites:** S-18
- **Parallel with:** F-05, S-20, S-21, S-22, S-23, S-24, S-25, S-26, S-27, S-28, S-29, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Zmiana typu kategorii z operacjami w dowolnym roku musi być zablokowana (PRD, FR-002), inaczej sumy przychodów i wydatków się rozjadą.
- **Status:** done

### S-20: Nowy budżet z listy kategorii

- **Outcome:** użytkownik może utworzyć nowy budżet, który bierze aktywne kategorie z listy i podpowiada kwoty planu z planu aktualnego poprzedniego roku
- **Change ID:** new-budget-from-category-list
- **PRD refs:** FR-005
- **Prerequisites:** S-18
- **Parallel with:** F-05, S-19, S-21, S-22, S-23, S-24, S-25, S-26, S-28, S-29, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Stoi wcześnie w strumieniu A, bo budżet 2027 powstanie w grudniu; utworzony starą zasadą zostawiłby kategorie do scalania. Zastępuje ADR-0014 (F-03).
- **Status:** done

### S-21: Scalanie kategorii

- **Outcome:** użytkownik może scalić dwie kategorie listy w jedną po podglądzie liczb (ile operacji, jakie plany), a operacje, plany i zebrane przechodzą na kategorię docelową
- **Change ID:** category-merge
- **PRD refs:** FR-023
- **Prerequisites:** S-18
- **Parallel with:** F-05, S-19, S-20, S-22, S-23, S-24, S-25, S-26, S-27, S-28, S-29, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Nieodwracalne, więc podgląd liczb jest częścią wycinka, nie ozdobą. To narzędzie porządków (literówki, dane testowe), które kończą się przed pomiarem trafności Importu „przed” w S-23. Co się dzieje z zebranym przy scaleniu, rozstrzyga ADR-0078. Kod i próba na kopii produkcji są gotowe (`context/archive/2026-11-11-category-merge/merge-trial.md`); scalenie obu par na produkcji idzie po F-06 i po wdrożeniu: F-06 → `scripts/deploy.sh` → `user:backup` → scalenie w UI → `app:control-numbers`.
- **Status:** done

### S-22: Grupy kategorii

- **Outcome:** użytkownik może utworzyć, nazwać, uporządkować i usunąć grupę (przychodów albo wydatków), przypisać do niej kategorie i widzieć kategorie w grupach z sumą grupy w „Kategoriach”, w Budżecie rocznym i w selektorze kategorii operacji
- **Change ID:** category-groups
- **PRD refs:** FR-004, FR-019, FR-021, FR-022
- **Prerequisites:** S-18
- **Parallel with:** F-05, S-19, S-20, S-21, S-23, S-24, S-26, S-27, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Suma grupy musi równać się sumie jej kategorii co do grosza, a sumy Budżetu nie mogą się zmienić. Grupa nie miesza typów i nie ma planu (PRD Non-Goals).
- **Status:** done

### S-23: Podpowiedzi Importu na liście kategorii

- **Outcome:** użytkownik może importować operacje z podpowiedziami wskazującymi kategorię listy, trafnymi co najmniej tak jak przed zmianą
- **Change ID:** import-suggestions-on-category-list
- **PRD refs:** FR-020
- **Prerequisites:** S-18
- **Parallel with:** F-05, S-19, S-20, S-21, S-22, S-24, S-25, S-26, S-27, S-28, S-29, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:**
  - Kiedy porządki w danych: przed wdrożeniem S-18 czy po, przez S-21 i zmianę kategorii operacji (Otwarte pytanie 4)? Od tego zależy moment pomiaru trafności „przed”. — Owner: user. Block: no.
- **Risk:** Pomiar przed i po musi iść na tych samych danych (istnieje próg z `import-category-suggestions`). Gorsze podpowiedzi to jeden z najszybszych sygnałów awarii wskazanych w PRD.
- **Status:** ready

### S-24: Rytm kategorii w propozycji planu

- **Outcome:** użytkownik może zobaczyć rytm nadany istniejącym kategoriom z dzisiejszych flag, poprawić go, a propozycja planu miesiąca liczy kwotę z rytmu (stała opłata, bieżąca, równomierna roczna z odczytem „zostało X na Y mies.”)
- **Change ID:** category-rhythm
- **PRD refs:** FR-007, FR-009, FR-022
- **Prerequisites:** F-05, S-18
- **Parallel with:** S-19, S-20, S-21, S-22, S-23, S-28, S-29, S-30, S-31, S-32
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Flagi są czytane w około 50 plikach, w tym w parach kalkulatorów PHP/TS: rytm dochodzi obok flag, czytelnicy przechodzą obszarami, a flagi znikają na końcu (reguła z F-04). Minione miesiące i zatwierdzone plany nie mogą się zmienić.
- **Status:** done

### S-25: Plan miesiąca w grupach

- **Outcome:** użytkownik może zobaczyć w planie miesiąca kategorie w zwijanych grupach z sumą grupy i kategorie bez grupy osobno, a przekroczenie kategorii widać też na wierszu zwiniętej grupy
- **Change ID:** month-plan-groups
- **PRD refs:** FR-008
- **Prerequisites:** S-22, S-24
- **Parallel with:** S-19, S-20, S-21, S-23, S-26, S-27, S-28, S-29, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Stoi po S-24, bo sygnał przekroczenia na wierszu grupy zbiera sygnały kategorii, które zależą od rytmu (bieżąca wobec miesiąca, równomierna roczna wobec roku).
- **Status:** in-progress

### S-26: Wydatek nieregularny i „zebrane”

- **Outcome:** użytkownik może oznaczyć kategorię jako nieregularną i podać zebrane startowe, widzieć w planie miesiąca i w Podsumowaniu miesiąca stawkę oraz „zebrane”, a rachunek zużywa zebrane i obniża „do wydania” tylko o nadwyżkę; „odkładam / dobieram” działa jak dotąd
- **Change ID:** irregular-expense-collected
- **PRD refs:** US-02, FR-010, FR-011, FR-013, FR-022
- **Prerequisites:** F-05, S-24
- **Parallel with:** S-19, S-20, S-21, S-22, S-23, S-25, S-28, S-29, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** — (F9 (a)–(c) z przeglądu F-05 rozstrzygnięte w ADR-0073)
- **Risk:** Jedyna zmiana reguły „do wydania”: stawka i rachunek to jedna reguła, której nie da się dostarczyć osobno bez podwójnego obniżenia w miesiącu rachunku. Zebrane jest odczytem z operacji (zwroty netto), nie zapisaną liczbą; salda i Spendable Liquidity bez zmian, a Wynik miesiąca liczy faktyczny rachunek.
- **Status:** done

### S-27: Zebrane przez 31 grudnia

- **Outcome:** użytkownik może zobaczyć w nowym budżecie zebrane startowe kategorii nieregularnej przeniesione ze stanu na 31 grudnia, a stawka nowego roku wynika z nowego planu rocznego
- **Change ID:** collected-carry-over-new-year
- **PRD refs:** FR-012
- **Prerequisites:** S-20, S-26
- **Parallel with:** S-19, S-21, S-22, S-23, S-25, S-28, S-29, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** —
- **Uwaga po S-26:** łańcuch zebranego przechodzi przez 31 grudnia już od S-26 (ADR-0073); S-27 dodaje tylko odczyt „zebranego startowego” i jego pokazanie w wierszu Budżetu, w szkicu i w realizacji (ADR-0076).
- **Risk:** Zebrane startowe to odczyt stanu na 31 grudnia, nie liczba zapisana przy tworzeniu budżetu, bo budżet 2027 powstanie w grudniu, kiedy zebrane jeszcze się zmienia. Pierwsze przejście roku z prawdziwymi danymi wypada już na przełomie 2026 i 2027.
- **Status:** done

### S-28: Raport okresu po grupach

- **Outcome:** użytkownik może otworzyć „Raporty”, wybrać miesiąc albo rok i zobaczyć przychody, wydatki, wynik oraz grupy z rozwinięciem do kategorii, z kwotą i udziałem % wydatków, jako wykres i tabelę
- **Change ID:** period-report-by-group
- **PRD refs:** FR-014, FR-015
- **Prerequisites:** S-22
- **Parallel with:** F-05, S-19, S-20, S-21, S-23, S-24, S-25, S-26, S-27, S-33
- **Blockers:** —
- **Unknowns:**
  - Biblioteka wykresów czy wykres bez biblioteki? Decyzja planu plus ADR (`.ai/rules/reports.md`). — Owner: user. Block: no.
- **Risk:** Suma kategorii za rok musi równać się wykonaniu w Budżecie, a wynik miesiąca Wynikowi miesiąca, co do grosza; raport roku otwiera się w 2 s przy 10 000 operacji. Tu powstaje widok i decyzja o wykresach, z których korzysta reszta strumienia B.
- **Status:** done

### S-29: Rok do roku

- **Outcome:** użytkownik może porównać okres raportu z tym samym okresem rok wcześniej (kwota, różnica) na tej samej kategorii listy
- **Change ID:** year-over-year-report
- **PRD refs:** FR-016
- **Prerequisites:** S-28
- **Parallel with:** F-05, S-19, S-20, S-21, S-23, S-24, S-25, S-26, S-27, S-30, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Na produkcji porównanie lat działa dopiero od 2027, więc tu powstaje mock budżetu 2025 z operacjami (tylko dev). Porównanie idzie po kategorii listy, nie po nazwie.
- **Status:** done

### S-30: Trend w miesiącach roku

- **Outcome:** użytkownik może zobaczyć kwoty grup i kategorii w kolejnych miesiącach roku, domyślnie po grupach
- **Change ID:** report-trend-by-month
- **PRD refs:** FR-017
- **Prerequisites:** S-28
- **Parallel with:** F-05, S-19, S-20, S-21, S-23, S-24, S-25, S-26, S-27, S-29, S-31, S-32, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Element opcjonalny (nice-to-have). Wynik każdego miesiąca musi równać się Wynikowi miesiąca bez zapytań w pętli po miesiącach (Sloppy); sezonowość wydatków nieregularnych łatwo odczytać jako alarm.
- **Status:** done

### S-31: Z raportu do operacji

- **Outcome:** użytkownik może przejść z kategorii w raporcie do jej operacji w wybranym okresie
- **Change ID:** report-to-operations
- **PRD refs:** FR-018
- **Prerequisites:** S-28
- **Parallel with:** F-05, S-19, S-20, S-21, S-23, S-24, S-25, S-26, S-27, S-29, S-30, S-32, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Element opcjonalny (nice-to-have). Filtr Operacji przechodzi z nazwy i typu na kategorię listy i grupę (zmiana ADR-0016); lista musi pokazać to, co raport liczy (zwroty netto, bez transferów własnych).
- **Status:** ready

### S-32: Ostatnie 12 miesięcy

- **Outcome:** użytkownik może wybrać okres raportu „ostatnie 12 miesięcy”
- **Change ID:** rolling-twelve-month-report
- **PRD refs:** FR-025
- **Prerequisites:** S-28
- **Parallel with:** F-05, S-19, S-20, S-21, S-23, S-24, S-25, S-26, S-27, S-29, S-30, S-31, S-33
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Element opcjonalny (nice-to-have). Okres przechodzi przez dwa budżety, więc suma nie ma jednego odpowiednika w Budżecie rocznym; zgodność sprawdza się jako suma Wyników miesięcy.
- **Status:** ready

### S-33: Domyślny procent „odkładam”

- **Outcome:** użytkownik może ustawić domyślny procent planowanych przychodów, który propozycja planu wpisuje jako „odkładam”
- **Change ID:** default-set-aside-percent
- **PRD refs:** FR-024
- **Prerequisites:** S-24
- **Parallel with:** S-19, S-20, S-21, S-22, S-23, S-25, S-26, S-27, S-28, S-29, S-30, S-31, S-32
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Element opcjonalny (nice-to-have). Stoi po S-24, bo oba zmieniają propozycję planu miesiąca; reguła „odkładam / dobieram” (FR-013) się nie zmienia, zmienia się tylko kwota startowa.
- **Status:** ready

## Backlog Handoff

| Roadmap ID | Change ID                           | Suggested issue title                                                  | Ready for `/10x-plan` | Notes                                                |
| ---------- | ----------------------------------- | ---------------------------------------------------------------------- | --------------------- | ---------------------------------------------------- |
| F-03       | category-list-domain-decisions      | ADR i słownik: kategoria przez lata, grupa, reguła migracji              | done                  | Zarchiwizowane: `context/archive/2026-11-09-category-list-domain-decisions/` |
| F-04       | safe-first-data-migration           | Kopia bazy przed migracją i reguły migracji danych                      | done                  | Zarchiwizowane: `context/archive/2026-11-09-safe-first-data-migration/` |
| F-05       | rhythm-and-irregular-decisions      | ADR i słownik: rytmy, stawka, „zebrane”, 31 grudnia                     | done                  | Zarchiwizowane: `context/archive/2026-11-09-rhythm-and-irregular-decisions/` |
| F-06       | restore-import-identity-accounts    | Restore snapshotu: mapowanie id kont w tożsamości importu               | yes                   | Run `/10x-new restore-import-identity-accounts`; przed wdrożeniem i przed scaleniem par na produkcji (próba S-21, O1) |
| S-18       | category-list-after-migration       | Jedna lista kategorii po migracji i widok „Kategorie”                   | done                  | Zarchiwizowane: `context/archive/2026-11-09-category-list-after-migration/` |
| S-19       | category-edit-and-archive           | Edycja, archiwum i przywracanie kategorii                               | done                  | Zarchiwizowane: `context/archive/2026-11-10-category-edit-and-archive/` |
| S-20       | new-budget-from-category-list       | Nowy budżet z aktywnych kategorii listy                                | done                  | Zarchiwizowane: `context/archive/2026-11-10-new-budget-from-category-list/` |
| S-21       | category-merge                      | Scalanie kategorii z podglądem liczb                                   | done                  | Zarchiwizowane: `context/archive/2026-11-11-category-merge/`; scalenie par na produkcji po F-06 i wdrożeniu |
| S-22       | category-groups                     | Grupy kategorii w „Kategoriach”, Budżecie i selektorze                 | done                  | Zarchiwizowane: `context/archive/2026-11-10-category-groups/` |
| S-23       | import-suggestions-on-category-list | Podpowiedzi Importu na kategorii listy                                 | yes                   | Run `/10x-plan import-suggestions-on-category-list`; pomiar trafności po porządkach w danych (pytanie 4) |
| S-24       | category-rhythm                     | Rytm kategorii i propozycja planu z rytmu                              | done                  | Zarchiwizowane: `context/archive/2026-11-10-category-rhythm/`; przed wdrożeniem na produkcję próba na kopii (`reviews/production-rehearsal.md` w archiwum) |
| S-25       | month-plan-groups                   | Plan miesiąca w zwijanych grupach                                       | yes                   | Run `/10x-plan month-plan-groups`; S-26 done, plan miesiąca ma już wiersz nieregularny |
| S-26       | irregular-expense-collected         | Wydatek nieregularny: stawka i „zebrane”                                | done                  | Zarchiwizowane: `context/archive/2026-11-11-irregular-expense-collected/` |
| S-27       | collected-carry-over-new-year       | Zebrane startowe z 31 grudnia w nowym budżecie                          | done                  | Zarchiwizowane: `context/archive/2026-11-11-collected-carry-over-new-year/`; odczyt w wierszu Budżetu (ADR-0076) |
| S-28       | period-report-by-group              | Raporty: miesiąc albo rok po grupach i kategoriach                      | done                  | Zarchiwizowane: `context/archive/2026-11-10-period-report-by-group/` |
| S-29       | year-over-year-report               | Raporty: porównanie rok do roku                                         | done                  | Zarchiwizowane: `context/archive/2026-11-11-year-over-year-report/`; mock budżetu 2025 (dev) gotowy dla S-30…S-32 |
| S-30       | report-trend-by-month               | Raporty: trend w miesiącach roku                                        | done                  | Zarchiwizowane: `context/archive/2026-11-11-report-trend-by-month/` |
| S-31       | report-to-operations                | Raporty: przejście z kategorii do operacji                              | yes                   | Opcjonalne; run `/10x-plan report-to-operations` |
| S-32       | rolling-twelve-month-report         | Raporty: okres „ostatnie 12 miesięcy”                                   | yes                   | Opcjonalne; run `/10x-plan rolling-twelve-month-report` |
| S-33       | default-set-aside-percent           | Domyślny procent „odkładam” w propozycji planu                          | yes                   | Opcjonalne; run `/10x-plan default-set-aside-percent` |

This table is the clean handoff to Jira/Linear or any MCP-backed backlog. Include one row for every `F-NN` and `S-NN`. It should be compact enough to copy into issues, but it must not duplicate the detailed roadmap body.

## Open Roadmap Questions

1. **Mapowanie dzisiejszych flag na rytm przy migracji.** „Rachunek” → stała opłata jest jasne; „bieżąca” równomierna → bieżąca czy równomierna roczna, „jednorazowa” → równomierna roczna czy nieregularna? Na devie wszystkie wydatki są „bieżąca”. — Owner: user, przy ADR rytmu. Block: rozstrzyga F-05; gates S-24. — **Resolved 2026-11-10:** ADR-0066 (F-05).
2. **Słownictwo UI dla stawki i zebranego.** Wiersz nieregularny mówi dziś w szkicach „odkładam 150 · zebrane 450”, a sekcja na dole to też „odkładam”; nazwy polskie i angielskie (słownik) do ustalenia w ADR. — Owner: user. Block: rozstrzyga F-05; gates S-26. — **Resolved 2026-11-10:** ADR-0066 (F-05).
3. **Pojęcia z dokumentów, których nie ma w kodzie** (Earmark, Reserve, Year Close, Cash Expectation) są zdefiniowane na „kategorii należącej do budżetu” i łączeniu po nazwie. Czy przepisać ich definicje w słowniku w tej zmianie, czy oznaczyć jako do rewizji? — Owner: user, przy ADR tożsamości kategorii. Block: rozstrzyga F-03; gates S-18. — **Resolved 2026-11-09:** ADR-0063 (F-03, decyzja 7 planu): definicje przepisane minimalnie na kategorię listy i pozycję budżetu; ADR-0018 i ADR-0030 „Amended by ADR-0063”.
4. **Porządek w danych przed migracją.** Literówki, dane testowe z września–października, błędnie przypisane operacje (Prime Video w Rozrywce, Sinsay w Artykułach spożywczych) — czy użytkownik sprząta przed wdrożeniem, czy po, przez FR-023 i zmianę kategorii operacji? Wpływa na pomiar trafności Importu „przed”. — Owner: user. Block: S-23 (moment pomiaru, nie planowanie).

## Parked

- **Plan na grupie i więcej niż dwa poziomy** — Why parked: PRD v3 §Non-Goals; grupa tylko sumuje kategorie.
- **Import arkusza budżetu, liczniki mediów, wynagrodzenia** — Why parked: PRD v3 §Non-Goals; dane 2025 wchodzą tylko jako mock na devie (S-29).
- **Cele długoterminowe i nazwane pozycje odkładania** — Why parked: PRD v3 §Non-Goals; „odkładam / dobieram” zostaje jedną kwotą.
- **Domyślna kwota miesięczna kategorii** — Why parked: PRD v3, decyzja strukturalna 2; kwota wynika z planu rocznego i rytmu.
- **Podział jednej kategorii na kilka** — Why parked: PRD v3 daje tylko scalanie (FR-023); podgrupy to kategorie w grupie.
- **Własny zakres dat, eksport i wydruk raportu** — Why parked: poza PRD v3; okres to miesiąc, rok i (opcjonalnie) ostatnie 12 miesięcy.
- **Year Close i Reserve** — Why parked: niezaimplementowane na `main` (AGENTS.md); PRD v3 rozstrzyga koniec roku tylko dla zebranego (S-27), nie buduje Year Close.
- **Automatyczne wdrożenie i E2E w CI** — Why parked: health-check, kategoria B i poprawka 5; poza PRD v3. Do czasu E2E w CI plan każdego wycinka zmieniającego widoki dopisuje `npx playwright test` do weryfikacji.
- **Śledzenie błędów i metryki** — Why parked: obserwowalność częściowa, PRD v3 jej nie wymaga.

## Milestone History

- **M-1: Kontrola miesiąca bez arkusza** (`month-control-without-spreadsheet`) — closed 2026-11-03. Wszystkie elementy F-01 i S-01 do S-10 mają status `done`; warunek „pełny miesiąc w aplikacji” (kryterium sukcesu PRD) do potwierdzenia w użyciu.
- **M-2: Raporty okresu i wydatki nieregularne** (`period-reports-and-irregular-expenses`) — abandoned 2026-11-08. Zastąpiony przez PRD v3 (`context/foundation/prd-v3.md`), który przebudował zakres: jedna lista kategorii zamiast powiązania 1:1, rytmy zamiast flag, raporty po grupach. Żaden z elementów F-02, S-11…S-17 nie był `done`; zmianę `period-report-by-category` usunięto w sesji shape 2026-11-07.

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
