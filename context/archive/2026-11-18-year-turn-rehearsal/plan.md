# Próba przełomu roku 2026→2027 — plan implementacji

## Overview

Pierwszy przełom roku na prawdziwych danych wypada w nocy z 31 grudnia 2026 na 1 stycznia 2027 (wiersz 1 „Na już” w `context/foundation/development-directions.md`). Aplikacja nie ma żadnego zdarzenia przełomu. Wszystko na granicy roku jest albo odczytem liczonym na nowo z zegara (Zebrane, Propozycja planu miesiąca, Dashboard, Raporty), albo bramką, którą otwierasz ręcznie: start wykonania Budżetu i zatwierdzenie planu miesiąca. Ryzyko leży więc w kolejności Twoich kroków i w zegarze, a nie w migracji danych.

Plan robi cztery rzeczy:

- dokłada testy Feature na PostgreSQL dla przełomu, których dziś brakuje;
- dodaje lokalny „zegar próby” (tylko `APP_ENV=local`);
- przeprowadza jedno przejście na kopii Twoich danych w uzgodnionej kolejności (B) oraz drugie dla ścieżki „zapomniany start”;
- zapisuje procedurę N→N+1 w README („Przełom roku”).

Kod produktu się nie zmienia. Jedyny wyjątek: próba lub testy znajdą błąd, który blokuje przełom. Wtedy błąd staje się najpierw nieprzechodzącym testem, a potem poprawką w tej zmianie.

## Current State Analysis

Pełne ustalenia: `context/changes/year-turn-rehearsal/research.md`. Najważniejsze dla planu:

- **Zegar.** Serwer działa w UTC (`config/app.php:68`), więc rok zmienia się o 01:00 czasu warszawskiego. Frontend w ok. 15 miejscach czyta lokalny zegar przeglądarki. Przykłady:
  - `BudgetYearSwitcher.vue:82` oznacza rok bieżący;
  - `ExpenseFormDialog.vue:439` i `IncomeFormDialog.vue:332` ustawiają domyślną datę przez `toISOString()`, czyli w UTC.
- **„Bieżący rok” zależy od strony.**
  - Dashboard czyta Budżet roku kalendarzowego UTC (`DashboardController.php:35-37`).
  - Budżet, Plan miesiąca, Raporty i powłoka biorą najpierw rok z sesji (`SelectedBudgetYear.php:25-36`, ADR-0075).
- **Trzy kroki na produkcji mają twardą kolejność albo termin:**
  1. Grudzień 2026 trzeba zatwierdzić przed 2027-01-01 00:00 UTC. Po tej chwili niezatwierdzony grudzień czyta się jako `none` (`MonthPlanProposal.php:175-177`, `:206-209`). Zatwierdzenie rzuca wtedy „A past month cannot be approved” (`ApproveMonthPlan.php:41-43`), a styczeń bierze roczny udział planu 2027 zamiast kopii grudnia (`MonthPlanProposal.php:244-246`).
  2. Budżet 2027 musi być w wykonaniu przed pierwszym przychodem albo wydatkiem z datą w 2027. Wpis ręczny i Import odrzucają taki rok z komunikatem „Wydatek można zapisać tylko w budżecie w wykonaniu…” (`OperationCategoryChoice.php:126-127`, `FinancialOperationImportValidator.php:49-55`, `lang/pl.json:61,65`).
  3. Start wykonania nie ma blokady daty (`StartBudgetExecution.php:14`). Zamraża `initial_plan = planned` i zeruje `execution`.
- **Stan szkicu na Dashboardzie już istnieje.** Są:
  - odznaka „Szkic” i linia „budżet :year w szkicu” (`Dashboard.vue:105-112`, `lang/pl/ui.php:190,201`);
  - karta „Budżet jest w szkicu” z przyciskiem „Otwórz budżet” (`DashboardBudgetBlock.vue:121-126`);
  - przy braku Budżetu „Brak budżetu na rok :year” z „Utwórz budżet”.

  Testy sprawdzają te stany na datach spoza przełomu (`DashboardTest.php:663`, `:686`, `:1299`).
- **Plan miesiąca w (B) ma pułapkę.** `isBlockedByPreviousMonth` nigdy nie blokuje stycznia (`MonthPlanProposal.php:139-142`), a przyszły miesiąc wolno zatwierdzić (`ApproveMonthPlanTest.php:124`). Po starcie 2027 w grudniu styczeń da się więc zatwierdzić jeszcze w grudniu. Opłaty stałe stycznia czytają wtedy faktyczną płatność grudnia (`MonthPlanProposal.php:266-273`), zanim grudniowe płatności wpłyną.
- **Zebrane przechodzi przez rok samo** (`IrregularCollected.php:61-86`). Stawka stycznia wynosi 0, dopóki 2027 jest szkicem, i wypełnia się po starcie (`:145-173`, test `IrregularCollectedTest.php:136`).
- **Narzędzia próby:**
  - `user:backup` i `user:restore` (snapshot v13);
  - `user:create` i `user:reset-data`;
  - `app:control-numbers --output/--compare`.

  Uruchomiona aplikacja nie ma nadpisania „dziś”. Testy przechodzą przez przełom z `travelTo`. Zainstalowany Carbon 3.15.1 ustawia `setTestNow` na `FactoryImmutable` (`vendor/nesbot/carbon/src/Carbon/Traits/Test.php:52`), więc `Date::setTestNow` przesuwa też `CarbonImmutable`.
- **Brak testów dla:**
  - zatwierdzenia grudnia przez przełom;
  - Importu do roku-szkicu;
  - [2026 w wykonaniu, 2027 szkic] w styczniu;
  - Dashboardu 1–5 stycznia ze szkicem;
  - przelewu własnego i ruchu kapitału w roku-szkicu.

## Desired End State

- Testy na PostgreSQL przechodzą przez przełom w kolejności (B) i w ścieżkach błędu. Przypinają obecne zachowanie, a za rok (2027→2028) pokażą każdą zmianę.
- `php artisan app:rehearsal-clock "2027-01-01 00:30"` ustawia lokalnie „dziś” aplikacji, a `--off` je zdejmuje. Poza `local` komenda odmawia, a hak niczego nie robi.
- Plik `context/changes/year-turn-rehearsal/rehearsal.md` zawiera wynik każdego kroku próby na kopii Twoich danych oraz listę luk 1–5 jako kandydatów na osobne zmiany. Nie ma w nim danych osobowych ani kwot pojedynczych operacji.
- README ma sekcję „Przełom roku” z procedurą N→N+1 w godzinach UTC i PL. Na tę sekcję robisz prawdziwy przełom 30.12–2.01.

### Key Discoveries:

- `app/Support/MonthPlanProposal.php:206-209`: miesiąc jest przeszły, gdy `[year, month] < [today.year, today.month]` w UTC. Granica zatwierdzenia grudnia to `2026-12-31 23:59:59 UTC`.
- `app/Http/Controllers/Dev/*KitchenSinkController.php:13`: wzorzec `abort_unless(app()->isLocal(), 404)` dla kodu tylko lokalnego.
- `tests/Feature/SelectedBudgetYearTest.php:20-41`: helper `resolveFor` buduje sesję ręcznie. Nowe przypadki go używają.
- `storage/app/.gitignore` i `storage/app/private/.gitignore` ignorują wszystko. `storage/app/restore-trial/` jest wyłączony z kopii (`config/backup.php:37`).
- `context/archive/2026-11-10-category-rhythm/reviews/production-rehearsal.md`: układ tabeli kroków próby („Krok | Komenda | Oczekiwany wynik | Wynik”) do ponownego użycia.

## What We're NOT Doing

- Nie dodajemy powiadomienia `budget_not_in_execution` ani nie zmieniamy karty szkicu. Stan szkicu na Dashboardzie już istnieje (luka 1, decyzja z planowania).
- Nie zmieniamy reguły roku w sesji (ADR-0075, luka 3).
- Nie przechodzimy na strefę `Europe/Warsaw` (luka 4) i nie poprawiamy trybu „do tego samego dnia” w „Latach” (luka 5).
- Nie dodajemy blokady zatwierdzania stycznia w grudniu. Pułapkę opisuje README.
- Nie dodajemy daty do `StartBudgetExecution`.
- Nie budujemy Year Close ani Reserve.
- Nie pokazujemy zegara próby w UI (banner, prop Inertii). Zegar widać w odpowiedzi komendy i w dacie na Dashboardzie.
- Nie przywracamy kopii całej instancji (`backup:restore`). Próba używa snapshotu jednego użytkownika w bazie dev.
- Nie dodajemy testu E2E w Playwright do zestawu. Przejście w przeglądarce jest jednorazowe.
- Zmiana nie obejmuje prawdziwego przełomu na produkcji. Zamyka się po próbie, a przełom robisz według README.

## Implementation Approach

Najpierw testy. Przypinają zachowanie z researchu na danych z fabryk i mogą wcześnie pokazać błąd. Potem lokalny zegar, bez którego próba w przeglądarce nie przejdzie przez północ. Trzecia faza to próba na kopii Twoich danych:

- przejście w kolejności (B): zatwierdź grudzień → uruchom 2027 → przełom → Import grudnia do końca → zatwierdź styczeń;
- drugie przejście dla zapomnianego startu, na świeżym przywróceniu tego samego snapshotu.

README powstaje na końcu, z wyników próby, a nie z samego researchu.

Zasada dla znalezisk: błąd, który blokuje przełom (utrata danych, odrzucony krok procedury, zły odczyt planu stycznia), dostaje nieprzechodzący test w `YearTurnTest` i poprawkę w tej zmianie, z commitem `fix(year-turn-rehearsal): …`. Resztę zapisujemy w `rehearsal.md` jako kandydatów.

## Critical Implementation Details

- **Zegar w próbie idzie tylko do przodu.** Hak ustawia stały moment dla każdego żądania, więc wszystkie operacje dostają `created_at` z tej chwili. Sesja bazodanowa liczy wygaśnięcie z tego samego zegara: wygasa, gdy `last_activity < now − SESSION_LIFETIME` (120 min, `DatabaseSessionHandler::expired`). Każdy skok zegara o ponad 2 h do przodu wylogowuje, więc po T2 i po T4 zaloguj się ponownie. Drugie przejście („zapomniany start”) zaczyna się od `user:reset-data` i `user:restore`, a nie od cofania zegara po T4.
- **Daty operacji nie mogą wyprzedzać zegara.** Wpis ręczny i Import mają regułę `before_or_equal:today`, która czyta `Date::parse`, więc idzie za `travelTo` i za zegarem próby. Operacja z datą późniejszą niż „dziś” dostaje błąd daty przyszłej, a nie błąd fazy. Daty w testach i w pliku CSV kończą się najpóźniej na dniu zegara.
- **Zegar przeglądarki ustawiasz osobno.** Hak przesuwa tylko serwer. Rok bieżący w menu i domyślne daty w dialogach czytają czas przeglądarki. W przejściu ustaw go przez `playwright-cli run-code "async page => await page.clock.setFixedTime(new Date('…'))"` na ten sam moment, a w kroku T2 celowo na moment z przesunięciem UTC/PL.
- **Gałąź w głównym checkoutcie.** Stack Sail serwuje główny checkout, więc faza 3 biegnie tam, gdzie wybrana jest gałąź tej zmiany (albo `development` po scaleniu faz 1–2). W worktree podgląd ma osobną bazę (`context/foundation/agents/worktree-preview.md`), a przywrócenie i tak idzie wtedy do niej.

## Phase 1: Testy przełomu

### Overview

Testy Feature, które przechodzą przez `2026-12-31 → 2027-01-01` na danych z fabryk, uruchamiane na PostgreSQL. Przypinają obecne zachowanie. Test, który nie przechodzi, jest znaleziskiem: blokujące przełom naprawiamy według zasady z „Implementation Approach”. Nieblokujące zapisujemy w `rehearsal.md` jako kandydatów, a test przypina wtedy obserwowane zachowanie: nazwa mówi, że to znana luka (wzorem testu pułapki), a docblock odsyła do wpisu w `rehearsal.md`. To nie jest naginanie asercji do kodu, tylko zapis wymagania, którego przed pierwszym uruchomieniem nie znaliśmy.

### Changes Required:

#### 1. Ścieżka przełomu w jednym pliku

**File**: `tests/Feature/YearTurnTest.php` (nowy, `php artisan make:test YearTurnTest --phpunit`, w stylu sąsiednich klas z `RefreshDatabase`)

**Intent**: Jeden plik opisuje przełom jako scenariusz. Za rok czyta się go razem z README.

**Contract**: przypadki, każdy z `travelTo` w UTC:

- **Kolejność (B):**
  - Stan: 2026 w wykonaniu z zatwierdzonym grudniem, 2027 szkicem z co najmniej jedną kategorią Stała opłata i jedną Nieregularną.
  - 2026-12-30: start 2027.
    - Pozycje 2026 (plan, wykonanie, suma operacji) są bez zmian.
    - Dashboard nadal czyta 2026.
    - `ReportYearsRange` nie zawiera 2027.
  - 2027-01-01 00:00:01 UTC:
    - Propozycja stycznia kopiuje grudzień po `category_id`.
    - Zebrane z 31 grudnia przechodzi na styczeń ze stawką z planu 2027.
    - Dashboard pokazuje blok wykonania 2027.
    - Wydatek z datą 2027-01-01 zostaje zapisany (data nie może wyprzedzać zegara, zob. „Critical Implementation Details”).
- **Granica zatwierdzenia grudnia:**
  - 2026-12-31 23:59:59 UTC: zatwierdzenie przechodzi.
  - 2027-01-01 00:00:00 UTC, na świeżym stanie: zatwierdzenie odmawia z „A past month cannot be approved”, a Propozycja stycznia ma kwoty `AnnualPlanShare` z planu 2027.
- **Pułapka (B):** po starcie 2027 dnia 2026-12-30 styczeń 2027 da się zatwierdzić tego samego dnia. Test dokumentuje zachowanie, które README ostrzega, i nie jest oczekiwaniem produktu. Nazwa testu ma to mówić, np. `test_january_can_be_approved_in_december_once_the_next_year_is_started`.
- **Zapomniany start:**
  - 2026 w wykonaniu, 2027 szkic, 2027-01-02.
  - Dashboard: `budget.phase = 'draft'`, `budget_year = 2027`, bez kategorii i bez powiadomień miesiąca. Asercje jak w `DashboardTest.php:686`, ale w dacie przełomu i z 2026 w wykonaniu obok.
  - Wydatek z 2027-01-02 jest odrzucony z komunikatem fazy.
  - Po starcie 2027 ten sam wydatek przechodzi, a stawka Zebranego za styczeń przestaje być 0.
- **Przelew własny i ruch kapitału** z datą 2027-01-02 przy szkicu 2027, zegar 2027-01-02. Oczekiwanie z researchu: przechodzą, bo nie dotykają pozycji Budżetu. Odmowa jest znaleziskiem dla `rehearsal.md` i README, nie poprawką: test przypina wtedy odmowę jako znaną lukę (zasada z „Overview”).

#### 2. Import wyciągu przez przełom

**File**: `tests/Feature/CsvImportTest.php`

**Intent**: Wyciąg 29.12–02.01 importowany przy szkicu 2027 odrzuca wiersze ze stycznia, a po starcie przechodzi w całości. Dziś sprawdzony jest tylko wpis ręczny (`ExpenseTest.php:217`).

**Contract**: dwa przypadki na istniejących helperach pliku, oba z `travelTo('2027-01-02 09:00')`. Wiersze z 2026-12-29 i 2027-01-02:

- przy szkicu: wiersz 2027 ma błąd fazy, a wiersz 2026 jest poprawny;
- po `StartBudgetExecution`: oba wiersze są poprawne i zatwierdzenie zapisuje dwie operacje w pozycjach właściwych lat.

#### 3. Rok w sesji w styczniu

**File**: `tests/Feature/SelectedBudgetYearTest.php`

**Intent**: Przypina rozstrzygnięcie dla [2026 w wykonaniu, 2027 szkic] na 2027-01-05. Bez sesji wybierany jest rok 2027, a z sesją 2026 zostaje 2026 (luka 3, zapisana jako znana).

**Contract**: dwa przypadki przez `resolveFor` na latach [2026, 2027] i dacie 2027-01-05, bez rozszerzania helpera. `SelectedBudgetYear::resolve` nie czyta fazy (`SelectedBudgetYear.php:25-50`), więc komentarz w teście mówi, że faza nie zmienia wyniku (ADR-0075).

#### 4. Szkielet raportu próby

**File**: `context/changes/year-turn-rehearsal/rehearsal.md` (nowy)

**Intent**: Miejsce na znaleziska z fazy 1, zanim faza 3 dopisze przebieg próby.

**Contract**: nagłówki sekcji z fazy 3 (§1), w tym „Znaleziska” wypełnione wynikami pierwszego uruchomienia testów albo „brak”.

### Success Criteria:

#### Automated Verification:

- Nowe i zmienione testy przechodzą na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/YearTurnTest.php tests/Feature/CsvImportTest.php tests/Feature/SelectedBudgetYearTest.php`
- Pint bez zmian po uruchomieniu: `vendor/bin/pint --dirty --format agent`
- Bramka przechodzi: `composer ci:check` (Sail) i `vendor/bin/sloppy ci` (host)

#### Manual Verification:

- Każdy test, który nie przeszedł przy pierwszym uruchomieniu, jest opisany w `rehearsal.md` (sekcja „Znaleziska”) z decyzją: poprawka w tej zmianie albo kandydat

**Implementation Note**: Po zielonej bramce zatrzymaj się na potwierdzenie ręcznego kroku, zanim przejdziesz do fazy 2.

---

## Phase 2: Lokalny zegar próby

### Overview

Ustawialny moment „dziś” dla uruchomionej aplikacji, aktywny wyłącznie przy `APP_ENV=local`. Bez niego próba w przeglądarce nie przejdzie przez północ 31 grudnia.

### Changes Required:

#### 1. Odczyt i zapis momentu

**File**: `app/Support/RehearsalClock.php` (nowy, `php artisan make:class`)

**Intent**: Jedno miejsce, które czyta i zapisuje moment próby oraz decyduje, czy hak działa.

**Contract**:

- Moment leży w pliku `rehearsal-clock` na dysku `Storage::disk('local')` (`storage/app/private`, ignorowany przez git) jako ISO 8601 w UTC. Odczyt i zapis idą tylko przez ten dysk, żeby testy mogły go podmienić.
- `apply()` ustawia `Date::setTestNow()` tylko wtedy, gdy `app()->isLocal()` i plik istnieje.
  - W każdym innym środowisku nie dotyka systemu plików.
  - Gdy plik ma nieczytelną treść, rzuca wyjątek z nazwą pliku i treścią, bez cichego pominięcia.
- `set(CarbonImmutable)` i `clear()` służą komendzie.

#### 2. Hak w providerze

**File**: `app/Providers/AppServiceProvider.php`

**Intent**: Moment próby obowiązuje w każdym żądaniu HTTP i w każdej komendzie lokalnie.

**Contract**: `configureDefaults()` wywołuje `RehearsalClock::apply()` po `Date::use(CarbonImmutable::class)`.

#### 3. Komenda

**File**: `app/Console/Commands/RehearsalClockCommand.php` (nowy, `php artisan make:command`, atrybut `#[Signature]` jak w `ResetUserDataCommand.php:12`)

**Intent**: Ustawianie, odczyt i zdejmowanie zegara jednym poleceniem, bez edycji `.env` i bez restartu kontenera.

**Contract**:

- `app:rehearsal-clock {moment? : Data i godzina w UTC, np. "2027-01-01 00:30"} {--off}`.
- Bez argumentów wypisuje bieżący moment albo „zegar próby wyłączony”.
- Z argumentem zapisuje moment i wypisuje go w UTC i w `Europe/Warsaw`.
- `--off` usuwa plik.
- Poza `local` kończy się kodem 1 i komunikatem, nic nie zapisując.

#### 4. Testy i README

**File**: `tests/Feature/RehearsalClockTest.php` (nowy), `README.md` (tabela „Komendy administracyjne”)

**Intent**: Dowód, że hak nie działa poza `local`, oraz wiersz w tabeli komend.

**Contract**: każdy przypadek zaczyna się od `Storage::fake('local')`. Testy nie dotykają prawdziwego `storage/app/private`, bo uruchomiona bramka w fazie 3 nadpisałaby albo usunęła zegar trwającej próby. Przypadki:

- w środowisku `local` (`$this->app['env'] = 'local'`) zapisany moment ustawia `now()` i `CarbonImmutable::today('UTC')`;
- w `testing` i w `production` ten sam plik niczego nie zmienia;
- komenda poza `local` zwraca 1 i nie tworzy pliku;
- `--off` usuwa plik;
- nieczytelna treść pliku daje wyjątek.

Wiersz README: `app:rehearsal-clock {moment?}` „Zegar próby (tylko lokalnie): ustawia „dziś” aplikacji; `--off` wyłącza; zob. [Przełom roku](#przełom-roku)”.

### Success Criteria:

#### Automated Verification:

- Testy zegara przechodzą: `docker compose exec -u sail laravel.test php artisan test --compact tests/Feature/RehearsalClockTest.php`
- Pint bez zmian po uruchomieniu: `vendor/bin/pint --dirty --format agent`
- Bramka przechodzi: `composer ci:check` (Sail) i `vendor/bin/sloppy ci` (host)

#### Manual Verification:

- Lokalnie `app:rehearsal-clock "2027-01-01 00:30"` zmienia datę na Dashboardzie (linia pod tytułem pokazuje rok 2027), a `app:rehearsal-clock --off` przywraca dzisiejszą

**Implementation Note**: Po zielonej bramce zatrzymaj się na potwierdzenie ręcznego kroku, zanim przejdziesz do fazy 3.

---

## Phase 3: Próba na kopii produkcji

### Overview

Dwa przejścia na kopii Twoich danych w lokalnej bazie dev, z zegarem serwera i przeglądarki ustawionym na kolejne chwile przełomu. Agent prowadzi przeglądarkę przez `playwright-cli` i zapisuje wyniki, a Ty potwierdzasz kluczowe ekrany. Wynik trafia do `rehearsal.md`.

### Changes Required:

#### 1. Raport próby

**File**: `context/changes/year-turn-rehearsal/rehearsal.md` (szkielet z fazy 1)

**Intent**: Zapis próby, z którego powstaje README i który zostaje w archiwum zmiany.

**Contract**: sekcje:

- **„Przebieg”:** data, commit, źródło snapshotu (data UTC, wersja), użytkownik docelowy.
- **„Przygotowanie”:**
  1. Na VPS: `scripts/artisan.sh user:backup <id>`.
  2. Kopia przez `ssh <host> 'cat <ścieżka>' > storage/app/restore-trial/prod-user.json` (`cat` zamiast `scp` ze względu na pliki root-owned).
  3. `user:create "Próba" proba@example.test --password=…`.
  4. `user:restore proba@example.test storage/app/restore-trial/prod-user.json`.
  5. Plik CSV do T4 i T5: kopia świeżego eksportu mBank albo BNP z `storage/app/private/statements/` (faza 5 testing-import-integrity) z datami przesuniętymi na 29.12–02.01, z rachunku, który ma odpowiednik w przywróconych danych. Plik trafia do `storage/app/restore-trial/`.
- **Tabela kroków** „Krok | Zegar serwera (UTC) | Zegar przeglądarki | Co sprawdzić | Oczekiwany wynik | Wynik”, w układzie z `context/archive/2026-11-10-category-rhythm/reviews/production-rehearsal.md`. Kroki:
  - **T0:** zegar `2026-12-30 10:00`. Plan grudnia: jeśli kopia ma grudzień niezatwierdzony, zatwierdź go (kopia jest z listopada, więc zwykle trzeba).
  - **T1:** zegar `2026-12-30 10:00`. Przed startem `app:control-numbers --output=storage/app/restore-trial/before.json` (po T0, żeby zatwierdzenie grudnia nie weszło w różnice), potem start 2027.
    - Dashboard nadal 2026.
    - Raporty „Lata” bez 2027.
    - `app:control-numbers --compare=…/before.json` pokazuje różnice tylko w `lines` pozycji 2027 (plan początkowy, wykonanie). Faza Budżetu nie jest w liczbach kontrolnych; sprawdza ją Dashboard i ekran Budżetu.
  - **T2:** serwer `2026-12-31 23:30` UTC, przeglądarka `2027-01-01 00:30` PL.
    - Menu roku oznacza 2027, a serwer i domyślne daty dialogów mówią 2026-12-31 (luka 4).
    - Plan stycznia dostępny do zatwierdzenia (pułapka B): sprawdź i **nie zatwierdzaj**.
  - **T3:** zegar `2027-01-01 00:30` UTC, przeglądarka `01:30` PL.
    - Dashboard 2027 z blokiem wykonania, Zebranym i pokryciem.
    - Budżet 2027: „Zebrane startowe” bez „prognoza”.
    - Propozycja stycznia kopiuje grudzień: kilka kategorii, w tym opłaty stałe i Nieregularne.
    - Raport rok do roku: 1 stycznia wobec 1 stycznia 2026.
    - „Lata” w trybie „do dnia” (luka 5).
    - Rok w sesji, jeśli wcześniej był wybrany 2026 (luka 3).
  - **T4:** zegar `2027-01-02 09:00`.
    - Import pliku CSV z Przygotowania (krok 5, wiersze 29.12–02.01): wszystkie wiersze poprawne, operacje w pozycjach właściwych lat.
    - Potem zatwierdzenie stycznia.
  - **T5 (zapomniany start):**
    1. `user:reset-data proba@example.test --force` i ponowny `user:restore`.
    2. Zegar `2027-01-02 09:00`, bez zatwierdzania grudnia i bez startu 2027.
    3. Sprawdź:
       - Dashboard pokazuje kartę „Budżet jest w szkicu”;
       - zatwierdzenie grudnia jest niedostępne;
       - Import tego samego pliku odrzuca wiersze ze stycznia z komunikatem fazy;
       - po starcie 2027 Import przechodzi, a stawka Zebranego za styczeń się wypełnia;
       - styczeń bierze roczny udział.
- **„Znaleziska”:** fazy 1 i 3, każde z decyzją „poprawione w tej zmianie (sha)” albo „kandydat”.
- **„Luki 1–5”:** każda z potwierdzeniem albo zaprzeczeniem z próby. To kandydaci na osobne zmiany, bez decyzji o naprawie.
- **„Sprzątanie”:**
  - `app:rehearsal-clock --off`;
  - `user:reset-data proba@example.test --force`;
  - usunięcie plików z `storage/app/restore-trial/`;
  - `page.clock` wraca do realnego czasu (zamknięcie przeglądarki).

  Konto „Próba” zostaje puste (nie ma `user:delete`).

Bez danych osobowych, nazw kontrahentów i kwot pojedynczych operacji. Dopuszczalne są liczby wierszy, liczby kategorii i zgodność „tak/nie”.

#### 2. Poprawki znalezisk blokujących (warunkowo)

**File**: zależnie od znaleziska; test w `tests/Feature/YearTurnTest.php`

**Intent**: Błąd, który blokuje procedurę przełomu, najpierw staje się nieprzechodzącym testem, a potem poprawką. Kryterium „blokuje” podaje „Implementation Approach”.

**Contract**: test czerwony przed poprawką i zielony po niej; commit `fix(year-turn-rehearsal): …`. Brak takich znalezisk zapisuje się w `rehearsal.md` jako „brak”.

### Success Criteria:

#### Automated Verification:

- Porównanie liczb kontrolnych po T1 pokazuje tylko różnice w pozycjach 2027 i w ich planie początkowym: `docker compose exec laravel.test php artisan app:control-numbers --compare=storage/app/restore-trial/before.json`. Do `rehearsal.md` trafia liczba i klucze różnic, bez wartości (to prawdziwe kwoty planu)
- Testy poprawek (jeśli są) przechodzą na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/YearTurnTest.php`

#### Manual Verification:

- Kroki T0–T4 mają w `rehearsal.md` wynik zgodny z oczekiwanym albo znalezisko z decyzją
- Krok T5 (zapomniany start) ma wynik w `rehearsal.md`
- Potwierdzasz ekrany T3: Dashboard 2027, Propozycja stycznia wobec grudnia, „Zebrane startowe”
- Sprzątanie wykonane: zegar wyłączony, dane „Próby” wyczyszczone, `storage/app/restore-trial/` pusty

**Implementation Note**: Po wypełnieniu `rehearsal.md` zatrzymaj się na Twoje potwierdzenie ekranów T3, zanim przejdziesz do fazy 4.

---

## Phase 4: Instrukcja „Przełom roku” w README

### Overview

Stała procedura N→N+1 obok „Próby lokalnej” i „Liczb kontrolnych”, napisana z wyników próby. Na nią robisz prawdziwy przełom 2026→2027 i kolejne.

### Changes Required:

#### 1. Sekcja README

**File**: `README.md` (nowa sekcja `#### Przełom roku` w części „Wdrożenie i przywracanie”, obok „Próba lokalna”)

**Intent**: Kroki z datami i godzinami, ostrzeżenia oraz drogi naprawy, bez odsyłania do archiwum zmiany.

**Contract**: treść:

- **Kolejność:**
  1. Do 31 grudnia N: zatwierdź plan grudnia N. Twardy termin to 31.12 23:59:59 UTC, czyli 1.01 00:59:59 czasu warszawskiego.
  2. 30–31 grudnia, po kroku 1: uruchom wykonanie Budżetu N+1.
  3. Od 1 stycznia: zaimportuj grudzień do końca.
  4. Zatwierdź plan stycznia.
- **Ostrzeżenie:** po kroku 2 aplikacja pozwala zatwierdzić styczeń jeszcze w grudniu. Nie rób tego, bo opłaty stałe stycznia czytają faktyczne płatności grudnia.
- **Drogi naprawy:**
  - zapomniany grudzień: stycznia nie da się już skopiować z grudnia, Propozycja bierze roczny udział, a kwoty poprawiasz ręcznie przed zatwierdzeniem;
  - zapomniany start: uruchom N+1 w dowolnej chwili, bo Zebrane i Propozycje uzupełnią się same, potem powtórz odrzucony Import.
- **Godzina 00:00–01:00 PL 1 stycznia:** menu i domyślne daty dialogów mówią już N+1, a serwer jeszcze N.
- **Krótka lista „co sprawdzić 1–2 stycznia”** z kroków T3–T4 próby.
- **Próba przed przełomem:** odesłanie do `app:rehearsal-clock` i snapshotu użytkownika z krokami przygotowania z `rehearsal.md`.

#### 2. Kierunki rozwoju

**File**: `context/foundation/development-directions.md`

**Intent**: Wiersz 1 „Na już” wskazuje wynik. Luki potwierdzone w próbie są widoczne jako kandydaci obok listy tarć.

**Contract**:

- Komórka „Status” wiersza 1 zmienia się na `zrobione (year-turn-rehearsal)` dopiero przy archiwizacji (zgodnie z „Jak korzystać”). W tej fazie dostaje dopisek z odesłaniem do sekcji README.
- Potwierdzone luki trafiają jednym zdaniem pod tabelę „Na już” z odesłaniem do `rehearsal.md`.

### Success Criteria:

#### Automated Verification:

- Formatowanie i lint przechodzą: `npm run check` (Vite+ `vp check`)
- Link do kotwicy `#przełom-roku` z tabeli komend istnieje: `grep -n "#### Przełom roku" README.md`

#### Manual Verification:

- Czytasz sekcję „Przełom roku” i potwierdzasz, że wykonasz z niej przełom 30.12–2.01 bez otwierania `rehearsal.md`

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów jednostkowych. Odczyty przełomu (Propozycja, Zebrane) mają już testy jednostkowe i Feature. Nowe testy łączą je w ścieżkę.

### Integration Tests:

- `YearTurnTest` testuje:
  - kolejność (B);
  - granicę 23:59:59 / 00:00:00 UTC dla grudnia;
  - pułapkę stycznia;
  - zapomniany start;
  - przelew i ruch kapitału w szkicu.
- `CsvImportTest` testuje wyciąg 29.12–02.01 przy szkicu i po starcie.
- `SelectedBudgetYearTest` testuje [2026 w wykonaniu, 2027 szkic] na 2027-01-05 z sesją i bez niej.
- `RehearsalClockTest` testuje hak w `local`, jego brak w `testing` i `production` oraz komendę.
- Wszystko na PostgreSQL, bo CI i produkcja działają na PostgreSQL.

### Manual Testing Steps:

1. Przejście (B) T0–T4 na kopii Twoich danych, z zegarem serwera i przeglądarki.
2. Przejście „zapomniany start” T5 na świeżym przywróceniu.
3. Przeczytanie sekcji README jako jedynej instrukcji.

## Performance Considerations

`RehearsalClock::apply()` w każdym żądaniu sprawdza najpierw `isLocal()` i dopiero wtedy sięga do pliku. Na produkcji dokłada jedno porównanie łańcucha.

## Migration Notes

Brak migracji. Snapshot produkcji trafia tylko do lokalnej bazy dev, na osobnego użytkownika. Plik JSON leży w `storage/app/restore-trial/` (ignorowany przez git, wyłączony z kopii) i znika przy sprzątaniu.

## References

- Research: `context/changes/year-turn-rehearsal/research.md`
- Szablon tabeli próby: `context/archive/2026-11-10-category-rhythm/reviews/production-rehearsal.md`
- Procedury README: „Próba lokalna”, „Liczby kontrolne”, tabela „Komendy administracyjne”
- Wcześniejsze decyzje o przełomie: `context/archive/2026-11-09-rhythm-and-irregular-decisions/plan.md:61`, `:264`; ADR-0066, ADR-0073, ADR-0074, ADR-0075, ADR-0076
- Wzorzec kodu tylko lokalnego: `app/Http/Controllers/Dev/DashboardKitchenSinkController.php:13`
- Testy, które nowe uzupełniają: `tests/Feature/DashboardTest.php:686`, `tests/Feature/ApproveMonthPlanTest.php:124`, `:144`, `tests/Feature/IrregularCollectedTest.php:136`, `tests/Feature/SelectedBudgetYearTest.php:43-68`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Testy przełomu

#### Automated

- [x] 1.1 Nowe i zmienione testy przechodzą na PostgreSQL — a62afab
- [x] 1.2 Pint bez zmian po uruchomieniu — a62afab
- [x] 1.3 Bramka przechodzi: composer ci:check i sloppy ci — a62afab

#### Manual

- [ ] 1.4 Testy nieprzechodzące przy pierwszym uruchomieniu opisane w rehearsal.md z decyzją

### Phase 2: Lokalny zegar próby

#### Automated

- [x] 2.1 Testy zegara przechodzą — b750442
- [x] 2.2 Pint bez zmian po uruchomieniu — b750442
- [x] 2.3 Bramka przechodzi: composer ci:check i sloppy ci — b750442

#### Manual

- [ ] 2.4 app:rehearsal-clock zmienia i przywraca datę na Dashboardzie

### Phase 3: Próba na kopii produkcji

#### Automated

- [x] 3.1 Liczby kontrolne po T1 różnią się tylko w pozycjach 2027 — 2b25633
- [x] 3.2 Testy poprawek (jeśli są) przechodzą na PostgreSQL — 2b25633

#### Manual

- [ ] 3.3 Kroki T0–T4 z wynikiem w rehearsal.md
- [ ] 3.4 Krok T5 (zapomniany start) z wynikiem w rehearsal.md
- [ ] 3.5 Potwierdzone ekrany T3
- [ ] 3.6 Sprzątanie wykonane

### Phase 4: Instrukcja „Przełom roku” w README

#### Automated

- [x] 4.1 Formatowanie i lint przechodzą — 4f85f50
- [x] 4.2 Kotwica sekcji Przełom roku istnieje — 4f85f50

#### Manual

- [ ] 4.3 Sekcja README wystarcza do przełomu bez rehearsal.md
