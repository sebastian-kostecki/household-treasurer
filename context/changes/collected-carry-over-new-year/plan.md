# Zebrane startowe z 31 grudnia w Budżecie — plan implementacji

## Overview

S-27 z roadmapy M-3 (FR-012): na ekranie Budżet kategoria nieregularna pokazuje pod nazwą „zebrane startowe” roku, czyli odczyt zebranego na 31 grudnia roku poprzedniego. Łańcuch zebranego przechodzi przez 31 grudnia już od S-26 (ADR-0066, ADR-0073); ta zmiana dodaje tylko pokazanie liczby. Nic nie jest zapisywane: liczba jest odczytem, więc grudniowy rachunek albo korekta przelicza ją sama. Pierwsze prawdziwe przejście roku wypada na przełomie 2026 i 2027.

## Current State Analysis

- **Odczyt już istnieje.** `IrregularCollected::collectedAtStartOfMonth(kategoria, rok, miesiąc)` liczy łańcuch od początku okresu do miesiąca docelowego, bez górnej granicy dat (`app/Support/IrregularCollected.php:61-86`). Początek stycznia roku Y zależy tylko od miesięcy do grudnia Y−1, więc faza budżetu Y nie ma znaczenia. Test przejścia roku jest w `tests/Feature/IrregularCollectedTest.php:122-134`.
- **Zero i „poza okresem” wyglądają tak samo.** Metoda zwraca `0` także wtedy, gdy miesiąc nie należy do okresu nieregularnego (`IrregularCollected.php:63-67`). Czy styczeń należy do okresu, mówi `CategoryRhythms::irregularPeriodIn()` (`app/Support/CategoryRhythms.php:138-164`), które zwraca `null` także dla miesiąca sprzed pierwszego wpisu.
- **Koszt.** Odczyt ładuje dane leniwie, raz na instancję, pięcioma zapytaniami niezależnie od liczby kategorii, miesięcy i lat; bez kategorii nieregularnych nie pyta bazy (`IrregularCollected.php:211-263`, test `IrregularCollectedTest.php:216-255`).
- **Strona Budżet.** `BudgetController::show` ładuje rytmy widocznych wierszy jednym zapytaniem (`app/Http/Controllers/BudgetController.php:75`) i ma dzisiejszą datę (`:62`). Wiersz buduje prywatne `categoryPayload()` (`:216-258`); `rhythm` wiersza to rytm oglądanego miesiąca (styczeń dla roku przyszłego, bieżący miesiąc dla bieżącego, grudzień dla minionego), nie rytm stycznia. Strona nie używa dziś `IrregularCollected`.
- **Wiersz.** Typ `BudgetCategoryRow` jest eksportowany z `resources/js/components/BudgetCategoryList.vue:40-65`. `BudgetCategoryRow.vue:86-98` pokazuje `CategoryChip` w rozmiarze `md` i obok znaczek „Zarchiwizowane” albo „przekroczono”; pod nazwą nie ma nic. W szkicu rytm widać w kolumnie „Typ” (od `md`), w realizacji rytmu nie widać wcale.
- **Wzorzec podpisu.** Plan miesiąca pokazuje „zebrane X” pod nazwą przez `CategoryChip size="lg"` ze slotami `#badges` i `#subtitle`, styl `meta` (`resources/js/components/MonthPlanItemRow.vue:138-193`, `resources/js/components/CategoryChip.vue:59-70`). ADR-0060 mówi, że Budżet zostaje przy małym kafelku.
- **Kopia tekstów.** Grupa `budget` w `lang/pl/ui.php:216-374` nie ma klucza o zebranym. `categories.opening_collected` („Zebrane startowe”, `:609`) to kwota wpisana przy oznaczeniu, czyli inna liczba.
- **Stany widoku.** `/dev/kitchen-sink/budget` ma fikstury w samym pliku (`resources/js/pages/dev/BudgetKitchenSink.vue:25-100`); wiersz nieregularny „Rekolekcje” jest tylko w realizacji (`:186-190`).
- **Dokumenty.** Słownik mówi już, że „zebrane startowe” nowego budżetu to odczyt na 31 grudnia, a nie kwota wpisu (`context/foundation/glossary.md:117`). Żaden dokument nie mówi, w których budżetach liczba stoi, jak wygląda przed końcem roku ani gdzie w wierszu.

## Desired End State

- W każdym budżecie (szkic i realizacja) wiersz kategorii, której styczeń roku budżetu należy do okresu nieregularnego, ma pod nazwą podpis „zebrane startowe X”. X to zebrane na początek stycznia tego roku, czyli stan na koniec grudnia roku poprzedniego.
- Gdy rok budżetu jest późniejszy niż rok dzisiejszej daty, podpis kończy się słowem „prognoza”: „zebrane startowe 600,00 · prognoza”. Od 1 stycznia tego roku słowa nie ma.
- Przykład prądu: oznaczony w listopadzie 2026 z zebranym startowym 300,00, plan 2026 = 1 800,00 (stawka 150,00), bez zatwierdzonych planów miesięcy. Szkic 2027 oglądany 11 listopada 2026 pokazuje „zebrane startowe 600,00 · prognoza”; po rachunku 400,00 z 20 grudnia pokazuje 200,00. Budżet 2027 w realizacji oglądany 10 marca 2027 pokazuje „zebrane startowe 200,00”, a marcowy rachunek tej liczby nie zmienia.
- Podpisu nie ma, gdy styczeń nie należy do okresu: kategoria oznaczona w trakcie roku (prąd w budżecie 2026), kategoria, której rytm zdjęto od stycznia, przychód, inne rytmy. Kategoria oznaczona w styczniu pokazuje kwotę wpisaną przy oznaczeniu.
- Zebrane równe zero w okresie pokazuje „zebrane startowe 0,00”, nie brak podpisu.
- Liczba w szkicu następnego roku równa się „zebranemu” w wierszu tej kategorii w planie grudnia, co do grosza.
- Wiersz zarchiwizowany (przy „Pokaż zarchiwizowane”) ma podpis w tonie reszty wiersza.

### Key Discoveries:

- Rytmy są już w kontrolerze, więc odczyt dodaje stałe 5 zapytań, a gdy żaden wiersz nie ma stycznia w okresie, nie dodaje żadnego — `app/Http/Controllers/BudgetController.php:75`, `app/Support/IrregularCollected.php:197-218`.
- Podpis zależy tylko od roku poprzedniego, więc edycja planu w szkicu go nie zmienia i nie trzeba kopii TS kalkulatora ani podglądu na żywo.
- `collectedAtStartOfMonth` nie odróżnia zera od braku okresu; o obecności podpisu decyduje `irregularPeriodIn(kategoria, rok, 1)` — `app/Support/CategoryRhythms.php:138-164`.
- Kategoria utworzona jako nieregularna po styczniu czyta się w styczniu jako nieregularna, ale nie ma okresu (F9 (c) z ADR-0073), więc znaczek „nieregularna” w kolumnie „Typ” nie oznacza, że podpis będzie.
- `NfrPerf01Test` mierzy stronę Budżet z kategorią nieregularną od stycznia roku budżetu; tam łańcuch jest pusty i odczyt nie ładuje danych, więc ten test nie pilnuje kosztu nowego odczytu — `tests/Feature/Nfr/NfrPerf01Test.php:59-64,148-170`.
- Fikstury kitchen sinka budują `BudgetCategoryRow` funkcją `row()`, więc nowe pole typu wymaga wartości domyślnej w jednym miejscu — `resources/js/pages/dev/BudgetKitchenSink.vue:25-48`.

## What We're NOT Doing

- **Stawka na ekranie Budżet** (plan roczny ÷ 12), także tylko w szkicu — odrzucone w planowaniu; stawkę pokazuje plan miesiąca.
- **Osobna kolumna „Zebrane startowe”** i **liczba w linii nazwy** — odrzucone; podpis stoi pod nazwą.
- **Podpis tylko w szkicu** — odrzucone; liczba zostaje po starcie realizacji.
- **Notatka pod tabelą zamiast słowa „prognoza”** i **brak oznaczenia prognozy** — odrzucone.
- **Suma zebranego** w wierszach grup, w „Razem” i w panelu miar; zebrane na Dashboardzie, przy saldach i na liście „Kategorii” (PRD v3, decyzja strukturalna 6).
- **Zapis liczby** przy tworzeniu budżetu albo starcie realizacji; zmiana `CreateBudget`, `StartBudgetExecution` i okna „Nowy budżet”.
- **Zmiana reguł łańcucha** z ADR-0066 i ADR-0073, migracja, zmiana snapshotu.
- **Etykieta rytmu w wierszu budżetu w realizacji**: podpis jest tam jedynym nowym elementem.
- **Zmiana `DemoHouseholdSeeder`**.

## Implementation Approach

AGENTS.md wymaga wpisu w słowniku i ADR przed kodem, więc faza 1 zaczyna od ADR-0076. Odczyt dla wiersza trafia do małej klasy w `app/Support`, żeby kontroler nie liczył sam i żeby `categoryPayload()` nie rósł o kolejne argumenty. Pole propsów zmienia się po obu stronach w jednym commicie (`.ai/rules/inertia-props.md`). Faza 2 dodaje widok: podpis według wzorca planu miesiąca, stany w kitchen sinku, E2E i dokumenty.

Nazwa pola `collected_at_year_start` celowo różni się od `opening_collected_minor_units` z „Kategorii”: tamto to kwota wpisu rytmu, to jest odczyt łańcucha.

## Critical Implementation Details

- **Obecność podpisu a wartość.** Najpierw `irregularPeriodIn(kategoria, rok budżetu, 1)`: `null` daje brak pola, każdy inny wynik daje pole z kwotą, także równą zero. Kwoty nie wolno używać jako warunku.
- **Prognoza.** `is_forecast` jest prawdą dokładnie wtedy, gdy rok budżetu jest większy niż rok dzisiejszej daty z kontrolera (UTC, jak reszta strony). Faza budżetu nie wchodzi do warunku: szkic 2027 oglądany w styczniu 2027 nie jest już prognozą.
- **Leniwe ładowanie.** Klasa odczytu nie pyta `IrregularCollected`, dopóki żaden wiersz nie ma stycznia w okresie, więc budżet bieżącego roku z kategorią oznaczoną w trakcie roku nie dostaje dodatkowych zapytań.

## Faza 1: ADR-0076, słownik i odczyt w propsach Budżetu

### Overview

Decyzje tej rozmowy trafiają do ADR i słownika. Powstaje klasa odczytu, a wiersz Budżetu niesie nowe pole w PHP i w typie TS. Widok jeszcze go nie pokazuje.

### Changes Required:

#### 1. ADR-0076 i słownik

**File**: `context/foundation/adr/0076-opening-collected-on-the-budget-screen.md` (nowy), `context/foundation/adr/0073-irregular-collected-storage-edge-cases-and-warnings.md`, `context/foundation/adr/0060-plan-change-direction-arrow.md`, `context/foundation/glossary.md`

**Intent**: Zapisać przed kodem, co „zebrane startowe” znaczy na ekranie Budżet, żeby implementacja i przegląd miały jedno źródło.

**Contract**: ADR-0076 (po angielsku, w stylu sąsiednich ADR) dodaje do ADR-0073 i mówi:
- „zebrane startowe” na ekranie Budżet to Collected na początek stycznia roku budżetu, równe Collected na koniec grudnia roku poprzedniego; odczyt, nigdy zapisana liczba;
- stoi w każdym budżecie, szkicu i w realizacji, przy kategorii, której styczeń należy do okresu nieregularnego; „nowy budżet” z FR-012 znaczy tu każdy budżet roku, nie tylko szkic;
- brak okresu w styczniu daje brak podpisu (oznaczenie w trakcie roku, zdjęty rytm, F9 (c)); oznaczenie w styczniu pokazuje Opening Collected tego wpisu; zero w okresie pokazuje 0,00;
- przed 1 stycznia roku budżetu liczba jest prognozą z rachunków i planów, które już są, i ma dopisek „prognoza”; przykład prądu 600,00 → 200,00 po grudniowym rachunku 400,00;
- podpis stoi pod nazwą kategorii, a wiersz z podpisem używa `CategoryChip` w rozmiarze `lg` (zmiana klauzuli ADR-0060 „Budżet zostaje przy małym kafelku” tylko dla takich wierszy);
- ADR-0037 usunął z Budżetu odczyty miesięczne; ten podpis jest stanem na granicy roku i nie przywraca żadnego z nich;
- stawki na ekranie Budżet nie ma; grupy, „Razem” i panel miar nie sumują zebranego;
- odrzucone warianty z sekcji „What We're NOT Doing”.

ADR-0073 i ADR-0060 dostają linię „Amended by ADR-0076”. Słownik: w „Opening Collected” zdanie o nowym budżecie zastąpione regułą z ADR-0076 (każdy budżet, styczeń w okresie, prognoza przed 1 stycznia); w „Collected” odesłanie do ADR-0076 przy zdaniu o 31 grudnia. Nie powstaje nowy termin.

#### 2. Odczyt dla wiersza Budżetu

**File**: `app/Support/BudgetCollectedAtYearStart.php` (przez `php artisan make:class`), `tests/Feature/BudgetCollectedAtYearStartTest.php` (przez `php artisan make:test --pest`)

**Intent**: Jedno miejsce, które dla budżetu i kategorii odpowiada, czy wiersz ma „zebrane startowe” i ile ono wynosi.

**Contract**: klasa budowana z budżetu, załadowanych `CategoryRhythms` i dzisiejszej daty; publicznie `forCategory(int $categoryId): ?array{minor_units: int, is_forecast: bool}`. Reguły z „Critical Implementation Details”. Kwotę bierze wyłącznie z `IrregularCollected::collectedAtStartOfMonth(kategoria, rok, 1)` (`.ai/rules/categories.md`), jedną instancją na budżet. Bez wiersza ze styczniem w okresie nie wykonuje żadnego zapytania.

#### 3. Pole wiersza po obu stronach

**File**: `app/Http/Controllers/BudgetController.php`, `resources/js/components/BudgetCategoryList.vue`, `resources/js/pages/dev/BudgetKitchenSink.vue`

**Intent**: Wiersz Budżetu niesie odczyt, a typ strony go zna, w jednym commicie.

**Contract**: każdy wiersz `incomeCategories` i `expenseCategories` dostaje `collected_at_year_start: { minor_units: number; is_forecast: boolean } | null`; docblock `categoryPayload()` i typ `BudgetCategoryRow` opisują to samo. Fikstura `row()` kitchen sinka ma domyślnie `null`. Gałąź „brak budżetu” się nie zmienia.

#### 4. Testy strony

**File**: `tests/Feature/BudgetCollectedAtYearStartTest.php`

**Intent**: Liczby z rozmowy przechodzą od danych do propsów strony, a koszt odczytu jest stały.

**Contract**: testy przez `assertInertia` na komponencie `Budget`, z datą ustawioną przez `travelTo` i rokiem w sesji, kwoty policzone ręcznie:
- szkic 2027 oglądany 2026-11-11: prąd nieregularny od listopada 2026 z kwotą 30 000, plan 2026 = 180 000 w realizacji, bez zatwierdzonych planów → `{ minor_units: 60000, is_forecast: true }`; z rachunkiem 40 000 z 2026-12-20 → 20 000;
- budżet 2027 w realizacji oglądany 2027-03-10 z tym samym grudniowym rachunkiem i rachunkiem z marca 2027 → `{ minor_units: 20000, is_forecast: false }`;
- zgodność z planem miesiąca: `collected.end_minor_units` wiersza prądu w planie grudnia 2026 równa się `minor_units` w szkicu 2027;
- `null`: przychód, bieżąca, prąd w budżecie 2026 (okres zaczyna się w listopadzie), kategoria z wpisem innego rytmu od stycznia 2027;
- wpis nieregularny od stycznia 2027 z kwotą 25 000 po okresie z 2026 → 25 000;
- rok poprzedni bez budżetu → kwota wpisu stoi (30 000);
- rachunek ponad zebrane w grudniu → `{ minor_units: 0, … }`, nie `null`;
- wiersz zarchiwizowany niesie pole przy `show_archived=1`;
- liczba zapytań strony jest taka sama dla jednej i dla trzech kategorii nieregularnych, a budżet 2026 z kategorią oznaczoną w listopadzie nie wykonuje zapytań odczytu zebranego.

### Success Criteria:

#### Automated Verification:

- Testy odczytu i propsów Budżetu przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/BudgetCollectedAtYearStartTest.php tests/Feature/BudgetTest.php tests/Feature/IrregularCollectedTest.php`
- Typy i build przechodzą: `npm run build && npm run types:check`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0076 i słownik opisują cztery decyzje z planowania bez sprzeczności z ADR-0066, ADR-0073, ADR-0060 i ADR-0037

**Implementation Note**: Worktree nie ma jeszcze zależności (hook zgłosił błąd `composer install`); przed pierwszym testem wykonaj kroki „Fresh checkout or worktree” z AGENTS.md. Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: Podpis w wierszu, kitchen sink, E2E i bramka

### Overview

Użytkownik widzi „zebrane startowe” pod nazwą kategorii w szkicu i w realizacji. Każdy stan jest w kitchen sinku, historia FR-012 przechodzi w przeglądarce, a dokumenty opisują stan po zmianie.

### Changes Required:

#### 1. Podpis w wierszu

**File**: `resources/js/components/BudgetCategoryRow.vue`, `lang/pl/ui.php`

**Intent**: Wiersz z odczytem pokazuje podpis według wzorca planu miesiąca; pozostałe wiersze wyglądają jak dziś.

**Contract**: gdy `collected_at_year_start !== null`, kafelek kategorii ma rozmiar `lg`, znaczki „Zarchiwizowane” i „przekroczono” przechodzą do slotu `#badges`, a slot `#subtitle` niesie `meta text-muted-foreground tabular-nums` z tekstem „zebrane startowe” i kwotą przez `formatMoneyAmount` (bez „zł”); przy `is_forecast` dochodzi „ · prognoza”. Element ma atrybut `data-collected-at-year-start` i `title` z podpowiedzią. Nowe klucze w grupie `budget`: `collected_at_year_start` („zebrane startowe”), `collected_at_year_start_forecast` („prognoza”), `collected_at_year_start_hint` („Zebrane na 31 grudnia poprzedniego roku.”), `collected_at_year_start_forecast_hint` („Prognoza na 31 grudnia: rachunki i zmiany planu do końca roku ją zmienią.”). Bez nowych klas siatki, bez nowych komponentów i bez nowego propa wiersza; gdyby klasa była potrzebna, trafia do `app.css` z nazwą (`.ai/rules/css.md`).

#### 2. Kitchen sink i design system

**File**: `resources/js/pages/dev/BudgetKitchenSink.vue`, `context/foundation/design-system.md`

**Intent**: Każdy nowy stan wiersza jest w `/dev/kitchen-sink/budget`.

**Contract**: nowe wiersze w fiksturach: szkic z prognozą („Prąd”, 600,00), szkic z prognozą 0,00, realizacja bez prognozy („Rekolekcje”), nieregularna bez podpisu (oznaczona w trakcie roku), zarchiwizowana z podpisem, długa nazwa z prognozą, wiersz z podpisem pod grupą. Wpis `default` w macierzy stanów je wymienia. `design-system.md`: lista stanów kitchen sinka Budżetu i zdanie o `CategoryChip` w „Gotowe elementy” (Budżet używa `lg` w wierszu z „zebranym startowym”, ADR-0076). Zrzuty `/dev/kitchen-sink/budget` i `/budget` w 1280 i 390 px, w jasnym i ciemnym motywie, do `context/changes/collected-carry-over-new-year/screenshots/`.

#### 3. E2E historii FR-012

**File**: `tests/e2e/collected-year-start.spec.ts` (nowy)

**Intent**: Zachować w zestawie przejście od oznaczenia kategorii do nowego budżetu, bo tylko uruchomiona aplikacja pokazuje okno „Kategorii”, okno „Nowy budżet” i wiersz Budżetu razem.

**Contract**: test tworzy przez okno „Kategorii” wydatek „nieregularna” z zebranym startowym 500,00 (wzorzec `tests/e2e/irregular-collected.spec.ts`), tworzy budżet następnego roku przez okno „Nowy budżet” (wzorzec `tests/e2e/sidebar-year-refresh.spec.ts`) i widzi w wierszu tej kategorii „zebrane startowe 500,00 · prognoza”. Kategoria nie ma planu w bieżącym roku, więc stawka wynosi 0 i wynik nie zależy od daty uruchomienia. Sprząta po sobie: usuwa utworzony budżet i archiwizuje kategorię. Nazwa unikalna, znacznik nie jest samą liczbą.

#### 4. Reguły i roadmapa

**File**: `.ai/rules/categories.md`, `.ai/rules/js.md`, `context/foundation/roadmap.md`

**Intent**: Następny agent znajdzie odczyt i stany widoku bez czytania tego planu.

**Contract**: `categories.md`: zdanie, że „zebrane startowe” wiersza Budżetu czyta `BudgetCollectedAtYearStart` przez `IrregularCollected` i że o podpisie decyduje okres w styczniu, nie kwota. `js.md`: stany „zebranego startowego” dopisane do zdania o kitchen sinku Budżetu, wraz z regułą `lg` dla wiersza z podpisem. Po zmianie reguł `php artisan boost:index-rules`. Roadmapa: w S-27 odesłanie do ADR-0076 i odświeżona uwaga w tabeli statusów („Czeka na S-26” jest nieaktualne); statusy zmieniają tylko `/10x-implement` i `/10x-archive`.

### Success Criteria:

#### Automated Verification:

- Testy literałów UI i kluczy tłumaczeń przechodzą: `npm run check:ui`, `npm test` i `php artisan test --compact tests/Unit/UiTranslationKeysTest.php tests/Feature/BudgetKitchenSinkTest.php`
- E2E historii FR-012 i cały zestaw przechodzą: `npx playwright test`
- Pełna bramka na PostgreSQL: `docker run … --entrypoint composer sail-8.5/app ci:check` (polecenie z AGENTS.md), `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev szkic 2027 pokazuje przy „Rekolekcjach” podpis „zebrane startowe X · prognoza”, gdzie X równa się „zebranemu” w wierszu „Rekolekcji” w planie grudnia 2026, a budżet 2026 nie ma tego podpisu
- `/dev/kitchen-sink/budget` pokazuje nowe stany w trybie jasnym i ciemnym, w 1280 i 390 px, a wiersze bez podpisu wyglądają jak przed zmianą
- Reguły w `.ai/rules`, `design-system.md` i roadmapa opisują stan po S-27 bez sprzeczności z ADR-0076

**Implementation Note**: Jeśli dev nie ma budżetu 2027, utwórz go oknem „Nowy budżet” i usuń po sprawdzeniu, o ile nie ma zostać. Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed zamknięciem zmiany.

---

## Testing Strategy

`…` w poleceniach to polecenie PostgreSQL dla worktree z AGENTS.md (sekcja Commands). Testy strony idą na PostgreSQL, bo zielony przebieg na SQLite nic tu nie dowodzi.

### Unit Tests:

- Brak nowej arytmetyki: `CollectedCalculator` i `IrregularCollected` mają już testy łańcucha, w tym przejścia przez 31 grudnia. Nie powstaje kopia TS.

### Integration Tests:

- `BudgetCollectedAtYearStartTest`: przykład prądu co do grosza w szkicu i w realizacji, zgodność z wierszem planu grudnia, przypadki bez podpisu, oznaczenie w styczniu, rok bez budżetu, zero w okresie, wiersz zarchiwizowany, stała liczba zapytań.
- `BudgetTest` i `IrregularCollectedTest` bez regresji.
- E2E: nowy `collected-year-start.spec.ts` i cały zestaw Playwright.

### Manual Testing Steps:

1. Na dev otwórz plan grudnia 2026 i zapisz „zebrane” z wiersza „Rekolekcji”.
2. Przełącz rok na 2027 (utwórz szkic, jeśli go nie ma) i sprawdź podpis „zebrane startowe X · prognoza” z tą samą kwotą.
3. Dodaj wydatek na „Rekolekcje” z datą w grudniu 2026, sprawdź, że liczba w szkicu 2027 spadła o jego kwotę (nie poniżej 0,00), i usuń wydatek.
4. Otwórz budżet 2026 i sprawdź, że „Rekolekcje” nie mają podpisu.
5. Sprawdź `/dev/kitchen-sink/budget` w obu motywach, w 1280 i 390 px.

## Performance Considerations

Strona Budżet dostaje 5 zapytań więcej tylko wtedy, gdy co najmniej jeden widoczny wiersz ma styczeń w okresie nieregularnym; zapytanie o operacje jest ograniczone do pozycji budżetów kategorii nieregularnych i dat od najwcześniejszego oznaczenia. Limit 2 s z `NfrPerf01Test` zostaje, ale ten test nie ćwiczy nowego odczytu (łańcuch jest tam pusty), więc kosztu pilnuje test stałej liczby zapytań z fazy 1.

## Migration Notes

Brak migracji, zmiany snapshotu i zmiany zapisanych danych. Wycofanie to cofnięcie commitów; żadna liczba nie ginie, bo nic nie jest zapisywane.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-27, linie 241-252)
- PRD: `context/foundation/prd-v3.md` (FR-012, decyzje strukturalne 6 i 9)
- ADR-0066, ADR-0073 (łańcuch i 31 grudnia), ADR-0060 (kafelek kategorii), ADR-0037 (Budżet bez odczytów miesięcznych), ADR-0075 (domyślny rok budżetu)
- Poprzednia zmiana: `context/archive/2026-11-11-irregular-expense-collected/plan.md`
- Słownik: `context/foundation/glossary.md` („Collected”, „Opening Collected”, „Irregular Rate”, „Budget”)
- Reguły: `.ai/rules/categories.md`, `inertia-props.md`, `js.md`, `css.md`, `reports.md`, `sloppy.md`
- Wzorce: `resources/js/components/MonthPlanItemRow.vue:138-193`, `tests/Feature/IrregularCollectedTest.php:122-134`, `tests/e2e/irregular-collected.spec.ts`, `tests/e2e/sidebar-year-refresh.spec.ts`
- Podgląd worktree do weryfikacji ręcznej: `context/foundation/agents/worktree-preview.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0076, słownik i odczyt w propsach Budżetu

#### Automated

- [ ] 1.1 Testy odczytu i propsów Budżetu przechodzą na PostgreSQL
- [ ] 1.2 Typy i build przechodzą
- [ ] 1.3 Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual

- [ ] 1.4 ADR-0076 i słownik opisują cztery decyzje z planowania bez sprzeczności z ADR-0066, ADR-0073, ADR-0060 i ADR-0037

### Phase 2: Podpis w wierszu, kitchen sink, E2E i bramka

#### Automated

- [ ] 2.1 Testy literałów UI i kluczy tłumaczeń przechodzą
- [ ] 2.2 E2E historii FR-012 i cały zestaw przechodzą
- [ ] 2.3 Pełna bramka na PostgreSQL

#### Manual

- [ ] 2.4 Na dev szkic 2027 pokazuje przy „Rekolekcjach” podpis równy „zebranemu” z planu grudnia 2026, a budżet 2026 go nie ma
- [ ] 2.5 `/dev/kitchen-sink/budget` pokazuje nowe stany w obu motywach, w 1280 i 390 px
- [ ] 2.6 Reguły, `design-system.md` i roadmapa opisują stan po S-27 bez sprzeczności z ADR-0076
