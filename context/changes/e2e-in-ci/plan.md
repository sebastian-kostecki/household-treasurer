# E2E w CI — plan implementacji

## Overview

Etap 2 planu testów (roadmap S-44, ryzyko #7) dodaje do `.github/workflows/tests.yml` osobny job `e2e`. Na każdym pushu i PR uruchamia on pięć speców Playwright oznaczonych `@gate`, na świeżej bazie PostgreSQL z kontem utworzonym przez `demo:seed-household`. Działa bez powtórek (`retries: 0`) i zapisuje trace przy porażce. Spec trafia do bramki dopiero wtedy, gdy spełnia §6.3 planu testów: sprawdza brak `pageerror`, bierze rok budżetu z aplikacji, a celowe zepsucie przepływu go czerwieni. Dlatego wcześniejsze fazy dostosowują istniejące specy, piszą spec zatwierdzenia Importu i usuwają z seedera daty po dziś, które w CI pojawiałyby się 1–14 stycznia.

## Current State Analysis

- **CI nie uruchamia Playwrighta.** `.github/workflows/tests.yml` ma jeden job `ci` (`:17-102`): PostgreSQL 18, PHP 8.5, Sloppy, `composer ci:check`. Trwa zwykle ok. 3 min 15 s, z czego `ci:check` to 1 min 47 s. Nie ma instalacji przeglądarek ani speców E2E. Wyzwalacze: push do `main` i `development` oraz każdy `pull_request` (`:3-9`). Gałęzie nie mają ochrony ani rulesetów (`gh api …/protection` → 404), więc „required” to dziś konwencja: czerwone CI po pushu.
- **Serwer E2E wymaga Sail.** `scripts/e2e-server.sh:25-28` odmawia startu bez sieci dockera `household-treasurer_sail`, a migrację i `php -S` uruchamia w obrazie `sail-8.5/app` (`:35-50`). Na runnerze tej sieci nie ma. Skrypt świadomie używa `php -S` z `server.php` zamiast `artisan serve`, który gubi nadpisania env (`:41-43`).
- **Konfiguracja Playwright.** `playwright.config.ts` ustawia `retries: CI ? 2 : 0` (`:19`), co plan testów zabrania w bramce (§2 #7, §5), oraz `trace: 'on-first-retry'` (`:29`), który bez powtórek nic nie zapisze. `workers: 1` zostaje (`:20-25`, wspólna sesja bez blokad). Komentarz przy ładowaniu `.env` (`:4-8`) twierdzi, że w CI pliku nie ma, a workflow kopiuje `.env.example`. Sprawdzone na Node 24: `process.loadEnvFile` nie nadpisuje zmiennych już ustawionych w środowisku, więc wartości z joba wygrywają z pustym `E2E_USERNAME=`.
- **Konto testowe istnieje tylko lokalnie.** `auth.setup.ts:6-13` czyta `E2E_USERNAME` i `E2E_PASSWORD`. `demo:seed-household <email>` sam tworzy użytkownika fabryką, z hasłem `password` i zweryfikowanym adresem (`DemoHouseholdSeeder.php:33-37`, `UserFactory.php:30-31`). Odmawia, gdy użytkownik ma już dane, ale na świeżej bazie to bez znaczenia. Kolejka ani scheduler nie są potrzebne.
- **Seeder tworzy daty po dziś w dniach 1–14 stycznia.** `pastDateInYear` (`DemoHouseholdSeeder.php:327-336`) zwraca `YYYY-01-15`, gdy „N dni temu” wypada w poprzednim roku. Seed z 5 stycznia daje więc większość operacji, przelewy własne i ruch kapitału z datą o 10 dni w przyszłości. `monthEndsInYear` (`:299-325`) ma w tym roku fallback na dziś. Gałąź `01-15` jest tam nieosiągalna, bo `$year = now()->year` (`:29`).
- **Specy-bramki nie spełniają §6.3:**
  - `import-other-format-without-header.spec.ts` bierze rok z `new Date().getFullYear()` (`:16`) i ma znanego flake'a. `Date.now()` (13 cyfr) w opisie przechodzi test Luhna w ok. 1 na 10 przebiegów i serwer wycina go jako numer karty (`DefaultPaymentInstrumentNumberOmitter.php:189-228`), więc `getByText(…, { exact: true })` nie znajduje wiersza. Otwarty punkt w `context/archive/2026-11-10-category-rhythm/follow-ups/review-fixes.md:6`. Daty `${year}-03-15` są też w przyszłości przed 15 marca, a podgląd unieważnia takie wiersze (`CsvImportPreviewValidator.php:20-50`).
  - `month-plan-groups.spec.ts:59` i `irregular-collected.spec.ts:58` biorą rok z zegara runnera i nie sprawdzają `pageerror`.
  - `seed.spec.ts` spełnia §6.3 i jest wzorcem.
- **Nie ma speca zatwierdzenia Importu.** Przepływ (`OperationsImport*.vue`, `ImportPreviewRow.vue`, `ImportPreviewSubmitBar.vue`, `CsvImportController::store` `:151-356`):
  1. Wiersze startują niezaznaczone.
  2. Każdy wymaga kategorii.
  3. „Zatwierdź import” jest wyłączony z podanym powodem, dopóki czegoś brakuje.
  4. Po sukcesie następuje przekierowanie na `/operations` z toastem „N operacje zaimportowane.”.
  5. Nie ma „cofnij import”; operacje usuwa się pojedynczo z menu wiersza.

  Podgląd żyje w sesji (`CsvImportSession.php`), stąd `workers: 1`.
- **Rok z aplikacji jest już dostępny.** S-39 (`shell.today`) nie jest zbudowane. Menu roku w pasku bocznym (`BudgetYearSwitcher.vue:254-288`) wymienia lata jako `menuitemradio`, a szkic oznacza `.sidebar-year-state-draft` (przez `aria-describedby`). Po świeżym seedzie najnowszy rok bez „szkic” to rok seedu, czyli rok serwera w UTC. Znacznik „bieżący” i „Dodaj rok” biorą rok z zegara przeglądarki (`BudgetYearSwitcher.vue:82`), więc się nie nadają.

### Decyzje z wywiadu (2026-11-19)

1. **Konto w CI:** jawnie w workflow (`E2E_USERNAME: e2e@example.test`, `E2E_PASSWORD: password`), a job seeduje świeżą bazę przez `demo:seed-household e2e@example.test`. Sekrety GitHuba odrzucone: PR Dependabota i z forków ich nie dostają, a hasło chroni bazę, która żyje tylko w trakcie joba.
2. **Układ:** osobny job `e2e` równolegle do `ci`, z własną usługą `pgsql`. Krok po `ci:check` odrzucony, bo czerwony `ci:check` ukrywałby wynik E2E.
3. **Okno 1–14 stycznia:** seeder nie tworzy dat po dziś, a dowodem jest test Feature z `travelTo` na 2027-01-01 i 2027-01-05. Wywiad wspominał też próbę bramki z zegarem próby, ale po korekcie w rozmowie ją porzucono: zegar próby przestawia tylko serwer, więc taki przebieg sprawdzałby S-39 (dialog bierze datę z przeglądarki). Zegar próby w CI odrzucony, bo CI przestałoby testować prawdziwą datę.
4. **Dowód na GitHubie:** lokalnie `actionlint` i próba w trybie CI (`CI=1`, świeża baza, ten sam seed, `--repeat-each=20`). Przebieg na GitHubie to krok ręczny po pushu `development` przez użytkownika. Push gałęzi odrzucony, bo lokalny `development` ma 105 niewypchniętych commitów.

## Desired End State

- Push do `development` (i każdy PR) uruchamia job `e2e` obok `ci`. Job seeduje świeżą bazę, uruchamia `setup` i pięć speców `@gate` bez powtórek. Przy porażce zostawia artefakt z raportem HTML i trace.
- Celowe zepsucie przepływu w każdym specu-bramce czerwieni ten spec. Wszystkie bramki sprawdzają brak `pageerror` i biorą rok z menu roku, nie z zegara runnera.
- Próba w trybie CI przechodzi 20 razy z rzędu (`--repeat-each=20`, 100 przebiegów bramek) na świeżej bazie.
- `demo:seed-household` uruchomiony 1 albo 5 stycznia nie tworzy żadnej operacji, przelewu własnego ani ruchu kapitału po dziś.
- `context/foundation/test-stack.md` i `context/foundation/test-plan.md` (§4, §5, §6.3, §6.6) opisują stan po zmianie.
- Pozostałe 11 speców działa lokalnie jak dotąd. `npx playwright test` uruchamia każdy spec dokładnie raz.

### Key Discoveries:

- `process.loadEnvFile('.env')` zostawia zmienne ustawione wcześniej w środowisku (sprawdzone na Node v24.21.0), więc `env:` joba wygrywa z pustymi wartościami z `.env.example`.
- Playwright obsługuje `grep` na poziomie projektu (`node_modules/playwright/types/test.d.ts:372-389`). Projekt `gate` z `grep: /@gate/` i zależnością `setup` uruchamia logowanie bez tagowania `auth.setup.ts`.
- Logowanie ma limit 5 prób na minutę na adres i IP (`FortifyServiceProvider.php:90-94`). `--repeat-each` powtarza też projekt `setup`, więc próba stabilności loguje się raz, a powtarza tylko bramki (`--no-deps`).
- Duplikat Importu to ten sam rachunek, data, kwota ze znakiem i surowy opis (`ImportIdentityDigest.php:16-41`). Unikalny stamp w opisie wyklucza duplikat po przerwanym przebiegu. Wykrywanie par (`ImportPairCandidateDetector.php:122-176`) patrzy na przeciwne kwoty na innym rachunku w ±3 dni, więc spec używa jednego rachunku i nietypowych kwot.
- Rachunki z seedu są otwarte `YYYY-01-01` (`DemoHouseholdSeeder.php:30`). Data `<rok seedu>-01-01` jest więc zawsze ≥ otwarciu i ≤ dziś serwera.

## What We're NOT Doing

- Wszystkich 15 speców w CI. Pozostałe zostają lokalne (§5, §2 #7 anti-pattern). Nie poprawiamy też zegara runnera w `categories.spec.ts` ani `reports.spec.ts`.
- `retries` ani `failOnFlakyTests` (odrzucone w F-09). Spec, który flakuje, wychodzi z bramki, a nie dostaje powtórki.
- Ochrony gałęzi ani wymaganego checku na GitHubie. Użytkownik merguje lokalnie i pushuje, więc bramką jest czerwony job `e2e`.
- Zmiany wyzwalaczy workflow. Job dziedziczy push do `main` i `development` oraz każdy PR, co obejmuje push i PR do `development` z §5.
- Cache przeglądarek Playwright. Instalacja Chromium to ok. 30–60 s; cache wróci, jeśli czas joba zacznie przeszkadzać.
- `demo:seed-previous-year` w CI. Żadna bramka nie potrzebuje roku poprzedniego.
- S-39: daty domyślnej w dialogach z zegara przeglądarki ani znacznika „bieżący” w menu roku.
- Fallbacku `01-15` w `monthEndsInYear`, bo gałąź jest nieosiągalna przy `$year = now()->year`.
- Pushu gałęzi i draft PR. Commity zostają lokalnie.
- Statusu §3 w `test-plan.md`, który przestawia orkiestrator `/10x-test-plan`.

## Implementation Approach

Kolejność idzie od przyczyny do bramki. Najpierw seeder (czerwony test, potem poprawka), bo CI będzie go wołać na każdym przebiegu. Potem cztery istniejące specy i nowy spec, każdy dowiedziony celowym zepsuciem na lokalnym serwerze E2E z Sail, według `context/foundation/agents/parallel-changes.md`. Dopiero gdy pięć speców spełnia §6.3, config dostaje tryb CI i przechodzi próbę stabilności na świeżej bazie, tak jak zrobi to runner. Na końcu job w workflow i dokumenty. Workflow zmienia się raz, już na sprawdzonym configu.

Tag `@gate` stoi w samym specu (`test('…', { tag: '@gate' }, …)`), więc lista bramek jest widoczna obok kodu, a `--list` ją pokazuje. Projekt `gate` wybiera tagowane testy, a `chromium` je pomija (`grepInvert`). Dzięki temu pełny lokalny przebieg uruchamia każdy spec raz, a CI i lokalna próba wołają `npm run e2e:gate`.

## Critical Implementation Details

- **Logowanie przy próbie stabilności.** Uruchom najpierw `--project=setup`, potem `--project=gate --no-deps --repeat-each=20`. Samo `--repeat-each=20` z zależnością powtórzyłoby logowanie i mogło trafić w limit 5/min (429).
- **Wybór serwera.** `webServer.command` wybiera skrypt runnera po `GITHUB_ACTIONS`, a nie po `CI`. Lokalna próba z `CI=1` ma dostać semantykę CI (bez `reuseExistingServer`, `forbidOnly`, reportery), ale nadal serwer z obrazu Sail, bo host ma PHP 8.3.
- **Środowisko lokalne agenta.** `npx` musi być linuksowym Node 24 (`$HOME/.nvm/versions/node/v24.21.0/bin` na początku `PATH`). `npm run build` woła `php artisan wayfinder:generate`, więc potrzebny jest shim `php` uruchamiający `sail-8.5/app`. Dane logowania do bazy skopiowanej z szablonu biorą się z `.env` głównego checkoutu (eksport bez wypisywania).

## Faza 1: Seeder bez dat po dziś

### Overview

Najpierw czerwony test, który przypina 1 i 5 stycznia, potem poprawka fallbacku w `pastDateInYear`. Od tej fazy seed w CI daje spójne dane przez cały rok.

### Changes Required:

#### 1. Test seedera w styczniu

**File**: `tests/Feature/SeedDemoHouseholdCommandTest.php`

**Intent**: Dowieść, że seed wykonany na początku roku nie tworzy dat po dziś ani przed otwarciem rachunków. Test ma być czerwony przed poprawką, bo dziś daje `2027-01-15`.

**Contract**: Nowy przypadek (dwa momenty przez data provider albo dwie metody, w stylu klasy PHPUnit pliku): `travelTo` na `2027-01-01 10:00:00 UTC` i `2027-01-05 10:00:00 UTC`, potem `Artisan::call('demo:seed-household', ['user' => <email>])` dla świeżego użytkownika. Asercje: dla `FinancialOperation.operation_date`, `OwnTransfer.transfer_date` i `CapitalMovement.movement_date` użytkownika maksimum ≤ dziś, a minimum ≥ `2027-01-01`. Uruchamiany na PostgreSQL (daty).

#### 2. Fallback na dziś

**File**: `database/seeders/DemoHouseholdSeeder.php`

**Intent**: Gdy „N dni temu” wypada w poprzednim roku, `pastDateInYear` zwraca dzisiejszą datę zamiast `YYYY-01-15`. Każda data z tej metody leży wtedy w przedziale [1 stycznia roku seedu, dziś].

**Contract**: `pastDateInYear(int $year, int $daysAgo): string` bez zmiany sygnatury. Fallback `sprintf('%d-01-15', $year)` (`:332`) zastąpiony dzisiejszą datą (`now()`, więc zegar próby i `travelTo` działają). Reszta metody bez zmian.

### Success Criteria:

#### Automated Verification:

- Nowy test seedera jest czerwony przed poprawką (`2027-01-15` > dziś) na PostgreSQL: worktree command z `AGENTS.md` z `-e DB_DATABASE=testing_e2e_in_ci` i ścieżką `tests/Feature/SeedDemoHouseholdCommandTest.php`
- Po poprawce `tests/Feature/SeedDemoHouseholdCommandTest.php` przechodzi na PostgreSQL
- `tests/Feature/DatabaseSeederTest.php` i `tests/Feature/SeedPreviousYearCommandTest.php` przechodzą na PostgreSQL
- Pint na zmienionych plikach PHP (w obrazie Sail, ze ścieżkami plików)

---

## Faza 2: Specy-bramki zgodne z §6.3

### Overview

Cztery istniejące specy-bramki dostają rok z aplikacji, sprawdzanie `pageerror`, odporny na Luhna stamp i tag `@gate`. Każdy zostaje dowiedziony celowym zepsuciem przepływu.

### Changes Required:

#### 1. Pomocnik roku z menu

**File**: `tests/e2e/support/budget-year.ts`

**Intent**: Dać specom rok budżetu z aplikacji: najnowszy rok w menu, który nie jest szkicem. Po świeżym seedzie to rok serwera, a w lokalnej bazie E2E rok w wykonaniu, nawet gdy inny spec zostawił szkic następnego roku.

**Contract**: Nowy eksport `newestExecutionBudgetYear(page: Page): Promise<number>`. Otwiera menu roku (istniejący `openYearMenu`), czyta wszystkie `menuitemradio` i pomija te z `.sidebar-year-state-draft`. Zwraca największy rok, zamyka menu (Escape, menu znika) i rzuca błąd, gdy żaden rok się nie kwalifikuje. Wymaga strony z paskiem bocznym.

#### 2. Rok z aplikacji i `pageerror` w specach planu miesiąca

**File**: `tests/e2e/month-plan-groups.spec.ts`, `tests/e2e/irregular-collected.spec.ts`

**Intent**: `openCurrentMonthPlan` bierze rok z `newestExecutionBudgetYear` zamiast `new Date().getFullYear()`. Każdy spec zbiera `pageerror` od początku testu i na końcu sprawdza pustą listę, jak `seed.spec.ts:11-12,52`.

**Contract**: Asercja `expect(pageErrors).toEqual([])` stoi po bloku `try/finally`, więc obejmuje też sprzątanie. Test dostaje `{ tag: '@gate' }`.

#### 3. Stamp i daty w speku importu bez nagłówka

**File**: `tests/e2e/import-other-format-without-header.spec.ts`

**Intent**: Usunąć flake'a Luhna i zależność od zegara runnera. Stamp to `Date.now().toString(36)`, nigdy 13–19 samych cyfr. Oba wiersze CSV mają datę `<rok z menu>-01-01`, zawsze ≤ dziś serwera i ≥ otwarciu rachunków. Rok czyta się po wejściu na `/operations/import`.

**Contract**: Test dostaje `{ tag: '@gate' }`. Asercje kolumn „Kolumna 1..3” i obu wierszy podglądu zostają. Zamknięcie punktu `review-fixes.md:6` odnotowane w notatkach zmiany.

#### 4. Tag spec seed

**File**: `tests/e2e/seed.spec.ts`

**Intent**: Oznaczyć wzorcowy spec jako bramkę. Już spełnia §6.3.

**Contract**: `{ tag: '@gate' }`, poza tym bez zmian.

#### 5. Celowe zepsucia

**File**: kod produkcyjny przepływu każdego speca (zmiana tymczasowa, cofana)

**Intent**: Dowieść, że każda bramka czerwieni się, gdy jej przepływ nie kończy się skutkiem na stronie (§6.3).

**Contract**: Po jednym zepsuciu na spec, np.:
- `seed` — zapis wydatku z dialogu gubi opis;
- `month-plan-groups` — zwinięta grupa ukrywa `data-group-over-plan`;
- `irregular-collected` — dialog kategorii nie wysyła „Zebrane startowe”;
- `import-other-format-without-header` — przełącznik nagłówka nie zmienia stanu formularza;
- jeden `throw` w `onMounted` strony przepływu — dowód, że asercja `pageerror` działa.

Implementujący zapisuje w `change.md` (Notes), które zepsucie czerwieni który spec. Każde zepsucie jest cofnięte przed commitem.

### Success Criteria:

#### Automated Verification:

- Każdy z czterech speców przechodzi lokalnie na bazie `e2e_e2e_in_ci` (szablon `household_treasurer_e2e`, port 81xx według `parallel-changes.md`): `E2E_PORT=<port> E2E_DB_DATABASE=e2e_e2e_in_ci npx playwright test tests/e2e/<spec>.spec.ts`
- Celowe zepsucie przepływu czerwieni każdy z czterech speców, a po cofnięciu spec jest zielony
- `throw` w `onMounted` strony przepływu czerwieni spec przez asercję `pageerror`
- `grep -n "new Date().getFullYear()" tests/e2e/{seed,import-other-format-without-header,month-plan-groups,irregular-collected}.spec.ts` nic nie zwraca
- `npx vp check` (lint i format) przechodzi dla zmienionych plików TS

---

## Faza 3: Spec zatwierdzenia Importu

### Overview

Nowa bramka przechodzi przez cały przepływ: wgranie CSV, podgląd, zatwierdzenie, operacje na liście po przeładowaniu, sprzątanie. Do tego cookbook §6.3 dostaje wzorzec.

### Changes Required:

#### 1. Spec

**File**: `tests/e2e/import-approve.spec.ts`

**Intent**: Chronić zatwierdzenie Importu, czyli przepływ, którego nie widzą testy HTTP: zaznaczenie wierszy, kategoria w wierszu, odblokowanie przycisku i wysłanie wyboru z DOM. Skutek ma być widoczny na liście i trwały po przeładowaniu.

**Contract**: `{ tag: '@gate' }`, zbieranie `pageerror`, stamp `Date.now().toString(36)`, rok z `newestExecutionBudgetYear`. Kroki:

1. CSV formatu „Inny” z nagłówkiem: `Data;Opis;Kwota`, dwa wiersze z datą `<rok>-01-01`, opisami `E2E zatwierdzenie A <stamp>` i `E2E zatwierdzenie B <stamp>`, kwotami `-12,37` i `-3,41`.
2. Rachunek „eKonto” po dokładnej nazwie, potem „Pokaż podgląd”.
3. Dla każdego wiersza: checkbox „Zaznacz wiersz: 01.01.<rok>, <opis>” i kategoria „Żywność” w comboboxie „Kategoria / konto: 01.01.<rok>, <opis>”, wybrana jawnie, bo mogła być podpowiedziana.
4. „Zatwierdź import”.

Asercje:
- URL `/operations` i toast „2 operacje zaimportowane.”;
- `/operations?q=<stamp>` pokazuje oba opisy z kwotami −12,37 i −3,41, także po `reload`;
- brak `pageerror`.

Sprzątanie w `finally`: każdą zaimportowaną operację usuwa menu wiersza „Więcej akcji” → „Usuń” → dialog „Usunąć wydatek?” (wzorzec `seed.spec.ts:44-50`), z asercją, że opis zniknął.

#### 2. Cookbook

**File**: `context/foundation/test-plan.md`

**Intent**: Zastąpić „Spec zatwierdzenia Importu: TBD — see §3 Phase 2” (§6.3, `:192`) wzorcem z tej fazy. Wzorzec obejmuje stamp base36 (Luhn), datę `<rok>-01-01`, jawny wybór kategorii, jeden rachunek i nietypowe kwoty (pary) oraz sprzątanie pojedynczo.

**Contract**: Jeden punkt w §6.3. Nazwa speca trafia też do listy bramek w §5 (`:130`, „nazwę nada S-44”).

#### 3. Celowe zepsucie

**File**: kod produkcyjny przepływu Importu (zmiana tymczasowa, cofana)

**Intent**: Dowieść, że spec czerwieni się, gdy zatwierdzenie nie daje skutku. Przykład: wysyłka zatwierdzenia gubi kategorię wiersza, więc serwer odrzuca zestaw i toast się nie pojawia. Albo akcja zapisuje operacje bez opisu.

**Contract**: Zapis w `change.md` (Notes) i cofnięcie przed commitem.

### Success Criteria:

#### Automated Verification:

- `E2E_PORT=<port> E2E_DB_DATABASE=e2e_e2e_in_ci npx playwright test tests/e2e/import-approve.spec.ts` przechodzi lokalnie
- Celowe zepsucie zatwierdzenia czerwieni spec, a po cofnięciu jest zielony
- Po przebiegu w bazie `e2e_e2e_in_ci` nie ma operacji z opisem zawierającym stamp przebiegu
- `npx vp check` przechodzi dla nowego speca

#### Manual Verification:

- Trace albo raport HTML nowego speca pokazuje kolejno: podgląd z dwoma wierszami, toast „2 operacje zaimportowane.” i oba wiersze na liście po przeładowaniu

---

## Faza 4: Tryb CI Playwrighta

### Overview

Config dostaje projekt `gate`, politykę bez powtórek z trace przy porażce i serwer dla runnera. Całość przechodzi próbę stabilności na świeżej bazie, przygotowanej tak, jak zrobi to job.

### Changes Required:

#### 1. Konfiguracja

**File**: `playwright.config.ts`

**Intent**: Bramka bez powtórek i z trace przy porażce, wybór bramek przez projekt, serwer dla runnera. Komentarz o `.env` zgodny z faktami.

**Contract**:
- `retries: 0` (komentarz: §5 planu testów, powtórka maskuje flake'a);
- `use.trace: 'retain-on-failure'`;
- `reporter`: w CI `[['list'], ['github'], ['html', { open: 'never' }]]`, lokalnie `'html'` jak dotąd;
- projekty: `setup` bez zmian, `chromium` z `grepInvert: /@gate/`, nowy `gate` z tymi samymi `use` i `dependencies: ['setup']` oraz `grep: /@gate/`;
- `webServer.command`: `bash scripts/e2e-server-ci.sh ${PORT}`, gdy ustawione `GITHUB_ACTIONS`, inaczej dotychczasowy `bash scripts/e2e-server.sh ${PORT}`;
- `reuseExistingServer: !process.env.CI` i `workers: 1` bez zmian;
- komentarz przy `loadEnvFile`: w CI `.env` pochodzi z `.env.example`, a zmienne joba wygrywają, bo `loadEnvFile` nie nadpisuje ustawionych.

#### 2. Serwer na runnerze

**File**: `scripts/e2e-server-ci.sh` (nowy)

**Intent**: Serwer aplikacji dla Playwrighta na runnerze GitHuba, bez dockera. Build, migracja i seed robi job wcześniej, więc skrypt tylko sprawdza warunki i uruchamia serwer.

**Contract**: `scripts/e2e-server-ci.sh <port>`, `set -euo pipefail`, polskie komentarze jak w `e2e-server.sh`. Kończy się błędem, gdy istnieje `public/hot` albo brakuje `public/build/manifest.json`. Na końcu `exec php -S 127.0.0.1:<port>` z katalogu `public` z routerem `../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` i `PHP_CLI_SERVER_WORKERS=4`. Konfiguracja bazy pochodzi ze zmiennych joba. Komentarz wyjaśnia, dlaczego `php -S` zamiast `artisan serve` (za `e2e-server.sh:41-43`).

#### 3. Skrypt npm

**File**: `package.json`

**Intent**: Jedna komenda bramki dla CI i dla człowieka.

**Contract**: `"e2e:gate": "playwright test --project=gate"`. Przed edycją przeczytaj `.ai/rules/frontend-toolchain.md` (reguła obejmuje `package.json`).

#### 4. Próba w trybie CI i stabilność

**File**: brak zmian w repo (procedura z wynikiem zapisanym w `change.md`)

**Intent**: Uruchomić bramkę tak jak job: pusta baza, migracja, seed `e2e@example.test`, `CI=1`, bez powtórek. Dowieść stabilności 20 kolejnymi przebiegami.

**Contract**:
1. Pusta baza `e2e_ci_e2e_in_ci` (`create database`, bez szablonu).
2. `artisan migrate --force` i `artisan demo:seed-household e2e@example.test` w obrazie `sail-8.5/app` z `-e DB_DATABASE=e2e_ci_e2e_in_ci`.
3. `CI=1 E2E_PORT=<port> E2E_DB_DATABASE=e2e_ci_e2e_in_ci E2E_USERNAME=e2e@example.test E2E_PASSWORD=password npx playwright test --project=setup`.
4. To samo z `--project=gate --no-deps --repeat-each=20`.
5. Smoke `scripts/e2e-server-ci.sh`, bo próba z `CI=1` idzie przez `e2e-server.sh` (wybór po `GITHUB_ACTIONS`), a nowy skrypt nie może pierwszy raz wystartować dopiero na GitHubie. Po `npm run build` uruchom skrypt w obrazie `sail-8.5/app` na sieci `household-treasurer_sail`, ze zmiennymi bazy jak w jobie (`DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_DATABASE=e2e_ci_e2e_in_ci`, `DB_USERNAME=sail`, `DB_PASSWORD=password`, `APP_URL=http://localhost:8000`) przez `-e`, bez `-p`. W tym samym kontenerze (`--entrypoint bash … -c`) uruchom skrypt w tle, poczekaj na `curl -fsS http://127.0.0.1:8000/login` (200) i pobierz jeden plik z `public/build/manifest.json` pod `/build/…` (200). Osobno sprawdź, że przy istniejącym `public/hot` skrypt kończy się kodem ≠ 0 i komunikatem (plik usuń po sprawdzeniu). Nie sprawdza to Chromium ani usługi `pgsql` runnera, które zostają krokiem 5.4.

Każda porażka w 20 rundach oznacza poszukanie przyczyny. Jeśli da się ją usunąć w specu bez maskowania, poprawka i ponowne 20 rund. Jeśli nie, spec traci `@gate`, zostaje lokalny, a §5 i issue to odnotowują (zasada etapu: bramka tylko ze stabilnych speców). Bazę usuń po fazie.

### Success Criteria:

#### Automated Verification:

- `npx playwright test --list --project=gate` pokazuje `setup` i dokładnie pięć testów: `seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected`, `import-approve`
- `npx playwright test --list` pokazuje każdy spec z `tests/e2e` dokładnie raz
- Próba w trybie CI na świeżej bazie: `setup` zielony, potem `--project=gate --no-deps --repeat-each=20` daje 100 przebiegów bez porażki
- Wymuszona porażka w trybie CI (np. `forbidOnly` przez tymczasowe `test.only` albo celowe zepsucie z fazy 2) zostawia trace w `test-results/` i raport w `playwright-report/`
- `npx vp check` i `npm run types:check` przechodzą
- Smoke `scripts/e2e-server-ci.sh` w obrazie Sail: `/login` i plik z manifestu odpowiadają 200, a przy `public/hot` skrypt kończy się błędem

---

## Faza 5: Job `e2e` w CI i dokumenty

### Overview

Workflow dostaje job `e2e` złożony z kroków dowiedzionych w fazie 4. Dokumenty opisują stan po zmianie. Przebieg na GitHubie sprawdza użytkownik po pushu `development`.

### Changes Required:

#### 1. Job `e2e`

**File**: `.github/workflows/tests.yml`

**Intent**: Uruchamiać bramkę E2E na każdym pushu i PR, równolegle do `ci`, na własnej bazie i z artefaktem przy porażce.

**Contract**: Nowy job `e2e`:
- `runs-on: ubuntu-24.04`, `timeout-minutes: 20`;
- usługa `pgsql` jak w `ci` (`:20-34`);
- `env`: `DB_*` jak w `ci` (`:36-42`), `APP_URL: http://localhost:8000`, `E2E_USERNAME: e2e@example.test`, `E2E_PASSWORD: password`, z komentarzem, że to dane jednorazowej bazy joba (decyzja 1).

Kroki:
1. checkout (`persist-credentials: false`, bez `fetch-depth: 0`);
2. setup-php 8.5 z tymi samymi rozszerzeniami;
3. cache Composera;
4. setup-node z `.nvmrc` i cache npm;
5. `composer install`;
6. `.env` z `.env.example` i `key:generate`;
7. `php artisan migrate --force`;
8. `php artisan demo:seed-household e2e@example.test`;
9. `npm ci`;
10. `npm run build`;
11. `npx playwright install --with-deps chromium`;
12. `npm run e2e:gate`;
13. przy `if: failure()` upload `playwright-report/` i `test-results/` (retencja 14 dni).

Każda akcja przypięta pełnym SHA z komentarzem wersji, jak istniejące (`:46`, `:53`, `:65`, `:72`); dotyczy to także `actions/upload-artifact`. Job `ci` bez zmian. Wyzwalacze, `permissions` i `concurrency` wspólne.

#### 2. Stos testów

**File**: `context/foundation/test-stack.md`

**Intent**: Dopisać fakty CI: projekt `gate` i `npm run e2e:gate`, serwer `scripts/e2e-server-ci.sh` na runnerze, konto `e2e@example.test` z `demo:seed-household` na świeżej bazie, `retries: 0`, `retain-on-failure`, artefakt. Opis `seed` przy #7 (`:16`) odpowiada odświeżonemu ryzyku (wymóg `test-plan.md:90`).

**Contract**: Sekcja `## E2E` z komentarzem „Written by /10x-e2e-setup” zostaje, a ponowne `/10x-e2e-setup` może ją nadpisać. W niej zmieniają się tylko opis `seed` przy #7 (wymóg `test-plan.md:90`) i `updated:`. Fakty CI trafiają do osobnej sekcji `## E2E w CI` pod nią, poza blokiem `/10x-e2e-setup`.

#### 3. Plan testów

**File**: `context/foundation/test-plan.md`

**Intent**: Plan opisuje bramkę taką, jaka jest. §4 zmienia wiersz e2e: CI z projektem `gate`, lokalnie bez zmian. §5 dostaje nazwę `import-approve` i zdejmuje warunek „dziś … biorą go z `new Date().getFullYear()`”. §6.6 dostaje 2–3 linie o tym, czego etap nauczył (Luhn w stampie, rok z menu, logowanie raz przy `--repeat-each`).

**Contract**: §3 Status zostawiony orkiestratorowi. `Last updated` zmienione.

#### 4. Sprawdzenie workflow

**File**: brak zmian w repo

**Intent**: Wyłapać błędy składni i wyrażeń workflow przed pushem.

**Contract**: `docker run --rm -v "$PWD":/repo -w /repo rhysd/actionlint:<wersja> -color .github/workflows/tests.yml`. Jednorazowe narzędzie z obrazu, nie zależność projektu.

### Success Criteria:

#### Automated Verification:

- `actionlint` na `.github/workflows/tests.yml` bez uwag
- Każde `uses:` w `tests.yml` jest przypięte 40-znakowym SHA
- Pełna bramka na `testing_e2e_in_ci`: worktree command z `AGENTS.md` z `--entrypoint composer sail-8.5/app ci:check`, oraz `vendor/bin/sloppy ci` (w obrazie Sail)

#### Manual Verification:

- Po pushu `development` przez użytkownika: na GitHubie job `e2e` jest zielony obok `ci`, a log pokazuje `setup` i pięć bramek
- Tymczasowa gałąź z celowym zepsuciem z fazy 2 i draft PR do `development` (workflow startuje na pushu tylko do `main`/`development`, więc gałąź potrzebuje PR, by wyzwolić `pull_request`) czerwieni job `e2e` i zostawia artefakt z raportem i trace; push i PR robi użytkownik po pushu `development`, a po sprawdzeniu PR jest zamknięty i gałąź usunięta
- Czas joba `e2e` mieści się w ok. 5 min i nie wydłuża czasu do wyniku ponad czas joba `ci` o więcej niż 2 min

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów jednostkowych. Pomocnik roku jest kodem testowym i dowodzą go specy, które go używają.

### Integration Tests:

- `SeedDemoHouseholdCommandTest`: seed 1 i 5 stycznia 2027 daje daty w [2027-01-01, dziś] dla operacji, przelewów własnych i ruchów kapitału. Na PostgreSQL.

### E2E (bramki):

- Pięć speców `@gate`. Każdy sprawdza skutek na stronie, brak `pageerror` i rok z menu, i każdy czerwienieje przy celowym zepsuciu.
- Stabilność: 20 rund bramek na świeżej bazie w trybie CI.

### Manual Testing Steps:

1. Po pushu `development`: zakładka Actions, przebieg `tests`, oba joby zielone.
2. Gałąź z celowym zepsuciem i draft PR do `development` (sam push gałęzi nie uruchamia workflow): job `e2e` czerwony, adnotacja reportera `github` przy specu, artefakt z `playwright-report/`. Potem zamknij PR i usuń gałąź.
3. Otwarcie trace z artefaktu (`npx playwright show-trace`) pokazuje krok porażki.

## Performance Considerations

Job `e2e` biegnie równolegle do `ci`, więc wynik pojawia się po czasie dłuższego z nich. Szacunek dla `e2e`: setup ok. 1,5 min, instalacja Chromium 30–60 s, bramki ok. 1 min. Repo jest publiczne, więc minuty runnera nie kosztują.

## Migration Notes

Brak migracji danych. Zmiana seedera dotyczy tylko nowych seedów i nie rusza istniejących lokalnych baz E2E.

## References

- Roadmap: `context/foundation/roadmap.md` S-44 (`:184-195` w treści elementu), MS-07
- Plan testów: `context/foundation/test-plan.md` §2 #7 (`:53`, `:69`), §3 etap 2 (`:82`, `:90`), §5 (`:130`), §6.3 (`:185-192`)
- Stos testów: `context/foundation/test-stack.md`
- Health check: `context/foundation/health-check.md:217-224` („E2E poza CI”)
- Odświeżenie planu testów (wybór bramek, odrzucone `failOnFlakyTests`): `context/archive/2026-11-19-test-plan-refresh/`
- Flake Luhna: `context/archive/2026-11-10-category-rhythm/follow-ups/review-fixes.md:6`
- `workers: 1`: `context/archive/2026-11-06-sidebar-action/reviews/impl-review.md:112-113`
- Wzorzec speca: `tests/e2e/seed.spec.ts`
- Środowisko równoległe: `context/foundation/agents/parallel-changes.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Seeder bez dat po dziś

#### Automated

- [x] 1.1 Nowy test seedera jest czerwony przed poprawką (`2027-01-15` > dziś) na PostgreSQL — 8fead57
- [x] 1.2 Po poprawce `SeedDemoHouseholdCommandTest.php` przechodzi na PostgreSQL — 8fead57
- [x] 1.3 `DatabaseSeederTest.php` i `SeedPreviousYearCommandTest.php` przechodzą na PostgreSQL — 8fead57
- [x] 1.4 Pint na zmienionych plikach PHP — 8fead57

### Phase 2: Specy-bramki zgodne z §6.3

#### Automated

- [x] 2.1 Każdy z czterech speców przechodzi lokalnie na bazie `e2e_e2e_in_ci` — 4785356
- [x] 2.2 Celowe zepsucie przepływu czerwieni każdy z czterech speców, a po cofnięciu spec jest zielony — 4785356
- [x] 2.3 `throw` w `onMounted` strony przepływu czerwieni spec przez asercję `pageerror` — 4785356
- [x] 2.4 Brak `new Date().getFullYear()` w czterech specach-bramkach — 4785356
- [x] 2.5 `npx vp check` przechodzi dla zmienionych plików TS — 4785356

### Phase 3: Spec zatwierdzenia Importu

#### Automated

- [x] 3.1 `import-approve.spec.ts` przechodzi lokalnie — ac91b9e
- [x] 3.2 Celowe zepsucie zatwierdzenia czerwieni spec, a po cofnięciu jest zielony — ac91b9e
- [x] 3.3 Po przebiegu w bazie nie ma operacji ze stampem przebiegu — ac91b9e
- [x] 3.4 `npx vp check` przechodzi dla nowego speca — ac91b9e

#### Manual

- [x] 3.5 Trace albo raport HTML pokazuje podgląd, toast i oba wiersze po przeładowaniu — sprawdzone w trace (tekst toastu i oba wiersze w snapshotach po przeładowaniu)

### Phase 4: Tryb CI Playwrighta

#### Automated

- [x] 4.1 `--list --project=gate` pokazuje `setup` i dokładnie pięć bramek — 07c0d5f
- [x] 4.2 `--list` pokazuje każdy spec dokładnie raz — 07c0d5f
- [x] 4.3 Próba w trybie CI: `setup` zielony, potem 20 rund bramek (100 przebiegów) bez porażki — 07c0d5f
- [x] 4.4 Wymuszona porażka w trybie CI zostawia trace i raport — 07c0d5f
- [x] 4.5 `npx vp check` i `npm run types:check` przechodzą — 07c0d5f
- [x] 4.6 Smoke `e2e-server-ci.sh` w obrazie Sail: `/login` i asset 200, `public/hot` daje błąd — 07c0d5f

### Phase 5: Job `e2e` w CI i dokumenty

#### Automated

- [x] 5.1 `actionlint` na `tests.yml` bez uwag — 4b35ab3
- [x] 5.2 Każde `uses:` w `tests.yml` przypięte pełnym SHA — 4b35ab3
- [x] 5.3 Pełna bramka `ci:check` na `testing_e2e_in_ci` i Sloppy — 4b35ab3

#### Manual

- [ ] 5.4 Po pushu `development` job `e2e` zielony na GitHubie obok `ci`
- [ ] 5.5 Gałąź z celowym zepsuciem (draft PR do `development`) czerwieni job `e2e` i zostawia artefakt
- [ ] 5.6 Czas joba `e2e` ok. 5 min, nie wydłuża wyniku o więcej niż 2 min ponad `ci`
