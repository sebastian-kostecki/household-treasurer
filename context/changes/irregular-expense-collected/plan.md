# Wydatek nieregularny i „zebrane” — plan implementacji

## Overview

S-26 z roadmapy M-3 (US-02, FR-010, FR-011, FR-013, FR-022): wydatkowa kategoria listy dostaje czwarty rytm „nieregularna” z kwotą „zebrane startowe”. W planie miesiąca jej kwota to stawka, a wiersz pokazuje „zebrane”; rachunek zużywa zebrane, a „do wydania” obniża stawka plus nadwyżka rachunku ponad zebrane. Reguły są w ADR-0066; ten plan dodaje ADR-0073 z rozstrzygnięciami przypadków brzegowych F9, miejscem zapisu zebranego startowego, wyglądem wiersza, zasięgiem ostrzeżeń i przejściem łańcucha przez 31 grudnia. „Odkładam / dobieram” (FR-013) nie zmienia się.

## Current State Analysis

- **Rytm.** `category_rhythms` ma `category_id`, `start_year`, `start_month`, `rhythm` i unikat na trzech pierwszych (`database/migrations/2026_11_10_120000_create_category_rhythms_table.php:16-25`); kolumny na kwotę nie ma. Enum `App\Enums\Rhythm` ma trzy wartości (`app/Enums/Rhythm.php:5-10`), typ TS jest w `resources/js/types/rhythm.ts:6`. `CategoryRhythms` ładuje wpisy jednym zapytaniem i zna tylko `rhythm` (`app/Support/CategoryRhythms.php:60-124`).
- **Zapis rytmu.** Jedyną drogą jest `ChangeCategoryRhythm::handle(Category, ?Rhythm, today)` (`app/Actions/ChangeCategoryRhythm.php:27-69`): ten sam rytm bez późniejszego wpisu nic nie zmienia (:47-53), inaczej usuwa wpisy od bieżącego miesiąca i wstawia jeden. Wołają ją `CreateListCategory.php:57-62` i `UpdateListCategory::writeRhythm` (`app/Actions/UpdateListCategory.php:86-93`); walidacja to `Rule::enum(Rhythm::class)` w `CategoryValidationRules.php:44`, więc nowa wartość enuma byłaby przyjęta od razu.
- **„Kategorie”.** `CategoryListReading::forUser` daje na kategorię tylko `rhythm` bieżącego miesiąca (`app/Support/CategoryListReading.php:54-66`); okno `resources/js/components/CategoryListFormDialog.vue` ma trzy opcje w `SegmentedControl` (:75-79, :200-218) i nie ma pola kwoty. Wzorzec pola kwoty w oknie to `CategoryFormDialog.vue:183-212` (`FormField money`, `parseMoneyInputToMinorUnits`, ukryty input z minor units).
- **„Do wydania”.** Reguła pozycji jest w `ToSpendCalculator::itemMinorUnits` (`app/Support/ToSpendCalculator.php:72-84`, kopia `resources/js/lib/to-spend-calculator.ts:30-48`); pozycja niesie tylko plan, wykonanie i flagę pierwszej dodatniej kwoty. Wejścia dla planu miesiąca, Dashboardu i powiadomień buduje `MonthToSpend` (`app/Support/MonthToSpend.php:74-110`). Z tej samej reguły korzysta linia „W roku” (`app/Support/MonthYearComparison.php:29-42`).
- **Odchylenie i ponad plan.** `MonthItemDeviation` (`app/Support/MonthItemDeviation.php:21-70`, kopia `resources/js/lib/month-item-deviation.ts:15-49`); status wiersza w `resources/js/lib/month-plan-status.ts:32-65`.
- **Propozycja.** `MonthPlanProposal::proposedAmount` (`app/Support/MonthPlanProposal.php:198-229`) wpisuje równomiernej rocznej `averageFromAnnual` (prywatne, :286-295). Miniony miesiąc bez planu ma stan `none` (:157-158), więc stawki minionych niezatwierdzonych miesięcy nie da się dziś odczytać. Klasa ładuje tylko bieżący i poprzedni budżet w realizacji (:52-57); test limitu zapytań ≤ 10 (`tests/Feature/MonthPlanProposalTest.php:420-445`).
- **Wykonanie po miesiącach.** `MonthCategoryActuals::forMonths` (`app/Support/MonthCategoryActuals.php:27-60`) i `BudgetMonthExecutionIndex` czytają jeden budżet naraz; żadna klasa nie czyta wykonania kategorii przez kilka lat.
- **Wiersz planu.** `MonthPlanItemRow::forLine` (`app/Support/MonthPlanItemRow.php:42-90`), typ `resources/js/types/month-plan.ts:22-52`, widok `resources/js/components/MonthPlanItemRow.vue` (etykieta rytmu :71-82 z `default: ''`, podpis :141-170). Podsumowanie miesiąca (`MonthSummaryCard.vue`) ma tylko sumy per typ; wiersze kategorii minionego miesiąca to ten sam wiersz planu w stanie zamkniętym.
- **Podnoszenie planu do wydatków** (ADR-0062) jest tylko po stronie klienta i nie patrzy na rytm (`resources/js/lib/month-plan-match-actual.ts:11-62`).
- **Powiadomienia.** `DashboardNotices` podnosi `month_item_over_plan` z kwotą plan − wykonanie (`app/Support/DashboardNotices.php:122-169`, kwota :153).
- **Ostrzeżenia przy wpisie i w Imporcie** liczy przeglądarka z `EntryToSpendInputs` (`app/Support/EntryToSpendInputs.php:36-149`): miesiące z planem do dziś, pozycje z planem i wykonaniem, rytm per miesiąc. `previewToSpendReadings` czyta każdy miesiąc z planem dotknięty zmianami (`resources/js/lib/entry-to-spend-warning.ts:160-240`), więc ostrzeżenie o kilku miesiącach już istnieje w kształcie danych.
- **Kreska „Postęp roku”** jest bramkowana tylko w TS (`resources/js/lib/dashboard-categories.ts:50-60`, komentarz o S-26 w :58).
- **Snapshot.** `BackupUserData::SNAPSHOT_VERSION = 9` (`app/Actions/BackupUserData.php:20`), wpisy rytmu w :183-189. `RestoreUserData` wczytuje rytmy tylko przy `version === SNAPSHOT_VERSION` (:416-417), listy wersji w :34 i :115-141. Ręcznie pisane pliki są dla v5, v6 i v8; v9 nie ma.
- **Operacja wymaga budżetu w realizacji** (`app/Support/CategoryList/OperationCategoryChoice.php:48`), więc rachunek w miesiącu budżetu-szkicu albo roku bez budżetu nie powstanie.
- **Dane dev** (użytkownik 1, wg planu S-24): budżet 2026 w realizacji, zatwierdzony tylko listopad 2026; „Rekolekcje” (plan 2 380 zł) i „Ubezpieczenie samochodu” (541 zł) są równomierne roczne z kwotą 0 w listopadzie. `DemoHouseholdSeeder` ma same bieżące i żadnego zatwierdzonego planu.
- **W kodzie nie ma** „stawki”, „zebranego” ani „nieregularnej” poza komentarzem w `dashboard-categories.ts:58`. Scalanie kategorii (S-21) nie istnieje.

## Desired End State

- W „Kategoriach” okno wydatku ma czwartą opcję rytmu „nieregularna” i pole „Zebrane startowe” (≥ 0, domyślnie 0), widoczne tylko dla tego rytmu. Oznaczenie zapisuje wpis od bieżącego miesiąca z tą kwotą; korekta kwoty poprawia wpis, który zaczął bieżący okres, bez zmiany miesiąca oznaczenia; zdjęcie rytmu kończy zebrane, a ponowne oznaczenie zaczyna od nowa.
- Propozycja planu wpisuje nieregularnej stawkę floor(plan aktualny / 12), w grudniu resztę. Zatwierdzenie ją zamraża, tryb zmiany pozwala poprawić, ale nie podnosi jej do rachunku i nie pokazuje „Ustaw plan na wydatki”.
- Wiersz nieregularnej w planie miesiąca: kolumna „Plan” to stawka, „Faktycznie” to rachunek, „Odchylenie” to nadwyżka, status „ponad plan” przy nadwyżce > 0, podpis „zebrane X”, gdzie X = max(0, zebrane z początku miesiąca + stawka − rachunek). W przyszłym miesiącu X to stan na koniec przy rachunkach, które już są. Minione miesiące pokazują stan na koniec miesiąca.
- „Do wydania” liczy pozycję nieregularną jako stawka + nadwyżka. US-02: zebrane 300 na 1 lipca, stawka 150, rachunek 400 → zebrane 50, „do wydania” niższe o 150; rachunek 600 → zebrane 0, nadwyżka 150, „do wydania” niższe o 300, wiersz „ponad plan”. Wynik miesiąca i raporty liczą faktyczny rachunek, salda i Spendable Liquidity się nie zmieniają.
- Zebrane jest odczytem liczonym łańcuchem od miesiąca oznaczenia, z podłogą 0 w każdym miesiącu, przez 31 grudnia bez kroku: zebrane 600 na 31 grudnia 2026 to zebrane 600 na 1 stycznia 2027.
- Rozstrzygnięte przypadki brzegowe: miesiąc budżetu-szkicu albo roku bez budżetu ma stawkę 0 i zebrane stoi; zatwierdzony miesiąc bez pozycji kategorii ma stawkę 0 do czasu dodania jej w „Zmień plan”; miesiące sprzed oznaczenia kategorii utworzonej jako nieregularna czytają się jako nieregularna z zebranym 0 i nie wchodzą do łańcucha.
- Dashboard: powiadomienie „Ponad plan” dla nieregularnej podaje nadwyżkę; nieregularna nie ma kreski „Postęp roku” (także na stronie Budżet) i jest poza pulą tempa i „Pozostało w miesiącu”.
- Ostrzeżenie przy wpisie i w Imporcie nie pojawia się dla rachunku pokrytego zebranym, a pojawia się, gdy wpis zepchnie poniżej zera „do wydania” miesiąca daty albo późniejszego miesiąca z planem; wiersze jednego Importu łączą się w łańcuch.
- `user:backup` pisze v10 z zebranym startowym na wpisie rytmu; v9 i starsze nadal się wczytują.
- Migracja dodaje jedną kolumnę i nie zmienia żadnego wiersza; liczby kontrolne przed i po są identyczne.

### Key Discoveries:

- Wszystkie odczyty „do wydania” przechodzą przez `MonthToSpend::rowAmounts`, więc zebrane z początku miesiąca wystarczy dodać w jednym miejscu — `app/Support/MonthToSpend.php:103-110`.
- Przy zebranym 0 reguła „stawka + nadwyżka” daje dokładnie większą z planu i wykonania, więc miesiące sprzed oznaczenia liczą się w „do wydania” jak dziś bieżąca.
- Stawka oglądanego miesiąca to kwota wiersza z `MonthPlanReading` (zatwierdzona albo z propozycji); własnej reguły stawki potrzebują tylko miesiące wcześniejsze w łańcuchu — `app/Support/MonthPlanProposal.php:133-174`.
- Formuła plan ÷ 12 z resztą w grudniu jest prywatna i ma kopię w `MonthRemainingCalculator` — `app/Support/MonthPlanProposal.php:286-308`, `app/Support/MonthRemainingCalculator.php:91-100`.
- Test migracji, który cofa `create_category_rhythms_table`, odtworzy tabelę bez nowej kolumny, jeśli nowa migracja nie dołączy do jego listy rollbacku — `.ai/rules/migrations.md`, `tests/Feature/Migrations/CategoryRhythmMigrationTest.php`.
- `RestoreUserData` rozpoznaje format z rytmem przez równość z bieżącą wersją, więc podbicie do 10 bez zmiany tego warunku wyłączy rytm przy v9 — `app/Actions/RestoreUserData.php:416-417`.
- Klucze etykiet rytmu są składane dynamicznie (`t.categories[\`rhythm_${…}\`]`), a `UiTranslationKeysTest` ich nie sprawdza, więc brak klucza `rhythm_irregular` nie wywróci żadnego testu — `resources/js/components/CategoryListRow.vue:55`, `resources/js/components/BudgetCategoryRow.vue:48`.
- Dashboard i strona planu miesiąca nie mają testu limitu zapytań ani czasu; strona Operacji ma limit 2 s przy 10 000 operacji — `tests/Feature/Nfr/NfrPerf01Test.php:45-82`.

## What We're NOT Doing

- **„Zebrane startowe” na ekranie nowego budżetu** (FR-012) — S-27. Łańcuch przez 31 grudnia powstaje tutaj, S-27 dodaje tylko pokazanie tej liczby w Budżecie.
- **Zebrane przy scalaniu kategorii** (FR-023) — S-21; **plan miesiąca w grupach** — S-25.
- **Zebrane poza wierszem planu miesiąca**: nie przy saldach, nie na Dashboardzie, nie na liście „Kategorii”, nie w karcie Podsumowania miesiąca (PRD v3, decyzja strukturalna 6). Karta podsumowania zostaje z prostymi sumami.
- **Stawka z formuły w zatwierdzonym miesiącu bez pozycji** — odrzucone w planowaniu: stawka wynosi 0, dopóki użytkownik nie doda kategorii w „Zmień plan”.
- **Miesiące sprzed oznaczenia jako „bieżąca”** — odrzucone: reguła odczytu rytmu zostaje jedna dla wszystkich rytmów.
- **Podpis „przed → po rachunku”** i **ukrywanie zebranego w przyszłych miesiącach** — odrzucone: jedna liczba, także jako prognoza.
- **Ostrzeżenie tylko dla miesiąca daty operacji** i **zebrane liczone wyłącznie na serwerze** — odrzucone: ostrzeżenie sięga późniejszych miesięcy z planem.
- **Blokada zapisu**: ostrzeżenia dalej niczego nie blokują; nie powstaje nowy rodzaj powiadomienia Dashboardu.
- **Kolumna „Stawka”** w tabeli planu, **widok historii rytmu** i **edycja minionych wpisów**.
- **Zmiana `DemoHouseholdSeeder`**: dane demo zostają bez kategorii nieregularnej.
- **Stawka jako (plan − zebrane stawki) / pozostałe miesiące** i inne warianty odrzucone w ADR-0066.
- **Osobna próba na kopii produkcji**: migracja nie rusza danych. Próba S-24 (`context/archive/2026-11-10-category-rhythm/follow-ups/review-fixes.md`) pozostaje warunkiem wdrożenia obu zmian.

## Implementation Approach

Kolejność jak w S-24: decyzje i schemat, potem zapis w „Kategoriach”, potem odczyty obszarami, na końcu E2E i dokumenty. AGENTS.md wymaga wpisu w słowniku i ADR przed kodem, więc faza 1 zaczyna od ADR-0073.

Trzy klocki niosą całą zmianę:

1. **Kalkulator łańcucha** (`CollectedCalculator`, para PHP/TS): zebrane na koniec miesiąca, nadwyżka i łańcuch miesięcy. Kopia TS istnieje, bo podgląd planu reaguje na wpisywaną stawkę przed zapisem, a ostrzeżenia liczą późniejsze miesiące przed zapisem operacji (`.ai/rules/month-calculators.md`).
2. **Odczyt zebranego** (`IrregularCollected`): dla kategorii z wpisem nieregularnym ładuje stałą liczbą zapytań budżety użytkownika z fazą, pozycje budżetów, kwoty zatwierdzonych planów i wykonanie po miesiącach od najwcześniejszego miesiąca oznaczenia, po kategorii listy, przez wszystkie lata. Odpowiada na pytanie „zebrane na początek miesiąca m”. Bez kategorii nieregularnych nie wykonuje żadnego zapytania.
3. **Jedno wejście więcej w regule pozycji**: `ToSpendCalculator` i `MonthItemDeviation` dostają zebrane z początku miesiąca; podaje je `MonthToSpend::rowAmounts`, więc plan miesiąca, Dashboard i powiadomienia czytają tę samą liczbę.

Każdy odczyt bierze rytm miesiąca, którego dotyczy (ADR-0072). Między fazą 2 a 3 kategorię da się oznaczyć, ale odczyty liczą ją jeszcze domyślną gałęzią (jak bieżącą); to stan tylko na gałęzi roboczej, a każda faza zostawia zielone testy.

## Critical Implementation Details

- **Reguła zapisu.** `ChangeCategoryRhythm` zostaje jedyną drogą. Nowy wpis nieregularny zapisuje zebrane startowe (brak kwoty = 0); wpis innego rytmu ma `null`. Ten sam rytm „nieregularna” bez późniejszego wpisu nie dodaje wpisu: jeśli kwota różni się od zapisanej, poprawia ją na wpisie obowiązującym w bieżącym miesiącu. Zdjęcie i ponowne oznaczenie w jednym miesiącu zastępuje wpis tego miesiąca (wynika z istniejącej reguły „usuń od bieżącego miesiąca, wstaw jeden”). Ten sam rytm z późniejszym wpisem (możliwy tylko po restore) zaczyna okres od bieżącego miesiąca z przesłaną kwotą, zgodnie z ADR-0072.
- **Okres.** Każdy wpis nieregularny zaczyna okres: zebrane na początek jego miesiąca startu to jego zebrane startowe, a okres kończy się na następnym wpisie. Miesiąc sprzed pierwszego wpisu kategorii, której pierwszy wpis jest nieregularny, nie należy do żadnego okresu: zebrane na jego początek wynosi 0, a jego rachunki nie wchodzą do łańcucha.
- **Reguła stawki miesiąca w łańcuchu, w tej kolejności.** (1) Rok bez budżetu albo budżet nie w realizacji → 0. (2) Brak pozycji budżetu kategorii w tym budżecie → 0. (3) Miesiąc ma zatwierdzony plan → `current_amount_minor_units` pozycji, a bez pozycji 0. (4) W pozostałych przypadkach formuła floor(plan aktualny / 12), grudzień reszta, liczona z dzisiejszego planu aktualnego; 0, gdy pozycja budżetu została zarchiwizowana przed pierwszym dniem miesiąca. Faza budżetu i stan archiwum są czytane w chwili odczytu.
- **Jedno źródło liczb wiersza.** Stawka oglądanego miesiąca to kwota wiersza z `MonthToSpend::rowAmounts`, a zebrane z początku miesiąca pochodzi z `IrregularCollected`; „zebrane” wiersza, odchylenie i pozycja w „do wydania” liczą się z tych samych trzech liczb przez `CollectedCalculator`, żeby wiersz i suma nie mogły się rozjechać.
- **Lista rollbacku testów migracji.** Nowa migracja dołącza do list rollbacku testów, które cofają `create_category_rhythms_table`, inaczej tabela wróci bez kolumny.
- **Wersje snapshotu.** Rytm wczytują wersje 9 i 10; warunek „równa bieżącej” w `RestoreUserData.php:416-417` musi stać się listą wersji.
- **Zapytania.** Wykonanie po miesiącach grupuje się po `budget_line_id` i `operation_date` i składa w miesiące w PHP, jak w `MonthCategoryActuals` (bez funkcji dat bazy, bo testy domyślne idą na SQLite). Żadnego zapytania w pętli po miesiącach ani kategoriach (Sloppy).

## Faza 1: ADR-0073, słownik, kolumna i odczyt łańcucha

### Overview

Decyzje tej rozmowy trafiają do ADR i słownika. Powstaje kolumna zebranego startowego, czwarta wartość rytmu, kalkulator łańcucha w parze PHP/TS i klasa odczytu zebranego. Poza testami nikt jeszcze ich nie czyta, a „nieregularnej” nie da się wybrać.

### Changes Required:

#### 1. ADR-0073 i słownik

**File**: `context/foundation/adr/0073-irregular-collected-storage-edge-cases-and-warnings.md` (nowy), `context/foundation/adr/0066-category-rhythm-and-irregular-collected.md`, `context/foundation/glossary.md`

**Intent**: Zapisać przed kodem to, czego ADR-0066 nie rozstrzyga, żeby implementacja i przegląd miały jedno źródło.

**Contract**: ADR-0073 dodaje do ADR-0066 (linia „Amended by ADR-0073” w ADR-0066) i mówi:
- zebrane startowe jest kolumną wpisu historii rytmu; każdy wpis nieregularny zaczyna okres; reguła zapisu z „Critical Implementation Details”;
- F9 (a): operacja wymaga budżetu w realizacji, więc miesiąc budżetu-szkicu i rok bez budżetu mają stawkę 0 i zebrane stoi;
- F9 (b): zatwierdzony miesiąc bez pozycji kategorii ma stawkę 0, dopóki użytkownik nie doda jej w „Zmień plan”; przykład „Serwis auta” (zebrane startowe 500, rachunek 700 → nadwyżka 200);
- F9 (c): miesiące sprzed pierwszego wpisu kategorii utworzonej jako nieregularna czytają się jako nieregularna z zebranym 0, poza łańcuchem; przykład rachunku 300 z października;
- podpis „zebrane X” w wierszu planu: stan na koniec miesiąca, w bieżącym miesiącu na dziś, w przyszłym jako prognoza przy istniejących rachunkach; stawka to kwota w kolumnie „Plan”, słowo „stawka” stoi w podpowiedzi okna „Kategorii”;
- ostrzeżenie przy wpisie i w Imporcie sprawdza miesiąc daty operacji i późniejsze miesiące z planem, na które wpis działa przez zebrane; wiersze jednego Importu łączą się w łańcuch; przykład zaległego rachunku 400 z września i rachunku 700 w listopadzie (zebrane na 1 listopada 600 → 200, nadwyżka 350);
- łańcuch przechodzi przez 31 grudnia już w S-26, S-27 dodaje pokazanie „zebranego startowego” w nowym budżecie;
- snapshot v10 niesie zebrane startowe na wpisie, v9 wczytuje się bez niego;
- `down()` migracji zamienia wpisy nieregularne na równomierną roczną i usuwa kolumnę (stratne: ginie zebrane startowe i rytm);
- odrzucone warianty z sekcji „What We're NOT Doing”.

Słownik: „Irregular Rate” (0 w zatwierdzonym miesiącu bez pozycji), „Collected” (okres, miesiące sprzed oznaczenia, rok bez budżetu), „Opening Collected” (kolumna wpisu, korekta na wpisie okresu), „To Spend” (zasięg ostrzeżenia), „Rhythm” (odesłanie do ADR-0073).

#### 2. Kolumna zebranego startowego

**File**: `database/migrations/<data>_add_opening_collected_to_category_rhythms.php` (przez `php artisan make:migration`), `tests/Feature/Migrations/CategoryRhythmOpeningCollectedMigrationTest.php` (nowy), `tests/Feature/Migrations/CategoryRhythmMigrationTest.php` i pozostałe testy cofające `create_category_rhythms_table`

**Intent**: Dodać miejsce na zebrane startowe bez ruszania istniejących wierszy, z uczciwym `down()`.

**Contract**: `category_rhythms.opening_collected_minor_units` — `bigint`, nullable, bez wartości domyślnej. `up()` nie zmienia żadnego wiersza. `down()` przez `DB::table()` zamienia `rhythm = 'irregular'` na `'even_annual'`, potem usuwa kolumnę. Test na PostgreSQL wg wzorca `CategoryListMigrationTest` (pomija się poza `pgsql`): po `up()` wszystkie wpisy mają `null` i ten sam rytm; po `down()` wpis nieregularny jest równomierną roczną, pozostałe bez zmian. Nowa migracja dołącza do list rollbacku wcześniejszych testów migracji rytmu.

#### 3. Wartość rytmu, model, fabryki, typ TS, etykiety

**File**: `app/Enums/Rhythm.php`, `app/Models/CategoryRhythm.php`, `database/factories/CategoryRhythmFactory.php`, `database/factories/BudgetLineFactory.php`, `resources/js/types/rhythm.ts`, `lang/pl/ui.php`, `app/Concerns/CategoryValidationRules.php`

**Intent**: Wartość istnieje w kodzie i w testach, ale użytkownik jeszcze jej nie wybierze.

**Contract**: `Rhythm::Irregular = 'irregular'`; model: kolumna w `fillable`, cast `integer`; stany fabryk `irregular(int $openingCollectedMinorUnits = 0)` (wpis od stycznia roku budżetu w `BudgetLineFactory`, jak `evenAnnual()`); typ TS `Rhythm` zyskuje `'irregular'`; klucze `month_plan.rhythm_irregular` („Nieregularna”) i `categories.rhythm_irregular` („nieregularna”). Do fazy 2 `listCategoryRules()` nie przyjmuje `irregular` (enum z wyłączeniem tej wartości), żeby nie powstał wpis bez kwoty.

#### 4. Odczyt wpisu okresu

**File**: `app/Support/CategoryRhythms.php`, `tests/Feature/CategoryRhythmsTest.php`

**Intent**: Klasa odczytu rytmu zna zebrane startowe i okres, nadal jednym zapytaniem.

**Contract**: wpisy w pamięci niosą `opening_collected_minor_units`. Nowe metody: okres nieregularny obowiązujący w miesiącu (`start_year`, `start_month`, `opening_collected_minor_units`; `null`, gdy rytm miesiąca nie jest nieregularny albo miesiąc jest przed pierwszym wpisem) oraz lista załadowanych kategorii, które mają jakikolwiek wpis nieregularny. Test liczby zapytań (`=== 1`) zostaje.

#### 5. Kalkulator łańcucha (para PHP/TS)

**File**: `app/Support/CollectedCalculator.php`, `resources/js/lib/collected-calculator.ts`, `tests/Unit/CollectedCalculatorTest.php`, `resources/js/lib/collected-calculator.test.ts`

**Intent**: Jedna czysta arytmetyka zebranego dla serwera i podglądów.

**Contract**: trzy funkcje na minor units: zebrane na koniec miesiąca = max(0, początek + stawka − wykonanie); nadwyżka = max(0, wykonanie − (początek + stawka)); łańcuch (zebrane startowe, lista miesięcy `{stawka, wykonanie}`) → dla każdego miesiąca `{początek, koniec, nadwyżka}`. Te same przypadki w obu testach: US-02 (300, 150, 400 → 50 i 0; 600 → 0 i 150), zwrot −50 przy początku 50 i stawce 150 → 250, przykład lipcowy z ADR-0066 (stawki 150 od stycznia, rachunki 400 w marcu i maju → nadwyżka maja 50, zebrane na 1 lipca 150), podłoga w każdym miesiącu, pusty łańcuch.

#### 6. Formuła plan ÷ 12 w jednym miejscu PHP

**File**: `app/Support/MonthPlanProposal.php` oraz nowa mała klasa albo publiczna metoda statyczna w `app/Support`

**Intent**: Stawka minionego niezatwierdzonego miesiąca i propozycja muszą liczyć tę samą formułę.

**Contract**: publiczna funkcja (plan roczny, miesiąc) → floor(plan / 12), grudzień: plan − 11 × floor, wydzielona z `averageFromAnnual` bez zmiany wyniku; propozycja woła ją zamiast prywatnej kopii. Kopii w `MonthRemainingCalculator` nie ruszamy.

#### 7. Odczyt zebranego

**File**: `app/Support/IrregularCollected.php`, `tests/Feature/IrregularCollectedTest.php`

**Intent**: Jedno miejsce, które zna łańcuch zebranego przez miesiące i lata i podaje zebrane na początek miesiąca.

**Contract**: budowana dla użytkownika i załadowanego `CategoryRhythms`; publicznie: zebrane na początek miesiąca (kategoria listy, rok, miesiąc) → `int` (0, gdy miesiąc nie jest w okresie). Reguła stawki i okresu wg „Critical Implementation Details”; wykonanie miesiąca to suma operacji pozycji budżetu datowanych w miesiącu, zwroty netto. Stała liczba zapytań niezależna od liczby kategorii, miesięcy i lat (test ustala ją i pilnuje), zero zapytań bez wpisów nieregularnych. Przypadki testu, z kwotami z ADR-0066: US-02; zwrot w sierpniu; przykład lipcowy z podniesieniem planu do 2 400 (stawki styczeń–czerwiec 150, zebrane na 1 lipca 150); miniony niezatwierdzony miesiąc bierze stawkę z dzisiejszego planu; 31 grudnia (zebrane 200 → 1 stycznia 200, stawka stycznia z planu 2 100 = 175); budżet 2027 w szkicu → stawka stycznia 0 i zebrane stoi, po starcie budżetu stawka wraca; rok bez budżetu; pozycja zarchiwizowana → stawka 0 w niezatwierdzonych miesiącach po archiwum, przywrócenie ją oddaje; zatwierdzony miesiąc bez pozycji → stawka 0; miesiąc sprzed oznaczenia → 0 i rachunek z października nie zmienia zebranego na 1 listopada; zdjęcie rytmu kończy okres, ponowne oznaczenie zaczyna od nowej kwoty; korekta zebranego startowego przelicza łańcuch.

### Success Criteria:

#### Automated Verification:

- Testy jednostkowe kalkulatora łańcucha przechodzą: `php artisan test --compact tests/Unit/CollectedCalculatorTest.php` i `npm test -- collected-calculator`
- Testy odczytu rytmu i zebranego przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/CategoryRhythmsTest.php tests/Feature/IrregularCollectedTest.php tests/Feature/MonthPlanProposalTest.php`
- Migracja kolumny i `down()` przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/Migrations`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0073 i słownik opisują pięć decyzji z planowania i przypadek F9 (a) bez sprzeczności z ADR-0066 i ADR-0072

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: „Kategorie”: nieregularna i zebrane startowe, snapshot v10

### Overview

Użytkownik oznacza wydatek jako nieregularny, podaje zebrane startowe i może je poprawić. Backup pisze v10, restore przyjmuje v9 i v10.

### Changes Required:

#### 1. Zapis rytmu z zebranym startowym

**File**: `app/Actions/ChangeCategoryRhythm.php`, `app/Actions/CreateListCategory.php`, `app/Actions/UpdateListCategory.php`, `app/Concerns/CategoryValidationRules.php`, `app/Http/Controllers/CategoryListController.php`

**Intent**: Oznaczenie, korekta kwoty, zdjęcie i ponowne oznaczenie działają wg ADR-0066 i ADR-0073.

**Contract**: `ChangeCategoryRhythm::handle(Category, ?Rhythm, CarbonImmutable $today, ?int $openingCollectedMinorUnits = null)` z regułą z „Critical Implementation Details”. Pole żądania `opening_collected_minor_units`: `nullable`, `integer`, `min:0`, górna granica równa `MAX_PLAN_AMOUNT_MINOR_UNITS` z `resources/js/lib/month-plan-amount.ts`, zabronione, gdy `rhythm` nie jest `irregular`. `listCategoryRules()` przyjmuje cztery rytmy. Okno kategorii na stronie Budżet nadal nie wysyła rytmu i niczego nie zmienia.

#### 2. Propsy „Kategorii”

**File**: `app/Support/CategoryListReading.php`, `resources/js/types/categories.ts`, `tests/Feature/CategoriesPageTest.php`

**Intent**: Okno edycji musi wiedzieć, jaka kwota jest zapisana i od którego miesiąca trwa okres.

**Contract**: każda kategoria dostaje `opening_collected_minor_units: int|null` i `irregular_since: {year, month}|null` (okres obowiązujący w bieżącym miesiącu; `null` dla innych rytmów). PHP i typ TS w jednym commicie z `assertInertia`.

#### 3. Okno kategorii

**File**: `resources/js/components/CategoryListFormDialog.vue`, `lang/pl/ui.php`

**Intent**: Czwarta opcja rytmu i pole kwoty widoczne tylko dla nieregularnej.

**Contract**: opcja „nieregularna” w tym samym `SegmentedControl`; pole „Zebrane startowe” jako `FormField money` wg wzorca `CategoryFormDialog.vue:183-212` (`parseMoneyInputToMinorUnits`, `minorUnitsToMoneyInput`, ukryty input z minor units; nie `useOperationAmountInput`), puste = 0, nieczytelna albo ujemna kwota blokuje „Zapisz” i pokazuje błąd pola. Podpowiedź przy nowym oznaczeniu mówi, że to stan zebranego na 1. dzień bieżącego miesiąca i że stawka miesięczna to plan roczny ÷ 12 z Budżetu; przy edycji istniejącej nieregularnej podaje miesiąc oznaczenia z `irregular_since` i to, że korekta przelicza zebrane od tego miesiąca. Nowe klucze w `t.categories.*`. Lista „Kategorii” pokazuje tylko etykietę rytmu, żadnej kwoty (`ui-contract.test.ts`).

#### 4. Kitchen sinki

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`, `resources/js/lib/categories-fixtures.ts`, `resources/js/pages/dev/CategoriesKitchenSink.vue`, `tests/Feature/DialogsKitchenSinkTest.php`, `tests/Feature/CategoriesKitchenSinkTest.php`

**Intent**: Każdy nowy stan okna i wiersza listy jest w kitchen sinku.

**Contract**: stany `/dev/ui/dialogs`: nowe oznaczenie, edycja istniejącej nieregularnej, błąd kwoty; wiersz „nieregularna” w `/dev/ui/categories`.

#### 5. Snapshot v10

**File**: `app/Actions/BackupUserData.php`, `app/Actions/RestoreUserData.php`, `tests/Feature/UserSnapshotCommandTest.php`, `tests/Fixtures/snapshots/v9-category-list.json` (nowy, pisany ręcznie), `tests/Feature/ApplicationBackupRestoreTest.php` (jeśli sprawdza wersję)

**Intent**: Backup niesie zebrane startowe i miesiąc oznaczenia (FR-022), a każdy wcześniejszy snapshot nadal się wczytuje.

**Contract**: `SNAPSHOT_VERSION = 10`; każdy wpis `rhythms` dostaje `opening_collected_minor_units` (liczba ≥ 0 dla nieregularnej, `null` dla pozostałych); miesiącem oznaczenia jest `start_year` / `start_month` wpisu. Restore: 9 dochodzi do wszystkich list wersji i do `LIST_FORMAT_VERSIONS`; rytm wczytują wersje 9 i 10, wersje poniżej 9 idą przez `LegacyRhythmConversion` jak dziś. v10 odmawia: nieregularnej bez kwoty, kwoty ujemnej albo nieliczbowej, kwoty na innym rytmie. v9 wczytuje wpisy z kwotą `null` i odmawia rytmu `irregular`, którego ta wersja nigdy nie zapisała. Testy: asercje wersji 10, round trip kategorii nieregularnej (kwota i miesiąc oznaczenia), odrzucenie niespójnego v10, restore ręcznego pliku v9 i pomocnik `asV9Snapshot` obok istniejących `asV8Snapshot`.

#### 6. Testy zapisu

**File**: `tests/Feature/CategoryRhythmWriteTest.php`, `tests/Feature/CategoryListWriteTest.php`

**Intent**: Reguła zapisu pokryta przypadkami, które ją różnicują.

**Contract**: oznaczenie z kwotą i bez (0); korekta kwoty nie dodaje wpisu i nie zmienia miesiąca startu; ponowny zapis tej samej kwoty nic nie zmienia; zdjęcie rytmu zostawia `null` na nowym wpisie; ponowne oznaczenie w tym samym miesiącu zastępuje wpis nową kwotą; ponowne oznaczenie w późniejszym miesiącu dodaje wpis; kwota przy innym rytmie i przy przychodzie odrzucona; kwota ujemna odrzucona; zmiana typu na przychód usuwa wpisy.

### Success Criteria:

#### Automated Verification:

- Testy zapisu rytmu i propsów „Kategorii” przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/CategoryRhythmWriteTest.php tests/Feature/CategoryListWriteTest.php tests/Feature/CategoriesPageTest.php`
- Testy snapshotu v10 i restore v9 przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/UserSnapshotCommandTest.php tests/Feature/ApplicationBackupRestoreTest.php`
- Testy kitchen sinków i literałów UI przechodzą: `php artisan test --compact tests/Feature/DialogsKitchenSinkTest.php tests/Feature/CategoriesKitchenSinkTest.php tests/Unit/CategoriesHardcodedUiTest.php` i `npm run check:ui`
- E2E „Kategorii” przechodzi: `npx playwright test tests/e2e/categories.spec.ts`
- Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev oznaczenie „Rekolekcji” jako nieregularnej z zebranym startowym 300,00 zapisuje się, lista pokazuje „nieregularna”, a ponownie otwarte okno pokazuje 300,00 i miesiąc oznaczenia listopad 2026
- `/dev/ui/dialogs` pokazuje trzy nowe stany w trybie jasnym i ciemnym, a cztery opcje rytmu mieszczą się na 390 px

**Implementation Note**: Jeśli cztery etykiety rytmu nie mieszczą się w `SegmentedControl` na 390 px, zatrzymaj się i zapytaj; nie wprowadzaj nowego wzorca wyboru bez decyzji. Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 3: Plan miesiąca: stawka, „zebrane” i „do wydania”

### Overview

Odczyty miesiąca liczą nieregularną wg ADR-0066: propozycja wpisuje stawkę, „do wydania” bierze stawkę i nadwyżkę, wiersz pokazuje „zebrane”, powiadomienie „Ponad plan” podaje nadwyżkę. Pary PHP/TS zmieniają się w jednym commicie.

### Changes Required:

#### 1. Para „do wydania”

**File**: `app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts`, `tests/Unit/ToSpendCalculatorTest.php`, `resources/js/lib/to-spend-calculator.test.ts`

**Intent**: Pozycja nieregularna liczy się jako stawka + nadwyżka.

**Contract**: pozycja dostaje zebrane z początku miesiąca (`collected_at_start` / `collectedAtStart`, 0 dla innych rytmów i przychodu); `itemMinorUnits` dla nieregularnej zwraca plan + nadwyżka z `CollectedCalculator`; pozostałe rytmy bez zmian. Przypadki w obu testach: bez rachunku → 150; 400 przy 300 → 150; 600 przy 300 → 300; zebrane 0 → większa z planu i wykonania; zwrot netto.

#### 2. Para odchylenia i status

**File**: `app/Support/MonthItemDeviation.php`, `resources/js/lib/month-item-deviation.ts`, `resources/js/lib/month-plan-status.ts`, `tests/Unit/MonthItemDeviationTest.php` i testy TS obok

**Intent**: Odchylenie nieregularnej to nadwyżka, a ponad plan znaczy nadwyżka > 0.

**Contract**: `deviationMinorUnits` i `isOverPlan` dostają zebrane z początku miesiąca; dla nieregularnej odchylenie = nadwyżka (nigdy `null`), ponad plan = nadwyżka > 0, pozycja nigdy nie jest „rozliczona”. Status wiersza: tylko „ponad plan” przy nadwyżce, bez znaczków stałej opłaty.

#### 3. Wejścia odczytu miesiąca

**File**: `app/Support/MonthToSpend.php`, `app/Support/MonthPlanProposal.php`, `tests/Feature/MonthToSpendTest.php`

**Intent**: Wszystkie powierzchnie czytające „do wydania” dostają zebrane z jednego miejsca.

**Contract**: `MonthToSpend` buduje `IrregularCollected` z rytmów już załadowanych przez propozycję (bez drugiego zapytania o rytm); `rowAmounts` zwraca dodatkowo `collected_at_start_minor_units`; `toSpendBreakdown` je przekazuje. Bez kategorii nieregularnych liczba zapytań `MonthToSpend::forMonth` się nie zmienia (test).

#### 4. Propozycja ze stawką

**File**: `app/Support/MonthPlanProposal.php`, `tests/Feature/MonthPlanProposalTest.php`

**Intent**: Propozycja wpisuje nieregularnej stawkę z formuły.

**Contract**: nieregularna idzie tą samą gałęzią co równomierna roczna (plan 1 800 → 150, plan 2 380 → 198,33 i grudzień 198,37); limit zapytań ≤ 10 zostaje.

#### 5. Wiersz planu i „W roku”

**File**: `app/Support/MonthPlanItemRow.php`, `app/Support/MonthPlanJoinCandidates.php`, `app/Support/MonthYearComparison.php`, `resources/js/types/month-plan.ts`, `tests/Feature/MonthPlanPageTest.php`, `tests/Unit/MonthYearComparisonTest.php`

**Intent**: Wiersz niesie zebrane i liczy odchylenie oraz „W roku” wg rytmu.

**Contract**: nowe pole `collected: { start_minor_units: number; end_minor_units: number } | null` (tylko nieregularna, we wszystkich miesiącach: minionym, bieżącym i przyszłym; koniec liczony z kwoty wiersza i wykonania miesiąca); `deviation_minor_units` i `is_over_plan` z zebranym; `is_idle_even_annual` i `even_annual_remaining` puste; „W roku” nieregularnej = wykonanie roku (przed miesiącem + wykonanie miesiąca). Wiersz kandydata do dołączenia ma ten sam kształt. PHP i typ TS w jednym commicie z `assertInertia`.

#### 6. Widok wiersza i podglądy na żywo

**File**: `resources/js/components/MonthPlanItemRow.vue`, `resources/js/pages/MonthPlan.vue`, `lang/pl/ui.php`

**Intent**: Użytkownik widzi rytm i „zebrane”, a podgląd reaguje na wpisywaną stawkę.

**Contract**: etykieta „Nieregularna” w kolumnie „Rytm”; podpis „zebrane X” pod nazwą, w bieżącym miesiącu po linii „W roku” po „·”; kwota przez `formatMoneyAmount` bez „zł”, `tabular-nums`. W propozycji i w trybie zmiany X, odchylenie, status i „do wydania” liczą się na żywo z wpisywanej kwoty przez kopie TS (`start_minor_units` z serwera). Nowy klucz `month_plan.collected`. Bez nowych klas siatki i bez nowych komponentów; gdyby powstał, trafia na listy `MonthPlanHardcodedUiTest` i `scripts/check-ui-literals.mjs`.

#### 7. Wyjątek od podnoszenia planu do wydatków

**File**: `resources/js/lib/month-plan-match-actual.ts`, `resources/js/lib/month-plan-match-actual.test.ts`

**Intent**: Tryb zmiany nie podnosi stawki do kwoty rachunku (ADR-0066 o ADR-0062).

**Contract**: dla wiersza o rytmie `irregular` tryb zmiany startuje z zapisaną kwotą, a stan przycisku i znacznika jest pusty; inne rytmy bez zmian.

#### 8. Powiadomienie „Ponad plan”

**File**: `app/Support/DashboardNotices.php`, `tests/Feature/DashboardTest.php`

**Intent**: Nieregularna podnosi `month_item_over_plan` tylko przy nadwyżce i podaje jej kwotę.

**Contract**: kwota powiadomienia dla nieregularnej = −nadwyżka (ten sam znak co dziś plan − wykonanie); rachunek pokryty zebranym nie daje powiadomienia; kolejność i waga powiadomień bez zmian (`.ai/rules/support.md`).

#### 9. Kitchen sink planu

**File**: `resources/js/lib/month-plan-fixtures.ts`, `resources/js/pages/dev/MonthPlanSink.vue`, `tests/Feature/DevMonthPlanSinkTest.php`

**Intent**: Każdy stan wiersza nieregularnej jest w `/dev/month-plan`.

**Contract**: wiersze: przed rachunkiem (zebrane 450,00), rachunek pokryty (50,00), nadwyżka (0,00, +150,00, „ponad plan”), przyszły miesiąc z prognozą, miniony miesiąc, „Poza planem” ze stawką 0, tryb edycji i tryb zmiany bez „Ustaw plan na wydatki”. Zrzuty `/dev/month-plan` i `/month-plan` w 1280 i 390 px do `context/changes/irregular-expense-collected/screenshots/` (`context/foundation/design-system.md`).

#### 10. Testy strony i planów

**File**: `tests/Feature/MonthPlanPageTest.php`, `tests/Feature/MonthSummaryTest.php`, `tests/Feature/ApproveMonthPlanTest.php`, `tests/Feature/ChangeMonthPlanTest.php`

**Intent**: US-02 i jej sąsiedztwo przechodzą od danych do propsów strony.

**Contract**: US-02 w lipcu 2027 (oba kryteria akceptacji: 400 i 600, z `to_spend_minor_units`, `collected`, odchyleniem i `is_over_plan`); Wynik miesiąca liczy faktyczny rachunek; sumy karty podsumowania zostają prostymi sumami, a wiersz minionego miesiąca pokazuje zebrane na koniec i nadwyżkę; zatwierdzenie zamraża stawkę; zmiana stawki w trybie zmiany zmienia zebrane następnych miesięcy; zatwierdzony miesiąc bez pozycji ma stawkę 0 i wiersz „Poza planem”; przyszły miesiąc pokazuje prognozę.

### Success Criteria:

#### Automated Verification:

- Testy jednostkowe par przechodzą: `php artisan test --compact tests/Unit` i `npm test`
- Testy planu miesiąca, propozycji, „do wydania” i powiadomień przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/MonthToSpendTest.php tests/Feature/MonthPlanProposalTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/MonthSummaryTest.php tests/Feature/ApproveMonthPlanTest.php tests/Feature/ChangeMonthPlanTest.php tests/Feature/DashboardTest.php tests/Feature/DevMonthPlanSinkTest.php`
- Testy literałów UI planu przechodzą: `php artisan test --compact tests/Unit/MonthPlanHardcodedUiTest.php` i `npm run check:ui`
- E2E przechodzi: `npx playwright test`
- Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev „Rekolekcje” (zebrane startowe 300,00 z fazy 2): listopad pokazuje „Nieregularna”, stawkę z zatwierdzonego planu i „zebrane” równe 300,00 + stawka; „do wydania” listopada jest takie jak przed fazą; grudzień proponuje 198,37 z podpisem „zebrane” o 198,37 wyższym niż w listopadzie
- Wydatek 400,00 na „Rekolekcje” z dzisiejszą datą ponad zebrane daje w wierszu nadwyżkę ze statusem „ponad plan” i „zebrane 0,00”, „do wydania” spada o nadwyżkę, a Dashboard pokazuje „Ponad plan” z tą samą kwotą; po usunięciu wydatku wszystko wraca
- W „Zmień plan” wiersz „Rekolekcji” startuje z zapisaną stawką, nie ma „Ustaw plan na wydatki”, a wpisanie innej stawki od razu zmienia „zebrane” i „do wydania”
- `/dev/month-plan` pokazuje nowe stany w trybie jasnym i ciemnym, w 1280 i 390 px

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 4: Dashboard, Budżet i ostrzeżenia przy wpisie i w Imporcie

### Overview

Nieregularna traci kreskę „Postęp roku”, a ostrzeżenia przy wpisie i w Imporcie liczą zebrane łańcuchem, także dla późniejszych miesięcy z planem.

### Changes Required:

#### 1. Kreska „Postęp roku” i pule

**File**: `resources/js/lib/dashboard-categories.ts`, `resources/js/lib/dashboard-categories.test.ts`, `tests/Unit/MonthRemainingCalculatorTest.php`, `tests/Unit/MonthPaceCalculatorTest.php` i testy TS obok, `resources/js/pages/dev/BudgetKitchenSink.vue`, `resources/js/pages/dev/dashboard-fixtures.ts`, `context/foundation/design-system.md`

**Intent**: Nieregularna nie ma kreski roku i jest poza pulą tempa i „Pozostało w miesiącu”.

**Contract**: `showsYearElapsedMark` zwraca fałsz dla `irregular`; testy pul dostają czwartą wartość (kod pul już ją wyklucza, więc zmiana kodu produkcyjnego nie jest tam oczekiwana); kitchen sinki Budżetu i Dashboardu mają wiersz nieregularnej bez kreski, a lista stanów w `design-system.md` to odnotowuje.

#### 2. Dane łańcucha dla ostrzeżeń

**File**: `app/Support/IrregularCollected.php`, `app/Support/EntryToSpendInputs.php`, `resources/js/lib/entry-to-spend-warning.ts` (typy), `tests/Feature/EntryToSpendInputsTest.php`

**Intent**: Przeglądarka dostaje to, czego potrzebuje, żeby przeliczyć zebrane po zmianie wykonania w dowolnym miesiącu okresu.

**Contract**: `EntryToSpendInputs::forUser` zwraca dodatkowo listę okresów: `category_id`, `start_year`, `start_month`, `opening_minor_units` i kolejne miesiące od startu okresu do bieżącego miesiąca (albo końca okresu) z `rate_minor_units` i `actual_minor_units`. Obejmuje każdy okres kategorii, które są w payloadzie. Propsy stron Operacji i podglądu Importu zmieniają się po obu stronach w jednym commicie z `assertInertia`. Testy liczby zapytań: bez nieregularnych jak dziś, z nieregularnymi stały dodatek niezależny od liczby miesięcy.

#### 3. Ostrzeżenia z łańcuchem

**File**: `resources/js/lib/entry-to-spend-warning.ts`, `resources/js/lib/import-to-spend-warnings.ts`, testy `*.test.ts` obok, komponenty pokazujące ostrzeżenie (`ExpenseFormDialog.vue`, `Operations.vue`, `OperationsImportPreview.vue`)

**Intent**: Rachunek pokryty zebranym nie ostrzega, a wpis, który przez zebrane zepchnie miesiąc pod zero, ostrzega z nazwą tego miesiąca.

**Contract**: zmiana na kategorii z okresem obejmującym miesiąc daty koryguje wykonanie tego miesiąca w okresie i przelicza łańcuch kopią TS kalkulatora; odczytywane miesiące to miesiące zmian oraz późniejsze miesiące z planem w tym samym okresie; „do wydania” każdego z nich przed i po używa zebranego z początku miesiąca. Zmiany oczekujące (wcześniejsze wiersze Importu) należą do stanu wyjściowego także przez łańcuch. Miesiąc sprzed okresu liczy zebrane 0. Warunek ostrzeżenia bez zmian: po zmianie ujemne i niższe niż przed. Każde zwrócone ostrzeżenie jest pokazane z nazwą swojego miesiąca. Przypadki testów: rachunek pokryty → brak; zaległy rachunek 400 z września przy rachunku 700 w listopadzie (zebrane na 1 listopada 600 → 200) → ostrzeżenie dla listopada, żadnego dla września; dwa wiersze jednego Importu; usunięcie albo zmniejszenie operacji nie ostrzega; rachunek z grudnia i odczyt stycznia następnego budżetu; miesiąc sprzed oznaczenia.

#### 4. Wydajność i kitchen sinki ostrzeżeń

**File**: `tests/Feature/Nfr/NfrPerf01Test.php`, `tests/Feature/Nfr/NfrPerf03ImportPreviewTest.php`, `resources/js/lib/operation-dialogs-fixtures.ts`, `resources/js/lib/import-preview-fixtures.ts`

**Intent**: Limit 2 s trzyma się z kategorią nieregularną, a stan ostrzeżenia o późniejszym miesiącu jest w kitchen sinku.

**Contract**: dane testów wydajności dostają kategorię nieregularną z operacjami w całym roku, limity bez zmian; fikstury okna wydatku i podglądu Importu mają stan z ostrzeżeniem o późniejszym miesiącu.

### Success Criteria:

#### Automated Verification:

- Testy TS ostrzeżeń i kreski roku przechodzą: `npm test`
- Testy payloadu ostrzeżeń i wydajności przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/EntryToSpendInputsTest.php tests/Feature/HandEntryMonthRemainingTest.php tests/Feature/Nfr`
- E2E przechodzi: `npx playwright test`
- Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev Dashboard i strona Budżet pokazują „Rekolekcje” jako nieregularną bez kreski „Postęp roku”, a pozostałe kategorie mają ją jak dotąd
- W oknie nowego wydatku kwota na „Rekolekcje” pokryta zebranym nie daje ostrzeżenia „do wydania”, a kwota ponad zebrane, która spycha „do wydania” listopada poniżej zera, je daje
- Kitchen sinki okna wydatku i podglądu Importu pokazują ostrzeżenie z nazwą późniejszego miesiąca

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 5: E2E, dokumenty i bramka

### Overview

Historia US-02 przechodzi w przeglądarce, dokumenty i reguły opisują stan po zmianie, a pełna bramka jest zielona na PostgreSQL.

### Changes Required:

#### 1. E2E historii użytkownika

**File**: `tests/e2e/irregular-collected.spec.ts` (nowy)

**Intent**: Zachować w zestawie przejście od oznaczenia kategorii do rachunku, bo tylko uruchomiona aplikacja pokazuje okno, plan miesiąca i „do wydania” razem.

**Contract**: test tworzy przez okno „Kategorii” wydatek „nieregularna” z zebranym startowym 500,00, księguje wydatek 400,00 z dzisiejszą datą (żądaniem z `X-XSRF-TOKEN`, jak `tests/e2e/categories.spec.ts:20-35`), otwiera plan bieżącego miesiąca i widzi wiersz „Nieregularna” z „zebrane 100,00”; po drugim wydatku 300,00 widzi „ponad plan”, odchylenie 200,00 i „zebrane 0,00”. Sprząta po sobie (operacje i kategoria). Nazwy unikalne, znacznik nie jest samą liczbą (`context/archive/2026-11-10-category-rhythm/follow-ups/review-fixes.md`).

#### 2. Reguły i dokumenty

**File**: `.ai/rules/month-calculators.md`, `.ai/rules/snapshot.md`, `.ai/rules/categories.md`, `.ai/rules/migrations.md`, `.ai/rules/js-month-plan.md`, `context/foundation/glossary.md`, `context/foundation/design-system.md`, `README.md`

**Intent**: Następny agent znajdzie nową parę kalkulatorów, wersję snapshotu i regułę zapisu bez czytania tego planu.

**Contract**: `month-calculators.md`: wiersz pary `CollectedCalculator` z powodem kopii TS i zdanie, że ostrzeżenia czytają okresy z `EntryToSpendInputs`; `snapshot.md`: v10 i plik v9; `categories.md`: zebrane startowe pisze tylko `ChangeCategoryRhythm`, a zebrane czyta tylko `IrregularCollected`; `migrations.md`: przykład stratnego `down()`; `js-month-plan.md`: podpis „zebrane” i brak „Ustaw plan na wydatki” dla nieregularnej; README: akapit o wycofaniu S-26 (`down()` zamienia nieregularną na równomierną roczną, snapshot v10 nie wczyta się w kodzie sprzed zmiany). Po zmianie reguł `php artisan boost:index-rules`.

#### 3. Roadmapa

**File**: `context/foundation/roadmap.md`

**Intent**: Roadmapa mówi, co zostało dla S-27 i że F9 jest rozstrzygnięte.

**Contract**: w S-26 „Unknowns” zastąpione odesłaniem do ADR-0073; w S-27 dopisek, że łańcuch przez 31 grudnia działa od S-26, a S-27 dodaje odczyt „zebranego startowego” w nowym budżecie; w S-21 bez zmian (zebrane przy scalaniu nadal tam). Statusy zmieniają tylko `/10x-implement` i `/10x-archive`.

### Success Criteria:

#### Automated Verification:

- E2E historii US-02 przechodzi: `npx playwright test tests/e2e/irregular-collected.spec.ts`
- Pełna bramka na PostgreSQL: `docker run … --entrypoint composer sail-8.5/app ci:check` (polecenie z AGENTS.md)
- `vendor/bin/sloppy ci` i `npx playwright test` przechodzą

#### Manual Verification:

- Słownik, ADR-0073, reguły w `.ai/rules` i roadmapa opisują stan po S-26 bez sprzeczności
- Historia US-02 przechodzi na dev od oznaczenia kategorii, przez stawkę w propozycji grudnia, po rachunek pokryty zebranym i rachunek ponad zebrane

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed zamknięciem zmiany.

---

## Testing Strategy

`…` w poleceniach to polecenie PostgreSQL dla worktree z AGENTS.md (sekcja Commands). Testy migracji, snapshotu, odczytu łańcucha i stron idą na PostgreSQL, bo zielony przebieg na SQLite nic tu nie dowodzi.

### Unit Tests:

- `CollectedCalculator` w PHP i TS na tych samych przypadkach: podłoga 0 w każdym miesiącu, zwrot, nadwyżka, łańcuch przez kilka miesięcy.
- `ToSpendCalculator` i `MonthItemDeviation` w obu kopiach: nieregularna przy zebranym 0, pokrytym rachunku i nadwyżce; pozostałe rytmy bez zmian.
- Pule tempa i „Pozostało w miesiącu” z czwartą wartością rytmu; `showsYearElapsedMark`.
- Ostrzeżenia TS: łańcuch przez miesiące, wiersze jednego Importu, późniejszy miesiąc.

### Integration Tests:

- `IrregularCollectedTest`: przykłady ADR-0066 co do grosza, trzy przypadki F9, 31 grudnia, archiwum, dwa okresy, stała liczba zapytań.
- `MonthPlanPageTest`: US-02 z oboma kryteriami akceptacji przez propsy strony.
- `CategoryRhythmWriteTest`: reguła zapisu i korekty.
- `UserSnapshotCommandTest`: v10 w obie strony, v9 z ręcznego pliku, odrzucenia.
- Test migracji z `down()` na PostgreSQL.
- E2E: `categories.spec.ts` bez regresji i nowy `irregular-collected.spec.ts`.

### Manual Testing Steps:

1. W „Kategoriach” oznacz „Rekolekcje” jako nieregularną z zebranym startowym 300,00 i sprawdź okno po ponownym otwarciu.
2. W planie listopada sprawdź etykietę, stawkę, „zebrane” i niezmienione „do wydania”; w grudniu propozycję 198,37.
3. Dodaj wydatek ponad zebrane, sprawdź nadwyżkę w planie i na Dashboardzie, potem go usuń.
4. W „Zmień plan” sprawdź brak podnoszenia stawki i podgląd na żywo.
5. Sprawdź ostrzeżenie w oknie wydatku dla kwoty pokrytej i ponad zebrane.
6. Na końcu przywróć „Rekolekcjom” rytm, jeśli dane dev mają zostać jak przed zmianą.

## Performance Considerations

`IrregularCollected` dodaje stałą liczbę zapytań tylko wtedy, gdy użytkownik ma wpis nieregularny; zapytanie o wykonanie jest ograniczone do pozycji budżetów tych kategorii i dat od najwcześniejszego miesiąca oznaczenia. Strona Operacji i podgląd Importu mają limit 2 s przy 10 000 operacji i dostają w fazie 4 kategorię nieregularną w danych testu. Dashboard i plan miesiąca nie mają testu czasu; pilnuje ich test stałej liczby zapytań odczytu zebranego. Payload okresów dla ostrzeżeń rośnie o jeden wiersz na miesiąc okresu na kategorię nieregularną.

## Migration Notes

- Jedna migracja schematu: nullable kolumna na `category_rhythms`. Żaden wiersz się nie zmienia i żadna kategoria nie staje się nieregularna, więc liczby kontrolne `app:control-numbers` przed i po `migrate` są identyczne.
- Wycofanie po wdrożeniu bez utraty operacji: `down()` zamienia wpisy nieregularne na równomierną roczną (ta sama kwota propozycji, „do wydania” jako większa z planu i wykonania) i usuwa kolumnę. Giną zebrane startowe i oznaczenia; operacje, plany i kwoty zostają. Kopia przed migracją (ADR-0064) pokrywa awarię w trakcie wdrożenia.
- Snapshot v10 nie wczyta się w kodzie sprzed S-26; drogą powrotu jest `down()`, nie restore nowego snapshotu w starym kodzie.
- S-26 wdraża się po S-24 albo razem z nim; próba S-24 na kopii produkcji jest nadal otwartym warunkiem wdrożenia.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-26, S-27, S-21)
- PRD: `context/foundation/prd-v3.md` (US-02, FR-010, FR-011, FR-013, FR-022, decyzje strukturalne 5–9)
- ADR-0066: `context/foundation/adr/0066-category-rhythm-and-irregular-collected.md`; ADR-0072, ADR-0062, ADR-0033, ADR-0064, ADR-0058
- Przegląd F-05 (F9): `context/archive/2026-11-09-rhythm-and-irregular-decisions/reviews/impl-review.md:128-136`
- Poprzednia zmiana: `context/archive/2026-11-10-category-rhythm/` (plan, przeglądy, `follow-ups/review-fixes.md`)
- Słownik: `context/foundation/glossary.md` („Rhythm”, „Irregular Rate”, „Collected”, „Opening Collected”, „To Spend”, „Year after the Month”, „Month Summary”)
- Reguły: `.ai/rules/migrations.md`, `snapshot.md`, `categories.md`, `month-calculators.md`, `js-month-plan.md`, `js-dialogs.md`, `js.md`, `inertia-props.md`, `support.md`, `sloppy.md`
- Wzorce: `tests/Feature/Migrations/CategoryListMigrationTest.php`, `app/Support/MonthCategoryActuals.php:27-60`, `resources/js/components/CategoryFormDialog.vue:183-212`, `tests/e2e/categories.spec.ts:20-35`
- Podgląd worktree do weryfikacji ręcznej: `context/foundation/agents/worktree-preview.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0073, słownik, kolumna i odczyt łańcucha

#### Automated

- [x] 1.1 Testy jednostkowe kalkulatora łańcucha przechodzą — 312795a
- [x] 1.2 Testy odczytu rytmu i zebranego przechodzą na PostgreSQL — 312795a
- [x] 1.3 Migracja kolumny i `down()` przechodzą na PostgreSQL — 312795a
- [x] 1.4 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — 312795a

#### Manual

- [x] 1.5 ADR-0073 i słownik opisują pięć decyzji z planowania i przypadek F9 (a) bez sprzeczności z ADR-0066 i ADR-0072 — 312795a

### Phase 2: „Kategorie”: nieregularna i zebrane startowe, snapshot v10

#### Automated

- [x] 2.1 Testy zapisu rytmu i propsów „Kategorii” przechodzą na PostgreSQL — 3086f91
- [x] 2.2 Testy snapshotu v10 i restore v9 przechodzą na PostgreSQL — 3086f91
- [x] 2.3 Testy kitchen sinków i literałów UI przechodzą — 3086f91
- [x] 2.4 E2E „Kategorii” przechodzi — 3086f91
- [x] 2.5 Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci` — 3086f91

#### Manual

- [x] 2.6 Na dev oznaczenie „Rekolekcji” jako nieregularnej z zebranym startowym 300,00 zapisuje się i wraca w oknie — 3086f91
- [x] 2.7 `/dev/ui/dialogs` pokazuje trzy nowe stany, a cztery opcje rytmu mieszczą się na 390 px — 3086f91

### Phase 3: Plan miesiąca: stawka, „zebrane” i „do wydania”

#### Automated

- [x] 3.1 Testy jednostkowe par przechodzą — 8f154b4
- [x] 3.2 Testy planu miesiąca, propozycji, „do wydania” i powiadomień przechodzą na PostgreSQL — 8f154b4
- [x] 3.3 Testy literałów UI planu przechodzą — 8f154b4
- [x] 3.4 E2E przechodzi — 8f154b4
- [x] 3.5 Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci` — 8f154b4

#### Manual

- [x] 3.6 Na dev listopad i grudzień 2026 pokazują stawkę i „zebrane” dla „Rekolekcji”, a „do wydania” listopada się nie zmienia — 8f154b4
- [x] 3.7 Wydatek ponad zebrane daje nadwyżkę w planie i „Ponad plan” na Dashboardzie — 8f154b4
- [x] 3.8 „Zmień plan” nie podnosi stawki do rachunku i przelicza „zebrane” na żywo — 8f154b4
- [x] 3.9 `/dev/month-plan` pokazuje nowe stany w obu trybach, w 1280 i 390 px — 8f154b4

### Phase 4: Dashboard, Budżet i ostrzeżenia przy wpisie i w Imporcie

#### Automated

- [x] 4.1 Testy TS ostrzeżeń i kreski roku przechodzą — 70774f0
- [x] 4.2 Testy payloadu ostrzeżeń i wydajności przechodzą na PostgreSQL — 70774f0
- [x] 4.3 E2E przechodzi — 70774f0
- [x] 4.4 Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci` — 70774f0

#### Manual

- [x] 4.5 Dashboard i Budżet pokazują nieregularną bez kreski „Postęp roku” — 70774f0
- [x] 4.6 Wpis pokryty zebranym nie ostrzega, a wpis ponad zebrane ostrzega — 70774f0
- [x] 4.7 Kitchen sinki pokazują ostrzeżenie z nazwą późniejszego miesiąca — 70774f0

### Phase 5: E2E, dokumenty i bramka

#### Automated

- [x] 5.1 E2E historii US-02 przechodzi
- [x] 5.2 Pełna bramka na PostgreSQL
- [ ] 5.3 `vendor/bin/sloppy ci` i `npx playwright test` przechodzą

#### Manual

- [x] 5.4 Słownik, ADR-0073, reguły i roadmapa opisują stan po S-26 bez sprzeczności
- [x] 5.5 Historia US-02 przechodzi na dev od oznaczenia kategorii do rachunku ponad zebrane
