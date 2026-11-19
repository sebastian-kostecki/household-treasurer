<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Ten sam dzień w menu, na Dashboardzie i w dialogach (S-39)

- **Plan**: context/changes/one-app-day/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-19
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 1 warning, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Evidence

- Zakres: commity 6ad3c98 (p1), 895a687 (p2), fbefca2 (p3), 73692cf (p4) względem 7ddcb49; `infrastructure.md` i `tech-stack.md` w diffie pochodzą z merge'a `development` (210e7a4), nie z tej zmiany.
- Każda pozycja „Changes Required” z faz 1–4 ma MATCH: `AppDay` w kształcie z Critical Implementation Details, 20× `today('UTC')` i `RestoreCategory` przeniesione, 15 reguł `before_or_equal:` z datą App Day budowanych w metodach (nie statycznie), trzy porównania `archived_at` przez `AppDay::of`, `shell.today` po obu stronach z `assertInertia` na czterech granicach i dla gościa, 7 dialogów ustawia datę w `watch(open)`, komponenty w layoucie czytają dzień przez `useShell()`. Granice „What We're NOT Doing” zachowane (bez zmian w `config/`, `database/`, `tests/e2e`, bez `Intl` ze strefą, `_known_gap` nietknięty).
- Kryteria automatyczne (uruchomione 2026-11-19): `composer ci:check` na PostgreSQL `testing_one_app_day` w `sail-8.5/app` — exit 0 (PHP 1954 passed, 4 skipped; Vitest 803 passed, 1 skipped; lint, `check:ui`, `vue-tsc` czyste); `vendor/bin/sloppy ci` — passed (3 medium, nic high); `vendor/bin/pint --test` — passed; grepy 1.3, 2.4, 3.4, 4.1–4.3 zgodne z planem; grep 1.2 trafia tylko w próbkę w samym strażniku (F6).
- Kryteria ręczne: wszystkie `[x]`, potwierdzenia użytkownika zapisane w acfffc0.

## Findings

### F1 — Strażnik zegara przepuszcza formy, które przywracają błąd UTC

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: tests/Unit/AppDayClockGuardTest.php:16-21
- **Detail**: Wzorce PHP łapią tylko `::today(`, helper `today(`, `now()->(year|month|toDateString)` i dosłowne `before_or_equal:today`. Przechodzą: płynne reguły `Rule::date()->todayOrBefore()` / `beforeToday()` / `afterToday()` / `todayOrAfter()` (istnieją w `vendor/laravel/framework/src/Illuminate/Validation/Rules/Date.php:38-65` i rozwijają się do `today` w UTC — dokładnie stary błąd), reguły tekstowe `before:today`, `after_or_equal:today`, `before_or_equal:now|tomorrow`, `now('UTC')->year` (argument w nawiasie), `now()->format('Y…')` / `->day` / `->startOfDay()`, odczyt przez zmienną (`$now = now(); … $now->year` — tak wyglądał `ArchiveCategory` przed zmianą). Strażnik JS przepuszcza `new Date ()`, `new Date` bez nawiasów, `Date()` bez `new` i `new Intl.DateTimeFormat(…).format()` bez argumentu. Dziś żadna z tych form nie występuje w `app/` ani w `resources/js`, więc to luka na przyszłość, nie błąd w działaniu.
- **Fix**: Dopisać do `APP_DAY_CLOCK_FORBIDDEN_PATTERNS` wzorce `/->(?:todayOrBefore|beforeToday|afterToday|todayOrAfter)\(/`, `/\b(?:before|after)(?:_or_equal)?:(?:today|now|tomorrow|yesterday)\b/`, `/now\([^)]*\)->(?:year|month|day|toDateString|format|startOf\w*)\b/`; w JS `/new Date\b\s*(?:\(\s*\)|(?![\s(]))/` i `/(?<![\w.])Date\(\)/`; każdy z próbką w autoteście strażnika; w `.ai/rules/app-day.md` jedno zdanie, że odczyt przez zmienną i `date('Y')` łapie tylko przegląd.
  - Strength: Zamyka najgroźniejszą lukę (płynne reguły Laravela) tym samym mechanizmem i autotestem, które już są.
  - Tradeoff: Szerszy `now(...)->format` może dać fałszywe trafienie przy przyszłym znaczniku czasu w nazwie pliku (dziś `BackupUserData.php:33` to `now()->format('YmdHis')` — trzeba go wykluczyć wzorcem albo zawęzić `format` do formatów z samą datą).
  - Confidence: MED — wzorce sprawdzone na obecnym kodzie tylko grepem, nie uruchomione.
  - Blind spot: Indirection przez zmienną zostaje poza zasięgiem regexów.
- **Decision**: FIXED — wzorce PHP (now(…)->year|month|day|toDateString|startOf…, now()->format z samą datą, względne reguły dat, Rule::date()->todayOrBefore() i rodzeństwo) i JS (new Date / new Date (), Date(), Intl format() bez daty) w `tests/Unit/AppDayClockGuardTest.php`, każdy z próbką w autoteście (najpierw czerwień, potem zieleń); celowe zepsucie w DashboardController zgłoszone z plikiem i linią; zdanie o odczycie przez zmienną w `.ai/rules/app-day.md`.

### F2 — Globalny komunikat `before_or_equal` zakłada „dziś” i datę

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: lang/pl/validation.php:5
- **Detail**: Plan świadomie wybrał klucz globalny (Key Discoveries: dziś `before_or_equal` w `app/` znaczy tylko „nie później niż App Day”), więc obecne pola dostają poprawny tekst. Klucz jest jednak globalny i bez `:attribute`: przyszłe `before_or_equal:date_to` (zakres dat — lustro istniejących `after_or_equal` w `IndexOperationsRequest.php:52`, `TermDepositValidationRules.php:22`) albo `before_or_equal:<stała data>` pokaże „Data nie może być w przyszłości.”.
- **Fix A ⭐ Recommended**: Zostawić klucz globalny i dopisać do `.ai/rules/app-day.md` zdanie: `before_or_equal` w `lang/pl/validation.php` znaczy „nie później niż App Day”; inna reguła `before_or_equal` dostaje własny komunikat w `messages()`.
  - Strength: Zero zmian w kodzie, zgodne z decyzją planu; agent edytujący walidację widzi regułę.
  - Tradeoff: Pułapka zostaje, tylko opisana.
  - Confidence: HIGH — reguła ładuje się dla `app/**/*.php`.
  - Blind spot: Człowiek bez agenta reguły nie przeczyta.
- **Fix B**: Przenieść komunikat do `custom.<atrybut>.before_or_equal` (`operation_date`, `transfer_date`, `movement_date`, wzorce z `*` dla przebudowy) albo do `messages()` w traitach.
  - Strength: Usuwa pułapkę u źródła.
  - Tradeoff: Kilka atrybutów i ścieżek (wpis ręczny, przebudowa, uzgodnienie salda) do wyliczenia; łatwo pominąć jedną i wrócić do angielskiego tekstu.
  - Confidence: MED — wymaga sprawdzenia nazw pól we wszystkich 16 regułach i testu na każdą.
  - Blind spot: Nazwy pól w przebudowie operacji (tablice) nie zostały sprawdzone.
- **Decision**: FIXED (Fix A) — `.ai/rules/app-day.md` mówi, że globalny `before_or_equal` znaczy „nie później niż App Day”, a inna reguła dostaje własny komunikat w `messages()`.

### F3 — Docblocki nadal mówią, że dzień to UTC

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/ReportTwelveMonthWindow.php:36, app/Support/ReportYearsRange.php:32, app/Support/PastMonthPlanRefused.php:12
- **Detail**: „`$today` is the day the controller uses (UTC).” oraz „the server day (UTC today, ADR-0103 decides the app day)”, choć `ReportController` i reszta podają już `AppDay::today()`.
- **Fix**: Zmienić na „`$today` is the App Day (ADR-0103)” / „the server App Day (ADR-0103)”.
- **Decision**: FIXED — trzy docblocki mówią o App Day (ADR-0103).

### F4 — `ArchiveCategory` czyta zegar dwa razy

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/ArchiveCategory.php:22,31
- **Detail**: `archived_at` to `$now = now()`, a rok Budżetu to osobny odczyt `AppDay::today()->year`. Oba odczyty mogą (teoretycznie, o mikrosekundy) leżeć po dwóch stronach polskiej północy 31.12, a wtedy `archived_at` i rok archiwizowanej pozycji nie zgadzają się z `AppDay::of(archived_at)`.
- **Fix**: `->where('year', AppDay::of($now)->year)` — jeden odczyt zegara, dzień z tej samej chwili co `archived_at`.
- **Decision**: FIXED — `AppDay::of($now)->year`, jeden odczyt zegara.

### F5 — `lib` sam parsuje `'YYYY-MM-DD'` zamiast helperów `app-day.ts`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/auth-year-progress.ts:17-18, resources/js/lib/operation-dialogs-fixtures.ts:752-753
- **Detail**: Oba pliki powtarzają `Number(today.slice(0, 4))` i `Number(today.slice(5, 7))`, a `.ai/rules/app-day.md` wskazuje `appDayYear` / `appDayMonth`, których używają `format.ts` i `AppSidebar`.
- **Fix**: Importować `appDayYear` / `appDayMonth` z `@/lib/app-day` (dzień miesiąca w `authYearProgress` zostaje jako `slice(8, 10)`).
- **Decision**: FIXED — `appDayYear` / `appDayMonth` przez import względny `./app-day` (alias `@` jest wyłączony w Vitest, `.ai/rules/frontend-toolchain.md`).

### F6 — Drobne rozjazdy dokumentów z kodem i planem

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/test-plan.md:182
- **Detail**: §6.2 opisuje strażnik JS jako zakaz `new Date()` i `Date.now()`, pomija trzeci wzorzec `.toISOString().slice(0, 10)`. Przy okazji, bez potrzeby zmian: kryterium planu 1.2 (`grep -rn "today('UTC')" app tests` „nic nie wypisuje”) trafia w próbkę w autoteście strażnika (`tests/Unit/AppDayClockGuardTest.php:66`) — intencja spełniona; plan wymieniał `/dev/operations` jako importera fixture'ów dialogów, a jedynym importerem jest `DialogsKitchenSink` — błąd planu, nie implementacji.
- **Fix**: Dopisać `.toISOString().slice(0, 10)` do opisu strażnika JS w §6.2.
- **Decision**: FIXED — §6.2 opisuje pełny zestaw wzorców strażnika PHP i JS.

### F7 — Nieplanowane drobne odstępstwa: akapit README i test listy Kategorii

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: README.md (sekcja „Przełom roku”, akapit „00:00–01:00 PL 1 stycznia”), tests/Feature/CategoriesPageTest.php
- **Detail**: README oprócz trzech zaplanowanych edycji skraca akapit o luce 00:00–01:00 (plan: „reszta procedury bez zmian”); akapit opisywał lukę trzech zegarów, której już nie ma, więc zmiana jest zasadna. Nowy test w `CategoriesPageTest` ustawia `archived_at = '2027-01-31 23:30:00'` fabryką, a nie przez `ArchiveCategory` jak mówi kontrakt fazy 1; uzasadnienie planu (Kategoria i pozycja stemplowane razem) dotyczy tylko testów Propozycji i Collected, które używają `ArchiveCategory`.
- **Fix**: Przyjąć oba odstępstwa bez zmian w kodzie (zapis w tym raporcie wystarcza jako addendum).
- **Decision**: ACCEPTED — oba odstępstwa zasadne, ten raport jest addendum; bez zmian w kodzie.

## Triage

Użytkownik: „napraw samodzielnie” — zastosowane rekomendowane poprawki.

- Fixed: F1, F2 (Fix A), F3, F4, F5, F6 (6)
- Accepted: F7 (1)

Weryfikacja po poprawkach (2026-11-19): `AppDayClockGuardTest`, `ArchiveCategoryTest`, `MonthPlanProposalTest`, `IrregularCollectedTest`, `CategoriesPageTest`, `ReportTwelveMonthWindowTest` na PostgreSQL — 94 passed; Vitest 803 passed; `npm run check`, `types:check`, `check:ui`, Pint, Sloppy — passed.
