# Wczytanie lat 2020 i 2021 ze starszych arkuszy budżetu — plan implementacji

## Overview

Sheet Load (ADR-0085, `budget:load-sheet`) ma wczytać `Budżet_2020.xlsx` i `Budżet_2021.xlsx`. Oba pliki mają ten sam, starszy układ (bez arkusza `Ustawienia`, przychody w H, listy w I–L), który dziś jest odrzucany („Ustawienia: brak arkusza”). Zmiana dodaje ten układ do czytnika, nowy cel mapowania „ruch funduszu” (wpłaty do funduszu celowego i zwroty z niego nie są operacjami, ale liczą się do uzgodnienia z „Analizą”), 29 nowych etykiet mapowania, poprawia błąd formuły w pliku 2021 u źródła i kończy się wczytaniem obu lat na produkcji. Rok 2019 jest poza zakresem (inny skoroszyt).

## Current State Analysis

Pełne ustalenia: `context/changes/sheet-load-2020-2021/research.md`. Najważniejsze:

- **Czytnik zna tylko układ 2022–2025.** Rok z `Ustawienia!B2` (`app/Support/SheetLoad/BudgetSheetReader.php:25-30`); brak arkusza → `brak arkusza` (`:63-71`). `MonthSheetReader` szuka bloków po etykietach, ale litery kolumn są stałymi: przychody G/I (`MonthSheetReader.php:23-24`), wydatki z funduszu w I (`:93-104`), rozpisanie Codziennych G/I kończone pustym G albo „do kiedy wydatki” (`:107-128`), listy J/K/L/M z „opieka zdrowotna” / „leczenie prywatne” / „badania” w J i nagłówkiem „nazwa” w K wymaganym (`:28-36`, `:135`, `:152-194`).
- **Układ 2020/2021 (jeden dla obu lat, sprawdzony na wszystkich 24 miesiącach):** wydatki B/D bez zmian; przychody G/H do „oszczędności”; listy I/J/K/L = kategoria/nazwa/plan/wydatek; blok medyczny „Opieka zdrowotna”, „Lekarze - dentysta”, „Badania” w I, **bez nagłówka „nazwa”**; wiersz wydatku „Lekarz - dentysta” (2020 maj–cze, 2021) / „Lekarz (dentysta)” (2020 lip–gru) równy liście „Lekarze - dentysta”.
- **2020 styczeń–kwiecień nie ma bloku medycznego ani wierszy medycznych wydatków**; wizyty są pozycjami listy nieregularnej z kategorią „Lekarz”/„Badania”. Lista kończy się na pierwszym wierszu z pustymi I–L (wiersze z kreskami są w środku listy, np. `kwiecień!I6`); niżej leżą niepowiązane listy (`styczeń!K48` „Badania”, `L48` 155,90), więc czytanie do `highestRow` wciągnęłoby je.
- **Rozpisanie Codziennych 2020 kończy się wierszem „Na tydzień”** (`styczeń!H26` = 250); dzisiejsza pętla (`MonthSheetReader.php:120`) wzięłaby go za pozycję. 2021 kończy się „Do kiedy wydatki” jak 2022.
- **Rok:** żaden plik nie ma `Ustawienia`. `grudzień!A1` jest w obu plikach datą 1 grudnia roku (2020: 44166 = 2020-12-01 z `=DATE(YEAR(maj!A1),…)`; 2021: 44531 = 2021-12-01 z `=DATE(styczeń!A1,12,1)`); `styczeń!A1` w 2020 to tekst nagłówka.
- **Fundusz w „Analizie”:** `Analiza!C` liczy wpłaty do funduszu jako wydatek (Fundusz Wydatków Celowych*, „(oszcz.)”, Rezerwa na wydatki*, Święta*, Prezenty*, w 2020 także Nadpłata kredytu), a `Analiza!B` liczy „przychody rozłożone” (zwroty z funduszu na duży wydatek tego samego miesiąca: `kwiecień 2021!H6` 2100 = część `L26` ortodonta `=350+2100`; `czerwiec 2020!H6` zawiera `L11` i 155,36) jako przychód. Roczne kwoty (z wierszy arkusza, w zł):

  | Wiersz | 2020 | 2021 |
  | --- | --- | --- |
  | Fundusz Wydatków Celowych + „(oszcz.)” | 1 310,00 + 205,00 | 4 817,00 |
  | Rezerwa na wydatki | 83,92 | 385,80 |
  | Święta | 112,24 | 127,76 |
  | Prezenty (wiersz wydatku) | 150,00 | 180,00 |
  | Nadpłata kredytu | 2 456,89 | — (w 2021 w pomijanym bloku oszczędności, `G13`) |
  | **Razem wpłaty (wydatek „Analizy”)** | **4 318,05** | **5 510,56** |
  | przychody rozłożone (przychód „Analizy”) | 4 098,28 | 4 900,00 |

- **Planner odmawia na każdą etykietę bez mapowania**, także z kwotą 0 (`SheetLoadPlanner.php:56,93,145,241`), i na sumę różną od `Analiza!B16`/`C16` o grosz (`:40-41`, `:351-359`). `SKIP` dziś znaczy „bez operacji i poza Analizą” (oszczędności długoterminowe, przychód „oszczędności”); nie ma celu „bez operacji, ale w Analizie”.
- **Lista leczenia jest wiązana z wierszem wydatku dokładną etykietą `leczenie prywatne`** (`SheetLoadPlanner.php:80`, `MonthSheetReader.php:292`); bez zmiany „Lekarz - dentysta” byłby jedną operacją miesięcznie, a nazwy wizyt by zginęły (wbrew decyzji 7 ADR-0085).
- **Błąd w pliku 2021:** od lipca `E30` = `SUM(D3:D20)+SUM(D24:D29)` (styczeń–czerwiec `SUM(D3:D20)+SUM(D23:D29)`), więc pomija `D23` „Rozrywka i wyjścia”: lipiec 152,49, sierpień 47,90, wrzesień 0, październik 96,90, listopad 128,80, grudzień 111,90 = 537,99 zł. Zależne: `Analiza!C9:C14` (`=<miesiąc>!E30`), `C15` (`AVERAGE`), `C16` (`SUM`, dziś 89 673,56, po poprawce 90 211,55) oraz `lipiec!D31` (notatka, `-sierpień!E30`). Czytnik czyta zapisane wartości formuł (`SheetCells.php:253-274`), więc poprawka musi zmienić także zapisane `<v>`, nie tylko formułę.
- **Zapis i wycofanie są niezależne od układu:** `LoadBudgetSheet` liczy sumy i saldo otwarcia tylko z operacji planu (`app/Actions/LoadBudgetSheet.php:196-221`, `SheetLoadPlan.php:37-53`); blokady, płynność i raporty są kluczowane `sheet_year` i działają dla nowych lat bez zmian (research §5).
- **Testy:** `tests/Support/SheetWorkbookFactory.php` zna układy `'2023'` i `'2022'`, zawsze pisze `Ustawienia` i ma pozycje na sztywno (`:52-235`). Testy czytnika i plannera to `tests/Unit/BudgetSheetReaderTest.php`, `tests/Unit/SheetLoadPlannerTest.php` (prawdziwy `mapping.php`), zapis `tests/Feature/LoadBudgetSheetTest.php` (gałąź pgsql), komenda `tests/Feature/LoadBudgetSheetCommandTest.php`.
- **Lekcja z wczytania 2022–2025** (`context/archive/2026-11-12-legacy-budget-import/production-load-notes.md:51-71`): punkty „`--dry-run` prawdziwych plików” zostały odhaczone bez przebiegu, a syntetyczne skoroszyty bez formuł ukryły błąd. Tu przebieg na prawdziwych plikach ma dokładne polecenie i oczekiwane kwoty, a fixture ma komórki z formułą.

## Desired End State

`budget:load-sheet {user} Budżet_2020.xlsx --dry-run` i to samo dla poprawionego `Budżet_2021.xlsx` kończą się kodem 0, bez ostrzeżeń arkusza i bez reszt bez rozpisania, z jednym przeniesieniem ujemnego przychodu w 2021 (`grudzień!H8` −18,87 → `grudzień!H7`), z listą ruchów funduszu i sumami:

| Rok | Przychody: operacje + ruchy funduszu = „Analiza” B16 | Wydatki: operacje + ruchy funduszu = „Analiza” C16 | Saldo otwarcia konta arkusza |
| --- | --- | --- | --- |
| 2020 | 69 913,54 + 4 098,28 = 74 011,82 | 65 239,23 + 4 318,05 = 69 557,28 | −4 674,31 |
| 2021 | 91 448,99 + 4 900,00 = 96 348,99 | 84 700,99 + 5 510,56 = 90 211,55 | −6 748,00 |

Kwoty operacji są wyliczone w planowaniu z sum wierszy; autorytetem jest `--dry-run`. Różnica oznacza, że „Analiza” nie liczy któregoś wiersza funduszu tak, jak ustalił research — to STOP i badanie, nie korekta mapowania pod wynik.

Pliki 2022–2025 dają przy `--dry-run` te same sumy co dziś (README: 2022 106 233,93 / 101 219,38; 2023 117 847,66 / 122 115,75; 2024 110 559,17 / 126 760,34; 2025 134 670,58 / 134 852,23 zł) i ten sam wydruk (bez sekcji ruchów funduszu). Na produkcji są Budżety 2020 i 2021 w fazie execution na kontach „Arkusz 2020” / „Arkusz 2021”, a `app:control-numbers --compare=<przed> --allow-additions` przechodzi.

### Key Discoveries:

- `SheetCells::normalizeLabel()` zdejmuje końcowe `*` i zamienia `work:` na `praca:` (`SheetCells.php:241-248`), więc znaczniki `*`, `**`, `***` z 2020/2021 nie wymagają zmian.
- Przeniesienie ujemnego przychodu działa w obrębie źródła = Kategorii docelowej (`SheetLoadPlanner.php:233-264`, `:311-349`). Dlatego „międzyokresowe” (2021, tylko `grudzień!H8` −18,87) musi mieć tę samą Kategorię co „pozostałe (premie, 13)” (Wynagrodzenie Sebastian, 1): w Kategorii 7 byłoby jedyną komórką źródła i planner odmówiłby („roczna suma … musi być dodatnia”, `:287-301`).
- Ograniczenie zakresu do 2022–2025 w ADR-0085 wynikało tylko z tego, jakie pliki dostarczono (`context/archive/2026-11-12-legacy-budget-import/plan.md:42`); domena nie blokuje lat 2020/2021 (research §5).
- „Kosmetyki” (2020/2021: „Kosmetyczka i fryzjer”, nieregularne „Kosmetyki”) już istnieje na produkcji (id 42, zarchiwizowana) i jest używana ponownie (`LoadBudgetSheet.php:102-108`); miesiąc przed pierwszym wpisem Rytmu bierze Rytm tego wpisu (`CategoryRhythms.php:16-17`), więc `rhythm_start` 2022-01 zostaje.

## What We're NOT Doing

- Rok 2019 (inny skoroszyt: `styczeń_19`, `grudzień_18`, `Podsumowanie`).
- Korekty kwot „Analizy” w kodzie lub w `mapping.php` ani porównania z `Analiza!E16:K16`: błąd 2021 poprawiamy w pliku.
- Opcji `--year` w komendzie: rok wynika z pliku.
- Mapowania per rok: etykiety są globalne jak dziś. Istniejące wpisy 2022–2025 się nie zmieniają (`fundusz wydatków nieregularnych` → 10 zostaje wydatkiem).
- Zmian w `LoadBudgetSheet`, `UnloadBudgetSheet`, schemacie bazy, snapshocie, UI, glosariuszu i `rhythm_start` „Kosmetyki”.
- Ogólnej obsługi dowolnych układów: tylko dwa znane (2022–2025 i 2020–2021); każdy inny jest odrzucany z nazwą komórki.

## Implementation Approach

Najpierw decyzje (ADR-0092, ADR przed kodem wg `AGENTS.md`) i poprawka pliku 2021, żeby kod pracował na danych, które „Analiza” opisuje poprawnie. Potem czytnik: enum `SheetLayout` niesie wszystko, co różni dwa układy (kolumny, etykiety bloku medycznego, czy nagłówek „nazwa” i blok medyczny są wymagane), a `BudgetSheetReader` go rozpoznaje. Potem planner: nowy cel `SheetMapping::FUND` daje „ruch funduszu” bez operacji, który wchodzi do uzgodnienia z „Analizą” i do wydruku `--dry-run`; zapis i saldo otwarcia dalej liczą tylko operacje. Wiersz „Lekarz - dentysta” wiąże się z listą „Lekarze - dentysta” tak jak „Leczenie prywatne”. Na końcu dokumentacja, bramka i wczytanie na produkcji wg procedury z README.

## Critical Implementation Details

- **Poprawka pliku zmienia zapisane wartości, nie tylko formuły.** Czytnik nie przelicza formuł (`SheetCells.php:251-274`), więc `Analiza!C16` musi mieć zapisane 90 211,55. Edycja odbywa się punktowo w XML-u skoroszytu (formatowanie, wykresy i reszta komórek bajt w bajt bez zmian), z `fullCalcOnLoad="1"` w `xl/workbook.xml`, żeby Excel przeliczył całość przy otwarciu. Uwaga na formuły współdzielone (`<f t="shared" …>`): jeśli `E30` którejś karty jest wzorcem albo członkiem formuły współdzielonej, trzeba to obsłużyć jawnie.
- **Przebiegi na prawdziwych plikach muszą się naprawdę odbyć**, z kodu worktree, a raport fazy podaje ich wynik (kod wyjścia, sumy, liczby ostrzeżeń, operacji i ruchów funduszu). Pliki leżą poza repo: `/home/sebastian/my-projects/household-treasurer/storage/app/private/`. Polecenie (dev DB tylko do odczytu, `--dry-run` nic nie zapisuje):
  ```bash
  docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=household_treasurer -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -v /home/sebastian/my-projects/household-treasurer/storage/app/private:/sheets:ro -w /var/www/html --entrypoint php sail-8.5/app -d memory_limit=1G artisan budget:load-sheet 1 /sheets/Budżet_2020.xlsx --dry-run
  ```

## Faza 1: ADR-0092 i poprawka pliku 2021

### Overview

Zapisuje decyzje przed kodem i poprawia błąd formuły w `Budżet_2021.xlsx`, tak żeby `Analiza!C16` była prawdą.

### Changes Required:

#### 1. ADR-0092

**File**: `context/foundation/adr/0092-sheet-load-of-2020-and-2021.md`

**Intent**: Zmienia decyzje 1, 3, 4 i 7 ADR-0085 dla lat 2020 i 2021 i zapisuje rozstrzygnięcia tego planowania wraz z odrzuconymi wariantami.

**Contract**: Treść (styl i układ jak ADR-0085: kontekst, Rule, decyzje, Consequences, We rejected):
- Zakres: 2020 i 2021 w układzie 2020–2021, rozpoznanym po braku arkusza `Ustawienia` i „kategoria” w `styczeń!I3`; rok z `grudzień!A1` (data 1 grudnia). Inny układ jest odrzucany z nazwą komórki. 2019 poza zakresem.
- Ruch funduszu: wpłaty do funduszu (Fundusz Wydatków Celowych*, „(oszcz.)”, Rezerwa na wydatki*, Święta*, Prezenty* jako wiersz wydatku, Nadpłata kredytu 2020) i zwroty z niego („przychody rozłożone”, przychód „fundusz wydatków nieregularnych”) nie są operacjami; uzgodnienie z „Analizą” liczy operacje + ruchy funduszu co do grosza; `--dry-run` je wypisuje; saldo otwarcia konta arkusza liczy tylko operacje. Nadpłata kredytu jako ruch funduszu, bo w 2021 ten sam ruch jest w pomijanym bloku oszczędności.
- Plik 2021 poprawiony u źródła: `E30` lipiec–grudzień, kwoty, komórki zależne, SHA-256 przed i po (z `workbook-fix.md`), kopia oryginału. „Analiza jest prawdą” obowiązuje dalej bez wyjątku w kodzie.
- Rozpisanie Codziennych czytane także w 2020/2021 (koniec na „Na tydzień” lub „Do kiedy wydatki”); wiersz „Lekarz - dentysta” / „Lekarz (dentysta)” rozbity na pozycje listy „Lekarze - dentysta” jak „Leczenie prywatne”.
- Mapowanie nowych etykiet (tabela z Fazy 3), w tym „pozostałe (premie, 13)” i „międzyokresowe” → Wynagrodzenie Sebastian.
- Odrzucone: korekta kwoty „Analizy” w `mapping.php`; porównanie z `Analiza!E16:K16`; wpłaty i zwroty funduszu jako wydatek i przychód (wariant zgodny z decyzją 3 ADR-0085 dla `**`); opcja `--year`; nadpłata kredytu jako wydatek „Kredyt”; premie w „Pozostałe” przychodach.

#### 2. ADR-0085: dopisek o zmianie

**File**: `context/foundation/adr/0085-sheet-load-of-past-years.md`

**Intent**: Czytelnik ADR-0085 ma wiedzieć, że zakres i decyzje 3, 4, 7 rozszerza ADR-0092.

**Contract**: Jedno zdanie przy decyzji 1 (na wzór „amended by ADR-0089”), bez przepisywania treści.

#### 3. Poprawka `Budżet_2021.xlsx`

**File**: `/home/sebastian/my-projects/household-treasurer/storage/app/private/Budżet_2021.xlsx` (poza git, w checkoutcie głównym); skrypt jednorazowy w `$CLAUDE_JOB_DIR/tmp` (nie w repo); zapis przebiegu w `context/changes/sheet-load-2020-2021/workbook-fix.md`.

**Intent**: Przywraca `D23` do sumy wydatków lipiec–grudzień i aktualizuje zapisane wartości zależnych komórek, tak żeby `Analiza!C16` = 90 211,55 bez zmian w czymkolwiek innym.

**Contract**:
- Przed edycją kopia `Budżet_2021.przed-korekta.xlsx` w tym samym katalogu i SHA-256 obu plików.
- `lipiec`…`grudzień`!`E30`: formuła `SUM(D3:D20)+SUM(D23:D29)`, zapisana wartość = stara + `D23` danego miesiąca.
- Zapisane wartości wszystkich komórek zależnych (przechodnio), co najmniej `Analiza!C9:C14`, `C15`, `C16`, `lipiec!D31`; zbiór zależnych wyznaczony skanem formuł całego skoroszytu, nie z pamięci.
- `xl/workbook.xml`: `<calcPr fullCalcOnLoad="1">`.
- Dowód: skrypt porównuje wartość i formułę każdej komórki każdego arkusza oryginału i poprawki; różnią się tylko komórki ze zbioru oczekiwanego. Każda nowa zapisana wartość równa się wynikowi silnika obliczeń PhpSpreadsheet dla tej komórki. `workbook-fix.md` zawiera: SHA-256 przed/po, listę zmienionych komórek ze starą i nową wartością, opis metody.
- Uruchamiane w obrazie `sail-8.5/app` (host ma PHP 8.3, `vendor` wymaga 8.4.1), z repo zamontowanym tylko do odczytu poza katalogiem pliku.

### Success Criteria:

#### Automated Verification:

- `context/foundation/adr/0092-sheet-load-of-2020-and-2021.md` istnieje, a ADR-0085 odsyła do niego
- Skrypt porównania kończy się kodem 0: różnią się tylko komórki oczekiwane, `Analiza!C16` = 90 211,55, `E30` lipiec–grudzień ma formułę `SUM(D3:D20)+SUM(D23:D29)`
- `Budżet_2021.przed-korekta.xlsx` istnieje i ma SHA-256 sprzed poprawki zapisane w `workbook-fix.md`

#### Manual Verification:

- Poprawiony plik otwiera się w Excelu bez komunikatu o naprawie, a `Analiza!C16` pokazuje 90 211,55

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie otwarcia pliku w Excelu.

---

## Faza 2: Czytnik układu 2020–2021

### Overview

Czytnik rozpoznaje układ 2020–2021, czyta rok z `grudzień!A1` i wszystkie bloki miesiąca z właściwych kolumn; układ 2022–2025 działa jak dziś. Testy najpierw (czerwone na dzisiejszym kodzie).

### Changes Required:

#### 1. Układ arkusza

**File**: `app/Support/SheetLoad/SheetLayout.php` (nowy)

**Intent**: Jedno miejsce na to, czym różnią się dwa układy, zamiast rozgałęzień po literach kolumn w czytniku.

**Contract**: Enum z przypadkami `Since2022` (2022–2025) i `Before2022` (2020–2021). Zwraca: kolumnę kwoty przychodu (I / H); kolumny listy kategoria/nazwa/plan/wydatek (J,K,L,M / I,J,K,L); etykietę listy leczenia (`leczenie prywatne` / `lekarze - dentysta`); czy nagłówek „nazwa” nad listą leczenia jest wymagany (tak / nie); czy blok medyczny jest wymagany (tak / nie).

#### 2. Rozpoznanie układu i rok

**File**: `app/Support/SheetLoad/BudgetSheetReader.php`, `app/Support/SheetLoad/BudgetSheet.php` (docblock)

**Intent**: Plik z `Ustawienia` czyta się jak dziś; plik bez `Ustawienia` z „kategoria” w `styczeń!I3` jest układem 2020–2021 z rokiem z `grudzień!A1`.

**Contract**:
- `Ustawienia` istnieje → `Since2022`, rok z `B2` jak dziś.
- Brak `Ustawienia`, `styczeń!I3` po normalizacji = `kategoria` → `Before2022`; rok: `grudzień!A1` musi być liczbą (zapisana wartość formuły), datą Excela równą 1 grudnia; inaczej `SheetReadException('grudzień!A1', …)` z opisem oczekiwanej daty.
- Ani jedno, ani drugie → `SheetReadException` z adresem `Ustawienia`, komunikat nazywa oba oczekiwane znaki rozpoznawcze.
- Układ trafia do `MonthSheetReader::read()` jako trzeci argument (domyślnie `Since2022`, żeby istniejące wywołania testów działały).

#### 3. Czytnik miesiąca

**File**: `app/Support/SheetLoad/MonthSheetReader.php`

**Intent**: Wszystkie kolumny i etykiety bloków z `SheetLayout`; blok medyczny opcjonalny w `Before2022`; rozpisanie Codziennych z „Na tydzień” jako końcem.

**Contract**:
- Przychody i „wydatki z funduszu”: kwota z kolumny przychodu układu.
- Rozpisanie Codziennych: etykiety w G, kwota z kolumny przychodu układu; koniec przed pustym G, „do kiedy wydatki” albo „na tydzień” (dla obu układów).
- Listy: „kategoria”, „opieka zdrowotna”, etykieta listy leczenia, „badania” szukane w kolumnie kategorii układu; nagłówek „nazwa” w kolumnie nazwy wymagany tylko, gdy układ tak mówi (w `Before2022` lista leczenia zaczyna się wiersz pod etykietą); pusty wiersz listy = puste wszystkie cztery kolumny listy układu; kreski sprawdzane w kolumnie nazwy i wydatku.
- `Before2022` bez „opieka zdrowotna” pod „kategoria”: listy leczenia i badań puste, lista nieregularna kończy się przed pierwszym pustym wierszem listy. `Since2022` bez tej etykiety dalej odmawia jak dziś.
- Ostrzeżenie „leczenie prywatne … różni się od: lista leczenia” dotyczy każdego wiersza wydatku z `MonthSheet::TREATMENT_ROWS` (poniżej).

#### 4. Wiersze leczenia

**File**: `app/Support/SheetLoad/MonthSheet.php`

**Intent**: Jedna lista etykiet wiersza wydatku, który zastępuje lista leczenia, wspólna dla czytnika (ostrzeżenie) i plannera (rozbicie na pozycje).

**Contract**: Stała `TREATMENT_ROWS = ['leczenie prywatne', 'lekarz - dentysta', 'lekarz (dentysta)']` (etykiety znormalizowane).

#### 5. Fabryka skoroszytów

**File**: `tests/Support/SheetWorkbookFactory.php`

**Intent**: Syntetyczny skoroszyt w układzie 2020–2021 z literałami (bez prawdziwych danych) i co najmniej jedną komórką z formułą.

**Contract**: `layout => '2020'`: bez arkusza `Ustawienia`; `A1` każdego miesiąca jako formuła `=DATE(<rok>,<miesiąc>,1)` (zapisana wartość liczona przy zapisie), `styczeń!A1` tekst nagłówka; przychody G/H; listy w I–L z „kategoria” w `I3`; blok „Opieka zdrowotna” / „Lekarze - dentysta” / „Badania” w I bez nagłówka „nazwa”; wiersze wydatków z „Lekarz - dentysta”, wierszami funduszu („Fundusz Wydatków Celowych*”, „Rezerwa na wydatki*”, „Nadpłata kredytu”) i przychodem „przychody rozłożone”; rozpisanie Codziennych w G/H zakończone „Na tydzień”; opcja `withoutMedical` (lista numerów miesięcy bez bloku medycznego i bez wierszy medycznych, z niepowiązaną listą niżej po pustym wierszu, jak `styczeń!K48`). `Analiza!B16/C16` domyślnie liczone z wierszami funduszu (jak w prawdziwych plikach), bez „oszczędności”. Układy `'2023'` i `'2022'` bez zmian.

#### 6. Testy czytnika

**File**: `tests/Unit/BudgetSheetReaderTest.php`

**Intent**: Pokrywają nowy układ i jego przypadki brzegowe; istniejące testy zostają bez zmian asercji.

**Contract**: Nowe testy:
- reads the 2020-2021 layout: year from grudzień!A1, income from H, lists from I to L, the treatment list without a name header
- a 2020 month without a medical block ends the irregular list at the first blank row and leaves the lower lists out
- the daily breakdown ends at Na tydzień
- refuses a workbook without Ustawienia that is not the 2020-2021 layout, naming the cell
- refuses grudzień!A1 that is not the first of December
- warns when the Lekarz - dentysta row differs from its list

### Success Criteria:

#### Automated Verification:

- Nowe testy czytnika są czerwone przed zmianą `app/Support/SheetLoad` i zielone po niej
- `tests/Unit/BudgetSheetReaderTest.php` przechodzi w całości (stare testy bez zmian asercji)
- Pint: `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia
- PHPStan w `composer ci:check` przechodzi dla `app/Support/SheetLoad`

---

## Faza 3: Planner, mapowanie i ruchy funduszu

### Overview

Nowy cel mapowania „ruch funduszu”, rozbicie „Lekarz - dentysta” na pozycje, 29 nowych etykiet, wydruk w komendzie i przebieg `--dry-run` na wszystkich sześciu prawdziwych plikach.

### Changes Required:

#### 1. Cel „ruch funduszu”

**File**: `app/Support/SheetLoad/SheetMapping.php`

**Intent**: Odróżnia „bez operacji, ale w Analizie” (fundusz) od „bez operacji i poza Analizą” (`SKIP`).

**Contract**: Stała `FUND = 'fundusz'` obok `SKIP` i `NEW_CATEGORY`; docblock klasy opisuje trzy rodzaje celu. Dozwolona tylko w sekcjach `expenses` i `income`.

#### 2. Plan i uzgodnienie

**File**: `app/Support/SheetLoad/SheetLoadPlanner.php`, `app/Support/SheetLoad/SheetLoadPlan.php`

**Intent**: Wiersz wydatku i komórka przychodu z celem `FUND` nie dają operacji, tylko ruch funduszu; uzgodnienie z „Analizą” sumuje operacje i ruchy funduszu; wiersze `TREATMENT_ROWS` rozbijają się na pozycje listy leczenia.

**Contract**:
- `SheetLoadPlan` dostaje listę ruchów funduszu (komórka, etykieta surowa, kwota, typ przychód/wydatek; kwoty 0 pominięte) i dwie sumy (przychody, wydatki funduszu). `incomeTotal()`, `expenseTotal()`, `openingBalanceMinorUnits()` i `categoryTotals()` dalej liczą tylko operacje, więc `LoadBudgetSheet` się nie zmienia.
- `assertTotal`: operacje + ruchy funduszu danego typu = `Analiza` co do grosza; komunikat odmowy podaje obie składowe.
- Komórki przychodu z `FUND` nie wchodzą do grup źródeł (nie podlegają kontroli dodatniej sumy ani przenoszeniu ujemnych).
- `listedBlock`: wiersz z etykietą z `MonthSheet::TREATMENT_ROWS` → lista leczenia; nazwa bloku w opisie reszty = etykieta wiersza.
- Docblock klasy plannera wymienia ruchy funduszu.

#### 3. Mapowanie

**File**: `database/sheet-load/mapping.php`

**Intent**: Każda etykieta 2020/2021 ma wpis zgodny z decyzjami planowania; wpisy 2022–2025 bez zmian.

**Contract**: Nowe klucze (znormalizowane):

| Sekcja | Etykieta | Cel |
| --- | --- | --- |
| expenses | hbo go, netflix | 23 Rozrywka |
| expenses | ogrzewanie | 35 Eksploatacja domu |
| expenses | bilety i transport | 22 Bilety i parking |
| expenses | lekarz - dentysta, lekarz (dentysta) | 20 Leczenie prywatne (pozycjami listy) |
| expenses | fundusz wydatków celowych, fundusz wydatków celowych (oszcz.), rezerwa na wydatki, święta, prezenty, nadpłata kredytu | `FUND` |
| income | przychody rozłożone, fundusz wydatków nieregularnych | `FUND` |
| income | pozostałe (premie, 13), międzyokresowe | 1 Wynagrodzenie Sebastian |
| daily_breakdown | gospodarstwo domowe | 8 Artykuły spożywcze |
| daily_breakdown | jedzenie poza domem | 24 Jedzenie poza domem |
| irregular_categories | lekarz, badania | 20 Leczenie prywatne |
| irregular_categories | gospodarstwo | 37 Wyposażenie domu |
| irregular_categories | jedzenie | 24 Jedzenie poza domem |
| irregular_categories | darowizna / prezent / ubranie | 28 Darowizny / 31 Prezenty / 27 Ubrania |
| irregular_categories | biurowe, szkoła | 30 Edukacja |
| irregular_categories | higiena | 29 Rzeczy osobiste |
| irregular_categories | kary | 10 Pozostałe |

Komentarz nagłówka pliku wymienia `FUND` i ADR-0092; komentarz przy „międzyokresowe” mówi, czemu 1 (to samo źródło co premie, inaczej jedyna ujemna komórka źródła).

#### 4. Wydruk komendy

**File**: `app/Console/Commands/LoadBudgetSheetCommand.php`

**Intent**: `--dry-run` pokazuje, co zostało ruchem funduszu i jak składa się uzgodnienie, a wydruk lat bez funduszu zostaje taki jak dziś.

**Contract**: Sekcja „Ruchy funduszu (bez operacji, liczone przez „Analizę”) (N):” z adresem, etykietą i kwotą każdego ruchu, przez istniejące `printWarnings`-podobne formatowanie. Linie „Przychody:” / „Wydatki:” przy niezerowym funduszu: „operacje X zł + ruchy funduszu F zł = „Analiza” <adres>: Y zł (zgodne)”; przy zerowym bez zmian.

#### 5. Testy plannera, komendy i zapisu

**File**: `tests/Unit/SheetLoadPlannerTest.php`, `tests/Feature/LoadBudgetSheetCommandTest.php`, `tests/Feature/LoadBudgetSheetTest.php`

**Intent**: Pokrywają ruchy funduszu, rozbicie leczenia w nowym układzie, mapowanie wszystkich etykiet fixture i zapis roku 2020 na PostgreSQL.

**Contract**: Nowe testy:
- fund movements make no operation and count towards Analiza for income and expense
- refuses when operations and fund movements differ from Analiza by one grosz, naming both parts
- a Lekarz - dentysta row becomes the items of its treatment list and a difference gives a remainder
- the real mapping maps every label of the 2020-2021 fixture and uses FUND only for expenses and income
- a negative międzyokresowe cell moves into the premie cell of the same month
- dry-run of a 2020-2021 workbook lists the fund movements and the agreed totals (komenda)
- loading a 2020-2021 workbook writes no fund operation and leaves the sheet account at 0 with the opening balance of the operations (zapis, gałąź pgsql)

### Success Criteria:

#### Automated Verification:

- Nowe testy plannera i komendy są czerwone przed zmianą i zielone po niej
- `tests/Unit/SheetLoadPlannerTest.php`, `tests/Unit/BudgetSheetReaderTest.php`, `tests/Feature/LoadBudgetSheetCommandTest.php` przechodzą
- `tests/Feature/LoadBudgetSheetTest.php` przechodzi na PostgreSQL (`testing_sheet_load_2020_2021`, polecenie worktree z `AGENTS.md`)
- `--dry-run` `Budżet_2020.xlsx` z kodu worktree: kod 0, 0 ostrzeżeń arkusza, 0 reszt, przychody 69 913,54 + 4 098,28 = 74 011,82, wydatki 65 239,23 + 4 318,05 = 69 557,28, saldo otwarcia −4 674,31
- `--dry-run` poprawionego `Budżet_2021.xlsx` z kodu worktree: kod 0, 0 ostrzeżeń arkusza, 0 reszt, 1 przeniesienie (`grudzień!H8` → `grudzień!H7`), przychody 91 448,99 + 4 900,00 = 96 348,99, wydatki 84 700,99 + 5 510,56 = 90 211,55, saldo otwarcia −6 748,00
- `--dry-run` `Budżet_2022.xlsx`…`Budżet_2025.xlsx` z kodu worktree: kod 0 i sumy jak w README, bez sekcji ruchów funduszu

#### Manual Verification:

- Przegląd tabeli Kategorii z `--dry-run` 2020 i 2021: kwoty w Kategoriach wyglądają wiarygodnie (np. Leczenie prywatne z nazwami wizyt, Wynagrodzenie Sebastian z premiami 2021)

**Implementation Note**: Po fazie zatrzymaj się na przegląd wydruków `--dry-run` przez użytkownika.

---

## Faza 4: Dokumentacja i bramka

### Overview

README opisuje wczytanie lat 2020–2025, a pełna bramka przechodzi na PostgreSQL.

### Changes Required:

#### 1. README

**File**: `README.md` (sekcja „Wczytanie arkusza (lata 2022–2025)”, `:220-252`)

**Intent**: Procedura obejmuje 2020 i 2021: nagłówek, pętla kopiowania plików, oczekiwane sumy `--dry-run` (z ruchami funduszu), uwaga o poprawionym pliku 2021 (SHA-256 z `workbook-fix.md`) i o nowym pliku „przed” liczb kontrolnych (stary `20261112-…` jest nieaktualny po dodaniu lat).

**Contract**: Sekcja „Wczytanie arkusza (lata 2020–2025)” z odwołaniem do ADR-0085 i ADR-0092; reszta README bez zmian.

### Success Criteria:

#### Automated Verification:

- Pełna bramka na PostgreSQL: `docker run … --entrypoint composer sail-8.5/app ci:check` z `DB_DATABASE=testing_sheet_load_2020_2021` przechodzi
- `vendor/bin/sloppy ci` na hoście bez nowych znalezisk (wg `.ai/rules/sloppy.md`; gdy host nie uruchamia, w Sail, z adnotacją w raporcie)

#### Manual Verification:

- README czyta się jako kompletna procedura dla 2020 i 2021 bez sięgania do planu

---

## Faza 5: Wczytanie na produkcji

### Overview

Próba na kopii produkcji, potem wczytanie 2020 i 2021 na produkcji wg README, z dowodem „tylko dodano”. Kroki wykonuje człowiek (albo agent na jego polecenie, krok po kroku).

### Changes Required:

#### 1. Notatki z wczytania

**File**: `context/changes/sheet-load-2020-2021/production-load-notes.md`

**Intent**: Ślad wykonania jak w `context/archive/2026-11-12-legacy-budget-import/production-load-notes.md`: wdrożony commit, SHA-256 plików na serwerze (2021 = poprawiony), nazwy plików snapshotu i liczb kontrolnych „przed”, wyniki `--dry-run`, id Budżetów i kont arkusza, wynik `--compare`.

**Contract**: Kolejność kroków z README: wdrożenie (bez migracji) → kopiowanie plików → `user:backup` i `app:control-numbers` „przed” → `--dry-run` obu lat → wczytanie 2020, potem 2021 → `--compare … --allow-additions` → przegląd w aplikacji.

### Success Criteria:

#### Manual Verification:

- Próba na kopii produkcji (`restore_trial`): wczytanie 2020 i 2021, `--compare --allow-additions` kod 0, wycofanie obu lat i ponowne wczytanie
- SHA-256 `Budżet_2021.xlsx` na serwerze równa się SHA-256 po poprawce z `workbook-fix.md`
- Produkcja: `--dry-run` 2020 i 2021 z sumami jak w Fazie 3, wczytanie obu lat, `app:control-numbers --compare=<przed> --allow-additions` kod 0
- Aplikacja na produkcji: lata 2020–2027 w selektorze roku, raport 2022 z kolumną 2021, raport 2021 z kolumną 2020, saldo „Na kontach” i szkic 2027 bez zmian

---

## Testing Strategy

### Unit Tests:

- Czytnik: rozpoznanie układu, rok z `grudzień!A1` (z formuły), kolumny przychodu i list, opcjonalny blok medyczny z końcem listy na pustym wierszu, „Na tydzień”, odmowy z adresem komórki.
- Planner: ruchy funduszu poza operacjami i w uzgodnieniu, odmowa przy różnicy 1 gr, rozbicie „Lekarz - dentysta”, pełne mapowanie fixture, przeniesienie „międzyokresowe”.

### Integration Tests:

- Komenda `--dry-run` na skoroszycie 2020–2021 (wydruk ruchów funduszu i sum).
- Zapis roku 2020–2021 na PostgreSQL (`LoadBudgetSheet` używa `UPDATE … FROM`).

### Manual Testing Steps:

1. `--dry-run` sześciu prawdziwych plików z kodu worktree (polecenie w „Critical Implementation Details”), porównanie z kwotami z „Desired End State” i README.
2. Przegląd tabeli Kategorii 2020 i 2021 przez użytkownika.
3. Próba na kopii produkcji, potem produkcja wg README.

## Performance Considerations

Pliki 2020/2021 mają ~280 KB (2022–2025 ~1 MB, odczyt 23,5 s). `highestRow()` jest liczone raz na arkusz (`SheetCells.php:174-177`); nowe pętle końca listy używają go tak samo.

## Migration Notes

Brak migracji. Wycofanie roku: `budget:unload-sheet {user} 2020` / `2021` jak dla 2022–2025. „Kosmetyki” zostaje, bo używają jej lata 2022–2025.

## References

- Research: `context/changes/sheet-load-2020-2021/research.md`
- Decyzja bazowa: `context/foundation/adr/0085-sheet-load-of-past-years.md`
- Poprzednia zmiana: `context/archive/2026-11-12-legacy-budget-import/plan.md`, `production-load-notes.md`
- Czytnik: `app/Support/SheetLoad/BudgetSheetReader.php:21-45`, `MonthSheetReader.php:86-233`
- Planner: `app/Support/SheetLoad/SheetLoadPlanner.php:23-100,233-264,351-359`
- Mapowanie: `database/sheet-load/mapping.php`
- Fabryka: `tests/Support/SheetWorkbookFactory.php:50-235`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0092 i poprawka pliku 2021

#### Automated

- [x] 1.1 `context/foundation/adr/0092-sheet-load-of-2020-and-2021.md` istnieje, a ADR-0085 odsyła do niego
- [x] 1.2 Skrypt porównania kończy się kodem 0: różnią się tylko komórki oczekiwane, `Analiza!C16` = 90 211,55, `E30` lipiec–grudzień ma formułę `SUM(D3:D20)+SUM(D23:D29)`
- [x] 1.3 `Budżet_2021.przed-korekta.xlsx` istnieje i ma SHA-256 sprzed poprawki zapisane w `workbook-fix.md`

#### Manual

- [x] 1.4 Poprawiony plik otwiera się w Excelu bez komunikatu o naprawie, a `Analiza!C16` pokazuje 90 211,55

### Phase 2: Czytnik układu 2020–2021

#### Automated

- [ ] 2.1 Nowe testy czytnika są czerwone przed zmianą `app/Support/SheetLoad` i zielone po niej
- [ ] 2.2 `tests/Unit/BudgetSheetReaderTest.php` przechodzi w całości (stare testy bez zmian asercji)
- [ ] 2.3 Pint: `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia
- [ ] 2.4 PHPStan w `composer ci:check` przechodzi dla `app/Support/SheetLoad`

### Phase 3: Planner, mapowanie i ruchy funduszu

#### Automated

- [ ] 3.1 Nowe testy plannera i komendy są czerwone przed zmianą i zielone po niej
- [ ] 3.2 `tests/Unit/SheetLoadPlannerTest.php`, `tests/Unit/BudgetSheetReaderTest.php`, `tests/Feature/LoadBudgetSheetCommandTest.php` przechodzą
- [ ] 3.3 `tests/Feature/LoadBudgetSheetTest.php` przechodzi na PostgreSQL (`testing_sheet_load_2020_2021`, polecenie worktree z `AGENTS.md`)
- [ ] 3.4 `--dry-run` `Budżet_2020.xlsx` z kodu worktree: kod 0, 0 ostrzeżeń arkusza, 0 reszt, przychody 69 913,54 + 4 098,28 = 74 011,82, wydatki 65 239,23 + 4 318,05 = 69 557,28, saldo otwarcia −4 674,31
- [ ] 3.5 `--dry-run` poprawionego `Budżet_2021.xlsx` z kodu worktree: kod 0, 0 ostrzeżeń arkusza, 0 reszt, 1 przeniesienie (`grudzień!H8` → `grudzień!H7`), przychody 91 448,99 + 4 900,00 = 96 348,99, wydatki 84 700,99 + 5 510,56 = 90 211,55, saldo otwarcia −6 748,00
- [ ] 3.6 `--dry-run` `Budżet_2022.xlsx`…`Budżet_2025.xlsx` z kodu worktree: kod 0 i sumy jak w README, bez sekcji ruchów funduszu

#### Manual

- [ ] 3.7 Przegląd tabeli Kategorii z `--dry-run` 2020 i 2021: kwoty w Kategoriach wyglądają wiarygodnie (np. Leczenie prywatne z nazwami wizyt, Wynagrodzenie Sebastian z premiami 2021)

### Phase 4: Dokumentacja i bramka

#### Automated

- [ ] 4.1 Pełna bramka na PostgreSQL: `docker run … --entrypoint composer sail-8.5/app ci:check` z `DB_DATABASE=testing_sheet_load_2020_2021` przechodzi
- [ ] 4.2 `vendor/bin/sloppy ci` na hoście bez nowych znalezisk (wg `.ai/rules/sloppy.md`; gdy host nie uruchamia, w Sail, z adnotacją w raporcie)

#### Manual

- [ ] 4.3 README czyta się jako kompletna procedura dla 2020 i 2021 bez sięgania do planu

### Phase 5: Wczytanie na produkcji

#### Manual

- [ ] 5.1 Próba na kopii produkcji (`restore_trial`): wczytanie 2020 i 2021, `--compare --allow-additions` kod 0, wycofanie obu lat i ponowne wczytanie
- [ ] 5.2 SHA-256 `Budżet_2021.xlsx` na serwerze równa się SHA-256 po poprawce z `workbook-fix.md`
- [ ] 5.3 Produkcja: `--dry-run` 2020 i 2021 z sumami jak w Fazie 3, wczytanie obu lat, `app:control-numbers --compare=<przed> --allow-additions` kod 0
- [ ] 5.4 Aplikacja na produkcji: lata 2020–2027 w selektorze roku, raport 2022 z kolumną 2021, raport 2021 z kolumną 2020, saldo „Na kontach” i szkic 2027 bez zmian
