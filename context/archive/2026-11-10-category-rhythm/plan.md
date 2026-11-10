# Rytm kategorii w propozycji planu — plan implementacji

## Overview

S-24 z roadmapy M-3 (FR-007, FR-009, FR-022): wydatkowa kategoria listy dostaje rytm (stała opłata, bieżąca, równomierna roczna) trzymany jako historia wpisów (rytm, miesiąc startu) zgodnie z ADR-0066. Migracja nadaje rytm z dzisiejszych flag `is_paced` / `month_item_kind`, użytkownik widzi go i poprawia w „Kategoriach”, propozycja planu miesiąca liczy kwotę z rytmu (stała opłata — ostatnia zapłacona kwota; równomierna roczna — plan ÷ 12 z odczytem „zostało X na Y mies.”), a wszyscy czytelnicy flag przechodzą obszarami na rytm. Na końcu flagi znikają z bazy migracją z odwracalnym `down()`. Nieregularna (stawka, „zebrane”, zebrane startowe) to S-26.

## Current State Analysis

- **Flagi.** Każda wydatkowa pozycja budżetu (`budget_lines`) ma `is_paced` (bool, domyślnie true; `database/migrations/2026_09_10_195354_add_is_paced_to_categories_table.php:12`) i `month_item_kind` (`App\Enums\MonthItemKind` bill|running, domyślnie running; `2026_11_02_090956_…:12`), rzutowane w `app/Models/BudgetLine.php:162-163`. Czyta je lub pisze 111 plików: pary kalkulatorów PHP/TS, propozycja, wiersze planu, powiadomienia, Budżet, dołączanie do budżetu, tworzenie budżetu, snapshot, fikstury i ~40 plików testów.
- **Rytmu nie ma w kodzie** (`grep -ri rhythm app database` pusty). Nie ma też w schemacie żadnej tabeli historii „od miesiąca”; miesiąc w bazie to rok budżetu + `tinyint month` (`month_plans`, `2026_11_02_091919_…:14-21`).
- **Propozycja** (`app/Support/MonthPlanProposal.php:172-193`) czyta tylko `is_paced`: wydatek jednorazowy → 0 (:174-176), inaczej kopia poprzedniego miesiąca (:182-183) albo średnia ADR-0033 (:204-213); styczeń czyta grudzień tej samej kategorii listy (:198-202). Rachunek i bieżąca proponują tak samo, a klasa nie czyta operacji (test limitu zapytań ≤ 7, `tests/Feature/MonthPlanProposalTest.php:307-327`). Test `test_one_off_category_is_proposed_as_zero` (:268) opisuje regułę, którą ADR-0066 zmienia.
- **Reguła rachunku** „plan, potem faktyczna po pierwszej dodatniej” jest w `ToSpendCalculator::itemMinorUnits` (`app/Support/ToSpendCalculator.php:69-81`, TS `resources/js/lib/to-spend-calculator.ts:36`); faktyczne kwoty miesiąca daje `MonthCategoryActuals::forMonths` (`app/Support/MonthCategoryActuals.php:27-60`, jedno zapytanie na budżet).
- **Edycja flag dziś:** okno kategorii na stronie Budżet (`resources/js/components/CategoryFormDialog.vue:229-266`, `CategoryController@store/update`, `CategoryValidationRules.php:78-102`) i przełącznik „rachunek / bieżąca” w wierszu planu (`MonthPlanKindCell.vue`, trasa `budgets/{budget}/categories/{category}/month-item-kind`, `routes/web.php:80`, `CategoryController::updateMonthItemKind` :71-76), który przepisuje rodzaj dla całego roku, także minionych miesięcy (`lang/pl/ui.php:433`).
- **„Kategorie”** (`CategoryController::index` → `CategoryListReading::forUser`, `app/Support/CategoryListReading.php:91-129`; okno `resources/js/components/CategoryListFormDialog.vue`, zapis `CategoryListController` → `CreateListCategory` / `UpdateListCategory`, reguły `CategoryValidationRules::listCategoryRules` :39-48) nie zna flag. Zmiana typu na przychód resetuje dziś flagi wszystkich pozycji (`UpdateListCategory.php:327-331`).
- **Wiersz planu** (`app/Support/MonthPlanItemRow.php:41-85`, typ `resources/js/types/month-plan.ts:22-44`): `month_item_kind`, `is_one_off`, `can_change_kind`, `available_limit_minor_units` (tylko jednorazowa, „Pozostały limit roczny”, `MonthPlanItemRow.vue:138-157`), `is_idle_one_off` (jednorazowa z kwotą 0 i bez operacji w miesiącu → zwinięta sekcja „Jednorazowe bez planu i wydatków”, `MonthPlanTable.vue:245-270`).
- **Snapshot** `BackupUserData::SNAPSHOT_VERSION = 8` (`app/Actions/BackupUserData.php:20`); flagi siedzą na wpisach `budget_lines` (:199-200). Wersje 6–8 to też formaty bez rytmu, więc mapowanie przy przywracaniu dotyczy v1–v8, nie „v1–6” z ADR-0066. v1–v5 idą przez zamrożone `LegacyCategoryConversion` (`RestoreUserData.php:450-512`), które flag nie dotyka.
- **Liczby kontrolne** (`app/Support/ControlNumbers.php:45-71`) liczy nowy kod na starym schemacie przed `migrate` i po nim (`scripts/deploy.sh:54-64`); `meta` jest pomijane w porównaniu. Nic w nich nie dotyczy flag.
- **Dane dev** (użytkownik 1): budżet 2026 w realizacji — 29 wydatków równomierna + bieżąca, 2 jednorazowa + bieżąca („Ubezpieczenie samochodu” 541 zł zapłacone w styczniu, „Rekolekcje” 2 380 zł zapłacone w marcu i lipcu); szkic 2027 tak samo; **zero rachunków**; zatwierdzony tylko listopad 2026 (obie jednorazowe z kwotą 0).
- **Czas.** Kontrolery biorą `CarbonImmutable::today('UTC')` i przekazują `$today` w dół (`MonthPlanController.php:53`, `DashboardController.php:33`); nie ma serwisu zegara.

## Desired End State

- Każda wydatkowa kategoria listy ma co najmniej jeden wpis rytmu, przychodowa żadnego. Po migracji rytm wynika z flag pozycji budżetów (tabela ADR-0066), kolejne identyczne wpisy są scalone; budżety, operacje, plany i każda kwota „do wydania” zatwierdzonego miesiąca są takie same co do grosza.
- W „Kategoriach” okno tworzenia i edycji wydatku ma pole „Rytm” (stała opłata | bieżąca | równomierna roczna) z podpowiedzią „Zmiana działa od <bieżący miesiąc>; minione miesiące bez zmian”, a wiersz listy pokazuje rytm bieżącego miesiąca. Zapis zastępuje wpisy od bieżącego miesiąca jednym wpisem; zapis tego samego rytmu nie dodaje wpisu.
- Propozycja planu liczy kwotę z rytmu miesiąca: stała opłata — ostatnia zapłacona kwota (ADR-0066 „Fixed Charge Proposal”), bieżąca — kopia poprzedniego miesiąca, równomierna roczna — floor(plan aktualny / 12), reszta w grudniu. Dla dev: „Rekolekcje” proponują w grudniu 2026 198,37 zł, „Ubezpieczenie samochodu” 45,12 zł.
- Wiersz planu pokazuje rytm jako etykietę (bez przełącznika); wiersz równomiernej rocznej w bieżącym i przyszłych miesiącach ma podtytuł „zostało X na Y mies.” (X = plan aktualny − wykonanie roku, Y = miesiące od bieżącego miesiąca kalendarzowego do grudnia), w bieżącym miesiącu dopisany do linii „W roku”; zwinięta sekcja nazywa się „Równomierne roczne bez planu i wydatków”.
- „Do wydania”, odchylenie, status, tempo, „W roku”, ponad plan, ostrzeżenia przy operacji i w Imporcie, „Pozostało w miesiącu”, kreska upływu roku, powiadomienia Dashboardu i strona Budżet czytają rytm miesiąca, którego dotyczą, zgodnie z tabelą ADR-0066.
- Kolumn `is_paced` i `month_item_kind` ani enuma `MonthItemKind` nie ma; `down()` migracji usuwającej odtwarza flagi z rytmu.
- `user:backup` pisze v9 z historią rytmu na kategorii; v1–v8 wczytują się z rytmem z flag tą samą regułą co migracja.

### Key Discoveries:

- Propozycja nie czyta operacji, a „ostatnia zapłacona kwota” ich wymaga; `MonthCategoryActuals::forMonths` daje jedno zapytanie na budżet — `app/Support/MonthPlanProposal.php:40-60`, `app/Support/MonthCategoryActuals.php:27-77`.
- `ToSpendCalculator::itemMinorUnits` zwraca przy płatności 100 i zwrocie 150 kwotę −50, a reguła propozycji stałej opłaty wymaga wtedy planu poprzedniego miesiąca — nie da się jej użyć wprost — `app/Support/ToSpendCalculator.php:69-81`.
- „Pozostało w miesiącu” liczy tylko TS (PHP `MonthRemainingCalculator` używają tylko testy), a `BudgetMonthExecutionIndex::paced*` nie mają wywołań — `app/Support/MonthRemainingCalculator.php:48-57`, `app/Support/BudgetMonthExecutionIndex.php:54-69`.
- Payloady wejść hand entry, Importu i ostrzeżeń „do wydania” niosą flagę raz na budżet, a rytm jest na miesiąc — `app/Support/HandEntryMonthRemainingInputs.php:58-67`, `app/Support/CsvImport/ImportMonthRemainingContext.php:54-104`, `app/Support/EntryToSpendInputs.php:63-67,130-144`.
- Wzorzec testu migracji na PostgreSQL z `migrate:rollback --path` — `tests/Feature/Migrations/CategoryListMigrationTest.php:20-49`; parzystość migracja ↔ restore — tamże :416.
- Sztywne listy wersji snapshotu — `app/Actions/RestoreUserData.php:32,97-127`.

## What We're NOT Doing

- **Nieregularna** (FR-010, FR-011): wartość rytmu, zebrane startowe, stawka, „zebrane”, reguła „do wydania” stawka + nadwyżka, wyjątek ADR-0062 dla nieregularnej — S-26. Tabela historii dostaje kolumnę zebranego startowego dopiero tam.
- **Zebrane przez 31 grudnia** (FR-012) — S-27; plan miesiąca w grupach (FR-008) — S-25; scalanie kategorii (FR-023) — S-21.
- **Zmiana rytmu poza „Kategoriami”**: przełącznik w planie miesiąca i pola flag w oknie Budżetu znikają bez zastępstwa (decyzja planu).
- **Widok historii rytmu** w „Kategoriach” (lista wpisów, edycja minionych miesięcy): wpisy minionych miesięcy zmienia tylko migracja.
- **„Zostało X na Y mies.” na Budżecie i Dashboardzie** oraz w minionych miesiącach planu.
- **Sekcja zwinięta dla innych rytmów** niż równomierna roczna.
- **Zmiana ADR-0066** poza poprawką „wersje 1 do 6” → „1 do 8” i odesłaniem do ADR-0072.

## Implementation Approach

Expand → backfill → przełączanie czytelników → drop (`.ai/rules/migrations.md`). Faza 1 zapisuje decyzje planu w ADR-0072, tworzy tabelę `category_rhythms`, enum `Rhythm`, zamrożoną klasę mapowania flag na rytm (wspólną dla migracji i restore) i jedną klasę odczytu „rytm kategorii w miesiącu” ładowaną jednym zapytaniem. Faza 2 daje zapis rytmu w „Kategoriach” i snapshot v9. Fazy 3–5 przełączają czytelników obszarami (propozycja i wiersz planu; odczyty miesiąca; odczyty roku, Pozostało w miesiącu i Budżet); para kalkulatorów PHP/TS zmienia się w jednym commicie (`.ai/rules/month-calculators.md`). Każda faza zostawia zielone testy, bo do fazy 6 flagi nadal są w bazie i nadal są pisane. Faza 6 usuwa piszących flagi, kolumny i enum oraz domyka dokumenty i próbę na kopii produkcji.

Każdy odczyt bierze rytm miesiąca, którego dotyczy: plan miesiąca i jego propozycja — oglądany miesiąc; „do wydania”, tempo, powiadomienia, kreska roku na Dashboardzie — bieżący miesiąc; strona Budżet — oglądany miesiąc (`BudgetViewedMonth`); hand entry i Import — miesiąc daty operacji; „Kategorie” — bieżący miesiąc.

## Critical Implementation Details

- **Reguła zapisu rytmu.** Jedna akcja dla tworzenia, edycji i zmiany typu: jeśli rytm bieżącego miesiąca (wg reguły odczytu) równa się zapisywanemu i kategoria nie ma wpisu ze startem po bieżącym miesiącu, nic się nie dzieje; inaczej (także przy tym samym rytmie, gdy istnieje przyszły wpis, np. styczeń 2027 z migracji szkicu o innych flagach — lista pokazuje tylko bieżący miesiąc, więc zapis musi znaczyć „od teraz tak”) usuwa wszystkie wpisy z miesiącem startu ≥ bieżący (także przyszłe, np. styczeń 2027 z migracji szkicu) i wstawia jeden wpis od bieżącego miesiąca. Kolejne identyczne wpisy nie są scalane (scala tylko migracja, ADR-0066). Zmiana typu na przychód usuwa wszystkie wpisy; zmiana na wydatek wstawia wpis od bieżącego miesiąca z wybranym rytmem (domyślnie bieżąca).
- **Odczyt.** Rytm miesiąca m to wpis z najpóźniejszym startem nie późniejszym niż m; miesiąc przed pierwszym wpisem ma rytm pierwszego wpisu. Wydatek bez żadnego wpisu (nie powinien istnieć, test inwariantu) czyta się jako bieżąca; przychód nie ma rytmu (`null`).
- **Ostatnia zapłacona kwota.** Dla stałej opłaty w miesiącu m: jeśli miesiąc m − 1 (styczeń: grudzień poprzedniego budżetu w realizacji, ta sama kategoria listy) ma dodatnią operację i faktyczna netto > 0, propozycja wpisuje tę faktyczną; w przeciwnym razie kwotę planu m − 1 (`source->amounts`), a bez źródła (miesiąc minionego bez planu albo brak pozycji) — średnią ADR-0033. Płatność w minionym niezatwierdzonym miesiącu wygrywa ze średnią (dosłownie ADR-0066). Faktyczne kwoty muszą wejść stałą liczbą zapytań (jedno na budżet), niezależnie od liczby miesięcy.
- **Kolejność w fazie 3.** Wiersz planu dostaje pole `rhythm`, ale `month_item_kind` i `is_one_off` zostają do fazy 4, bo czyta je podgląd TS „do wydania” i tempa (`MonthPlan.vue:295,317`); usuwa je faza 4 razem z parami kalkulatorów.
- **Odtworzenie flag w `down()`.** Dla każdej wydatkowej pozycji: rytm jej roku w bieżącym miesiącu (rok bieżący), w grudniu (rok miniony) albo w styczniu (rok przyszły) → stała opłata = (true, bill), bieżąca = (true, running), równomierna roczna = (false, running); przychód = (true, running). Odtworzenie jest stratne przy zmianie rytmu w połowie roku i dla jednorazowa + rachunek (wraca jako równomierna + rachunek); ADR-0072 to nazywa.

## Faza 1: ADR, historia rytmu i backfill

### Overview

Decyzje planu trafiają do ADR, powstaje tabela historii z modelem i enumem, zamrożone mapowanie flag na rytm, migracja wypełniająca historię i jedna klasa odczytu. Nikt jeszcze nie czyta rytmu poza testami.

### Changes Required:

#### 1. ADR-0072 i poprawka ADR-0066

**File**: `context/foundation/adr/0072-rhythm-edited-on-the-list-and-flags-dropped.md`, `context/foundation/adr/0066-category-rhythm-and-irregular-collected.md`

**Intent**: Zapisać decyzje tej rozmowy przed kodem (AGENTS.md) i poprawić zakres wersji snapshotu w ADR-0066.

**Contract**: ADR-0072: rytm zmienia się tylko w „Kategoriach” (tworzenie i edycja, trzy wartości do S-26), plan miesiąca i Budżet pokazują etykietę, przełącznik rodzaju w planie i pola flag w oknie Budżetu znikają (zmienia ADR-0033 w części o przełączniku i ADR-0062 nie dotyka); „zostało X na Y mies.” w bieżącym i przyszłych miesiącach planu, w propozycji i po zatwierdzeniu, jako podtytuł wiersza zamiast „Pozostały limit roczny”, w bieżącym miesiącu dopisany do linii „W roku”, X na dziś, Y od bieżącego miesiąca kalendarzowego; sekcja „Równomierne roczne bez planu i wydatków” z regułą jednorazowej przeniesioną na równomierną roczną; reguła zapisu i zmiany typu (Critical Implementation Details); kategoria wydatkowa bez pozycji budżetu dostaje w migracji bieżącą od miesiąca migracji; każdy odczyt bierze rytm miesiąca, którego dotyczy (Implementation Approach); stała opłata przy minionym niezatwierdzonym miesiącu źródłowym — płatność wygrywa, inaczej średnia; `one_off_over_limit` → `even_annual_over_limit`, a równomierna roczna nie podnosi `month_item_over_plan`; snapshot v9 i mapowanie v1–v8; flagi usunięte w S-24 z odwracalnym, stratnym `down()` (reguła odtworzenia); odrzucone: przełącznik rytmu w planie, brak kolumny rytmu w planie, odczyt tylko przy propozycji / tylko w bieżącym miesiącu, sekcja dla każdego rytmu, usunięcie sekcji, flagi zostawione do osobnej zmiany, `down()` rzucający wyjątek. ADR-0066: „A snapshot of version 1 to 6” → „version 1 to 8 (ADR-0072)”; ADR-0033 dostaje linię „Amended by ADR-0072”.

#### 2. Tabela, enum i model

**File**: `database/migrations/<ts>_create_category_rhythms_table.php`, `app/Enums/Rhythm.php`, `app/Models/CategoryRhythm.php`, `app/Models/Category.php`, `database/factories/CategoryRhythmFactory.php`

**Intent**: Historia rytmu na kategorii listy (ADR-0066 „Where the Rhythm lives”).

**Contract**: `category_rhythms`: `id`, `category_id` (FK → `categories`, cascade), `start_year` (smallint), `start_month` (tinyint 1–12), `rhythm` (string), timestamps; `unique(category_id, start_year, start_month)`. Enum `Rhythm`: `FixedCharge = 'fixed_charge'`, `Running = 'running'`, `EvenAnnual = 'even_annual'` (S-26 dopisze `Irregular`), bez `label()` — żaden enum w `app/Enums` jej nie ma, etykiety polskie („stała opłata”, „bieżąca”, „równomierna roczna”) są tylko w `lang/pl/ui.php`. `Category::rhythms()` (`hasMany`, sort po roku i miesiącu). `down()` usuwa tabelę.

#### 3. Zamrożone mapowanie flag na rytm

**File**: `app/Support/CategoryList/LegacyRhythmConversion.php`

**Intent**: Jedna reguła ADR-0066 („Migration and snapshot”) dla migracji i restore v1–v8, na tablicach, bez modeli i akcji (`.ai/rules/migrations.md`).

**Contract**: wejście: lista `{category_id, category_type, year, is_paced, month_item_kind}` (pozycje budżetów) oraz lista id wydatkowych kategorii i `(year, month)` „od kiedy” dla kategorii bez pozycji; wyjście: lista `{category_id, rhythm, start_year, start_month}`. Każda wydatkowa pozycja daje wpis od stycznia roku budżetu wg tabeli (`bill` → `fixed_charge` przy dowolnym `is_paced`; `running` + true → `running`; `running` + false → `even_annual`); wpisy kategorii posortowane po roku, kolejne identyczne scalone; przychód bez wpisu; wydatek bez pozycji → `running` od podanego miesiąca. Docblock jak w `LegacyCategoryConversion`: zmiana klasy zmienia znaczenie migracji, która już się wykonała.

#### 4. Migracja wypełniająca

**File**: `database/migrations/<ts>_backfill_category_rhythms.php`

**Intent**: Wypełnić historię z flag po wdrożeniu (expand → backfill).

**Contract**: `DB::table()` czyta pozycje z rokiem budżetu i typem kategorii, woła `LegacyRhythmConversion` z miesiącem migracji (`CarbonImmutable::today('UTC')`), wstawia wpisy partiami. Nie zmienia `budget_lines`, `budgets`, operacji ani planów. `down()` usuwa wszystkie wiersze `category_rhythms` (prawdziwe odwrócenie: flagi są nietknięte).

#### 5. Odczyt rytmu

**File**: `app/Support/CategoryRhythms.php`

**Intent**: Jedno miejsce z regułą odczytu ADR-0066, ładowane jednym zapytaniem dla zbioru kategorii, żeby czytelnicy w fazach 2–5 nie pytali w pętli (Sloppy).

**Contract**: `CategoryRhythms::forCategoryIds(iterable<int>)` ładuje wpisy i typ kategorii jednym zapytaniem; `rhythmIn(int $categoryId, int $year, int $month): ?Rhythm` (przychód → `null`, wydatek bez wpisów → `Running`) i `rhythmsInYear(int $categoryId, int $year): array<int, Rhythm>|null` (miesiąc 1–12 → rytm, dla payloadów TS w fazach 4–5; przychód → `null`).

#### 6. Liczby kontrolne

**File**: `app/Support/ControlNumbers.php`, `tests/Feature/ControlNumbersCommandTest.php`

**Intent**: Liczba kontrolna z ADR-0066 (pozycje jednorazowa + rachunek na kopii produkcji) i liczba wpisów rytmu, w `meta`, żeby nie dawały różnicy przed/po.

**Contract**: `meta.one_off_bill_lines` (gdy `budget_lines` ma obie kolumny flag, inaczej `null`) i `meta.category_rhythm_rows` (gdy tabela istnieje, inaczej `null`); `VERSION` bez zmiany, jeśli `differences()` dalej pomija `meta`.

#### 7. Testy

**File**: `tests/Unit/LegacyRhythmConversionTest.php`, `tests/Feature/Migrations/CategoryRhythmMigrationTest.php`, `tests/Feature/CategoryRhythmsTest.php`

**Intent**: Mapowanie i scalanie (dev: 29 → bieżąca, 2 → równomierna roczna, 2026 i 2027 scalone w jeden wpis od stycznia 2026; rachunek z dowolną flagą → stała opłata; rytm zmieniony między latami daje dwa wpisy), kategoria bez pozycji, przychód bez wpisu; migracja i `down()` na PostgreSQL z inwariantami (każda kwota budżetów, operacji i planów bez zmian, każda wydatkowa kategoria ma ≥ 1 wpis, przychodowa 0); reguła odczytu (miesiąc przed pierwszym wpisem, granica wpisu, wydatek bez wpisów).

**Contract**: wzorzec `tests/Feature/Migrations/CategoryListMigrationTest.php` (pomija się bez `pgsql`, `migrate:rollback --path` plików tej zmiany).

### Success Criteria:

#### Automated Verification:

- Testy mapowania i odczytu przechodzą: `php artisan test --compact tests/Unit/LegacyRhythmConversionTest.php tests/Feature/CategoryRhythmsTest.php`
- Migracja, inwarianty i `down()` przechodzą na PostgreSQL (komenda worktree z AGENTS.md): `… artisan test --compact tests/Feature/Migrations/CategoryRhythmMigrationTest.php`
- Liczby kontrolne na obu schematach przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/ControlNumbersCommandTest.php`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0072 opisuje decyzje z tej rozmowy, a ADR-0066 mówi o wersjach 1–8
- Na danych dev po `migrate` „Rekolekcje” i „Ubezpieczenie samochodu” mają jeden wpis równomierna roczna od stycznia 2026, pozostałe 29 wydatków bieżąca

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: Rytm w „Kategoriach” i snapshot v9

### Overview

Użytkownik widzi i poprawia rytm w „Kategoriach”; nowa kategoria dostaje rytm od bieżącego miesiąca; snapshot niesie historię, a starsze snapshoty dostają rytm z flag.

### Changes Required:

#### 1. Akcja zapisu rytmu

**File**: `app/Actions/ChangeCategoryRhythm.php`, `app/Actions/CreateListCategory.php`, `app/Actions/UpdateListCategory.php`, `app/Actions/CreateCategory.php` (tworzenie z Budżetu), `database/seeders/DemoHouseholdSeeder.php`, `database/factories/CategoryFactory.php`

**Intent**: Reguła zapisu z Critical Implementation Details w jednym miejscu, wołana przy tworzeniu (wybrany rytm, domyślnie bieżąca), edycji i zmianie typu.

**Contract**: `ChangeCategoryRhythm::handle(Category $category, ?Rhythm $rhythm, CarbonImmutable $today): void` w transakcji z `lockForUpdate` na kategorii; `null` dla przychodu usuwa wpisy. Kategoria tworzona ze strony Budżet (`CreateCategory`) i kategoria wydatkowa przywracana z archiwum nie zmieniają reguły: nowa — bieżąca od bieżącego miesiąca; przywracana zachowuje swoją historię. `DemoHouseholdSeeder` (wołany przez `DatabaseSeeder` i `SeedDemoHouseholdCommand`, tworzy kategorie poza akcjami, :267) zapisuje wydatkom wpis bieżącej przez `ChangeCategoryRhythm`; `CategoryFactory` dostaje `afterCreating` z wpisem bieżącej dla wydatku (stany rytmu z fazy 4 go zastępują), żeby inwariant „każdy wydatek ma ≥ 1 wpis” i restore v9 trzymały się także dla danych demo i testów.

#### 2. Walidacja i kontroler

**File**: `app/Concerns/CategoryValidationRules.php`, `app/Http/Requests/StoreListCategoryRequest.php`, `app/Http/Requests/UpdateListCategoryRequest.php`, `app/Http/Controllers/CategoryListController.php`

**Intent**: Pole `rhythm` w formularzu listy: wymagane dla wydatku, zabronione dla przychodu.

**Contract**: `rhythm`: `required_if:type,expense`, `prohibited_if:type,income`, `Rule::enum(Rhythm::class)`; kontroler podaje `CarbonImmutable::today('UTC')` do akcji.

#### 3. Propsy „Kategorii”

**File**: `app/Support/CategoryListReading.php`, `resources/js/types/categories.ts`, `resources/js/pages/Categories.vue`, `resources/js/lib/categories-fixtures.ts`

**Intent**: Wiersz kategorii niesie rytm bieżącego miesiąca, strona — miesiąc, od którego działa zmiana.

**Contract**: `CategoryListItem.rhythm: 'fixed_charge' | 'running' | 'even_annual' | null`; prop strony `rhythmChangeMonth: { year: number; month: number }`. Odczyt przez `CategoryRhythms` jednym zapytaniem (stała liczba zapytań czytania listy). Obie strony propa i `assertInertia` w jednym commicie (`.ai/rules/inertia-props.md`).

#### 4. Okno i wiersz listy

**File**: `resources/js/components/CategoryListFormDialog.vue`, `resources/js/components/CategoryListRow.vue`, `lang/pl/ui.php`, `resources/js/pages/dev/CategoriesKitchenSink.vue`, `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Pole „Rytm” po opisie, tylko dla wydatku, jako `SegmentedControl block name="rhythm"` w `FormField group` z podpowiedzią „Zmiana działa od <miesiąc rok>; minione miesiące bez zmian”; wiersz listy pokazuje rytm w podpisie.

**Contract**: wzorzec pola z `CategoryFormDialog.vue:250-266` i `.ai/rules/js-dialogs.md`; teksty `t.categories.rhythm_*` w `lang/pl/ui.php` („Rytm”, „stała opłata”, „bieżąca”, „równomierna roczna”, podpowiedź); stany okna (tworzenie wydatku, edycja, zmiana typu, błąd pola) w `DialogsKitchenSink`, wiersz z każdym rytmem w `CategoriesKitchenSink`.

#### 5. Snapshot v9

**File**: `app/Actions/BackupUserData.php`, `app/Actions/RestoreUserData.php`, `tests/Fixtures/snapshots/v8-category-list.json` (nowa, napisana ręcznie, z flagami jednorazowa + bieżąca i rachunek), `tests/Feature/UserSnapshotCommandTest.php`

**Intent**: FR-022: snapshot niesie historię rytmu; v1–v8 wczytują się z rytmem z flag (`.ai/rules/snapshot.md`).

**Contract**: `SNAPSHOT_VERSION = 9`; wpis kategorii dostaje `rhythms: list<{rhythm, start_year, start_month}>`; 9 dopisane do każdej listy wersji i do `LIST_FORMAT_VERSIONS`. Restore v9 wstawia wpisy i odrzuca niespójne dane (wydatek bez wpisu, przychód z wpisem, nieznany rytm, miesiąc poza 1–12, duplikat startu). Restore v1–v8 po odtworzeniu pozycji woła `LegacyRhythmConversion` z flag pozycji (v1–v5 po `LegacyCategoryConversion`), z miesiącem restore dla kategorii bez pozycji. Do fazy 6 v9 nadal pisze flagi na `budget_lines`. Testy: round trip v9 z historią po zmianie rytmu, round trip v9 użytkownika po `DemoHouseholdSeeder`, restore v5, v6 i v8 daje rytm z flag równy wynikowi migracji (parzystość jak `CategoryListMigrationTest.php:416`), odrzucenie niespójnego v9.

#### 6. Testy zapisu

**File**: `tests/Feature/CategoryRhythmWriteTest.php` (nowy), `tests/Feature/CategoriesPageTest.php`

**Intent**: Reguła zapisu: zmiana w listopadzie zastępuje wpisy od listopada i usuwa styczeń 2027; zapis tego samego rytmu nie dodaje wpisu; zapis tego samego rytmu przy przyszłym wpisie innego rytmu (bieżąca od 01.2026, równomierna roczna od 01.2027, zapis „bieżąca” w listopadzie) usuwa styczeń 2027 i zostawia wpis bieżącej od listopada; zmiana i powrót w tym samym miesiącu zostawia jeden wpis z listopada; nowa kategoria — wpis od bieżącego miesiąca; zmiana typu w obie strony; walidacja; prop `rhythm` i `rhythmChangeMonth`.

**Contract**: `travelTo('2026-11-10')`.

### Success Criteria:

#### Automated Verification:

- Testy zapisu rytmu i propsów „Kategorii” przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/CategoryRhythmWriteTest.php tests/Feature/CategoriesPageTest.php`
- Testy snapshotu przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/UserSnapshotCommandTest.php tests/Feature/Migrations/`
- E2E „Kategorii” przechodzi: `npx playwright test tests/e2e/categories.spec.ts`
- Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- W „Kategoriach” na dev zmiana rytmu „Czynsz” na stałą opłatę pokazuje podpowiedź „od listopada 2026”, a wiersz listy po zapisie mówi „stała opłata”
- `/dev/ui/categories` i kitchen sink okien pokazują pole „Rytm” we wszystkich stanach, także w trybie ciemnym

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 3: Propozycja z rytmu i wiersz planu

### Overview

Propozycja liczy kwotę z rytmu oglądanego miesiąca, a wiersz planu pokazuje rytm jako etykietę, „zostało X na Y mies.” i przemianowaną sekcję. Przełącznik rodzaju i jego trasa znikają.

### Changes Required:

#### 1. Propozycja

**File**: `app/Support/MonthPlanProposal.php`

**Intent**: Tabela ADR-0066, wiersz „Proposal”: stała opłata — ostatnia zapłacona kwota, bieżąca — kopia jak dziś, równomierna roczna — średnia ADR-0033 niezależnie od źródła; przychód bez zmian.

**Contract**: `proposedAmount` dostaje rytm pozycji w miesiącu (przez `CategoryRhythms`, ładowane w `forBudget` jednym zapytaniem) i faktyczne kwoty miesiąca źródłowego (`MonthCategoryActuals` jednym zapytaniem na budżet: bieżący i poprzedni w realizacji). Reguła stałej opłaty z Critical Implementation Details. Liczba zapytań ładowania pozostaje stała dla 12 odczytów (próg w teście podniesiony jawnie o te zapytania, nie zależny od miesięcy).

#### 2. Wiersz planu

**File**: `app/Support/MonthPlanItemRow.php`, `app/Support/MonthPlanJoinCandidates.php`, `app/Http/Controllers/MonthPlanController.php`, `resources/js/types/month-plan.ts`

**Intent**: Wiersz niesie rytm miesiąca i odczyt równomiernej rocznej; flagi zostają tylko dla podglądu TS do fazy 4.

**Contract**: nowe pola: `rhythm: Rhythm | null`, `even_annual_remaining: { available_limit_minor_units: number; months_left: number } | null` (równomierna roczna w bieżącym i przyszłych miesiącach; X = `CategoryExecutionMetrics::remainingMinorUnits(plan aktualny, wykonanie)`, Y = 12 − bieżący miesiąc kalendarzowy + 1 dla bieżącego roku, Y = 12 dla roku późniejszego niż bieżący — budżet 2027 może być w realizacji w listopadzie 2026, bo `StartBudgetExecution` sprawdza tylko `isDraft()` — a miesiące roku minionego dają `null`), `is_idle_even_annual` (równomierna roczna z kwotą 0 i bez operacji w miesiącu). Usunięte: `can_change_kind`, `available_limit_minor_units`, `is_idle_one_off`. `month_item_kind` i `is_one_off` zostają (faza 4). `assertInertia` w tym samym commicie.

#### 3. Widok

**File**: `resources/js/components/MonthPlanItemRow.vue`, `resources/js/components/MonthPlanTable.vue`, `resources/js/pages/MonthPlan.vue`, `resources/js/components/MonthPlanKindCell.vue` (usunięty; wiersz renderuje etykietę rytmu tekstem), `lang/pl/ui.php`

**Intent**: Kolumna rodzaju pokazuje etykietę rytmu (tekst, bez przełącznika); podtytuł równomiernej rocznej „zostało X na Y mies.” zamiast „Pozostały limit roczny”, w bieżącym miesiącu po linii „W roku” po „·” (`text-destructive` przy X < 0, jak dziś limit); badge „Jednorazowa” znika (etykieta rytmu go zastępuje); sekcja zwinięta filtruje `is_idle_even_annual` z napisem „Równomierne roczne bez planu i wydatków”; `kind_scope_note` i obsługa `change-kind` znikają.

**Contract**: kwoty przez `formatMoneyAmount` bez „zł” (ADR-0058); nowe teksty w `t.month_plan.*` (`rhythm_*`, `even_annual_remaining` z liczbą miesięcy w odmianie „mies.”, `idle_even_annuals`); usunięte klucze `kind_bill`, `kind_running`, `kind_scope_note`, `one_off_badge`, `available_limit`, `idle_one_offs` tam, gdzie nikt ich już nie czyta. Nowy lub usunięty komponent zmienia listę w `tests/Unit/MonthPlanHardcodedUiTest.php` i `scripts/check-ui-literals.mjs`.

#### 4. Usunięcie przełącznika rodzaju

**File**: `routes/web.php`, `app/Http/Controllers/CategoryController.php`, `app/Http/Requests/UpdateCategoryMonthItemKindRequest.php`, `tests/Feature/CategoryMonthItemKindTest.php`

**Intent**: Rytm zmienia się tylko w „Kategoriach” (ADR-0072).

**Contract**: trasa `categories.month-item-kind`, metoda `updateMonthItemKind` i request usunięte. W `CategoryMonthItemKindTest` przypadki trasy PATCH znikają razem z funkcją, której usunięcie użytkownik zatwierdził w planowaniu (ADR-0072); przypadki o odczytach rodzaju (To Spend, propozycja, kopiowanie przy nowym budżecie) przechodzą jako przypadki rytmu do testów faz 3–4 i 6, a plik znika, gdy nie zostanie w nim żaden przypadek.

#### 5. Kitchen sink i fikstury

**File**: `resources/js/lib/month-plan-fixtures.ts`, `resources/js/pages/dev/MonthPlanSink.vue`, `tests/Feature/DevMonthPlanSinkTest.php`

**Intent**: Stany: etykieta każdego rytmu, równomierna roczna z X > 0, X = 0 i X < 0 w bieżącym i przyszłym miesiącu, sekcja zwinięta równomiernych rocznych.

**Contract**: fikstury z nowymi polami; `idleOneOff` → `idleEvenAnnual`.

#### 6. Testy

**File**: `tests/Feature/MonthPlanProposalTest.php`, `tests/Feature/MonthPlanPageTest.php`

**Intent**: `test_one_off_category_is_proposed_as_zero` zmienia się, bo zmieniło się wymaganie (ADR-0066): równomierna roczna proponuje floor(plan / 12), grudzień resztę (dev: 2 380 → 198,33 / grudzień 198,37; 541 → 45,08 / 45,12). Nowe: stała opłata — płatność 172,15 przy planie 180 → 172,15; bez płatności → 180; płatność 100 i zwrot 150 → plan 180; dwie płatności → suma netto; styczeń z grudnia poprzedniego budżetu; miniony niezatwierdzony miesiąc z płatnością → płatność, bez płatności → średnia; rytm zmieniony w listopadzie działa w propozycji listopada i grudnia, nie w październiku; limit zapytań. Strona: pola `rhythm`, `even_annual_remaining` (listopad 2026: X 0, Y 2; grudzień też Y 2; budżet 2027 w realizacji oglądany w listopadzie 2026: Y 12), `is_idle_even_annual`, brak `can_change_kind`.

**Contract**: `travelTo('2026-11-10')` dla przypadków dev.

### Success Criteria:

#### Automated Verification:

- Testy propozycji i strony planu przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/MonthPlanProposalTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/DevMonthPlanSinkTest.php tests/Feature/ApproveMonthPlanTest.php`
- Test literałów UI planu przechodzi: `php artisan test --compact tests/Unit/MonthPlanHardcodedUiTest.php` i `npm run check:ui`
- E2E przechodzi: `npx playwright test`
- Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev grudzień 2026 proponuje „Rekolekcje” 198,37 i „Ubezpieczenie samochodu” 45,12 z podtytułem „zostało 0,00 na 2 mies.”; listopad (zatwierdzony) ma obie w sekcji „Równomierne roczne bez planu i wydatków”, a „do wydania” listopada jest takie jak przed zmianą
- Po zmianie „Czynsz” na stałą opłatę w „Kategoriach” i operacji 950,00 w listopadzie grudzień proponuje 950,00; potem „Czynsz” wraca na bieżącą, a operacja 950,00 jest usunięta, żeby „do wydania” listopada dało się porównać w 4.5
- `/dev/month-plan` pokazuje nowe stany w trybie jasnym i ciemnym, wiersz bez przełącznika rodzaju

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 4: Odczyty miesiąca z rytmu

### Overview

„Do wydania”, odchylenie i status, tempo, „W roku”, ponad plan oraz ostrzeżenia „do wydania” przy operacji i w Imporcie czytają rytm miesiąca. Pary PHP/TS zmieniają się razem.

### Changes Required:

#### 1. „Do wydania”

**File**: `app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts` (+ testy obu), `app/Support/MonthToSpend.php`, `resources/js/pages/MonthPlan.vue` (podgląd na żywo)

**Intent**: Reguła pozycji wg rytmu: stała opłata = dzisiejsza reguła rachunku, bieżąca i równomierna roczna = większa z planu i faktycznej; przychód bez zmian.

**Contract**: wejście pozycji `rhythm: Rhythm | null` zamiast `kind`; `MonthToSpend` czyta rytm miesiąca przez `CategoryRhythms`; podgląd TS czyta `row.rhythm`.

#### 2. Odchylenie, rozliczenie, status

**File**: `app/Support/MonthItemDeviation.php`, `resources/js/lib/month-item-deviation.ts`, `resources/js/lib/month-plan-status.ts` (+ testy), `app/Support/MonthPlanItemRow.php`, `app/Support/MonthPlanJoinCandidates.php`

**Intent**: Stała opłata dziedziczy regułę rachunku (odchylenie „—” do zapłaty, badge „Zapłacone / do zapłaty / niezapłacone”); ponad plan równomiernej rocznej = wykonanie roku > plan aktualny (ADR-0066), pozostałe rytmy jak dziś.

**Contract**: `isOverPlan` dostaje rytm oraz wykonanie roku i plan aktualny (pola wiersza, np. `year_execution_minor_units` / `current_plan_minor_units`, jeśli TS ich potrzebuje do podglądu); `isSettled` / `deviationMinorUnits` po rytmie.

#### 3. Tempo

**File**: `app/Support/MonthPaceCalculator.php`, `resources/js/lib/month-pace-calculator.ts`, `resources/js/lib/month-pace-preview.ts` (+ testy), `app/Support/MonthPace.php`

**Intent**: Pula tempa = rytm bieżąca (ADR-0066); wejście `is_one_off` znika.

**Contract**: wejście `{rhythm, plan, actual}`.

#### 4. „W roku … z …”

**File**: `app/Support/MonthYearComparison.php`, `tests/Unit/MonthYearComparisonTest.php`

**Intent**: Wykonanie przed miesiącem + kwota pozycji wg reguły „do wydania” rytmu.

**Contract**: przekazuje rytm do `ToSpendCalculator`.

#### 5. Powiadomienie „ponad plan” i ostrzeżenia przy wpisie

**File**: `app/Support/DashboardNotices.php` (`month_item_over_plan`), `app/Support/EntryToSpendInputs.php`, `resources/js/lib/entry-to-spend-warning.ts`, `resources/js/lib/import-to-spend-warnings.ts` (+ testy), `app/Support/CategoryList/CategoryChoiceOptions.php` (wpisy dołączające), `resources/js/lib/operation-dialogs-fixtures.ts` (`entryToSpend`, :142-145; stan ostrzeżenia dla stałej opłaty w kitchen sinku okien)

**Intent**: Równomierna roczna nie podnosi `month_item_over_plan` (sygnał roku daje faza 5, a do niej `one_off_over_limit` obejmuje ten sam zbiór na zmigrowanych danych); payload ostrzeżeń niesie rytm każdego miesiąca roku, bo rytm jest na miesiąc.

**Contract**: `entryToSpend.budgets[].categories[]`: `month_item_kind` → `rhythms: Record<number, Rhythm>` (miesiąc 1–12, przez `CategoryRhythms::rhythmsInYear`, jedno zapytanie), `null` dla przychodu; TS czyta rytm miesiąca daty operacji. `assertInertia` dla `entryToSpend` (Operacje i Import) w tym samym commicie.

#### 6. Wiersz planu bez flag

**File**: `app/Support/MonthPlanItemRow.php`, `resources/js/types/month-plan.ts`, `resources/js/lib/month-plan-fixtures.ts`

**Intent**: Usunąć `month_item_kind` i `is_one_off` z wiersza, gdy nikt ich nie czyta.

**Contract**: `assertInertia` potwierdza brak pól.

#### 7. Testy

**File**: `tests/Unit/ToSpendCalculatorTest.php`, `tests/Unit/MonthItemDeviationTest.php`, `tests/Unit/MonthPaceCalculatorTest.php`, `tests/Feature/MonthToSpendTest.php`, `tests/Feature/MonthPaceTest.php`, `tests/Feature/EntryToSpendInputsTest.php`, `tests/Feature/DashboardTest.php`, testy TS obok plików

**Intent**: Parametry testów przechodzą z flag na rytm bez zmiany oczekiwanych liczb (czyste mapowanie); nowe przypadki tylko dla zmian zachowania: równomierna roczna ponad plan wobec roku (miesiąc ponad plan, rok w planie → bez badge'a; rok ponad plan → badge), brak `month_item_over_plan` dla równomiernej rocznej, rytm zmieniony w listopadzie zmienia regułę „do wydania” listopada, nie października.

**Contract**: fabryka stanów rytmu (np. `CategoryRhythmFactory` / stan `Category::factory()->rhythm(Rhythm::FixedCharge)`) zamiast `->bill()` / `->oneOff()` tam, gdzie test czyta rytm.

### Success Criteria:

#### Automated Verification:

- Testy jednostkowe par przechodzą: `php artisan test --compact tests/Unit/ToSpendCalculatorTest.php tests/Unit/MonthItemDeviationTest.php tests/Unit/MonthPaceCalculatorTest.php tests/Unit/MonthYearComparisonTest.php` i `npm test`
- Testy odczytów miesiąca przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/MonthToSpendTest.php tests/Feature/MonthPaceTest.php tests/Feature/EntryToSpendInputsTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/DashboardTest.php tests/Feature/MonthSummaryTest.php`
- E2E przechodzi: `npx playwright test`
- Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev „do wydania” listopada 2026 na Dashboardzie i w planie miesiąca jest takie jak przed zmianą (zapisz wartość przed fazą 1)
- Wydatek przy „Czynsz” jako stałej opłacie w oknie operacji pokazuje ostrzeżenie „do wydania” jak dla rachunku

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 5: Odczyty roku, „Pozostało w miesiącu” i strona Budżet

### Overview

Powiadomienie ponad limitem roku, kreska upływu roku, „Pozostało w miesiącu” przy wpisie ręcznym i w Imporcie oraz strona Budżet czytają rytm; okno kategorii Budżetu traci pola flag.

### Changes Required:

#### 1. Powiadomienie roku

**File**: `app/Support/DashboardNotices.php`, `resources/js/types/dashboard.ts`, `resources/js/lib/dashboard-notice-presentation.ts`, `resources/js/components/DashboardNotices.vue`, `resources/js/pages/dev/dashboard-fixtures.ts` (+ testy)

**Intent**: `one_off_over_limit` → `even_annual_over_limit`: aktywna kategoria z rytmem równomierna roczna w bieżącym miesiącu i wykonaniem roku > plan aktualny (ADR-0050 w brzmieniu ADR-0066); etykieta „Ponad limit” bez zmian.

**Contract**: rodzaj powiadomienia przemianowany po obu stronach w jednym commicie, `assertInertia` na `notices`.

#### 2. Kreska upływu roku i etykieta rytmu

**File**: `resources/js/lib/dashboard-categories.ts` (+ test), `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/BudgetController.php`, `resources/js/components/CategoryMeterRow.vue`, `resources/js/components/BudgetCategoryRow.vue`, `resources/js/components/BudgetCategoryList.vue`, `resources/js/pages/dev/BudgetKitchenSink.vue`

**Intent**: Kreska na przychodzie i każdym wydatku o rytmie innym niż nieregularna (ADR-0043 w brzmieniu ADR-0066), więc równomierna roczna ją dostaje; wiersz Budżetu pokazuje etykietę rytmu oglądanego miesiąca zamiast „rachunek / bieżąca”.

**Contract**: `expenseCategories[].is_paced` (Dashboard i Budżet) i `month_item_kind` (Budżet) → `rhythm`; Dashboard — bieżący miesiąc, Budżet — `BudgetViewedMonth`; `showsYearElapsedMark(type, rhythm)`. Stan kitchen sinka „jednorazowa bez kreski” → „równomierna roczna z kreską”.

#### 3. Okno kategorii Budżetu

**File**: `resources/js/components/CategoryFormDialog.vue`, `app/Http/Requests/StoreCategoryRequest.php`, `app/Http/Requests/UpdateCategoryRequest.php`, `app/Concerns/CategoryValidationRules.php`, `app/Actions/CreateCategory.php`, `app/Actions/UpdateCategory.php`, `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Rytm nie jest ustawieniem roku (ADR-0072): pola „Kategoria równomierna” i „Rachunek / Bieżąca” znikają; nowa kategoria z Budżetu dostaje bieżącą od bieżącego miesiąca (faza 2).

**Contract**: reguły `is_paced` / `month_item_kind` i `withPacedFlagValidation` / `withMonthItemKindValidation` usunięte; akcje do fazy 6 zapisują na pozycji domyślne flagi.

#### 4. „Pozostało w miesiącu”

**File**: `app/Support/MonthRemainingCalculator.php`, `resources/js/lib/month-remaining-calculator.ts` (+ testy), `app/Support/HandEntryMonthRemainingInputs.php`, `resources/js/lib/hand-entry-month-remaining-preview.ts`, `app/Support/CsvImport/ImportMonthRemainingContext.php`, `app/Support/CsvImport/ImportCategoryOptions.php`, `resources/js/lib/import-month-remaining-warnings.ts`, `resources/js/lib/import-preview-rows.ts`, `resources/js/components/ExpenseFormDialog.vue`, `resources/js/components/ImportPreviewRow.vue`, `app/Support/BudgetMonthExecutionIndex.php`, `lang/pl/ui.php`, `resources/js/lib/operation-dialogs-fixtures.ts` (:193), `resources/js/lib/import-preview-fixtures.ts` (:186-223; stany: stała opłata w puli, równomierna roczna bez „Pozostało w miesiącu”)

**Intent**: Pula = stała opłata + bieżąca w miesiącu daty operacji (ADR-0024 w brzmieniu ADR-0066); kategoria równomierna roczna nie ma „Pozostało w miesiącu”. Martwe `BudgetMonthExecutionIndex::paced*` znikają. Tekst „…puli kategorii równomiernych…” (`ui.php:841`) mówi o stałych opłatach i bieżących.

**Contract**: payloady `handEntryMonthRemaining.budgets[].categories[]`, `monthRemainingContext` i `expenseCategories[]` (Import): `is_paced` → `rhythms: Record<number, Rhythm>`; funkcje `paced*` w parze PHP/TS przemianowane na pulę („pool”) i zmienione w jednym commicie; `assertInertia` dla Operacji i Importu.

#### 5. Testy

**File**: `tests/Feature/DashboardTest.php`, `tests/Feature/BudgetTest.php`, `tests/Feature/CategoryListBudgetPageTest.php`, `tests/Feature/CategoryPacedFlagTest.php`, `tests/Feature/HandEntryMonthRemainingTest.php`, `tests/Feature/CsvImportTest.php`, `tests/Feature/ExpenseTest.php`, `tests/Unit/MonthRemainingCalculatorTest.php`, testy TS

**Intent**: Parametry przechodzą na rytm; nowe przypadki dla zmian zachowania: jednorazowa + rachunek (stała opłata) wchodzi do puli „Pozostało w miesiącu” i traci `even_annual_over_limit`; równomierna roczna dostaje kreskę; pula liczy rytm miesiąca operacji (rytm zmieniony w listopadzie, operacja w październiku). `CategoryPacedFlagTest` — przypadki o odczytach przechodzą jako przypadki rytmu do testów faz 4–5, przypadki o polu w oknie Budżetu zastępuje jeden test, że pola flag nie ma w żądaniu (usunięcie pola zatwierdził użytkownik, ADR-0072), a przypadki snapshotu (:220, :247) przechodzą do `UserSnapshotCommandTest`; plik znika, gdy nie zostanie w nim żaden przypadek.

**Contract**: jak w fazie 4.

### Success Criteria:

#### Automated Verification:

- Testy odczytów roku, Budżetu i „Pozostało w miesiącu” przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/DashboardTest.php tests/Feature/BudgetTest.php tests/Feature/CategoryListBudgetPageTest.php tests/Feature/HandEntryMonthRemainingTest.php tests/Feature/CsvImportTest.php tests/Feature/ExpenseTest.php tests/Unit/MonthRemainingCalculatorTest.php`
- Testy TS przechodzą: `npm test`
- E2E przechodzi: `npx playwright test`
- Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev Dashboard i Budżet 2026 pokazują kreskę upływu roku przy „Rekolekcje” i „Ubezpieczenie samochodu”, wiersze Budżetu mają etykiety rytmu, okno kategorii Budżetu nie ma pól flag
- `/dev/kitchen-sink/budget` i `/dev/ui/dashboard` pokazują nowe stany w trybie jasnym i ciemnym

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 6: Usunięcie flag, dokumenty i próba na kopii produkcji

### Overview

Nikt nie czyta flag; znikają ich zapisy, kolumny, enum i stany fabryki. Słownik i roadmapa opisują stan po S-24, a wdrożenie jest przećwiczone na kopii danych produkcyjnych.

### Changes Required:

#### 1. Zapisy flag

**File**: `app/Actions/CreateBudget.php`, `app/Actions/JoinDraftBudgets.php`, `app/Actions/JoinBudgetAtFirstUse.php`, `app/Actions/CreateListCategory.php`, `app/Actions/RestoreCategory.php`, `app/Actions/UpdateListCategory.php`, `app/Actions/CreateCategory.php`, `app/Actions/UpdateCategory.php`, `app/Support/CategoryList/CategoryJoinFlags.php` (usunięty), `app/Support/CategoryList/CategoryChoiceOptions.php`, `app/Support/MonthPlanJoinCandidates.php`

**Intent**: Nowy budżet i dołączanie do budżetu przestają kopiować flagi (ADR-0033, ADR-0068 w brzmieniu ADR-0066); sygnatury bez parametrów flag.

**Contract**: `JoinDraftBudgets::handle(Category, ?int $exceptBudgetId)`; `CategoryJoinFlags` znika razem z wywołaniami.

#### 2. Migracja usuwająca kolumny

**File**: `database/migrations/<ts>_drop_rhythm_flags_from_budget_lines.php`, `tests/Feature/Migrations/CategoryRhythmMigrationTest.php`

**Intent**: Drop po przełączeniu wszystkich czytelników; `down()` odtwarza flagi z rytmu (reguła w Critical Implementation Details) i ma test.

**Contract**: `up()` usuwa `is_paced` i `month_item_kind`; `down()` dodaje je z dawnymi domyślnymi wartościami i wypełnia przez `DB::table()` z odwrotnego mapowania. Test: `down()` na danych dev daje 29 × (true, running) i 2 × (false, running) w 2026 i 2027; stała opłata → (true, bill); rytm zmieniony w listopadzie bieżącego roku → flaga wg listopada.

#### 3. Model, enum, fabryki, snapshot

**File**: `app/Models/BudgetLine.php`, `app/Enums/MonthItemKind.php` (usunięty), `database/factories/BudgetLineFactory.php`, `app/Actions/BackupUserData.php`, `app/Actions/RestoreUserData.php`, `tests/**` (pozostali użytkownicy `->bill()` / `->oneOff()`)

**Intent**: Usunąć flagi z modelu i fabryki (stany `bill()` / `oneOff()` zastąpione stanami rytmu na kategorii); v9 przestaje pisać flagi na `budget_lines` (v9 nie było jeszcze wydane, więc bez nowej wersji), restore v9 ich nie czyta, restore v1–v8 dalej mapuje je na rytm.

**Contract**: `RestoreUserData::restoredCategoryIsPaced` / `restoredCategoryMonthItemKind` zostają tylko jako wejście `LegacyRhythmConversion` dla v1–v8.

#### 4. Dokumenty

**File**: `context/foundation/glossary.md`, `.ai/rules/categories.md`, `.ai/rules/migrations.md`, `.ai/rules/snapshot.md` (akapit o v9: `rhythms` na kategorii, restore v1–v8 przez `LegacyRhythmConversion`, 9 w `LIST_FORMAT_VERSIONS`, fikstura `v8-category-list.json`), `context/foundation/roadmap.md` (tylko gdy `/10x-implement` tego nie robi), `context/foundation/test-plan.md` (jeśli wymienia flagi)

**Intent**: Słownik opisuje stan po S-24: wpisy „Paced Category”, „One-off Category”, „Month Item Kind” jako historyczne (usunięte w S-24, mapowanie migracji), bez „until S-24” w „Budget Line”, „Category”, „Rhythm”; reguły w `.ai/rules` bez odwołań do flag jako bieżących; przykład expand/backfill/drop w `migrations.md` w czasie przeszłym. Po zmianie reguł `php artisan boost:index-rules`.

**Contract**: zmiana brzmienia, nie reguły (reguły ustaliły ADR-0066 i ADR-0072).

#### 5. Dane dev i próba na kopii produkcji

**File**: brak zmian w kodzie; wynik w `context/changes/category-rhythm/reviews/production-rehearsal.md`

**Intent**: Sprawdzić wdrożenie na prawdziwych danych przed produkcją (`.ai/rules/migrations.md`).

**Contract**: na kopii danych produkcyjnych: zapis „do wydania” każdego zatwierdzonego miesiąca (strona planu, kod z `development`), potem przebieg jak `scripts/deploy.sh` (kontrola przed, `migrate`, kontrola po z `--compare` bez różnic), `meta.one_off_bill_lines` przed migracją, `meta.category_rhythm_rows` po, „do wydania” każdego zatwierdzonego miesiąca bez zmian, `migrate:rollback` migracji S-24 i ponowne liczby kontrolne bez różnic.

### Success Criteria:

#### Automated Verification:

- Migracja usuwająca, odtworzenie flag w `down()` i inwarianty przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/Migrations/`
- Nie ma czytelników flag: `grep -rnE "is_paced|month_item_kind|MonthItemKind|isPaced|monthItemKind" app resources/js routes database/factories database/seeders` zwraca tylko `app/Actions/RestoreUserData.php` (wejście v1–v8), `app/Support/CategoryList/LegacyRhythmConversion.php` (wejście mapowania) i `app/Support/ControlNumbers.php` (`meta.one_off_bill_lines`, gdy kolumny istnieją); migracje leżą poza przeszukiwanymi katalogami
- Pełna bramka na PostgreSQL (komenda worktree z AGENTS.md): `… --entrypoint composer sail-8.5/app ci:check`
- `vendor/bin/sloppy ci` i `npx playwright test` przechodzą

#### Manual Verification:

- Słownik, ADR-0072 i reguły opisują stan po S-24 bez sprzeczności
- Próba na kopii produkcji: liczby kontrolne bez różnic po `migrate` i po `migrate:rollback`, „do wydania” zatwierdzonych miesięcy bez zmian, liczba pozycji jednorazowa + rachunek zapisana w `reviews/production-rehearsal.md`

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed zamknięciem zmiany.

---

## Testing Strategy

### Unit Tests:

- `LegacyRhythmConversion`: każdy wiersz tabeli mapowania, scalanie identycznych lat, zmiana między latami, kategoria bez pozycji, przychód.
- `CategoryRhythms`: miesiąc przed pierwszym wpisem, granica wpisu, wydatek bez wpisów.
- Pary kalkulatorów PHP/TS z tymi samymi przypadkami po obu stronach (`.ai/rules/month-calculators.md`): „do wydania”, odchylenie i ponad plan (równomierna roczna wobec roku), tempo, „Pozostało w miesiącu”.

### Integration Tests:

- Migracja i `down()` każdej z trzech migracji na PostgreSQL, z kwotami co do grosza.
- Propozycja: ostatnia zapłacona kwota (z ADR-0066: 172,15 / 180 / netto −50 → plan / dwie płatności), średnia równomiernej rocznej z dev, styczeń z grudnia, limit zapytań.
- Zapis rytmu: zastąpienie od bieżącego miesiąca, ten sam rytm, zmiana typu.
- Snapshot: round trip v9, parzystość restore v5/v6/v8 z migracją, odrzucenie niespójnego v9.
- `assertInertia` dla każdego zmienionego propa („Kategorie”, plan miesiąca, Dashboard, Budżet, Operacje, Import).

### Manual Testing Steps:

1. Przed fazą 1 zapisz „do wydania” listopada 2026 i kwoty Budżetu 2026 na dev.
2. Po fazie 2 zmień „Czynsz” na stałą opłatę w „Kategoriach”; sprawdź podpowiedź „od listopada 2026”.
3. Po fazie 3 obejrzyj grudzień 2026: 198,37 / 45,12, „zostało 0,00 na 2 mies.”, Czynsz proponuje kwotę zapłaconą w listopadzie. Potem przywróć „Czynsz” na bieżącą i usuń operację z listopada — oba kroki zmieniają „do wydania” listopada, które porównuje punkt 4.
4. Po fazach 4–5 porównaj „do wydania” listopada i kwoty Budżetu z punktu 1; sprawdź kreskę przy równomiernych rocznych.
5. Po fazie 6 przeprowadź próbę na kopii produkcji.

## Performance Considerations

`CategoryRhythms` ładuje historię jednym zapytaniem na zbiór kategorii; propozycja, „Kategorie”, payloady Operacji i Importu zachowują stałą liczbę zapytań (testy limitu zapytań tam, gdzie już są). Faktyczne kwoty dla stałej opłaty idą przez `MonthCategoryActuals::forMonths`, jedno zapytanie na budżet.

## Migration Notes

Trzy migracje w jednym wdrożeniu: tabela, wypełnienie, usunięcie flag. `scripts/deploy.sh` robi `user:backup` (v8, stary kod) i `backup:run` przed `migrate`, a liczby kontrolne przed i po (S-24 zmienia tylko `meta`, które porównanie pomija). Wycofanie po wdrożeniu bez utraty nowych operacji: `php artisan down`, `migrate:rollback` trzech migracji S-24 (flagi wracają z rytmu, reguła odtworzenia w ADR-0072, stratna przy zmianie rytmu w połowie roku i dla jednorazowa + rachunek; potem tabela historii znika), cofnięcie kodu (`git pull --ff-only` poprzedniej wersji i `deploy.sh`). Snapshot v9 nie wczyta się w starym kodzie; zapasem są snapshoty v8 sprzed wdrożenia (gubią operacje od wdrożenia).

## References

- Roadmapa: `context/foundation/roadmap.md` (S-24)
- PRD: `context/foundation/prd-v3.md` (FR-007, FR-009, FR-022)
- ADR-0066: `context/foundation/adr/0066-category-rhythm-and-irregular-collected.md`; ADR-0033, ADR-0024, ADR-0043, ADR-0050, ADR-0062, ADR-0063, ADR-0068
- Słownik: `context/foundation/glossary.md` („Rhythm”, „Paced Category”, „One-off Category”, „Month Item Kind”, „To Spend”, „Month Remaining”, „Year after the Month”)
- Poprzednia zmiana: `context/archive/2026-11-09-rhythm-and-irregular-decisions/` (plan i przeglądy, lista czytelników F7)
- Reguły: `.ai/rules/migrations.md`, `snapshot.md`, `categories.md`, `month-calculators.md`, `js-month-plan.md`, `js-dialogs.md`, `js.md`, `inertia-props.md`, `wayfinder.md`, `sloppy.md`
- Wzorce: `tests/Feature/Migrations/CategoryListMigrationTest.php`, `app/Support/CategoryList/LegacyCategoryConversion.php`, `app/Support/MonthCategoryActuals.php`, `resources/js/components/CategoryFormDialog.vue:250-266`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR, historia rytmu i backfill

#### Automated

- [x] 1.1 Testy mapowania i odczytu przechodzą — e261666
- [x] 1.2 Migracja, inwarianty i `down()` przechodzą na PostgreSQL — e261666
- [x] 1.3 Liczby kontrolne na obu schematach przechodzą na PostgreSQL — e261666
- [x] 1.4 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — e261666

#### Manual

- [x] 1.5 ADR-0072 opisuje decyzje z tej rozmowy, a ADR-0066 mówi o wersjach 1–8 — e261666
- [x] 1.6 Na danych dev po `migrate` równomierne roczne i bieżące mają poprawne wpisy — e261666

### Phase 2: Rytm w „Kategoriach” i snapshot v9

#### Automated

- [x] 2.1 Testy zapisu rytmu i propsów „Kategorii” przechodzą na PostgreSQL — f83b7be
- [x] 2.2 Testy snapshotu przechodzą na PostgreSQL — f83b7be
- [x] 2.3 E2E „Kategorii” przechodzi — f83b7be
- [x] 2.4 Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci` — f83b7be

#### Manual

- [x] 2.5 Zmiana rytmu „Czynsz” w „Kategoriach” pokazuje podpowiedź i etykietę — f83b7be
- [x] 2.6 Kitchen sinki pokazują pole „Rytm” we wszystkich stanach — f83b7be

### Phase 3: Propozycja z rytmu i wiersz planu

#### Automated

- [x] 3.1 Testy propozycji i strony planu przechodzą na PostgreSQL — 8108d17
- [x] 3.2 Test literałów UI planu przechodzi — 8108d17
- [x] 3.3 E2E przechodzi
- [x] 3.4 Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci` — 8108d17

#### Manual

- [x] 3.5 Grudzień 2026 na dev proponuje kwoty z rytmu, a listopad się nie zmienia — 8108d17
- [x] 3.6 Stała opłata proponuje kwotę zapłaconą w poprzednim miesiącu — 8108d17
- [x] 3.7 `/dev/month-plan` pokazuje nowe stany — 8108d17

### Phase 4: Odczyty miesiąca z rytmu

#### Automated

- [x] 4.1 Testy jednostkowe par przechodzą — 2724382
- [x] 4.2 Testy odczytów miesiąca przechodzą na PostgreSQL — 2724382
- [x] 4.3 E2E przechodzi — 2724382
- [x] 4.4 Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci` — 2724382

#### Manual

- [x] 4.5 „Do wydania” listopada 2026 na dev bez zmian — 2724382
- [x] 4.6 Ostrzeżenie „do wydania” dla stałej opłaty działa jak dla rachunku — 2724382

### Phase 5: Odczyty roku, „Pozostało w miesiącu” i strona Budżet

#### Automated

- [x] 5.1 Testy odczytów roku, Budżetu i „Pozostało w miesiącu” przechodzą na PostgreSQL — e5dd783
- [x] 5.2 Testy TS przechodzą — e5dd783
- [x] 5.3 E2E przechodzi — e5dd783
- [x] 5.4 Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci` — e5dd783

#### Manual

- [x] 5.5 Dashboard i Budżet pokazują kreskę i etykiety rytmu, okno Budżetu bez flag — e5dd783
- [x] 5.6 Kitchen sinki Budżetu i Dashboardu pokazują nowe stany — e5dd783

### Phase 6: Usunięcie flag, dokumenty i próba na kopii produkcji

#### Automated

- [x] 6.1 Migracja usuwająca, odtworzenie flag w `down()` i inwarianty przechodzą na PostgreSQL — ddbb516
- [x] 6.2 Nie ma czytelników flag — ddbb516
- [x] 6.3 Pełna bramka na PostgreSQL — ddbb516
- [x] 6.4 `vendor/bin/sloppy ci` i `npx playwright test` przechodzą — ddbb516

#### Manual

- [x] 6.5 Słownik, ADR-0072 i reguły opisują stan po S-24 bez sprzeczności — ddbb516
- [x] 6.6 Próba na kopii produkcji bez różnic — ddbb516
