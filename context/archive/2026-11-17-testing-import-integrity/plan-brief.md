# Integralność Importu i bramka trafności podpowiedzi — Plan Brief

> Full plan: `context/changes/testing-import-integrity/plan.md`
> Research: `context/changes/testing-import-integrity/research.md`

## What & Why

To jest etap 1 planu testów: ryzyka #1 i #4. Zatwierdzenie Importu nie może po cichu dublować ani gubić operacji, a błąd jednego wiersza musi wycofać cały zestaw. Trafność podpowiedzi kategorii nie może spaść niezauważona. Dziś kod w dużej mierze tego pilnuje, ale testy liczą tylko rekordy albo patrzą na odpowiedź HTTP, a trafności nie pilnuje żadna bramka.

## Starting Point

- **Atomowość:** cały zestaw zapisuje jedna transakcja (`ImportFinancialOperations.php:78`).
- **Duplikaty:** to ostrzeżenie z jednym potwierdzeniem, bez ograniczenia w bazie. Powtórzony `index` w żądaniu tworzy dwie operacje z jednego wiersza (luka A).
- **Gubienie wierszy:** wiersze z nieczytelną datą albo kwotą znikają bez śladu, bo tak każe FR-IMP-08 (luka B). BNP ignoruje kolumnę `Waluta` (luka C).
- **Trafność:** S-23 zmierzył ją na kopii produkcji z 11.09 (0 różniących się wierszy), ale nie zostawił bramki, bo tekst z banku nie trafia do repo.
- **CI:** cały zestaw testów działa już na PostgreSQL.

## Desired End State

- Testy na PostgreSQL sprawdzają stan bazy przed i po zatwierdzeniu:
  - wyciąg przez Nowy Rok wycofuje wszystko, razem z saldem, wykonaniem i dołączoną pozycją;
  - rozbita para wraca;
  - zapisane saldo = pochodne;
  - ponowny Import bez potwierdzenia nic nie zapisuje;
  - obsługiwane zmiany układu dają komplet wierszy, a zmiany nagłówka padają głośno.
- Powtórzony `index` odpada na walidacji.
- Bramka trafności na 1031 pseudonimizowanych wierszach z Twojej historii czerwieni każdy spadek z listą wierszy.
- Issue B i C są na GitHubie. Twoje świeże eksporty są fixture. Plan testów ma przeniesione korekty.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Potwierdzone luki | A (`distinct`) naprawiona tu po czerwonym teście; B i C jako issue | Zmiana zostaje testowa, a B naprawiamy dopiero, gdy prawdziwy wyciąg go pokaże | Plan |
| Źródło fixture wyciągów | Najpierw istniejące prawdziwe pliki i ręczne warianty, Twoje świeże eksporty w osobnej fazie | Nic nie blokuje implementacji, a dzisiejszy format i tak trafi do testów | Plan |
| Zestaw referencyjny trafności | Pseudonimy tokenów ze snapshotu z 2026-11-12 (1031 wierszy), generator sprawdza zgodność rankingu z oryginałem | Prawdziwy rozkład danych bez tekstu z banku w repo | Plan |
| Próg trafności | Zero spadku (reguła `--compare` z S-23), poprawa przechodzi, bazę podnosi się świadomie | Bez powolnego dryfu w dół | Plan |
| Równoczesne podwójne zatwierdzenie | Zaakceptowane ryzyko w §7, test tylko dla ponownego wysłania po sukcesie | Zero zmian produkcyjnych bez testu | Plan |
| Poziom bazowy #4 | Wynik stałego zestawu w repo, nie „przed S-16” ani odczyt z produkcji | S-16 porzucono; odczyt na żywych danych zmienia wiersze, a `--compare` wymaga tych samych | Research |
| Wyrocznie | Ręczna liczba wierszy i krotki z pliku, saldo pochodne `AccountBalanceOnDate` | Oczekiwane wartości nie pochodzą z testowanego kodu | Research |
| Duplikat przy innym mapowaniu | Bez zmian i bez testu utrwalającego (ADR-0007) | To świadoma granica dopasowania dokładnego | Research |

## Scope

**In scope:**
- testy atomowości, duplikatów i formatów w `tests/Feature/ImportIntegrityTest.php`;
- reguła `distinct`;
- warianty fixture mBank i BNP;
- komenda `import:suggestion-reference` i jej testy;
- zestaw referencyjny, plik bazowy i test benchmarku;
- issue B i C;
- fixture ze świeżych eksportów;
- §2, §3, §5, §6 i §7 planu testów.

**Out of scope:**
- naprawa luk B i C;
- licznik pominiętych wierszy (zmiana FR-IMP-08);
- blokada wyścigu podwójnego zatwierdzenia;
- unikalne ograniczenie na digest;
- zmiany podpowiadacza i komendy pomiaru;
- nieaktualne S-14 i S-16 w ryzykach #5 i #6;
- refaktor `CsvImportTest.php`.

## Architecture / Approach

Testy Feature przechodzą przez prawdziwe trasy: podgląd → sesja → zatwierdzenie. Każdy porównuje migawkę stanu bazy (liczności, salda, wykonanie, pozycje) przed i po. Fixture to prawdziwe pliki z ręcznymi edycjami.

Benchmark ma trzy części:
- Generator czyta snapshot `user:backup` i zamienia każdy znormalizowany token na stały pseudonim. Przed zapisem sprawdza, że `rank()` na pseudonimach jest identyczny jak na oryginale.
- Test zasiewa bazę z zestawu i uruchamia istniejące `measure()`.
- Wynik trafia do istniejącego `differences()` z kluczami wierszy zamiast id.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Atomowość i salda | Wycofanie ze stanem bazy: Nowy Rok, para, saldo zapisane = pochodne | Wiersz padający musi iść po wierszu zapisanym, inaczej test nic nie dowodzi |
| 2. Duplikaty | Ponowne wysłanie, brak potwierdzenia, identyczne wiersze, nakładka; `distinct` po czerwonym teście | Jedyna zmiana produkcyjna; UI nie wysyła powtórzeń |
| 3. Formaty + issue B, C | Warianty mBank/BNP z prawdziwych plików, głośne błędy nagłówka, pełny przebieg | Warianty muszą powstać z ręcznej edycji, nie z parsera |
| 4. Benchmark trafności | Generator, zestaw 1031 wierszy, plik bazowy, bramka zero spadku | Czas replayu w CI (koszt kwadratowy); nazwy kategorii w repo |
| 5. Świeże eksporty | Fixture z dzisiejszego eksportu i prawdziwa nakładka | Zależy od Twoich plików i przeglądu anonimizacji |
| 6. Plan testów | Korekty §2/§3/§5, cookbook §6, ryzyko w §7 | — |

**Prerequisites:**
- Stack Sail działa (sieć `household-treasurer_sail`, obraz `sail-8.5/app`).
- Baza `testing_testing_import_integrity` jest utworzona.
- Snapshot `1-20261112070312.json` jest w głównym checkoutcie.
- `gh` jest zalogowane.
- Do fazy 5 potrzebne są Twoje trzy eksporty.

**Estimated effort:** ~3–4 sesje: fazy 1–3 w jednej–dwóch, faza 4 w jednej, faza 5 i 6 krótko po dostarczeniu plików.

## Open Risks & Assumptions

- **Pseudonimizacja** jest bezstratna tylko przy założeniu, że model widzi wyłącznie tożsamość tokenów. Generator to sprawdza i przerywa przy różnicy, ale założenie nie było dotąd testowane.
- **Liczby bazowe** mogą różnić się od odczytu S-23. Zestaw zasiewa jeden Budżet 2026 i stan kategorii ze snapshotu z 12.11.
- **Benchmark** może być wolny w CI. Implementujący pyta przed przycięciem zestawu.
- **Świeże eksporty** mogą ujawnić lukę B na prawdziwych danych. Wtedy test zostaje pominięty z odnośnikiem do issue B, a naprawa idzie osobno.

## Success Criteria (Summary)

- Celowe usunięcie transakcji, mapowanie mBank po pozycji albo zepsucie modelu podpowiedzi czerwienią nowe testy z czytelnym powodem.
- Ponowny Import nakładającego się wyciągu bez potwierdzenia zostawia bazę bez zmian.
- `composer ci:check` na PostgreSQL jest zielony z nowymi testami, a bramka trafności pilnuje poziomu bazowego zapisanego w repo.
