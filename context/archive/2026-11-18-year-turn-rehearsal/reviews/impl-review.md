<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Próba przełomu roku 2026→2027

- **Plan**: context/changes/year-turn-rehearsal/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-18
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 5 warnings, 4 observations

Uwaga o zakresie: wszystkie wiersze Automated w Progress są `[x]`, wszystkie wiersze Manual (1.4, 2.4, 3.3–3.6, 4.3) są `[ ]`. Przegląd obejmuje fazy 1–4 w całości, a wiersze Manual traktuje jako oczekujące (F5).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | FAIL |

## Success criteria — wyniki

| Kryterium | Polecenie | Wynik |
|---|---|---|
| 1.1 testy na PostgreSQL (+ 2.1) | `docker compose exec -u sail -e DB_CONNECTION=pgsql … php artisan test --compact YearTurnTest CsvImportTest SelectedBudgetYearTest RehearsalClockTest` | ✅ 126 passed (1314 assertions) |
| 1.2 / 2.2 Pint | `vendor/bin/pint --test --format agent <zmienione pliki>` (Sail) | ✅ passed |
| 1.3 / 2.3 bramka | `composer ci:check` (Sail, domyślny SQLite z `phpunit.xml`) | ❌ 5 failed / 1823 passed: 4 nowe testy tej zmiany + `BalanceReconciliationTest` (spoza zmiany) |
| 1.3 / 2.3 Sloppy | `vendor/bin/sloppy ci` (host) | ✅ passed (3 medium, wszystkie poza zmianą) |
| 3.1 liczby kontrolne | — (jednorazowe, wynik w `rehearsal.md`: 44 klucze, tylko `initial_plan` 2027) | ✅ wg raportu |
| 4.1 format i lint | `npm run check` | ✅ 407 plików, 0 ostrzeżeń |
| 4.2 kotwica | `grep -n "#### Przełom roku" README.md` | ✅ README.md:283 |
| Sprzątanie | `storage/app/private/rehearsal-clock`, `storage/app/restore-trial/` | ✅ brak pliku zegara, katalog pusty |

## Findings

### F1 — Cztery nowe testy nie przechodzą na SQLite, więc lokalny `composer ci:check` jest czerwony

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/YearTurnTest.php:119, :266, :267; tests/Feature/CsvImportTest.php:716, :742
- **Detail**: `assertDatabaseHas(..., ['operation_date' => '2027-01-01'])` porównuje kolumnę z castem `date` z łańcuchem `Y-m-d`. PostgreSQL ma typ `date`, więc przechodzi; SQLite trzyma `2027-01-02 00:00:00` („Found similar results … transfer_date: 2027-01-02 00:00:00”). Kryterium 1.3/2.3 to `composer ci:check` w Sail, które biegnie na domyślnym SQLite z `phpunit.xml`, i dziś kończy się 5 błędami. Progress oznacza 1.3 i 2.3 jako przechodzące. CI używa PostgreSQL, więc w CI testy są zielone. Piąty błąd (`BalanceReconciliationTest::saving the same day again…`, :68) nie jest z tej zmiany (plik z 9c8b2ec) i też występuje tylko na SQLite — poza zakresem, ale warto go odnotować osobno.
- **Fix**: Zamienić te asercje na zapytanie niezależne od silnika, np. `$this->assertTrue(FinancialOperation::query()->whereDate('operation_date', '2027-01-02')->where(...)->exists())`, i uruchomić je na obu bazach.
- **Decision**: FIXED — asercje dat przez model i `->toDateString()` (wzorzec `IncomeTest.php:72`); 4 pliki zmiany: 134 passed na SQLite i na PostgreSQL. `BalanceReconciliationTest` zostaje czerwony tylko na SQLite (na PostgreSQL przechodzi) — poza zakresem tej zmiany.

### F2 — Zapis i usunięcie pliku zegara mogą się nie udać bez śladu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/RehearsalClock.php:59, :64; app/Console/Commands/RehearsalClockCommand.php:48; README.md:311
- **Detail**: Dysk `local` ma `'throw' => false` (config/filesystems.php:36-38), a `set()` i `clear()` ignorują `bool` z `put()`/`delete()`. README każe uruchamiać komendę przez `docker compose exec laravel.test …` bez `-u sail`, czyli jako root, więc plik zegara jest root-owned (znany problem w tym repo). Późniejsze ustawienie jako `sail` zwraca `false`, a komenda i tak wypisuje nowy moment (linia 48 drukuje sparsowane wejście, nie zapisany stan). Aplikacja zostaje na starym momencie. To narusza zasadę „nigdy nie ukrywaj dowodów”.
- **Fix**: Sprawdzać wynik `put()`/`delete()` i rzucać `RuntimeException` z `Storage::disk('local')->path(self::FILE)`; komenda wypisuje `RehearsalClock::current()` po zapisie; w README dodać `-u sail`.
- **Decision**: FIXED — `set()`/`clear()` sprawdzają wynik i rzucają `RuntimeException` ze ścieżką pliku; komenda wypisuje zapisany stan (`RehearsalClock::current()`); README z `-u sail`. Testy czerwone przed poprawką: `test_a_failed_write_…`, `test_a_failed_delete_…`.

### F3 — Przypadek Importu przy szkicu przypina inne zachowanie bez oznaczenia znanej luki

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/CsvImportTest.php:680
- **Detail**: Plan (faza 1 §2): przy szkicu wiersz 2027 ma błąd fazy, a wiersz 2026 jest poprawny. Test sprawdza co innego: oba wiersze podglądu są `valid`, zapis obu jest odrzucony w całości z błędem fazy na `rows`, a zapis samego wiersza 0 przechodzi. Odstępstwo jest zapisane w `rehearsal.md` jako kandydat, więc decyzja jest w porządku. Zasada z „Overview” fazy 1 wymaga jednak, żeby test przypinający znaną lukę mówił to w nazwie i miał docblock z odesłaniem do `rehearsal.md` — tak jak `SelectedBudgetYearTest::…_known_gap`. Ten nie ma ani jednego, ani drugiego.
- **Fix**: Dodać docblock z odesłaniem do wpisu w `rehearsal.md` („Znaleziska”, faza 1) i sufiks `_known_gap` w nazwie (oraz zaktualizować nazwę cytowaną w `rehearsal.md`).
- **Decision**: FIXED — docblock z odesłaniem do `rehearsal.md` i sufiks `_known_gap`; nazwa poprawiona w `rehearsal.md`.

### F4 — Zmiana oznaczona jako `implemented`, a wszystkie kroki ręczne czekają

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/year-turn-rehearsal/plan.md:452-489; context/changes/year-turn-rehearsal/rehearsal.md:48
- **Detail**: Wiersze Manual 1.4, 2.4, 3.3–3.6 i 4.3 są `[ ]`, a `change.md` dostał `implemented` w c38b472. Plan wymagał zatrzymania na Twoje potwierdzenie ekranów T3 przed fazą 4; w repo nie ma śladu tego potwierdzenia. Treść dla 1.4, 3.3, 3.4 i 3.6 istnieje w `rehearsal.md`, więc to głównie brak odhaczenia. Dwa sprawdzenia z planu nie mają wyniku: T3 „Raport rok do roku: 1 stycznia wobec 1 stycznia 2026” (raportowane jest tylko „Lata”) oraz T5 „stawka Zebranego za styczeń się wypełnia” (rehearsal.md:48: „nie sprawdzono”, odesłanie do `IrregularCollectedTest`).
- **Fix**: Potwierdzasz 2.4, 3.5 i 4.3 (te wymagają Ciebie), odhaczasz 1.4, 3.3, 3.4, 3.6 na podstawie `rehearsal.md`, a dwa brakujące sprawdzenia zapisujesz w `rehearsal.md` jako „pominięte, pokryte przez …” albo dopełniasz przed archiwizacją.
  - Strength: Progress zaczyna mówić prawdę, a `/10x-archive` nie zamknie zmiany z niepotwierdzonymi ekranami.
  - Tradeoff: Wymaga kilku minut Twojej uwagi; T5 trzeba by powtórzyć, żeby naprawdę zobaczyć stawkę.
  - Confidence: HIGH — stan pól wyboru i treść `rehearsal.md` są jednoznaczne.
  - Blind spot: Nie wiem, czy potwierdzenie T3 padło w rozmowie poza repo.
- **Decision**: FIXED w części — w `rehearsal.md` dopisane jako „pominięte” T3 rok do roku i T5 stawka Zebranego (z testami, które to pokrywają). Wiersze Manual w Progress zostają `[ ]`: 2.4, 3.5 i 4.3 wymagają Twojego potwierdzenia, a 1.4, 3.3, 3.4, 3.6 odhaczysz razem z nimi.

### F5 — Zegar próby: brak bariery dla momentu w przeszłości, luźny parser, zepsuty plik blokuje własne wyłączenie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/RehearsalClockCommand.php:40; app/Support/RehearsalClock.php:30, :45-54
- **Detail**: Trzy słabości tego samego narzędzia. (a) `setTestNow` zamraża czas; ciasteczka sesji i XSRF dostają `Expires = now + 120 min`. Moment starszy niż 2 h od zegara ściennego daje ciasteczka już wygasłe i pętlę logowania (419); limiter logowania Fortify (5/min) też nie wygasa, póki zegar stoi. Dzisiejsze momenty leżą w przyszłości, więc próba przeszła, ale powtórka dla przeszłej daty się nie uda, a README ostrzega tylko o skokach do przodu. (b) `CarbonImmutable::parse($input, 'UTC')` przyjmuje `2027-02-30` (→ 2 marca), pusty łańcuch (→ teraz), `tomorrow` i `12/31/2026` (m/d), więc gałąź błędu w linii 41-45 jest prawie nieosiągalna. (c) Nieczytelny plik rzuca wyjątek przy starcie providera, więc nie zadziała nawet `app:rehearsal-clock --off`, a komunikat nie podaje ścieżki pliku.
- **Fix**: Parsować ściśle (`createFromFormat('!Y-m-d H:i', …, 'UTC')`, opcjonalnie z sekundami), odmawiać momentu starszego niż `now() - session.lifetime`, a w komunikacie wyjątku podać pełną ścieżkę pliku i „usuń ten plik”.
- **Decision**: FIXED — ścisłe formaty `Y-m-d H:i` / `Y-m-d H:i:s` w UTC z odrzuceniem przepełnienia (bez `catch`, Sloppy SL107); odmowa momentu starszego niż `session.lifetime`; `apply()` pomija komendę `app:rehearsal-clock`, więc `--off` działa przy zepsutym pliku; wyjątek podaje pełną ścieżkę i drogę naprawy; README opisuje format, odmowę i limiter. Testy czerwone przed poprawką: `test_command_rejects_…` (5), `test_an_unreadable_file_does_not_block_…`, ścieżka w `test_unreadable_content_…` (dawny test nie sprawdzał ścieżki: PHPUnit zachowuje tylko ostatnie `expectExceptionMessage`).

### F6 — „Kandydat” o Propozycji stycznia jest regułą produktu, nie luką

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/year-turn-rehearsal/rehearsal.md:46; app/Support/MonthPlanProposal.php:232-234
- **Detail**: `rehearsal.md` zostawia otwarte „Propozycja stycznia różni się od grudnia dla `even_annual` i `irregular`… Nie sprawdzano tego z ADR rytmu”. Kod rozstrzyga to jawnie i dla każdego miesiąca, nie tylko stycznia: rytm `EvenAnnual` i `Irregular` zawsze daje `AnnualPlanShare` (ADR-0066/0072). Błędne było sformułowanie planu i researchu („kopiuje grudzień po `category_id`”); README i `YearTurnTest` opisują już zachowanie poprawnie.
- **Fix**: Przenieść wpis w `rehearsal.md` z „Kandydat” do „zgodne z regułą rytmu (MonthPlanProposal.php:232, ADR-0066)”.
- **Decision**: FIXED — wpis w `rehearsal.md` przeniesiony z „Kandydat” do „zgodne z regułą rytmu”.

### F7 — `rehearsal.md` nazywa realny rachunek i zostawia ślad snapshotu na VPS

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: context/changes/year-turn-rehearsal/rehearsal.md:16, :20, :70
- **Detail**: Plan: „bez danych osobowych”. Raport nie ma kwot ani kontrahentów, ale podaje nazwę rachunku („eKonto”), id produkcyjnego użytkownika i ścieżkę snapshotu na VPS. Sam snapshot (pełne dane użytkownika) został na serwerze w `storage/app/private/user-snapshots/`; plan „Sprzątanie” go nie obejmował.
- **Fix**: Zastąpić nazwę rachunku „rachunek mBank z odpowiednikiem w kopii”, a snapshot na VPS usunąć (albo świadomie zostawić i zapisać dlaczego).
- **Decision**: FIXED w części — w `rehearsal.md` bez nazwy rachunku, id użytkownika i nazwy pliku snapshotu. Snapshotu na VPS nie usuwałem (operacja na produkcji): zostaje Twoja decyzja.

### F8 — Komenda miesza angielski i polski

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Console/Commands/RehearsalClockCommand.php:13, :19, :26, :42
- **Detail**: `#[Description]` jest po angielsku, opisy argumentów i każdy komunikat po polsku. Sąsiednie komendy (`SeedPreviousYearCommand.php:22-23`, `ResetUserDataCommand`, `CreateUserCommand`) piszą komunikaty konsoli po angielsku.
- **Fix**: Ujednolicić do jednego języka w pliku — najprościej angielski, jak sąsiedzi.
- **Decision**: FIXED differently — ujednolicone do polskiego (`#[Description]` po polsku), bo plan zapisał komunikaty komendy po polsku („zegar próby wyłączony”); komunikaty wyjątków w `RehearsalClock` zostają po angielsku jak w `app/Support`.

### F9 — Fikstury `YearTurnTest` powstają przed `travelTo`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/YearTurnTest.php:58-74
- **Detail**: `setUp()` i `givenTwoBudgets()` tworzą Kategorie i pozycje w realnym czasie, a dopiero potem podróżują. `CategoryFactory`/`BudgetLineFactory` zapisują domyślny rytm Bieżący z `start_year/start_month = now()`, więc rytm zależy od dnia uruchomienia CI. Dziś jest to nieszkodliwe dzięki regule odczytu ADR-0066, ale nowe przypadki w `CsvImportTest` i `SeedPreviousYearCommandTest` podróżują najpierw.
- **Fix**: Wywołać `$this->travelTo('2026-12-30 10:00:00')` na początku `setUp()`.
- **Decision**: FIXED — `travelTo('2026-12-30 10:00:00')` na początku `setUp()`. Przy okazji przypięty zegar w `test_command_stores_the_moment_in_local`, który po 1.01.2027 trafiłby na nową odmowę przeszłego momentu.

## Triage

Tryb: „napraw samodzielnie” (2026-11-18).

| Decyzja | Ustalenia |
|---|---|
| Fixed | F1, F2, F3, F5, F6, F9 |
| Fixed differently | F8 |
| Fixed w części | F4 (potwierdzenia ręczne czekają), F7 (snapshot na VPS) |

Weryfikacja po poprawkach: 4 pliki testów zmiany 134 passed na SQLite i PostgreSQL; `composer ci:check` (Sail, SQLite) 1835 passed, 1 failed (`BalanceReconciliationTest`, spoza zmiany, tylko SQLite); `vendor/bin/sloppy ci` passed; Pint passed; `npm run check` passed; komenda sprawdzona na kontenerze dev (odczyt, odmowa `2027-02-30 10:00`, ustawienie, `--off`).
