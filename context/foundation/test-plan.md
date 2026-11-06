# Test Plan

> Phased test rollout for this project. Strategy is frozen at the top
> (§1–§5); cookbook patterns at the bottom (§6) fill in as phases ship.
> Read before writing any new test.
>
> Refresh: re-run `/10x-test-plan --refresh` when stale (see §8).
>
> Last updated: 2026-11-06

Plan jest po polsku jak pozostałe dokumenty w `context/foundation/`; wartości Status w §3 zostają po angielsku, bo czyta je orkiestrator. Terminy domenowe według `context/foundation/glossary.md`.

## 1. Strategy

Tests follow three non-negotiable principles for this project:

1. **Cost × signal.** The cheapest test that gives a real signal for the
   risk wins. Do not promote to e2e because e2e "feels safer." Do not put a
   vision model on top of a deterministic visual diff that already catches
   the regression.
2. **User concerns are first-class evidence.** Risks anchored in "the
   user is worried that Import silently corrupts data, and the failure would
   surface somewhere in the Import area" carry the same weight as PRD lines
   or hot-spot data.
3. **Risks are scenarios, not code locations.** This plan documents *what
   could fail* and *why we believe it's likely* — drawn from documents,
   interview, and codebase *signal* (churn, structure, test base). It does
   NOT claim to know which line owns the failure. That knowledge is
   produced by `/10x-research` during each rollout phase. If the plan and
   research disagree about where the failure lives, research is the
   ground truth.

Hot-spot scope used for likelihood weighting: `app/`, `resources/js/` (bez wygenerowanych `resources/js/actions`, `resources/js/routes` i `components/ui`), `routes/`, `database/migrations/` — 126 commitów w ciągu 30 dni do 2026-11-06.

Ograniczenie projektu dla każdego etapu: testy migracji, surowego SQL, dat, JSON i ograniczeń bazy dowodzą czegoś tylko na PostgreSQL (`AGENTS.md`, Hard rules). Zielony przebieg na SQLite nie zamyka żadnego ryzyka z §2.

## 2. Risk Map

The top failure scenarios this project must protect against, ordered by
risk = impact × likelihood. Risks are failure scenarios in user / business
terms, not test names. The Source column cites the *evidence that surfaced
this risk* — never a specific file as "where the failure lives" (that is
research's job, see §1 principle #3).

| # | Risk (failure scenario) | Impact | Likelihood | Source (evidence — not anchor) |
|---|---|---|---|---|
| 1 | Zatwierdzenie Importu po cichu dubluje albo gubi operacje: wyciąg nakłada się na wcześniej zaimportowany, bank zmienił format, preambułę albo układ kolumn, a częściowa porażka nie wycofuje całego zestawu | High | High | interview Q1, Q2; `context/foundation/prd-mvp.md` §9 (atomowość zestawu, duplikaty, preambuła), NFR-DAT-01; hot-spot dir `app/Support/CsvImport` (9 zmian plików/30d, 24 commity/60d) |
| 2 | „do wydania” w podglądzie przeglądarki (TS) różni się od liczby z serwera (PHP), zwłaszcza po zmianie reguły dla wydatku nieregularnego | High | High | roadmap S-14 Risk („dwie kopie kalkulatorów (PHP i TS)”); `prd.md` Wymagania jakościowe (aktualna i poprawna liczba po operacji i Imporcie); hot-spot dirs `app/Support`, `resources/js/lib` (109 zmian plików/30d) |
| 3 | Sumy raportu okresu nie zgadzają się co do grosza z wykonaniem w budżecie rocznym albo z Wynikiem miesiąca (zwroty, transfery własne, ruchy kapitału, zarchiwizowane kategorie, granica miesiąca i roku) | High | High | `prd-v2.md` Guardrails i „najszybszy sygnał awarii”, US-01, US-02; roadmap S-11 (status planning), S-12; `context/foundation/prd-mvp.md` BR-01, BR-02, BR-08, BR-09, BR-19 |
| 4 | Podpowiedzi kategorii w Imporcie trafiają gorzej po zmianie łączenia kategorii między latami, a spadku nikt nie mierzy | Medium | High | `prd-v2.md` FR-013, Guardrails; roadmap S-16 Risk (pomiar trafności przed i po); interview Q1 |
| 5 | Backup (`user:backup` i backup aplikacji) kończy się sukcesem, ale snapshot jest niekompletny: pomija encję, nowe pola albo plany miesięcy. Albo backup pada bez czytelnego sygnału i przed wdrożeniem nie ma kopii, do której można wrócić | High | Medium | user comment on the seed brief („kluczowa akcja”); `prd-v2.md` Wycofanie zmiany (backup przed wdrożeniem), FR-014; `prd.md` Kompatybilność wsteczna; roadmap S-14 i S-16 dodają dane do snapshotu; roadmap Baseline (`backup:monitor`) |
| 6 | `user:restore` snapshotu w starszej wersji (1–5) wczytuje się bez błędu, ale gubi plany miesięcy, powiązania albo kwoty | High | Medium | `prd.md` Kompatybilność wsteczna (wersje 1 do 4 działają dalej); `prd-v2.md` FR-014 (stary format z wartościami domyślnymi); roadmap S-14, S-16 (snapshot v6, uzgodnienie numerów wersji) |
| 7 | Krytyczny przepływ w przeglądarce (wgranie pliku → podgląd → zatwierdzenie Importu, dialog operacji, plan miesiąca) przestaje działać, choć testy HTTP i Vitest przechodzą | Medium | High | interview Q4; hot-spot dirs `resources/js/components` (231 zmian plików/30d), `resources/js/pages` (106); brak warstwy e2e (profil bazy testów) |
| 8 | (nadużycie) Nowa trasa albo filtr (raporty, przejście z raportu do operacji) zwraca albo zmienia dane innego użytkownika: uwierzytelnienie jest sprawdzane, własność zasobu nie | High | Low | `context/foundation/prd-mvp.md` NFR-SEC-01, NFR-SEC-02; roadmap S-11, S-13 (nowe trasy i filtry kategorii i okresu); `prd-v2.md` Wymagania jakościowe (izolacja danych użytkownika) |

### Risk Response Guidance

| Risk | What would prove protection | Must challenge | Context `/10x-research` must ground | Likely cheapest layer | Anti-pattern to avoid |
|------|-----------------------------|----------------|--------------------------------------|-----------------------|-----------------------|
| #1 | Ponowny Import nakładającego się wyciągu nie tworzy żadnej nowej operacji. Wariant formatu albo preambuły daje komplet wierszy. Błąd jednego wybranego wiersza wycofuje cały zestaw: liczba operacji i salda kont przed i po są równe | „Podgląd oznaczył duplikat, więc zapis go nie utworzy” | Granica podgląd → zatwierdzenie, tożsamość wiersza Importu, transakcja zapisu, obsługiwane formaty banków, zanonimizowane prawdziwe warianty wyciągów jako fixture | integracja (Feature na PostgreSQL) z plikami fixture | Fixture wygenerowany przez parser zamiast z prawdziwego wyciągu; asercja tylko na odpowiedzi HTTP, bez stanu bazy |
| #2 | Jedna tabela przypadków (wejście → oczekiwane „do wydania”, wyliczone ręcznie z reguły To Spend w `context/foundation/glossary.md`) przechodzi po stronie PHP i TS | „Kopia TS jest przepisana 1:1, więc liczy to samo” | Które kalkulatory mają dwie kopie, skąd każda bierze wejścia, gdzie reguła jest zapisana, jak S-14 ją zmieni | test kontraktu i parzystości: unit po obu stronach na wspólnym fixture | Problem wyroczni: oczekiwane wartości wyliczone kodem PHP i wklejone do testu TS |
| #3 | Na zestawie operacji z ręcznie wyliczonym wynikiem (zwrot, transfer własny, ruch kapitału, zarchiwizowana kategoria, operacja na granicy miesiąca i roku) raport = wykonanie w budżecie rocznym = Wynik miesiąca | „Obie liczby pochodzą z tego samego odczytu, więc się zgadzają” | Odczyty liczące wykonanie i Wynik miesiąca, co testy S-11 już pokrywają, reguła przynależności operacji do budżetu (BR-13) | integracja na PostgreSQL | Asercja raport == inny odczyt (dwa błędy się znoszą) zamiast niezależnej, ręcznie policzonej wyroczni |
| #4 | Stały zestaw referencyjny (opis z wyciągu → oczekiwana kategoria) ma trafność co najmniej równą zapisanemu poziomowi bazowemu, a spadek czerwieni bramkę | „Testy jednostkowe podpowiadacza przechodzą, więc trafność się nie zmieniła” | Jak podpowiadacz łączy kategorie między latami, źródło zestawu referencyjnego (prawdziwe opisy po porządkowaniu kategorii, przed S-16), sposób zapisu poziomu bazowego | test benchmarku (integracja) z progiem | Próg dopasowany do bieżącego wyniku bez zapisanego pomiaru „przed” |
| #5 | Backup i restore do pustej bazy dają te same liczności i sumy kwot na każdą tabelę użytkownika. Strażnik kompletności czerwieni się, gdy nowa tabela albo kolumna z danymi użytkownika nie trafia do snapshotu. Błąd backupu daje niezerowy kod wyjścia i nie zostawia pliku udającego kompletny | „Plik powstał i jest poprawnym JSON, więc backup jest pełny” | Zakres `user:backup` i backupu aplikacji, lista tabel z danymi użytkownika, obsługa błędów i kod wyjścia, istniejące testy backupu | integracja (Artisan na PostgreSQL) | Test, który sprawdza tylko istnienie pliku albo klucze najwyższego poziomu |
| #6 | Zamrożony snapshot każdej wspieranej wersji wczytuje się do pustej bazy, a kwoty, powiązania i liczności są równe oczekiwanym, z wartościami domyślnymi dla pól spoza danej wersji | „Restore bez wyjątku oznacza pełny restore” | Formaty v1–v5 i encje każdej wersji, mapowanie wartości domyślnych, istniejące testy round-trip | integracja (Artisan) z zamrożonymi plikami snapshotów w repo | Snapshot generowany bieżącym kodem backupu, który nigdy nie sprawdza starego formatu |
| #7 | Kilka testów w przeglądarce przechodzi krytyczne przepływy do końca, bez błędów JS, i widzi ich skutek (operacja na liście, zmienione „do wydania” po Imporcie) | „Kitchen sink się renderuje, więc przepływ działa” | Które przepływy zależą od stanu klienta (dialogi, wgranie pliku, podgląd), czego nie łapią testy `assertInertia` i Vitest, jak uruchomić aplikację z bazą testową | e2e: 3–5 testów (Pest Browser plugin albo Playwright; dodanie zależności wymaga zgody użytkownika) | Zrzuty pikselowe przy ciągłych restylach Atrament; e2e dla zachowania, które łapie test Feature |
| #8 | Dla każdej trasy z zasobem albo filtrem drugi użytkownik dostaje 403/404 i nie widzi cudzych kwot w odpowiedzi | „Middleware `auth` wystarczy” | Lista tras z wiązaniem modeli i filtrami, polityki, wzorzec istniejących testów izolacji | integracja: jeden test przeglądający trasy | Test tylko dla nowych tras, który nie złapie następnych |

## 3. Phased Rollout

Each row is a discrete rollout phase that will open its own change folder
via `/10x-new`. Status moves left-to-right through the values below; the
orchestrator updates Status as artifacts appear on disk.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Integralność Importu | Dowieść, że Import nie dubluje, nie gubi i nie zostawia połowicznych zestawów, i zapisać poziom bazowy trafności podpowiedzi przed S-16 | #1, #4 | integracja + benchmark | change opened | `context/changes/testing-import-integrity/` |
| 2 | Niezmienniki kwot | Dowieść zgodności „do wydania” PHP↔TS przed S-14 oraz zgodności raportu z wykonaniem i Wynikiem miesiąca po S-11 | #2, #3 | kontrakt/parzystość + integracja | not started | — |
| 3 | Backup i restore | Dowieść, że backup jest kompletny i głośno pada, a restore v1–v5 jest bezstratny, zanim S-14/S-16 wprowadzą v6 | #5, #6 | integracja | not started | — |
| 4 | Przepływy w przeglądarce | Dowieść, że krytyczne przepływy działają w prawdziwej przeglądarce bez błędów JS | #7 | e2e | not started | — |
| 5 | Bramki jakości | Utrwalić poziom: przegląd tras pod izolację, testy `arch()` dla twardych zasad, testy pod PHPStan, e2e w CI | #8, przekrojowo | integracja + bramki | not started | — |

Kolejność: etap 1 to obawa nr 1 i wcześniejsza wpadka użytkownika, a poziom „przed” dla #4 musi powstać przed S-16. Etap 2 musi wylądować przed zmianą reguły w S-14; część #3 czeka na S-11. Etap 3 stoi przed v6 snapshotu. Etap 4 wymaga decyzji o zależności. Etap 5 utrwala to, co dostarczą etapy 1–4. Warstwa AI-native nie ma etapu: deterministyczne testy dają sygnał dla każdego ryzyka.

## 4. Stack

The classic test base for this project. AI-native tools (if any) carry a
`checked:` date so future readers can see which lines need re-verification.

Profil bazy testów: **meaningful**. Skonfigurowane Pest i Vitest, 94 pliki testów PHP (Feature, Unit, Nfr) i 26 plików `*.test.ts` w `resources/js/lib`, bramka `composer ci:check` w CI na PostgreSQL 18 i PHP 8.5.

| Layer | Tool | Version | Notes |
|---|---|---|---|
| unit + integration (PHP) | Pest (PHPUnit) | 5.3 (13.4) | `phpunit.xml` domyślnie SQLite w pamięci; dowód dla bazy tylko na PostgreSQL (komendy w `AGENTS.md`) |
| wydajność (NFR) | Pest, grupa `nfr` | 5.3 | `phpunit.nfr.xml`, PostgreSQL; wzorzec 2 s przy 10 000 operacji |
| unit (TS) | Vitest przez `vp test` (vite-plus) | 5 (vite-plus 1.4) | pliki `*.test.ts` obok logiki w `resources/js/lib` |
| kontrakt PHP ↔ Vue | `assertInertia` w testach Feature | — | wymóg z `AGENTS.md` dla każdego propsa Inertia |
| strażnik UI | `npm run check:ui` | — | skaner literałów UI i tokenów Atrament |
| e2e | none yet — see Phase 4 | — | kandydaci: Pest Browser plugin (`pestphp/pest-plugin-browser` + Playwright) albo `@playwright/test`; zależność wymaga zgody użytkownika |
| testy architektury | none yet — see Phase 5 | — | Pest `arch()` |

**Stack grounding tools (current session):**
- Docs: Laravel Boost `search-docs` (pestphp/pest 5.x) — potwierdzono Browser Testing w Pest 5: plugin `pestphp/pest-plugin-browser` na Playwright, `visit()`, `assertNoJavaScriptErrors`, `assertNoSmoke`, `assertNoAccessibilityIssues`, tryb ciemny i urządzenia mobilne; checked: 2026-11-06
- Search: Exa (`web_search_exa`) — dostępne, nieużyte; oficjalna dokumentacja przez Boost wystarczyła; checked: 2026-11-06
- Runtime/browser: none — w tej sesji nie ma MCP Playwright ani narzędzia przeglądarki; etap 4 dobiera runner w kodzie testów, nie przez MCP; checked: 2026-11-06
- Provider/platform: `gh` CLI dla GitHub Actions (historia przebiegów CI); GitLab MCP nie połączył się w tej sesji; checked: 2026-11-06

## 5. Quality Gates

The full set of gates that must pass before a change reaches production.
"Required for §3 Phase <N>" means the gate is enforced once that rollout
phase lands; before that, the gate is `planned`.

| Gate | Where | Required? | Catches |
|---|---|---|---|
| lint + format (`vp check`, Pint), typecheck (`vue-tsc`, PHPStan poziom 7), Sloppy | local + CI | required | dryf składni i typów, wzorce zapytań w pętli |
| unit + integration na PostgreSQL (`composer ci:check`) | local (Sail) + CI | required | regresje logiki i odczytów |
| testy integralności Importu i benchmark trafności podpowiedzi | local + CI | required after §3 Phase 1 | duplikaty i zgubione wiersze, spadek trafności |
| testy parzystości „do wydania” PHP↔TS i niezmienników raportu | local + CI | required after §3 Phase 2 | rozjazd kopii kalkulatorów, sumy różne od budżetu |
| round-trip backup/restore i strażnik kompletności snapshotu | local + CI | required after §3 Phase 3 | niekompletny backup, stratny restore starszych wersji |
| e2e krytycznych przepływów | CI on PR | required after §3 Phase 5 (planned in Phase 4) | zepsute przepływy w przeglądarce |
| przegląd tras pod izolację, `arch()`, PHPStan na `tests/` | local + CI | required after §3 Phase 5 | dostęp do cudzych danych, złamane twarde zasady |

## 6. Cookbook Patterns

How to add new tests in this project. Each sub-section is filled in once
the relevant rollout phase ships; before that, the sub-section reads
"TBD — see §3 Phase <N>."

### 6.1 Adding a unit test

- **PHP**: `tests/Unit/`, `php artisan make:test --pest --unit <Name>Test`. Reference test: `tests/Unit/ToSpendCalculatorTest.php`.
- **TS**: `*.test.ts` obok modułu w `resources/js/lib/`. Reference test: `resources/js/lib/to-spend-calculator.test.ts`.
- **Run locally**: `php artisan test --compact <path>` (Sail), `npm test`.
- Parzystość PHP↔TS na wspólnym fixture: TBD — see §3 Phase 2 (wzorzec ochrony przed rozjazdem kopii kalkulatora).

### 6.2 Adding an integration test

- **Location**: `tests/Feature/`, `php artisan make:test --pest <Name>Test`. Dane przez fabryki modeli; daty jawne, nie losowe.
- **Run locally on PostgreSQL**: komenda z `AGENTS.md` (Commands), dla testów z bazą zawsze.
- Import z prawdziwymi wariantami wyciągów i stanem bazy przed i po: TBD — see §3 Phase 1 (wzorzec dla duplikatów, zgubionych wierszy i atomowości zestawu).
- Niezmiennik raportu z ręcznie policzoną wyrocznią: TBD — see §3 Phase 2.

### 6.3 Adding an e2e test

- TBD — see §3 Phase 4 (krytyczny przepływ w przeglądarce bez błędów JS, ze skutkiem widocznym na stronie).

### 6.4 Adding a test for a new route or Inertia page

- **Pattern now**: test Feature z `actingAs` i `assertInertia` dla każdego propsa (`AGENTS.md`).
- Kontrola własności dla drugiego użytkownika: TBD — see §3 Phase 5 (wzorzec odmowy dostępu do cudzych danych).

### 6.5 Adding data covered by backup and restore

- TBD — see §3 Phase 3 (wzorzec round-tripu, strażnika kompletności i zamrożonego snapshotu dla każdej wersji).

### 6.6 Per-rollout-phase notes

(Po każdym etapie `/10x-implement` dopisuje tu 2–3 linie o tym, czego etap nauczył.)

## 7. What We Deliberately Don't Test

Exclusions agreed during the rollout (Phase 2 interview, Q5). Future
contributors should respect these unless the underlying assumption changes.

- **Lokaty (Term Deposit) i ruchy kapitału (Capital Movement)** — bez nowych testów ponad istniejące. Ocenić ponownie, gdy zmiana dotknie ich reguł, Importu ruchów kapitału albo Total funds. (Source: Phase 2 interview Q5.)
- **Zrzuty pikselowe widoków** — nie wykrywają regresji przy ciągłych restylach Atrament; stan widoku chronią kitchen sinki `/dev/...` i `npm run check:ui`. Ocenić ponownie, gdy design system się ustabilizuje. (Source: challenger pass, seed brief.)

## 8. Freshness Ledger

- Strategy (§1–§5) last reviewed: 2026-11-06
- Stack versions last verified: 2026-11-06
- AI-native tool references last verified: 2026-11-06

Refresh (`/10x-test-plan --refresh`) when:

- a new top-3 risk surfaces from the roadmap or archive,
- a recommended tool's `checked:` date is older than three months,
- the project's tech stack changes (new framework, new test runner),
- §7 negative-space no longer matches what the team believes.
