---
project: 'Household Treasurer'
version: 1
status: draft
created: 2026-11-02
updated: 2026-11-03
prd_version: 1
main_goal: market-feedback
top_blocker: decisions
milestone_id: month-control-without-spreadsheet
milestone_seq: 1
milestone_status: open
---

# Roadmap: Household Treasurer

> Derived from `context/foundation/prd.md` (v1) + auto-researched codebase baseline.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-1: Kontrola miesiąca bez arkusza** — Status: open

- **Intent:** Użytkownik prowadzi kontrolę całego miesiąca wyłącznie w aplikacji, od planu po podsumowanie, bez zakładek miesięcznych arkusza `budget_szablon.xlsx`.
- **Source materials:** `context/foundation/prd.md` (v1), z blokiem przekazania dla mapy drogowej z `context/foundation/shape-notes.md`.
- **Done when:** każdy element F-NN i S-NN poniżej ma status `done`, a pełny miesiąc został przeprowadzony w aplikacji zgodnie z głównym kryterium sukcesu PRD.
- **Scope anchors:** FR-001 do FR-019, US-01.

## Vision recap

Budżet roczny nie pozwala kontrolować wydatków w codzienności, a obecne odczyty `Wynik` i `Pozostało w miesiącu` nie mówią wyraźnie, ile jest jeszcze do wydania. Zmiana dodaje plan miesiąca obok budżetu rocznego i jedną liczbę „do wydania”, tak aby zasadniczo nie wydawać więcej, niż zarobiono. Odstępstwo od tej zasady pozostaje jawną decyzją, a nie błędem.

## North star

**S-01: użytkownik może zobaczyć propozycję planu miesiąca, poprawić ją, zatwierdzić i odczytać jedną liczbę „do wydania”** — ten przepływ najszybciej sprawdza wzór z PRD na prawdziwych danych, a to właśnie jest cel sekwencjonowania.

> Gwiazda przewodnia (north star) to najmniejszy wycinek od początku do końca, którego dostarczenie dowodzi, że produkt działa. Stoi tak wcześnie, jak pozwalają warunki wstępne, bo reszta ma sens tylko wtedy, gdy on działa.

## At a glance

| ID   | Change ID                   | Outcome (user can …)                                                                             | Prerequisites       | PRD refs                                                   | Status   |
| ---- | --------------------------- | ------------------------------------------------------------------------------------------------ | ------------------- | ---------------------------------------------------------- | -------- |
| F-01 | month-plan-domain-decisions | (foundation) decyzje o planie miesiąca i progi powiadomień są zapisane w dokumentach domeny       | —                   | Constraints & Compatibility, FR-012                        | done |
| S-01 | month-plan-and-to-spend     | użytkownik może zobaczyć propozycję planu, poprawić ją, zatwierdzić i odczytać „do wydania”      | F-01                | US-01, FR-001, FR-002, FR-005, FR-015, FR-018, FR-019      | done |
| S-02 | set-aside-and-draw          | użytkownik może wpisać w planie kwotę „odkładam / dobieram” i zobaczyć jej wpływ na „do wydania” | S-01                | FR-004, FR-016                                             | done |
| S-03 | approved-plan-changes       | użytkownik może zmienić zatwierdzony plan w trakcie miesiąca                                     | S-01                | FR-003                                                     | done |
| S-04 | month-items-plan-vs-actual  | użytkownik może zobaczyć plan i faktyczną kwotę każdej pozycji oraz kategorie jednorazowe        | S-01                | FR-006, FR-008                                             | done |
| S-05 | month-spending-pace         | użytkownik może zobaczyć tempo wydatków względem upływu dni miesiąca                             | S-04                | FR-007                                                     | proposed |
| S-06 | to-spend-replaces-remaining | użytkownik może odczytać „do wydania” na Dashboardzie i stronie Budżet, także w miesiącu bez planu | S-01                | FR-011, FR-013                                             | done |
| S-07 | month-plan-notices          | użytkownik może zobaczyć na Dashboardzie powiadomienia związane z planem miesiąca                | F-01, S-04, S-05    | FR-012                                                     | proposed |
| S-08 | plan-based-entry-warnings   | użytkownik może zobaczyć przy operacji i w podglądzie Importu ostrzeżenia liczone z planu        | S-01                | FR-014, FR-017                                             | done |
| S-09 | month-summary               | użytkownik może zobaczyć podsumowanie miesiąca, w tym odkładanie zamierzone i realne             | S-02, S-03, S-04    | US-01, FR-009, FR-013                                      | done |
| S-10 | month-plans-vs-year         | użytkownik może porównać plany miesięcy z budżetem rocznym                                       | S-01                | FR-010                                                     | in-progress |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                  | Chain                                     | Note                                                                     |
| ------ | ---------------------- | ----------------------------------------- | ------------------------------------------------------------------------ |
| A      | Plan i liczba          | `F-01` → `S-01` → `S-02` → `S-03` → `S-09` | Najkrótsza droga do użycia na prawdziwym miesiącu; `S-09` dołącza do B przy `S-04`. |
| B      | Pozycje i sygnały      | `S-04` → `S-05` → `S-07`                  | Dołącza do A przy `S-01`; zamienia liczbę w konkretne sygnały w trakcie miesiąca. |
| C      | Zastąpienie odczytów   | `S-06` → `S-08`                           | Dołącza do A przy `S-01`; `S-08` nie zależy od `S-06`, łączy je wspólny temat. |
| D      | Porównanie z rokiem    | `S-10`                                    | Dołącza do A przy `S-01`; niezależne od pozostałych strumieni.            |

## Baseline

What's already in place in the codebase as of `2026-11-02` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — strony Inertia/Vue w `resources/js/pages`, tylko po polsku.
- **Backend / API:** present — trasy sesyjne w `routes/web.php` i `routes/settings.php`; JSON API nie ma.
- **Data:** present — 16 migracji, PostgreSQL. Planu miesiąca ani rodzaju pozycji nie ma w kodzie.
- **Auth:** present — logowanie, 2FA i passkeys przez Fortify, polityki w `app/Policies`, bez samodzielnej rejestracji.
- **Deploy / infra:** present — `docker-compose.prod.yml`, `scripts/deploy.sh`; wdrożenie ręczne, CI tylko testuje.
- **Observability:** partial — log plikowy, `/up`, `backup:monitor`; bez śledzenia błędów i metryk.

Różnice względem dokumentów, istotne dla wycinków: strona Budżet dostaje `Pozostało w miesiącu` w propsach, ale go nie wyświetla (odczyt pokazuje kafelek Dashboardu), `Wynik` jest tylko na stronie Budżet, a żadne powiadomienie nie używa jeszcze wagi `warning`.

## Foundations

### F-01: Decyzje domenowe planu miesiąca

- **Outcome:** (foundation) nowy ADR zastąpił ADR-0024 w części o „drugim budżecie miesięcznym”, a nowe terminy oraz progi powiadomień o wadze `warning` są w `CONTEXT.md`.
- **Change ID:** month-plan-domain-decisions
- **PRD refs:** Constraints & Compatibility, FR-012
- **Unlocks:** S-01 (model planu jest niezgodny z ADR-0024, dopóki nowy ADR go nie zastąpi), S-07 (ADR-0032 wymaga terminu i progów przed kodem powiadomienia `warning`).
- **Prerequisites:** —
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:** —
- **Status:** done
- **Status:** planning

## Slices

### S-01: Plan miesiąca i liczba „do wydania”

- **Outcome:** użytkownik może zobaczyć propozycję planu, poprawić ją, zatwierdzić i odczytać „do wydania”
- **Change ID:** month-plan-and-to-spend
- **PRD refs:** US-01, FR-001, FR-002, FR-005, FR-015, FR-018, FR-019
- **Prerequisites:** F-01
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Wprowadza nowe dane i rodzaj pozycji, więc tu powstaje format snapshotu, a przywracanie wersji 1 do 4 ma działać dalej; błąd we wzorze podważa całą resztę mapy.
- **Status:** done

### S-02: Odkładam / dobieram

- **Outcome:** użytkownik może wpisać w planie kwotę „odkładam / dobieram” i zobaczyć jej wpływ na „do wydania”
- **Change ID:** set-aside-and-draw
- **PRD refs:** FR-004, FR-016
- **Prerequisites:** S-01
- **Parallel with:** S-03, S-04, S-05, S-06, S-07, S-08, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Stoi osobno, bo to pierwsza kwota planu spoza kategorii; ostrzeżenie o dobieraniu ponad Spendable Liquidity nie może zmieniać sald.
- **Status:** done

### S-03: Zmiana zatwierdzonego planu

- **Outcome:** użytkownik może zmienić zatwierdzony plan w trakcie miesiąca
- **Change ID:** approved-plan-changes
- **PRD refs:** FR-003
- **Prerequisites:** S-01
- **Parallel with:** S-02, S-04, S-05, S-06, S-07, S-08, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Podsumowanie potrzebuje planu zatwierdzonego i końcowego, więc oba muszą pozostać rozróżnialne od pierwszej zmiany.
- **Status:** done

### S-04: Pozycje miesiąca: plan i wykonanie

- **Outcome:** użytkownik może zobaczyć plan i faktyczną kwotę każdej pozycji oraz kategorie jednorazowe
- **Change ID:** month-items-plan-vs-actual
- **PRD refs:** FR-006, FR-008
- **Prerequisites:** S-01
- **Parallel with:** S-02, S-03, S-06, S-08, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Stoi wcześnie, bo tempo, powiadomienia i podsumowanie korzystają z tego widoku; ryzyko to zaciemnienie go długą listą kategorii z zerowym planem.
- **Status:** done

### S-05: Tempo wydatków

- **Outcome:** użytkownik może zobaczyć tempo wydatków względem upływu dni miesiąca
- **Change ID:** month-spending-pace
- **PRD refs:** FR-007
- **Prerequisites:** S-04
- **Parallel with:** S-02, S-03, S-06, S-08, S-09, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Fałszywe ostrzeżenia uczą ignorowania sygnału, dlatego tempo obejmuje tylko pozycje „bieżące” bez kategorii jednorazowych.
- **Status:** proposed

### S-06: „Do wydania” zamiast `Pozostało w miesiącu`

- **Outcome:** użytkownik może odczytać „do wydania” na Dashboardzie i stronie Budżet, także w miesiącu bez planu
- **Change ID:** to-spend-replaces-remaining
- **PRD refs:** FR-011, FR-013
- **Prerequisites:** S-01
- **Parallel with:** S-02, S-03, S-04, S-05, S-07, S-08, S-09, S-10
- **Blockers:** —
- **Unknowns:**
  - Czy na stronie Budżet „do wydania” ma być nowym odczytem, skoro `Pozostało w miesiącu` nie jest tam dziś wyświetlane? — Owner: user. Block: no.
- **Risk:** Stary odczyt zostaje dla minionych miesięcy bez planu, więc oba odczyty muszą współistnieć bez mylenia użytkownika.
- **Status:** done

### S-07: Powiadomienia planu miesiąca

- **Outcome:** użytkownik może zobaczyć na Dashboardzie powiadomienia związane z planem miesiąca
- **Change ID:** month-plan-notices
- **PRD refs:** FR-012
- **Prerequisites:** F-01, S-04, S-05
- **Parallel with:** S-02, S-03, S-06, S-08, S-09, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** To pierwsze powiadomienia o wadze `warning`, a powiadomienia o drobiazgach uczą ignorowania strefy uwagi.
- **Status:** proposed

### S-08: Ostrzeżenia przy operacjach liczone z planu

- **Outcome:** użytkownik może zobaczyć przy operacji i w podglądzie Importu ostrzeżenia liczone z planu
- **Change ID:** plan-based-entry-warnings
- **PRD refs:** FR-014, FR-017
- **Prerequisites:** S-01
- **Parallel with:** S-02, S-03, S-04, S-05, S-06, S-07, S-09, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Logika ostrzeżeń istnieje dziś w dwóch kopiach, więc zmiana musi objąć obie, a wprowadzanie operacji nie może stać się dłuższe.
- **Status:** done

### S-09: Podsumowanie miesiąca

- **Outcome:** użytkownik może zobaczyć podsumowanie miesiąca, w tym odkładanie zamierzone i realne
- **Change ID:** month-summary
- **PRD refs:** US-01, FR-009, FR-013
- **Prerequisites:** S-02, S-03, S-04
- **Parallel with:** S-05, S-06, S-07, S-08, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Zamyka US-01 i rozstrzyga odczyt minionego miesiąca z planem, więc musi czekać na odkładanie oraz plan zatwierdzony i końcowy.
- **Status:** done

### S-10: Plany miesięcy a budżet roczny

- **Outcome:** użytkownik może porównać plany miesięcy z budżetem rocznym
- **Change ID:** month-plans-vs-year
- **PRD refs:** FR-010
- **Prerequisites:** S-01
- **Parallel with:** S-02, S-03, S-04, S-05, S-06, S-07, S-08, S-09
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Stoi na końcu, bo nie jest potrzebne do prowadzenia miesiąca, a porównanie nie może zmieniać budżetu rocznego.
- **Status:** in-progress

## Backlog Handoff

| Roadmap ID | Change ID                   | Suggested issue title                                        | Ready for `/10x-plan` | Notes                                              |
| ---------- | --------------------------- | ------------------------------------------------------------ | --------------------- | -------------------------------------------------- |
| F-01       | month-plan-domain-decisions | Zapisać decyzje domenowe planu miesiąca i progi powiadomień  | yes                   | Run `/10x-plan month-plan-domain-decisions`        |
| S-01       | month-plan-and-to-spend     | Plan miesiąca i liczba „do wydania”                          | no                    | Czeka na F-01                                      |
| S-02       | set-aside-and-draw          | Kwota „odkładam / dobieram” w planie miesiąca                | no                    | Czeka na S-01                                      |
| S-03       | approved-plan-changes       | Zmiana zatwierdzonego planu miesiąca                         | no                    | Czeka na S-01                                      |
| S-04       | month-items-plan-vs-actual  | Plan i wykonanie pozycji miesiąca                            | no                    | Czeka na S-01                                      |
| S-05       | month-spending-pace         | Tempo wydatków względem dni miesiąca                         | no                    | Czeka na S-04                                      |
| S-06       | to-spend-replaces-remaining | „Do wydania” na Dashboardzie i stronie Budżet                | no                    | Czeka na S-01                                      |
| S-07       | month-plan-notices          | Powiadomienia Dashboardu związane z planem miesiąca          | no                    | Czeka na F-01, S-04 i S-05                         |
| S-08       | plan-based-entry-warnings   | Ostrzeżenia przy operacjach i w Imporcie liczone z planu     | no                    | Czeka na S-01                                      |
| S-09       | month-summary               | Podsumowanie miesiąca i realne odkładanie                    | no                    | Czeka na S-02, S-03 i S-04                         |
| S-10       | month-plans-vs-year         | Porównanie planów miesięcy z budżetem rocznym                | no                    | Czeka na S-01                                      |

This table is the clean handoff to Jira/Linear or any MCP-backed backlog. Include one row for every `F-NN` and `S-NN`. It should be compact enough to copy into issues, but it must not duplicate the detailed roadmap body.

## Open Roadmap Questions

1. **Jaki jest koszt obecnego obejścia?** Notatki opisują arkusz i jego strukturę, ale nie nazywają ceny jego utrzymania. — Owner: user. Block: none.
2. **Czy są wymiary jakości, w które ta zmiana świadomie nie celuje?** Non-Goals z notatek są wyłącznie funkcjonalne. — Owner: user. Block: none.

## Parked

- **Agenda i terminy płatności** — Why parked: PRD §Non-Goals; rachunek nie ma daty płatności.
- **Współdzielenie z domownikami** — Why parked: PRD §Non-Goals; jedna osoba prowadzi cały dom.
- **Import arkusza** — Why parked: PRD §Non-Goals; bez przenoszenia danych z `budget_szablon.xlsx`.
- **Zmiany budżetu rocznego i sald** — Why parked: PRD §Non-Goals i zabezpieczenia PRD.
- **Przenoszenie reszty i zamykanie miesiąca** — Why parked: PRD §Non-Goals; rozliczenie to tylko podsumowanie.
- **Fundusz wydatków nieregularnych i przelewy na oszczędności** — Why parked: PRD §Non-Goals; oszczędności to jedna kwota.
- **Płatności w częściach** — Why parked: PRD §Non-Goals; reguła zakłada rachunki płacone jednorazowo.
- **Procent od zarobków w „odkładam”** — Why parked: PRD §Non-Goals; pierwsza wersja przyjmuje tylko kwotę.
- **Śledzenie błędów i metryki** — Why parked: punkt bazowy pokazuje obserwowalność częściową, ale PRD jej nie wymaga, więc nie ma tu elementu przygotowawczego.

## Milestone History

(Pusta: to pierwszy kamień milowy.)

## Done

(Pusta przy pierwszym wygenerowaniu. Wpisy dodaje `/10x-archive`.)

- **F-01: (foundation) nowy ADR zastąpił ADR-0024 w części o „drugim budżecie miesięcznym”, a nowe terminy oraz progi powiadomień o wadze `warning` są w `CONTEXT.md`.** — Archived 2026-11-02 → `context/archive/2026-11-02-month-plan-domain-decisions/`. Lesson: —.
- **S-01: użytkownik może zobaczyć propozycję planu, poprawić ją, zatwierdzić i odczytać „do wydania”** — Archived 2026-11-02 → `context/archive/2026-11-02-month-plan-and-to-spend/`. Lesson: —.
- **S-02: użytkownik może wpisać w planie kwotę „odkładam / dobieram” i zobaczyć jej wpływ na „do wydania”** — Archived 2026-11-02 → `context/archive/2026-11-02-set-aside-and-draw/`. Lesson: —.
- **S-03: użytkownik może zmienić zatwierdzony plan w trakcie miesiąca** — Archived 2026-11-02 → `context/archive/2026-11-02-approved-plan-changes/`. Lesson: —.
- **S-04: użytkownik może zobaczyć plan i faktyczną kwotę każdej pozycji oraz kategorie jednorazowe** — Archived 2026-11-02 → `context/archive/2026-11-02-month-items-plan-vs-actual/`. Lesson: —.
- **S-06: użytkownik może odczytać „do wydania” na Dashboardzie i stronie Budżet, także w miesiącu bez planu** — Archived 2026-11-02 → `context/archive/2026-11-02-to-spend-replaces-remaining/`. Lesson: —.
- **S-08: użytkownik może zobaczyć przy operacji i w podglądzie Importu ostrzeżenia liczone z planu** — Archived 2026-11-03 → `context/archive/2026-11-02-plan-based-entry-warnings/`. Lesson: —.
- **S-09: użytkownik może zobaczyć podsumowanie miesiąca, w tym odkładanie zamierzone i realne** — Archived 2026-11-03 → `context/archive/2026-11-03-month-summary/`. Lesson: —.
