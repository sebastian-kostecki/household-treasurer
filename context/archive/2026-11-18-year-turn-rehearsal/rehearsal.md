# Próba przełomu roku 2026→2027 — raport

Bez danych osobowych, nazw kontrahentów i kwot pojedynczych operacji. Dopuszczalne są liczby wierszy, liczby kategorii i zgodność „tak/nie”.

## Przebieg

- Data próby: 2026-11-18 (zegar serwera i przeglądarki ustawiany na kolejne chwile przełomu).
- Commit kodu: `1978e17` (gałąź `development`, fazy 1–2 zmiany).
- Źródło snapshotu: `user:backup` użytkownika produkcyjnego, 2026-11-18 11:08 UTC, wersja 13, suma SHA-256 zgodna po obu stronach.
- Użytkownik docelowy: konto „Próba” (lokalna baza dev, osobny użytkownik). W kopii 2026 był w wykonaniu, a 2027 szkicem (z 43 pozycjami).
- Kolejność (B): zatwierdź grudzień, uruchom 2027, przełom, Import grudnia do końca, zatwierdź styczeń.
- Przeglądarka: `playwright-cli`, `page.clock.setFixedTime` na ten sam moment co zegar serwera (strefa przeglądarki: Europa/Warszawa).

## Przygotowanie

1. Na VPS: `scripts/artisan.sh user:backup <id>` (jeden użytkownik w instancji).
2. Kopia przez `docker compose exec -T app cat <plik>` po `ssh`, do `storage/app/restore-trial/prod-user.json`; porównanie SHA-256.
3. Lokalnie `docker compose up -d`, `migrate --force` (baza dev miała jedną zaległą migrację `balance_reconciliations`, której wymaga snapshot v13).
4. `user:create "Próba" proba@example.test --password=…` i `user:restore … --force`.
5. Plik CSV do T4 i T5: kopia świeżego eksportu mBank (6 wierszy) z datami przesuniętymi na 29.12.2026–02.01.2027, z rachunku, który ma odpowiednik w przywróconych danych.

## Tabela kroków

| Krok | Zegar serwera (UTC) | Zegar przeglądarki | Co sprawdzić | Oczekiwany wynik | Wynik |
| --- | --- | --- | --- | --- | --- |
| T0 | 2026-12-30 10:00 | 2026-12-30 10:00 UTC | Plan grudnia zatwierdzony | tak | OK: grudzień „Niezatwierdzony” w kopii, po „Zatwierdź” „Zatwierdzony”. Dashboard pokazuje „30 grudnia 2026 · budżet 2026 w realizacji” (zegar próby działa). |
| T1 | 2026-12-30 10:00 | 2026-12-30 10:00 UTC | Start 2027; Dashboard 2026; Raporty „Lata” bez 2027; liczby kontrolne tylko w pozycjach 2027 | tak | OK: Dashboard nadal 2026; „Lata 2019–2026”, każdy rok liczony „do 30.12”; `--compare` pokazuje 44 różnice: 43 × `lines.<id>.initial_plan` (wszystkie pozycje Budżetu 2027) i 1 × `budgets.<id>.initial_plan` (Budżet 2027). Poza 2027 bez różnic. |
| T2 | 2026-12-31 23:30 | 2027-01-01 00:30 PL | Menu roku vs. serwer; styczeń do zatwierdzenia (nie zatwierdzać) | tak | OK, potwierdza lukę 4: menu roku oznacza 2027 „bieżący”, a serwer pokazuje „31 grudnia 2026”. Domyślna data w dialogu operacji: 2026-12-31 (UTC). Skok zegara >2 h wylogował (ponowne logowanie). Plan stycznia 2027 ma aktywne „Zatwierdź” już 31.12 (pułapka B), niezatwierdzony. |
| T3 | 2027-01-01 00:30 | 2027-01-01 01:30 PL | Dashboard 2027, Zebrane startowe, Propozycja stycznia, rok do roku, „Lata”, rok w sesji | tak | OK z uwagami: Dashboard „1 stycznia 2027 · budżet 2027 w realizacji” z blokiem „Budżet 2027”. Budżet 2027: „zebrane startowe” jest, „prognoza” nie ma. „Lata 2019–2027”, każdy rok „do 1.01”. Rok w sesji 2026 zostaje 2026 (luka 3) przy Dashboardzie na 2027. Propozycja stycznia: 30 z 46 pozycji grudnia ma tę samą kwotę (bez rytmu, bieżące `running` i opłaty stałe `fixed_charge`), a 16 z `even_annual` i `irregular` bierze inną kwotę (roczny udział planu 2027). |
| T4 | 2027-01-02 09:00 | 2027-01-02 09:00 UTC | Import 29.12–02.01 i zatwierdzenie stycznia | tak | OK: 6 wierszy poprawnych, zapisano 6 operacji: 4 w pozycjach Budżetu 2026 i 2 w pozycjach Budżetu 2027. Plan stycznia zatwierdzony po Imporcie. Skok zegara >2 h wylogował. |
| T5 | 2027-01-02 09:00 | 2027-01-02 09:00 UTC | Zapomniany start (świeże przywrócenie) | tak | OK: po `user:reset-data` i `user:restore` Dashboard pokazuje odznakę „Szkic”, „budżet 2027 w szkicu” i kartę „Budżet jest w szkicu”. Grudzień 2026: brak „Zatwierdź”, komunikat „Miniony miesiąc bez zatwierdzonego planu nie ma propozycji ani liczby do wydania”. Plan miesiąca 2027: „dostępny po rozpoczęciu realizacji”. Import tego samego pliku: 2 wiersze z 2027 mają „Brak kategorii (wydatkowej) dla roku operacji”, a „Zatwierdź import” jest wyłączony (odmowa już w podglądzie). Po starcie 2027 Import przechodzi: 4 + 2 operacje, w pozycjach właściwych lat. Styczeń niezatwierdzony, 26 z 50 kwot Propozycji różni się od wersji z kopii grudnia (roczny udział). |

## Znaleziska

Faza 1 (testy Feature na PostgreSQL, `YearTurnTest`, dwa przypadki w `CsvImportTest`, dwa w `SelectedBudgetYearTest`):

- Pierwsze uruchomienie: 4 testy `YearTurnTest` nie przeszły z `ValidationException` „Pozycje planu miesiąca są nieprawidłowe”. Przyczyna leżała w pomocniku testu (operator `...` przenumerowuje klucze całkowite, więc identyfikatory kategorii zgubiły się w tablicy kwot), a nie w produkcie. Decyzja: poprawiony pomocnik (`array_replace`), brak znaleziska produktu.
- Po poprawce wszystkie przypadki przechodzą. Przelew własny i ruch kapitału w roku-szkicu są przyjmowane.
- Obserwacja nieblokująca (kandydat, bez poprawki): Import wyciągu 29.12–02.01 przy szkicu 2027 odrzuca cały zapis (jedna transakcja), a nie tylko wiersze ze stycznia; wiersze grudnia zapisują się dopiero po odznaczeniu wierszy stycznia albo po starcie 2027. Pinuje to `CsvImportTest::test_statement_across_the_year_turn_is_refused_as_a_whole_while_the_new_year_is_a_draft_known_gap`.
- Obserwacja nieblokująca (luka 3): sesja z rokiem 2026 zostaje na 2026 w styczniu, mimo że istnieje 2027. Pinuje to `SelectedBudgetYearTest::test_session_year_of_the_old_year_stays_selected_in_january_known_gap`.

Faza 3 (próba na kopii danych):

- Znaleziska blokujące przełom: brak. Kolejność (B) przeszła bez poprawek produktu.
- Kandydat: w interfejsie Import odmawia już w podglądzie (wiersze roku-szkicu bez kategorii, wyłączony „Zatwierdź import”), a nie dopiero przy zapisie. Komunikat „Brak kategorii dla roku operacji” nie mówi, że trzeba rozpocząć realizację Budżetu tego roku.
- Kandydat: po T1 aż do przełomu menu roku i Dashboard nie mówią, że Budżet następnego roku został już uruchomiony i czeka na rok kalendarzowy. Po przełomie menu pokazuje „bieżący” przed serwerem (luka 4).
- Zgodne z regułą rytmu, nie kandydat: Propozycja stycznia różni się od grudnia dla `even_annual` i `irregular`, bo te rytmy w każdym miesiącu biorą roczny udział planu (`MonthPlanProposal.php:232`, ADR-0066, ADR-0072). Sformułowanie planu i researchu „kopiuje grudzień po `category_id`” było za szerokie; README opisuje „kopiuje grudzień” tylko dla pozycji bez rytmu, bieżących i opłat stałych. (Przegląd implementacji, F6.)
- Pominięte w T5: stawka Zebranego za styczeń (przed i po starcie 2027) nie była oglądana w przeglądarce; plan miesiąca 2027 w szkicu jest niedostępny. Zachowanie przypinają `IrregularCollectedTest::…:136` i `YearTurnTest` (zapomniany start: Zebrane na początek lutego rośnie o stawkę stycznia po starcie).
- Pominięte w T3: raport rok do roku (1 stycznia 2027 wobec 1 stycznia 2026) nie ma osobnego wyniku; sprawdzony był tylko widok „Lata” w trybie „do dnia” (luka 5).
- Liczby kontrolne obejmują całą instancję lokalną. W próbie były tylko dane konta „Próba” i dane dev, a różnice dotyczyły wyłącznie pozycji 2027.

## Luki 1–5

Kandydaci na osobne zmiany, bez decyzji o naprawie.

1. **Szkic bieżącego roku bez ostrzeżenia: zaprzeczona w części.** Dashboard ma odznakę „Szkic”, linię „budżet 2027 w szkicu” i kartę „Budżet jest w szkicu” (T5). Brakuje jedynie osobnego powiadomienia w „Wymaga uwagi” i wskazania, że Import przestanie działać.
2. **Niezatwierdzony grudzień o 01:00 PL: potwierdzona.** Test granicy 23:59:59/00:00:00 UTC; w T5 grudzień nie ma „Zatwierdź” i komunikatu o braku propozycji.
3. **Sesja trzyma rok 2026 w styczniu: potwierdzona (T3).** Po logowaniu sesja miała 2026, a Dashboard już 2027.
4. **UTC a czas lokalny wokół północy: potwierdzona (T2).** Menu roku mówi 2027 od 00:00 PL, serwer i domyślna data dialogu jeszcze 2026-12-31.
5. **Tryb „do tego samego dnia” w „Latach”: potwierdzona (T1, T3).** Każdy rok jest liczony „do 30.12” (T1) i „do 1.01” (T3); 1 stycznia obejmuje tylko jeden dzień poprzednich lat.

## Sprzątanie

Wykonane po T5:

- `app:rehearsal-clock --off` (komenda raportuje „zegar próby wyłączony”);
- `user:reset-data proba@example.test --force` (0 Budżetów i 0 operacji);
- `storage/app/restore-trial/` pusty, plik `rehearsal-clock` usunięty;
- przeglądarka zamknięta (`page.clock` wraca do realnego czasu), katalog `.playwright-cli` usunięty.

Konto „Próba” zostaje puste (nie ma `user:delete`). Na VPS został plik snapshotu w `storage/app/private/user-snapshots/` (zrobiony przez `user:backup`, pełne dane użytkownika): do usunięcia ręcznie albo do świadomego zostawienia.
