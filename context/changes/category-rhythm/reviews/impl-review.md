<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Rytm kategorii w propozycji planu (S-24)

- **Plan**: context/changes/category-rhythm/plan.md
- **Scope**: Full plan (6 of 6 phases)
- **Reviewed phases**: 1, 2, 3, 4, 5, 6
- **Date**: 2026-11-10
- **Verdict**: NEEDS ATTENTION → po triage: 8 FIXED, 1 FIX QUEUED (F1, próba na kopii produkcji), 1 ACCEPTED
- **Findings**: 0 critical, 4 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Grounding

Zakres: `eb2f66b..7cbec3b`, 7 commitów, 152 pliki (+5231 / −1891). Treść planu nie zmieniła się w trakcie implementacji (tylko pola Progress). `context/foundation/lessons.md` nie istnieje.

Kryteria automatyczne, uruchomione na stanie końcowym (HEAD `7cbec3b`):

| Kryterium | Wynik |
|---|---|
| Pełna bramka `composer ci:check` na PostgreSQL (obraz `sail-8.5/app`, baza `testing`) | zielona: lint 288 plików, 521 testów TS, PHPStan bez błędów, 1196 testów PHP, 2 pominięte (rejestracja Fortify, wyłączona funkcja) |
| Testy migracji rytmu na PostgreSQL (`CategoryRhythmMigrationTest`, 4 przypadki) | wykonane, zielone (nie pominięte) |
| 6.2 brak czytelników flag (`grep` z planu) | tylko `RestoreUserData.php`, `LegacyRhythmConversion.php`, `ControlNumbers.php` |
| `vendor/bin/sloppy ci` | 100/100, 0 ustaleń (baseline bez zmian) |
| `npx playwright test` | 33/33 |

Uwaga o środowisku: w tej sesji host ma PHP 8.3.36, a zależności wymagają ≥ 8.4.1, więc Sloppy i `wayfinder:generate` z buildu E2E poszły przez PHP 8.5 z obrazu Sail, nie na hoście. Dane logowania E2E wzięte z `.env` głównego checkoutu (worktree ma je puste).

Potwierdzone bez ustaleń: pięć reguł z „Critical Implementation Details” prześledzonych linia po linii (zapis w `ChangeCategoryRhythm`, odczyt w `CategoryRhythms`, ostatnia zapłacona kwota w `MonthPlanProposal`, mapowanie w `LegacyRhythmConversion`, `down()` migracji usuwającej); żadna granica „What We're NOT Doing” nie jest przekroczona; autoryzacja tras listy kategorii przez requesty i `CategoryPolicy`; migracje na `DB::table()` i zamrożonej klasie, transakcyjne na PostgreSQL; inwariant „każdy wydatek ma ≥ 1 wpis” na każdej ścieżce tworzenia; pary kalkulatorów PHP/TS zgodne także dla rytmu `null` i brakującego klucza miesiąca; każde nowe „dziś” to `CarbonImmutable::today('UTC')`; nic specyficznego dla PHP 8.5.

Zachowania zgodne z decyzją planu, nie ustalenia: zapis w „Kategoriach”, który zmienia tylko nazwę, usuwa przyszły wpis innego rytmu (reguła „od teraz tak”, ADR-0070, test `CategoryRhythmWriteTest.php:78`); `down()` migracji usuwającej zależy od dnia wycofania (stratne, ADR-0070).

## Triage (2026-11-10)

| Wynik | Ustalenia |
|---|---|
| Fixed | F2, F3, F4, F5 (Fix A), F6, F7, F8, F9 |
| Fix queued | F1 (Fix A): plik z procedurą jest, przebieg czeka na świeże archiwum z VPS |
| Accepted | F10 |

Weryfikacja po poprawkach, na ostatecznym stanie plików (niezacommitowanym):

| Kryterium | Wynik |
|---|---|
| `composer ci:check` na PostgreSQL | zielona: lint 290 plików, 524 testy TS (46 plików), PHPStan bez błędów, 1200 testów PHP, 2 pominięte (Fortify) |
| `vendor/bin/sloppy ci` (obraz Sail) | 100/100, 0 ustaleń |
| `npx playwright test` | 32/33 w pełnym przebiegu; `import-other-format-without-header.spec.ts` nie przeszedł z przyczyny niezwiązanej ze zmianą (znacznik `Date.now()` przeszedł test Luhna i Import wyciął go z opisu jak numer karty), w powtórce zielony; zapisane w `follow-ups/review-fixes.md` |

Nowe testy z triage: trzy testy liczby zapytań o rytm (F2), `hand-entry-month-remaining-preview.test.ts` i wzmocniony test Importu (F4), dwa przypadki odrzucenia snapshotu v9 (F7), test wyjątku dla niezaładowanej kategorii (F8).

## Findings

### F1 — Próba na kopii produkcji nie ma zapisu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: context/changes/category-rhythm/reviews/production-rehearsal.md (brak pliku)
- **Detail**: Plan (faza 6, pkt 5 i kryterium 6.6) wymaga wyniku próby w `reviews/production-rehearsal.md`: `meta.one_off_bill_lines` przed migracją, `meta.category_rhythm_rows` po, `--compare` bez różnic, „do wydania” zatwierdzonych miesięcy bez zmian, wynik `migrate:rollback`. Pliku nie ma w historii gita. Commit `7cbec3b` mówi: „6.6 potwierdzone przez użytkownika bez pliku production-rehearsal.md”. Żadna z tych liczb nie jest zapisana nigdzie indziej (commity, plan, ADR-0070). Pozycje jednorazowa + rachunek to jedyne dane, których zachowanie się zmienia (wchodzą do puli „Pozostało w miesiącu”, tracą powiadomienie ponad limitem), a ich liczby na produkcji nie ma w repo.
- **Fix A ⭐ Recommended**: Zapisać `reviews/production-rehearsal.md` teraz, z liczb z przeprowadzonej próby; jeśli ich nie zachowano, powtórzyć próbę na kopii produkcji przed wdrożeniem.
  - Strength: Domyka jedyny brakujący element planu i daje dowód dla wdrożenia trzech migracji, w tym usuwającej kolumny.
  - Tradeoff: Powtórzenie próby to kilkanaście minut ręcznej pracy na kopii danych.
  - Confidence: HIGH — plan i `.ai/rules/migrations.md` wymagają próby dla takiej migracji.
  - Blind spot: Nie wiem, czy liczby z próby zachowały się poza repo.
- **Fix B**: Dopisać w planie adnotację, że 6.6 potwierdzono ustnie bez zapisu, i polegać na liczbach kontrolnych `scripts/deploy.sh` przy wdrożeniu.
  - Strength: Zero dodatkowej pracy; `deploy.sh` i tak porównuje liczby kontrolne przed i po.
  - Tradeoff: `deploy.sh` nie sprawdza „do wydania” zatwierdzonych miesięcy ani wycofania; pierwsza prawdziwa próba `down()` odbyłaby się na produkcji.
  - Confidence: MEDIUM — zależy od tego, jak dokładna była próba bez zapisu.
  - Blind spot: Liczba pozycji jednorazowa + rachunek na produkcji pozostaje nieznana w repo.
- **Decision**: FIX QUEUED (Fix A) — utworzono `reviews/production-rehearsal.md` z procedurą i polami wyników; przebieg na świeżym archiwum z VPS czeka w `follow-ups/review-fixes.md` (lokalne archiwum z 2026-09-11 jest sprzed `month_item_kind`)

### F2 — Historia rytmu ładowana raz na budżet, nie raz na odczyt

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/EntryToSpendInputs.php:96, app/Support/HandEntryMonthRemainingInputs.php:48, app/Support/CsvImport/ImportMonthRemainingContext.php:87
- **Detail**: Plan (4.5, 5.4, Performance Considerations) mówi o jednym zapytaniu `CategoryRhythms` na zbiór kategorii. `CategoryRhythms::forCategoryIds()` jest wołane w pętli po budżetach w realizacji w trzech klasach wejść, a `EntryToSpendInputs` dodatkowo woła `MonthPlanProposal::forBudget()`, które ładuje tę samą historię jeszcze raz (`MonthPlanProposal.php:71`). Operacje i Import robią 2 + 3N zapytań o rytm (N = budżety w realizacji; bez Year Close N rośnie co rok). Dwa wywołania `CategoryChoiceOptions::forType()` na Operacjach ładują rytm, którego kontroler nie czyta. Dashboard ładuje historię trzy razy (`DashboardController.php:94`, `MonthPlanProposal.php:71`, `DashboardNotices.php:178`). Liczba jest ograniczona budżetami, nie wierszami; `BudgetMonthExecutionIndex::forBudget` ma ten sam kształt.
- **Fix**: W każdym `forUser()` zbudować jedno `CategoryRhythms` z id kategorii wszystkich budżetów i wpisów dołączających i przekazać je w dół; udostępnić instancję, którą już trzyma `MonthPlanProposal`.
  - Strength: Wraca do kontraktu planu i stałej liczby zapytań niezależnej od liczby lat.
  - Tradeoff: Zmiana sygnatur w 3–5 klasach odczytu; Dashboard wymaga przekazania instancji przez dwa poziomy.
  - Confidence: HIGH — wszyscy wołający przeczytani, wzorzec „załaduj raz, przekaż” jest już w `CategoryListReading`.
  - Blind spot: Nie ma testu limitu zapytań dla Operacji i Importu, więc poprawka potrzebuje własnego.
- **Decision**: FIXED (zakres wąski, zatwierdzony) — `CategoryRhythms::forBudgets()` i jedno ładowanie na odczyt w `HandEntryMonthRemainingInputs`, `ImportMonthRemainingContext`, `EntryToSpendInputs`; trzy testy regresji (czerwone przed poprawką: 2 zamiast 1, 2 zamiast 1, 4 zamiast 3). Bez zmian zostają: zapytanie o rytm w `MonthPlanProposal::forBudget()` (1 na budżet), trzy ładowania na Dashboardzie i dwa w `CategoryChoiceOptions::forType()`

### F3 — Dokumenty nadal opisują usunięte flagi

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .ai/rules/js.md:39, context/foundation/design-system.md:105, README.md:7, README.md:116
- **Detail**: Plan (6.4) wymaga reguł `.ai/rules` bez odwołań do flag jako bieżących. `.ai/rules/js.md:39` nadal wymienia stan kitchen sinka „one-off without the year mark, paced with it”, `design-system.md:105` „kategoria jednorazowa bez kreski, równomierna z kreską” (stan zastąpiony w fazie 5; etykieta „równomierna z kreską” została też w `BudgetKitchenSink.vue:488`). `README.md:7` opisuje kategorie jako „równomierne (Paced) albo jednorazowe (One-off)”, a `README.md:116` podaje snapshot „wersja 6” (teraz 9; nieaktualne już przed tą zmianą). Słownik, `categories.md`, `snapshot.md` i `migrations.md` są poprawione.
- **Fix**: Poprawić cztery miejsca i etykietę w kitchen sinku na brzmienie po S-24, potem `php artisan boost:index-rules`.
- **Decision**: FIXED — pięć zmian brzmienia (`.ai/rules/js.md:39`, `design-system.md:105`, `README.md:7`, `README.md:116`, nagłówek w `BudgetKitchenSink.vue`), `boost:index-rules` bez zmian w indeksie; akapit README o wycofaniu S-18 zostaje

### F4 — Test „pula liczy rytm miesiąca operacji” niczego nie dowodzi

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/lib/import-month-remaining-warnings.test.ts:120, resources/js/lib/hand-entry-month-remaining-preview.ts
- **Detail**: Plan (5.5) wymaga przypadku „pula liczy rytm miesiąca operacji (rytm zmieniony w listopadzie, operacja w październiku)”. Test Importu ma tytuł „warns for a fixed charge and takes the rhythm of the month of the row”, ale sprawdza tylko `showCategoryWarning === false` dla wiersza z marca o rytmie równomierna roczna; nic nie sprawdza, że stała opłata ostrzega. `previewHandEntryMonthRemainingWarnings` w fazie 5 przeszło z `is_paced` na `rhythms[miesiąc operacji]` i nie ma pliku testu, a to jedyna implementacja działająca na produkcji (PHP `MonthRemainingCalculator` wołają tylko testy). Testy PHP payloadów `handEntryMonthRemaining` i `monthRemainingContext` używają dwunastu identycznych miesięcy.
- **Fix**: Dodać w teście Importu asercję dodatnią (miesiąc ze stałą opłatą ostrzega, sąsiedni z równomierną roczną nie) i plik `hand-entry-month-remaining-preview.test.ts` z rytmem zmienionym w połowie roku.
- **Decision**: FIXED — test Importu ma trzy asercje (stała opłata w lutym ostrzega, równomierna roczna w marcu nie, kontrola: stała opłata w marcu ostrzega); nowy `hand-entry-month-remaining-preview.test.ts` (3 testy, mutacja „rytm miesiąca 1” wykryta przez 2); import kalkulatora w podglądzie zmieniony z aliasu `@` na względny, bo alias jest wyłączony w testach; zdanie w `.ai/rules/month-calculators.md` poprawione

### F5 — Rytm zadeklarowany w wielu miejscach przed S-26

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: app/Support/EntryToSpendInputs.php:158, app/Support/HandEntryMonthRemainingInputs.php:95, app/Support/CsvImport/ImportMonthRemainingContext.php:122, app/Support/CategoryList/CategoryChoiceOptions.php:115, resources/js/types/categories.ts:2
- **Detail**: Cztery identyczne prywatne `rhythmValues()` w PHP. Unia `'fixed_charge' | 'running' | 'even_annual'` zadeklarowana pięć razy w TS (`CategoryRhythm`, `MonthRhythm`, `PoolRhythm`, `DashboardRhythm`, inline w `BudgetCategoryList.vue:63`). Etykiety rytmu trzy razy w `lang/pl/ui.php` (`budget.rhythm_*`, `month_plan.rhythm_*`, `categories.rhythm_*`; dwa zestawy identyczne). S-26 dopisuje `Irregular` i musi trafić we wszystkie kopie.
- **Fix A ⭐ Recommended**: Scalić teraz: jedna metoda na `CategoryRhythms`, jeden eksportowany typ `Rhythm` w `resources/js/types`, jeden zestaw etykiet małymi literami.
  - Strength: S-25 i S-26 budują na jednym miejscu; typy i 1700 testów pilnują zmiany mechanicznej.
  - Tradeoff: Dotyka kilkunastu plików w zmianie, która jest już zielona.
  - Confidence: HIGH — kopie są dosłownie identyczne.
  - Blind spot: Nie sprawdziłem, czy etykiety `month_plan.rhythm_*` różnią się celowo wielkością liter.
- **Fix B**: Zostawić i zrobić to jako pierwszy krok S-26.
  - Strength: S-24 zamyka się bez dodatkowego diffu.
  - Tradeoff: S-25 (plan miesiąca w grupach) zdąży dodać kolejną kopię.
  - Confidence: MEDIUM — zależy od kolejności S-25 i S-26.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A, wariant „jedna nazwa”) — `CategoryRhythms::rhythmValuesInYear()` zastępuje cztery prywatne `rhythmValues()`; nowy `resources/js/types/rhythm.ts` z jedynym typem `Rhythm`, pięć dawnych nazw (`CategoryRhythm`, `MonthRhythm`, `PoolRhythm`, `DashboardRhythm`, `MonthRemainingRhythm`) i dwie unie inline usunięte; `BudgetCategoryRow` czyta `t.categories.rhythm_*`, zestaw `budget.rhythm_*` usunięty. `month_plan.rhythm_*` zostaje: jest celowo wielką literą (nagłówek kolumny planu)

### F6 — Stan S-24 w roadmapie jest niezacommitowany

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md:54, context/foundation/roadmap.md:215, context/foundation/roadmap.md:339
- **Detail**: W HEAD S-24 ma status `ready`; zmiana na `in-progress` istnieje tylko w katalogu roboczym worktree (jedyny niezacommitowany plik) i zginie przy usunięciu worktree. Linia 339 nadal mówi „Run `/10x-plan category-rhythm`”. Plan czyni ten punkt warunkowym, więc to luźny koniec, nie odchylenie.
- **Fix**: Zacommitować status razem z poprawkami z przeglądu (`in-progress` teraz, `done` przy zamknięciu, jak `docs(roadmap): S-22 done`) i poprawić linię 339.
- **Decision**: FIXED — status `in-progress` zostaje w tabeli i sekcji S-24, wiersz Backlog Handoff mówi o gotowej implementacji i przeglądzie oraz o próbie na kopii produkcji przed wdrożeniem; `done` przy zamknięciu zmiany, jak dla S-22. Zmiana czeka na commit razem z poprawkami z przeglądu

### F7 — Restore v9 przepuszcza dwa zniekształcenia jako surowy błąd

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/RestoreUserData.php:451
- **Detail**: Wpis rytmu jest indeksowany bez `is_array`, a `start_year` sprawdzane tylko przez `is_int`. Ręcznie zmieniony snapshot z wpisem-napisem daje `TypeError`, a `start_year: 70000` daje na PostgreSQL `QueryException` (zakres `smallint`; SQLite to przyjmuje) zamiast `UserSnapshotException`. Dane są bezpieczne, bo restore działa w jednej transakcji.
- **Fix**: Odrzucić wpis niebędący tablicą i rok poza rozsądnym zakresem przez `UserSnapshotException`; dopisać oba przypadki do zestawu przy `UserSnapshotCommandTest.php:1147`.
- **Decision**: FIXED — korekta ustalenia: test pokazał, że wpis niebędący obiektem był już odrzucany czystym `UserSnapshotException` (przez `?? ''` jako „unknown rhythm”), więc `TypeError` z opisu nie występował; realna luka to tylko rok poza zakresem (`QueryException` na PostgreSQL, przypadek czerwony przed poprawką). Dodano walidację roku 1–9999 i jawny komunikat dla wpisu niebędącego tablicą, dwa przypadki w zestawie testu, zdanie w `.ai/rules/snapshot.md`

### F8 — Niezaładowana kategoria czyta się jak przychód

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/CategoryRhythms.php:73
- **Detail**: `rhythmIn()` i `rhythmsInYear()` zwracają `null` dla id spoza załadowanego zbioru, tak samo jak dla przychodu. Dziś wszyscy wołający przekazują pełne zbiory, więc nic nie jest zepsute; przyszły czytelnik z przefiltrowanym zbiorem dostanie po cichu regułę „większa z planu i faktycznej”, brak puli tempa i brak etykiety.
- **Fix**: Rzucać `LogicException` dla id, którego nie było w `forCategoryIds()`; `null` zostaje tylko dla załadowanego przychodu.
- **Decision**: FIXED — `CategoryRhythms::typeOf()` rzuca `LogicException` z id kategorii spoza załadowanego zbioru (test czerwony przed poprawką); pełny zestaw PHP na PostgreSQL po zmianie: 1200 zaliczonych, więc żaden istniejący odczyt nie polegał na cichym `null`

### F9 — Kwota „zostało X na Y mies.” bez `tabular-nums`, nazwy „kind” i „paced” dla rytmu

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/MonthPlanItemRow.vue:156
- **Detail**: Podtytuł równomiernej rocznej renderuje kwotę w zwykłym `span`; linia, którą zastąpił („Pozostały limit roczny”), miała `class="tabular-nums"`, a `.ai/rules/js.md` wymaga stylu kwoty także w podpisach wierszy. Zostały też nazwy sprzed zmiany: `'kind_column' => 'Rytm'` (`lang/pl/ui.php:451`), `hasKindColumn` i `.month-plan-cell-kind` (`MonthPlanItemRow.vue:57,123`, `app.css:860-883`), `kindLabel` (`BudgetCategoryRow.vue:43`), `const paced = poolCategories(...)` (`import-month-remaining-warnings.ts:168`), tytuły testów z „paced” w `month-remaining-calculator.test.ts`.
- **Fix**: Owinąć kwotę w `tabular-nums`; nazwy zmienić przy okazji F5 albo zostawić.
- **Decision**: FIXED (tylko `tabular-nums`) — klasa na spanie podtytułu „zostało X na Y mies.” w `MonthPlanItemRow.vue`; nazwy `kind` / `paced` zostają świadomie (kosmetyka dotykająca CSS, lang i strażników UI)

### F10 — Dwa stany kitchen sinka z planu bez własnego scenariusza

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/DialogsKitchenSink.vue, resources/js/lib/operation-dialogs-fixtures.ts:151
- **Detail**: Plan (2.4) wymienia stan okna „zmiana typu”; sink ma `category-list-rhythm`, `category-list-income` i `category-list-rhythm-error`, a zmianę typu widać tylko po ręcznym przełączeniu w `category-list-new`. Plan (4.5) prosi o stan ostrzeżenia dla stałej opłaty; fikstura przemapowała kategorię 3 z rachunku na `fixed_charge`, ale lista scenariuszy wydatku jest taka sama jak przed zmianą (siedem).
- **Fix**: Dodać scenariusz `category-list-type-change` i nazwać istniejący scenariusz ostrzeżenia jako stałą opłatę, albo przyjąć, że przemapowana fikstura wystarcza.
- **Decision**: ACCEPTED — oba stany są osiągalne: zmianę typu widać po przełączeniu typu w scenariuszach „Kategoria listy: nowa” i „przychód”, a ostrzeżenie dla stałej opłaty pokazuje istniejący scenariusz wydatku (kategoria 3 fikstury to `fixed_charge`). Scenariusz otwarty „w trakcie zmiany typu” wymagałby propa w produkcyjnym `CategoryListFormDialog` tylko dla kitchen sinka
