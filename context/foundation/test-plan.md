# Test Plan

> Phased test rollout for this project. Strategy is frozen at the top
> (§1–§5); cookbook patterns at the bottom (§6) fill in as phases ship.
> Read before writing any new test.
>
> Refresh: re-run `/10x-test-plan --refresh` when stale (see §8).
>
> Last updated: 2026-11-19 (e2e w CI)

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

Hot-spot scope used for likelihood weighting: `app/`, `resources/js/` (bez wygenerowanych `resources/js/actions`, `resources/js/routes` i `components/ui`), `routes/`, `database/migrations/` — 372 commity w oknie 30 dni od 2026-10-20 do 2026-11-19.

Ograniczenie projektu dla każdego etapu: testy migracji, surowego SQL, dat, JSON i ograniczeń bazy dowodzą czegoś tylko na PostgreSQL (`AGENTS.md`, Hard rules). Zielony przebieg na SQLite nie zamyka żadnego ryzyka z §2.

## 2. Risk Map

The top failure scenarios this project must protect against, ordered by
risk = impact × likelihood. Risks are failure scenarios in user / business
terms, not test names. The Source column cites the *evidence that surfaced
this risk* — never a specific file as "where the failure lives" (that is
research's job, see §1 principle #3).

| # | Risk (failure scenario) | Impact | Likelihood | Source (evidence — not anchor) |
|---|---|---|---|---|
| 1 | Zatwierdzenie Importu po cichu dubluje, gubi albo zapisuje inne operacje niż w pliku: wyciąg nakłada się na wcześniej zaimportowany, bank zmienił format, preambułę albo układ kolumn, zmiana formatu komórki sprawia, że wiersz znika, kwota w obcej walucie ląduje jako PLN, a częściowa porażka nie wycofuje całego zestawu | High | High | wywiad 2026-11-19 P1, P2; interview Q1, Q2; `context/foundation/prd-mvp.md` §9 (atomowość zestawu, duplikaty, preambuła), NFR-DAT-01; issue #271, #272; `context/archive/2026-11-17-testing-import-integrity/`; audyt obserwowalności 2026-11-19 IMP-1; hot-spot dir `app/Support/CsvImport` (52 zmiany plików/30d) |
| 2 | „do wydania” w podglądzie przeglądarki (TS) różni się od liczby z serwera (PHP), zwłaszcza po zmianie reguły dla wydatku nieregularnego | High | High | roadmap S-26 (M-3, `context/archive/2026-11-11-irregular-expense-collected/plan.md`, obie kopie zmienione razem); `.ai/rules/month-calculators.md` (zgodność kopii tylko z konwencji); `prd.md` Wymagania jakościowe (aktualna i poprawna liczba po operacji i Imporcie); hot-spot dirs `app/Support` (347 zmian plików/30d), `resources/js/lib` (425) |
| 3 | Sumy raportu okresu nie zgadzają się co do grosza z wykonaniem w budżecie rocznym albo z Wynikiem miesiąca (zwroty, transfery własne, ruchy kapitału, zarchiwizowane kategorie, granica miesiąca i roku) | High | Medium | `prd-v3.md` Guardrails (sumy raportu = wykonanie Budżetu i Wynik miesiąca), ADR-0070; `prd-v2.md` Guardrails i „najszybszy sygnał awarii”, US-01, US-02; roadmap S-28…S-32 (M-3), S-34 i S-35 (M-4), S-43 (cięcie „Lat” w styczniu); `context/foundation/prd-mvp.md` BR-01, BR-02, BR-08, BR-09, BR-19 |
| 4 | Podpowiedzi kategorii w Imporcie trafiają gorzej po zmianie kodu podpowiadacza albo listy kategorii (scalenie, archiwum), a spadku nie łapie żadna bramka | Medium | High | `prd-v3.md` FR-020 i Guardrails; S-23 `measurements.md`; interview Q1 |
| 5 | Backup (`user:backup` i backup aplikacji) kończy się sukcesem, ale snapshot jest niekompletny: pomija encję, nowe pola albo plany miesięcy. Albo backup pada bez czytelnego sygnału i przed wdrożeniem nie ma kopii, do której można wrócić | High | High | user comment on the seed brief („kluczowa akcja”); `prd-v2.md` Wycofanie zmiany (backup przed wdrożeniem), FR-014; `prd.md` Kompatybilność wsteczna; osiem podbić wersji snapshotu v6→v13 bez strażnika kompletności (`.ai/rules/snapshot.md`), z czego wynika High; roadmap Baseline (`backup:monitor`); audyt obserwowalności 2026-11-19 BKP-3 (cichy nieudany zapis snapshotu), naprawiony w zmianie `backup-signals` |
| 6 | `user:restore` snapshotu w wersji starszej niż bieżąca wczytuje się bez błędu, ale gubi plany miesięcy, powiązania albo kwoty | High | Low | `prd.md` Kompatybilność wsteczna (wersje 1 do 4 działają dalej); `prd-v2.md` FR-014 (stary format z wartościami domyślnymi); zamrożone snapshoty v5/v6/v8/v9 z testami restore dla każdej wersji (`.ai/rules/snapshot.md`) |
| 7 | Krytyczny przepływ w przeglądarce (wgranie → podgląd → zatwierdzenie Importu, dialog operacji, plan miesiąca) psuje się po merge'u, a CI jest zielone, bo specy E2E działają tylko lokalnie | Medium | High | wywiad 2026-11-19 P4; interview Q4; `context/foundation/test-stack.md`; roadmap S-44 Risk; hot-spot dirs `resources/js/components` (550 zmian plików/30d), `resources/js/pages` (340) |
| 8 | (nadużycie) Nowa trasa albo filtr (raporty, przejście z raportu do operacji) zwraca albo zmienia dane innego użytkownika: uwierzytelnienie jest sprawdzane, własność zasobu nie | High | Low | `context/foundation/prd-mvp.md` NFR-SEC-01, NFR-SEC-02; roadmap S-21, S-22, S-31 (M-3: nowe trasy i filtry kategorii i okresu); `prd-v2.md` Wymagania jakościowe (izolacja danych użytkownika) |
| 9 | Wokół północy w Polsce, zwłaszcza 31.12 23:00 UTC = 1.01 00:00, serwer, menu roku i dialogi pokazują różny dzień albo rok. Rok w sesji zostaje stary, plan grudnia nie daje się zatwierdzić i traci kwoty, Import przy szkicu nowego roku odrzuca cały wyciąg, a „Lata” porównują jeden dzień | High | High | `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` (luki 1–5); roadmap M-5 MS-01…MS-05; F-08 (ADR-0103); audyt obserwowalności 2026-11-19 MPL-1, IMP-2 |

Numery ryzyk są stałe między refreshami, więc kolejność wierszy nie jest już kolejnością priorytetu. Priorytet wyznacza kolejność etapów w §3.

### Risk Response Guidance

| Risk | What would prove protection | Must challenge | Context `/10x-research` must ground | Likely cheapest layer | Anti-pattern to avoid |
|------|-----------------------------|----------------|--------------------------------------|-----------------------|-----------------------|
| #1 | Każda zapisana operacja ma (datę, kwotę ze znakiem, rachunek) równe krotkom spisanym ręcznie z pliku. Do tego dotychczasowe dowody: ponowny Import nakładającego się wyciągu nie tworzy nowej operacji, wariant formatu albo preambuły daje komplet wierszy (dowodzi go ręcznie policzona liczba wierszy pliku, wyrocznia, nie parser), a błąd jednego wiersza wycofuje cały zestaw (liczba operacji i salda kont przed i po są równe). Po decyzji o FR-IMP-08/10 wiersz nieczytelny i kwota w obcej walucie dają widoczny skutek zgodny z nową regułą | „Liczba operacji się zgadza, więc treść też”; „podgląd jest poprawny, więc zapis też”; „podgląd oznaczył duplikat, więc zapis go nie utworzy” | Granica podgląd → zapis (obie strony biorą wartości z sesji), znak kwoty a typ operacji, rachunek z sesji, tożsamość wiersza Importu, transakcja zapisu, FR-IMP-08/10 jako obowiązujące reguły, formaty z #271, kolumna waluty z #272, zanonimizowane prawdziwe warianty wyciągów jako fixture | integracja na PostgreSQL z fixture | Krotki odczytane parserem zamiast ręcznie z pliku; fixture wygenerowany przez parser; asercja tylko na odpowiedzi HTTP; asercja #271/#272 przed zmianą PRD/ADR |
| #2 | Jedna tabela przypadków (wejście → oczekiwane „do wydania”, wyliczone ręcznie z reguły To Spend w `context/foundation/glossary.md`) przechodzi po stronie PHP i TS | „Kopia TS jest przepisana 1:1, więc liczy to samo”; „wejścia, które TS dostaje od serwera, są te same, które liczy PHP” (ścieżka wejść `EntryToSpendInputs`) | Które kalkulatory mają dwie kopie, skąd każda bierze wejścia, gdzie reguła jest zapisana, jak S-26 zmienił obie kopie, która kopia liczy na produkcji, skąd TS bierze wejścia (`EntryToSpendInputs`) | test kontraktu i parzystości: unit po obu stronach na wspólnym fixture | Problem wyroczni: oczekiwane wartości wyliczone kodem PHP i wklejone do testu TS |
| #3 | Na zestawie operacji z ręcznie wyliczonym wynikiem (zwrot, transfer własny, ruch kapitału, zarchiwizowana kategoria, operacja na granicy miesiąca i roku) raport = wykonanie w budżecie rocznym = Wynik miesiąca | „Obie liczby pochodzą z tego samego odczytu, więc się zgadzają”; „istniejące testy porównują odczyt z odczytem” | Odczyty liczące wykonanie i Wynik miesiąca, istniejące testy raport = Wynik miesiąca i wykonanie (`PeriodReportTest`, `ReportYearsTest`, `BudgetMonthResultTest`), które odczyty dzielą `MonthCategoryActuals`, ruchy kapitału poza raportem okresu, reguła przynależności operacji do budżetu (BR-13) | integracja na PostgreSQL | Asercja raport == inny odczyt (dwa błędy się znoszą) zamiast niezależnej, ręcznie policzonej wyroczni |
| #4 | Stały zestaw referencyjny (pseudonimizowany opis → oczekiwana kategoria) ma trafność co najmniej równą zapisanemu poziomowi bazowemu, a spadek czerwieni bramkę | „Testy jednostkowe podpowiadacza przechodzą, więc trafność się nie zmieniła” | Głos po id kategorii listy plus oferta `CategoryChoiceOptions`; zestaw pseudonimizowany bez tekstu z banku, z poziomem bazowym zapisanym w repo razem z zestawem | test benchmarku (integracja) z progiem | Próg obniżony do bieżącego wyniku po spadku albo poziom bazowy spoza repo |
| #5 | Backup i restore do pustej bazy dają te same liczności i sumy kwot na każdą tabelę użytkownika. Strażnik kompletności czerwieni się, gdy nowa tabela albo kolumna z danymi użytkownika nie trafia do snapshotu. Błąd backupu daje niezerowy kod wyjścia i nie zostawia pliku udającego kompletny | „Plik powstał i jest poprawnym JSON, więc backup jest pełny” | Zakres `user:backup` i backupu aplikacji, lista tabel z danymi użytkownika, obsługa błędów i kod wyjścia, istniejące testy backupu, istniejący test nieudanego zapisu snapshotu (`backup-signals`); luka to strażnik kompletności | integracja (Artisan na PostgreSQL) | Test, który sprawdza tylko istnienie pliku albo klucze najwyższego poziomu |
| #6 | Zamrożony snapshot każdej wspieranej wersji wczytuje się do pustej bazy, a kwoty, powiązania i liczności są równe oczekiwanym, z wartościami domyślnymi dla pól spoza danej wersji | „Restore bez wyjątku oznacza pełny restore” | Wersje 1–13, zamrożone v5/v6/v8/v9, wersje budowane w teście, reguła restore dla każdej wersji, encje każdej wersji, mapowanie wartości domyślnych, istniejące testy round-trip | integracja (Artisan) z zamrożonymi plikami snapshotów w repo | Snapshot generowany bieżącym kodem backupu, który nigdy nie sprawdza starego formatu |
| #7 | CI na pushu i PR do `development` uruchamia specy-bramki. Każdy pada, gdy jego przepływ nie kończy się skutkiem na stronie albo rzuca błąd JS, co sprawdza celowe zepsucie przepływu | „15 speców przechodzi lokalnie, więc wszystkie nadają się na bramkę”; „zielone po powtórce = stabilne” | Brak speca zatwierdzenia Importu, powtórki w CI, serwer E2E zależny od sieci Sail, seed zależny od daty uruchomienia (1–14 stycznia), użytkownik testowy tylko w lokalnej bazie e2e (dane logowania w `.env`), rok budżetu w specach z zegara runnera, stan globalny między specami przy jednym workerze | e2e w CI | Wszystkie 15 speców jako bramka; `retries` maskujące niestabilny spec; spec bez asercji skutku |
| #8 | Dla każdej trasy z zasobem albo filtrem drugi użytkownik dostaje 403/404 i nie widzi cudzych kwot w odpowiedzi | „Middleware `auth` wystarczy” | Lista tras z wiązaniem modeli i filtrami, polityki, wzorzec istniejących testów izolacji | integracja: jeden test przeglądający trasy | Test tylko dla nowych tras, który nie złapie następnych |
| #9 | Przy czasie zamrożonym na 31.12 22:59:59 i 23:00:00 UTC (przełom roku) oraz 30.06 21:59:59 i 22:00:00 UTC (przełom miesiąca w czasie letnim) props serwera („dziś” powłoki, rok Dashboardu, rok budżetu) dają ten sam App Day w Europe/Warsaw. Od S-39 przeglądarka bierze dzień tylko z `shell.today`. Testy `_known_gap` S-40 i S-42 przepisują na wymagane zachowanie (zmiana wymagania, nie strojenie asercji) | „Zielone testy na północy UTC dowodzą zachowania o północy w Polsce”; „poprawka sprawdzona w jednym dialogu działa we wszystkich” | Źródła „dziś” na serwerze i w przeglądarce, ADR-0103 i merge F-08, testy przypięte do północy UTC (zmiana wymagania w S-39), brak fake timerów w Vitest i TZ w CI | integracja (zamrożony czas + `assertInertia`) + unit TS z dniem jako argumentem + strażnik statyczny zegara w JS; e2e tylko wizualnie przez zegar próby | Testy tylko na północy UTC; zamrożenie czasu tylko w PHP, gdy przeglądarka ma własny zegar |

## 3. Phased Rollout

Each row is a discrete rollout phase that will open its own change folder
via `/10x-new`. Status moves left-to-right through the values below; the
orchestrator updates Status as artifacts appear on disk.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Integralność Importu | Dowieść, że Import nie dubluje, nie gubi i nie zostawia połowicznych zestawów, i zapisać poziom bazowy trafności podpowiedzi na stałym zestawie referencyjnym | #1, #4 | integracja + benchmark | complete | `context/archive/2026-11-17-testing-import-integrity/` |
| 2 | Bramki E2E w CI | CI na pushu i PR do `development` czerwieni się, gdy merge psuje zatwierdzenie Importu, dialog operacji albo plan miesiąca; bramka tylko ze stabilnych speców, bez powtórek (roadmap S-44, zmiana `e2e-in-ci`) | #7 | e2e w CI | not started | — |
| 3 | Wierność Importu | Zapisana operacja = transakcja z pliku (data, kwota ze znakiem, rachunek); po decyzji o FR-IMP-08/10 przypięte #271 i #272 | #1 | integracja | not started | — |
| 4 | Backup i restore | Strażnik kompletności snapshotu czerwieni się przy tabeli albo kolumnie z danymi użytkownika spoza snapshotu, restore każdej starszej wersji jest bezstratny (głośna porażka zapisu ma już test z `backup-signals`) | #5, #6 | integracja | not started | — |
| 5 | Niezmienniki kwot | „do wydania” PHP = TS na wspólnej tabeli przypadków; raport = wykonanie = Wynik miesiąca na ręcznej wyroczni | #2, #3 | kontrakt/parzystość + integracja | complete | `context/archive/2026-11-18-testing-amount-invariants/` |
| 6 | Bramki jakości | Przegląd tras pod izolację, `arch()` dla twardych zasad, PHPStan na `tests/` | #8, przekrojowo | integracja + bramki | not started | — |

Kolejność:

- Etap 2 jest jedynym etapem planu testów w M-5 (roadmap S-44); etapy 4 i 6 są w Parked, a etap 5 przeszedł poza M-5 w zmianie `testing-amount-invariants` (otwartej 2026-11-18 przed odświeżeniem planu, zarchiwizowanej 2026-11-19). Etap 2 realizuje zmiana o id `e2e-in-ci`: `/10x-new` S-44 używa tego id (nie `testing-…`), a orkiestrator przy następnym wywołaniu przestawia wiersz na change opened z folderem `context/changes/e2e-in-ci/`. Ta zmiana aktualizuje też `context/foundation/test-stack.md` (opis #7 przy `seed` i fakty CI).
- Etap 3 to obawa nr 1 i zaczyna się od decyzji produktowej o FR-IMP-08/10.
- Etap 4 wyprzedza etap 5, bo kopia jest drogą odwrotu przy wdrożeniach, a osiem podbić wersji snapshotu przeszło bez strażnika kompletności (#5 High/High).
- Ryzyko #9 nie ma etapu: testy jadą z S-39…S-43 według §6.2, a bramka stoi w §5. To świadomy wyjątek od zasady „każda bramka ma etap §3”. Orkiestrator nie śledzi jej stanu, więc sprawdza go ręcznie następny `--refresh`.
- Warstwa AI-native nie ma etapu: deterministyczne testy dają sygnał dla każdego ryzyka.

## 4. Stack

The classic test base for this project. AI-native tools (if any) carry a
`checked:` date so future readers can see which lines need re-verification.

Profil bazy testów: **meaningful**. Skonfigurowane Pest i Vitest, 176 plików testów PHP (133 w `tests/Feature`, 43 w `tests/Unit`), 63 pliki `*.test.ts`, 15 speców Playwright i 44 pliki PHP z zamrożonym czasem (`travelTo`), bramka `composer ci:check` w CI na PostgreSQL 18 i PHP 8.5.

| Layer | Tool | Version | Notes |
|---|---|---|---|
| unit + integration (PHP) | Pest (PHPUnit) | 5.3 (13.4) | `phpunit.xml` domyślnie SQLite w pamięci; dowód dla bazy tylko na PostgreSQL (komendy w `AGENTS.md`) |
| wydajność (NFR) | Pest, grupa `nfr` | 5.3 | `phpunit.nfr.xml`, PostgreSQL; wzorzec 2 s przy 10 000 operacji |
| unit (TS) | Vitest przez `vp test` (vite-plus) | 5.1 (vite-plus 1.6) | pliki `*.test.ts` obok logiki w `resources/js/lib` |
| kontrakt PHP ↔ Vue | `assertInertia` w testach Feature | — | wymóg z `AGENTS.md` dla każdego propsa Inertia |
| strażnik UI | `npm run check:ui` | — | skaner literałów UI i tokenów Atrament |
| e2e | Playwright (`@playwright/test`) | 1.65 | CI: job `e2e` w `.github/workflows/tests.yml`, projekt `gate` (`npm run e2e:gate`, pięć speców `@gate`, `retries: 0`); lokalnie wszystkie specy: jeden worker, serwer z obrazu Sail, baza `household_treasurer_e2e` z `demo:seed-household`, a dla raportów `demo:seed-previous-year`; szczegóły w `context/foundation/test-stack.md` |
| testy architektury | none yet — see Phase 6 | — | Pest `arch()` |

**Stack grounding tools (current session):**
- Docs: Laravel Boost `search-docs` — dostępne, nieużyte; wersje i konfiguracja wzięte z `composer.lock`, `package-lock.json` i `context/foundation/test-stack.md`; checked: 2026-11-19
- Search: Exa (`web_search_exa`) — dostępne, nieużyte; checked: 2026-11-19
- Runtime/browser: skill `playwright-cli`, bez MCP Playwright; opcję `failOnFlakyTests` sprawdzono w typach zainstalowanego Playwright 1.65.0; checked: 2026-11-19
- Provider/platform: `gh` CLI dla GitHub Actions (historia przebiegów CI); checked: 2026-11-19

## 5. Quality Gates

The full set of gates that must pass before a change reaches production.
"Required for §3 Phase <N>" means the gate is enforced once that rollout
phase lands; before that, the gate is `planned`.

| Gate | Where | Required? | Catches |
|---|---|---|---|
| lint + format (`vp check`, Pint), typecheck (`vue-tsc`, PHPStan poziom 7 (Larastan 3.15)), Sloppy | local + CI | required | dryf składni i typów, wzorce zapytań w pętli |
| unit + integration na PostgreSQL (`composer ci:check`) | local (Sail) + CI | required | regresje logiki i odczytów |
| testy integralności Importu i benchmark trafności podpowiedzi | local + CI | required (§3 Phase 1 `complete`) | duplikaty i zgubione wiersze, spadek trafności |
| e2e speców-bramek: `seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected` i `import-approve` (zatwierdzenie Importu) | CI na pushu do `main` i `development` oraz na każdym PR | required after §3 Phase 2; w CI `retries: 0` i trace przy porażce (`retain-on-failure`); pozostałe specy lokalnie. Spec staje się bramką dopiero po spełnieniu §6.3: sprawdza brak `pageerror` i bierze rok budżetu z aplikacji, nie z zegara runnera | zepsuty przepływ w przeglądarce |
| testy przełomu roku (granice 22:59:59/23:00 UTC, strażnik zegara w domenowym JS) | local + CI | required od zarchiwizowania zmiany S-39 (`one-app-day`), rozszerzane przez S-40…S-43; wyjątek od zasady „bramka z etapem §3” (§3, akapit kolejności) | różne „dziś” serwera i przeglądarki |
| wierność Importu (krotki po zapisie) | local + CI | required after §3 Phase 3 | zapis inny niż plik |
| round-trip backup/restore i strażnik kompletności snapshotu | local + CI | required after §3 Phase 4 (kod wyjścia przy nieudanym zapisie jest już w `composer ci:check`) | niekompletny backup, stratny restore starszych wersji |
| testy parzystości „do wydania” PHP↔TS i niezmienników raportu | local + CI | required after §3 Phase 5 | rozjazd kopii kalkulatorów, sumy różne od budżetu |
| przegląd tras pod izolację, `arch()`, PHPStan na `tests/` | local + CI | required after §3 Phase 6 | dostęp do cudzych danych, złamane twarde zasady |

## 6. Cookbook Patterns

How to add new tests in this project. Each sub-section is filled in once
the relevant rollout phase ships; before that, the sub-section reads
"TBD — see §3 Phase <N>."

### 6.1 Adding a unit test

- **PHP**: `tests/Unit/`, `php artisan make:test --pest --unit <Name>Test`. Reference test: `tests/Unit/ToSpendCalculatorTest.php`.
- **TS**: `*.test.ts` obok modułu w `resources/js/lib/`. Reference test: `resources/js/lib/to-spend-calculator.test.ts`.
- **Run locally**: `php artisan test --compact <path>` (Sail), `npm test`.
- **Parzystość PHP↔TS na wspólnym fixture** (rozjazd kopii kalkulatora „do wydania”): wzorzec z etapu 5.
  - Tabela przypadków leży w `tests/Fixtures/amount-invariants/`: `to-spend-calculator-cases.json` (pozycje, rozbicia, łańcuchy Zebranych) i `entry-to-spend-scenarios.json` (scenariusze ścieżki wejść). Każdy przypadek ma ręczne `derivation` z glosariusza (To Spend, Irregular Rate, Collected); oczekiwane wartości nigdy nie pochodzą z wyjścia kodu.
  - Jeden plik czytają oba światy: Pest przez `__DIR__.'/../Fixtures/amount-invariants/...'` (`tests/Unit/ToSpendCalculatorParityTest.php`), Vitest przez `readFileSync` (`resources/js/lib/to-spend-calculator.parity.test.ts`, `entry-to-spend-warning.parity.test.ts`).
  - Nowy przypadek: dopisz wpis do JSON z ręcznym `derivation`, uruchom oba testy; wpis wymaga obu stron zielonych albo świadomego `it.skip` z numerem issue.
  - Scenariusze ścieżki wejść mają klucze (`key`) zamiast id; `tests/Feature/EntryToSpendParityTest.php` buduje stan przez akcje i fabryki, a `inputs` w pliku są pisane ręcznie, nigdy kopiowane z `EntryToSpendInputs` (inaczej wracamy do problemu wyroczni). Feature sprawdza serwer, Vitest podgląd TS na tych samych `inputs`. Pole `dialog` to stan dialogu wpisu, z którego `handEntryToSpendChanges` musi dać `changes`. Oczekiwane liczby są w scenariuszu dwa razy (`server_months` dla PHP, `readings` dla TS), więc Vitest sprawdza, że każdy odczyt ma w `server_months` miesiąc z tymi samymi kwotami.
- **Dzień „dziś” w Vitest**: od S-39 logika przyjmuje App Day jako argument (string `YYYY-MM-DD` z `shell.today` albo rok z niego wyprowadzony), a test podaje go jawnie. Funkcja nie czyta zegara (`new Date()` bez argumentu, `Date.now()`), test nie używa fake timerów (żaden test Vitest nie zamraża czasu). Wzorzec: `resources/js/lib/app-day.test.ts` (rok, zakres miesiąca przez przełom roku, luty przestępny); `resources/js/lib/format.test.ts` podaje już `'YYYY-MM-DD'`.

### 6.2 Adding an integration test

- **Location**: `tests/Feature/`, `php artisan make:test --pest <Name>Test`. Dane przez fabryki modeli; daty jawne, nie losowe.
- **Run locally on PostgreSQL**: komenda z `AGENTS.md` (Commands), dla testów z bazą zawsze.
- **Import z prawdziwymi wariantami wyciągów** (duplikaty, zgubione wiersze, atomowość zestawu): wzorzec w `tests/Feature/ImportIntegrityTest.php`, na PostgreSQL.
  - Test robi migawkę stanu bazy przed i po zatwierdzeniu (helpery `importIntegrity*`) i porównuje liczności operacji, przelewów własnych, ruchów kapitału i pozycji budżetu, salda kont i lokat oraz wykonanie pozycji; asercja tylko na odpowiedzi HTTP nie wystarcza.
  - Wyrocznie są niezależne od parsera: ręcznie policzona liczba wierszy pliku, ręczne krotki (data, kwota w groszach) z pliku sprawdzane na podglądzie, saldo pochodne z `AccountBalanceOnDate`.
  - Fixture pochodzi z prawdziwego pliku, edytowanego ręcznie (anonimizacja, nakładka, układ) w `tests/Fixtures/import/`, nigdy wygenerowanego parserem. Obecne: mbank-overlap, no-preamble, long-preamble, reordered-columns, renamed-amount. Warianty BNP (xlsx) test buduje w locie przez PhpSpreadsheet.
  - Atomowość: wiersz, który pada po wierszu już zapisanym, musi wycofać cały zestaw (stan bazy jak przed).
  - Jeśli S-42 wybierze zapis częściowy wyciągu na przełomie roku, to jest zmiana reguły ryzyka #1: S-42 jawnie aktualizuje ten punkt, guidance #1 i testy atomowości, zamiast je obchodzić.
  - Nowy wariant banku: weź prawdziwy plik, zanonimizuj ręcznie, policz wiersze ręcznie, dodaj plik do `tests/Fixtures/import/` i przypadek do `ImportIntegrityTest.php` z oczekiwanymi krotkami zapisanymi w teście.
  - Krotki po zapisie (wierność zapisanej operacji wobec pliku): TBD — see §3 Phase 3.
  - Świeże eksporty użytkownika (wrzesień i październik 2026, mBank i BNP) są zanonimizowane razem z kwotami i leżą poza gitem w `storage/app/private/import-fixtures/` ze skryptami `tools/anonymize.php` i `tools/verify.php`. Ich testy w `ImportIntegrityTest.php` są pomijane z komunikatem, gdy plików brak, więc w CI się nie uruchamiają. Przy nowym eksporcie: oryginał do `storage/app/private/statements/`, potem `anonymize.php`, `verify.php` i nowe liczby w teście.
- **Benchmark trafności podpowiedzi kategorii**: `tests/Feature/ImportSuggestionAccuracyBenchmarkTest.php` (około 10 s na PostgreSQL) porównuje trafność ze stałym zestawem `tests/Fixtures/suggestions/reference-2026.json` (1031 wierszy, pseudonimy tokenów posortowane w wierszu, kategorie „Kategoria N” w kolejności niezależnej od id, bez kwot i bez tekstu z banku) i poziomem bazowym `tests/Fixtures/suggestions/reference-2026-baseline.json`.
  - Regeneracja zestawu: `php artisan import:suggestion-reference {snapshot} --year=2026` na snapshocie `user:backup` w wersji co najmniej 10. Generator wymusza równość rankingu przed i po pseudonimizacji.
  - Świadome podniesienie bazy: uruchom test z `SUGGESTION_BASELINE_WRITE=1` (odmawia przy ustawionym `CI`), a nowy poziom bazowy trafia do commita razem z uzasadnieniem.
  - Spadek poniżej poziomu bazowego czerwieni test i wypisuje listę wierszy, które przestały trafiać. Nie obniżaj progu, żeby test przeszedł.
- **Niezmiennik raportu z ręcznie policzoną wyrocznią**: `tests/Feature/ReportAmountInvariantTest.php`, na PostgreSQL.
  - Operacje są księgowane przez akcje (`ManageFinancialOperation`), więc licznik wykonania pozycji rusza tak jak w aplikacji; lista operacji i każda oczekiwana liczba są policzone ręcznie w teście.
  - Każdy świadek (licznik pozycji, strona Budżetu, raport okresu, Wynik miesiąca) jest porównywany osobno z ręcznym literałem; żadna asercja nie porównuje jednego odczytu z drugim.
  - Granice miesiąca i roku są parami po obu stronach: 31.03/01.04 i 31.12/01.01. Zarchiwizowanie kategorii następuje po zaksięgowaniu operacji, żeby test widział ją w obu stanach. Rok z arkusza ma osobny test z tymi samymi odczytami.
  - Sprawdzone zepsucia: `->subDay()->month` przy przypisaniu operacji do miesiąca w `MonthCategoryActuals::forMonths` oraz usunięcie filtra `budget_id` w `BudgetMonthResult` czerwienią test. Zamiana `<` na `<=` przy `afterLastDay` jest równoważna (granicę odcina już przypisanie do miesiąca w pętli), więc nie jest dobrym zepsuciem.
- **Przełom roku i północ w Polsce** (ryzyko #9; reguła w ADR-0103, `context/foundation/adr/0103-one-app-day-in-europe-warsaw.md`):
  - Czas zamrażasz przez `travelTo` na granicach polskiej północy: przełom roku 31.12 o 22:59:59 UTC i 23:00:00 UTC (zimą polska północ to 23:00 UTC), przełom miesiąca w czasie letnim 30.06 o 21:59:59 UTC i 22:00:00 UTC (przykład z ADR-0103). Nigdy sama północ UTC.
  - Asercja przez `assertInertia` na „dziś” powłoki (`shell.today`), roku Dashboardu i roku budżetu. Przeglądarka bierze dzień tylko z propsów serwera, więc fake timerów w Vitest nie potrzeba.
  - Dawne testy `_known_gap` (`tests/Feature/SelectedBudgetYearTest.php`, `tests/Feature/CsvImportTest.php`) przypinały błędne zachowanie (stary rok sesji w styczniu, odrzucenie całego wyciągu). S-40 i S-42 przepisały je na wymagane zachowanie (rok 2027 w styczniu według ADR-0111; zachowanie wyciągu według decyzji S-42) i zdjęły sufiks `_known_gap`. To zmiana wymagania, nie strojenie asercji. Żaden test `_known_gap` nie został (`grep -rn _known_gap tests` jest puste).
  - Testy przypięte do północy UTC (`tests/Feature/YearTurnTest.php`, `tests/Feature/BudgetCollectedAtYearStartTest.php`) zostały przepisane w S-39 jako zmiana wymagania (ADR-0103), nie jako dostrojenie asercji.
  - Strażnik statyczny: `tests/Unit/AppDayClockGuardTest.php`, dwa testy. PHP: w `app/` dzień czyta tylko `App\Support\AppDay` (zakazane `::today(` poza AppDay, `today()`, `now(…)->year|month|day|toDateString|startOf…`, `now()->format` z samą datą, względne reguły dat (`before_or_equal:today`, `after:tomorrow` …) i `Rule::date()->todayOrBefore()` z rodzeństwem; lista wyjątków może tylko maleć). JS: w `resources/js` bez plików testów zakazane `new Date()` bez argumentu, `Date()`, `Date.now()`, `.toISOString().slice(0, 10)` i `Intl.DateTimeFormat(…).format()` bez daty; metody wołane na przekazanej dacie są dozwolone. Precedens: `tests/Unit/MonthPlanHardcodedUiTest.php`.
  - Zegar próby (`app:rehearsal-clock`) służy tylko do sprawdzenia wizualnego w przeglądarce, nie jest dowodem.

### 6.3 Adding an e2e test

- **Location**: `tests/e2e/*.spec.ts`. Reference spec: `tests/e2e/seed.spec.ts` (sprząta po sobie, sprawdza skutek na stronie i brak `pageerror`).
- Nazwy danych unikalne w każdym przebiegu; rok budżetu z aplikacji (menu roku przez pomocniki `tests/e2e/support/budget-year.ts`), nigdy z zegara runnera (`new Date().getFullYear()`); brak `pageerror` sprawdzany w każdym specu-bramce; jeden worker.
- **Run**: według `context/foundation/test-stack.md`.
- Nowy spec-bramka: celowe zepsucie przepływu musi go zaczerwienić. Bez `waitForTimeout` i bez `test.skip` przy braku danych.
- Lista speców-bramek: §5.
- Spec zatwierdzenia Importu (`tests/e2e/import-approve.spec.ts`): stempel w opisach to `Date.now().toString(36)` (base 36, bo 13–19 cyfr z rzędu Import wycina jako numer karty, Luhn); data wierszy `<rok>-01-01`, rok z menu roku; kategoria wybierana jawnie, także gdy aplikacja ją podpowiada; jedno konto (`eKonto`) i nietypowe kwoty (`-12,37`, `-3,41`), żeby wiersze nie wyglądały na parę przelewu własnego (wykrywanie par ±3 dni); sprzątanie przez `withCleanup` (`tests/e2e/support/cleanup.ts`; po porażce przepływu błąd sprzątania jest logowany i nie przykrywa przyczyny), po nagłówku strony, wiersz po wierszu (menu „Więcej akcji” → „Usuń”), tylko istniejących, z asercją zniknięcia.

### 6.4 Adding a test for a new route or Inertia page

- **Pattern now**: test Feature z `actingAs` i `assertInertia` dla każdego propsa (`AGENTS.md`).
- Kontrola własności dla drugiego użytkownika: TBD — see §3 Phase 6 (wzorzec odmowy dostępu do cudzych danych).

### 6.5 Adding data covered by backup and restore

- TBD — see §3 Phase 4 (wzorzec round-tripu, strażnika kompletności i zamrożonego snapshotu dla każdej wersji).

### 6.6 Per-rollout-phase notes

(Po każdym etapie `/10x-implement` dopisuje tu 2–3 linie o tym, czego etap nauczył.)

- **Etap 1, Integralność Importu**: luka A (powtórzony `rows.*.index`) zamknięta regułą `distinct` po czerwonym teście. Luki B i C zostają otwarte jako issue #271 i #272.
- Wyroczni „kompletu wierszy” nie da się wziąć z parsera: liczba wierszy pliku jest liczona ręcznie i zapisana w teście.
- Poziom bazowy trafności (zmierzony na zestawie 2026): rok — z podpowiedzią 77,0%, trafne wśród podpowiedzianych 93,7% (poziom suggestion); sierpień–listopad — 79,6% i 96,9%.
- **Etap 5, Niezmienniki kwot** (zmiana `testing-amount-invariants`, otwarta 2026-11-18 jako etap 2 przed odświeżeniem planu): parzystość kalkulatora i ścieżka wejść zielone po obu stronach, poza kandydatem 2 (kategoria dołączająca w styczniu): Feature zielony, Vitest czerwony (podgląd TS nie ma planu dla kategorii bez `BudgetLine`). Poprawka nie mieści się w jednym miejscu, więc issue #273 (`needs-triage`), przypadek Vitest ma `it.skip` z numerem, a Feature zostaje aktywny.
- Raport, strona Budżetu, licznik pozycji i Wynik miesiąca zgadzają się z ręcznymi literałami, granice 31.03/01.04 i 31.12/01.01 oraz rok z arkusza włącznie; po drodze test arkusza wymagał obu etykiet przychodu w ładowarce.
- **Etap 2, E2E w CI** (zmiana `e2e-in-ci`): w specach Importu stamp w opisie to `Date.now().toString(36)`, bo 13–19 cyfr przechodzi test Luhna i Import wycina je jako numer karty (flake w `import-other-format-without-header`); poza Importem dziesiętny `Date.now()` jest bezpieczny. Rok budżetu spec czyta z menu roku (`newestExecutionBudgetYear`), nie z zegara runnera, ale dialog wydatku podpowiada dzisiejszą datę (od S-39 App Day z `shell.today`): lokalnie, gdy w wykonaniu jest już Budżet następnego roku, `month-plan-groups` i `irregular-collected` otworzą plan innego roku niż wydatek. W CI seed tworzy tylko bieżący rok.
- Sprzątanie w `finally` przykrywało błąd przepływu, gdy samo padło na tym samym zepsutym serwerze; bramki sprzątają przez `withCleanup`, które po porażce loguje błąd sprzątania i rzuca pierwotny.
- `--repeat-each` powtarza też projekt `setup`, a logowanie ma limit 5 prób na minutę, więc próba stabilności loguje się raz (`--project=setup`), a bramki powtarza z `--no-deps`. Pierwsze 20 rund dało 99/100 dwa razy (limit testu 30 s dla długich przepływów i wolna animacja wyjścia dialogu), po poprawce limitów w configu 100/100.
- Sam `throw` w `onMounted` nie daje `pageerror`, bo Vue przechwytuje błąd hooka; dowód asercji `pageerror` to błąd rzucony z `setTimeout`.

## 7. What We Deliberately Don't Test

Exclusions agreed during the rollout (Phase 2 interview, Q5). Future
contributors should respect these unless the underlying assumption changes.

- **Lokaty (Term Deposit) i ruchy kapitału (Capital Movement)** — bez nowych testów ponad istniejące. Ocenić ponownie, gdy zmiana dotknie ich reguł, Importu ruchów kapitału albo Total funds. (Source: Phase 2 interview Q5.)
- **Równoczesne podwójne zatwierdzenie Importu** — świadomie nietestowane: jest jeden użytkownik, przycisk jest blokowany w trakcie wysyłki, a ponowne wysłanie po sukcesie ma test. Ocenić ponownie, gdy w gospodarstwie pojawi się drugi użytkownik albo Import przez API. Znane, otwarte luki formatów: issue #271 i #272. (Source: `testing-import-integrity`, plan faza 6.)
- **Domyślna strona Budżetu bez zarchiwizowanych pozycji** — zamierzone: sumy Wykonania na stronie pomijają zarchiwizowane pozycje, a raport i Wynik miesiąca je zawierają. Utrwala to test `ReportAmountInvariantTest.php`. (Source: `testing-amount-invariants`, pytanie otwarte 1.)
- **Opłata stała wpisana wstecz (kandydat 1)** — podgląd TS ostrzega tylko o miesiącu operacji, a serwer przesuwa też Propozycję następnego miesiąca. Zgodne z zakresem ostrzeżenia w glosariuszu (To Spend), test zielony po obu stronach. Ocenić ponownie, gdy zmieni się zakres ostrzeżenia albo reguła Propozycji. (Source: `testing-amount-invariants`, faza 3.)
- **Ścieżka wejść „do wydania” poza pięcioma scenariuszami etapu 5** — usunięcie operacji, edycja zmieniająca kategorię albo miesiąc operacji i dialog przychodu (moment pierwszego wpływu) nie mają scenariusza w `entry-to-spend-scenarios.json`. Ocenić ponownie, gdy zmiana dotknie `EntryToSpendInputs` albo `handEntryToSpendChanges` w tych gałęziach. (Source: `testing-amount-invariants`, przegląd 2.7.)
- **Kandydaci 3–6 z research `testing-amount-invariants`** — świadomie nietestowani w tym etapie; wracają, gdy zmiana dotknie ich odczytów. Kandydat 2 (kategoria dołączająca w styczniu) ma issue #273.
- **Zrzuty pikselowe widoków** — nie wykrywają regresji przy ciągłych restylach Atrament; stan widoku chronią kitchen sinki `/dev/...` i `npm run check:ui`. Ocenić ponownie, gdy design system się ustabilizuje. (Source: challenger pass, seed brief.)

## 8. Freshness Ledger

- Strategy (§1–§5) last reviewed: 2026-11-19
- Stack versions last verified: 2026-11-19
- AI-native tool references last verified: 2026-11-19

Refresh (`/10x-test-plan --refresh`) when:

- a new top-3 risk surfaces from the roadmap or archive,
- a recommended tool's `checked:` date is older than three months,
- the project's tech stack changes (new framework, new test runner),
- §7 negative-space no longer matches what the team believes.
