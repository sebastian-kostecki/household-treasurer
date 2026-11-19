---
type: observability-audit
date: 2026-11-19 06:02
mode: audit
commit: e50f8f6
branch: development
dirty_tree: true   # context/foundation/development-directions.md, roadmap.md (dokumenty, nie kod)
areas: [import, operations-dashboard, month-plan-year-turn, backups-scheduler-deploy]
area_source: user   # propozycja z roadmapy M-5, zatwierdzona przez użytkownika
runtime_proof: not-run
error_tracker: none (S-45 production-error-tracking zablokowany; Laravel report() → stack/single → storage/logs/laravel.log)
previous_report: null
findings: { critical: 6, high: 12, medium: 19, low: 16 }
---

# Audyt obserwowalności — import, operacje i Dashboard, plan miesiąca i przełom roku, kopie i harmonogram (2026-11-19)

## 1. TL;DR

- **Niezmienniki wyrażone wyjątkami, których Laravel nie raportuje.** `ValidationException`, `ModelNotFoundException`, `abort(403/404)` i `AuthorizationException` służą też jako asercje „to nie może się zdarzyć po walidacji”. Te błędy nie trafią ani do `laravel.log`, ani do przyszłego trackera z S-45 (`Handler.php:170-183`).
- **Szeroki `catch` zamieniany na komunikat bez `report()`.** Tak działają `ApproveMonthPlan.php:89`, `ChangeMonthPlan.php:86/184`, `CsvImportController.php:75` i `PreviewCsvImportRequest.php:103`. Repo ma własną konwencję „report, potem komunikat” (`CreateCategory.php:75-76`), ale te miejsca jej nie stosują.
- **`null`, `false` i pominięcie jako normalny wynik.** Parser wyciągu zwraca `null` na nieznany format, a wiersz po cichu znika (`CsvImportController.php:82-85`). Dysk `local` ma `throw=false`, a `BackupUserData` nie sprawdza `put()`. Sesja podglądu zwraca `null` w trzech różnych przypadkach.
- **Sygnały operacyjne żyją wewnątrz tego, co monitorują.** Harmonogram nie działa w trybie konserwacji. Mail o kopii wychodzi tylko przy błędzie i z tego samego `schedule:work`. `/up` nie dotyka bazy.
- **Klient nie ma kanału błędów.** `app.ts` nie ma żadnego globalnego handlera. Błąd renderu Vue, błąd sieci w dialogu i błędy 2FA lub passkeys kończą się w konsoli przeglądarki.
- **Najważniejsza konsekwencja przed 1 stycznia:** po północy UTC kliknięcie „Zatwierdź” dla grudnia czyści wpisane kwoty i pokazuje „Ten miesiąc nie ma planu” bez żadnego komunikatu. W logu nie zostaje nic (MPL-1). Import wyciągu z przełomu odrzuca cały zapis jednym zdaniem bez numeru wiersza i też bez logu (IMP-2).

## 2. Model przechwytywania

**Środowisko.** Jeden kontener (`docker-compose.prod.yml`, `docker/8.5/supervisord.prod.conf`):
- nginx (`error_log /dev/stderr warn`, `access_log off`, `nginx.conf:7,18`, bo log dostępu pisze Traefik);
- PHP-FPM (`error_log → stderr`, `request_terminate_timeout=120s`, `max_execution_time=60`, `php-fpm.conf:3,23,32`);
- supervisor z `queue:work --tries=3` (→ `storage/logs/queue.log`) i `schedule:work` (→ `storage/logs/scheduler.log`).

Postgres działa w sąsiednim kontenerze, przed całością stoją Traefik i Cloudflare. W produkcji `APP_ENV=production`, `APP_DEBUG=false` (`.env.prod.example`).

**Tracker błędów:** brak w repo (żaden SDK w `composer.json` ani `package.json`). Roadmapa: S-45 `production-error-tracking`, status `blocked`. „Granicą przechwytywania” w tym audycie jest więc `report()` handlera Laravela. Co do niej dotrze z obiektem wyjątku, trafi też do przyszłego trackera. Co zostanie złapane lub przerobione wcześniej, zostanie niewidoczne także po S-45.

**Handler:** `bootstrap/app.php:57-61` ustawia tylko `shouldRenderJsonWhen`. Nie ma `report`, `dontReport`, `context` ani `buildContextUsing`. Domyślny `internalDontReport` (`vendor/.../Foundation/Exceptions/Handler.php:170-183`) pomija HttpException (każde `abort()`, 419, 429), ModelNotFound, Authorization, Authentication i Validation. Kontekst wpisu to tylko `userId` (`Handler.php:672-680`): bez trasy, URL, id żądania i wydania. Formatter loguje stos i łańcuch `previous`.

**Logowanie:** `stack` → `LOG_STACK=single` → `storage/logs/laravel.log` bez rotacji, `LOG_LEVEL=error` (`.env.prod.example:18-21`), deprecations idą do `null`. Jedyne wywołanie poniżej `error` to `Log::info` w `MergeCategories.php:64`, które w produkcji ginie. Gdy samego logu nie da się zapisać, `HandleExceptions.php:194-198` połyka błąd zapisu i nic nie zostaje.

**Praca w tle:** w `app/` nic nie trafia do kolejki (brak `ShouldQueue`, `dispatch` i kolejkowanej poczty), więc worker kolejki działa na pusto. Harmonogram to wyłącznie spatie `backup:run` o 03:00, `backup:clean` o 03:30 i `backup:monitor` o 04:00 (`bootstrap/app.php:36-56`, `environments(['production'])`, bez `evenInMaintenanceMode`, `onFailure` i `appendOutputTo`). Wynik zadań trafia do `/dev/null` (`Event.php:41`). Jedyny aktywny kanał powiadomień to mail spatie przy błędzie kopii (`config/backup.php:86-98`).

**Klient:** `resources/js/app.ts:11-38` woła `createInertiaApp` bez `app.config.errorHandler`, `window.onerror`, `unhandledrejection` i globalnego `router.on('httpException'|'networkError')`. Odpowiedź 500/404/403/419 na wizytę Inertia to domyślna angielska strona Laravela w modalu-iframe (`@inertiajs/core` 3.11 `index.js:2954-2977`; brak `resources/views/errors/`). Błąd sieci jest odrzucany ponownie jako nieobsłużony promise (`index.js:3427-3432`). Błąd renderu Vue w buildzie produkcyjnym to tylko `console.error`.

**Tożsamość wdrożenia:** w kodzie nie ma tagu wydania ani wersji. `scripts/deploy.sh` kończy się poleceniem „Check /up”.

**Założenia (poza repo, nie ustalenia):**
- Platforma zbiera stdout i stderr kontenera, a pliki `storage/logs/*.log` są dostępne przez bind mount. Niepotwierdzone.
- `laravel.log` czyta się ręcznie, a z logów nie powstają alerty. Potwierdzone roadmapą („log plikowy, brak powiadomień aplikacji”).
- SMTP i `BACKUP_NOTIFICATION_MAIL` w `.env.prod` są ustawione i dostarczają pocztę. Niepotwierdzone.
- Traefik zapisuje log dostępu ze statusami. Niepotwierdzone; to komentarz w `nginx.conf:17`.
- Nic spoza repo (np. uptime monitor) nie pinguje aplikacji z zewnątrz. Niepotwierdzone. Jeśli taki monitor istnieje, BKP-1 i BKP-4 tracą część wagi.

## 3. Co dociera do `laravel.log` (a później do trackera S-45)

Wersja **statyczna** (bez sond środowiska uruchomieniowego, zob. §8).

| Postać awarii | Odpowiedź | Logi platformy | `laravel.log` / tracker | Werdykt |
|---|---|---|---|---|
| Wyjątek nieobsłużony w kontrolerze lub akcji | 500, angielski modal Inertia | — | wpis ze stosem, tylko `userId` | słabo (brak kontekstu) |
| `ModelNotFound` / `abort(403/404)` jako strażnik niezmiennika w akcji | 404/403 w modalu | — | nic | pominięte |
| `ValidationException` zbudowany z błędu wewnętrznego lub zegara | 302 + błąd pola (czasem niewyświetlony) | — | nic | pominięte |
| `catch (UniqueConstraintViolation)` bez `report()` | 302 + komunikat „już zatwierdzony” | — | nic | pominięte |
| Wiersz CSV z nieznanym formatem daty lub kwoty | 200, mniej wierszy | — | nic | pominięte |
| Import 5000 wierszy przekracza 120 s | 502/504/524 | linia FPM na stderr | nic | pominięte |
| Niezapisywalny `laravel.log` (właściciel root, pełny dysk) | 500 | — | nic (połknięte w `HandleExceptions.php:196`) | pominięte |
| Błąd renderu Vue / błąd sieci / `catch {}` w 2FA | pusty fragment / dialog bez reakcji | konsola przeglądarki | nic | pominięte |
| `pg_dump` / dysk / zip w `backup:run` | — | linia w `scheduler.log` | wpis + mail `BackupHasFailed` | dobrze |
| Aplikacja w trybie konserwacji po nieudanym deployu | 503 | — | nic; harmonogram stoi, maila brak | pominięte |
| `schedule:work` martwy (FATAL w supervisorze) | — | log supervisora | nic, maila brak | pominięte |
| Snapshot użytkownika nie zapisuje się | wyjście 0, „Backed up …” | — | nic | pominięte |

## 4. Systemowe przyczyny źródłowe

1. **Niezmienniki jako wyjątki, których framework nie raportuje.** Akcje rzucają `ModelNotFoundException`, `abort(403/404)` albo `ValidationException` w miejscach, gdzie walidacja już powinna wykluczyć ten stan:
   - `ManageFinancialOperation.php:143,194,229-265`;
   - `JoinBudgetAtFirstUse.php:59-88,206-254`;
   - `ImportFinancialOperations.php:150-153,171-174,209-212,236-239,274-277`;
   - `ApproveMonthPlan.php:41-63`;
   - `Expense/IncomeController.php:73-75`;
   - `DashboardController.php:167`.

   Wszystkie wpadają do `internalDontReport` (`Handler.php:170-183`). Lokalnie to rozsądne, bo użytkownik dostaje komunikat zamiast 500. Skutek: najcenniejsza klasa błędów, czyli walidator i akcja niezgodne co do stanu, jest niewidoczna dziś i pozostanie niewidoczna po S-45. Wyjaśnia: IMP-2, IMP-9, OPS-3, MPL-1, MPL-4, MPL-5, MPL-7, PLB-3.
2. **Złapanie po szerokiej klasie i konwersja na komunikat bez `report()`.** Dotyczy `catch (UniqueConstraintViolationException)` w `ApproveMonthPlan.php:89` (bez nawet związania zmiennej) i `ChangeMonthPlan.php:86/184`. Także `catch (InvalidArgumentException)` w `CsvImportController.php:75` i `PreviewCsvImportRequest.php:103`: ten wyjątek rzucają też PhpSpreadsheet, Carbon i błędy programistyczne. `ValidationException` nie przenosi `previous`, więc bez `report()` przyczyna znika. Wyjaśnia: MPL-2, MPL-3, IMP-8.
3. **`null`, `false` albo pominięcie jako zwykły wynik.**
   - `CsvImportParser::parseDate/parseMoney` (`:124-141`, `:251-253`) → `isOperationShapedRow` (`CsvImportController.php:472-479`) wyrzuca wiersz bez licznika.
   - Dysk `local` ma `'throw' => false` (`config/filesystems.php:37-38`), a `BackupUserData.php:34-41` ignoruje wynik `put()`.
   - `CsvImportSession::get` (`:348-375`) zwraca `null` dla „wygasło”, „cudze” i „zły kształt”.
   - `AccountReconciliationReading.php:91` ma `?? 0`.

   Wyjaśnia: IMP-1, IMP-5, IMP-12, BKP-3, OPS-6.
4. **Sygnały operacyjne zależą od procesu, który mają pilnować.**
   - `Event::isDue` zwraca false w trybie konserwacji (`vendor/.../Scheduling/Event.php:285-289`), a deploy celowo zostawia tryb konserwacji przy błędzie (`production-deploy.sh:108-115`).
   - Kopia i jej monitor biegną w tym samym `schedule:work` (`bootstrap/app.php:36-56`), a mail wychodzi tylko przy błędzie (`config/backup.php:90-92`).
   - Wynik zadań idzie do `/dev/null`.
   - `/up` nie sprawdza bazy (`bootstrap/app.php:16`, brak listenera `DiagnosingHealth`).

   Skutek: cisza wygląda tak samo jak zdrowie. Wyjaśnia: BKP-1, BKP-2, BKP-4, BKP-5, BKP-6, BKP-7.
5. **Klient bez kanału błędów, a UI pokazuje błąd tylko w stanie, z którego go wysłano.**
   - `app.ts:11-38` nie ma żadnego haka.
   - `MonthPlan.vue:666-669` pokazuje `errors.items` tylko przy `state === 'proposal'`, a watch `:139-147` czyści wpisane kwoty przy zmianie stanu.
   - Dialogi operacji renderują stałą listę kluczy błędów.
   - `useTwoFactorAuth.ts:41-92` ma `catch {}`.

   Wyjaśnia: MPL-1, MPL-9, OPS-4, OPS-5, PLB-2, PLB-5, PLB-6.
6. **Jeden plikowy zlew logów z ubogim kontekstem.**
   - `LOG_STACK=single` bez rotacji i bez stderr.
   - Awaria zapisu logu jest połykana (`HandleExceptions.php:196`).
   - `Handler::context()` daje tylko `userId`, a w `app/` nie ma `Context::add`.
   - `LOG_LEVEL=error` odcina jedyny `Log::info` z danymi do odzyskania (`MergeCategories.php:64`).

   To kod i konfiguracja w repo (`config/logging.php:55-66`, `.env.prod.example:18-21`), nie ustawienie panelu. Wyjaśnia: PLB-1, PLB-4, IMP-10, BKP-8, BKP-10, BKP-13.

## 5. Ustalenia według obszarów

Każde ustalenie ma priorytet: krytyczne, wysokie, średnie albo niskie. Znacznik ✔ oznacza ustalenie sprawdzone ręcznie w kodzie podczas syntezy (§8); pozostałe pochodzą od audytorów, którzy czytali cytowane wiersze.

### Import wyciągu (podgląd → zapis)

**IMP-1 ✔ — wiersze, których parser nie odczyta, znikają bez śladu.** Krytyczne, kategoria `missing-throw`.
- Gdzie: `CsvImportController.php:82-85,472-479`; `CsvImportParser.php:124-141,178-183,251-253`; `BnpParibasXlsxParser.php:168-197`.
- Na produkcji: bank zmienia format daty lub kwoty (`+45,50`, `1.234,56`, `0,00` w nieużywanej kolumnie) i podgląd ma mniej wierszy, bez licznika pominiętych. Gdy odpadnie wszystko, użytkownik czyta „Plik nie zawiera wierszy z datą i kwotą”. W logu nie ma nic.
- Kierunek poprawki: liczyć odrzucone linie z numerem i powodem, pokazać licznik, a przy odrzuceniu większości wierszy wywołać `report()`.

**IMP-2 ✔ — odmowa przy zapisie to `ValidationException(['rows' => $errors[0]])`.** Krytyczne, kategoria `flattened-response`.
- Gdzie: `ImportFinancialOperations.php:150-153,171-174,209-212,236-239,274-277`.
- Na produkcji: wiersz poprawny w podglądzie zostaje odrzucony przy zapisie przez fazę Budżetu, rok arkusza, zarchiwizowane konto lub kategorię. Cały import się wycofuje, a użytkownik dostaje jedno zdanie bez numeru wiersza i daty. W logu nie ma nic. Przez to luka S-42 będzie niewidoczna.
- Kierunek poprawki: klucz `rows.{index}`, data w komunikacie i zgłoszenie „podgląd OK / zapis odrzucony” z kontem, indeksem i regułą.

**IMP-3 — CSV spoza UTF-8 (np. CP1250 przy formacie „Inny”) psuje podgląd błędem 500 przy każdej wizycie.** Wysokie, kategoria `missing-throw`.
- Gdzie: `DefaultPaymentInstrumentNumberOmitter.php:305` (`preg_split … ?: []`); brak kontroli kodowania; `JsonResponse.php:90-91`.
- Na produkcji: błąd 500 „Malformed UTF-8” powtarza się, aż użytkownik wgra nowy plik. Wpis w logu ma stos vendora, ale bez pliku, formatu i etapu.
- Kierunek poprawki: kontrola kodowania przy parsowaniu i błąd pliku „złe kodowanie”.

**IMP-4 — duży zapis importu przerywa timeout FPM (120 s) lub Cloudflare (~100 s).** Wysokie, kategoria `coverage-gap`.
- Gdzie: zapis jednym żądaniem do `MAX_ROWS=5000`; `php-fpm.conf:23`.
- Na produkcji: strona 502/504/524. Przy 524 PHP może dokończyć zapis, a użytkownik ponawia (zob. IMP-7). W `laravel.log` nie ma nic, zostaje tylko linia FPM na stderr.
- Kierunek poprawki: zapis partiami lub w zadaniu kolejki oraz wpis start/koniec z liczbą wierszy i czasem.

**IMP-5 — wygasły lub uszkodzony podgląd po cichu wraca do kroku 1.** Wysokie, kategoria `flattened-response`.
- Gdzie: `CsvImportController.php:123-127,156-160`; `CsvImportSession::get` `:348-375`.
- Na produkcji: po długim kategoryzowaniu „Importuj” ląduje na kroku 1, a wybory przepadają. Wygaśnięcie wygląda tak samo jak błąd kształtu po deployu. W logu nie ma nic.
- Kierunek poprawki: komunikat flash o wygaśnięciu, wpis z rozróżnieniem trzech przypadków i `report()` dla złego kształtu.

**IMP-6 — zapis nie ma tożsamości podglądu.** Średnie, kategoria `missing-throw`.
- Gdzie: `CsvImportController.php:111,162-176`; `StoreCsvImportRequest.php:206-219`.
- Na produkcji: podgląd z drugiej karty nadpisuje sesję. Wybory z pierwszej karty trafiają wtedy po indeksie do cudzych wierszy albo kończą się komunikatem „One or more selected rows are invalid”. W logu nie ma nic.
- Kierunek poprawki: UUID podglądu w sesji i w formularzu oraz zalogowana niezgodność.

**IMP-7 — podwójne wysłanie może zaimportować wyciąg dwa razy.** Średnie, kategoria `swallowed`.
- Gdzie: `CsvImportController.php:321-346`; indeksy digestów nie są unikalne (`create_financial_operations_table.php:36`).
- Na produkcji: duplikaty operacji i cisza w logu.
- Kierunek poprawki: atomowe pobranie lub blokada podglądu i wpis każdego zapisu.

**IMP-8 — `catch (InvalidArgumentException)` łapie też błędy bibliotek i kodu.** Średnie, kategoria `identity-lost`.
- Gdzie: `CsvImportController.php:75-79`; `PreviewCsvImportRequest.php:103-105`.
- Na produkcji: angielski tekst wyjątku pojawia się przy polu pliku, a pierwotny wyjątek nie jest zgłoszony.
- Kierunek poprawki: własny `ImportFileRejected`; wszystko inne przepuścić.

**IMP-9 — „niemożliwe” stany w akcjach par są pokazywane jako zmiana danych w międzyczasie.** Średnie, kategoria `flattened-response`.
- Gdzie: `AttachImportPairOwnTransfer.php:223-297`; `ConvertImportPairFinancialOperation.php:51-77`.
- Na produkcji: „kandydat już nie pasuje” i wycofanie. Wygląda to jak współbieżna zmiana, a w logu nie ma nic.
- Kierunek poprawki: zgłaszany `LogicException` dla stanów niemożliwych.

**IMP-10 — błąd bazy w trakcie zapisu jest logowany bez kontekstu importu.** Średnie, kategoria `missing-context`.
- Gdzie: `CsvImportController::store`; `ImportFinancialOperations.php:78`.
- Na produkcji: wpis `QueryException` ma tylko `userId`, bez konta, formatu, etapu i indeksu wiersza.
- Kierunek poprawki: `Context::add('import', …)` w `preview()` i `store()`, aktualizowany w pętli.

**IMP-11 — uszkodzony lub zaszyfrowany XLSX daje 500 z wpisem `error`.** Niskie, kategoria `noise`.
- Gdzie: `BnpParibasXlsxParser.php:37`; `PreviewCsvImportRequest.php:99`.
- Na produkcji: błąd złego pliku wygląda jak błąd serwera, a plik jest parsowany dwa razy.
- Kierunek poprawki: złapać wyjątek czytnika jako błąd pliku.

**IMP-12 — wiersze bez surowego opisu nie dostają tożsamości importu.** Niskie, kategoria `missing-throw`.
- Gdzie: `ImportIdentityFactory.php:26-28,90-92`; `ImportDuplicateDetector.php:55-60`.
- Na produkcji: ponowny import tworzy duplikat bez ostrzeżenia.
- Kierunek poprawki: licznik takich wierszy w podglądzie.

### Operacje wprowadzane ręcznie i Dashboard

**OPS-1 — stany kont i Execution nigdy nie są porównywane z księgą.** Krytyczne, kategoria `missing-throw`.
- Gdzie: `SpendableLiquidity.php:15`; `TotalFunds.php:15-16`; `DashboardController.php:195,250-251` wobec `AccountBalanceOnDate.php:17-29` i `MonthCategoryActuals.php:36-42`.
- Na produkcji: przy dryfie licznika (np. OPS-2 albo błąd w revert) Dashboard pokazuje wiarygodną, ale złą kwotę. Uzgodnienie salda może przy tym mówić „zgadza się”. W logu nie ma nic, a `app:control-numbers` nie jest w harmonogramie.
- Kierunek poprawki: zaplanowana kontrola licznik ↔ księga, która zgłasza nazwany wyjątek z id i obiema wartościami.

**OPS-2 ✔ — wyścig utraconej aktualizacji salda.** Wysokie, kategoria `missing-throw`.
- Gdzie: `ManageOwnTransfer.php:96-101`; `ManageCapitalMovement.php:133-145` (odczyt, a potem zapis `current + delta` bez `lockForUpdate`). `ManageFinancialOperation.php:251-287` robi to samo poprawnie: blokada i `increment`.
- Na produkcji: przelew własny równoległy z wydatkiem albo importem na tym samym koncie po cichu nadpisuje saldo. Zostaje trwały dryf (OPS-1) i żadnego śladu.
- Kierunek poprawki: `lockForUpdate` i SQL `increment`, jak w `ManageFinancialOperation`. To także poprawka poprawności, nie tylko obserwowalności.

**OPS-3 — rozjazd typu operacji jest wyrażony jako brak uprawnień.** Średnie, kategoria `flattened-response`.
- Gdzie: `ExpenseController.php:73-75`; `IncomeController.php:73-75`; `UpdateExpenseRequest.php:29-31`; `UpdateIncomeRequest.php:29-30`.
- Na produkcji: modal „403 This action is unauthorized”. Błędu klienta albo nieaktualnej listy nie da się odróżnić od naruszenia własności. W logu nie ma nic.
- Kierunek poprawki: 403 tylko dla własności; rozjazd typu jako 409/422 ze zgłoszeniem.

**OPS-4 — dialogi wprowadzania pokazują tylko stałą listę kluczy błędów.** Średnie, kategoria `swallowed`.
- Gdzie: `ExpenseFormDialog.vue:523-656`; `IncomeFormDialog.vue:413-546`; `OwnTransferFormDialog.vue:243-320`; `CapitalMovementFormDialog.vue:283-382`; `OperationCategoryDialog.vue:204`.
- Na produkcji: błąd 422 pod innym kluczem (np. `convert_to_capital_movement`, `interest_*`) zostawia dialog otwarty bez komunikatu. Nikt tego nie widzi.
- Kierunek poprawki: lista zapasowa nieznanych kluczy, jak w `ConfirmActionDialog.vue:69-73`.

**OPS-5 — błędy klienta w podglądzie uzgodnienia salda trafiają tylko do konsoli.** Średnie, kategoria `logged-not-captured`.
- Gdzie: `useBalanceReconciliationPreview.ts:46-79`.
- Na produkcji: użytkownik widzi „nie udało się”, a przyczyna (zły kształt JSON, sieć) zostaje w przeglądarce.
- Kierunek poprawki: podłączyć do globalnego kanału klienta (PLB-2).

**OPS-6 — saldo na dzień w podglądzie spada do `?? 0`.** Niskie, kategoria `missing-throw`.
- Gdzie: `AccountReconciliationReading.php:91`.
- Na produkcji: przy niezgodności walidacji z odczytem pojawia się saldo 0,00 zł i wiarygodna różnica.
- Kierunek poprawki: rzucić wyjątek zamiast zera.

**OPS-7 — odrzucony filtr listy Operacji odbija bez komunikatu.** Niskie, kategoria `flattened-response`.
- Gdzie: `IndexOperationsRequest.php:456-480`; `Operations.vue` i `OperationsFilters.vue` nie czytają `errors`.
- Na produkcji: stary link po scaleniu kategorii „nic nie robi”. W logu nie ma nic.
- Kierunek poprawki: odrzucać złe klucze jak `RedirectsToOperationsList` albo pokazać błąd.

### Plan miesiąca, start Budżetu i przełom roku

**MPL-1 ✔ — 1 stycznia „Zatwierdź” dla grudnia czyści dane i nic nie mówi.** Krytyczne, kategorie `swallowed` i `flattened-response`.
- Gdzie: `ApproveMonthPlan.php:41-43`; `MonthPlanProposal.php:158,176`; `MonthPlan.vue:139-147,666-669`.
- Na produkcji: po północy UTC odczyt grudnia ma stan `none`, a akcja rzuca `ValidationException` „A past month cannot be approved”. Strona renderuje się w stanie `none`, gdzie `InputError` się nie wyświetla, a watch zeruje wpisane kwoty. W logu nie ma nic, nie zostaje też „dziś” serwera ani data klienta.
- Kierunek poprawki: pokazywać `errors.items` w każdym stanie albo przez toast oraz zgłosić odmowę „miesiąc minął” z budżetem, miesiącem, dziś serwera i datą klienta (`Log::warning` nie wystarczy przy `LOG_LEVEL=error`).

**MPL-2 ✔ — każde naruszenie unikalności przy zatwierdzaniu to „już zatwierdzony”, bez zgłoszenia.** Wysokie, kategoria `swallowed`.
- Gdzie: `ApproveMonthPlan.php:89-91` (`catch` bez zmiennej).
- Na produkcji: błąd w `month_plan_items` albo w `JoinBudgetAtFirstUse` wygląda jak wyścig. Plan zostaje propozycją z komunikatem „już zatwierdzony”, a w logu nie ma nic.
- Kierunek poprawki: związać wyjątek, wywołać `report($e)` i rozróżnić constraint.

**MPL-3 — w zmianie planu naruszenie unikalności to zawsze błąd, a staje się „pozycje nieprawidłowe”.** Wysokie, kategoria `swallowed`.
- Gdzie: `ChangeMonthPlan.php:86,184-186` (wiersz zablokowany w `:142-146`).
- Na produkcji: winę dostaje użytkownik, a w logu nie ma nic.
- Kierunek poprawki: `report()` i domenowy wyjątek.

**MPL-4 — jeden komunikat obejmuje pięć różnych odmów i nie ma kontekstu.** Wysokie, kategoria `missing-context`.
- Gdzie: `ApproveMonthPlan.php:52,63`; `ChangeMonthPlan.php:155,175,185`.
- Na produkcji: propozycja przeliczona przy wysłaniu różni się od wyrenderowanej (północ, kategoria zarchiwizowana na granicy miesiąca), a komunikat jest ten sam co przy sfałszowanym formularzu. Nie wiadomo, która kontrola zadziałała.
- Kierunek poprawki: osobny klucz na każdą przyczynę i zgłoszenie z brakującymi i nadmiarowymi id.

**MPL-5 — odmowa zmiany grudnia po północy UTC nie zostawia śladu.** Średnie, kategoria `coverage-gap`.
- Gdzie: `ChangeMonthPlan.php:136-138`.
- Na produkcji: komunikat się pokazuje, ale nikt nie wie, jak często znane okno S-39…S-43 trafia użytkownika.
- Kierunek poprawki: zgłoszenie z dziś serwera, budżetem i miesiącem.

**MPL-6 — propozycja stycznia po cichu spada do udziału rocznego.** Średnie, kategoria `missing-context`.
- Gdzie: `MonthPlanProposal.php:61-66,219-225,244-258`.
- Na produkcji: liczby stycznia zmieniają się w nocy 1 stycznia. Powodem może być niezatwierdzony grudzień, Budżet 2026 nie w realizacji albo brak dopasowania pozycji. Odczyt nie mówi, które źródło zadziałało.
- Kierunek poprawki: pole `source` w `MonthPlanReading` i wpis, gdy źródło stycznia jest puste przy istniejącym poprzednim budżecie.

**MPL-7 — konflikt fazy i naruszenie własności to oba 403.** Niskie, kategoria `identity-lost`.
- Gdzie: `BudgetPolicy.php:25-44`; `StartBudgetExecution.php:146-148`; `ApproveMonthPlan.php:24-26`; `ChangeMonthPlan.php:128-130`.
- Na produkcji: podwójne kliknięcie „Rozpocznij realizację” wygląda jak niezgodność `user_id`, a w logu nie ma nic.
- Kierunek poprawki: 409 lub błąd walidacji dla konfliktu fazy.

**MPL-8 — rok w sesji spada do domyślnego bez śladu.** Niskie, kategoria `missing-context`.
- Gdzie: `SelectedBudgetYear.php:185-195`.
- Na produkcji: 1 stycznia nie da się odtworzyć, który rok widział użytkownik.
- Kierunek poprawki: `Context::add` z rozstrzygniętym rokiem i jego pochodzeniem.

**MPL-9 — nieudana zmiana roku gubi status i treść błędu.** Niskie, kategoria `identity-lost`.
- Gdzie: `BudgetYearSwitcher.vue:170-172`; `budget-year-selection.ts:17-24`; `BudgetCategoryList.vue:290-301`.
- Na produkcji: ogólny toast `select_year_failed` i domyślny modal, bo zdarzenie nie jest anulowane.
- Kierunek poprawki: przekazać status do toastu, a po S-45 do reportera.

**MPL-10 — błędy wczytania i usunięcia arkusza gubią liczby i przyczynę.** Niskie, kategoria `missing-context`.
- Gdzie: `LoadBudgetSheet.php:228-240`; `LoadBudgetSheetCommand.php:39-42,67-70`; `UnloadBudgetSheetCommand.php:197-200`.
- Na produkcji: operator widzi tylko `getMessage()`, bez łańcucha `previous`.
- Kierunek poprawki: liczby w komunikatach i `previous` przy `-v`.

### Kopie zapasowe, harmonogram, kolejka i deploy

**BKP-1 — alert powstaje tylko przy błędzie, a strażnik biegnie w tym samym procesie co kopia.** Krytyczne, kategorie `coverage-gap` i `config`.
- Gdzie: `config/backup.php:90-92`; `bootstrap/app.php:36-56`; `supervisord.prod.conf:35-41`.
- Na produkcji: martwy `schedule:work` (FATAL po `startretries`), pusty `APP_ENV` albo zepsuty SMTP oznaczają brak kopii i brak maila. Kontener żyje dzięki nginx. Użytkownik dowie się przy próbie odtworzenia.
- Kierunek poprawki: zewnętrzny dead-man's switch pingowany po udanym `backup:run` albo tygodniowy mail `HealthyBackupWasFound`.

**BKP-2 ✔ — tryb konserwacji wyłącza cały harmonogram bez śladu.** Krytyczne, kategoria `coverage-gap`.
- Gdzie: `vendor/.../Scheduling/Event.php:285-289`; `bootstrap/app.php:37-56` (brak `evenInMaintenanceMode()`); `production-deploy.sh:108-115`.
- Na produkcji: nieudany deploy celowo zostaje w trybie konserwacji. Od tej chwili nie ma nocnych kopii, czyszczenia ani maila z monitora. Jedyną kopią jest ta sprzed migracji.
- Kierunek poprawki: `evenInMaintenanceMode()` dla `backup:run` i `backup:monitor`.

**BKP-3 ✔ — snapshot użytkownika, który się nie zapisał, jest raportowany jako sukces.** Wysokie, kategoria `missing-throw`.
- Gdzie: `config/filesystems.php:37-38`; `BackupUserData.php:34-41`.
- Na produkcji: `user:backup` wypisuje „Backed up … to <path>” i kończy się kodem 0. Deploy liczy użytkownika jako zabezpieczonego, a scalanie kategorii idzie bez kopii bezpieczeństwa.
- Kierunek poprawki: sprawdzać wynik `put()` i `makeDirectory()` albo ustawić `'throw' => true`.

**BKP-4 — `/up` zwraca 200, gdy aplikacja nie działa.** Wysokie, kategoria `flattened-response`.
- Gdzie: `bootstrap/app.php:16`; `vendor/.../Foundation/Http/HealthCheck.php`; brak listenera `DiagnosingHealth`.
- Na produkcji: przy wyłączonym pgsql każda strona zwraca 500, a `/up` mówi „up”. Tak samo podczas utknięcia w trybie konserwacji. To właśnie `/up` sprawdza się po deployu (`deploy.sh:74`).
- Kierunek poprawki: listener `DiagnosingHealth` z `select 1` i porażka w trybie konserwacji.

**BKP-5 — błąd spoza `Exception` w `backup:run` omija mail o awarii.** Średnie, kategoria `identity-lost`.
- Gdzie: `vendor/spatie/.../BackupCommand.php:104`; `CleanupCommand.php:57`.
- Na produkcji: `TypeError` albo fatal nie wysyła `BackupHasFailed`. O 04:00 przychodzi tylko „backup too old” bez przyczyny.
- Kierunek poprawki: `->onFailure()` albo `emailOutputOnFailure()` w harmonogramie.

**BKP-6 — wyłączony pgsql o 03:00 oznacza pominiętą kopię bez maila.** Średnie, kategoria `coverage-gap`.
- Gdzie: `ScheduleRunCommand.php:125,163-173`; `CACHE_STORE=database`.
- Na produkcji: mutex `withoutOverlapping`/`onOneServer` czyta bazę przed startem zadania, więc pada sam `schedule:run`. Zostaje tylko wpis w `laravel.log`.
- Kierunek poprawki: plikowy store dla mutexów albo heartbeat z BKP-1.

**BKP-7 — wynik zadań jest wyrzucany, a `scheduler.log` to szum.** Średnie, kategoria `logged-not-captured`.
- Gdzie: `vendor/.../Scheduling/Event.php:41`; brak `appendOutputTo`.
- Na produkcji: zostaje jedna linia „FAIL” i około 1440 linii dziennie „No scheduled commands are ready”. Błąd ma przy tym dwa wpisy, z których drugi nie ma przyczyny.
- Kierunek poprawki: `->appendOutputTo(storage_path('logs/backup.log'))` i `schedule:work --whisper`.

**BKP-8 — bezczynny worker kolejki zalewa log w czasie awarii bazy.** Średnie, kategoria `noise`.
- Gdzie: `supervisord.prod.conf:27-33`; `vendor/.../Queue/Worker.php:518-523`.
- Na produkcji: co 1–2 s pełny stos `QueryException` w nierotowanym `laravel.log`. Prawdziwe błędy toną, a dysk dzielony z kopiami się zapełnia.
- Kierunek poprawki: usunąć worker, dopóki nic nie jest kolejkowane, i kanał `daily`.

**BKP-9 — błędy odtwarzania gubią przyczynę.** Średnie, kategoria `missing-context`.
- Gdzie: `RestoreApplicationBackup.php:81-97,189-193`.
- Na produkcji: złe hasło, pełny dysk i uszkodzony zip dają ten sam komunikat „Could not extract”. Po porażce psql nie pada informacja, że baza została pusta.
- Kierunek poprawki: `getStatusString()` w komunikacie i „baza pozostała pusta”.

**BKP-10 — `LOG_LEVEL=error` odcina jedyny zapis ścieżki snapshotu scalania.** Średnie, kategoria `logged-not-captured`.
- Gdzie: `MergeCategories.php:64`; `.env.prod.example:21`.
- Na produkcji: README każe szukać pliku odzyskania w logu, ale tego wpisu nigdy nie ma.
- Kierunek poprawki: zapisać ścieżkę w osobnym kanale audytu, którego nie odcina `LOG_LEVEL=error`, albo pokazać ją w komunikacie flash po scaleniu.

**BKP-11 — zapasowym adresatem maila jest adres nadawcy.** Niskie, kategoria `config`.
- Gdzie: `config/backup.php:98`.
- Na produkcji: przy pustym `BACKUP_NOTIFICATION_MAIL` alerty idą na `noreply@…`, którego nikt nie czyta. W `.env.prod.example` zmienna jest ustawiona, więc to ryzyko na przyszłość.
- Kierunek poprawki: bez zapasowego adresu; błąd przy starcie w produkcji.

**BKP-12 — błąd kopii zdalnej jest wyciszony.** Niskie, kategoria `swallowed`.
- Gdzie: `vendor/spatie/.../BackupJob.php:365-383`; `deploy.sh:58` (`--disable-notifications`).
- Na produkcji: podczas deployu błąd kopii zdalnej znika całkowicie, a migracja rusza.
- Kierunek poprawki: zostawić powiadomienia albo sprawdzić stan kopii przed migracją.

**BKP-13 — kroki deployu po `chown` biegną jako root.** Niskie, kategoria `coverage-gap`.
- Gdzie: `deploy.sh:46,61,68-72`.
- Na produkcji: `laravel.log` albo widoki należące do roota blokują zapis przez PHP-FPM (sail), co prowadzi do PLB-1.
- Kierunek poprawki: `-u sail` przy migracji i cache.

**BKP-14 — puste hasło archiwum daje kopię niezaszyfrowaną bez ostrzeżenia.** Niskie, kategoria `config`.
- Gdzie: `config/backup.php:74-76`.
- Na produkcji: jawny zip, także w kopii zdalnej.
- Kierunek poprawki: błąd przy starcie w produkcji, gdy hasło jest puste.

### Platforma i wspólny kod

**PLB-1 ✔ — niezapisywalny `laravel.log` oznacza całkowitą ciszę.** Wysokie, kategoria `coverage-gap`.
- Gdzie: `vendor/.../HandleExceptions.php:194-198`; `LOG_STACK=single`.
- Na produkcji: właściciel root (BKP-13, lokalny restore) albo pełny dysk sprawiają, że każdy błąd ginie. Użytkownik dostaje 500, na stderr nie trafia nic.
- Kierunek poprawki: `LOG_STACK=daily,stderr`. Tracker z S-45 zarejestrować przed loggerem.

**PLB-2 — klient nie ma żadnego kanału błędów.** Wysokie, kategoria `coverage-gap`.
- Gdzie: `resources/js/app.ts:11-38`; `OperationsImport.vue:160-171`; `OperationsImportPreview.vue` (brak `onError`).
- Na produkcji: błąd renderu Vue daje pusty fragment, a błąd sieci w dialogu zostawia dialog bez reakcji i z nieobsłużonym promise w konsoli. Nic nie trafia na serwer.
- Kierunek poprawki: `app.config.errorHandler`, `unhandledrejection` i globalne `router.on('httpException'/'networkError')` z toastem teraz i reporterem w S-45.

**PLB-3 — strażnicy niezmienników w akcjach to niezgłaszane 404/403.** Wysokie, kategoria `flattened-response`.
- Gdzie:
  - `ManageFinancialOperation.php:143,194,229-265`;
  - `JoinBudgetAtFirstUse.php:42-88,206-254`;
  - `ChangeSheetOperationCategory.php:66-71`;
  - `ManageOwnTransfer.php:91-93`;
  - `ManageCapitalMovement.php:119-130`;
  - `CategoryController.php:157`;
  - `CategoryValidationRules.php:67-70`;
  - `DashboardController.php:167`.
- Na produkcji: gdy walidacja i akcja się rozjadą (albo coś zostanie zarchiwizowane w drugiej karcie), użytkownik widzi modal „404 Not Found” nad dialogiem, a cały import, zatwierdzenie lub zapis się wycofuje. W logu nie ma nic.
- Kierunek poprawki: zgłaszany wyjątek domenowy (np. `InvariantViolation`) z id zamiast `ModelNotFoundException`.

**PLB-4 — kontekst wpisu to tylko `userId`.** Średnie, kategoria `missing-context`.
- Gdzie: `vendor/.../Handler.php:672-680`; w `app/` brak `Context::`.
- Na produkcji: wpis nie ma trasy, metody, URL, id żądania ani wydania. Nie da się ustalić, który przepływ padł.
- Kierunek poprawki: middleware `Context::add` (trasa, id żądania, wydanie) i `buildContextUsing`.

**PLB-5 — `catch {}` w konfiguracji 2FA.** Średnie, kategoria `swallowed`.
- Gdzie: `useTwoFactorAuth.ts:41,54,82,92`.
- Na produkcji: angielski komunikat, a przyczyna przepada w kliencie.
- Kierunek poprawki: związać błąd i przekazać go do kanału klienta.

**PLB-6 — błędy WebAuthn (passkeys) nie wychodzą z przeglądarki.** Średnie, kategoria `coverage-gap`.
- Gdzie: `PasskeyVerify.vue`; `PasskeyRegister.vue`; vendor `PasskeyVerificationRequest.php:56` (`catch (Throwable)`).
- Na produkcji: zła konfiguracja RP ID po cichu wyłącza logowanie passkey.
- Kierunek poprawki: przekazać `onError` do kanału klienta.

**PLB-7 — strona błędu to ogólny angielski modal.** Niskie, kategoria `flattened-response`.
- Gdzie: brak `resources/views/errors/` i `Inertia::handleExceptionsUsing`.
- Na produkcji: użytkownik nie ma ścieżki powrotu ani informacji, co zgłosić.
- Kierunek poprawki: polska strona błędu z id żądania.

**PLB-8 — komendy konsoli wypisują tylko `getMessage()`.** Niskie, kategoria `logged-not-captured`.
- Gdzie: 30 bloków `catch` w `app/Console/Commands` (np. `RestoreApplicationBackupCommand.php:21,40`, `SeedPreviousYearCommand.php:42,63`).
- Na produkcji: operator nie dostaje stosu, a w logu nie ma wpisu.
- Kierunek poprawki: `report($e)` w komendach, które zapisują dane.

**PLB-9 — `LOG_DEPRECATIONS_CHANNEL=null`.** Niskie, kategoria `config`.
- Gdzie: `.env.prod.example:20`.
- Na produkcji: deprecations z PHP 8.5 są niewidoczne, choć kod celuje w 8.4.
- Kierunek poprawki: kanał `stack` na poziomie `warning`.

**PLB-10 — wyścig nazw w `Create*`/`Rename*` jest logowany jako `error`.** Niskie, kategoria `noise`.
- Gdzie: `CreateCategory.php:76`; `CreateListCategory.php:69`; `RenameCategoryGroup.php:36`.
- Na produkcji: podwójne kliknięcie użytkownika daje wpis błędu.
- Kierunek poprawki: zostawić bez zmian, dopóki to rzadkie. Po S-45 obniżyć do poziomu `info`.

**Uzasadnione, bez działań:**
- `RehearsalClock.php:52`: działa tylko przy `isLocal()` i rzuca dalej z `previous`.
- `RedirectsToOperationsList.php:14-38`: odrzucanie kluczy listy ma komentarz.
- `SelectedBudgetYear.php:235-238`: `match` bez gałęzi domyślnej da głośny 500 przy nowej fazie (Year Close), więc warto o tym pamiętać.
- Odczyt Dashboardu nie ma `try/catch`, więc jego błędy są głośne.
- Błędy `pg_dump` mają kod wyjścia, stderr, `report()` i mail.

## 6. Zalecana kolejność poprawek

1. **Wyjątek domenowy dla niezmienników i `report()` przed każdą konwersją na komunikat** (przyczyny 1 i 2). Jedna klasa (`InvariantViolation` z id) w miejsce `firstOrFail`/`abort` w akcjach po walidacji. Do tego związanie i `report()` w `ApproveMonthPlan.php:89`, `ChangeMonthPlan.php:86/184`, `CsvImportController.php:75` i `PreviewCsvImportRequest.php:103`, przy czym `catch (InvalidArgumentException)` trzeba zawęzić do własnego wyjątku. Zamyka PLB-3, MPL-2, MPL-3, IMP-8 i IMP-9, a częściowo OPS-3 i MPL-7. To mały wysiłek o szerokim zasięgu i warunek, żeby S-45 cokolwiek z tych miejsc zobaczył. Ograniczenie: nie zgłaszać prawdziwych błędów wejścia (szum, PLB-10).
2. **Harmonogram kopii przed przełomem roku.** `evenInMaintenanceMode()`, `onFailure`/`appendOutputTo` i heartbeat po udanym `backup:run` (zewnętrzny dead-man's switch albo tygodniowy mail „healthy”). Zamyka BKP-1, BKP-2, BKP-5 i BKP-7, a częściowo BKP-6. Wybór usługi heartbeat to decyzja użytkownika poza repo.
3. **`'throw' => true` na dysku `local` albo sprawdzanie `put()` w `BackupUserData`.** Zamyka BKP-3. To kilka linii, a ryzyko jest wysokie, bo snapshot przed scalaniem i przed deployem jest dziś ślepy. Testy `UserSnapshotCommandTest` trzeba przejrzeć pod kątem nowego zachowania.
4. **Przełom roku w planie miesiąca** (przed 2026-12-31, współgra z S-39/S-41). `errors.items` w każdym stanie albo toast, zgłoszona odmowa „miesiąc minął” z kontekstem, osobne klucze odmów i pole `source` propozycji. Zamyka MPL-1, MPL-4, MPL-5 i MPL-6. Ograniczenie: przy `LOG_LEVEL=error` `Log::warning` nie trafi do pliku, więc trzeba użyć `report()` albo obniżyć próg.
5. **Import przed S-42.** Licznik odrzuconych linii, odmowy przy zapisie z indeksem i datą, komunikat wygaśnięcia podglądu i token podglądu. Zamyka IMP-1, IMP-2, IMP-5 i IMP-6, a częściowo IMP-7. IMP-4 (timeout) wymaga decyzji: kolejka czy partie.
6. **Zlew logów i kontekst** jako przygotowanie S-45. `LOG_STACK=daily,stderr`, middleware `Context::add` (trasa, id żądania, wydanie z `git rev-parse` w obrazie) i usunięcie bezczynnego workera. Zamyka PLB-1, PLB-4, IMP-10, BKP-8 i BKP-13. Ograniczenie prywatności: id zamiast e-maila, bez kwot i opisów operacji w kontekście.
7. **Kanał błędów klienta.** Globalne `httpException`/`networkError`/`errorHandler`/`unhandledrejection` z toastem i zapasowa lista kluczy w dialogach. Zamyka PLB-2, PLB-5, PLB-6, OPS-4, OPS-5 i MPL-9. Wysyłka na serwer czeka na S-45.
8. **`/up` z `select 1` i porażką w trybie konserwacji.** Zamyka BKP-4.
9. **Dryf liczników.** Poprawka wyścigu w `ManageOwnTransfer`/`ManageCapitalMovement` (blokada + `increment`) i zaplanowana kontrola licznik ↔ księga. Zamyka OPS-2 i OPS-1. OPS-2 to błąd poprawności i warto go naprawić niezależnie od audytu.
10. Pozostałe ustalenia średnie i niskie przy okazji zmian w danym obszarze.

## 7. Zmiany od ostatniego audytu

Pierwsze uruchomienie, brak wcześniejszego raportu.

## 8. Metoda i ograniczenia

- **Agenci:** czterech audytorów obszarów i jeden audytor wspólnego kodu, wszyscy tylko do odczytu i równolegle. Sprawdzone wersje: laravel/framework v13.42.0, inertia-laravel v3.7.0, @inertiajs/core i vue3 3.11.0, Vue 3.5.45, spatie/laravel-backup ^10.3.
- **Sprawdzone ręcznie podczas syntezy (wszystkie potwierdzone):**
  - MPL-1 (`ApproveMonthPlan.php:41-43,89`, `MonthPlanProposal.php:158,176`, `MonthPlan.vue:139-147,666-669`);
  - IMP-1 (`CsvImportController.php:82-85,472-479`, `CsvImportParser.php:124-141`);
  - IMP-2 (`ImportFinancialOperations.php:150-153`);
  - OPS-2 (`ManageOwnTransfer.php:96-101` wobec `ManageFinancialOperation.php:251-287`);
  - PLB-1 (`HandleExceptions.php:194-198`) i kontekst `Handler.php:672-680`;
  - BKP-2 (`Event.php:285-289`, `production-deploy.sh:108-115`);
  - BKP-3 (`filesystems.php:37-38`, `BackupUserData.php:34-41`);
  - powiadomienia tylko przy błędzie (`backup.php:86-98`).
- **Duplikaty usunięte między obszarami:** strażnicy 404 w imporcie, operacjach i planie miesiąca to jedno PLB-3. Brak globalnego kanału klienta z trzech obszarów to jedno PLB-2. Timeout FPM z dwóch raportów to jedno IMP-4.
- **Odrzucone jako warunki wstępne:** brak trackera (S-45), `access_log off` w nginx (log pisze Traefik, zob. Założenia) i brak reguł alertów.
- **Liczby z przeszukania repo (bez testów):**
  - serwer: 45 bloków `catch`, 0 pustych, 12 bez związania zmiennej, 7 wywołań `report()`, 0 `Log::error`, 35 bloków, które ani nie rzucają dalej z przyczyną, ani nie zgłaszają (30 w komendach konsoli i 5 w HTTP), 3 `throw` w `catch` bez `previous`;
  - klient: 13 bloków `catch` (4 `catch {}` w `useTwoFactorAuth.ts`), 7 `console.error`, 2 `console.warn`, 0 globalnych hooków błędów.
- **Środowisko uruchomieniowe:** nie uruchamiano (`runtime_proof: not-run`). Wszystkie werdykty z §3 są statyczne. Najlepsze kandydatury na sondę w izolowanym worktree z `fake-ingest.mjs`: MPL-1 (zegar próby `app:rehearsal-clock` na 2027-01-01 00:30), IMP-1 (CSV z datą `2026/12/31`), PLB-3 (zarchiwizowana kategoria między podglądem a zapisem), BKP-2 (`artisan down` + `schedule:run`) i BKP-3 (`storage/app/private` tylko do odczytu).
- **Nie dało się zweryfikować:** realnej zawartości `.env.prod`, działania SMTP, logu Traefika i tego, czy cokolwiek z zewnątrz pinguje `/up`.
