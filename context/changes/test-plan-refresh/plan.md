# Odświeżenie planu testów po M-4 i próbie przełomu roku — plan implementacji

## Overview

Zmiana przepisuje `context/foundation/test-plan.md` §1–§6 i §8 według `refresh-brief.md`, `research.md` i sześciu decyzji z wywiadu planu (2026-11-19). §7 zostaje bez zmian (P5). Zgoda na przepisanie §1/§2 jest w `change.md` (tryb `--refresh`). Wynik odblokowuje roadmapę F-09 (MS-06):

- S-44 `e2e-in-ci` dostaje listę speców-bramek i politykę powtórek;
- S-39…S-43 dostają wzorzec testu przełomu roku w §6.

Zmiana edytuje tylko dokument. Nie pisze testów, YAML CI ani zmian PRD.

## Current State Analysis

Pełne ustalenia: `research.md` (ugruntowane) i `refresh-brief.md` (obawy i co jest nieaktualne). Najważniejsze dla planu:

- **Nieaktualne fakty** (`test-plan.md`):
  - §3 etap 1 `change opened` (`:77`), choć zmiana jest zarchiwizowana w `context/archive/2026-11-17-testing-import-integrity/` (28/28 w Progress);
  - odwołania do porzuconego M-2: S-11…S-16 w §2 (`:48-54`, `:61`, `:62`) i w akapicie kolejności (`:83`);
  - #6 „wersja 1–5” (`:52`), choć `SNAPSHOT_VERSION` = 13;
  - #7 „brak warstwy e2e” (`:53`), choć jest 15 speców;
  - §4 „94/26”, vite-plus 1.4, wiersz e2e „none yet” (`:90`, `:96`, `:99`);
  - §5 bramka e2e „after Phase 5” (`:121`);
  - §6.3 „TBD” (`:156`);
  - §1 linia hot-spotów z 2026-11-06 (`:33`).
- **#1 rozszerzone.** Krotki (data, kwota) sprawdzamy tylko na podglądzie (`tests/Feature/ImportIntegrityTest.php:497-502`). Po zatwierdzeniu nic nie porównuje zapisanej operacji z plikiem. #271 i #272 są dziś zgodne z FR-IMP-08 i FR-IMP-10 (`context/foundation/prd-mvp.md:255,257`), więc ich przypięcie wymaga najpierw decyzji produktowej (słownik i ADR, `AGENTS.md`). Audyt obserwowalności (`context/audits/observability/2026-11-19_import-operations-year-turn-backups.md`, IMP-1) potwierdza ciche znikanie wierszy.
- **Baza worktree.** Worktree wyszedł z `2141188`. Lokalny `development` ma 9 commitów więcej (stan na 2026-11-19): merge F-08 (`cbaafaf`), audyt obserwowalności (`d162bd0`) i `backup-signals` (`3c885df`). `test-plan.md` się tam nie zmienił, więc baza diffu §7 (`2141188`) zostaje. Roadmapa M-5 leży niezacommitowana w głównym checkoucie, a `development` ma M-4.
- **Przełom roku.** F-08 jest rozstrzygnięty w ADR-0103 (`context/foundation/adr/0103-one-app-day-in-europe-warsaw.md`) i zmergowany do `development` (`cbaafaf`, zmiana w `context/archive/2026-11-18-app-day-decision/`). Do tego worktree trafia przez warunek wstępny. Stan testów:
  - żaden test PHP nie stoi na 22:59:59/23:00 UTC;
  - testy granicy stoją na północy UTC (`tests/Feature/YearTurnTest.php:95,130,145`, `tests/Feature/BudgetCollectedAtYearStartTest.php:172,176`);
  - 0 z 63 testów Vitest zamraża czas;
  - dwa testy `_known_gap`: `tests/Feature/SelectedBudgetYearTest.php:82` (S-40) i `tests/Feature/CsvImportTest.php:685` (S-42).
  - Audyt MPL-1: po północy UTC „Zatwierdź” dla grudnia czyści kwoty bez komunikatu.
- **#7 zmienione.** Problemy dzisiejszej warstwy E2E:
  - żaden spec nie zatwierdza Importu;
  - `playwright.config.ts:19` ustawia `retries: CI ? 2 : 0`;
  - `scripts/e2e-server.sh:17,26` wymaga sieci Sail;
  - seed zależy od dnia uruchomienia (`database/seeders/DemoHouseholdSeeder.php:30,327-332`).
  - Zainstalowany Playwright 1.65.0 ma `failOnFlakyTests` (`node_modules/playwright/types/test.d.ts:1327`), ale decyzja to `retries: 0`.
- **Dowody do #5.** Od v6 do v13 było osiem podbić wersji bez strażnika kompletności, i to one trzymają #5 na High (rubryka schematu: obszar zmienia się co tydzień). Audyt BKP-3 (`user:backup`, którego plik się nie zapisał, kończył się kodem 0) jest już naprawiony na `development` w `backup-signals` (`3c885df`, test w `tests/Feature/BackupSignalsTest.php`). Nie podnosi więc oceny, a głośna porażka zapisu ma już test.
- **Liczby hot-spotów 2026-11-19.** W zakresie z §1 jest 370 commitów od 2026-10-20 (329 bez merge'ów, liczone przed merge'em `development`), a brief mówił 373. Zmiany plików: `resources/js/components` 550, `resources/js/lib` 425, `app/Support/CsvImport` 48. Faza 1 przelicza je komendami z fazy 1 w stałym oknie 2026-10-20 → 2026-11-19.
- **Schemat** (`.claude/skills/10x-test-plan/references/test-plan-schema.md`):
  - numerów ryzyk się nie zmienia, nowe dopisuje się na końcu;
  - słownik statusów §3 jest stały;
  - Source w §2 bez `file:line`, nazw funkcji i modułów;
  - §6 ma 3–6 podsekcji;
  - w pliku nie ma kodu testów ani YAML CI;
  - każda bramka w §5 ma etap §3, który ją włącza. Bramka przełomu roku jest świadomym wyjątkiem (decyzja 2);
  - §2 ma mieć 5–7 wierszy, a §3 3–5 etapów. 9 ryzyk i 6 etapów to świadome odstępstwo: numerów się nie zmienia, a wynika to z decyzji 1 i 6.

## Desired End State

`context/foundation/test-plan.md` odpowiada stanowi repo z 2026-11-19:

- **§2** ma 9 ryzyk:
  - #1 rozszerzone o wierność pól;
  - #3 Medium, #5 High, #6 Low;
  - #7 jako „E2E tylko lokalnie”;
  - nowe #9 „przełom roku”;
  - po jednym wierszu Risk Response Guidance na ryzyko;
  - żadnego odwołania do M-2.
- **§3** ma 6 etapów w kolejności: 1 Integralność Importu (`complete`, folder archiwum), 2 Bramki E2E w CI, 3 Wierność Importu, 4 Backup i restore, 5 Niezmienniki kwot, 6 Bramki jakości.
- **§4** i **§5** podają bieżące wersje i liczby, bramkę E2E z pięciu speców przy `retries: 0` oraz bramkę przełomu roku od S-39.
- **§6** ma wzorzec E2E i wzorzec przełomu roku według ADR-0103.
- **§8** ma datę refreshu.
- **§7** jest bajt w bajt taki jak przed zmianą.

Sprawdzenie: kontrole grep i diff z kryteriów faz oraz lektura użytkownika.

### Key Discoveries:

- Obie strony Importu biorą wartości z sesji (`app/Http/Controllers/CsvImportController.php:236-247`), więc porównanie podgląd = zapis łapie tylko błędy mapowania. Wyrocznią zostają ręczne krotki z pliku (`research.md`, Architecture Insights).
- Zapisana kwota: `amount_minor_units` przechowuje wartość bez znaku, a `import_identity_amount_minor_units` kwotę ze znakiem z pliku (`app/Actions/ImportFinancialOperations.php:249-269`). Wyrocznia po zapisie to (data, kwota ze znakiem, rachunek).
- ADR-0103 każe przeglądarce brać dzień wyłącznie z `shell.today`. Test przełomu to więc `travelTo` na granicach polskiej północy plus `assertInertia`, bez fake timerów. Testy przypięte do północy UTC zmieniają się w S-39 jako zmiana wymagania.
- Strażnik „brak zegara w domenowym JS” ma precedens w `tests/Unit/MonthPlanHardcodedUiTest.php` i `npm run check:ui`.
- Specy-bramki (decyzja 3): `seed.spec.ts`, `import-other-format-without-header.spec.ts`, `month-plan-groups.spec.ts`, `irregular-collected.spec.ts` oraz nowy spec zatwierdzenia Importu, który napisze S-44.

## Decyzje z wywiadu planu (2026-11-19)

| # | Decyzja | Wybór |
|---|---|---|
| 1 | #1 i #271/#272 | Osobny etap „Wierność Importu”. Krotki po zapisie od razu. #271/#272 po decyzji użytkownika o FR-IMP-08/10 (słownik i ADR), podjętej w tym etapie |
| 2 | Przełom roku | Ryzyko #9, wzorzec w §6 teraz, bramka w §5 od S-39. Testy piszą S-39…S-43, bez wiersza w §3 |
| 3 | Bramki E2E | 4 stabilne specy plus nowy spec zatwierdzenia Importu w S-44. Reszta lokalnie |
| 4 | Powtórki | `retries: 0` w CI dla bramek, trace `retain-on-failure` |
| 5 | Ocena | #3 High→Medium, #5 Medium→High, #6 Medium→Low, #9 High/High, reszta bez zmian |
| 6 | Kolejność §3 | 2 Bramki E2E w CI → 3 Wierność Importu → 4 Backup i restore → 5 Niezmienniki kwot → 6 Bramki jakości |

## What We're NOT Doing

- Zmiana nie dotyka §7 ani jednego znaku (P5).
- Nie powstają testy, specy, zmiany `playwright.config.ts` ani YAML CI. To praca S-44 i kolejnych etapów.
- Nie zmieniamy PRD, słownika ani ADR dla FR-IMP-08/10. Decyzja zapada w etapie 3, a plan testów tylko ją zapowiada.
- Nie zmieniamy roadmapy. Czy etap 3 wchodzi do M-5, rozstrzyga `/10x-roadmap`. Kopia roadmapy w worktree to M-4, a M-5 leży niezacommitowana w głównym checkoucie.
- Nie ma osobnego etapu §3 dla przełomu roku (decyzja 2).
- Kolumna Source w §2 nie dostaje kotwic `file:line` (zasada #3 planu testów).
- Nie commitujemy roadmapy M-5. To warunek wstępny, który spełnia `/10x-roadmap` w głównym checkoucie.
- F-08 jest już na `development`. Zmiana dociąga go tylko merge'em `development` (warunek wstępny) i cytuje ADR-0103 bez adnotacji.

## Implementation Approach

Trzy fazy, każda to spójny blok sekcji z własnymi kontrolami mechanicznymi i jednym commitem `docs(test-plan-refresh): … (pN)`. Kolejność idzie za zależnościami treści. Numery ryzyk z §2 są potrzebne w §3 i §5, a numery etapów z §3 w placeholderach §6. Teksty piszemy po polsku, jak obecny plik. Nagłówki tabel i stałe zdania ze schematu zostają po angielsku, statusy §3 też.

**Warunki wstępne przed fazą 1** (sprawdza je kontrola 1.1):

1. Roadmapa M-5 jest zacommitowana na `development` przez `/10x-roadmap` w głównym checkoucie.
2. Worktree ma zmergowany `development`. Najpierw commit plików planu tej zmiany, potem sprawdzenie obu kierunków względem `origin/development` i `git merge development`. Merge przynosi ADR-0103, audyt obserwowalności, `backup-signals` i roadmapę M-5.

## Critical Implementation Details

- **Numeracja ryzyk.** Przełom roku dostaje numer #9 i trafia na koniec tabeli §2, a #1–#8 zachowują numery. O priorytecie #9 mówią §3, akapit kolejności i §5.
- **§7 nietykalne.** Każda faza kończy się diffem §7 względem `HEAD` sprzed zmiany (`2141188`). Diff musi być pusty.

## Faza 1: Mapa ryzyk (§1, §2)

### Overview

Aktualizuje linię hot-spotów w §1 i przepisuje tabelę ryzyk oraz Risk Response Guidance w §2 według decyzji 1, 2 i 5.

### Changes Required:

#### 1. §1 linia hot-spotów

**File**: `context/foundation/test-plan.md`

**Intent**: Zastąpić „126 commitów w ciągu 30 dni do 2026-11-06” bieżącą liczbą policzoną tą samą komendą, żeby oceny prawdopodobieństwa miały aktualny dowód.

**Contract**: Zakres się nie zmienia. Okno jest stałe, 30 dni od 2026-10-20 do 2026-11-19, niezależnie od dnia implementacji. Linia podaje liczbę commitów z komendy:
`git log --since=2026-10-20 --until=2026-11-19T23:59:59 --oneline -- app resources/js routes database/migrations ':!resources/js/actions' ':!resources/js/routes' ':!resources/js/components/ui' | wc -l`
(przed merge'em `development`: 370). Liczby zmian plików na katalog w Source §2 liczy ta komenda (2026-11-19: `resources/js/components` 550, `app/Support/CsvImport` 48):
`git log --since=2026-10-20 --until=2026-11-19T23:59:59 --name-only --format= -- <katalog> ':!resources/js/components/ui' | grep -c .`
Zasady 1–3 i akapit o PostgreSQL zostają bez zmian.

#### 2. §2 tabela ryzyk

**File**: `context/foundation/test-plan.md`

**Intent**: Usunąć odwołania do M-2, wpisać nowe oceny, rozszerzyć #1, przeformułować #7 i dopisać #9, tak żeby każdy wiersz cytował bieżące dowody.

**Contract**: 9 wierszy #1–#9, numery #1–#8 bez zmian. Source zawiera tylko dokumenty, wywiad, issue, archiwum i katalogi hot-spotów z liczbami. Bez plików kodu, funkcji i modułów.
- **#1** (High/High): scenariusz „Zatwierdzenie Importu po cichu dubluje, gubi albo zapisuje inne operacje niż w pliku”. Dochodzą zmiana formatu komórki (wiersz znika) i kwota w obcej walucie zapisana jako PLN. Source dokłada: wywiad 2026-11-19 P1, P2; issue #271, #272; `context/archive/2026-11-17-testing-import-integrity/`; audyt obserwowalności 2026-11-19 IMP-1; hot-spot `app/Support/CsvImport` (przeliczona liczba zmian plików/30d).
- **#2** (High/High): treść bez zmian. Source: S-14 zastępują M-3 S-26 (`context/archive/2026-11-11-irregular-expense-collected/plan.md`, obie kopie zmienione razem) i `.ai/rules/month-calculators.md` (zgodność kopii tylko z konwencji). Hot-spoty `app/Support`, `resources/js/lib` z przeliczonymi liczbami.
- **#3** (High/**Medium**): treść bez zmian. Source: S-11/S-12 zastępują M-3 S-28…S-32, M-4 S-34/S-35 i roadmapa S-43 (cięcie „Lat” w styczniu).
- **#5** (High/**High**): treść bez zmian. Source: osiem podbić wersji snapshotu v6→v13 bez strażnika kompletności (`.ai/rules/snapshot.md`) zastępuje S-14/S-16; z nich wynika High. Dochodzi dopisek: audyt obserwowalności 2026-11-19 BKP-3 (cichy nieudany zapis snapshotu) naprawiony w zmianie `backup-signals`.
- **#6** (High/**Low**): „(1–5)” → „starszej niż bieżąca”. Source: zamiast S-14, S-16 wchodzą zamrożone snapshoty v5/v6/v8/v9 z testami restore dla każdej wersji (`.ai/rules/snapshot.md`).
- **#7** (Medium/High): scenariusz „Krytyczny przepływ w przeglądarce (wgranie → podgląd → zatwierdzenie Importu, dialog operacji, plan miesiąca) psuje się po merge'u, a CI jest zielone, bo specy E2E działają tylko lokalnie”. Source: wywiad 2026-11-19 P4; `context/foundation/test-stack.md`; roadmapa S-44 Risk; hot-spoty `resources/js/components`, `resources/js/pages` z przeliczonymi liczbami.
- **#8** (High/Low): treść bez zmian. Source: S-11/S-13 zastępują M-3 S-21, S-22, S-31 (nowe trasy i filtry).
- **#9** (High/High, nowe): „Wokół północy w Polsce, zwłaszcza 31.12 23:00 UTC = 1.01 00:00, serwer, menu roku i dialogi pokazują różny dzień albo rok. Rok w sesji zostaje stary, plan grudnia nie daje się zatwierdzić i traci kwoty, Import przy szkicu nowego roku odrzuca cały wyciąg, a „Lata” porównują jeden dzień”. Source: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` (luki 1–5); roadmapa M-5 MS-01…MS-05; F-08 (ADR-0103); audyt obserwowalności 2026-11-19 MPL-1, IMP-2.

#### 3. §2 Risk Response Guidance

**File**: `context/foundation/test-plan.md`

**Intent**: Dać każdemu z 9 ryzyk wiersz, który mówi, co dowiedzie ochrony, co zakwestionować i czego unikać. To przekazanie do kolejnych `/10x-research` i `/10x-plan`.

**Contract**: 9 wierszy #1–#9 w tej samej tabeli z sześcioma kolumnami ze schematu. Wiersze #4 bez zmian. W pozostałych:
- **#1**:
  - *What would prove*: każda zapisana operacja ma (datę, kwotę ze znakiem, rachunek) równe krotkom spisanym ręcznie z pliku; do tego obecne dowody (brak nowej operacji przy nakładce, komplet wierszy wariantu, wycofanie zestawu). Po decyzji o FR-IMP-08/10 wiersz nieczytelny i kwota w obcej walucie dają widoczny skutek zgodny z nową regułą.
  - *Must challenge*: „liczba operacji się zgadza, więc treść też”; „podgląd jest poprawny, więc zapis też”.
  - *Context*: granica podgląd → zapis (obie strony z sesji), znak kwoty a typ operacji, rachunek z sesji, FR-IMP-08/10 jako obowiązujące reguły, formaty z #271, kolumna waluty z #272.
  - *Layer*: integracja na PostgreSQL z fixture.
  - *Anti*: krotki odczytane parserem zamiast ręcznie z pliku; asercja #271/#272 przed zmianą PRD/ADR.
- **#2**: *Context* „jak S-14 ją zmieni” → „jak S-26 zmienił obie kopie, która kopia liczy na produkcji”. Reszta bez zmian.
- **#3**: *Must challenge* dokłada „istniejące testy porównują odczyt z odczytem”. *Context* „co testy S-11 już pokrywają” → „istniejące testy raport = Wynik miesiąca i wykonanie, ruchy kapitału poza raportem okresu”. Reszta bez zmian.
- **#5**: *What would prove* bez zmian, bo już wymaga niezerowego kodu wyjścia, a nieudany zapis ma test z `backup-signals`. *Context* dokłada „istniejący test nieudanego zapisu snapshotu (`backup-signals`); luka to strażnik kompletności”. Reszta bez zmian.
- **#6**: *Context* „Formaty v1–v5” → „wersje 1–13, zamrożone v5/v6/v8/v9, wersje budowane w teście, reguła restore dla każdej wersji”.
- **#7**:
  - *What would prove*: CI na pushu i PR do `development` uruchamia specy-bramki. Każdy pada, gdy jego przepływ nie kończy się skutkiem na stronie albo rzuca błąd JS, co sprawdza celowe zepsucie przepływu.
  - *Must challenge*: „15 speców przechodzi lokalnie, więc wszystkie nadają się na bramkę”; „zielone po powtórce = stabilne”.
  - *Context*: brak speca zatwierdzenia Importu, powtórki w CI, serwer E2E zależny od sieci Sail, seed zależny od daty uruchomienia (1–14 stycznia), konto testowe poza `.env`, stan globalny między specami przy jednym workerze.
  - *Layer*: e2e w CI.
  - *Anti*: wszystkie 15 speców jako bramka; `retries` maskujące niestabilny spec; spec bez asercji skutku.
- **#8**: bez zmian.
- **#9**:
  - *What would prove*: przy czasie zamrożonym na 31.12 22:59:59 i 23:00:00 UTC (latem 21:59:59 i 22:00:00 UTC) props serwera („dziś” powłoki, rok Dashboardu, rok budżetu) dają ten sam dzień aplikacji w Europe/Warsaw. Przeglądarka bierze dzień tylko z propsów. Testy `_known_gap` zielenieją w S-40 i S-42 bez zmiany asercji wymagań.
  - *Must challenge*: „zielone testy na północy UTC dowodzą zachowania o północy w Polsce”; „poprawka sprawdzona w jednym dialogu działa we wszystkich”.
  - *Context*: źródła „dziś” na serwerze i w przeglądarce, ADR-0103 i merge F-08, testy przypięte do północy UTC (zmiana wymagania w S-39), brak fake timerów w Vitest i TZ w CI.
  - *Layer*: integracja (zamrożony czas + `assertInertia`) + unit TS z dniem jako argumentem + strażnik statyczny zegara w JS; e2e tylko wizualnie przez zegar próby.
  - *Anti*: testy tylko na północy UTC; zamrożenie czasu tylko w PHP, gdy przeglądarka ma własny zegar.

### Success Criteria:

#### Automated Verification:

- Warunki wstępne spełnione (`development` z F-08 i audytem zmergowany, roadmapa M-5 na miejscu): `git merge-base --is-ancestor cbaafaf HEAD && test -f context/audits/observability/2026-11-19_import-operations-year-turn-backups.md && grep -c '^### S-44' context/foundation/roadmap.md` kończy się kodem 0 i wypisuje ≥ 1
- §2 ma 9 wierszy ryzyk i 9 wierszy guidance: `sed -n '/^## 2\./,/^## 3\./p' context/foundation/test-plan.md | grep -cE '^\| [1-9] \|'` = 9 i `… | grep -cE '^\| #[1-9] '` = 9
- §1–§2 bez slice'ów M-2: `sed -n '/^## 1\./,/^## 3\./p' context/foundation/test-plan.md | grep -nE 'S-1[1-7]\b'` zwraca nic
- Tabela ryzyk §2 bez kotwic kodu: `sed -n '/^## 2\./,/^### Risk Response/p' context/foundation/test-plan.md | grep -nE '\.(php|ts|vue)\b|::|\(\)'` zwraca nic
- §7 bez zmian: `diff <(git show 2141188:context/foundation/test-plan.md | sed -n '/^## 7\./,/^## 8\./p') <(sed -n '/^## 7\./,/^## 8\./p' context/foundation/test-plan.md)` zwraca nic

#### Manual Verification:

- Użytkownik czyta §2 i potwierdza, że #1 i #9 opisują jego obawy językiem scenariusza, a nie nazwami testów

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na potwierdzenie użytkownika, zanim przejdziesz do fazy 2.

---

## Faza 2: Etapy, stos i bramki (§3, §4, §5)

### Overview

Przepisuje tabelę etapów i akapit kolejności (decyzje 1, 2, 6), aktualizuje stos (§4) i bramki (§5, decyzje 2–4).

### Changes Required:

#### 1. §3 tabela etapów i akapit kolejności

**File**: `context/foundation/test-plan.md`

**Intent**: Ustawić sześć etapów w uzgodnionej kolejności, tak żeby orkiestrator `/10x-test-plan` podał jako następny S-44, a cele mówiły, jaką ochronę etap dowodzi.

**Contract**: Kolumny i słownik statusów bez zmian. Wiersze:

| # | Phase name | Goal | Risks | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Integralność Importu | cel jak dziś | #1, #4 | integracja + benchmark | `complete` | `context/archive/2026-11-17-testing-import-integrity/` |
| 2 | Bramki E2E w CI | CI na pushu i PR do `development` czerwieni się, gdy merge psuje zatwierdzenie Importu, dialog operacji albo plan miesiąca; bramka tylko ze stabilnych speców, bez powtórek (roadmapa S-44, zmiana `e2e-in-ci`) | #7 | e2e w CI | `not started` | — |
| 3 | Wierność Importu | zapisana operacja = transakcja z pliku (data, kwota ze znakiem, rachunek); po decyzji o FR-IMP-08/10 przypięte #271 i #272 | #1 | integracja | `not started` | — |
| 4 | Backup i restore | strażnik kompletności snapshotu czerwieni się przy tabeli albo kolumnie z danymi użytkownika spoza snapshotu, restore każdej starszej wersji bezstratny (głośna porażka zapisu ma już test z `backup-signals`) | #5, #6 | integracja | `not started` | — |
| 5 | Niezmienniki kwot | „do wydania” PHP = TS na wspólnej tabeli przypadków; raport = wykonanie = Wynik miesiąca na ręcznej wyroczni | #2, #3 | kontrakt/parzystość + integracja | `not started` | — |
| 6 | Bramki jakości | przegląd tras pod izolację, `arch()` dla twardych zasad, PHPStan na `tests/` | #8, przekrojowo | integracja + bramki | `not started` | — |

Akapit kolejności zastępuje `:83`. Mówi, że:
- etap 2 jest jedynym etapem planu testów w M-5 (roadmapa S-44; etapy 4–6 są w Parked);
- etap 3 to obawa nr 1 i zaczyna się od decyzji produktowej o FR-IMP-08/10;
- etap 4 wyprzedza 5, bo kopia jest drogą odwrotu przy wdrożeniach, a osiem podbić wersji snapshotu przeszło bez strażnika kompletności (#5 High/High);
- ryzyko #9 nie ma etapu: testy jadą z S-39…S-43 według §6.2, a bramka stoi w §5. To świadomy wyjątek od zasady „każda bramka ma etap §3”. Orkiestrator nie śledzi jej stanu, więc sprawdza go ręcznie następny `--refresh`;
- warstwa AI-native nie ma etapu.

#### 2. §4 Stack

**File**: `context/foundation/test-plan.md`

**Intent**: Wpisać bieżące liczby i wersje oraz faktyczną warstwę e2e, żeby §4 odpowiadał `composer.lock`, `package-lock.json` i `test-stack.md`.

**Contract**:
- **Profil**: 175 plików testów PHP (132 w `tests/Feature`, 43 w `tests/Unit`), 63 `*.test.ts`, 15 speców Playwright, 44 pliki z zamrożonym czasem. Bramka `composer ci:check` na PostgreSQL 18 i PHP 8.5. Implementer przelicza te liczby komendami z `research.md` §Summary w dniu implementacji.
- **Wiersze**:
  - Pest 5.3 (PHPUnit 13.4);
  - Vitest 5.1 przez vite-plus 1.6;
  - e2e: Playwright 1.65, lokalnie do etapu 2 (jeden worker, serwer z obrazu Sail, baza `household_treasurer_e2e` z `demo:seed-household`, a dla raportów `demo:seed-previous-year`; szczegóły w `context/foundation/test-stack.md`). Notka o „Pest Browser plugin” znika;
  - testy architektury: „none yet — see Phase 6”;
  - Larastan nie dostaje wiersza w §4. Jego wersja (3.15) trafia do §5, do wiersza lint/typecheck.
- **Stack grounding tools**: daty `checked: 2026-11-19` według briefu §1 (Boost `search-docs` dostępny, nieużyty; Exa dostępny, nieużyty; skill `playwright-cli`, bez MCP Playwright; `gh` CLI). Runtime/browser dokłada, że `failOnFlakyTests` sprawdzono w typach zainstalowanego Playwright 1.65.0.

#### 3. §5 Quality Gates

**File**: `context/foundation/test-plan.md`

**Intent**: Każda bramka wskazuje etap albo wycinek, który ją włącza. E2E i przełom roku dostają wymagania z decyzji 2–4.

**Contract**: Wiersze (Gate | Where | Required? | Catches):
- lint, format, typecheck, Sloppy: bez zmian poza dopiskiem „PHPStan poziom 7 (Larastan 3.15)”;
- `composer ci:check`: bez zmian;
- integralność Importu i benchmark: `required` (etap 1 `complete`);
- **e2e speców-bramek** (`seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected`, nowy spec zatwierdzenia Importu) | CI na pushu i PR do `development` | required after §3 Phase 2; w CI `retries: 0` i trace przy porażce; pozostałe specy lokalnie | zepsuty przepływ w przeglądarce;
- **testy przełomu roku** (granice 22:59:59/23:00 UTC, strażnik zegara w domenowym JS) | local + CI | required od zarchiwizowania zmiany S-39 (`one-app-day`), rozszerzane przez S-40…S-43; wyjątek od zasady „bramka z etapem §3” (decyzja 2) | różne „dziś” serwera i przeglądarki;
- wierność Importu (krotki po zapisie) | local + CI | required after §3 Phase 3 | zapis inny niż plik;
- round-trip backup/restore i strażnik kompletności | required after §3 Phase 4 (kod wyjścia przy nieudanym zapisie jest już w `composer ci:check`);
- parzystość PHP↔TS i niezmienniki raportu | required after §3 Phase 5;
- przegląd tras, `arch()`, PHPStan na `tests/` | required after §3 Phase 6.

### Success Criteria:

#### Automated Verification:

- §3 ma 6 wierszy etapów z dozwolonymi statusami: `sed -n '/^## 3\./,/^## 4\./p' context/foundation/test-plan.md | grep -E '^\| [1-6] \|' | grep -cE '\| (not started|change opened|researched|planned|implementing|complete) \|'` = 6
- §3–§5 bez slice'ów M-2 i bez „none yet” dla e2e: `sed -n '/^## 3\./,/^## 6\./p' context/foundation/test-plan.md | grep -nE 'S-1[1-7]\b|none yet — see Phase 4|vite-plus 1\.4|Pest Browser'` zwraca nic
- §5 wymienia cztery istniejące specy-bramki i `retries: 0` (nowy spec Importu nie ma jeszcze nazwy): `sed -n '/^## 5\./,/^## 6\./p' context/foundation/test-plan.md | grep -oE 'seed|import-other-format-without-header|month-plan-groups|irregular-collected|retries: 0' | sort -u | wc -l` = 5
- §7 bez zmian: ten sam `diff` co w fazie 1 zwraca nic

#### Manual Verification:

- Użytkownik potwierdza, że cel etapu 2 i bramka E2E w §5 dają S-44 wszystko, czego potrzebuje do planu (lista speców, polityka powtórek, brakujący spec Importu)

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na potwierdzenie użytkownika, zanim przejdziesz do fazy 3.

---

## Faza 3: Domknięcie (§3 status, §6, §8)

### Overview

Końcowa podfaza z kontraktu `/10x-test-plan`. Uzgadnia status §3 z dyskiem, uzupełnia wzorce §6 (E2E, przełom roku, placeholdery z nowymi numerami etapów), bumpuje daty §8 i nagłówka.

### Changes Required:

#### 1. §3 status i nagłówek

**File**: `context/foundation/test-plan.md`

**Intent**: Potwierdzić z dyskiem, że etap 1 jest `complete` (28/28 `[x]` w zarchiwizowanym `plan.md`) i że żaden inny etap nie ma folderu zmiany. Zaktualizować „Last updated”.

**Contract**: Wiersz 1 `complete`, folder archiwum. Wiersze 2–6 `not started`, `—`. Nagłówek `> Last updated: <dzień implementacji>`.

#### 2. §6 Cookbook Patterns

**File**: `context/foundation/test-plan.md`

**Intent**: Zapisać wzorce, które już istnieją w repo (E2E) albo są ustalone decyzją (przełom roku według ADR-0103), i przenumerować placeholdery na nowe etapy. S-44 i S-39…S-43 mają mieć przepis bez szukania.

**Contract**: §6 zostaje przy podsekcjach 6.1–6.6 (limit schematu).
- **6.1 unit**: punkt „Parzystość PHP↔TS” wskazuje §3 Phase 5. Nowy punkt dla Vitest: „dziś” przekazywane jako argument `YYYY-MM-DD`, bez `new Date()` i fake timerów. Reference: `resources/js/lib/budget-year-menu.test.ts`, `resources/js/lib/format.test.ts`.
- **6.2 integracja**:
  - blok Importu dokłada „krotki po zapisie: TBD — see §3 Phase 3 (wierność zapisanej operacji wobec pliku)”;
  - niezmiennik raportu wskazuje §3 Phase 5;
  - nowy blok **„Przełom roku i północ w Polsce”**: `travelTo` na 31.12 22:59:59 i 23:00:00 UTC oraz latem 21:59:59/22:00:00 UTC; asercja `assertInertia` na „dziś” powłoki, rok Dashboardu i rok budżetu; nigdy sama północ UTC.
  - W tym samym bloku: testy `_known_gap` (`tests/Feature/SelectedBudgetYearTest.php`, `tests/Feature/CsvImportTest.php`) zmieniają nazwę i zielenieją z S-40 i S-42 bez zmiany asercji wymagań. Testy przypięte do północy UTC (`tests/Feature/YearTurnTest.php`, `tests/Feature/BudgetCollectedAtYearStartTest.php`) zmieniają się w S-39 jako zmiana wymagania (ADR-0103). Strażnik statyczny przeciw `new Date()`, `getFullYear()` i `toISOString().slice(0, 10)` w domenowym JS powstaje w S-39 (precedens `tests/Unit/MonthPlanHardcodedUiTest.php`). Zegar próby (`app:rehearsal-clock`) służy tylko do sprawdzenia wizualnego. Cytat: ADR-0103 (`context/foundation/adr/0103-one-app-day-in-europe-warsaw.md`), bez adnotacji o merge'u.
- **6.3 e2e** (zamiast TBD):
  - lokalizacja `tests/e2e/*.spec.ts`; reference `tests/e2e/seed.spec.ts` (sprząta po sobie, sprawdza skutek na stronie i `pageerror`);
  - unikalne nazwy danych, bieżący rok przez `tests/e2e/support/budget-year.ts`, jeden worker;
  - uruchamianie według `context/foundation/test-stack.md`;
  - nowy spec-bramka: celowe zepsucie przepływu musi go zaczerwienić, nie może mieć `waitForTimeout` ani `test.skip` przy braku danych;
  - lista bramek w §5;
  - spec zatwierdzenia Importu: TBD — see §3 Phase 2.
- **6.4**: kontrola własności TBD → §3 Phase 6.
- **6.5**: backup i restore TBD → §3 Phase 4.
- **6.6**: notatki etapu 1 bez zmian.

#### 3. §8 Freshness Ledger

**File**: `context/foundation/test-plan.md`

**Intent**: Zapisać datę refreshu.

**Contract**: trzy linie „last reviewed/verified” = dzień implementacji. Lista wyzwalaczy refreshu bez zmian.

### Success Criteria:

#### Automated Verification:

- Wiersz 1 §3 ma `complete` i folder archiwum, a folder istnieje: `grep -E '^\| 1 \|.*\| complete \| `context/archive/2026-11-17-testing-import-integrity/`' context/foundation/test-plan.md` i `test -d context/archive/2026-11-17-testing-import-integrity`
- §6 bez nieaktualnych numerów etapów: `sed -n '/^## 6\./,/^## 7\./p' context/foundation/test-plan.md | grep -nE 'see §3 Phase [1-6]'` pokazuje tylko Phase 2–6 zgodne z §3, a `grep -c '^### 6\.'` = 6
- §6.3 nie jest „TBD” w całości, a §6.2 ma blok przełomu roku: `sed -n '/^### 6\.3/,/^### 6\.4/p' context/foundation/test-plan.md | grep -c 'seed.spec.ts'` ≥ 1 i `sed -n '/^### 6\.2/,/^### 6\.3/p' context/foundation/test-plan.md | grep -c '22:59:59'` ≥ 1
- Daty §8 i nagłówka = dzień implementacji: `grep -cE '(last reviewed|last verified|Last updated): <dzień>' context/foundation/test-plan.md` = 4
- Cały plik bez slice'ów M-2 poza §7: `sed -n '1,/^## 7\./p' context/foundation/test-plan.md | grep -nE 'S-1[1-7]\b'` zwraca nic
- §7 bez zmian: ten sam `diff` co w fazie 1 zwraca nic

#### Manual Verification:

- Użytkownik czyta §6.2 (przełom roku) i §6.3 (e2e) i potwierdza, że S-39 i S-44 mogą z nich startować bez szukania w archiwum

---

## Testing Strategy

Zmiana dotyczy tylko dokumentu. Dowodem są kontrole grep i diff z kryteriów faz oraz lektura użytkownika. Nie uruchamiamy `composer ci:check`, bo nie zmienia się żaden plik, który bramka sprawdza.

### Manual Testing Steps:

1. Przeczytać §2 #1 i #9 jako scenariusze awarii.
2. Sprawdzić, że następne wywołanie `/10x-test-plan` poda etap 2 (pierwszy status różny od `complete`).
3. Przeczytać §6.2 i §6.3 z perspektywy planu S-39 i S-44.

## Migration Notes

Brak. Orkiestrator `/10x-test-plan` czyta §3 tymi samymi literałami statusów.

## References

- Brief refreshu: `context/changes/test-plan-refresh/refresh-brief.md`
- Research: `context/changes/test-plan-refresh/research.md`
- Schemat: `.claude/skills/10x-test-plan/references/test-plan-schema.md` (główny checkout)
- Etap 1: `context/archive/2026-11-17-testing-import-integrity/`
- Próba przełomu: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md`
- Audyt: `context/audits/observability/2026-11-19_import-operations-year-turn-backups.md` (IMP-1, IMP-2, MPL-1, BKP-3)
- F-08: `context/foundation/adr/0103-one-app-day-in-europe-warsaw.md`, `context/archive/2026-11-18-app-day-decision/` (na `development` od `cbaafaf`)
- BKP-3 naprawione: `backup-signals` (`3c885df`, `tests/Feature/BackupSignalsTest.php`)
- Roadmapa M-5: `context/foundation/roadmap.md` (F-09, S-39…S-44), na `development` po warunku wstępnym 1
- Przegląd planu: `context/changes/test-plan-refresh/reviews/plan-review.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Mapa ryzyk (§1, §2)

#### Automated

- [x] 1.1 Warunki wstępne spełnione (development i roadmapa M-5 zmergowane) — 367d55d
- [x] 1.2 §2 ma 9 wierszy ryzyk i 9 wierszy guidance — 367d55d
- [x] 1.3 §1–§2 bez slice'ów M-2 — 367d55d
- [x] 1.4 Tabela ryzyk §2 bez kotwic kodu — 367d55d
- [x] 1.5 §7 bez zmian — 367d55d

#### Manual

- [ ] 1.6 Użytkownik potwierdza #1 i #9 jako scenariusze swoich obaw

### Phase 2: Etapy, stos i bramki (§3, §4, §5)

#### Automated

- [x] 2.1 §3 ma 6 wierszy etapów z dozwolonymi statusami
- [x] 2.2 §3–§5 bez slice'ów M-2 i nieaktualnych wpisów e2e
- [x] 2.3 §5 wymienia cztery istniejące specy-bramki i retries: 0
- [x] 2.4 §7 bez zmian

#### Manual

- [ ] 2.5 Użytkownik potwierdza, że etap 2 i bramka E2E wystarczą do planu S-44

### Phase 3: Domknięcie (§3 status, §6, §8)

#### Automated

- [ ] 3.1 Wiersz 1 §3 complete z istniejącym folderem archiwum
- [ ] 3.2 §6 bez nieaktualnych numerów etapów, 6 podsekcji
- [ ] 3.3 §6.3 ma wzorzec e2e, §6.2 blok przełomu roku
- [ ] 3.4 Daty §8 i nagłówka równe dniu implementacji
- [ ] 3.5 Cały plik bez slice'ów M-2 poza §7
- [ ] 3.6 §7 bez zmian

#### Manual

- [ ] 3.7 Użytkownik potwierdza, że S-39 i S-44 mogą startować z §6
