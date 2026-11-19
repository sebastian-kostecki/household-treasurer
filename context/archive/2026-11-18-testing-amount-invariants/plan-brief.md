# Niezmienniki kwot — Plan Brief

> Full plan: `context/changes/testing-amount-invariants/plan.md`
> Research: `context/changes/testing-amount-invariants/research.md`

## What & Why

Etap 2 planu testów obejmuje dwa ryzyka:
- **#2:** „do wydania” w przeglądarce (TS) różni się od liczby z serwera (PHP).
- **#3:** sumy raportu okresu nie zgadzają się co do grosza z wykonaniem w budżecie rocznym albo z Wynikiem miesiąca.

Obie reguły są już na `main` (S-26 i S-28), więc etap jest siatką regresji wokół nich. Nie poprzedza zmiany reguły.

## Starting Point

- **Kalkulatory.** Kopie PHP i TS kalkulatorów „do wydania” i Zebranych liczą dziś to samo, ale ich testy nie dzielą żadnego przypadku.
- **Wejścia.** Prawdziwe ryzyko jest na wejściach, które serwer wysyła do przeglądarki (`EntryToSpendInputs`), a ten odcinek nie ma testu.
- **Raport.** Testy porównują raport z innymi odczytami, a nie z ręcznie policzoną liczbą. Żaden scenariusz nie łączy zwrotu, transferu własnego, ruchu kapitału, archiwum i granic miesiąca i roku.

## Desired End State

- **Tabela kalkulatorów.** Jedna ręcznie policzona tabela czytana przez Pest i Vitest.
- **Ścieżka wejść.** Scenariusze, w których serwer przed i po zaksięgowaniu oraz podgląd w przeglądarce dają tę samą ręczną liczbę, np. 5 520 → 5 420.
- **Kandydaci.** Kandydat 1 utrwalony jako zgodny z regułą. Kandydat 2 naprawiony albo zgłoszony jako issue.
- **Raport.** Jeden scenariusz, w którym licznik, strona Budżetu, raport i Wynik miesiąca równają się tym samym literałom. Do tego rok wczytany z arkusza.
- **Dokumentacja.** Plan testów i reguły wskazują, gdzie dopisać następny przypadek.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Punkt odniesienia | Reguła S-26 i raport S-28, zamiast „przed S-14 / po S-11” | M-2 porzucono, a obie reguły są już na `main` | Research |
| Wyrocznia | Ręczny literał z glosariusza, z `derivation`; każdy odczyt osobno do literału | Raport == inny odczyt nie łapie błędu wspólnego obu | Research |
| Wykonanie roczne a archiwum | Liczniki pozycji, suma po wszystkich i strona Budżetu z `?show_archived=1`; domyślny widok bez archiwum utrwalony jako zamierzony w §7 | Ekran staje się świadkiem bez zmiany produktu | Plan |
| Zakres #2 | Tabela kalkulatorów + 5 scenariuszy ścieżki wejść + kandydaci 1 i 2 | Ryzyko leży na wejściach, nie w samej arytmetyce | Plan |
| Czerwony kandydat | K1 utrwalony (zgodny z zakresem ostrzeżenia w glosariuszu). K2: poprawka tutaj, jeśli to jedno miejsce bez zmiany reguły, inaczej issue i `it.skip` z numerem | Jak etap 1: zmiana zostaje testowa, błąd nie ginie | Plan |
| Rok z arkusza | Jeden test: licznik = raport = suma Wyników miesiąca = 12 200, z operacją 31.12 | Arkusz to jedyny pisarz bez niezależnej wyroczni | Plan |
| Fixture wspólny | `tests/Fixtures/amount-invariants/*.json`, Pest przez `__DIR__`/`base_path`, Vitest przez `readFileSync`, klucze kategorii zamiast id | Sekwencje PostgreSQL zmieniają id między przebiegami | Plan |

## Scope

**In scope:**
- dwa pliki fixture i pięć nowych testów (Pest Unit, Pest Feature ×2, Vitest ×2);
- scenariusze kandydatów 1 i 2;
- ewentualna mała poprawka kandydata 2 albo issue;
- §2, §3, §5, §6 i §7 planu testów;
- reguły `month-calculators.md` i `reports.md`.

**Out of scope:**
- zmiana reguły To Spend, zakresu ostrzeżenia albo widoku Budżetu;
- kandydaci 3–6 z research;
- północ i strefy czasowe;
- parzystość pozostałych kalkulatorów;
- nieaktualne S-14 i S-16 w ryzykach #5, #6, #8 (do `--refresh`).

## Architecture / Approach

Każdy fixture zawiera wynik policzony ręcznie, a nie sposób jego wyliczenia. Testy PHP zasiewają bazę przez akcje (`ManageFinancialOperation`, `ManageOwnTransfer`, `ManageCapitalMovement`, `ArchiveCategory`), bo tylko wtedy rusza licznik wykonania. W scenariuszach #2 PHP dodatkowo sprawdza, że serwer wysyła dokładnie te wejścia, które zapisano w fixture. TS liczy podgląd na tych samych wejściach. Obie strony porównują wynik z tą samą liczbą.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Tabela parzystości kalkulatorów | Jeden JSON, Pest i Vitest, przypadki dziś nietestowane | Typed lint może odrzucić `JSON.parse(...) as T` (pierwsze użycie w repo) |
| 2. Ścieżka wejść „do wydania” | 5 scenariuszy: serwer przed i po, wejścia i podgląd TS | Ręczne spisanie pełnych wejść `forUser` jest pracochłonne |
| 3. Kandydaci 1 i 2 | K1 utrwalony; K2 czerwony, potem poprawka albo issue | Mechanizm K2 wynika z odczytu kodu i nie był odtworzony |
| 4. Niezmiennik raportu | Scenariusz z granicami i archiwum, rok z arkusza | Ograniczenia ładowarki arkusza przy małym skoroszycie |
| 5. Plan testów i reguły | Backport §2/§3, cookbook §6, §5 `required`, §7, reguły ścieżek | — |

**Prerequisites:**
- Stack Sail działa (sieć `household-treasurer_sail`, obraz `sail-8.5/app`).
- Baza `testing_testing_amount_invariants` jest utworzona.
- W worktree wykonano `npm ci` (Node z nvm).
- `gh` jest zalogowane (wariant z issue).

**Estimated effort:** ~2–3 sesje. Fazy 1–2 w jednej, faza 3 i 4 w jednej, faza 5 krótko.

## Open Risks & Assumptions

- **Liczby w scenariuszach** są policzone ręcznie w planie. Implementujący sprawdza je z glosariuszem przed pierwszym uruchomieniem testów i poprawia literał tylko wtedy, nigdy po zobaczeniu wyniku kodu.
- **Kandydat 2:** jeśli serwer też nie da 4 600, research pomylił się co do mechanizmu. Implementujący zatrzymuje się i zgłasza to.
- **Poprawka K2** prawdopodobnie zmienia kształt propsa `entryToSpend`, czyli wychodzi poza jedno miejsce. Wtedy idzie issue.

## Success Criteria (Summary)

- Zepsucie dowolnej kopii kalkulatora, wejść z serwera, granicy miesiąca w raporcie albo filtra Wyniku miesiąca czerwieni nowy test z czytelną nazwą przypadku.
- Liczba „do wydania” z dialogu wydatku i z planu miesiąca jest w testach jedną ręcznie policzoną liczbą.
- `composer ci:check` na PostgreSQL jest zielony z nowymi testami, a bramka z §5 jest `required`.
