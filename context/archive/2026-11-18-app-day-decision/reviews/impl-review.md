<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Jeden dzień aplikacji (App Day) — plan implementacji

- **Plan**: context/changes/app-day-decision/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-19
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated checks (all green, 2026-11-19): `ls context/foundation/adr | grep -c '^0103-'` → 1; `grep -c '^\*\*App Day\*\*:' glossary.md` → 1; `grep -c '(UTC)' glossary.md` → 0; ADR-0103 names 0074, 0075, 0076, 0077, 0081, 0097, 0098; 7 ADRs carry „Superseded in part by ADR-0103” (exactly the planned list); `today('UTC')` in `app` → 20; `before_or_equal:today` → 15; `toISOString().slice(0, 10)` outside tests → 7; diff `e50f8f6..HEAD` touches only `context/`. No other file in `context/foundation` or `.ai/rules` still states „today in UTC” outside the superseded sentences.

Code facts checked against the repo: every file:line in „Spis miejsc w kodzie” and „Current State Analysis” except the two in F1; Laravel `before_or_equal:today` → `Date::parse` in the app zone, no zone parameter; `shell` is a plain shared array dropped by `only` reloads (inertia-laravel 3.7.0 `PropsResolver`); time-zone examples and the DST paragraph (2027: 28.03 02→03, 31.10 03→02 local); 42 server / 14 browser places derivable from the inventory.

## Findings

### F1 — Spis dla S-39: Dockerfile wskazuje etap `assets`, zakres `formatRelativeDate` o 3 linie za długi

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/app-day-decision/plan.md:13, plan.md:47
- **Detail**: Kontrakt fazy 2.2 to „spis zgodny z kodem”. `docker/8.5/Dockerfile.prod:15,20` to `ENV TZ=UTC` i `/etc/localtime` w etapie `assets` (`FROM ubuntu:24.04 AS assets`, linia 11); obraz produkcyjny (`AS runtime`, linia 77) ustawia je w liniach 88 i 92. `lib/format.ts:137-180` (`formatRelativeDate`) kończy się w linii 177, 178–182 to docblock `formatLongDate`. S-39 planuje na tym spisie.
- **Fix**: W plan.md zmienić na `docker/8.5/Dockerfile.prod:15,20,88,92` (etap `assets` i `runtime`) oraz `lib/format.ts:137-177`.
- **Decision**: FIXED

### F2 — Potwierdzenia ręczne 1.6 i 2.5 nieodhaczone, a faza 2 weszła po fazie 1

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/app-day-decision/plan.md (## Progress, 1.6 i 2.5)
- **Detail**: Plan każe zatrzymać się po fazie 1 na ręczne potwierdzenie ADR-0103 i wpisu App Day. Faza 2 jest zacommitowana (9b5c354), a 1.6 i 2.5 są `[ ]`. Albo potwierdzenie padło w sesji i nie zostało zapisane, albo decyzja nie została jeszcze przeczytana. ADR-0103 jest fundamentem S-39…S-43, więc niepotwierdzona decyzja to ryzyko dla kolejnych wycinków.
- **Fix**: Użytkownik czyta ADR-0103, wpis App Day oraz linie „Superseded” w ADR-0097 i ADR-0098, potem odhacza 1.6 i 2.5 w Progress.
- **Decision**: PENDING — ręczne potwierdzenie użytkownika (1.6, 2.5); agent nie odhacza za niego

### F3 — ADR-0103 „Data”: „pierwsza godzina po północy” jest prawdziwa tylko zimą

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/adr/0103-one-app-day-in-europe-warsaw.md:19
- **Detail**: „an archiving in the first hour after Polish midnight moves to the next day” — latem (CEST, UTC+2) dzień w UTC i w Polsce różnią się przez dwie pierwsze godziny po północy. Akapit „We rejected” mówi poprawnie „an hour in winter and two in summer”. Test archiwizacji w S-39 łatwo napisać tylko na zimę.
- **Fix**: „in the first hour (two in summer) after Polish midnight”.
- **Decision**: FIXED

### F4 — Drobne nieścisłości cytatów i spisu

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/adr/0097-report-years-ranking-and-chart-colours.md:5; context/foundation/adr/0103-one-app-day-in-europe-warsaw.md:7; context/changes/app-day-decision/plan.md:13
- **Detail**: (a) Linia „Superseded” w ADR-0097 cytuje „today's day in UTC” z prostym apostrofem, a treść (`:9`) ma „today’s” — grep po cytacie nie trafi. (b) ADR-0103 mówi, że S-39 zastępuje 15 reguł „datą wyliczoną z `AppDay`”; pułapka: `'today Europe/Warsaw'` w regule tekstowej daje 23:00 UTC poprzedniego dnia przy wartości parsowanej o 00:00 UTC i odrzuca sam App Day — działa tylko data `Y-m-d` (np. `Rule::date()->beforeOrEqual(AppDay::today())`). (c) Liczba 42 wychodzi tylko, gdy `ArchiveCategory.php:21,30` liczy się jako jedno miejsce; gość dostaje z `ShellState` nulle, puste listy i `false`, nie same nulle.
- **Fix**: Zmienić apostrof w ADR-0097:5 na „’”, dopisać w ADR-0103 „(a `Y-m-d` date, never `today Europe/Warsaw`)” i doprecyzować w plan.md liczenie ArchiveCategory oraz stan `shell` gościa.
- **Decision**: FIXED
