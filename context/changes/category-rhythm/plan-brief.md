# Rytm kategorii w propozycji planu — Plan Brief

> Full plan: `context/changes/category-rhythm/plan.md`

## What & Why

Wydatek płacony kilka razy w roku nie pasuje do dzisiejszych dwóch flag pozycji budżetu (równomierna / jednorazowa i rachunek / bieżąca): jako bieżąca obniża „do wydania” jednym skokiem, jako jednorazowa znika z miesięcy bez płatności. ADR-0066 zastępuje flagi rytmem kategorii listy. S-24 (FR-007, FR-009, FR-022) daje trzy rytmy — stała opłata, bieżąca, równomierna roczna — nadane z flag, poprawiane w „Kategoriach”, z propozycją planu liczoną z rytmu; nieregularna to S-26.

## Starting Point

Flagi `is_paced` i `month_item_kind` są na `budget_lines` i czyta je 111 plików (pary kalkulatorów PHP/TS, propozycja, wiersze planu, powiadomienia, Budżet, dołączanie, snapshot v8). Propozycja czyta tylko `is_paced` (jednorazowa → 0) i nie czyta operacji; flagi zmienia okno Budżetu i przełącznik w planie, który przepisuje cały rok. Na dev: 29 bieżących, 2 jednorazowe (Rekolekcje 2 380 zł, Ubezpieczenie samochodu 541 zł, obie już zapłacone), zero rachunków, zatwierdzony tylko listopad 2026.

## Desired End State

W „Kategoriach” wydatek ma pole „Rytm”, a zmiana działa od bieżącego miesiąca; minione miesiące i zatwierdzone kwoty się nie zmieniają. Propozycja planu wpisuje dla stałej opłaty ostatnią zapłaconą kwotę, dla bieżącej kopię poprzedniego miesiąca, dla równomiernej rocznej plan ÷ 12 (grudzień 2026: Rekolekcje 198,37, Ubezpieczenie 45,12) z podtytułem „zostało 0,00 na 2 mies.”. Wszystkie odczyty idą z rytmu miesiąca, flag nie ma w bazie, a snapshot v9 niesie historię rytmu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Rytmy, mapowanie flag, reguły propozycji i odczytów | Tabela ADR-0066 | Rozstrzygnięte w F-05 przed kodem. | ADR-0066 |
| Miejsce zmiany rytmu | Tylko „Kategorie”; plan i Budżet pokazują etykietę, przełącznik w planie i pola flag w oknie Budżetu znikają | Jedno miejsce i jasne „od kiedy”. | Plan |
| „Zostało X na Y mies.” | Bieżący i przyszłe miesiące, w propozycji i po zatwierdzeniu, podtytuł wiersza (w bieżącym po „W roku”); X na dziś, Y od bieżącego miesiąca kalendarzowego | Odczyt zawsze przy kwocie, którą ustalasz, także w trybie zmiany. | Plan |
| Sekcja zwinięta | „Równomierne roczne bez planu i wydatków”, reguła jednorazowej przeniesiona | Listopad wygląda jak dziś. | Plan |
| Flagi i wycofanie | Usunięte w ostatniej fazie S-24; `down()` odtwarza je z rytmu (stratnie przy zmianie w połowie roku i dla jednorazowa + rachunek) | Jedna zmiana, wycofanie bez utraty operacji. | Plan |
| Zapis historii | Zastąpienie wpisów od bieżącego miesiąca (też przyszłych), ten sam rytm nic nie dodaje; typ → przychód usuwa wpisy, → wydatek wpis od bieżącego miesiąca | Dosłownie ADR-0066 plus brakująca reguła zmiany typu. | Plan |
| Który miesiąc czyta odczyt | Miesiąc, którego dotyczy (plan — oglądany, Dashboard — bieżący, Budżet — oglądany, wpis — data operacji) | Rytm jest na miesiąc, nie na rok. | Plan |
| Snapshot | v9 z historią; v1–v8 przez to samo mapowanie (poprawka „1–6” w ADR-0066) | v6–v8 to też formaty bez rytmu. | Research |
| Powiadomienie roku | `one_off_over_limit` → `even_annual_over_limit`; równomierna roczna bez `month_item_over_plan` | Ponad plan równomiernej rocznej liczy się wobec roku. | ADR-0066 / Plan |

## Scope

**In scope:**
- Tabela `category_rhythms`, enum `Rhythm` (3 wartości), zamrożone mapowanie flag, migracja wypełniająca, odczyt jednym zapytaniem
- Pole „Rytm” w „Kategoriach”, reguły zapisu, snapshot v9 i restore v1–v8
- Propozycja z rytmu, etykieta rytmu i „zostało X na Y mies.” w planie, przemianowana sekcja
- Przełączenie wszystkich czytelników flag (pary PHP/TS) i usunięcie flag z odwracalnym `down()`
- ADR-0070, słownik i reguły, próba na kopii produkcji

**Out of scope:**
- Nieregularna, stawka, „zebrane”, zebrane startowe (S-26), 31 grudnia (S-27), grupy w planie (S-25), scalanie (S-21)
- Zmiana rytmu w planie miesiąca lub Budżecie, widok historii rytmu
- „Zostało X na Y mies.” poza planem miesiąca

## Architecture / Approach

Expand → backfill → przełączanie → drop. `LegacyRhythmConversion` (zamrożona, tablice) zamienia flagi pozycji budżetów na wpisy (rytm, rok, miesiąc) — dla migracji i restore v1–v8. `CategoryRhythms` odczytuje rytm kategorii w miesiącu dla zbioru kategorii jednym zapytaniem; `ChangeCategoryRhythm` jest jedyną drogą zapisu. Czytelnicy przechodzą obszarami: propozycja i wiersz planu → odczyty miesiąca → odczyty roku i Budżet; do fazy 6 flagi nadal są pisane, więc każda faza ma zielone testy.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR, historia rytmu i backfill | Tabela, mapowanie, migracja na PostgreSQL, odczyt, liczby kontrolne | Mapowanie na produkcji (jednorazowa + rachunek) |
| 2. Rytm w „Kategoriach” i snapshot v9 | Pole „Rytm”, reguły zapisu, v9, restore v1–v8 | Parzystość restore z migracją |
| 3. Propozycja z rytmu i wiersz planu | Ostatnia zapłacona kwota, plan ÷ 12, „zostało X na Y mies.”, bez przełącznika | Limit zapytań propozycji |
| 4. Odczyty miesiąca z rytmu | „Do wydania”, odchylenie, tempo, „W roku”, ponad plan, ostrzeżenia | Pary PHP/TS rozjechane |
| 5. Odczyty roku, „Pozostało w miesiącu” i Budżet | Powiadomienie roku, kreska, pula, okno Budżetu bez flag | Payloady na miesiąc zamiast na rok |
| 6. Usunięcie flag, dokumenty, próba | Drop z odwracalnym `down()`, słownik, próba na kopii produkcji | Stratne odtworzenie flag przy wycofaniu |

**Prerequisites:** F-05 i S-18 (done); kopia danych produkcyjnych do próby w fazie 6; stos Sail z PostgreSQL dla testów worktree.
**Estimated effort:** ~6 sesji, jedna na fazę; fazy 4 i 5 największe (~40 plików testów).

## Open Risks & Assumptions

- Liczba pozycji jednorazowa + rachunek na produkcji jest nieznana; po S-24 stają się stałą opłatą (wchodzą do puli „Pozostało w miesiącu”, tracą powiadomienie roku). Faza 1 dodaje ją do liczb kontrolnych, faza 6 zapisuje ją z próby.
- Równomierna roczna, już zapłacona w całości, nadal proponuje plan ÷ 12 (grudzień: 198,37 i 45,12) i obniża „do wydania” niezatwierdzonego miesiąca, dopóki użytkownik nie zmniejszy kwoty albo (S-26) nie oznaczy jej jako nieregularnej.
- Do fazy 6 `budget_lines` ma flagi i rytm jednocześnie; nowe pozycje dostają domyślne flagi, które nikt już nie czyta.
- `down()` usuwające flagi jest stratne; to świadoma decyzja (ADR-0070).

## Success Criteria (Summary)

- Po wdrożeniu „do wydania” każdego zatwierdzonego miesiąca, kwoty Budżetu i liczby kontrolne są takie same co do grosza.
- Użytkownik zmienia rytm w „Kategoriach” i widzi skutek w propozycji od bieżącego miesiąca, bez zmian w minionych.
- Grep po flagach w `app/` i `resources/js/` trafia tylko we wczytywanie starych snapshotów, zamrożone mapowanie i liczby kontrolne, a wycofanie migracji przywraca flagi bez utraty operacji.
