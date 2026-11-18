# Próba przełomu roku 2026→2027 — raport

Bez danych osobowych, nazw kontrahentów i kwot pojedynczych operacji. Dopuszczalne są liczby wierszy, liczby kategorii i zgodność „tak/nie”.

## Przebieg

Faza 3 uzupełnia: data próby, commit, źródło snapshotu (data UTC, wersja), użytkownik docelowy.

## Przygotowanie

Faza 3 uzupełnia kroki 1–5 (snapshot z VPS, kopia przez `ssh … cat`, `user:create`, `user:restore`, plik CSV z datami 29.12–02.01).

## Tabela kroków

| Krok | Zegar serwera (UTC) | Zegar przeglądarki | Co sprawdzić | Oczekiwany wynik | Wynik |
| --- | --- | --- | --- | --- | --- |
| T0 | 2026-12-30 10:00 | realny | Plan grudnia zatwierdzony | tak | do uzupełnienia w fazie 3 |
| T1 | 2026-12-30 10:00 | realny | Start 2027; Dashboard 2026; Raporty „Lata” bez 2027; liczby kontrolne tylko w pozycjach 2027 | tak | do uzupełnienia w fazie 3 |
| T2 | 2026-12-31 23:30 | 2027-01-01 00:30 PL | Menu roku vs. serwer; styczeń do zatwierdzenia (nie zatwierdzać) | tak | do uzupełnienia w fazie 3 |
| T3 | 2027-01-01 00:30 | 2027-01-01 01:30 PL | Dashboard 2027, Zebrane startowe, Propozycja stycznia, rok do roku, „Lata”, rok w sesji | tak | do uzupełnienia w fazie 3 |
| T4 | 2027-01-02 09:00 | realny | Import 29.12–02.01 i zatwierdzenie stycznia | tak | do uzupełnienia w fazie 3 |
| T5 | 2027-01-02 09:00 | realny | Zapomniany start (świeże przywrócenie) | tak | do uzupełnienia w fazie 3 |

## Znaleziska

Faza 1 (testy Feature na PostgreSQL, `YearTurnTest`, dwa przypadki w `CsvImportTest`, dwa w `SelectedBudgetYearTest`):

- Pierwsze uruchomienie: 4 testy `YearTurnTest` nie przeszły z `ValidationException` „Pozycje planu miesiąca są nieprawidłowe”. Przyczyna leżała w pomocniku testu (operator `...` przenumerowuje klucze całkowite, więc identyfikatory kategorii zgubiły się w tablicy kwot), a nie w produkcie. Decyzja: poprawiony pomocnik (`array_replace`), brak znaleziska produktu.
- Po poprawce wszystkie przypadki przechodzą. Znaleziska blokujące przełom: brak. Przelew własny i ruch kapitału w roku-szkicu są przyjmowane.
- Obserwacja nieblokująca (kandydat, bez poprawki): Import wyciągu 29.12–02.01 przy szkicu 2027 odrzuca cały zapis (jedna transakcja), a nie tylko wiersze ze stycznia; wiersze grudnia zapisują się dopiero po odznaczeniu wierszy stycznia albo po starcie 2027. Pinuje to `CsvImportTest::test_statement_across_the_year_turn_is_refused_as_a_whole_while_the_new_year_is_a_draft`.
- Obserwacja nieblokująca (luka 3): sesja z rokiem 2026 zostaje na 2026 w styczniu, mimo że istnieje 2027. Pinuje to `SelectedBudgetYearTest::test_session_year_of_the_old_year_stays_selected_in_january_known_gap`.

Faza 3 dopisze znaleziska z próby, każde z decyzją „poprawione w tej zmianie (sha)” albo „kandydat”.

## Luki 1–5

Kandydaci na osobne zmiany, bez decyzji o naprawie. Faza 3 dopisuje potwierdzenie albo zaprzeczenie z próby.

1. Brak powiadomienia, że Budżet bieżącego roku jest jeszcze szkicem: do uzupełnienia w fazie 3.
2. Niezatwierdzony grudzień staje się niezatwierdzalny o 01:00 czasu warszawskiego, bez ostrzeżenia: test granicy w `YearTurnTest` potwierdza zachowanie serwera, do uzupełnienia w fazie 3.
3. Sesja trzyma rok 2026 w styczniu: potwierdzone testem (zob. Znaleziska), do uzupełnienia w fazie 3.
4. Niezgodność UTC i czasu lokalnego wokół północy: do uzupełnienia w fazie 3.
5. Tryb „do tego samego dnia” w „Latach” dla lat z arkusza w styczniu: do uzupełnienia w fazie 3.

## Sprzątanie

Faza 3 uzupełnia wykonanie:

- `app:rehearsal-clock --off`;
- `user:reset-data proba@example.test --force`;
- usunięcie plików z `storage/app/restore-trial/`;
- zamknięcie przeglądarki (`page.clock` wraca do realnego czasu).

Konto „Próba” zostaje puste (nie ma `user:delete`).
