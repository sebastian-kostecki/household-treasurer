# Wczytanie historycznych budżetów 2022–2025 z arkuszy xlsx — Plan Brief

> Full plan: `context/changes/legacy-budget-import/plan.md`
> Research: `context/changes/legacy-budget-import/research.md`

## What & Why

Lata 2022–2025 istnieją tylko w starym arkuszu Excela, więc raporty rok do roku i „ostatnie 12 miesięcy” na produkcji nie mają z czym porównać 2026. Zmiana wczytuje te cztery lata jednorazowo jako zwykłe Budżety w execution ze zwykłymi operacjami, nie zmieniając żadnej zapisanej wartości 2026/2027. Odwraca to decyzję PRD v3 („dane 2025 tylko jako mock na devie”).

## Starting Point

Arkusz to sumy miesięczne bez dat; 2022 ma inny układ niż 2023–2025, a każdy rok sumuje się co do grosza do swojego arkusza „Analiza”. Żadna ścieżka zapisu się nie nadaje: konta otwierają się 2025-12-31, a wpis i Import przesuwają salda. Wzorem jest dev-only `PreviousYearMockSeeder` (bezpośredni insert, Execution = suma).

## Desired End State

Na produkcji są Budżety 2022–2025 z operacjami na zarchiwizowanych kontach „Arkusz RRRR” (saldo bieżące 0). Raporty 2026 pokazują kolumnę roku poprzedniego, a raport 12 miesięcy ma pełne pokrycie. Salda, płynność, dashboard, strona kont, szkic 2027 i ostrzeżenie o deficycie są bez zmian. Numery kontrolne w trybie „tylko dodano” to potwierdzają, a każdy rok cofa jedna komenda.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres lat | 2022, 2023, 2024, 2025; parser po etykietach | Pliki dostarczone, układy przeczytane; różnice mieszczą się w regułach. | Plan |
| Konto | Konto arkusza na rok z `accounts.sheet_year`, poza płynnością, ukryte na dashboardzie i stronie kont | Bez flagi płynność szkicu 2027 wzrosłaby o 15 636,36 zł. | Plan |
| Które wiersze | „Analiza jest prawdą”: bez wpłat i wypłat oszczędności; odkładania `**` jako wydatki; braki jako „reszta bez rozpisania” | Dowód co do grosza dla każdego roku. | Plan |
| Codzienne wydatki | Artykuły spożywcze (8); 2022 według rozpisania | Największa pozycja zostaje porównywalna z 2026. | Plan |
| Mapowanie | Tabela w `database/sheet-load/mapping.php`, po id z oczekiwaną nazwą | Lata łączy id kategorii (ADR-0063); próba sprawdza nazwy na produkcji. | Research / Plan |
| Kosmetyki | Nowa zarchiwizowana kategoria „Kosmetyki” (z Fryzjerem), Rytm Running od 2022-01, bez dołączania do szkiców | Prośba użytkownika; szkic 2027 bez zmian, a restore wymaga Rytmu. | Plan |
| Plan linii | Initial = Current = Execution = suma operacji | Tak zapisuje arkusz (C = D), bez fałszywych przekroczeń. | Plan |
| Data operacji | Ostatni dzień miesiąca; pozycje list osobno | Trwający miesiąc nie wygląda na tańszy niż rok wcześniej. | Plan |
| Ujemne przychody | Przeniesienie do najbliższego późniejszego miesiąca źródła | Przychód musi mieć kwotę ≥ 1; suma roczna bez zmian. | Plan |
| Odczyty | Zmiany raportów, blokad kategorii i selektora lat zaakceptowane | Są celem zmiany. | Plan |
| Dowód i wycofanie | `--allow-additions`, `budget:unload-sheet`, próba na kopii `backup:restore` | ADR-0064 / PRD v3 „Migracja danych”; próba musi zachować id. | Research / Plan |

## Scope

**In scope:**
- ADR-0083, glosariusz (Sheet Account, Sheet Load), PRD v3 i roadmapa
- Migracja `sheet_year`, odczyty płynności, ukrycie i blokady konta arkusza, blokada wpisu i Importu w roku wczytanym, snapshot v12
- Sekcja kategorii i tryb „tylko dodano” w numerach kontrolnych
- Czytnik xlsx, plik mapowania, plan wczytania, `budget:load-sheet` i `budget:unload-sheet`
- Procedura w README, próba na kopii, wczytanie na produkcji (krok użytkownika)

**Out of scope:**
- Lata sprzed 2022, transakcje z formuł, faktyczne rachunki z sejfu
- Plany miesięcy, Rytmy (poza Kosmetykami), zebrane, przelewy dla lat z arkusza
- Zmiany widoków i propsów Inertia, edycja operacji z arkusza w UI
- Kategoria „Codzienne wydatki”, nowe aktywne kategorie

## Architecture / Approach

`BudgetSheetReader` (xlsx → rok z adresami komórek i ostrzeżeniami) → `SheetLoadPlanner` (mapowanie → operacje, linie, saldo konta, asercja „Analizy”; bez bazy) → `LoadBudgetSheet` (jedna transakcja: konto arkusza, Budget, linie, bulk insert, Execution) i `UnloadBudgetSheet`. Komendy `budget:load-sheet --dry-run` i `budget:unload-sheet` uruchamiane przez `scripts/artisan.sh`, z numerami kontrolnymi przed i po.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzja i słownik | ADR-0083, terminy, PRD v3 i roadmapa | Sprzeczność z resztą PRD v3 |
| 2. Konto arkusza | `sheet_year`, płynność, ukrycie, blokady, snapshot v12 | Pominięte miejsce, które pokazuje konta zarchiwizowane |
| 3. Numery kontrolne | `--allow-additions` i sekcja kategorii | Klasyfikator przepuści linię w istniejącym Budżecie |
| 4. Czytnik arkusza | Odczyt obu układów po etykietach | Etykieta lub granica listy inna niż w czterech plikach |
| 5. Mapowanie i plan | Tabela, operacje, `--dry-run` | Suma ≠ „Analiza” przez regułę reszt lub przeniesień |
| 6. Zapis i wycofanie | Obie komendy z testami PostgreSQL | Akcja domenowa wywołana przypadkiem zmienia 2027 |
| 7. Próba i produkcja | README, próba, produkcja | Nazwy kategorii produkcji ≠ mapowanie |

**Prerequisites:** pliki `Budżet_2022…2025.xlsx` w `storage/app/private/` głównego checkoutu (są); baza testowa worktree `testing_legacy_budget_import`; dostęp użytkownika do produkcji i archiwum `backup:run`.
**Estimated effort:** ~4–5 sesji w 7 fazach (fazy 4–6 największe).

## Open Risks & Assumptions

- Id 1–38 i nazwy kategorii pochodzą z kopii produkcji na devie; produkcja ma 41 kategorii, więc nazwy potwierdza dopiero `--dry-run` na próbie.
- Luty „pozostałe” 2023 (6 831,07, bez opisu) przyjęty jako zwrot podatku.
- Kwoty nadpisań zwrotów podatku zakładają, że komórka lutego zawiera co najmniej tę kwotę; inaczej plan odmówi.
- Operacji z arkusza nie da się edytować w UI (polityka), a wpis i Import w roku wczytanym odmawiają (inaczej data 2025-12-31 na prawdziwym koncie przesunęłaby saldo 2026); poprawki idą przez wycofanie i ponowne wczytanie.

## Success Criteria (Summary)

- Raporty 2026 i „ostatnie 12 miesięcy” pokazują lata 2022–2025, a sumy roczne równają się „Analizie” każdego pliku.
- `app:control-numbers --compare=<przed> --allow-additions` przechodzi na produkcji; salda, dashboard i szkic 2027 bez zmian.
- `budget:unload-sheet` każdego roku przywraca numery kontrolne bez różnic.
