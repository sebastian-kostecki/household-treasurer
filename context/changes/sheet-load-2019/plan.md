# Wczytanie roku 2019 ze starego arkusza budżetu — plan implementacji

## Overview

Sheet Load (ADR-0085, ADR-0096, `budget:load-sheet`) ma wczytać `Budżet_2019.xlsx`. Ten skoroszyt ma inny układ niż 2020–2025 (arkusze `styczeń_19` … `grudzień_19`, `grudzień_18`, `Podsumowanie` zamiast `Analiza`) i trzy warianty miesięcy w jednym roku. Jego suma wydatków `Podsumowanie!H16` miesza plan, pensje z lipca i wykonanie. Zmiana:

- poprawia `Podsumowanie` u źródła, tak jak plik 2021;
- dodaje osobny czytnik 2019, który zwraca te same `BudgetSheet`/`MonthSheet`;
- uczy planer list wiersza (osobiste, „Dom i akcesoria”) i nadpisań wydatków („Rachunki”);
- uogólnia nową kategorię („Kosmetyki” i nowa zarchiwizowana „Stypendium”);
- dodaje etykiety 2019 do `mapping.php`;
- kończy się wczytaniem roku na produkcji.

## Current State Analysis

Pełne ustalenia są w `context/changes/sheet-load-2019/research.md`, a decyzje użytkownika w `change.md` (sekcja „Decyzje użytkownika”). Najważniejsze:

- **Czytnik odrzuca plik przy rozpoznaniu układu.** Szuka arkuszy `styczeń`…`grudzień` (`app/Support/SheetLoad/BudgetSheetReader.php:15`) oraz `Analiza` (`:28`) i zna tylko `Ustawienia` albo „kategoria” w `styczeń!I3` (`:46`). `MonthSheetReader` ma na stałe wydatki B/D, „wydatki nieregularne” i „oszczędności” w G (`MonthSheetReader.php:18-23`), więc 2019 nie da się opisać trzecim `SheetLayout`.
- **Skoroszyt 2019 (SHA-256 `b99e9217…0f6`) ma trzy warianty miesięcy i `grudzień_18`** (research §1):
  - `grudzień_18`: tylko plan. Żadna formuła się do niego nie odwołuje.
  - Wariant A, styczeń–luty:
    - „Przychody” w `A5`, „Wydatki” w `A10`, etykiety w A, plan w B, wykonanie w C;
    - wiersze list mają sumę listy w B i puste C: „akcesoria do domu”, „osobiste: Jana”, „osobiste: Sebastian”, „cele: Sebastian”;
    - listy: osobiste Sebastian F/G i Jana H/I w wierszach 7–15, „Cele” F19:F21/I, „Dom i akcesoria” F24:F27/I.
  - Wariant B, marzec–czerwiec: to samo dwa wiersze wyżej („Przychody” `A3`, „Wydatki” `A8`). Czerwiec ma jeden wiersz „osobiste” (`A23`) zamiast dwóch, „Oszczędności (10%)” w `A25` i wiersze list z wartością w C.
  - Wariant C, lipiec–grudzień:
    - „Przychody” `A2`, kwoty przychodów w C, „Wydatki” `A7`;
    - etykiety pozycji w B, plan w C, wykonanie w D;
    - „Pozostałe” (bilans) w `B25` (lipiec) lub `B26` (sierpień–grudzień);
    - listy osobiste: G/H i I/J w lipcu–wrześniu; od października nazwa/plan/wydatek w G/H/I i J/K/L;
    - „Cele do odłożenia” i „Dom i akcesoria” w G… z kwotami w J (lipiec–wrzesień) lub L (październik–grudzień);
    - w listopadzie i grudniu rozpisanie „Codzienne wydatki” w `M12:O16`.
- **Kontrole w danych:**
  - W styczniu–czerwcu jedyne wiersze wydatków z pustym C i niezerowym B to wiersze list i „cele: Sebastian”. Sprawdzone w zrzucie komórek wierszy 3–28 tych arkuszy w trakcie planowania.
  - `maj_19!A10` nie ma etykiety, a `C10` = 0.
  - W lutym i marcu niektóre etykiety są tekstem sformatowanym (RichText), np. `luty_19!A6`, a w lipcu `B10` „Rachunki (prąd+telefon+internet)”.
- **`Podsumowanie!H16` (64 035,95) nie nadaje się na prawdę:**
  - styczeń–czerwiec to plan (`H3 =styczeń_19!B10`);
  - maj liczy też cele;
  - lipiec to dwie pensje (`H9 =lipiec_19!C3+lipiec_19!C4`);
  - sierpień–listopad to wykonanie z „Cele”, a grudzień wykonanie bez „Cele”.

  `I16` (77 914,29) pomija tylko `marzec_19!B7` „inne” 125,00, bo `marzec_19!B3 =B4+B5+B6`.
- **Planer jest wspólny dla wszystkich lat i zna trzy rodzaje list wiersza.** `listedBlock` podpina listę leczenia, badań i rozpisania Codziennych (`SheetLoadPlanner.php`, `listedBlock`/`listedOperations`). Reszta takiej listy idzie do kategorii z sekcji `remainders` mapowania. Uzgadnia operacje + ruchy funduszu z `analysisIncome`/`analysisExpense` (`:42-43`, `:373`).
- **Nadpisania (`overrides`) działają tylko w przychodach.** `incomeGroups`/`applyOverride` dla komórek przychodów; nieużyte nadpisanie jest odmową.
- **Nowa kategoria to jedna, sztywna „Kosmetyki” wydatkowa:**
  - `SheetMapping::NEW_CATEGORY` i `new_category` w `mapping.php:188-194`;
  - `LoadBudgetSheet::newCategoryId`/`createLines`/`categoryId` (`LoadBudgetSheet.php:102-129`, `:226`);
  - `UnloadBudgetSheet::removeNewCategory`, które rozpoznaje ją po jednym wpisie Rytmu `Running` od `rhythm_start` (`UnloadBudgetSheet.php:74-106`);
  - wiersz w wydruku komendy (`LoadBudgetSheetCommand::printCategoryCheck`).

  Kategoria przychodów nie ma Rytmu (`app/Support/CategoryRhythms.php:16`).
- **Domena nie blokuje 2019.** Rok 2000–2100 jest dozwolony; blokady, płynność i raporty są kluczowane `sheet_year` (research §5). Odczyty, które zmieniają się z założenia: kolumna „rok wcześniej” raportu 2020 (`ReportComparisonPeriod.php:35`) i widok „Lata”, gdzie 2019 będzie pierwszą kolumną (`ReportYearsRange.php:37-42`).
- **Wzorzec poprawki pliku:** `context/archive/2026-11-13-sheet-load-2020-2021/workbook-fix.md`. Była to punktowa edycja XML (`<f>` i `<v>`), zbiór komórek zależnych wyznaczony silnikiem przed i po, `fullCalcOnLoad="1"`, kopia oryginału i dowód „różni się dokładnie N komórek”.
- **Testy:** `tests/Support/SheetWorkbookFactory.php` zna układy `'2023'`, `'2022'` i `'2020'` (`:14-17`, `:54`) i zawsze pisze 12 arkuszy bez sufiksu oraz `Analiza`. Testy mają po jednym „reads the X layout” na układ (`tests/Unit/BudgetSheetReaderTest.php:46,121,258`), pokrycie prawdziwego mapowania (`tests/Unit/SheetLoadPlannerTest.php`), zapis i wycofanie na PostgreSQL (`tests/Feature/LoadBudgetSheetTest.php`) oraz `--dry-run` (`tests/Feature/LoadBudgetSheetCommandTest.php`).

## Desired End State

`budget:load-sheet {user} Budżet_2019.xlsx --dry-run` na poprawionym pliku kończy się kodem 0 i wypisuje:

| Pozycja | Oczekiwanie |
| --- | --- |
| Przychody | operacje 78 039,29 zł = `Podsumowanie!I16` (bez ruchów funduszu) |
| Wydatki | operacje 67 286,75 zł = `Podsumowanie!H16` (bez ruchów funduszu) |
| Saldo otwarcia konta „Arkusz 2019” | −10 752,54 zł |
| Ruchy funduszu | brak: „cele: Sebastian” (I–VI) i „Cele” (VII–XII), 6 144,80 zł, są pomijane jak oszczędności |
| Reszty bez rozpisania | dwie: marzec −10,00 zł (lista Sebastiana zawiera `G14` „bilety”, wiersz nie) i lipiec −35,00 zł (lista ma fryzjera 35,00, `lipiec_19!D21` nie) |
| Nowa kategoria | wiersz: kategoria „Stypendium” (przychód) zostanie utworzona jako zarchiwizowana |

Kwoty operacji pochodzą z sum wierszy policzonych w researchu; autorytetem jest `--dry-run`. Różnica w sumach to STOP i badanie, a nie dopasowanie mapowania pod wynik. Znak i adres reszt potwierdza wydruk.

Oczekiwane `Podsumowanie!H3:H14` po poprawce, czyli wykonanie bez celów i bez oszczędności (research §2):

| Miesiąc | H (zł) |
| --- | --- |
| I | 3 687,64 |
| II | 4 622,88 |
| III | 4 796,91 |
| IV | 4 684,13 |
| V | 4 838,06 |
| VI | 5 531,82 |
| VII | 10 995,52 |
| VIII | 4 987,85 |
| IX | 5 120,57 |
| X | 6 300,10 |
| XI | 5 208,56 |
| XII | 6 512,71 |
| **Razem `H16`** | **67 286,75** |

Pliki 2020–2025 dają przy `--dry-run` te same sumy i ten sam wydruk co dziś (README, „Wczytanie arkusza”). Na produkcji jest Budżet 2019 w fazie execution na koncie „Arkusz 2019”, zarchiwizowana kategoria przychodów „Stypendium” z operacjami tylko w 2019, a `app:control-numbers --compare=<przed> --allow-additions` przechodzi.

### Key Discoveries:

- `SheetCells::normalizeLabel()` obniża wielkość liter, zdejmuje końcowe `*`, scala spacje i zamienia `work:` na `praca:` (`SheetCells.php:102-109`). Etykiety 2019 trafiają do mapowania w tej postaci, np. `rachunki (prąd+telefon+internet)`, `rachunki`, `osobiste: jana`.
- Faktyczna kwota „Rachunki” `D10` w lipcu–grudniu to ręczna suma składników w kolejności Play / UPC / PGE. Na przykład `sierpień_19!D10 =130+59.99+L3`, gdzie `L3` to PGE 78,63, a `październik_19!D10 =138.13+50.99+77.7`. Podział z decyzji użytkownika to więc stałe kwoty z tych formuł (Faza 3).
- `grudzień_19!C31` = 43830 = 2019-12-31 to jedyna komórka z datą w grudniu. W styczniu–maju rok jest tylko w sufiksie nazwy arkusza (research §1).
- W listopadzie i grudniu rozpisanie Codziennych ma etykiety, które mapowanie już zna: jedzenie → 8, higiena i kosmetyki → 42, środki czystości → 42, gospodarstwo domowe → 8 (`mapping.php:63-71`). Planer bierze je przez istniejące `dailyBreakdown` (ADR-0096 decyzja 4).
- Wyrównanie ujemnej reszty jest już obsłużone: `remainderOperation` daje wydatek ujemny (zwrot) w kategorii reszty (`SheetLoadPlanner.php`, `remainderOperation`), a dla list wiersza ma to być kategoria wiersza.

## What We're NOT Doing

- Trzeciego `SheetLayout` w `MonthSheetReader` ani ogólnej obsługi dowolnych układów. Znane są tylko 2022–2025, 2020–2021 i ten jeden skoroszyt 2019; każdy inny jest odrzucany z nazwą komórki.
- Wczytania `grudzień_18`, arkuszy `Oszczędności` i `Urlopy` ani bloków bocznych („Podział wydatków”, „Wizyty”, bloki „Rachunki” `K2`/`M2` poza kwotami nadpisań).
- Kwot sum w kodzie lub w `mapping.php` (ADR-0096 to odrzucił). Prawda jest w poprawionym pliku.
- Pomijania pozycji „do oddania” / „Urszula” / „oddać (alb+apt+l)” (decyzja 9): wczytujemy je jak są.
- Zmian istniejących wpisów mapowania 2020–2025, schematu bazy, snapshotu, UI, glosariusza i `rhythm_start` „Kosmetyki”.
- Opcji `--year`: rok wynika z pliku.

## Implementation Approach

1. **Decyzje przed kodem.** ADR-0102 i dopisek w ADR-0096, zgodnie z `AGENTS.md`. Do tego dwa dane z zewnątrz: lista kategorii z produkcji (id i nazwa kategorii sportowej) oraz poprawka pliku, żeby `Podsumowanie!H16`/`I16` były prawdą.
2. **Czytnik.** `BudgetSheetReader` rozpoznaje skoroszyt 2019 przed odmową i oddaje go osobnej klasie. Ta klasa czyta każdy miesiąc po etykietach w stałych kolumnach swojego wariantu i zwraca `MonthSheet`, z listami wiersza jako nowym polem. Planer, zapis, konto arkusza i wycofanie pozostają wspólne.
3. **Planer i mapowanie.**
   - Listy wiersza dołączają do `listedBlock`.
   - Nadpisania obejmują wiersze wydatków.
   - Nowa kategoria staje się zbiorem definicji kluczowanych nazwą: „Kosmetyki” i „Stypendium”.
   - `mapping.php` dostaje etykiety 2019 z decyzji użytkownika.
4. **Wczytanie na produkcji** według README, z próbą na odtworzonej kopii.

## Critical Implementation Details

- **Poprawka pliku zmienia zapisane wartości, nie tylko formuły.** Czytnik czyta zapisane wartości (`BudgetSheetReader.php:100`, `setReadDataOnly(true)`). Edycja punktowa w XML, jak w 2021:
  - `<f>` i `<v>` wybranych komórek oraz `<v>` wszystkich komórek zależnych wyznaczonych silnikiem przed i po;
  - `fullCalcOnLoad="1"` w `xl/workbook.xml`;
  - formuły współdzielone (`<f t="shared">`) obsłużone jawnie.

  Zbiór zależnych wyznaczył skan: `marzec_19!D1`, `Podsumowanie!I5`, `H16`, `I16` (`workbook-fix.md`). Uwaga: 21 komórek zależy od `TODAY()` (research), więc porównanie „silnik przed/po” musi je odfiltrować albo zamrozić dzień.
- **Kategorie z mapowania muszą mieć nazwę z produkcji.** Komenda odmawia przy innej nazwie (`SheetMappingCategoryCheck.php:17-37`). Kategoria sportowa z decyzji 5 to 49 „Sport” (odczyt z produkcji 2026-11-16, `production-categories.md`); przed wczytaniem `--dry-run` na produkcji sprawdza nazwę ponownie. Testy siewu biorą kategorie z sekcji `categories` (`LoadBudgetSheetTest.php:32-38`).
- **Przebiegi na prawdziwym pliku muszą się naprawdę odbyć**, z kodu worktree, a raport fazy podaje ich wynik: kod wyjścia, sumy, liczby reszt i operacji. Plik leży poza repo w `/home/sebastian/my-projects/household-treasurer/storage/app/private/`. Polecenie (dev DB tylko do odczytu, `--dry-run` nic nie zapisuje; sprawdzenie nazw kategorii na dev DB może odmówić, bo dev nie jest kopią produkcji, ale sumy i tabela wypisują się przed tym sprawdzeniem):
  ```bash
  docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=household_treasurer -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -v /home/sebastian/my-projects/household-treasurer/storage/app/private:/sheets:ro -w /var/www/html --entrypoint php sail-8.5/app -d memory_limit=1G artisan budget:load-sheet 1 /sheets/Budżet_2019.xlsx --dry-run
  ```

## Faza 1: ADR-0102, kategorie z produkcji i poprawka pliku 2019

### Overview

Zapisuje decyzje przed kodem, ustala kategorię sportową z produkcji i poprawia `Budżet_2019.xlsx` tak, żeby `Podsumowanie!H16` i `I16` były prawdą roku.

### Changes Required:

#### 1. ADR-0102

**File**: `context/foundation/adr/0102-sheet-load-of-2019.md`

**Intent**: Rozszerza zakres ADR-0085/0096 o rok 2019 i zapisuje decyzje użytkownika z `change.md` wraz z odrzuconymi wariantami.

**Contract**: Styl i układ jak ADR-0096 (kontekst, Rule, decyzje, Consequences, We rejected). Treść:
- **Zakres.** 2019 ze skoroszytu z arkuszami `<miesiąc>_19` i `Podsumowanie`, rozpoznanego przed odmową. Rok z `grudzień_19!C31` (31 grudnia), zgodny z sufiksem. `grudzień_18`, `Oszczędności` i `Urlopy` nie są czytane.
- **Prawda.** Poprawione `Podsumowanie!H16`/`I16` pełni rolę „Analizy”:
  - `H` = wykonanie bez celów i bez oszczędności;
  - `marzec_19!B3` obejmuje „inne” 125,00;
  - zapis w `workbook-fix.md`.
- **Pominięcia.** „cele: Sebastian”, „Cele”, „Oszczędności”, „Oszczędności (10%)”, „z poprzedniego” i przychód „oszczędności” są pomijane (SKIP): nie są operacjami ani ruchami funduszu, a poprawione `Podsumowanie` ich nie liczy.
- **Listy.** „Wydatki osobiste” (Sebastian, Jana) i „Dom i akcesoria” są rozbite na pozycje w kategorii wiersza; reszta listy wiersza idzie do kategorii wiersza. Rozpisanie Codziennych z listopada i grudnia jak w ADR-0096 decyzja 4.
- **Rachunki.** Podział na Telefony / Internet / Prąd według składników `D10` jako nadpisania wydatków.
- **Stypendium.** Nowa zarchiwizowana kategoria przychodów, tworzona przez wczytanie i usuwana przez wycofanie. Nowa kategoria z ADR-0085 staje się zbiorem definicji.
- **Mapowanie** z decyzji 7 i kategoria sportowa z produkcji.
- **Odrzucone:**
  - `Podsumowanie` bez poprawki z listą odchyleń;
  - suma z wierszy liczona w kodzie (kontrola sprawdzająca samą siebie);
  - cele jako wydatek w kategoriach;
  - cele jako ruch funduszu liczony w uzgodnieniu (wybrany najpierw, zmieniony przez użytkownika: wpłaty na cele nie są wydatkami roku);
  - jedna operacja na wiersz zamiast pozycji list;
  - „Rachunki” w całości do Prądu;
  - podział „Rachunków” według dzisiejszych opłat;
  - stypendium w źródle Sebastian;
  - trzeci `SheetLayout`.

#### 2. ADR-0096: dopisek o zmianie

**File**: `context/foundation/adr/0096-sheet-load-of-2020-and-2021.md`

**Intent**: Decyzja 1 mówi „2019 is out of scope”. Dopisek „(Extended by ADR-0102: 2019 …)” wskazuje nowy ADR, tak jak dopisek w ADR-0085 decyzji 1.

**Contract**: Jedno zdanie w nawiasie przy decyzji 1; treść decyzji bez zmian.

#### 3. Kategoria sportowa z produkcji

**File**: `context/changes/sheet-load-2019/production-categories.md`

**Intent**: Wykonane 2026-11-16 w trakcie planowania (odczyt tylko do odczytu przez SSH, bez `user:backup`): kategoria sportowa to **49 „Sport”**. Notatka zapisuje id, nazwę, typ i stan archiwum kategorii sportowej oraz pełną listę id → nazwa dla id spoza `mapping.php` (13, 26, 38, 40, 41, 43, 46–49).

**Contract**: Tabela id | nazwa | typ | zarchiwizowana, plus źródło (nazwa pliku snapshotu, data). Bez danych osobowych poza nazwami kategorii.

#### 4. Poprawka `Budżet_2019.xlsx`

**File**: `storage/app/private/Budżet_2019.xlsx` (poza repo, gitignored) i zapis w `context/changes/sheet-load-2019/workbook-fix.md`

**Status**: wykonane 2026-11-16 przed implementacją (na prośbę użytkownika): plik poprawiony, dowód `workbook-fix/verify.php` PASS, zapis w `workbook-fix.md` (SHA-256 po poprawce `73fc70cf…4c86`). Implementacja tej pozycji tylko uruchamia dowód; nie poprawia pliku drugi raz.

**Intent**: Poprawić `Podsumowanie!H3:H14`, żeby każdy miesiąc sumował wykonanie wierszy wydatków bez celów i bez oszczędności. Poprawić `marzec_19!B3`, żeby obejmowało `B7`. Zachować oryginał obok jako `Budżet_2019.przed-korekta.xlsx`.

**Contract**:
- Formuły `H3:H14` odwołują się wprost do komórek wykonania:
  - styczeń–czerwiec: C wierszy stałych oraz B wierszy list, bez „cele: Sebastian” i „Oszczędności”;
  - lipiec–grudzień: D wierszy pozycji przed „Cele”, bez „Cele” i „Oszczędności (10%)”.
- `marzec_19!B3 =B4+B5+B6+B7`.
- Zapisane wartości `H3:H14` równe tabeli z „Desired End State”: `H16` = 67 286,75, `I5` = 6 042,61, `I16` = 78 039,29.
- `workbook-fix.md` jak plik 2021: SHA-256 oryginału i poprawki, tabela zmienionych komórek (formuła, wartość przed → po), metoda, dowód porównania wszystkich komórek wszystkich arkuszy.

### Success Criteria:

#### Automated Verification:

- ADR-0102 istnieje, a ADR-0096 decyzja 1 ma dopisek „Extended by ADR-0102”: `grep -n "ADR-0102" context/foundation/adr/0096-sheet-load-of-2020-and-2021.md`
- Skrypt dowodu poprawki kończy się kodem 0 (`php context/changes/sheet-load-2019/workbook-fix/verify.php "$PWD" storage/app/private/Budżet_2019.przed-korekta.xlsx storage/app/private/Budżet_2019.xlsx`):
  - różnią się dokładnie komórki z tabeli `workbook-fix.md` (poza komórkami `TODAY()`);
  - zapisane nowe wartości równają się wynikowi silnika;
  - `Podsumowanie!H16` = 67 286,75, `I16` = 78 039,29;
  - `unzip -t` bez błędów.
- SHA-256 `Budżet_2019.przed-korekta.xlsx` równa się `b99e9217bc4b4bfa075e7a25e815598118c16e50c94e000a2b5a499b35fbf0f6`

#### Manual Verification:

- Użytkownik dostarczył listę kategorii z produkcji, a `production-categories.md` podaje id i nazwę kategorii sportowej
- Użytkownik otworzył poprawiony plik w arkuszu kalkulacyjnym i zaakceptował miesięczne `H` oraz `marzec_19!B3`

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika (lista kategorii i akceptacja poprawki), zanim ruszy Faza 2.

---

## Faza 2: Czytnik 2019

### Overview

`BudgetSheetReader` rozpoznaje skoroszyt 2019 i czyta go osobną klasą do `BudgetSheet` z `Podsumowanie!I16`/`H16` jako sumami arkusza. `MonthSheet` dostaje listy wiersza.

### Changes Required:

#### 1. Listy wiersza w `MonthSheet`

**File**: `app/Support/SheetLoad/MonthSheet.php`

**Intent**: Nowe pole niesie listę pozycji przypiętą do konkretnego wiersza wydatku. Dotychczasowe układy dają pustą tablicę, więc ich zachowanie się nie zmienia.

**Contract**:
- `rowLists`: `array<string, list<SheetListItem>>` kluczowane adresem komórki kwoty wiersza (`SheetEntry::$address`), domyślnie `[]`.
- Opis pozycji z listy osobistej niesie osobę: nazwa z arkusza + ` (Jana)` / ` (Sebastian)`, ucięta jak dziś do 255 znaków.

#### 2. Rozpoznanie skoroszytu 2019 i rok

**File**: `app/Support/SheetLoad/BudgetSheetReader.php`

**Intent**: Przed odmową „to nie jest też układ 2020–2021” rozpoznać skoroszyt z arkuszem `Podsumowanie` i arkuszami miesięcy z sufiksem `_<rr>` i oddać go czytnikowi 2019. Inny układ jest dalej odrzucany z nazwą komórki.

**Contract**:
- Rozpoznanie: istnieje `Podsumowanie` i dwanaście arkuszy `<nazwa z MONTH_SHEETS>_19`.
- Rok = rok daty w `grudzień_19!C31`, która musi być 31 grudnia i zgadzać się z sufiksem; inaczej `SheetReadException` z adresem tej komórki.
- `analysisIncome` = `Podsumowanie!I16`, `analysisExpense` = `Podsumowanie!H16`.
- Docblock `BudgetSheet` wymienia trzecie źródło sum.

#### 3. Czytnik miesiąca 2019

**File**: `app/Support/SheetLoad/MonthSheet2019Reader.php` (nowy; nazwa do potwierdzenia przy implementacji, zgodnie z konwencją katalogu)

**Intent**: Czyta jeden arkusz `<miesiąc>_19` w wariancie rozpoznanym po etykietach i zwraca `MonthSheet`.

**Contract**:
- **Rozpoznanie wariantu po etykietach:**
  - A: „przychody” w `A5` i „wydatki” w `A10`;
  - B: „przychody” w `A3` i „wydatki” w `A8`;
  - C: „przychody” w `A2` i „wydatki” w `A7`.
  - Inny wariant to odmowa z adresem.
- **Przychody:** wszystkie wiersze z etykietą między „Przychody” a „Wydatki”, kwota z B (A/B) lub C (C).
- **Wydatki A/B:**
  - wiersze z etykietą w A od wiersza po „Wydatki” do ostatniego wiersza z etykietą bloku;
  - kwota z C;
  - wiersze list (`akcesoria do domu`, `osobiste: jana`, `osobiste: sebastian`, `osobiste`) i `cele: sebastian` biorą B, gdy C jest puste;
  - każdy inny wiersz z pustym C i niezerowym B to odmowa z adresem (B to plan);
  - wiersz bez etykiety z kwotą 0 jest pomijany, a z kwotą różną od 0 to odmowa.
- **Wydatki C:** wiersze z etykietą pozycji w B od wiersza po „Wydatki” do wiersza przed „Pozostałe”, kwota z D.
- **`irregularTotal` i `balance`:** zerowe `SheetEntry` z adresem nagłówka „Wydatki”. Listy nieregularna, medyczna i badań są puste, a wypłata z funduszu = 0.
- **Listy wiersza:**
  - osobiste Sebastiana i Jany wiążą się z wierszami osobistymi miesiąca; w czerwcu i w wariancie C obie listy wiążą się z jednym wierszem „osobiste”;
  - „Dom i akcesoria” wiąże się z „akcesoria do domu”;
  - lista biegnie od wiersza pod nagłówkiem do wiersza przed nagłówkiem następnego bloku; puste wiersze i wiersze bez kwoty są pomijane;
  - kwoty od października z kolumn „wydatek:” (I, L), nie „plan:”.
- **Lista „Cele”** nie jest czytana: jej wiersz jest pomijany (SKIP).
- **Rozpisanie Codziennych:** w listopadzie i grudniu `dailyBreakdown` z `M13:O16` (etykieta M, kwota O); w pozostałych miesiącach `null`.
- **Ostrzeżenia** (nie odmowy) tak jak dziś:
  - suma listy wiersza różna od wiersza;
  - rozpisanie różne od wiersza „Codzienne wydatki”.

#### 4. Fabryka skoroszytów

**File**: `tests/Support/SheetWorkbookFactory.php`

**Intent**: Układ `'2019'` pisze mały skoroszyt w kształcie 2019: `grudzień_18`, dwanaście arkuszy `_19` w wariantach A/B/C według miesiąca, `Podsumowanie` z sumami i `grudzień_19!C31` jako datą.

**Contract**:
- Te same opcje co inne układy (`months` z nadpisaniem wierszy).
- Co najmniej jedna komórka z formułą, której wartość zapisaną czyta czytnik, np. wiersz listy `=SUM(...)` i `H16 =SUM(...)`.
- Etykieta RichText w jednej komórce przychodu.
- Wariant C z listami wydatku „plan:”/„wydatek:” w październiku.

#### 5. Testy czytnika

**File**: `tests/Unit/BudgetSheetReaderTest.php`

**Intent**: Pokryć rozpoznanie, rok, trzy warianty, listy wiersza, rozpisanie i odmowy.

**Contract** — testy:
- „reads the 2019 workbook”: rok, 12 miesięcy, sumy `Podsumowanie`, adresy;
- B wierszy list w styczniu–maju i C/D wierszy stałych;
- czerwcowy jeden wiersz osobisty z dwiema listami;
- kolumny „wydatek:” od października;
- rozpisanie listopad–grudzień;
- odmowa: wiersz stały z pustym C i niezerowym B; `grudzień_19!C31` nie 31 grudnia; nieznany wariant miesiąca;
- ostrzeżenie: lista wiersza różna od wiersza;
- odczyt prawdziwych plików 2020–2025 w testach bez zmian (istniejące testy zielone).

### Success Criteria:

#### Automated Verification:

- Testy czytnika przechodzą: `docker compose exec -u sail laravel.test php artisan test --compact tests/Unit/BudgetSheetReaderTest.php`
- PHPStan i Pint bez błędów: `composer ci:check` (krok analizy) oraz `vendor/bin/pint --dirty --format agent`
- Czytnik na prawdziwym, poprawionym pliku (kod worktree, skrypt jednorazowy lub `tinker` tylko do odczytu) zwraca rok 2019, 12 miesięcy, `analysisExpense` 67 286,75 i `analysisIncome` 78 039,29, bez wyjątku

---

## Faza 3: Planer, mapowanie i nowe kategorie

### Overview

Planer korzysta z list wiersza i nadpisań wydatków, nowa kategoria staje się zbiorem definicji, a `mapping.php` dostaje etykiety 2019. Faza kończy się `--dry-run` prawdziwego pliku z sumami z „Desired End State”.

### Changes Required:

#### 1. Listy wiersza w planerze

**File**: `app/Support/SheetLoad/SheetLoadPlanner.php`

**Intent**: Gdy wiersz wydatku ma listę w `MonthSheet::rowLists`, planer daje operację na każdą pozycję w kategorii wiersza i resztę w kategorii wiersza. Dotychczasowe bloki (leczenie, badania, Codzienne) działają bez zmian.

**Contract**:
- `listedBlock` sprawdza najpierw `rowLists[$entry->address]`.
- Reszta listy wiersza trafia do celu wiersza, a nie do sekcji `remainders`.
- Cel `FUND`/`SKIP` wiersza z listą to odmowa z adresem; dziś cel `FUND` w pozycji listy jest już odmową w `itemOperations`.

#### 2. Nadpisania wydatków

**Files**: `app/Support/SheetLoad/SheetLoadPlanner.php`, `app/Support/SheetLoad/SheetMapping.php`, `database/sheet-load/mapping.php`

**Intent**: Część komórki wiersza wydatku staje się osobną operacją w innej kategorii, tak jak dziś w przychodach. Służy do podziału „Rachunków” 2019.

**Contract**:
- Wpis `overrides` dostaje opcjonalne `'type' => 'expense'` (domyślnie przychód, więc istniejące wpisy 2023–2025 się nie zmieniają).
- Nadpisanie wydatku działa na wierszu bez listy:
  - kwota nadpisania nie większa od komórki, inaczej odmowa jak dziś;
  - reszta komórki zostaje operacją wiersza w jego kategorii (0 → bez operacji);
  - nieużyte nadpisanie wydatku to odmowa jak w przychodach.
- Wpisy 2019 (etykieta po normalizacji; reszta wiersza → 15 Prąd):

  | Miesiąc | Etykieta | → 14 Telefony „Play” | → 44 Internet „UPC” | Reszta → 15 |
  | --- | --- | --- | --- | --- |
  | 7 | `rachunki (prąd+telefon+internet)` | 131,00 | — | 117,05 |
  | 8 | `rachunki` | 130,00 | 59,99 | 78,63 |
  | 9 | `rachunki` | 130,21 | 95,18 | 0 |
  | 10 | `rachunki` | 138,13 | 50,99 | 77,70 |
  | 11 | `rachunki` | 130,14 | 50,99 | 0 |
  | 12 | `rachunki` | 131,15 | 50,99 | 0 |

  Razem: Telefony 790,63, Internet 308,14, Prąd 273,38 = 1 372,15 zł. Opisy np. „Play (arkusz 2019)” i „UPC (arkusz 2019)”.

#### 3. Nowe kategorie jako zbiór definicji

**Files**: `app/Support/SheetLoad/SheetMapping.php`, `app/Support/SheetLoad/SheetMappingCategoryCheck.php`, `app/Actions/LoadBudgetSheet.php`, `app/Actions/UnloadBudgetSheet.php`, `app/Console/Commands/LoadBudgetSheetCommand.php`, `database/sheet-load/mapping.php`

**Intent**: Wczytanie tworzy każdą potrzebną nową kategorię z definicji kluczowanej nazwą, a wycofanie usuwa każdą, która nie ma już linii i ma tylko historię zapisaną przez wczytanie. Zachowanie „Kosmetyki” się nie zmienia.

**Contract**:
- **`mapping.php`:** `new_categories` => `['kosmetyki' => [...dzisiejsze `new_category`...], 'stypendium' => ['name' => 'Stypendium', 'type' => 'income', 'icon' => …, 'color' => …, 'rhythm_start' => null]]`. Ikona i kolor z dozwolonego zestawu kategorii (te same reguły co w `CategoryValidationRules`).
- **Cel w mapowaniu:** `SheetMapping::NEW_CATEGORY` zostaje celem „Kosmetyki”. Cel nowej kategorii o kluczu K ma postać zwracaną przez jedną metodę `SheetMapping` (np. `newCategoryTarget('stypendium')`), a `targetName()` rozpoznaje każdy klucz.
- **`SheetMappingCategoryCheck::existingNewCategory`:** szuka po kluczu (typ i `name_key` definicji).
- **`LoadBudgetSheet`:** mapa klucz → id dla celów nowych kategorii w planie:
  - istniejąca kategoria tego typu i nazwy jest użyta ponownie;
  - nowa jest tworzona jako zarchiwizowana;
  - `ChangeCategoryRhythm` tylko dla wydatku z `rhythm_start`;
  - przychód bez Rytmu.
- **`UnloadBudgetSheet::removeNewCategory`:** dla każdej definicji usuwa kategorię, gdy jest zarchiwizowana i bez linii, a historia Rytmu to dokładnie jeden wpis `Running` od `rhythm_start` (wydatek) albo brak wpisów (przychód bez `rhythm_start`).
- **Komenda:** jeden wiersz na każdą nową kategorię w planie („Kategoria „Stypendium” zostanie utworzona (zarchiwizowana).” albo „już istnieje (id N) i zostanie użyta.”); kolumna Id „nowa”.

#### 4. Mapowanie 2019

**File**: `database/sheet-load/mapping.php`

**Intent**: Etykiety 2019 z decyzji użytkownika. Istniejące wpisy 2020–2025 bez zmian.

**Contract**: Nowe klucze, po normalizacji, w blokach z komentarzem „2019 (ADR-0102)”:
- **`expenses`:**
  - prąd+gaz → 15;
  - rachunki (prąd+telefon+internet), rachunki → 15 (z nadpisaniami z pkt 2);
  - samochód: inne → 17;
  - cinema city, rozrywka: inne, rozrywka - inne, wyjścia → 23;
  - lekarze, zdrowie i uroda: inne → 20;
  - apteka i kosmetyki → 12;
  - akcesoria do domu → 37;
  - osobiste, osobiste: jana, osobiste: sebastian → 29;
  - siłownia → 49 (Sport; `production-categories.md`);
  - cele, cele: sebastian, oszczędności, oszczędności (10%) → SKIP.
  - Etykiety już zmapowane (czynsz, telefony, internet, codzienne wydatki, paliwo, kosmetyczka, kosmetyczka i fryzjer, apteka, rata kredytu, ubezpieczenie, bilety i transport) zostają, jeśli ich postać po normalizacji jest ta sama. Każdą etykietę 2019 sprawdza test pokrycia.
- **`income`:** stypendium → nowa kategoria „stypendium”; z poprzedniego → SKIP; inne i oszczędności są już zmapowane (7, SKIP).
- **`categories`:** dopisać `49 => ['name' => 'Sport', 'type' => 'expense']`. Bez tego sprawdzenie jej nie obejmie, a testy jej nie zasieją.

#### 5. Testy planera, komendy i zapisu

**Files**: `tests/Unit/SheetLoadPlannerTest.php`, `tests/Feature/LoadBudgetSheetCommandTest.php`, `tests/Feature/LoadBudgetSheetTest.php`

**Intent**: Zablokować nowe zachowania i zgodność wstecz.

**Contract** — testy:
- **Planer:**
  - lista wiersza: pozycje w kategorii wiersza, reszta ujemna i dodatnia w kategorii wiersza;
  - wiersz z listą i celem FUND to odmowa;
  - nadpisanie wydatku: podział, reszta 0 bez operacji, kwota większa od komórki to odmowa, nieużyte to odmowa;
  - prawdziwe mapowanie mapuje każdą etykietę fixture `'2019'` i fixture 2020 bez zmian;
  - uzgodnienie z `Podsumowanie` co do grosza z pominiętymi celami i oszczędnościami (bez ruchów funduszu).
- **Komenda `--dry-run` na fixture `'2019'`:**
  - wiersz o utworzeniu „Stypendium”;
  - brak sekcji ruchów funduszu;
  - sumy w formacie bez ruchów funduszu („Wydatki: … zł, „Analiza” Podsumowanie!H16: … (zgodne)”).
- **Zapis na PostgreSQL:**
  - wczytanie 2019 tworzy zarchiwizowaną „Stypendium” bez Rytmu z liniami tylko w 2019;
  - `budget:unload-sheet` 2019 ją usuwa;
  - istniejąca „Stypendium” użytkownika (niezarchiwizowana albo z innymi liniami) zostaje;
  - ścieżka „Kosmetyki” bez zmian (istniejące testy zielone);
  - dodatki tylko „additions” (`app:control-numbers --allow-additions` jak w obecnym teście round-trip).

### Success Criteria:

#### Automated Verification:

- Testy Sheet Load na PostgreSQL przechodzą: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Unit/SheetLoadPlannerTest.php tests/Unit/BudgetSheetReaderTest.php tests/Feature/LoadBudgetSheetTest.php tests/Feature/LoadBudgetSheetCommandTest.php`
- `--dry-run` poprawionego `Budżet_2019.xlsx` (polecenie z „Critical Implementation Details”) wypisuje przychody 78 039,29, wydatki 67 286,75 (bez ruchów funduszu), saldo otwarcia −10 752,54, dwie reszty bez rozpisania (marzec, lipiec) i wiersz o utworzeniu „Stypendium”
- `--dry-run` plików 2020–2025 daje te same sumy co README („Wczytanie arkusza”)

#### Manual Verification:

- Użytkownik przejrzał tabelę kategorii z `--dry-run` 2019, w tym Telefony / Internet / Prąd z podziału „Rachunków”, 29 Rzeczy osobiste, 37 Wyposażenie i 49 Sport

---

## Faza 4: Dokumentacja i bramka

### Overview

README i roadmapa opisują zakres 2019–2025; pełna bramka przechodzi.

### Changes Required:

#### 1. README

**File**: `README.md`

**Intent**: Sekcja „Wczytanie arkusza” obejmuje 2019. Zmienia się nagłówek „(lata 2019–2025)” i kotwica w tabeli komend (`:131-132`), pętla kopiowania (`:231`), oczekiwane sumy 2019 przy kroku 4, kolejność wczytania i wycofania oraz zdanie o „Stypendium” tworzonej przez wczytanie.

**Contract**: Sekcja `README.md:220-252` i odnośniki `:131-132`.

#### 2. Roadmapa

**File**: `context/foundation/roadmap.md`

**Intent**: Opis bieżącego stanu „budżety 2020–2027 … (ADR-0085, ADR-0096)” (`:38`, `:74`) uwzględnia 2019 i ADR-0102. Zamknięte kamienie zostają historyczne.

**Contract**: Dwie linie.

### Success Criteria:

#### Automated Verification:

- Bramka na PostgreSQL przechodzi: `composer ci:check` (w Sail, z `DB_CONNECTION=pgsql`)
- Sloppy bez nowych znalezisk wysokiej wagi: `vendor/bin/sloppy ci`
- Format PHP: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- Użytkownik przeczytał sekcję README i uznał procedurę za wykonalną

---

## Faza 5: Wczytanie na produkcji

### Overview

Próba na kopii produkcji, potem wczytanie 2019 na produkcji według README, z dowodem „tylko dodano”. Kroki wykonuje użytkownik albo agent na jego polecenie, krok po kroku.

### Changes Required:

#### 1. Notatki z wczytania

**File**: `context/changes/sheet-load-2019/production-load-notes.md`

**Intent**: Ślad wykonania jak w `context/archive/2026-11-13-sheet-load-2020-2021/production-load-notes.md`: wdrożony commit, SHA-256 pliku na serwerze (= poprawiony), pliki snapshotu i liczb kontrolnych „przed”, wynik `--dry-run`, id Budżetu, konta arkusza i „Stypendium”, wynik `--compare`.

**Contract**: Kolejność z README:
1. wdrożenie (bez migracji);
2. kopiowanie pliku;
3. `user:backup` i `app:control-numbers` „przed”;
4. `--dry-run`;
5. wczytanie 2019;
6. `--compare … --allow-additions`;
7. przegląd w aplikacji.

### Success Criteria:

#### Manual Verification:

- Próba na kopii produkcji (`restore_trial`): wczytanie 2019, `--compare --allow-additions` kod 0 („categories +1”), wycofanie, `--compare` bez `--allow-additions` „no differences”, ponowne wczytanie
- SHA-256 `Budżet_2019.xlsx` na serwerze równa się SHA-256 po poprawce z `workbook-fix.md`
- Produkcja: `--dry-run` 2019 z sumami z Fazy 3, wczytanie, `app:control-numbers --compare=<przed> --allow-additions` kod 0
- Aplikacja na produkcji:
  - lata 2019–2027 w selektorze roku;
  - raport 2020 z kolumną 2019;
  - widok „Lata” od 2019;
  - „Stypendium” zarchiwizowana na „Kategoriach”;
  - saldo „Na kontach” i szkic 2027 bez zmian

---

## Testing Strategy

### Unit Tests:

- **Czytnik:** rozpoznanie skoroszytu 2019, rok z `grudzień_19!C31`, trzy warianty, kolumna wykonania (C, D, B list), listy wiersza z osobą w opisie, kolumny „wydatek:” od października, rozpisanie listopad–grudzień, odmowy z adresem komórki, wartości formuł.
- **Planer:** listy wiersza z resztą w kategorii wiersza, nadpisania wydatków, cele i oszczędności pominięte poza uzgodnieniem, pełne pokrycie etykiet 2019 prawdziwym mapowaniem, zgodność wstecz fixture 2020/2022/2023.

### Integration Tests:

- Komenda `--dry-run` na skoroszycie `'2019'`: reszty, nowa kategoria i sumy bez ruchów funduszu.
- Zapis i wycofanie roku 2019 na PostgreSQL: „Stypendium” utworzona, potem usunięta; istniejąca kategoria użytkownika zostaje.

### Manual Testing Steps:

1. `--dry-run` poprawionego pliku 2019 i plików 2020–2025 z kodu worktree, porównanie z „Desired End State” i README.
2. Przegląd tabeli kategorii 2019 przez użytkownika.
3. Próba na kopii produkcji, potem produkcja według README.

## Performance Considerations

Plik 2019 ma ~106 KB (2022–2025 ~1 MB). Czytnik liczy `highestRow()` raz na arkusz, jak `SheetCells`.

## Migration Notes

Brak migracji. Wycofanie: `budget:unload-sheet {user} 2019`, które usuwa też „Stypendium”, gdy nie ma innych linii. Powrót do pliku sprzed poprawki: skopiować `Budżet_2019.przed-korekta.xlsx`.

## References

- Research: `context/changes/sheet-load-2019/research.md`
- Decyzje użytkownika: `context/changes/sheet-load-2019/change.md`
- Decyzje bazowe: `context/foundation/adr/0085-sheet-load-of-past-years.md`, `context/foundation/adr/0096-sheet-load-of-2020-and-2021.md`
- Poprzednia zmiana: `context/archive/2026-11-13-sheet-load-2020-2021/plan.md`, `workbook-fix.md`, `production-load-notes.md`
- Czytnik: `app/Support/SheetLoad/BudgetSheetReader.php:15,28,46,100`, `MonthSheetReader.php:14-40`, `MonthSheet.php`
- Planer: `app/Support/SheetLoad/SheetLoadPlanner.php:42-43,373` (`listedBlock`, `incomeGroups`, `applyOverride`)
- Nowa kategoria: `app/Actions/LoadBudgetSheet.php:102-129,226`, `app/Actions/UnloadBudgetSheet.php:74-106`, `app/Support/SheetLoad/SheetMappingCategoryCheck.php:43-52`
- Mapowanie: `database/sheet-load/mapping.php`
- Fabryka: `tests/Support/SheetWorkbookFactory.php:14-17,54`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0102, kategorie z produkcji i poprawka pliku 2019

#### Automated

- [x] 1.1 ADR-0102 istnieje, a ADR-0096 decyzja 1 ma dopisek „Extended by ADR-0102” — 592d854
- [x] 1.2 Skrypt dowodu poprawki kończy się kodem 0 (dokładnie komórki z tabeli, wartości = silnik, H16 = 67 286,75, I16 = 78 039,29, unzip -t) — 592d854
- [x] 1.3 SHA-256 kopii przed-korekta równa się b99e9217…0f6 — 592d854

#### Manual

- [x] 1.4 Lista kategorii z produkcji dostarczona, production-categories.md podaje kategorię sportową — 592d854
- [x] 1.5 Użytkownik zaakceptował poprawione H miesięcy i marzec_19!B3 — potwierdzone 2026-11-16

### Phase 2: Czytnik 2019

#### Automated

- [x] 2.1 Testy czytnika przechodzą — d513a39
- [x] 2.2 PHPStan i Pint bez błędów — d513a39
- [x] 2.3 Czytnik na prawdziwym poprawionym pliku zwraca rok 2019, 12 miesięcy i sumy Podsumowania — d513a39

### Phase 3: Planer, mapowanie i nowe kategorie

#### Automated

- [x] 3.1 Testy Sheet Load na PostgreSQL przechodzą — 4a12fc8
- [x] 3.2 --dry-run poprawionego pliku 2019 z sumami z Desired End State — 4a12fc8
- [x] 3.3 --dry-run plików 2020–2025 z sumami z README — 4a12fc8

#### Manual

- [x] 3.4 Użytkownik przejrzał tabelę kategorii z --dry-run 2019 — potwierdzone 2026-11-16

### Phase 4: Dokumentacja i bramka

#### Automated

- [x] 4.1 composer ci:check na PostgreSQL przechodzi — 850e902
- [x] 4.2 Sloppy bez nowych znalezisk wysokiej wagi — 850e902
- [x] 4.3 Pint bez zmian — 850e902

#### Manual

- [x] 4.4 Użytkownik przeczytał sekcję README — potwierdzone 2026-11-16

### Phase 5: Wczytanie na produkcji

#### Manual

- [x] 5.1 Próba na kopii produkcji: wczytanie, compare, wycofanie, ponowne wczytanie — 2026-11-16, production-load-notes.md
- [x] 5.2 SHA-256 pliku na serwerze = po poprawce — 2026-11-16, production-load-notes.md
- [x] 5.3 Produkcja: --dry-run, wczytanie, compare --allow-additions kod 0 — 2026-11-16, production-load-notes.md
- [x] 5.4 Aplikacja na produkcji: lata 2019–2027, raport 2020 z kolumną 2019, Lata od 2019, Stypendium zarchiwizowana, saldo i szkic 2027 bez zmian — potwierdzone przez użytkownika 2026-11-16
