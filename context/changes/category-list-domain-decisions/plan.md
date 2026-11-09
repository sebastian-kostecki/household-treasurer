# Category List Domain Decisions Implementation Plan

## Overview

Element przygotowawczy F-03 z `context/foundation/roadmap.md`. Zapisuje decyzje domenowe jednej listy kategorii **przed kodem** (AGENTS.md, twarda reguła o słowniku i terminach _Avoid_). Nowy ADR-0063 zastępuje ADR-0014 i zmienia ADR-0016, ADR-0018, ADR-0030, ADR-0033 i ADR-0034. `context/foundation/glossary.md` dostaje kategorię przez lata, pozycję budżetu z planem rocznym, grupę kategorii, archiwum globalne i regułę migracji. Zmiana dotyczy wyłącznie dokumentów. Odblokowuje S-18 (migracja i widok „Kategorie”), S-20 (nowy budżet z listy), S-22 (grupy) i S-23 (podpowiedzi Importu), a także rozstrzyga Otwarte pytanie 3 z PRD v3.

## Current State Analysis

- **Kategoria należy do jednego budżetu i trzyma kwoty roku.** `database/migrations/2026_09_07_180001_create_categories_table.php:16-27` ma `budget_id` (cascade), `name`, `icon`, `color`, `sort_order`, `type`, `planned_amount_minor_units` (Current Plan), `initial_plan_minor_units`, `execution_minor_units` i `archived_at`. Flagi `is_paced` i `month_item_kind` doszły w późniejszych migracjach. Nie ma żadnej unikalności nazwy: reguła walidacji to tylko `required|string|max:255` (`app/Concerns/CategoryValidationRules.php:22`).
- **Operacja wskazuje kategorię i budżet.** `financial_operations.category_id` i `budget_id` mają `restrictOnDelete`, a `budget_id` jest kopiowane z kategorii (`app/Actions/ManageFinancialOperation.php:144-145`). Rok daty operacji musi być rokiem budżetu kategorii, a ten budżet musi być w wykonaniu (`app/Concerns/ExpenseValidationRules.php:140-152`, to samo w `IncomeValidationRules.php`). `month_plan_items.category_id` ma cascade (`2026_11_02_091922_create_month_plan_items_table.php:17`).
- **Usuwanie, archiwum, typ.** Kategorię można usunąć tylko w szkicu (`app/Policies/CategoryPolicy.php:17`), a zarchiwizować tylko w wykonaniu (`CategoryPolicy.php:20-25`). W wykonaniu nie da się zmienić typu (`app/Http/Requests/UpdateCategoryRequest.php:74-76`). Kategorię dodaną w trakcie roku `store` zapisuje z planem początkowym 0 (`app/Http/Controllers/CategoryController.php:29-31`).
- **Nowy budżet kopiuje kategorie.** `app/Actions/CreateBudget.php:31-49` kopiuje niezarchiwizowane kategorie wybranego budżetu źródłowego: nazwę, typ, ikonę, kolor, kolejność, flagi i Current Plan jako plan szkicu. Nie zapisuje powiązania ze źródłem (ADR-0014).
- **Łączenie lat po nazwie i typie, zawsze dokładne** (bez zmiany wielkości liter i bez przycinania spacji):
  - propozycja stycznia z grudnia: `app/Support/MonthPlanProposal.php:195-212`,
  - filtr Operacji: `app/Support/OperationsListQuery.php:139-147` i `OperationsFilterOptions.php:32-64`,
  - chip filtra: `resources/js/components/OperationsActiveFilterChips.vue:170-181`,
  - podpowiedź Importu w dwóch etapach: `app/Support/CsvImport/ImportCategorySuggester.php:75-127`, `ImportCategorySuggestionModel.php:61,121-161`,
  - linki z Dashboardu i palety poleceń (`resources/js/lib/dashboard-categories.ts:34-49`, `components/DashboardNotices.vue:186-200`).
- **Dane na devie** (zapytanie do bazy, 2026-11-09): budżet 2026 w wykonaniu ma dwie „Rozrywki” (#23: 35 operacji, plan 2 400; #46: 2 operacje, plan 3 000) i dwa „Odsetki” (#5: 24 operacje; #41: 1 operacja). Szkic 2027 ma jedną „Rozrywkę” (#70, skopiowaną z #23) i jedne „Odsetki”. „Wakacje” są zarchiwizowane w 2026 (0 operacji, plan 0). Szkic 2027 (38 kategorii, skopiowany z #1–#39 bez „Wakacji”) nie ma ośmiu niezarchiwizowanych kategorii 2026 #40–#47 z 2026-11-02, które mają operacje i pozycje Month Plan: Pensja (plan 102 000 zł), Odsetki #41, Żywność, Mieszkanie (30 000 zł), Transport, Zdrowie, Rozrywka #46, Inne.
- **Snapshot ma wersję 5** (`app/Actions/BackupUserData.php:18`). Kategorie są w nim płaską listą z `budget_id`. Starsze wersje restore tylko uzupełnia domyślnymi wartościami (`app/Actions/RestoreUserData.php:79-97,424-443`).
- **Pojęcia tylko w dokumentach.** Earmark, Reserve, Year Close, Cash Expectation i Agenda nie istnieją w `app/` ani w migracjach (jedyny ślad: chroniony `Schema::hasTable('cash_expectations')` w `app/Actions/ResetUserData.php:18-22`). Słownik definiuje je na kategorii należącej do budżetu:
  - Earmark: „keyed by expense Category name and type, not by a Category id” (`glossary.md:124`),
  - Cash Expectation: „Category’s Budget year” (`:136`),
  - Year Close: „Aligns every expense Category of that Budget” (`:116`),
  - Reserve: _Avoid_ „a Category identity that spans years” (`:121`).
- **Wpisy słownika, które staną się sprzeczne:**
  - Category (`glossary.md:35-37`, w tym _Avoid_ „a Category identity that spans years”),
  - Initial Plan (`:27-29`), Current Plan (`:31-33`), Execution (`:47-49`),
  - Paced Category (`:39-41`), One-off Category (`:43-45`), Month Item Kind (`:75-77`, „Copied onto a new Budget … ADR-0014”),
  - Month Plan (`:63-65`, „Items are the Categories of that Budget”),
  - Financial Operation (`:127-129`), Cash Expectation (`:135-137`), Year Close, Reserve, Earmark (`:115-125`),
  - Import category suggestion (`:203-205`).
- **Format** (wzór: ADR-0033, ADR-0062; precedens odsyłacza: ostatnia linia ADR-0024). ADR to `context/foundation/adr/NNNN-slug.md` z tytułem H1 i akapitami: kontekst, decyzja, odrzucone opcje. Wpis słownika to `**Term**:`, definicja, „Polish UI copy: …” i wiersz `_Avoid_:`. Dokumenty są po angielsku, polskie copy UI w cudzysłowach „…”.
- **Numer ADR:** najwyższy to 0062, więc następny wolny to **0063** (`stack-assessment.md:106` mówi to samo; numer 0008 jest zdublowany, bez wpływu tutaj).

## Desired End State

- `context/foundation/adr/0063-one-category-list-across-years.md` zapisuje: listę kategorii użytkownika, pozycję budżetu (Budget Line) z planem rocznym, regułę dołączania pozycji, unikalność i normalizację nazw, archiwum globalne, usuwanie, zmianę typu, grupę kategorii, regułę migracji i konwersji snapshotu, nowe brzmienie klauzul ADR-0016/0018/0030/0033/0034 oraz odrzucone opcje.
- ADR-0014, ADR-0016, ADR-0018, ADR-0030, ADR-0033 i ADR-0034 mają na końcu jedną nową linię odsyłającą do ADR-0063. Ich dotychczasowe akapity są nietknięte.
- `glossary.md` ma nowe wpisy **Budget Line** i **Category Group** oraz przepisany wpis **Category** (bez _Avoid_ „a Category identity that spans years”). Sprzeczne wpisy z listy powyżej dostają minimalne dopiski. Fraza „identity that spans years” nie występuje w słowniku nigdzie.
- S-18 może wdrożyć migrację i widok „Kategorie”, S-20 tworzenie budżetu, S-22 grupy, a S-23 podpowiedzi Importu bez nowych decyzji domenowych.

### Key Decisions:

Ustalone w tej sesji planowania (pozostałe pochodzą z PRD v3, decyzje strukturalne 1 i 4, FR-002…FR-006, FR-019, FR-022, FR-023):

1. **Plan roczny na pozycji budżetu, która dołącza sama.**
   - **Budget Line** to jedna kategoria listy w jednym budżecie. Trzyma Initial Plan, Current Plan i Execution, a do czasu rytmu (F-05) także flagi Paced / One-off i Month Item Kind.
   - Nowy budżet dostaje pozycje z aktywnych kategorii listy (plan podpowiadany z Current Plan poprzedniego roku, FR-005).
   - Kategoria utworzona albo przywrócona na liście dołącza do każdego budżetu-szkicu z planem 0.
   - Do budżetu w wykonaniu, a także do szkicu, w którym jej brakuje (po „Usuń” albo po migracji), dołącza z planem 0 przy pierwszym użyciu w tym roku: Financial Operation (ręcznie lub z Importu), Cash Expectation, kwota w Month Plan albo plan wpisany w Budżecie.
   - Przypadek graniczny: „Domeny” utworzone 10 grudnia + operacja 80 zł z datą 2026-12-10 → pozycja 2026 z planem 0 i wykonaniem 80 oraz pozycja w szkicu 2027 z planem 0. Budżet 2025 bez zmian.
2. **Duplikaty w jednym budżecie rozdzielone z dopiskiem.** W każdym budżecie rekordy o tym samym kluczu (znormalizowana nazwa + typ) dostają numer kolejny według rosnącego id. Rekord nr 1 łączy się z kategorią listy o tej nazwie, nr *n* ≥ 2 z kategorią „<nazwa> (k)”, gdzie k to (n−1)-ty wolny numer ≥ 2. Numer jest zajęty, gdy klucz „<nazwa> (k)” ma dowolny oryginalny rekord użytkownika tego samego typu w dowolnym budżecie, więc nr *n* dostaje to samo k w każdym budżecie. Nazwę przed dopiskiem skraca się tak, żeby całość mieściła się w 255 znakach. Na devie: 2026 #23 i 2027 #70 → „Rozrywka”, 2026 #46 → „Rozrywka (2)”, analogicznie „Odsetki (2)”. Żadna pozycja nie zmienia planu ani wykonania. Porządki robi potem scalanie (FR-023, S-21).
3. **Nazwy bez wielkości liter i spacji, unikalne.** Klucz nazwy to nazwa po przycięciu spacji i ujednoliceniu wielkości liter (Unicode). Polskie znaki się liczą: „Prad” ≠ „Prąd”. Para (klucz nazwy, typ) jest unikalna na całej liście użytkownika, także wśród archiwalnych. Utworzenie albo zmiana nazwy na zajętą nazwę archiwalnej kategorii jest odrzucane z podpowiedzią przywrócenia. Przychód „Zwrot” i wydatek „Zwrot” mogą istnieć obok siebie.
4. **Aktywna = w najnowszym budżecie albo w najnowszym budżecie w wykonaniu.**
   - Po migracji kategoria listy jest aktywna tylko wtedy, gdy ma niezarchiwizowaną pozycję w najnowszym budżecie użytkownika (najwyższy rok, szkic też się liczy) albo w jego najnowszym budżecie w wykonaniu; inaczej trafia do archiwum.
   - Migracja nie dodaje pozycji do szkiców: kategoria aktywna bez pozycji w szkicu dołącza do niego przy pierwszym użyciu (Key Decision 1), tak jak po „Usuń” w szkicu (Key Decision 6).
   - Pisownia nazwy, ikona, kolor i kolejność pochodzą z najnowszego budżetu, który ma tę kategorię.
   - Na devie: „Wakacje” (archiwum 2026, brak w 2027) → archiwalna; #40–#47 (aktywne w 2026, brak w szkicu 2027) → aktywne, szkic 2027 bez zmian; kategoria tylko z 2025 → archiwalna.
   - Flagi zostają na pozycjach budżetu bez zmian, więc „do wydania” żadnego miesiąca się nie zmienia.
5. **Grupa przez lata.** Category Group jest atrybutem kategorii listy, więc przeniesienie działa we wszystkich latach i raportach (raport 2025 nie odtwarza dawnych grup). Grupa ma typ i przyjmuje tylko kategorie tego typu, nie ma planu ani ustawień, a jej nazwa jest unikalna w obrębie typu (ta sama normalizacja). Usunięcie grupy zostawia jej kategorie bez grupy; grupa nie ma archiwum. Po migracji grup nie ma.
6. **Usuwanie tylko nieużywanej.** „Usuń” w budżecie-szkicu zdejmuje wyłącznie pozycję tego budżetu, a kategoria zostaje na liście. Kategorię listy można usunąć tylko wtedy, gdy nie ma Financial Operation w żadnym roku, żadnej Cash Expectation i żadnej pozycji w budżecie w wykonaniu; usunięcie zdejmuje też jej pozycje w szkicach. W pozostałych przypadkach jest archiwum albo scalenie.
7. **Earmark, Reserve, Year Close i Cash Expectation przepisane minimalnie teraz.** Zamiast „nazwa + typ” jest kategoria listy, zamiast „kategoria budżetu” pozycja budżetu. ADR-0018 i ADR-0030 dostają linię „Amended by ADR-0063”.
8. **Archiwum na dwóch poziomach.**
   - Archiwum kategorii listy (globalne) decyduje tylko o wyborze (operacje, Month Plan, nowy budżet).
   - Budget Line ma własne `archived_at`. Sumy Budżetu, liczniki Dashboardu, sekcja archiwum Budżetu i propozycja Month Plan (`MonthPlanProposal.php:140`) czytają stan pozycji, nie listy.
   - Archiwizacja kategorii listy zdejmuje jej pozycje w szkicach i ustawia `archived_at` na pozycji w budżecie w wykonaniu bieżącego roku kalendarzowego. Pozycje w innych budżetach się nie zmieniają.
   - Przywrócenie czyści `archived_at` tej pozycji i dodaje pozycje w szkicach (Key Decision 1).
   - Migracja przenosi `archived_at` każdej dawnej kategorii na jej pozycję. Data archiwum kategorii listy to najpóźniejsze `archived_at` jej pozycji, a gdy żadna nie jest zarchiwizowana, moment migracji. Sumy żadnego roku się nie zmieniają (FR-021).
   - Przykład: „Wakacje” (archiwum 2026-09-09) → pozycja 2026 z tą datą, w sekcji archiwum Budżetu 2026 jak dziś. Archiwum „Rekolekcji” w marcu 2027 → pozycja w budżecie 2027 w wykonaniu wypada z sum 2027, Budżet 2026 bez zmian.

Ustalone przez agenta, bez osobnego pytania:

- **Jeden ADR.** Wzorem F-01 powstaje jeden ADR-0063, a zmieniane ADR-y dostają tylko linię odsyłającą.
- **Zmiana typu.** Zablokowana tym samym predykatem co usuwanie (Key Decision 6): jakakolwiek Financial Operation w dowolnym roku (FR-002), jakakolwiek Cash Expectation (dzisiejsza reguła ADR-0018) albo pozycja w budżecie w wykonaniu (dzisiejszy `UpdateCategoryRequest.php:74-76`). Zmiana typu na zajęty klucz (klucz nazwy, nowy typ) jest odrzucana, a zmiana typu zdejmuje kategorię z grupy (Key Decision 5).
- **Snapshot.** Wersje 1–5 przy restore przechodzą tę samą konwersję co migracja: punkty 2–4 oraz flagi na pozycje (FR-022).
- **Nowe brzmienie klauzul:**
  - ADR-0016: filtr Operacji po kategorii listy (id) przez wszystkie lata, a zmiana nazwy nie dzieli już historii.
  - ADR-0034: głosy liczone po kategorii listy, nie po parze nazwa + typ. Celem jest zwycięska kategoria, jeśli jest aktywna, a rok daty wiersza ma budżet w wykonaniu. Bez takiego budżetu podpowiedzi nie ma; pozycja dołącza przy commicie (punkt 1). Próg, k = 5 i remisy bez zmian.
  - ADR-0033: styczeń czyta grudzień tej samej kategorii listy w budżecie poprzedniego roku; reszta reguły propozycji bez zmian.

## What We're NOT Doing

- Żadnego kodu, migracji, testów ani tłumaczeń w `lang/`.
- **Rytm** (cztery rytmy, mapowanie flag, gdzie rytm mieszka, „zebrane”, 31 grudnia) należy do F-05 (`rhythm-and-irregular-decisions`). Tutaj zapisujemy tylko, że flagi zostają na pozycji budżetu, dopóki F-05 tego nie zmieni.
- **Reguły w `.ai/rules`** (migracje danych, tożsamość kategorii po id, konwersja snapshotu) i kopia bazy przed migracją należą do F-04 (`safe-first-data-migration`).
- **Scalanie kategorii (FR-023):** bez predykatów scalania pozycji w tym samym budżecie i bez podglądu liczb. To S-21. ADR wymienia scalanie tylko jako drogę porządków po dopiskach „(n)” i literówkach.
- **Porządki w danych przed migracją** (Otwarte pytanie 4) zostają decyzją użytkownika przy S-23.
- Bez zmian w PRD, `prd-mvp.md`, `.docs/specs` i `.ai/rules/*`, które dziś opisują łączenie po nazwie. Zmienią je wycinki razem z kodem, bo dokumenty nie mają opisywać więcej niż kod. Wyjątkiem jest słownik, który AGENTS.md każe zmienić przed kodem.
- Bez UI: jak Budżet w wykonaniu pokazuje aktywne kategorie bez pozycji, wygląd sekcji archiwum, komunikaty walidacji. To S-18, S-19 i S-20.
- Bez przebudowy wpisów słownika niezwiązanych z kategorią.
- Bez naprawy zdublowanego numeru ADR-0008.

## Implementation Approach

Najpierw decyzja (ADR-0063 z odsyłaczami), potem słownik, który się na nią powołuje. Oba dokumenty po angielsku, jak reszta `context/foundation/adr/` i `glossary.md`. Każda reguła migracji jest zapisana jednoznacznie (kolejność według id, najnowszy budżet, klucz nazwy, numer „(n)”), bo S-18 ma ją wdrożyć dosłownie i sprawdzić testem migracji na PostgreSQL. Przykłady liczbowe pochodzą z danych na devie, więc staną się przypadkami testowymi S-18.

## Critical Implementation Details

- **Spójność predykatów.** Te same sformułowania muszą brzmieć identycznie w ADR-0063 i `glossary.md`: „trimmed and case-insensitive (Unicode); diacritics count”, „ordered by ascending id”, „the newest Budget (highest year, draft included)”, „joins with plan 0”. Weryfikacja 2.2 szuka tych fraz.
- **Odsyłacze wyłącznie dopisane.** Linie „Superseded by / Amended by ADR-0063” dochodzą na końcu plików. Istniejących akapitów ADR-ów nie edytujemy (precedens ADR-0024 → ADR-0033); weryfikacja 1.3 to sprawdza.

## Phase 1: ADR-0063 i odsyłacze

### Overview

Zapisuje decyzję, która zastępuje „kategorię należącą do jednego budżetu” i kopiowanie kategorii (ADR-0014), oraz nowe brzmienie klauzul pięciu ADR-ów, które łączyły lata po nazwie.

### Changes Required:

#### 1. Nowy ADR

**File**: `context/foundation/adr/0063-one-category-list-across-years.md`

**Intent**: Zapisać, że kategoria jest jedna dla użytkownika przez lata, a plan roczny mieszka na pozycji budżetu. Opisać, jak migracja i restore starego snapshotu przechodzą na ten model bez zmiany żadnej kwoty i dlaczego odrzucono alternatywy. Czytelnik S-18…S-23 nie może potrzebować tej rozmowy.

**Contract**: Tytuł (H1) plus akapity, gęstość jak ADR-0033. Musi stwierdzić:

- **Kontekst.** Kategoria należała do budżetu, a lata łączyło zapytanie po nazwie i typie w pięciu miejscach kodu. PRD v3 (FR-001, FR-006) wymaga jednej listy.
- **Model.** Category należy do użytkownika i ma nazwę, ikonę, kolor, kolejność, typ, opcjonalną Category Group i opis (FR-002). Budget Line to jedna Category w jednym Budget, a Financial Operation, Cash Expectation i pozycja Month Plan wskazują Category w Budget roku swojej daty lub miesiąca. Do czasu rytmu (F-05) flagi Paced / One-off i Month Item Kind są na Budget Line.
- **Dołączanie pozycji:** Key Decision 1, z przykładem „Domeny”.
- **Nazwy:** Key Decision 3, dosłownie.
- **Archiwum globalne:** PRD, decyzja strukturalna 4 i FR-003/FR-019. Archiwalna kategoria znika z wyboru w operacjach, Month Plan i nowym budżecie. Zostaje w historii, w budżetach i raportach lat z wykonaniem, a na istniejącej operacji jest widoczna w selektorze tylko wtedy, gdy już jest wybrana. Przywrócenie zwraca ją do wyboru i dodaje pozycje w szkicach (Key Decision 1). Dwa poziomy archiwum (lista i Budget Line) z Key Decision 8, z przykładem „Wakacje” / „Rekolekcje”.
- **Typ i usuwanie:** blokada zmiany typu (Key Decisions, część „Ustalone przez agenta”) i Key Decision 6.
- **Grupa:** Key Decision 5.
- **Migracja i snapshot** (FR-006, FR-022): Key Decisions 2 i 4, w kolejności kroków:
  - (a) klucz nazwy,
  - (b) numer kolejny według id w każdym budżecie i dopisek „(n)”,
  - (c) atrybuty z najnowszego budżetu, który ma kategorię,
  - (d) aktywna tylko z niezarchiwizowaną pozycją w najnowszym budżecie użytkownika albo w jego najnowszym budżecie w wykonaniu; migracja nie dodaje pozycji do szkiców,
  - (e) każda dawna kategoria staje się pozycją budżetu z tymi samymi kwotami, flagami i `archived_at` (Key Decision 8), a operacje i pozycje Month Plan wskazują tę samą kategorię listy w tym samym budżecie,
  - (f) grupy puste, opis pusty.

  Restore snapshotu w wersjach 1–5 przechodzi te same kroki. Przykład z deva: #23 i #70 → „Rozrywka”, #46 → „Rozrywka (2)” aktywna, „Wakacje” → archiwalna, Pensja i pozostałe #40–#47 → aktywne bez pozycji w szkicu 2027.
- **ADR-0014 zastąpiony w całości:** nowy budżet bierze aktywne kategorie listy z planem z Current Plan poprzedniego roku (FR-005); nie ma już kopiowania rekordów.
- **Nowe brzmienie klauzul ADR-0016, ADR-0033 i ADR-0034:** dosłownie jak w Key Decisions, część „Ustalone przez agenta”.
- **ADR-0018:** „date year equals the Category’s Budget year” → rok daty musi mieć Budget (szkic albo wykonanie), a Category dołącza do niego jako Budget Line; blokada usunięcia i zmiany typu zostaje.
- **ADR-0030:** Earmark jest kluczowany kategorią listy, domyślne zastosowanie idzie na tę samą Category, a użytkownik może wybrać inną; Align po Year Close dotyczy każdej wydatkowej Budget Line tego Budget.
- **Odrzucone opcje:**
  - każda kategoria w każdym budżecie (stare lata pokazują kategorie, których nie było),
  - blokowanie operacji na kategorii bez pozycji (dodatkowy krok, także w Imporcie),
  - scalanie duplikatów w migracji (zmienia kwoty pozycji) i zatrzymanie migracji (ręczna praca wbrew FR-006),
  - dokładne porównanie nazw i lista bez unikalności (niejednoznaczny Import i porównanie lat),
  - archiwum tylko po jawnym archiwum ostatniego roku (stare kategorie wracają do selektorów),
  - grupa per rok (porównanie lat myli przeniesienia z wydatkami),
  - brak usuwania i usuwanie z pozycjami w budżecie w wykonaniu,
  - tożsamość przez lata jako kolumna „lineage” na rekordach per budżet (dalej dwie kategorie do edycji),
  - przeniesienie flag z najnowszego budżetu na listę w S-18 (zmieniłoby „do wydania” bieżącego miesiąca).

#### 2. Odsyłacze w zmienianych ADR-ach

**Files**: `context/foundation/adr/0014-copy-categories-as-new-records-on-budget-create.md`, `0016-operations-list-filters-by-category-name-and-type.md`, `0018-cash-expectation-available-limit-and-budget-year.md`, `0030-year-close-posts-year-result-into-reserve.md`, `0033-month-plan-beside-annual-budget.md`, `0034-import-category-suggestion-from-word-weights.md`

**Intent**: Agent czytający sam stary ADR nie może uznać łączenia po nazwie ani kopiowania kategorii za obowiązujące.

**Contract**: Jedna nowa linia na końcu każdego pliku, nazywająca zmienioną klauzulę:

- ADR-0014: „Superseded by ADR-0063: …”, bo zastąpiony w całości,
- ADR-0016, ADR-0034: „Superseded in part by ADR-0063: …”,
- ADR-0018, ADR-0030, ADR-0033: „Amended by ADR-0063: …”.

Istniejące akapity bez zmian.

### Success Criteria:

#### Automated Verification:

- ADR istnieje i numer 0063 jest jedyny: `ls context/foundation/adr | grep -c '^0063-'` zwraca `1`
- ADR-0063 powołuje się na każdy zmieniany ADR: `for n in 0014 0016 0018 0030 0033 0034; do grep -q "ADR-$n" context/foundation/adr/0063-*.md || echo "missing $n"; done` nic nie wypisuje
- Każdy zmieniany ADR odsyła do ADR-0063, a jego dotychczasowe akapity są nietknięte (po commicie fazy; `8637bbb` to baza zmiany): `for n in 0014 0016 0018 0030 0033 0034; do grep -q 'ADR-0063' context/foundation/adr/$n-*.md || echo "no link $n"; git diff 8637bbb HEAD -- context/foundation/adr/$n-*.md | grep -c '^-[^-]' | grep -qx 0 || echo "edited $n"; done` nic nie wypisuje

#### Manual Verification:

- ADR-0063 zawiera każdy punkt z Contract (model, dołączanie pozycji, nazwy, archiwum, typ i usuwanie, grupa, migracja a–f ze snapshotem, nowe brzmienie pięciu klauzul, odrzucone opcje) i nie przeczy PRD v3 (decyzje strukturalne 1 i 4, FR-001…FR-006, FR-019, FR-022)
- Przykład z deva przeliczony według kroków a–f bez zaglądania do planu daje: „Rozrywka” (#23, #70), „Rozrywka (2)” (#46), „Odsetki”, „Odsetki (2)”, „Wakacje” jako jedyna archiwalna; Pensja, Mieszkanie i pozostałe #40–#47 aktywne bez pozycji w szkicu 2027; plan i wykonanie każdej pozycji 2026 bez zmian
- Czytelnik każdego z sześciu starych ADR-ów widzi, która klauzula przestała obowiązywać

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Phase 2: Słownik

### Overview

Dodaje terminy jednej listy i usuwa ze słownika każdą sprzeczność z ADR-0063 minimalnymi dopiskami.

### Changes Required:

#### 1. Nowe i przepisane wpisy

**File**: `context/foundation/glossary.md`

**Intent**: Dać S-18…S-23 jednoznaczne terminy i predykaty. **Budget Line** wstawić po **Current Plan**, a **Category Group** po **Category**. Format jak istniejące wpisy.

**Contract**:

- **Category** (przepisany):
  - Bucket przychodu lub wydatku należący do użytkownika, jeden przez wszystkie lata.
  - Ma nazwę, ikonę, kolor, kolejność, typ, opcjonalną Category Group i opis.
  - Plan i wykonanie roku są na jej Budget Line.
  - Unikalna para (klucz nazwy, typ), z regułą klucza dosłownie jak w Critical Implementation Details.
  - Archiwum globalne z przywracaniem (zdania z ADR-0063).
  - Zmiana typu zablokowana tym samym predykatem co usuwanie (Financial Operation, Cash Expectation lub Budget Line w budżecie w wykonaniu), odrzucana na zajęty klucz, zdejmuje kategorię z grupy.
  - Usuwanie tylko nieużywanej (Key Decision 6).
  - Jedno zdanie o migracji: dawne kategorie o tym samym kluczu nazwy i typie w różnych budżetach stają się jedną Category (ADR-0063).
  - Zachowane zdania o Dashboardzie, sekcji archiwum Budżetu i puli Month Remaining, przeformułowane na zarchiwizowaną Budget Line (Key Decision 8).
  - _Avoid_: Tag, Label, Envelope, Category template, Category kind, Budget Line (for this list item), a per-Budget Category.
- **Budget Line** (nowy):
  - Jedna Category w jednym Budget; trzyma Initial Plan, Current Plan i Execution tego roku, a do czasu rytmu (F-05) flagi Paced / One-off i Month Item Kind.
  - Reguła dołączania z Key Decision 1, z „joins with plan 0”.
  - „Usuń” w szkicu zdejmuje tylko Budget Line.
  - Własny stan archiwum, który czytają sumy, Dashboard, sekcja archiwum Budżetu i propozycja Month Plan; ustawia go archiwum kategorii w budżecie w wykonaniu bieżącego roku (Key Decision 8).
  - Zdanie dosłownie: „A Category of a Budget elsewhere in this glossary means a Category with a Budget Line on that Budget.” (obejmuje m.in. Month Remaining i Available Limit bez osobnych dopisków).
  - Brak copy UI: UI pokazuje kategorię.
  - _Avoid_: Budget Category, Category (for this per-year record), Budget item, Month Plan item.
- **Category Group** (nowy):
  - Opcjonalna nazwana grupa kategorii jednego typu, przez wszystkie lata.
  - Bez planu i ustawień; sumuje plan i wykonanie swoich kategorii w Month Plan, Budget i raportach.
  - Nazwa unikalna w obrębie typu; usunięcie zostawia kategorie bez grupy.
  - Polish UI copy: „grupa”.
  - _Avoid_: Category (for the group), parent Category, subcategory, Envelope, a plan on a group.

#### 2. Dopiski w istniejących wpisach

**File**: `context/foundation/glossary.md`

**Intent**: Usunąć sprzeczności z ADR-0063 bez przeredagowania wpisów.

**Contract**:

- **Initial Plan** / **Current Plan** / **Execution:** kwoty są per Budget Line; Align działa na Budget Line.
- **Paced Category** / **One-off Category** / **Month Item Kind:** flaga jest na Budget Line (ADR-0063) do czasu rytmu (F-05). W Month Item Kind „Copied onto a new Budget like the Paced flag (ADR-0024:3; `CreateBudget.php:44`)” zamienić na: nowy budżet bierze flagi z Budget Line tej Category w poprzednim roku, a gdy jej nie ma, wartości domyślne.
- **Month Plan:** „Items are the Categories of that Budget” → „Items are the Categories with a Budget Line on that Budget”.
- **Financial Operation:** Category musi mieć typ zgodny z operacją, a budżet roku daty musi być w wykonaniu; Category dołącza do niego jako Budget Line.
- **Cash Expectation:** brzmienie z ADR-0063 punkt ADR-0018.
- **Year Close:** Align każdej wydatkowej Budget Line tego Budget.
- **Earmark:** „keyed by expense Category name and type, not by a Category id” → kluczowany wydatkową Category; domyślne zastosowanie na tę samą Category, z możliwością wyboru innej. Zdanie „Matching is a query over name and type” usunąć.
- **Reserve:** z _Avoid_ usunąć „a Category identity that spans years”; „expense Current Plan on a later Budget” → na wydatkowej Budget Line późniejszego Budget.
- **Import category suggestion:** uczy się po Category, nie po parze nazwa + typ. Celem jest zwycięska Category, jeśli jest aktywna, a rok daty wiersza ma Budget w wykonaniu. Usunąć „(a query, not a Category identity)”.

### Success Criteria:

#### Automated Verification:

- Nowe terminy mają wpis: `for t in 'Category' 'Budget Line' 'Category Group'; do grep -q "^\*\*$t\*\*:" context/foundation/glossary.md || echo "missing $t"; done` nic nie wypisuje
- Predykaty są zapisane dosłownie w obu dokumentach: `for p in 'case-insensitive' 'diacritics count' 'ascending id' 'joins with plan 0'; do grep -q "$p" context/foundation/glossary.md && grep -q "$p" context/foundation/adr/0063-*.md || echo "missing $p"; done` nic nie wypisuje
- Stara zasada zniknęła ze słownika: `grep -c 'identity that spans years\|not by a Category id\|query over name and type\|belongs to one Budget' context/foundation/glossary.md` zwraca `0`
- Diff obejmuje tylko dokumenty tej zmiany (plus artefakty zmiany i roadmapę): po commicie fazy `git diff --name-only 8637bbb HEAD -- . ':!context/changes' ':!context/foundation/roadmap.md'` zwraca wyłącznie `glossary.md`, `0063-…` i sześć zmienianych ADR-ów

#### Manual Verification:

- Przypadek „Domeny” przeliczony według wpisów Category i Budget Line daje pozycję 2026 z planem 0 i wykonaniem 80, pozycję w szkicu 2027 z planem 0 i brak zmian w 2025
- Przypadki nazw z wpisu Category: „prąd” / „Prąd” / „Prąd␣” → jedna kategoria; „Prad” i „Prąd” → dwie; nowy „prąd” przy archiwalnym „Prąd” → odmowa z podpowiedzią przywrócenia; przychód i wydatek „Zwrot” → dwie
- Przypadki zmiany typu: „Rekolekcje” z pozycją w budżecie 2026 w wykonaniu → zablokowana; wydatek „Zwrot” tylko w szkicu → przychód przy istniejącym przychodzie „Zwrot” → odmowa
- Przypadki usuwania: „Domeny” bez operacji, tylko w szkicu → usuwalna; „Wakacje” z pozycją w budżecie 2026 w wykonaniu → tylko archiwum; „Prąd” z operacjami → tylko archiwum lub scalenie
- Żaden z poprawionych wpisów nie przeczy ADR-0063; Earmark, Reserve, Year Close i Cash Expectation nie łączą już niczego po nazwie
- Słownik nie urósł poza dwa nowe wpisy, przepisany Category i dopiski w wymienionych wpisach

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Brak, bo to zmiana w dokumentach. Przykłady z Key Decisions są gotowymi przypadkami dla testu migracji S-18 na PostgreSQL: duplikaty „Rozrywka” / „Odsetki”, „Wakacje” archiwalna, kategoria tylko z 2025, „prąd” / „Prąd” / „Prąd␣”, „Prad” / „Prąd”.

### Integration Tests:

- Brak. Test snapshotu v5 z tą samą nazwą w dwóch budżetach (`stack-assessment.md:166`) należy do S-18.

### Manual Testing Steps:

1. Przeczytać ADR-0063 obok PRD v3 (decyzje strukturalne 1 i 4, FR-001…FR-006, FR-019, FR-022, FR-023): każda decyzja ma odpowiednik albo świadome pominięcie z „What We're NOT Doing”.
2. Przeliczyć przykład z deva i przypadek „Domeny” według słownika, bez zaglądania do planu.
3. Sprawdzić przypadki graniczne:
   - trzy „Rozrywki” w jednym budżecie przy istniejącej już nazwie „Rozrywka (2)” → „Rozrywka (3)” dla rekordu nr 2 i „Rozrywka (4)” dla nr 3,
   - przywrócenie archiwalnej kategorii przy istniejącym szkicu → pozycja w szkicu z planem 0,
   - operacja z datą w roku, którego budżet jest szkicem → błąd jak dziś, bez pozycji,
   - archiwum „Rekolekcji” w marcu 2027 → sumy 2027 bez niej, sumy 2026 bez zmian; „Wakacje” po migracji w sekcji archiwum Budżetu 2026.

## Performance Considerations

Nie dotyczy.

## Migration Notes

Nie dotyczy tej zmiany, bo dane się nie zmieniają. Wycofanie to revert commitów dokumentacyjnych. Reguła migracji danych jest treścią ADR-0063, a wdroży ją S-18 po F-04.

## References

- Roadmapa: `context/foundation/roadmap.md` (F-03, S-18, S-20, S-21, S-22, S-23, Otwarte pytanie 3)
- PRD: `context/foundation/prd-v3.md` (Decyzje strukturalne 1, 4, 10; FR-001…FR-006, FR-019, FR-021…FR-023; „Wymogi istniejącego systemu”; „Migracja danych”)
- Ocena stosu: `context/foundation/stack-assessment.md:103-108,152-166` (miejsca łączenia po nazwie, reguły do F-04)
- ADR-y: `0014`, `0016`, `0018`, `0030`, `0033`, `0034`; wzór gęstości `0033`, `0062`; precedens odsyłacza `0024` (ostatnia linia)
- Plan F-01 jako wzór zmiany dokumentacyjnej: `context/archive/2026-11-02-month-plan-domain-decisions/plan.md`
- Kod: `app/Actions/CreateBudget.php:31-49`, `app/Concerns/ExpenseValidationRules.php:140-152`, `app/Policies/CategoryPolicy.php:17-30`, `app/Support/MonthPlanProposal.php:195-212`, `app/Support/OperationsListQuery.php:139-147`, `app/Support/CsvImport/ImportCategorySuggester.php:75-127`, `app/Actions/BackupUserData.php:18`, `app/Actions/RestoreUserData.php:79-97`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0063 i odsyłacze

#### Automated

- [x] 1.1 ADR istnieje i numer 0063 jest jedyny — 629f76d
- [x] 1.2 ADR-0063 powołuje się na każdy zmieniany ADR — 629f76d
- [x] 1.3 Każdy zmieniany ADR odsyła do ADR-0063, a jego dotychczasowe akapity są nietknięte — 629f76d

#### Manual

- [x] 1.4 ADR-0063 zawiera każdy punkt z Contract i nie przeczy PRD v3 — 629f76d
- [x] 1.5 Przykład z deva przeliczony według kroków a–f daje oczekiwaną listę bez zmiany kwot — 629f76d
- [x] 1.6 Czytelnik każdego z sześciu starych ADR-ów widzi, która klauzula przestała obowiązywać — 629f76d

### Phase 2: Słownik

#### Automated

- [x] 2.1 Nowe terminy mają wpis
- [x] 2.2 Predykaty są zapisane dosłownie w obu dokumentach
- [x] 2.3 Stara zasada zniknęła ze słownika
- [ ] 2.4 Diff obejmuje tylko dokumenty tej zmiany

#### Manual

- [x] 2.5 Przypadek „Domeny” daje oczekiwane pozycje
- [x] 2.6 Przypadki nazw z wpisu Category dają oczekiwany wynik
- [x] 2.7 Przypadki zmiany typu dają oczekiwany wynik
- [x] 2.8 Przypadki usuwania dają oczekiwany wynik
- [x] 2.9 Żaden z poprawionych wpisów nie przeczy ADR-0063
- [x] 2.10 Słownik nie urósł poza zakres zmiany
