---
date: 2026-11-07T19:45:45+01:00
researcher: Claude (Sonnet 5.5)
git_commit: 1b17fc4577bb0ebec0c991788e40f995d2240f91
branch: development
repository: household-treasurer
topic: "ui-fixes-2: kwoty (wyjątki od ADR-0037), nazwa i znak, układ Dashboardu i Budżetu, strzałka zmiany planu, Plan miesiąca i Konta"
tags: [research, codebase, ui, atrament, amounts, branding, dashboard, budget, month-plan, account-avatar]
status: complete
last_updated: 2026-11-07
last_updated_by: Claude (Sonnet 5.5)
---

# Research: ui-fixes-2

**Date**: 2026-11-07T19:45:45+01:00
**Git Commit**: 1b17fc4577bb0ebec0c991788e40f995d2240f91 (branch `development`, working tree clean at start)

## Research Question

Sześć grup poprawek UI zgłoszonych przez użytkownika: (1) kwoty – zniesienie czterech wyjątków od reguły „jednostka w stopce” i stopka „Kwoty w zł” w każdej tabeli; (2) nazwa „Domowy Skarbnik” i nowy znak/favicon; (3) układ kolumn Dashboardu (`--aside-width`, miernik kategorii); (4) Budżet – `PlanChangedHint` i szerokości kolumn, strzałka jako nowe znaczenie koloru; (5) Plan miesiąca – „zatwierdzono” jak w Budżecie, wysokość ikony `CategoryChip`; (6) gotówka w `account-identity.ts` jako ikona zamiast „zł”; plus sinki do uzupełnienia. Badanie sprawdza stan kodu i dokumentów oraz weryfikuje twierdzenia z zgłoszenia. Nie zawiera decyzji ani planu.

## Summary

Wszystkie wskazane kotwice istnieją i opisują to, co zgłoszenie mówi (z drobnymi przesunięciami numerów linii, np. miernik kategorii jest przy `app.css:1580`, nie `1576`). Najważniejsze ustalenia, które zmieniają lub uzupełniają zgłoszenie:

- **Kwoty.** Cztery ADR-y (0039, 0044, 0051, 0052) rzeczywiście są wyjątkami od ADR-0037. Poza nimi istnieją **jeszcze komórki z „zł” bez żadnego ADR-u**: `MonthPlanItemRow.vue:127` i `:176`, `MonthYearSummary.vue:42,44,48`, `MonthSummaryCard.vue:159`. `DashboardHoldings.vue` ma odwrotny problem: komórki bez jednostki i **bez stopki**. Nowa stopka „Kwoty w zł” ma już klucz `budget.amounts_in_zl` (`lang/pl/ui.php:248`), ale nazwa klucza jest budżetowa.
- **Nazwa.** Widoczna nazwa płynie z jednej zmiennej `APP_NAME`, ale ta sama zmienna **nazywa ciasteczko sesji, prefiks cache i katalog kopii Spatie**; zmiana wartości w `.env` bez przypięcia tych trzech wylogowuje wszystkich i resetuje monitoring kopii.
- **Znak.** „HT” to tekst w `BrandMark.vue`, a nie obraz; ADR-0053 wprost mówi „kafelek tekstowy, nie obraz”. Zmiana na grafikę wymaga nowego ADR-u, który zmienia to postanowienie, oraz edycji trzech miejsc w `design-system.md`.
- **Dashboard.** Szeroki ekran nie ma żadnej obsługi (brak `2xl`/1536/1920 w `app.css`); kolumna główna w układzie z sidebarem nie ma `max-width`.
- **Strzałka.** W repo **nie istnieje** token ani znaczenie „kierunek zmiany” (wzrost/spadek). Dane do kierunku są po obu stronach (`initial_plan_minor_units` vs `current_plan_minor_units` w Budżecie; `plan_minor_units` vs `approved_minor_units` w Planie miesiąca).
- **Gotówka.** `accountShortMark` zwraca `'zł'` tylko dla `type === 'cash'`; `AccountAvatar.vue` ma już gałąź ikony (`Building2`), więc ikona gotówki to drugi wariant tej gałęzi.
- **Sinki.** Wszystkie dziewięć tras z listy istnieje; nazwy zgadzają się z podanymi.

## Detailed Findings

### 1. Kwoty: reguła ogólna i cztery wyjątki

**Reguła ogólna (ADR-0037).** W komórkach tabel jednostka stoi w stopce panelu „Kwoty w zł”; komórka używa `formatMoneyAmount` ze stylem `amount` (`context/foundation/design-system.md:71`; `.ai/rules/js.md:17`). Miary (metryki) i dialogi zostają przy `formatMoney`.

**Wyjątki (stan: ADR-y 0039, 0044, 0051, 0052, każdy sprawdzony w pliku).**

| ADR | Zakres wyjątku | Uzasadnienie w ADR |
| --- | --- | --- |
| 0039 | lista Operacji: kwota, suma dnia, kwoty przelewów własnych i ruchów kapitału; brak stopki „Kwoty w zł”, zostają stopki objaśniające przelewów i ruchów | lista z makiety, bez nagłówków kolumn, wiersz dnia niesie sumę (wniosek z treści, uzasadnienie niewypowiedziane wprost) |
| 0044 | tabela Kont | jedna kolumna kwot, brak wiersza sum, sumy w panelu metryk; ADR odrzuca stopkę jako zbędną |
| 0051 | tabela Lokat | jak 0044 |
| 0052 | podgląd importu | wiersze staną się wierszami Operacji i mają wyglądać tak samo przed i po zapisie; odrzucono `formatMoneyAmount` ze stopką |

Reguła jest zapisana w czterech miejscach, które trzeba zmienić razem: `design-system.md:3` (indeks ADR), `:71` i `:75`, `.ai/rules/js.md:17`, `.ai/rules/js-operations.md:15`, `.ai/rules/js-import.md:30`. Numeracja: najwyższy ADR to **0057**, brak 0046, **następny wolny to 0058** (`context/foundation/adr/`). ADR-y nie mają frontmattera ani pola Status; zastąpienie zapisuje się prozą na końcu („Superseded in part by ADR-NNNN: …”), np. ADR-0044 kończy się odwołaniami do 0045 i 0047. ADR-0037 nie ma odwołań wstecznych do 0039/0044/0051/0052.

**Miejsca renderujące „zł” w komórce (z kotwicami).**

- Pokryte wyjątkiem: `FinancialOperationRow.vue:84` (`formatFinancialOperationAmount`), `OperationsDayRow.vue:23` (`formatOperationDaySum`), `OwnTransferRow.vue:57` i `CapitalMovementRow.vue:79` (`formatMoney`), `Accounts.vue:229` (`formatMoney(account.current_balance_minor_units)`, sprawdzone bezpośrednio), `TermDeposits.vue:261`, `ImportPreviewRow.vue:201-204` i `:408` (`rowAmount?.text`, przez `lib/import-row-amount.ts`).
- **Bez żadnego ADR-u**, choć w komórkach Planu miesiąca: `MonthPlanItemRow.vue:127` (`formatMoney(row.available_limit_minor_units)`, linia „Dostępny limit”, sprawdzone bezpośrednio), `:176` (`formatMoney(row.approved_minor_units)`), `MonthYearSummary.vue:42,44,48`, `MonthSummaryCard.vue:159` (`formatMoney(year_draw)`; ta sama karta ma stopkę `amounts_in_zl` w `:161-163`, więc jednostka jest tam podwójnie). Czy te wiersze to „miary” (dozwolone `formatMoney`), czy komórki, nie jest rozstrzygnięte w dokumentach – to decyzja do planu.
- Formatery: `lib/format.ts:5` (`MONEY_UNIT = 'zł'`), `:7` `formatMoneyAmount`, `:21` `formatMoney`, `:29/:38` warianty ze znakiem, `:182` `formatFinancialOperationAmount`, `:212` `formatOperationDaySum`. Brak `Intl.NumberFormat` dla kwot.

**Stopki „Kwoty w zł” dziś.** Mają: Budżet (`BudgetCategoryList.vue:372-374`), tabela Planu miesiąca (`MonthPlanTable.vue:363-365`), `MonthSummaryCard.vue:161-163`, panel wykonania na Dashboardzie (`DashboardExecutionPanel.vue`, klucze `dashboard.execution_footer*` `lang/pl/ui.php:195-196` łączą jednostkę z innym tekstem), podgląd landingu. Nie mają: Operacje (finansowe), Konta, Lokaty, podgląd importu; `DashboardHoldings.vue` ma stopki z sumą, ale bez jednostki, mimo że komórki są bez jednostki (luka w obecnej regule). Jedyny ogólny klucz: `lang/pl/ui.php:248` `budget.amounts_in_zl`.

**Testy, które zareagują na zniesienie wyjątków (sprawdzone grepem w `tests/` i `resources/js`).**
- `resources/js/lib/import-row-amount.test.ts:10,20,30,40` (oczekiwane `'+64,00 zł'` itd.) – zmieni się, jeśli `importRowAmount` straci jednostkę.
- `tests/e2e/seed.spec.ts:39,42` (`toContainText('−12,34 zł')` na wierszu Operacji) – zmieni się razem z wierszem Operacji.
- `resources/js/lib/format.test.ts` (`:38-46`, `:79-99`, `:123-131`) – dotyczy wyłącznie samych formaterów; pozostaje zielone, jeśli zmieniają się tylko miejsca wywołania.
- Nie dotyczą: `account-identity.test.ts` (kafelek gotówki, osobna sprawa), `command-palette.spec.ts:87-92` (wpisuje „zł” w wyszukiwarkę; paleta używa `formatMoney` w `CommandPalette.vue:198`, to nie tabela). Testy PHP nie asertują „zł” – kwoty formatuje klient.
- Źródła kwot w sinkach: `lib/import-preview-fixtures.ts`, `lib/operations-fixtures.ts`, `pages/dev/AccountsKitchenSink.vue`, `TermDepositsKitchenSink.vue`, `OperationsSink.vue`, `ImportPreviewSink.vue`.

### 2. Nazwa „Domowy Skarbnik”

**Widoczna nazwa i skąd płynie.**
- `.env.example:1` `APP_NAME="Household Treasurer"`; to samo w `.env.prod.example:1`. `config/app.php:16` czyta `env('APP_NAME', 'Laravel')` – brak zakodowanej nazwy.
- Do Inertii: `HandleInertiaRequests.php:43` (`'name' => config('app.name')`). Konsumenci: `AppLogo.vue:5/14`, `AppMobileBar.vue:9/23`, `layouts/auth/AuthCardLayout.vue:17/31`, `pages/Welcome.vue:23` (+ `:92`, `:107`, `:286`), `LandingAppPreview.vue:20/123`, `dev/SidebarKitchenSink.vue:31/470`.
- Osobna ścieżka: tytuły stron `resources/js/app.ts:8,11` (`import.meta.env.VITE_APP_NAME`, wypalane przy buildzie; `Dockerfile.prod:47`, `docker-compose.prod.yml:8` mają domyślnie „Household Treasurer”, więc obraz produkcyjny wymaga przebudowy); `resources/views/app.blade.php:41` (`<title>{{ config('app.name') }}`).
- Zakodowane w tłumaczeniach: `lang/pl/ui.php:45` (`auth.aside.legal` = „© :year Household Treasurer”) i `:786` (`landing.meta_description`, używany w `Welcome.vue:96`).
- Dokumenty: `README.md:1`, `AGENTS.md:1`, `CHANGELOG.md:3`, `.ai/skills/openship-ui-development/SKILL.md:3,9`. `CLAUDE.md` i `CLAUDE.local.md` nie sprawdzone pod tym kątem.
- Żaden test nie asertuje nazwy (grep „Household”, „Treasurer”, „Skarbnik” w `tests/`, `playwright/`, `resources/js`, `phpunit*.xml` – jedyny trafiony `tests/Feature/SeedDemoHouseholdCommandTest.php:12` to nazwa klasy).

**Techniczne – nie zmieniać:** `DB_DATABASE=household_treasurer` (`.env.example:26`, `.github/workflows/tests.yml`, `playwright.config.ts:45`, `scripts/e2e-server.sh`), katalog kopii `/storage/household-treasurer-backups`, tag obrazu `household-treasurer-app:prod`, sieć `household-treasurer_sail` (zależy od nazwy katalogu), `package-lock.json:2`.

**Skutki uboczne zmiany `APP_NAME` (z kodu, nie z uruchomienia):**
- ciasteczko sesji: `config/session.php:132` = `Str::slug(APP_NAME).'-session'` → zmiana wylogowuje wszystkich;
- prefiks cache: `config/cache.php:121`, Redis `config/database.php:152`; `CACHE_PREFIX` jest w `.env.example:41` zakomentowany, `SESSION_COOKIE` nie występuje w żadnym z przykładów;
- Spatie Backup: `config/backup.php:25` i `:132` używają `APP_NAME` jako nazwy kopii i monitora → po zmianie monitor szuka w nowym, pustym katalogu aż do pierwszej kopii;
- issuer 2FA: `vendor/laravel/fortify/src/TwoFactorAuthenticatable.php:85` – nowe kody QR dostają nową nazwę, istniejące wpisy w aplikacjach uwierzytelniających zachowują starą etykietę, sekrety bez zmian;
- passkeys: bez skutku (`config/fortify.php:146` używa hosta z `APP_URL`, `vendor/laravel/passkeys/src/Passkeys.php:59-61`);
- nazwa nadawcy poczty (`MAIL_FROM_NAME="${APP_NAME}"`, `.env.example:57`), nazwa użytkownika logu Slack (`config/logging.php:87`).

### 3. Znak (BrandMark) i favicon

- `resources/js/components/BrandMark.vue` to jeden `<span aria-hidden="true" class="brand-mark bg-brand text-brand-foreground shrink-0 rounded-sm">HT</span>`; brak propsów, wariantów rozmiaru i obrazu. Styl: `@utility brand-mark` `app.css:300-304` (font mono 11 px, 600), rozmiar `--brand-mark-size: 26px` `app.css:162`.
- Użycia: `AppLogo.vue`, `AppMobileBar.vue`, `AuthCardLayout.vue`, `Welcome.vue` (nagłówek `:106`, stopka `:284`), `LandingAppPreview.vue:121`, `dev/SidebarKitchenSink.vue:468` (sekcja `data-frame="brand-mark"`, `:449-475`, tablica `brandMarkPlaces` `:33`, trasa `/dev/ui/sidebar`).
- Dokumenty do zmiany przy nowym znaku: `design-system.md:38` (wiersz tokenu `brand-mark`, „znak „HT””), `:51` (opis `brand`), `:144` („Znak produktu to BrandMark („HT”… Bez ikon Lucide w roli znaku”). ADR-0053 (`:15`): znak to „HT” w JetBrains Mono, „kafelek tekstowy, nie obraz”, zastąpił glif `Vault`. ADR-0057 (`:44`): „znak zawsze z nazwą, bez wyjątku”. Zmiana na grafikę zmienia więc postanowienie ADR-0053 (potwierdza zgłoszenie „to też zmiana z ADR-0053/0057”).
- `resources/js/ui-contract.test.ts:319-330` wymaga napisu `BrandMark` w `AppLogo`, `AuthCardLayout`, `Welcome`, `LandingAppPreview` i zabrania `AppLogoIcon` i `Vault`; tekstu „HT” nie sprawdza. Zachowanie nazwy komponentu nie wymaga zmiany tego testu.
- `public/favicon.svg` (3545 B, viewBox 166×166) to domyślny sześciokąt Laravela `#FF2D20`, nietknięty od commita `d6196b1`. `public/favicon.ico` (32×32), `public/apple-touch-icon.png` (166×166, a nie 180×180; zawartość nieobejrzana, prawdopodobnie domyślna). Brak `site.webmanifest`. Linki: `app.blade.php:36-38`; brak `theme-color` i manifestu. Kolory przydatne do projektu ikony: tło `#edf0f4`/`#0a111c` (`app.blade.php:25-31`), brand `#5fd4c4`.
- Brak specyfikacji SVG znaku.

### 4. Dashboard: układ kolumn

- Siatka strony: `app.css:1654-1661` – `.dashboard-grid` jedna kolumna, od `lg` `minmax(0, 1fr) var(--aside-width)`; `--aside-width: 300px` w `app.css:167`. Użycie: `Dashboard.vue:197`; lewa sekcja `DashboardBudgetBlock` → `DashboardExecutionPanel` (tabela `.category-meter-table`), prawy `<aside>` z `DashboardHoldings` (Konta i Lokaty, jedna kolumna, wiersze `.dashboard-holdings-row` `app.css:1342`).
- Tabela mierników: `container-type: inline-size` (`app.css:1567-1569`); od `@container (min-width: 38rem)` pięć kolumn `minmax(8rem, 1.4fr) 5rem 5rem 5rem minmax(9rem, 1fr)` (przy `app.css:1574-1580`). Kolumny kwot są sztywne 5rem, rośnie tylko kategoria (1.4fr) i miernik (1fr) – to potwierdza opis zgłoszenia o pustej przestrzeni; nadwyżka dzieli się między dwie kolumny.
- Szeroki ekran: brak jakiejkolwiek reguły (`2xl`, 1536, `min-width: 96rem` – nie znaleziono w `app.css`). `AppContent.vue:29` daje `max-w-7xl` tylko wariantowi bez sidebara; wariant z sidebarem (`AppContent.vue:23`) nie ma limitu szerokości.
- Decyzje projektowe: ADR-0043 („prawa kolumna ma stałą szerokość, token `--aside-width`, zamiast ok. 2/3 i 1/3”), ADR-0050 (próg 38rem na szerokości kontenera, bo próg okna zawiódł przy 1280 i 1024 px). Żaden z nich ani `design-system.md` nie ustala `max-width` strony. Zmiana szerokości kolumny aside zmienia więc ADR-0043 w części o „stałej szerokości”.

### 5. Budżet: `PlanChangedHint` i szerokości kolumn

- Siatka kategorii (tylko od `xl`): `app.css:1073-1082` – `2.5rem minmax(0,1fr) repeat(4, 6.5rem) minmax(6rem, 8rem) 4.5rem` (areas `handle category opening current execution remaining meter actions`); wariant bez uchwytu poniżej, szkic dalej od `:1092`. Użycie: `BudgetCategoryRow.vue:65`, `BudgetCategoryHeaderRow.vue`, `BudgetAmountCell.vue`. Kolumny kwot i miernik mają górną granicę (6.5rem, 8rem), więc po nich rośnie wyłącznie kolumna kategorii – potwierdza zgłoszenie.
- `PlanChangedHint.vue`: props `category: {initial_plan_minor_units, current_plan_minor_units}` i `label`; pokazuje się, gdy `planChanged(category)` (`lib/dashboard-categories.ts:62`) – czyli `initial !== current`; ikona `History` (`size-3.5`) w `text-brand-text`; etykieta/tooltip z `lang/pl/ui.php:240` („Plan zmieniony względem planu początkowego”). Jedyne użycie: `BudgetCategoryRow.vue:116`, w komórce „Plan początkowy”. Ikona nie niesie kierunku; kierunek jest wyliczalny z tych samych dwóch pól.
- Plik `PlanChangedHint.vue` **nie** jest na liście `scripts/check-ui-literals.mjs` ani w `BUDGET_VIEW_FILES` w `ui-contract.test.ts`; `CategoryChip.vue` także nie jest na liście `check:ui`.

### 6. Strzałka jako nowe znaczenie koloru

- Tabela „Kolor znaczy coś tylko w trzech miejscach” w `design-system.md:46-67` (kolumny Token | Znaczenie | Nie używaj do). Tokeny: `brand-text` (`app.css:126`, ciemny `:201`), `success` (`:136/:211`), `warning` (`:138/:213`), `refund` (`:141/:216`), `destructive`. **Brak** tokenu ani zapisu znaczenia „wzrost/spadek planu”; kierunek wyraża dziś tylko `amountToneClass` (`lib/amount-tone.ts`: >0 `success`, <0 `destructive`) i `deviationClass` w `MonthPlanItemRow.vue`.
- Reguła z `design-system.md:66`: „Nowy kolor dodawaj jako token w obu trybach i sprawdź kontrast w obu, nie jako klasę palety”; `:3` to indeks ADR-ów, do którego trzeba dopisać nowy.
- Nie znaleziono osobnej strony-rejestru tokenów w sinkach; stany dokumentuje sekcja „Kitchen sink i zrzuty” `design-system.md:103-113` oraz sam sink danego widoku. Czy strzałka ma używać istniejących `success`/`destructive`, czy nowego tokenu, nie wynika z dokumentów (otwarte; ma znaczenie, bo wzrost wydatku to nie „sukces”).

### 7. Plan miesiąca: „zatwierdzono” i `CategoryChip`

- „zatwierdzono”: `MonthPlanItemRow.vue:168-177` – `<p class="meta text-muted-foreground">` z `t.month_plan.approved_amount` (`lang/pl/ui.php:423` = „Zatwierdzono”) i `formatMoney(row.approved_minor_units)`; widoczne tylko gdy `approved_minor_units !== null && !== plan_minor_units`. Kwota bieżąca: `<span class="amount">` `:158-160`. Dane: `approved_minor_units` w `types/month-plan.ts:41`, backend `MonthPlanController.php:265,292`, kolumna `approved_amount_minor_units` (`app/Models/MonthPlanItem.php`). Kierunek jest wyliczalny z `plan_minor_units - approved_minor_units`.
- `CategoryChip` (`:79-83`, domyślnie `size="md"`): kafelek `size-8` (32 px) z ikoną `size-4`, obok tylko nazwy (`text-sm font-medium`); podtytuły (linie roczna `:95-109`, dostępny limit `:111-129`) leżą pod całym wierszem chipa, więc kafelek jest wysoki tylko na nazwę. Żądana wysokość „nazwa + podtytuł” wymaga zmiany struktury (podtytuł do wnętrza chipa lub tryb chipa), a rozmiar jako nazwana klasa w `app.css`.
- Ograniczenia: `.ai/rules/js-month-plan.md` – nowy rozmiar/siatka jako nazwana klasa w `app.css`, nie wartość arbitralna; `tests/Unit/MonthPlanHardcodedUiTest.php` zabrania w 14 plikach (w tym `MonthPlanItemRow.vue`) hexów, kolorów funkcyjnych, klas palety, wartości arbitralnych (`text-[11px]`, `w-[…]`), `grid-cols-[`, `text-lg/xl/2xl/3xl`, `tracking-wider`, `rounded-xl/2xl`, `border-border/NN`, `opacity-NN`; zwykłe `size-4`, `text-xs` są dozwolone. Nowe komponenty muszą wejść na listę `scripts/check-ui-literals.mjs` i do `/dev/month-plan`.
- Siatka: `.month-plan-grid` `app.css:859-880` (poniżej `xl` trzy kolumny, od `xl` `minmax(0,1fr) 9rem 8rem 7rem 7rem 8rem`).

### 8. Gotówka i `AccountAvatar`

- `lib/account-identity.ts:57-67` `accountShortMark`: `type === 'cash'` → `'zł'` (potwierdzone w pliku); `accountAvatarContent` `:78-90` zwraca unię `{kind:'logo'} | {kind:'mark'} | {kind:'icon'}`; dla gotówki nawet z `mark_url` zwraca znak tekstowy. `accountShortMark` jest wołane tylko w tym pliku i teście; `accountAvatarContent` tylko w `AccountAvatar.vue:32`.
- `AccountAvatar.vue`: rozmiary `xs/sm/md` = `size-4/5/7`, ikony `size-2.5/3/3.5`; gałąź ikony renderuje `Building2`; ikony z `@lucide/vue`. Ikona gotówki (np. `Coins`) to nowy wariant tej gałęzi.
- Użycia `AccountAvatar` (wszystkie zmienią wygląd dla konta gotówkowego): `Accounts.vue:199`, `TermDeposits.vue:222`, `dev/AccountsKitchenSink.vue:154`, `AccountSelect.vue:87,113`, `AccountBankSelect.vue:56,73` (tu zawsze `type="bank"`), `OperationsActiveFilterChips.vue:265`, `CompactAccountIdentity.vue:19`, `ImportPreviewRow.vue:489,527`, `DashboardHoldings.vue:110,183`, `DashboardNotices.vue:298`.
- Testy do zmiany: `account-identity.test.ts:20-22` („returns zł for cash”) i `:55-61` (`{kind:'mark', text:'zł'}`). Dokumenty: `design-system.md:92` i `.ai/rules/js.md:22` wymieniają „zł” dla gotówki; ADR-0054 i ADR-0047 dotyczą znaków kont.

### 9. Sinki i `check:ui`

- Trasy (`routes/web.php`): `dev/ui/dashboard` (`:32`), `dev/ui/welcome` (`:34`), `dev/ui/accounts` (`:35`), `dev/import-preview` (`:37`, bez `ui/`), `dev/ui/sidebar` (`:41`), `dev/ui/term-deposits` (`:42`), `dev/kitchen-sink/budget` (`:48`, `Route::inertia`, tylko `app()->isLocal()`), `dev/month-plan` i `dev/operations` (`:58-59`, tylko `environment('local')`). Wszystkie dziewięć istnieje pod nazwami z zgłoszenia. Kontrolery `Dev\*` robią `abort_unless(app()->isLocal(), 404)`.
- Strony: `DashboardKitchenSink.vue` + `dashboard-fixtures.ts`, `BudgetKitchenSink.vue`, `MonthPlanSink.vue` + `month-plan-fixtures.ts`, `AccountsKitchenSink.vue`, `TermDepositsKitchenSink.vue` + `term-deposits-fixtures.ts`, `OperationsSink.vue` + `operations-fixtures.ts`, `ImportPreviewSink.vue` + `import-preview-fixtures.ts`, `SidebarKitchenSink.vue`, `WelcomeKitchenSinkController` (stany przez `?state=guest|guest-register|authenticated`).
- Testy sinków: `tests/Feature/BudgetKitchenSinkTest.php`, `DevMonthPlanSinkTest.php`, `DevOperationsSinkTest.php`, `DialogsKitchenSinkTest.php`.
- `npm run check:ui` (`package.json:11`, także `composer.json:70`) uruchamia `scripts/check-ui-literals.mjs` na zakodowanej liście plików; zakazuje hexów, kolorów funkcyjnych, rozmiarów `-[Npx]`/`-[Nrem]`, `/[0.N]`, klas palety z numerem, `white`/`black`, `dark:`. Poza listą: `PlanChangedHint.vue`, `CategoryChip.vue`, `account-identity.ts`.

## Code References

- `context/foundation/adr/0037-panel-controls-table-header-and-budget-layout.md` – reguła „jednostka w stopce”
- `context/foundation/adr/0039-…`, `0044-accounts-table-follows-atrament.md`, `0051-term-deposits-follow-design-system-page.md`, `0052-import-preview-follows-page-import-preview.md` – cztery wyjątki
- `context/foundation/design-system.md:3,38,51,66,71,75,92,103-113,144` – indeks ADR, token `brand-mark`, kolor, kwoty, avatar, sinki, znak
- `resources/js/lib/format.ts:5,7,21,182,212` – formatery kwot
- `resources/js/pages/Accounts.vue:229`, `TermDeposits.vue:261`, `components/FinancialOperationRow.vue:84`, `MonthPlanItemRow.vue:127,176` – „zł” w komórkach
- `resources/js/components/MonthSummaryCard.vue:159-163`, `MonthYearSummary.vue:42-48` – „zł” bez ADR-u
- `resources/js/components/DashboardHoldings.vue:142-155` – stopka bez jednostki
- `lang/pl/ui.php:45,240,248,423,786` – nazwa, `plan_changed`, `amounts_in_zl`, `approved_amount`, opis landingu
- `resources/js/components/BrandMark.vue`, `resources/css/app.css:162,300-304` – znak
- `public/favicon.svg`, `public/favicon.ico`, `public/apple-touch-icon.png`, `resources/views/app.blade.php:36-38` – ikony
- `config/session.php:132`, `config/cache.php:121`, `config/backup.php:25,132` – konfiguracje zależne od `APP_NAME`
- `resources/css/app.css:167,1073-1082,1567-1581,1654-1661` – `--aside-width`, siatki Budżetu i Dashboardu
- `resources/js/components/PlanChangedHint.vue`, `BudgetCategoryRow.vue:116`, `lib/dashboard-categories.ts:62` – podpowiedź zmiany planu
- `resources/js/lib/account-identity.ts:57-90`, `components/AccountAvatar.vue`, `lib/account-identity.test.ts:20-22,55-61` – gotówka
- `routes/web.php:32-59`, `scripts/check-ui-literals.mjs` – sinki i strażnik UI

## Architecture Insights

- Jednostka walutowa ma jedno źródło w kodzie (`MONEY_UNIT`), ale **trzy polityki prezentacji** naraz: stopka (Budżet, Plan miesiąca, Dashboard), jednostka w komórce na mocy ADR (Operacje, Konta, Lokaty, Import) i jednostka w komórce bez ADR (cztery miejsca Planu miesiąca). Ujednolicenie jest więc decyzją o regule, nie tylko o czterech widokach.
- Reguły UI są zdublowane w trzech warstwach, które trzeba zmieniać razem: ADR, `design-system.md` (w tym indeks w wierszu 3) i `.ai/rules/*.md` (po zmianie reguł `php artisan boost:index-rules` wg `AGENTS.md`). Strażnicy (`check:ui`, `MonthPlanHardcodedUiTest`, `ui-contract.test.ts`) pilnują wartości, nie reguły jednostki.
- Layout Dashboardu i Budżetu opiera się na stałych szerokościach kolumn kwot; elastyczne są tylko kolumny nazwy/miernika. Szerokie ekrany nie mają osobnej reguły.
- `APP_NAME` pełni równocześnie rolę nazwy wyświetlanej i identyfikatora infrastruktury (sesja, cache, kopie), czego dokumentacja nie wspomina; `.env.example` nie przypina `SESSION_COOKIE`.

## Historical Context (from prior changes)

Sprawdzono ADR-y (treść, nie tylko tytuły) i `design-system.md`. Katalogów `context/changes/**` i `context/archive/**` nie przeszukiwano pod kątem wcześniejszych badań tych tematów (poza ostatnim zamkniętym `welcome-atrament-ui`, widocznym w git log).

- ADR-0037 – reguła ogólna; wiersz „zastąpione częściowo” istnieje tylko w ADR-ach wyjątkowych, nie w 0037 (potwierdzone: brak odwołań wstecznych).
- ADR-0043 / 0050 – stała szerokość aside i próg 38rem; dotyczą bezpośrednio punktu o Dashboardzie.
- ADR-0053 / 0057 – znak „HT” jako kafelek tekstowy, „zawsze z nazwą”.
- ADR-0047 / 0054 – skróty banków i `AccountAvatar` (kontekst dla ikony gotówki).
- ADR-0042 – układ Planu miesiąca i „note alert” (nie czytany w całości; powiązany z punktem o Planie miesiąca).

## Related Research

Brak innych dokumentów `research.md` sprawdzonych na potrzeby tej zmiany.

## Open Questions

1. **Zakres zniesienia wyjątków:** czy linie „Dostępny limit”, „Zatwierdzona kwota”, `MonthYearSummary` i `MonthSummaryCard:159` traktować jako komórki (stopka, bez „zł”), czy jako miary (z „zł”)? Dokumenty tego nie rozstrzygają.
2. **Stopka w tabelach z jedną kolumną** (Konta, Lokaty) i w Operacjach, gdzie wiersz dnia niesie sumę: ADR-y 0039/0044/0051 odrzuciły stopkę jako zbędną; nowy ADR musi wprost zmienić to uzasadnienie. Osobno: `DashboardHoldings.vue` (dziś bez jednostki i bez stopki) oraz podgląd importu (spójność z Operacjami, uzasadnienie ADR-0052) – czy ujednolicenie ma objąć też Operacje, skoro podgląd ma wyglądać „jak po zapisie”.
3. **Klucz tłumaczenia** stopki: ponowne użycie `budget.amounts_in_zl` czy neutralny klucz wspólny; dla panelu wykonania Dashboardu stopka jest sklejona z innym tekstem.
4. **Znak:** brak specyfikacji graficznej (kształt, kolory, wariant jasny/ciemny, tryb mały 26 px, favicon SVG/ICO/180 px apple-touch). Nowy znak jest decyzją projektową, której kod nie rozstrzyga; nie ustalono też, czy `BrandMark` ma przyjąć obraz, SVG inline czy zostać komponentem z nową treścią.
5. **Wdrożenie nazwy:** `APP_NAME` w `.env` produkcyjnym vs przypięcie `SESSION_COOKIE`/`CACHE_PREFIX`/nazwy kopii Spatie przed zmianą; przebudowa obrazu dla `VITE_APP_NAME`. Nie sprawdzano, jakie wartości ma środowisko produkcyjne.
6. **Kolor strzałki:** istniejące `success`/`destructive` (kierunek = dobre/złe, co dla wydatku bywa mylące) czy nowy token; w obu przypadkach zapis w `design-system.md` i nowy ADR. Wspólny komponent strzałki dla Budżetu i Planu miesiąca nie istnieje.
7. **Dashboard szeroki ekran:** nie zmierzono rzeczywistego wyglądu przy np. 1920 px; badanie opiera się na kodzie CSS. Brak decyzji o `max-width` strony vs `clamp()` kolumny aside vs próg `2xl`.
8. **`PlanChangedHint` i `CategoryChip` nie są na liście `check:ui`** – plan może dodać je do listy razem ze zmianami.
9. **Sink `/dev/ui/welcome` i `/dev/ui/sidebar`** pokażą zmianę znaku/nazwy; nie sprawdzano, jakie stany obecnie zawierają wszystkie wymienione sinki poza tymi, które wynikały z raportów (nie otwierano plików sinków).
