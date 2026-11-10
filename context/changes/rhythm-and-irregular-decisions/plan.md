# Rhythm and Irregular Decisions Implementation Plan

## Overview

Element przygotowawczy F-05 z `context/foundation/roadmap.md`. Zapisuje decyzje domenowe rytmu kategorii i wydatku nieregularnego **przed kodem** (AGENTS.md: zmiana reguły słownika zaczyna się od wpisu i ADR). Nowy ADR-0066 opisuje cztery rytmy zastępujące flagi Paced / One-off i Month Item Kind, mapowanie flag przy migracji, miejsce rytmu (historia na kategorii listy), stawkę i „zebrane”, regułę „do wydania” dla wydatku nieregularnego i przejście zebranego przez 31 grudnia. Zmienia ADR-0024, ADR-0030, ADR-0033, ADR-0043, ADR-0050, ADR-0062 i ADR-0063. `context/foundation/glossary.md` dostaje odpowiadające terminy. Zmiana dotyczy wyłącznie dokumentów. Odblokowuje S-24 (rytm w propozycji planu), S-26 (nieregularna i „zebrane”) i S-27 (31 grudnia) i rozstrzyga Otwarte pytania 1 i 2 z PRD v3.

## Current State Analysis

- **Flagi leżą na pozycji budżetu i działają w dwóch planach.** `budget_lines.is_paced` (domyślnie `true`) i `budget_lines.month_item_kind` (`bill` / `running`, domyślnie `running`), `app/Models/BudgetLine.php:25-26,37-38`, przeniesione z `categories` w `2026_11_09_122524_expand_…`. Przychód zawsze ma `true` / `running`.
  - `month_item_kind` steruje miesiącem: „do wydania” (`app/Support/ToSpendCalculator.php:76-80`, TS `resources/js/lib/to-spend-calculator.ts:28-36`), odchylenie i status (`app/Support/MonthItemDeviation.php:27-53`, `resources/js/lib/month-plan-status.ts:33-66`), ostrzeżenia „do wydania” przy operacji i w Imporcie (`entry-to-spend-warning.ts:143`).
  - `is_paced` steruje rokiem: propozycja jednorazowej = 0 (`app/Support/MonthPlanProposal.php:174-176`), Month Remaining i pula równomiernych (`resources/js/lib/month-remaining-calculator.ts:99,139-149`, ostrzeżenia `hand-entry-month-remaining-preview.ts:192,211`, `import-month-remaining-warnings.ts:72-98`), znacznik postępu roku (`resources/js/lib/dashboard-categories.ts:49-58`), powiadomienie `one_off_over_limit` przy rocznym limicie < 0 (`app/Support/DashboardNotices.php:162-205`), wiersz jednorazowej z „Pozostały limit roczny” (`MonthPlanController.php:303-309`).
  - Tylko tempo czyta obie flagi: pula = wydatek ∧ `running` ∧ nie jednorazowa (`app/Support/MonthPaceCalculator.php:26-29`).
  - `item over plan` (`MonthItemDeviation.php:46-49`, `DashboardNotices.php:121-159`) nie czyta żadnej flagi: każdy wydatek z faktyczną > plan.
  - Pozostali czytelnicy flag (dla S-24): `app/Support/MonthPace.php:32-33` (wejście tempa), `app/Support/MonthToSpend.php:77` (rodzaj do „do wydania”), `app/Support/MonthYearComparison.php:32` („W roku … z …”), `app/Support/EntryToSpendInputs.php:116-118`, `app/Support/HandEntryMonthRemainingInputs.php:56`, `app/Support/CsvImport/ImportMonthRemainingContext.php:88`, `app/Http/Controllers/DashboardController.php:259`, `app/Http/Controllers/BudgetController.php:219-220`.
- **Propozycja planu** (`MonthPlanProposal.php`): miesiące 2–12 kopiują poprzedni miesiąc (`:182-183`), styczeń grudzień tej samej kategorii z poprzedniego roku (`:155-170,186-192`), bez źródła średnia floor(plan/12) z resztą w grudniu (`:204-213`), jednorazowa zawsze 0. `month_item_kind` nie jest tu czytany.
- **Propozycja nie jest zapisywana.** `month_plans.approved_at` jest NOT NULL (`2026_11_02_091919_create_month_plans_table.php:18`): w bazie są tylko zatwierdzone plany, propozycja jest liczona w locie. Miniony miesiąc bez zatwierdzenia nie ma zapisanych kwot.
- **Dziś zmiana rodzaju pozycji działa wstecz.** Endpoint `PATCH budgets/{budget}/categories/{category}/month-item-kind` (`routes/web.php:73`) zmienia wszystkie miesiące, także minione (`lang/pl/ui.php:422`), a Podsumowanie miesiąca czyta bieżący rodzaj (`context/archive/2026-11-03-month-summary/plan.md:59`). PRD v3 wymaga, żeby rytm działał od miesiąca nadania, a minione miesiące się nie zmieniały (`prd-v3.md:237`).
- **Zapis flag:** `CreateBudget.php:48-49` (kopia z poprzedniego roku), `CreateCategory.php:36-39,58-59`, `JoinDraftBudgets.php:36-37`, `UpdateCategory.php:53-66`, `RestoreCategory.php:41-44`, walidacja `app/Concerns/CategoryValidationRules.php:28-29,62-87`, snapshot v6 z `is_paced` i `month_item_kind` per pozycja (`app/Actions/BackupUserData.php:19,176-177`, `RestoreUserData.php:90,263-264,656-674`), fabryka `BudgetLineFactory.php:100-142` (stany `bill`, `oneOff`).
- **Dane na devie** (zapytanie do bazy `household_treasurer`, 2026-11-09, użytkownik 1, budżet 2026 w wykonaniu): 31 wydatków, z czego 29 ma „równomierna + bieżąca”, a 2 „jednorazowa + bieżąca”: Ubezpieczenie samochodu (plan 541 zł, 1 operacja) i Rekolekcje (plan 2 380 zł, 2 operacje). Żadna pozycja nie jest „rachunkiem”. Prąd: plan 1 800 zł, 5 operacji. Zatwierdzony jest tylko listopad 2026 (po jednym na użytkownika).
- **Słowa w UI:** „Odkładam / dobieram”, „Odkładam”, „Dobieram” to Set-aside (`lang/pl/ui.php:434-437`). W copy nie ma „zebrane” ani „stawka”. Excel użytkownika ma „stawka m-c za prąd” 150 (`prd-v3.md:37`).
- **Reguła etapów migracji** już czeka na S-24: `.ai/rules/migrations.md:17` (rytm obok flag, czytelnicy obszarami, flagi znikają na końcu, para kalkulatorów PHP/TS w jednym commicie) i `.ai/rules/categories.md:35`.
- **Wpisy słownika zależne od flag:** Budget Line (`glossary.md:36`), Category (`:40`), Paced Category (`:47-49`), One-off Category (`:51-53`), Available Limit (`:60`), Month Remaining (`:64`), Year Elapsed (`:68`), Month Item Kind (`:83-85`), To Spend (`:88`), Year after the Month (`:92`), Month Pace (`:100`), Month Summary (`:108`), Month Result (`:116`), Earmark (`:132`). Do tego Current Month Plan (`:80`, ADR-0062) i Set-aside (`:96`).
- **Wcześniejsze odrzucenia funduszu:** ADR-0033:11 odrzucił „an irregular-expense fund”, PRD v1 (`prd.md:201,223`) fundusz i przelewy. PRD v3 godzi to tak, że „zebrane” jest odczytem w planie, nie przelewem ani kontem (`prd-v3.md:150-151`).
- **Format i numer.** Wzór F-03: `context/archive/2026-11-09-category-list-domain-decisions/plan.md`, ADR-0063. Najwyższy numer to 0065, więc następny wolny to **0066**. Baza zmiany: commit `221e00a`.

## Desired End State

- `context/foundation/adr/0066-category-rhythm-and-irregular-collected.md` zapisuje: cztery rytmy i ich zachowanie w każdym odczycie (tabela rytm × odczyt), historię rytmu na kategorii listy z miesiącem początku, mapowanie flag przy migracji i restore starego snapshotu, stawkę jako kwotę pozycji w planie miesiąca, „zebrane” jako odczyt liczony miesiąc po miesiącu z progiem 0, przejście przez 31 grudnia, nazwy PL/EN, nowe brzmienie klauzul ADR-0024/0030/0033/0043/0050/0062/0063 oraz odrzucone opcje.
- ADR-0024, ADR-0030, ADR-0033, ADR-0043, ADR-0050, ADR-0062 i ADR-0063 mają na końcu po jednej nowej linii odsyłającej do ADR-0066. Ich dotychczasowe akapity są nietknięte.
- `glossary.md` ma nowe wpisy **Rhythm**, **Irregular Rate**, **Collected** i **Opening Collected**, przepisany **To Spend** i opisane jako flagi zastąpione rytmem wpisy **Paced Category**, **One-off Category** i **Month Item Kind**. Zależne wpisy dostają minimalne dopiski. Fraza „until rhythm” nie występuje w słowniku.
- S-24, S-26 i S-27 mogą wdrożyć rytm, stawkę, zebrane i 31 grudnia bez nowych decyzji domenowych. Przykłady liczbowe z Key Decisions stają się ich przypadkami testowymi.

### Key Discoveries:

- Jednorazowa ma już sygnał „wobec roku” (`DashboardNotices.php:162-205`). Od równomiernej rocznej różni ją tylko propozycja (0 zamiast plan ÷ 12).
- Wszystkie trzy rytmy bez rachunku liczą w „do wydania” większą z planu i kwoty faktycznej (`ToSpendCalculator.php:76-80`), więc mapowanie nie zmienia „do wydania” żadnego zatwierdzonego miesiąca.
- Propozycje nie są zapisywane, więc „rytm z miesiąca” nie może mieszkać na pozycji planu miesiąca. Potrzebna jest historia na kategorii.

### Key Decisions:

Ustalone w tej sesji planowania (pozostałe pochodzą z PRD v3, decyzje strukturalne 2, 3, 5–9, FR-007, FR-009–FR-013, FR-022, i z ADR-0063):

1. **Mapowanie flag na rytm** (rozstrzyga Otwarte pytanie 1).

   | `is_paced` | `month_item_kind` | Rytm |
   | --- | --- | --- |
   | true | running | Running („bieżąca”) |
   | false | running | Even Annual („równomierna roczna”) |
   | true albo false | bill | Fixed Charge („stała opłata”) |
   | przychód | — | brak rytmu |

   Żadna kategoria nie staje się nieregularna przy migracji (PRD). Na devie: 29 wydatków → bieżąca, Ubezpieczenie samochodu i Rekolekcje → równomierna roczna. „Do wydania” każdego zatwierdzonego miesiąca zostaje co do grosza. Od pierwszej propozycji po wdrożeniu S-24 Rekolekcje proponują 198,33 zł (grudzień 198,37 zł), a Ubezpieczenie samochodu 45,08 zł (grudzień 45,12 zł), dopóki użytkownik nie oznaczy ich jako nieregularne (FR-007).
2. **Nazwy** (rozstrzyga Otwarte pytanie 2). Polskie copy: „stawka”, „zebrane”, „zebrane startowe”; wiersz nieregularnej „stawka 150 · zebrane 50”. „Odkładam” zostaje wyłącznie przy Set-aside. Rytmy: „stała opłata”, „bieżąca”, „równomierna roczna”, „nieregularna”. Angielskie terminy słownika: **Rhythm**, **Fixed Charge**, **Running**, **Even Annual**, **Irregular**, **Irregular Rate**, **Collected**, **Opening Collected**. _Avoid_: Fund, Sinking fund, Savings, Set-aside, Reserve, Earmark, Envelope, Accrual (dla stawki i zebranego).
3. **Stawka to kwota pozycji w planie miesiąca.** Propozycja wpisuje nieregularnej floor(Current Plan / 12) jej Budget Line, a grudzień bierze resztę (Current Plan − 11 × floor), jak średnia z ADR-0033. Zatwierdzenie zamraża kwotę w Approved Month Plan, a w trybie zmiany można ją poprawić (Current Month Plan). Stawka miesiąca bez zatwierdzonego planu to kwota propozycji liczona z bieżącego Current Plan, więc późniejsza zmiana planu zmienia też zebrane na początek następnych miesięcy, a przez to nadwyżkę i „do wydania” nawet zatwierdzonego miesiąca (grudzień bez zatwierdzenia, zatwierdzony styczeń), tak jak późna operacja (FR-011); Dashboard ostrzega o braku zatwierdzenia od 3. dnia. Miesiąc, w którym budżet roku nie jest w wykonaniu, ma stawkę 0. Przykład: Prąd nieregularny od stycznia 2027, zebrane startowe 0, plan 1 800, rachunki 400 w marcu i w maju, w lipcu plan roczny podniesiony do 2 400. Stawki styczeń–czerwiec zostają po 150, zebrane na 1 lipca wynosi 150 (maj: 200 + 150 − 400, nadwyżka 50 obniża „do wydania” maja), od lipca propozycja wpisuje 200. „Do wydania” styczeń–czerwiec się nie zmienia. Suma stawek roku to 6 × 150 + 5 × 200 + 200 = 2 100 z 2 400: po zmianie planu w trakcie roku stawki nie sumują się do Current Plan, a „reszta w grudniu” wyrównuje tylko zaokrąglenie. Brakujące 300 wyjdzie jako nadwyżka w miesiącu rachunku, chyba że użytkownik podniesie stawki w trybie zmiany (PRD, decyzja strukturalna 9: „użytkownik koryguje plan”).
4. **Propozycja stałej opłaty: ostatnia zapłacona kwota.** Jeśli poprzedni miesiąc miał pierwszą operację z dodatnią kwotą na tej kategorii, a jego kwota faktyczna (zwroty netto) jest > 0, propozycja wpisuje tę kwotę. Inaczej (także płatność 100 i zwrot 150, netto −50) wpisuje plan poprzedniego miesiąca, a bez źródła średnią z ADR-0033. Styczeń czyta grudzień tej samej kategorii listy (ADR-0063). Przykład: Telefon + Internet z planem października 180 i zapłatą 172,15 → propozycja listopada 172,15; październik bez zapłaty → 180. Bieżąca dalej kopiuje plan poprzedniego miesiąca (ADR-0033).
5. **Zmiana rytmu działa od bieżącego miesiąca.** Rytm to historia wpisów (rytm, miesiąc początku) na kategorii listy. Rytmem miesiąca m jest wpis o największym miesiącu początku ≤ m, a miesiąc przed pierwszym wpisem ma rytm pierwszego wpisu (operacja z Importu w minionym miesiącu nowej kategorii). Zmiana w „Kategoriach” zastępuje wszystkie wpisy od bieżącego miesiąca kalendarzowego jednym wpisem od tego miesiąca. Minione miesiące się nie zmieniają, a zatwierdzone kwoty bieżącego miesiąca też nie, zmienia się tylko reguła, którą są liczone. Oznaczenie jako nieregularna zapisuje na wpisie zebrane startowe (≥ 0, domyślnie 0). Zebrane startowe można poprawić na istniejącym wpisie bez zmiany miesiąca oznaczenia; łańcuch przelicza się sam. Zdjęcie „nieregularnej” kończy zebrane: od tego miesiąca odczytu nie ma, a salda się nie zmieniają. Ponowne oznaczenie startuje od nowa, z nowym miesiącem oznaczenia i nowym zebranym startowym. Przykład: Prąd oznaczony 9 listopada 2026 z zebranym startowym 300 → od listopada nieregularny, stawka listopada to jego kwota w zatwierdzonym planie listopada, październik i wcześniejsze bez zmian.

Ustalone przez agenta, bez osobnego pytania (wynikają z PRD i powyższych):

- **„Zebrane” liczone miesiąc po miesiącu z progiem 0.** Zebrane na początek miesiąca oznaczenia = Opening Collected; zebrane na koniec miesiąca m = max(0, zebrane na początek m + stawka m − kwota faktyczna m), a koniec m jest początkiem m + 1. Kwota faktyczna to wykonanie kategorii z operacji datowanych w miesiącu (zwroty netto). Nadwyżka m = max(0, kwota faktyczna m − (zebrane na początek m + stawka m)). Próg działa w każdym miesiącu, a nie tylko na końcu, bo nadwyżka obniżyła już „do wydania” swojego miesiąca i nie może być spłacana z późniejszych stawek. Zebrane nie jest zapisywane: późna operacja albo korekta przelicza je sama (FR-011). Przykłady US-02: na 1 lipca 300, stawka 150, rachunek 400 → zebrane 50, nadwyżka 0; rachunek 600 → zebrane 0, nadwyżka 150. Zwrot −50 w sierpniu po rachunku 400 → zebrane na koniec sierpnia 50 + 150 + 50 = 250.
- **31 grudnia bez osobnego kroku.** Łańcuch nie zatrzymuje się na końcu roku: zebrane na 1 stycznia to zebrane na koniec grudnia, a stawka stycznia wynika z planu nowego roku. „Zebrane startowe” nowego budżetu w UI (FR-012) to ten odczyt, nie zapisana liczba, bo budżet 2027 powstaje w grudniu. Przykład: Prąd z zebranym 200 na 31 grudnia 2026 i planem 2027 równym 2 100 → 1 stycznia 2027 zebrane 200, stawka stycznia 175.
- **Rytm × odczyt** (tabela w ADR-0066):

  | Odczyt | Stała opłata | Bieżąca | Równomierna roczna | Nieregularna |
  | --- | --- | --- | --- | --- |
  | „Do wydania” | plan, po pierwszej operacji z dodatnią kwotą faktyczna | max(plan, faktyczna) | max(plan, faktyczna) | stawka + nadwyżka |
  | Propozycja | ostatnia zapłacona (KD 4) | kopia poprzedniego miesiąca (ADR-0033) | floor(Current Plan / 12), reszta w grudniu | jak równomierna roczna (to jest stawka) |
  | Ponad plan (`exceeded`) | faktyczna > plan miesiąca | faktyczna > plan miesiąca | wykonanie roku > Current Plan (zastępuje `one_off_over_limit`) | nadwyżka > 0 |
  | Tempo | nie | tak (jedyna pula) | nie | nie |
  | Month Remaining (tylko minione miesiące bez planu) | w puli | w puli | poza pulą | poza pulą |
  | Znacznik postępu roku | tak | tak | tak | nie |
  | „W roku … z …” | jak dziś (reguła „do wydania”) | jak dziś | jak dziś | wykonanie przed miesiącem + kwota faktyczna miesiąca |
  | Odchylenie w Podsumowaniu | faktyczna − plan (niezapłacony: −plan w sumie) | faktyczna − plan | faktyczna − plan | nadwyżka |

  - Równomierna roczna pokazuje obok propozycji „zostało X na Y mies.”, gdzie X to Available Limit (Current Plan − wykonanie roku), a Y to liczba miesięcy do końca roku łącznie z bieżącym (FR-009).
  - Pula Month Remaining „stała opłata + bieżąca” odpowiada dzisiejszym równomiernym na wszystkich danych bez pozycji jednorazowej będącej rachunkiem (na devie takiej nie ma). S-24 liczy takie pozycje na kopii produkcji jako liczbę kontrolną.
  - Rekolekcje i Ubezpieczenie samochodu dostają znacznik postępu roku, którego dziś jako jednorazowe nie mają.
  - W Podsumowaniu kolumna wykonania nieregularnej pokazuje faktyczny rachunek, a Wynik miesiąca (Month Result) się nie zmienia. Wiersz pokazuje też stawkę i zebrane na koniec miesiąca. Odchylenie sum zostaje z prostych sum (wykonanie − Current Month Plan, jak dziś przy niezapłaconym rachunku); nadwyżkę jako odchylenie pokazuje tylko wiersz.
- **„Do wydania” bez zmian poza nieregularną:** przychody − pozycje według rytmu − Set-aside. Ostrzeżenie przy operacji i w Imporcie (spadek poniżej 0) liczy tę samą regułę, więc rachunek pokryty zebranym nie daje ostrzeżenia.
- **ADR-0062:** automatyczne podniesienie planu do kwoty faktycznej i przycisk „Ustaw plan na wydatki” nie dotyczą nieregularnej, bo podniosłyby stawkę do kwoty rachunku. Pozostałe rytmy bez zmian.
- **Archiwum i scalanie.** Zarchiwizowana Budget Line wypada z propozycji (ADR-0063), więc stawka jest 0, a zebrane stoi. Przywrócenie wznawia stawki. Przejście zebranego przy scalaniu (FR-023) rozstrzyga S-21.
- **Migracja i snapshot.** Historia rytmu dostaje dla każdej Budget Line wydatku wpis od stycznia roku jej budżetu z rytmem z mapowania (KD 1). Kolejne identyczne wpisy się scala. Restore snapshotu w wersjach 1–6 przechodzi to samo mapowanie po krokach ADR-0063 (FR-022). Nowa wersja snapshotu niesie historię rytmu z zebranym startowym (S-24, S-26). Nowa kategoria wydatku dostaje rytm bieżąca od bieżącego miesiąca.
- **Jeden ADR.** Wzorem F-01 i F-03 powstaje jeden ADR-0066, a zmieniane ADR-y dostają tylko linię odsyłającą.

## What We're NOT Doing

- Żadnego kodu, migracji, testów ani tłumaczeń w `lang/`. Kolumny, tabela historii rytmu, endpointy i nowa wersja snapshotu należą do S-24 i S-26.
- Bez UI: wygląd wiersza nieregularnej, wybór rytmu w „Kategoriach”, pole zebranego startowego, „zostało X na Y mies.”, widok zebranego startowego w nowym budżecie. To S-24, S-26 i S-27 z design systemem.
- Bez grup w planie miesiąca (S-25) i domyślnego procentu „odkładam” (S-33). „Odkładam / dobieram” się nie zmienia (FR-013).
- Bez scalania kategorii (FR-023, S-21). ADR mówi tylko, że przejście zebranego przy scalaniu rozstrzyga S-21.
- Bez zmian w PRD, `prd-mvp.md`, `.docs/specs`, `.ai/rules/*` i specyfikacjach w `context/archive/`, które dziś opisują flagi. Zmienią je wycinki razem z kodem. Wyjątkiem jest słownik, który AGENTS.md każe zmienić przed kodem.
- Bez Year Close, Reserve i Earmark poza zmianą „Paced or One-off” na „any expense Category”. Zebrane to nie Reserve ani Earmark.
- Bez osobnego odczytu „w tym zebrane” przy saldach, Spendable Liquidity i Total funds (PRD, decyzja strukturalna 6).

## Implementation Approach

Najpierw decyzja (ADR-0066 z odsyłaczami), potem słownik, który się na nią powołuje. Oba dokumenty po angielsku, jak reszta `context/foundation/adr/` i `glossary.md`, z polskim copy w cudzysłowach „…”. Każdy predykat jest zapisany jednoznacznie (próg 0 w każdym miesiącu, wpis o największym miesiącu początku ≤ m, „first operation with a positive amount”, floor z resztą w grudniu), bo S-24 i S-26 mają go wdrożyć dosłownie, w parach kalkulatorów PHP/TS (`.ai/rules/month-calculators.md`). Przykłady liczbowe (Prąd 400/600, podniesienie planu w lipcu, zwrot w sierpniu, Telefon 172,15, 31 grudnia 200 → 175, Rekolekcje 198,33/198,37) staną się przypadkami testowymi tych wycinków.

## Critical Implementation Details

- **Spójność predykatów.** Te same sformułowania muszą brzmieć identycznie w ADR-0066 i `glossary.md`: „max(0, Collected at the start of the month + Irregular Rate − actual amount)”, „the entry with the latest start month not after”, „from the current calendar month”, „last paid amount”. Weryfikacja 2.2 szuka tych fraz.
- **Odsyłacze wyłącznie dopisane.** Linie „Amended by ADR-0066” dochodzą na końcu plików. Istniejących akapitów ADR-ów nie edytujemy (precedens ADR-0024 → ADR-0033, ADR-0063); weryfikacja 1.3 to sprawdza.

## Phase 1: ADR-0066 i odsyłacze

### Overview

Zapisuje decyzję, która zastępuje flagi Paced / One-off i Month Item Kind rytmem, wprowadza stawkę i „zebrane” oraz zmienia klauzule siedmiu ADR-ów.

### Changes Required:

#### 1. Nowy ADR

**File**: `context/foundation/adr/0066-category-rhythm-and-irregular-collected.md`

**Intent**: Zapisać, co każdy rytm robi w każdym odczycie, gdzie rytm mieszka i od kiedy działa, jak liczą się stawka i zebrane i jak migracja przechodzi z flag na rytm bez zmiany „do wydania” zatwierdzonych miesięcy. Czytelnik S-24, S-26 i S-27 nie może potrzebować tej rozmowy.

**Contract**: Tytuł (H1) plus akapity, gęstość jak ADR-0063. Musi stwierdzić:

- **Kontekst.** Dwie niezależne flagi na Budget Line (ADR-0063) sterują miesiącem (`month_item_kind`) i rokiem (`is_paced`). Wydatek płacony kilka razy w roku nie pasuje do żadnej: jako bieżąca obniża „do wydania” skokiem w miesiącu rachunku, jako jednorazowa znika z miesięcy bez płatności (PRD v3, Business Logic Changes). PRD v3 (decyzje strukturalne 2, 3, 5–9) zastępuje je rytmem.
- **Rytmy:** cztery wartości z nazwami PL/EN (Key Decision 2) i tabela rytm × odczyt z „Ustalone przez agenta”, z „zostało X na Y mies.” i pulą Month Remaining. Przychód nie ma rytmu i liczy się jak dziś (plan, po pierwszym wpływie kwota faktyczna).
- **Gdzie rytm mieszka i od kiedy działa:** Key Decision 5, z przykładem Prądu z 9 listopada.
- **Stawka:** Key Decision 3, z przykładem podniesienia planu w lipcu (zebrane na 1 lipca 150, nadwyżka maja 50, suma stawek roku 2 100 z 2 400 i jej skutek).
- **Zebrane:** wzór miesiąc po miesiącu z progiem 0 i nadwyżką, uzasadnienie progu nazwane wprost jako doprecyzowanie formuły z PRD v3 (Business Logic Changes: jedna suma „nie mniej niż 0”; w przykładzie z Key Decision 3 dałaby 100 zamiast 150 na 1 lipca), wpływ zmiany planu przez miesiące bez zatwierdzenia na zatwierdzone miesiące (Key Decision 3), przykłady US-02 (400 → 50 i −150; 600 → 0 i −300, przekroczenie) i zwrotu w sierpniu. Zebrane nie zmienia sald, Spendable Liquidity, Total funds, Month Result ani wykonania i nie jest przelewem, kontem, Reserve ani Earmark.
- **31 grudnia:** łańcuch bez osobnego kroku, przykład 200 → 175.
- **Propozycja stałej opłaty:** Key Decision 4, z przykładem Telefonu.
- **Migracja i snapshot:** mapowanie z Key Decision 1, wpisy historii od stycznia roku każdej Budget Line, scalanie identycznych wpisów, ta sama konwersja przy restore wersji 1–6, nowa kategoria = bieżąca. Przykład z deva (29 → bieżąca, 2 → równomierna roczna, 198,33/198,37 i 45,08/45,12 zł). Liczba kontrolna „jednorazowa + rachunek” dla S-24.
- **Nowe brzmienie klauzul:**
  - ADR-0024: pula Month Remaining to stała opłata + bieżąca w danym miesiącu; „jednorazowa nie ma Month Remaining” staje się „równomierna roczna i nieregularna nie mają Month Remaining”.
  - ADR-0033: reguła propozycji według rytmu (tabela), stała opłata proponuje ostatnią zapłaconą kwotę, Month Item Kind nie jest już kopiowany na nowy budżet, „do wydania” według rytmu, odrzucony fundusz wydatków nieregularnych zostaje odrzucony („zebrane” jest odczytem, nie funduszem z przelewami).
  - ADR-0030: Earmark z dowolnej wydatkowej kategorii zamiast „(Paced or One-off)”.
  - ADR-0043: znacznik Year Elapsed mają przychody i wydatki poza nieregularną (zamiast „Paced, not One-off”).
  - ADR-0050: `one_off_over_limit` zastępuje sygnał ponad plan równomiernej rocznej wobec roku (tabela rytm × odczyt).
  - ADR-0062: automatyczne podniesienie i przycisk nie dotyczą nieregularnej.
  - ADR-0063: rytm jest atrybutem kategorii listy z historią, a nie flagą na Budget Line. Flagi na Budget Line zostają do czasu, aż S-24 przeniesie czytelników (`.ai/rules/migrations.md:17`).
- **Odrzucone opcje:**
  - rytm na Budget Line (brak granulacji miesiąca i edycja per rok, a nie w „Kategoriach”),
  - rytm zapisany na pozycji planu miesiąca (propozycje nie są zapisywane, minione miesiące bez planu go nie mają),
  - zmiana rytmu od następnego miesiąca (oznaczenie w listopadzie działałoby od grudnia),
  - stawka zawsze z bieżącego planu rocznego ÷ 12 (zmiana planu przelicza minione miesiące i tworzy zebrane bez pieniędzy) albo stała do stycznia (podniesienie planu nie działa),
  - stawka z (Current Plan − stawki wcześniejszych miesięcy roku) ÷ pozostałe miesiące (odchodzi od „plan roczny ÷ 12” z PRD, wymaga drugiego łańcucha i reguły dla planu obniżonego poniżej zebranych stawek),
  - zebrane jako zapisana liczba albo zachowane po zdjęciu „nieregularnej” (każda korekta wymagałaby przeliczenia zapisu),
  - zebrane bez progu w każdym miesiącu (nadwyżka liczona dwa razy),
  - wszystkie wydatki → równomierna roczna (tempo znika) oraz jednorazowa → bieżąca (wraca kwota w propozycji, wchodzi do tempa),
  - nieregularna domyślnie przy migracji (PRD),
  - „odkładam” dla stawki (dwa różne „odkładam” w planie i w Podsumowaniu),
  - propozycja stałej opłaty jako kopia planu (identyczna z bieżącą),
  - fundusz z przelewami na osobne konto (PRD v1, ADR-0033, PRD v3: pieniądze zostają na koncie głównym).

#### 2. Odsyłacze w zmienianych ADR-ach

**Files**: `context/foundation/adr/0024-month-remaining-is-not-available-limit.md`, `0030-year-close-posts-year-result-into-reserve.md`, `0033-month-plan-beside-annual-budget.md`, `0043-dashboard-layout-follows-atrament.md`, `0050-dashboard-notice-labels-and-budget-block.md`, `0062-month-plan-change-mode-raises-plan-to-actual.md`, `0063-one-category-list-across-years.md`

**Intent**: Agent czytający sam stary ADR nie może uznać flag Paced / One-off ani Month Item Kind za obowiązujące.

**Contract**: Jedna nowa linia na końcu każdego pliku, po pustej linii (inaczej dokleja się do kończącej plik listy w ADR-0062 i ADR-0063): „Amended by ADR-0066: …” z nazwą zmienionej klauzuli (jak w Contract powyżej). Istniejące akapity bez zmian.

### Success Criteria:

#### Automated Verification:

- ADR istnieje i numer 0066 jest jedyny: `ls context/foundation/adr | grep -c '^0066-'` zwraca `1`
- ADR-0066 powołuje się na każdy zmieniany ADR: `for n in 0024 0030 0033 0043 0050 0062 0063; do grep -q "ADR-$n" context/foundation/adr/0066-*.md || echo "missing $n"; done` nic nie wypisuje
- Każdy zmieniany ADR odsyła do ADR-0066, a jego dotychczasowe akapity są nietknięte (po commicie fazy; `221e00a` to baza zmiany): `for n in 0024 0030 0033 0043 0050 0062 0063; do grep -q 'ADR-0066' context/foundation/adr/$n-*.md || echo "no link $n"; git diff --numstat 221e00a HEAD -- context/foundation/adr/$n-*.md | awk -v n=$n '$2!=0 {print "edited " n}'; done` nic nie wypisuje

#### Manual Verification:

- ADR-0066 zawiera każdy punkt z Contract (rytmy i tabela, historia rytmu, stawka, zebrane, 31 grudnia, stała opłata, migracja i snapshot, nowe brzmienie siedmiu klauzul, odrzucone opcje) i nie przeczy PRD v3 (decyzje strukturalne 2, 3, 5–9, FR-007, FR-009–FR-013, FR-022) ani ADR-0063; próg 0 w każdym miesiącu to świadome doprecyzowanie formuły z PRD, nie sprzeczność
- Przykłady przeliczone według ADR-0066 bez zaglądania do planu dają: US-02 (400 → zebrane 50, „do wydania” −150; 600 → zebrane 0, −300, przekroczenie), podniesienie planu w lipcu (zebrane na 1 lipca 150, nadwyżka maja 50, stawka lipca 200), zwrot w sierpniu (250), 31 grudnia (200, stawka stycznia 175), Telefon (172,15 / 180), Rekolekcje (198,33, grudzień 198,37)
- Czytelnik każdego z siedmiu starych ADR-ów widzi, która klauzula przestała obowiązywać

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Phase 2: Słownik

### Overview

Dodaje terminy rytmu, stawki i zebranego, przepisuje „do wydania” i opisuje flagi jako zastąpione rytmem. Usuwa ze słownika każdą sprzeczność z ADR-0066 minimalnymi dopiskami.

### Changes Required:

#### 1. Nowe i przepisane wpisy

**File**: `context/foundation/glossary.md`

**Intent**: Dać S-24, S-26 i S-27 jednoznaczne terminy i predykaty. **Rhythm** wstawić po **Category Group**; **Irregular Rate**, **Collected** i **Opening Collected** po **Set-aside**. Format jak istniejące wpisy.

**Contract**:

- **Rhythm** (nowy):
  - Atrybut wydatkowej Category z historią: wpisy (rytm, miesiąc początku); rytmem miesiąca jest wpis o największym miesiącu początku nie późniejszym niż ten miesiąc, a miesiąc przed pierwszym wpisem ma rytm pierwszego wpisu.
  - Cztery wartości, każda w jednym zdaniu z regułą „do wydania” i propozycji z tabeli ADR-0066: Fixed Charge („stała opłata”), Running („bieżąca”), Even Annual („równomierna roczna”, z „zostało X na Y mies.”), Irregular („nieregularna”).
  - Zmiana działa od bieżącego miesiąca kalendarzowego; minione miesiące bez zmian. Nowa wydatkowa Category: Running. Przychód nie ma rytmu.
  - Zdanie o migracji: mapowanie z Key Decision 1 (ADR-0066).
  - Polish UI copy: „rytm” i cztery nazwy.
  - _Avoid_: Paced, One-off, Month Item Kind, Bill (for Fixed Charge), frequency, schedule, cadence.
- **Irregular Rate** (nowy): kwota nieregularnej pozycji w Month Plan danego miesiąca; propozycja floor(Current Plan / 12) z resztą w grudniu; zatwierdzona i bieżąca jak każda kwota planu; bez zatwierdzonego planu kwota propozycji; 0 w miesiącu bez budżetu w wykonaniu. Polish UI copy: „stawka”. _Avoid_: Set-aside, „odkładam”, Fund, Savings, Accrual, Envelope.
- **Collected** (nowy): wzór miesiąc po miesiącu z progiem 0 i nadwyżka (dosłownie jak w ADR-0066); odczyt, nie zapis; przechodzi przez 31 grudnia; kończy się po zdjęciu „nieregularnej”; nie zmienia Liquidity, Execution ani Month Result. Polish UI copy: „zebrane”. _Avoid_: Fund, Sinking fund, Savings, Reserve, Earmark, Set-aside, balance, Account.
- **Opening Collected** (nowy): kwota wpisana przy oznaczeniu nieregularnej (≥ 0, domyślnie 0), zebrane na początek miesiąca oznaczenia; poprawialna na wpisie bez zmiany miesiąca oznaczenia; „zebrane startowe” nowego budżetu w UI to odczyt Collected na 31 grudnia, nie ta kwota. Polish UI copy: „zebrane startowe”.
- **To Spend** (przepisany): przychody (plan, po pierwszym wpływie kwota faktyczna) − pozycje według rytmu (tabela) − Set-aside. Ponad plan według rytmu. Zdania o ostrzeżeniu przy operacji, Own Transfer, Capital Movement, powiadomieniu `exceeded` i ADR-0062 zostają. Bez słów „Bill items” i „Running items”.
- **Paced Category**, **One-off Category**, **Month Item Kind** (przepisane krótko): flaga na Budget Line zastąpiona przez Rhythm (ADR-0066), z mapowaniem, czytana do czasu, aż S-24 przeniesie czytelników. Polish UI copy i _Avoid_ zostają.

#### 2. Dopiski w istniejących wpisach

**File**: `context/foundation/glossary.md`

**Intent**: Usunąć sprzeczności z ADR-0066 bez przeredagowania wpisów.

**Contract**:

- **Budget Line:** „until rhythm (F-05) changes it, the Paced / One-off flag and the Month Item Kind” → legacy flags replaced by the Rhythm of the Category (ADR-0066).
- **Category:** zdanie o Paced / One-off i Month Item Kind → wydatkowa Category ma Rhythm (ADR-0066). Pula Month Remaining → Fixed Charge i Running.
- **Available Limit**, **Earmark:** „(Paced or One-off)” → dowolna wydatkowa Category.
- **Month Remaining:** pula = wydatkowe Category o rytmie Fixed Charge albo Running w tym miesiącu; Even Annual i Irregular nie mają Month Remaining.
- **Year Elapsed:** znacznik dla przychodów i wydatków poza Irregular.
- **Year after the Month:** reguła według rytmu; Irregular: wykonanie przed miesiącem + kwota faktyczna miesiąca.
- **Month Pace:** pula = pozycje Running.
- **Month Summary:** odchylenie wiersza Irregular = nadwyżka, odchylenie sum bez zmian (proste sumy); wiersz pokazuje stawkę i Collected; „an unpaid Bill” → „an unpaid Fixed Charge”.
- **Month Result:** „including archived and One-off” → wszystkie Category, w tym zarchiwizowane, każdego rytmu; nieregularna liczy faktyczny rachunek.
- **Current Month Plan:** podniesienie do kwoty faktycznej i przycisk nie dotyczą Irregular (ADR-0066).
- **Set-aside:** _Avoid_ uzupełnić o Irregular Rate i Collected.
- **Month Plan:** propozycja według rytmu (ADR-0033, ADR-0066).

### Success Criteria:

#### Automated Verification:

- Nowe terminy mają wpis: `for t in 'Rhythm' 'Irregular Rate' 'Collected' 'Opening Collected'; do grep -q "^\*\*$t\*\*:" context/foundation/glossary.md || echo "missing $t"; done` nic nie wypisuje
- Predykaty są zapisane dosłownie w obu dokumentach: `for p in 'max(0, Collected at the start of the month' 'the latest start month not after' 'from the current calendar month' 'last paid amount'; do grep -qF "$p" context/foundation/glossary.md && grep -qF "$p" context/foundation/adr/0066-*.md || echo "missing $p"; done` nic nie wypisuje
- Stare sformułowania zniknęły ze słownika: `grep -c 'until rhythm\|Bill items\|Running items\|Paced or One-off\|including archived and One-off' context/foundation/glossary.md` zwraca `0`
- Polskie copy jest w słowniku: `for c in '„stawka”' '„zebrane”' '„zebrane startowe”' '„stała opłata”' '„równomierna roczna”' '„nieregularna”'; do grep -qF "$c" context/foundation/glossary.md || echo "missing $c"; done` nic nie wypisuje
- Diff obejmuje tylko dokumenty tej zmiany (plus artefakty zmiany i roadmapę): po commicie fazy `git diff --name-only 221e00a HEAD -- . ':!context/changes' ':!context/foundation/roadmap.md'` zwraca wyłącznie `glossary.md`, `0066-…` i siedem zmienianych ADR-ów

#### Manual Verification:

- US-02 przeliczony według wpisów To Spend, Irregular Rate i Collected daje: rachunek 400 → stawka 150, zebrane 50, „do wydania” −150; rachunek 600 → zebrane 0, −300, przekroczenie; Wynik lipca liczy 400 albo 600
- Przypadek z podniesieniem planu w lipcu i przypadek 31 grudnia dają te same liczby co w Fazie 1 (150 i nadwyżka 50; 200 i stawka 175)
- Przypadki rytmu: Prąd oznaczony 9 listopada → październik bez zmian, listopad nieregularny; zdjęcie w marcu 2027 → od marca brak zebranego; nowa kategoria wydatku → bieżąca; Rekolekcje po migracji → równomierna roczna, ponad plan dopiero przy wykonaniu roku > 2 380
- Żaden z poprawionych wpisów nie przeczy ADR-0066; Set-aside, Reserve i Earmark nie mieszają się z zebranym
- Słownik nie urósł poza cztery nowe wpisy, przepisane To Spend, Paced Category, One-off Category, Month Item Kind i dopiski w wymienionych wpisach

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Brak, bo to zmiana w dokumentach. Przykłady z Key Decisions są gotowymi przypadkami dla par kalkulatorów PHP/TS w S-24 i S-26: US-02 (400, 600), podniesienie planu w lipcu (próg 0 w maju), zwrot w sierpniu, 31 grudnia (200 → 175), Telefon (172,15 / 180), Rekolekcje (198,33 / 198,37).

### Integration Tests:

- Brak. Test migracji flag na historię rytmu na PostgreSQL i restore snapshotu v6 z mapowaniem należą do S-24.

### Manual Testing Steps:

1. Przeczytać ADR-0066 obok PRD v3 (decyzje strukturalne 2, 3, 5–9, FR-007, FR-009–FR-013, FR-022): każda decyzja ma odpowiednik albo świadome pominięcie z „What We're NOT Doing”.
2. Przeliczyć przykłady z Fazy 1 według słownika, bez zaglądania do planu.
3. Sprawdzić przypadki graniczne:
   - nieregularna z zebranym startowym 0 i rachunkiem w miesiącu oznaczenia → nadwyżka = rachunek − stawka,
   - budżet 2027 jeszcze szkicem 15 stycznia 2027 → stawka stycznia 0, zebrane z 31 grudnia stoi,
   - zarchiwizowana nieregularna → stawka 0, zebrane stoi; przywrócenie wznawia stawki,
   - stała opłata z dwiema płatnościami w poprzednim miesiącu → propozycja = ich suma netto.

## Performance Considerations

Nie dotyczy tej zmiany. Zebrane jest odczytem łańcuchowym od miesiąca oznaczenia, więc S-26 musi je liczyć jednym zapytaniem po miesiącach, nie w pętli po miesiącach (`.ai/rules/sloppy.md`).

## Migration Notes

Nie dotyczy tej zmiany, bo dane się nie zmieniają. Wycofanie to revert commitów dokumentacyjnych. Mapowanie flag na rytm i historia rytmu są treścią ADR-0066, a wdroży je S-24 etapami z `.ai/rules/migrations.md:17`.

## References

- Roadmapa: `context/foundation/roadmap.md` (F-05, S-24, S-26, S-27, Otwarte pytania 1 i 2)
- PRD: `context/foundation/prd-v3.md` (Decyzje strukturalne 2, 3, 5–9; FR-007, FR-009–FR-013, FR-022; US-02; Business Logic Changes; Otwarte pytania 1 i 2)
- Notatki shape: `context/foundation/shape-notes.md:40-66,212-230,382-395`
- ADR-y: `0024`, `0033`, `0062`, `0063`, `0065`; wzór gęstości `0063`; precedens odsyłacza `0024` (ostatnia linia)
- Plan F-03 jako wzór zmiany dokumentacyjnej: `context/archive/2026-11-09-category-list-domain-decisions/plan.md`
- Reguły: `.ai/rules/migrations.md:17`, `.ai/rules/categories.md:35`, `.ai/rules/month-calculators.md`
- Kod: `app/Support/ToSpendCalculator.php:76-80`, `app/Support/MonthPlanProposal.php:155-213`, `app/Support/MonthItemDeviation.php:27-53`, `app/Support/MonthPaceCalculator.php:26-29`, `app/Support/DashboardNotices.php:121-205`, `resources/js/lib/month-remaining-calculator.ts:99,139-149`, `resources/js/lib/dashboard-categories.ts:49-58`, `app/Http/Controllers/MonthPlanController.php:271-311`, `app/Models/BudgetLine.php:25-38`, `database/migrations/2026_11_02_091919_create_month_plans_table.php:18`, `app/Actions/BackupUserData.php:19,176-177`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0066 i odsyłacze

#### Automated

- [x] 1.1 ADR istnieje i numer 0066 jest jedyny
- [x] 1.2 ADR-0066 powołuje się na każdy zmieniany ADR
- [x] 1.3 Każdy zmieniany ADR odsyła do ADR-0066, a jego dotychczasowe akapity są nietknięte

#### Manual

- [x] 1.4 ADR-0066 zawiera każdy punkt z Contract i nie przeczy PRD v3 ani ADR-0063
- [x] 1.5 Przykłady przeliczone według ADR-0066 dają oczekiwane liczby
- [x] 1.6 Czytelnik każdego z siedmiu starych ADR-ów widzi, która klauzula przestała obowiązywać

### Phase 2: Słownik

#### Automated

- [ ] 2.1 Nowe terminy mają wpis
- [ ] 2.2 Predykaty są zapisane dosłownie w obu dokumentach
- [ ] 2.3 Stare sformułowania zniknęły ze słownika
- [ ] 2.4 Polskie copy jest w słowniku
- [ ] 2.5 Diff obejmuje tylko dokumenty tej zmiany

#### Manual

- [ ] 2.6 US-02 przeliczony według wpisów słownika daje oczekiwane liczby
- [ ] 2.7 Przypadki podniesienia planu i 31 grudnia dają te same liczby co w Fazie 1
- [ ] 2.8 Przypadki rytmu dają oczekiwany wynik
- [ ] 2.9 Żaden z poprawionych wpisów nie przeczy ADR-0066
- [ ] 2.10 Słownik nie urósł poza zakres zmiany
