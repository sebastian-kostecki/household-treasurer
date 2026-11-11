# Scalanie kategorii — Plan Brief

> Full plan: `context/changes/category-merge/plan.md`

## What & Why

S-21 (FR-023): użytkownik scala na „Kategoriach” dwie kategorie listy w jedną. Źródło znika, a jej operacje, plany i „zebrane” przechodzą na kategorię docelową, po podglądzie liczb. To narzędzie porządków po migracji listy: na produkcji czekają „Jedzenie poza domem” → „Restauracje” (48 operacji) i „Eksploatacja domu” → „Utrzymanie domu” (20 operacji), a do ich scalenia filtr Operacji, podpowiedzi Importu i propozycja stycznia nie łączą lat tych par.

## Starting Point

Kategoria listy ma pozycje budżetu per rok, a operacje i pozycje planu miesiąca wskazują pozycję budżetu; wykonanie jest zapisaną kolumną. Kategorię z historią można dziś tylko zarchiwizować. Scalania nie ma w kodzie, a ADR-0066 zostawia S-21 decyzję, co dzieje się z „zebranym”.

## Desired End State

Wiersz kategorii ma menu „⋯” z „Scal z inną…”. Okno pyta o kategorię docelową, pokazuje podgląd z serwera (budżety, liczba operacji, plan „przed → po”, plany miesięcy, „zebrane”) i dopiero wtedy pozwala potwierdzić. Po scaleniu zostaje jedna kategoria z sumą danych obu, sumy budżetów się nie zmieniają, a plik snapshotu sprzed scalenia pozwala wrócić przez `user:restore`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Dwie pozycje w tym samym budżecie | Sumują się: plan początkowy, plan aktualny, wykonanie oraz kwoty zatwierdzone i bieżące planów miesięcy | Sumy budżetu, bilans i sumy planu miesiąca nie drgną, a literówkę z tego samego roku da się scalić |
| Pozycja tylko po stronie źródła | Przepięcie na cel bez zmiany id pozycji | Operacje i pozycje planu idą za pozycją; tak wyglądają obie pary z produkcji |
| Rytm | Historia rytmu celu zostaje, źródła znika | Tak jak nazwa, ikona, grupa i opis: wygrywa kategoria docelowa |
| „Zebrane” | Cel nieregularny: nowy okres od bieżącego miesiąca z sumą „zebranego” obu na 1. dzień miesiąca (np. 450 + 400 = 850); cel nie jest nieregularny: „zebrane” źródła znika, podgląd ostrzega kwotą | Dosłownie spełnia FR-023 i daje przewidywalną liczbę |
| Archiwum | Cel musi być aktywny; źródło aktywne albo archiwalne, scalane jak „przywróć, potem scal” | Sprzątanie archiwum jednym krokiem, bez stanu, którego nie da się odkręcić w UI |
| Wejście | Menu „⋯” w wierszu kategorii, która zniknie („Scal z inną…”, „Usuń”) | Mieści się w kolumnie 7rem, powtarza wzorzec wiersza grupy, kierunek wynika z wiersza |
| Podgląd | Tabela per budżet: rok, liczba operacji, plan aktualny celu „przed → po”; linie o planach miesięcy i „zebranym” | Pokazuje to, o co prosi FR-023, i mieści się w oknie 460 px |
| Nieodwracalność | Snapshot użytkownika przed scaleniem; gdy się nie zapisze, scalenia nie ma | Prawdziwa droga powrotu z gotowego mechanizmu `user:backup` / `user:restore` |
| Testy | Akcja i HTTP na PostgreSQL, Vitest, jeden test E2E, próba obu par na kopii produkcji | Operacja nieodwracalna jest przećwiczona na prawdziwych danych przed produkcją |
| Podgląd i zapis | Jedna klasa odczytu dla podglądu i dla akcji | Okno nie może pokazać czegoś innego, niż akcja zrobi |

## Scope

**In scope:**

- ADR-0076, wpis „Category Merge” w słowniku, reguła w `.ai/rules/categories.md`
- `CategoryMergeReading`, akcja `MergeCategories`, nowy okres w `ChangeCategoryRhythm`
- Podgląd JSON i scalenie na `CategoryMergeController`, polityka, żądanie, komunikaty
- Menu „⋯” wiersza, okno scalania, blok podglądu, kitchen sinki, strażniki UI
- Test E2E, README (powrót), próba na kopii produkcji

**Out of scope:**

- Cofanie w aplikacji, dziennik scaleń, scalanie do archiwalnej, scalanie wielu naraz, podział kategorii
- Zmiana snapshotu (wersja 10 zostaje), migracje, sprzątanie plików snapshotów
- Wybór, czyj rytm albo nazwa zostają; przepinanie podglądu Importu w toku
- Cash Expectation, Earmark, Reserve (nie istnieją w kodzie)

## Architecture / Approach

`CategoryMergeReading::for(źródło, cel, dziś)` wylicza w stałej liczbie zapytań, które pozycje budżetu zostaną przepięte, a które złożone, ile operacji przejdzie i jaka będzie suma „zebranego”. `GET categories/{category}/merge-preview` zwraca `toArray()` tego odczytu, a okno (zbudowane na `ConfirmActionDialog`, z `CategorySelect` i blokiem podglądu) pobiera go przy każdej zmianie celu wzorem palety poleceń. `POST categories/{category}/merge` woła `MergeCategories`: blokada listy typu, snapshot, zapisy zbiorcze w kolejności wymuszonej przez klucze obce, nowy okres „zebranego” przez `ChangeCategoryRhythm`, usunięcie źródła, kontrola sum per budżet. Odczyty raportów, filtrów i Importu łączą lata po id kategorii, więc nie wymagają zmian.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Słownik i ADR-0076 | Reguła scalania zapisana przed kodem | Sprzeczność z klauzulami „niezmienny” w ADR-0063 i ADR-0033 |
| 2. Odczyt scalenia i akcja | `CategoryMergeReading`, `MergeCategories`, nowy okres „zebranego”, testy na PostgreSQL | Kolejność zapisów wobec kluczy obcych; „zebrane” liczone po zapisie daje inną liczbę |
| 3. Granica HTTP | Podgląd JSON, scalenie, uprawnienia, komunikaty | Kategoria docelowa innego użytkownika albo typu |
| 4. Okno scalania i menu wiersza | Menu „⋯”, okno z podglądem, kitchen sinki, strażniki | Tabela w oknie 460 px; „Usuń” przeniesione do menu psuje istniejący test E2E |
| 5. E2E, README i bramka | Test scalenia w przeglądarce, opis powrotu, pełna bramka | Współdzielona baza E2E: test musi po sobie posprzątać |
| 6. Próba na kopii produkcji | Scalenie obu par lokalnie, liczby kontrolne, sprawdzony `user:restore` | Wymaga aktualnego snapshotu produkcji |

**Prerequisites:** S-18 (lista kategorii) i S-26 („zebrane”) są na `development`; do fazy 6 potrzebny snapshot produkcji z `user:backup`.
**Estimated effort:** około 5–6 sesji w 6 fazach; fazy 2 i 4 są największe.

## Open Risks & Assumptions

- Minione miesiące kategorii docelowej przeliczą się z operacjami źródła według rytmu celu (jak po spóźnionej operacji), a wiersze źródła znikną z minionych planów. ADR-0076 zapisuje to jako zamierzone.
- Scalenie archiwalnego źródła podnosi plan widoczny w sumach budżetu bieżącego roku o plan tej pozycji; podgląd pokazuje to notatką.
- `user:restore` cofa wszystko, co wpisano po scaleniu, i nadaje rekordom nowe id.
- Nazwa pliku snapshotu ma dokładność do sekundy, więc dwa scalenia w tej samej sekundzie nadpisałyby plik; przez UI to nierealne i plan tego nie zmienia.
- Podgląd Importu otwarty w chwili scalenia może nieść id usuniętej kategorii; zapis takiego wiersza zostanie odrzucony walidacją.
- Założenie: na produkcji obie pary nadal leżą w różnych budżetach; faza 6 opisuje dozwolone różnice, gdyby to się zmieniło.

## Success Criteria (Summary)

- Użytkownik scala „Jedzenie poza domem” z „Restauracjami” z menu wiersza, widzi przed potwierdzeniem 48 operacji i plan „przed → po”, a po scaleniu ma jedną kategorię przez wszystkie lata.
- Sumy planu i wykonania każdego budżetu, liczba operacji i salda kont są takie same przed i po scaleniu.
- Po pomyłce `user:restore` z pliku zapisanego przez scalenie przywraca obie kategorie.
