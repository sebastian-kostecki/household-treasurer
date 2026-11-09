<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Category List After Migration

- **Plan**: context/changes/category-list-after-migration/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6, 7
- **Date**: 2026-11-09
- **Verdict**: REJECTED
- **Findings**: 1 critical, 2 warnings, 5 observations
- **Triage**: F1 (Fix A), F2 (Fix A), F3–F8 fixed. Po triage `composer ci:check` w Sail na PostgreSQL zielony (1052 passed, 2 skipped), Sloppy czysty. Zmiany niezacommitowane. Zostaje próba fazy 7 (`follow-ups/review-fixes.md`, F1); `change.md` → `implementing`, dopóki jej nie ma.

Werdykt REJECTED wynika wyłącznie z F1 (brak próby na kopii produkcji, która jest warunkiem wdrożenia). Kod faz 1–6 jest zgodny z planem, a cała bramka jest zielona.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | FAIL |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Success criteria (uruchomione podczas przeglądu, commit c4b4e96)

| Item | Result |
|------|--------|
| `composer ci:check` w Sail, `DB_CONNECTION=pgsql` (1.3, 2.3, 3.4, 4.1, 5.4, 6.4) | PASS — 1048 passed, 2 skipped (NFR perf), 7864 assertions |
| `tests/Feature/Migrations` + `ControlNumbersCommandTest` + `UserSnapshotCommandTest` na PostgreSQL (1.1, 2.1, 2.2, 3.3, 6.1) | PASS — 35 passed, 233 assertions (nie pominięte) |
| `vendor/bin/sloppy ci` (host) (1.4) | PASS — 100/100, 0 findings |
| `grep "Category::factory()->for(\$budget" tests` (1.5) | PASS — pusty |
| `grep "budget_lines\.(name\|type\|icon\|color\|sort_order)" app` (3) | PASS — pusty |
| `grep "category_name\|category_type"` w app/Http, app/Support, resources/js/{lib,components,composables} (4.3) | PASS — pusty |
| E2E: categories, global-shortcuts, command-palette, seed (4.5, 5.3) | PASS — 22 passed |
| `bash -n` deploy.sh i lib/production-deploy.sh (6.2) | PASS |
| `php artisan boost:index-rules` bez zmian w drzewie (6.3) | PASS (host CLI) |
| Manual 7.1–7.5 | Odhaczone bez dowodów — zob. F1 |

## Findings

### F1 — Faza 7 odhaczona bez próby i bez `migration-trial.md`

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence / Success Criteria
- **Location**: context/changes/category-list-after-migration/migration-trial.md (brak); plan.md:567-571; README.md:151,168
- **Detail**: Plan (Desired End State, faza 7) wymaga zapisanej próby na kopii produkcji: liczby kontrolne przed / po migracji / po `down()`, lista sufiksów „(k)” i archiwalnych, wynik „Przywrócenie produkcji” w `restore_trial`. Pliku nie ma w drzewie ani w historii (`git log --all -- '*migration-trial.md'` pusty). Commit 1242336 zmienia tylko pola wyboru w `plan.md`. Lokalnie nie ma bazy `restore_trial` ani plików `storage/app/private/control-numbers/*`. `README.md:168` dalej mówi, że kroków „Przywrócenie produkcji” „jeszcze nie ćwiczono”, a `README.md:151` stawia ten plik jako krok 1 pierwszego wdrożenia S-18. Odhaczenie 7.5 („migration-trial.md zapisany”) jest nieprawdziwe; 7.1–7.4 nie mają żadnego śladu. To pierwsza nieodwracalna migracja na danych produkcyjnych.
- **Fix A ⭐ Recommended**: Odhacz 7.1–7.5, ustaw `change.md` na `implementing`, przeprowadź próbę według kontraktu fazy 7 i zapisz `migration-trial.md` (oraz popraw `README.md:168`).
  - Strength: Plan i README znów mówią prawdę; wdrożenie dostaje dowód, którego wymaga jego własny krok 1.
  - Tradeoff: Potrzebne świeże archiwum produkcji i ok. godzina pracy ręcznej.
  - Confidence: HIGH — brak artefaktu sprawdzony w drzewie, historii, bazie i `storage`.
  - Blind spot: Nie wiem, czy próba była robiona gdzie indziej (inny komputer) i tylko nie została zapisana.
- **Fix B**: Jeśli próba naprawdę przeszła poza tym repo — spisz `migration-trial.md` z własnych notatek i liczb kontrolnych, bez odznaczania.
  - Strength: Bez powtarzania pracy.
  - Tradeoff: Zapis z pamięci jest słabszym dowodem niż wyjście komend; bez plików `before.json` nie ma tabeli liczb.
  - Confidence: LOW — nic w repo nie wskazuje, że próba się odbyła.
  - Blind spot: Czy zachowały się liczby kontrolne z tej próby.
- **Decision**: FIXED (Fix A) — 7.1–7.5 odhaczone w `plan.md`, `change.md` → `implementing`; sama próba i `migration-trial.md` w kolejce `follow-ups/review-fixes.md` (F1).

### F2 — Archiwum listy nie decyduje o wyborze (operacje, Import, plan miesiąca, nowy budżet)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Safety & Quality
- **Location**: app/Concerns/ExpenseValidationRules.php:114 (to samo w IncomeValidationRules.php, OperationRebuildValidationRules.php, CsvImport/FinancialOperationImportValidator.php), app/Support/CsvImport/ImportCategoryOptions.php, app/Http/Controllers/OperationsController.php:150-183, app/Actions/CreateBudget.php:36-40, app/Actions/ArchiveCategory.php:25-32
- **Detail**: ADR-0063 („Global archive”) i `.ai/rules/categories.md:35`: archiwum kategorii listy decyduje o wyborze w operacjach, planie miesiąca i nowym budżecie. Kod czyta tylko `archived_at` Budget Line (`$category->isArchived()` na `BudgetLine`, `budgetLines()->whereNull('archived_at')`); `categories.archived_at` sprawdza jedynie `ImportCategorySuggester.php:120`. `ArchiveCategory` archiwizuje tylko pozycję budżetu w wykonaniu bieżącego roku (UTC), a polityka dopuszcza archiwum przy pozycji w dowolnym budżecie w wykonaniu. Skutek od stycznia 2027: (a) 1 stycznia z 2026 w wykonaniu i 2027 w szkicu archiwum nie archiwizuje żadnej pozycji w wykonaniu — kategoria liczy się dalej w sumach 2026 i jest do wyboru; (b) archiwum w marcu 2027 (przykład „Rekolekcje”) zostawia pozycję 2026 aktywną, więc wydatek z datą 2026 na zarchiwizowaną kategorię przechodzi walidację; `CreateBudget` skopiuje taką kategorię do nowego szkicu. Na dziś (listopad 2026, 2026 w wykonaniu, 2027 szkic) przypadek jest nieosiągalny. Plan odłożył „selektor z aktywnych kategorii listy” do S-19 (FR-019), ale ADR stosuje regułę już do stanu po S-18, a podpowiadacz Importu już ją stosuje — niespójność.
- **Fix A ⭐ Recommended**: Domknąć w S-18 — `whereHas('category', whereNull archived_at)` w opcjach operacji, Importu i źródle `CreateBudget`; serwer odrzuca nową operację na kategorię zarchiwizowaną na liście (istniejąca operacja może ją zachować); test „archiwum w marcu 2027 → wydatek z 2026 odrzucony” i przypadek 1 stycznia.
  - Strength: Zgodność z ADR-0063 i z podpowiadaczem Importu, zanim sytuacja stanie się osiągalna na produkcji.
  - Tradeoff: Kilka plików walidacji i opcji, nowy komunikat; zachowanie przy edycji istniejącej operacji wymaga uwagi.
  - Confidence: MED — reguła jest jasna, ale ścieżka „operacja już wskazuje” dotyka czterech walidatorów.
  - Blind spot: Plan miesiąca (`amountsByLine`) nie był sprawdzany pod tym kątem.
- **Fix B**: Zostawić do S-19 — dopisać do planu S-19 / follow-upu jawne wymaganie i termin „przed 1 stycznia 2027”, a w ADR-0065 zdanie, że do S-19 wybór czyta archiwum pozycji.
  - Strength: S-18 zostaje w swoim zakresie („Opcje zostają pozycjami roku jak dziś”).
  - Tradeoff: Ryzyko, że S-19 nie wejdzie przed nowym rokiem i reguła ADR będzie łamana na produkcji.
  - Confidence: MED — zależy od harmonogramu S-19.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — walidacja serwera już odrzucała kategorię zarchiwizowaną na liście (`Rule::exists('categories')->whereNull('archived_at')` w regułach wydatku, przychodu, przebudowy i Importu), więc poprawka objęła wybór: nowy scope `BudgetLine::openForChoice()` w opcjach Operacji (`OperationsController`), Importu (`ImportCategoryOptions`), źródle `CreateBudget::copyCategories` i sprawdzeniu `StoreBudgetRequest`. Testy `CategoryListBudgetPageTest::test_a_category_archived_on_the_list_leaves_the_choice_where_its_line_stays_active` i `::test_a_source_budget_with_only_list_archived_categories_is_refused` czerwone przed, zielone po. Pełny zestaw PostgreSQL 1050 zielonych, PHPStan, Pint, Sloppy czyste. Plan miesiąca nie sprawdzany (blind spot). Niezacommitowane.

### F3 — `deploy.sh` może po cichu pominąć snapshoty sprzed migracji

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: scripts/lib/production-deploy.sh:26-28, 33-39, 46-60
- **Detail**: `production_user_ids` uruchamia `sh -c 'if [ -n "$(psql … to_regclass …)" ]; then psql …; fi'`. Gdy pierwszy `psql` się nie powiedzie (hasło, baza niegotowa), test widzi pusty napis, `if` kończy się kodem 0, `user_ids` jest pusty i pętla nie robi żadnego `user:backup` — `set -euo pipefail` tego nie łapie, zostaje tylko stderr. `production_app_is_running` zwraca fałsz także dla zatrzymanego/upadłego kontenera albo błędu `docker compose`, a to jest traktowane jak pierwsze wdrożenie: bez snapshotów i bez `artisan down` w starym kontenerze. Dodatkowo snapshoty biegną przed `artisan down`, więc zapis między snapshotem a trybem serwisowym nie trafi do zapasu v5 (zamiana kolejności nic nie kosztuje).
- **Fix**: W `sh -c` `set -e` i błąd przy niepowodzeniu `psql` (zamiast „brak tabeli”); pierwsze wdrożenie wykrywać jawnie (brak tabeli `users` i brak kontenera w `ps -a`, albo flaga `FIRST_DEPLOY=1`), w innym razie przerwać; po pętli sprawdzić, że liczba snapshotów = liczba id; `artisan down` przed snapshotami.
  - Strength: Snapshoty v5 są jedynym zapasem po wycofaniu, który nie traci zmian sprzed migracji (plan, „Migration Notes”).
  - Tradeoff: Skrypt bez testów automatycznych poza `bash -n`; zmianę trzeba przejść ręcznie (np. w próbie z F1).
  - Confidence: HIGH — zachowanie `$(…)` w `[ -n ]` i `if !` przy errexit jest pewne.
  - Blind spot: Nie uruchomiłem skryptu przeciw prawdziwemu stackowi produkcyjnemu.
- **Decision**: FIXED (Fix now) — `production_user_ids` w `sh -ec` z `psql -v ON_ERROR_STOP=1` (błąd przerywa wdrożenie, czytany przed `artisan down`); `production_app_is_running` usunięte, pierwsze wdrożenie tylko przy braku kontenera `app` w `ps --all`, kontener zatrzymany przerywa skrypt (wyjście awaryjne `DEPLOY_WITHOUT_USER_SNAPSHOTS=1`); `artisan down` przed snapshotami; log „Snapshotted N user(s).”. Kontrola liczby snapshotów pominięta: błąd `user:backup` i tak przerywa pętlę (`set -e`). Sprawdzone stubem `docker compose` (scenariusze running / psql error / stopped / absent / override; stara wersja: 0 snapshotów i exit 0 przy błędzie psql, zatrzymany = pierwsze wdrożenie). README krok 3 i „Pierwsze wdrożenie S-18” krok 3 zaktualizowane. Do przejścia na prawdziwym stacku w próbie z F1. Niezacommitowane.

### F4 — Przeciągnięcie numeruje tylko część listy

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/ReorderCategories.php:22,42-49
- **Detail**: `sort_order` jest na liście (wspólny dla lat), ale reorder nadaje 0…n−1 tylko kategoriom z aktywnymi pozycjami tego budżetu i typu. Kategorie spoza tego zbioru (archiwalne pozycje, kategorie tylko z innego roku) zachowują stare numery i kolidują z nowymi, więc inne lata i widok „Kategorie” wpadają na drugi klucz (`name_key`). Przy dzisiejszych danych (2027 skopiowany z 2026) prawie niewidoczne; rośnie z każdym rokiem.
- **Fix**: Przenumerować całą listę danego typu: podane kategorie w podanej kolejności, pozostałe zachowują względną pozycję (np. wstawione wg dotychczasowego `sort_order`); test z kategorią tylko w innym roku.
  - Strength: Jedna kolejność listy, zgodnie z ADR-0063 („kolejność → kategoria listy, wszystkie lata”).
  - Tradeoff: Trzeba zdecydować, gdzie wstawić kategorie niewidoczne w tym budżecie.
  - Confidence: MED — reguła scalenia nie jest opisana w ADR.
  - Blind spot: S-19 może i tak przenieść kolejność do widoku „Kategorie”.
- **Decision**: FIXED (Fix now) — `ReorderCategories::listOrder()`: podane kategorie wchodzą w podanej kolejności w miejsca, które już zajmują na całej liście typu (`lockForUpdate`), pozostałe zostają na swoich miejscach, cała lista dostaje 0…n−1. Test `CategoryReorderTest::test_reorder_renumbers_the_whole_list_and_keeps_categories_outside_the_budget_in_place` czerwony przed (kolizje 1,1,2,2), zielony po; Reorder/CategoryListBudgetPage/Budget 84 zielone na PostgreSQL, PHPStan, Pint, Sloppy czyste. Niezacommitowane.

### F5 — Dwie kontrole, które nie mogą zadziałać

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_11_09_122525_backfill_category_list.php:142-166; app/Actions/RestoreUserData.php:254
- **Detail**: Backfill porównuje sumy wykonania i planów per budżet, których sam nie zapisuje (zmienia tylko `category_id`), więc ta asercja nie może się nie udać (inne asercje — typ, właściciel, budżet operacji i pozycji planu — robią prawdziwą robotę). Restore v6 sprawdza „typ pozycji ≠ typ kategorii” tylko przy `isset($lineData['type'])`, a `BackupUserData::serializeBudgetLine` nie pisze `type`, więc dla prawdziwego pliku v6 kontrola nigdy nie odpala (test wstrzykuje klucz).
- **Fix**: Zostawić asercję sum jako pas bezpieczeństwa z komentarzem, a martwą kontrolę typu w restore usunąć razem z testem albo zastąpić sprawdzeniem typu operacji względem kategorii.
- **Decision**: FIXED (Fix now) — asercja sum w migracji bez zmian (klasa i migracja zamrożone); kontrola typu usunięta z `RestoreUserData::restoreBudgetLines` razem z mutacją „category of another type” w `UserSnapshotCommandTest::test_restore_command_rejects_malformed_v6_budget_lines` (za zgodą z triage); `.ai/rules/snapshot.md` mówi, że pozycja v6 nie ma własnego typu. Kontrakt fazy 2 („kategoria innego typu niż pozycja”) jest w v6 strukturalnie niemożliwy. Snapshot + Migrations 32 zielone na PostgreSQL. Niezacommitowane.

### F6 — `down()` gubi kategorie listy bez pozycji i archiwum listy

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_11_09_122525_backfill_category_list.php:48-74; README.md („Wycofanie S-18”, krok 2)
- **Detail**: `down()` usuwa listę; przeżywa to, co wisi na pozycjach. Kategoria utworzona po wdrożeniu i zarchiwizowana (pozycje w szkicach usunięte, brak pozycji w wykonaniu) znika, a archiwum listy przy aktywnych pozycjach się nie odtwarza. README mówi, że nowe kategorie „zostają” — prawda tylko dla tych z pozycją. Operacje i kwoty bez zmian.
- **Fix**: Jedno zdanie w README przy wycofaniu: kategorie bez żadnej pozycji i archiwum samej listy nie przeżywają `down()`.
- **Decision**: FIXED (Fix now) — `README.md` „Wycofanie S-18”, krok 2: dopisane, co nie przeżywa `down()`. Niezacommitowane.

### F7 — Restore v6 nie sprawdza budżetu operacji względem pozycji

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/RestoreUserData.php:527-535
- **Detail**: `restoreFinancialOperations` mapuje `budget_line_id` i `budget_id` niezależnie; pozycje planu mają kontrolę budżetu, operacje nie. Migracja tego niezmiennika pilnuje (`$operationsInOtherBudget`), resolver i liczby kontrolne na nim polegają. Luka istniała w v5, ale v6 to nowy format.
- **Fix**: Odrzucić operację, której pozycja należy do innego budżetu, tak jak pozycję planu; jeden test z ręcznie zepsutym plikiem.
- **Decision**: FIXED (Fix now) — `restoreFinancialOperations` ładuje raz mapę pozycja → budżet i odrzuca operację z pozycją innego budżetu (cała transakcja restore się cofa). Test `UserSnapshotCommandTest::test_restore_command_rejects_an_operation_whose_budget_line_belongs_to_another_budget` czerwony przed, zielony po; snapshot/migracje/backup 37 zielonych na PostgreSQL, PHPStan, Pint, Sloppy czyste; `.ai/rules/snapshot.md` uzupełnione. Niezacommitowane.

### F8 — Dokumenty rozjechane z kodem

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .ai/rules/categories.md:24; context/foundation/roadmap.md:48
- **Detail**: Reguła mówi, że plan miesiąca używa `forBudget()`, a kod używa `BudgetLineResolver::amountsByLine` (`ChangeMonthPlan.php:54`, `ApproveMonthPlan.php:49`); `forBudget` nie ma wywołującego (addendum fazy 4). Roadmapa trzyma S-18 jako `in-progress` przy `change.md` = `implemented`. (Sprzeczność `README.md:168` z odhaczonym 7.4 należy do F1.)
- **Fix**: Poprawić zdanie w `categories.md` na `amountsByLine()`/`forYear()` (i zaktualizować status S-18 przy archiwizacji zmiany).
- **Decision**: FIXED (Fix now) — `.ai/rules/categories.md`: `forYear()` / `amountsByLine()` (`forBudget()` czeka na S-19) oraz wskazanie `BudgetLine::openForChoice()` z F2. Roadmapa bez zmian (S-18 `in-progress` zgadza się ze stanem po F1). Niezacommitowane.
