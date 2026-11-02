# S-03: Zmiana zatwierdzonego planu miesiąca — Plan Brief

> Full plan: `context/changes/approved-plan-changes/plan.md`
> Research: `context/changes/approved-plan-changes/research.md`

## What & Why

Użytkownik musi móc zmienić zatwierdzony plan w trakcie miesiąca (FR-003). Zmiana dotyczy tylko Current Month Plan; Approved Month Plan zostaje zamrożony, żeby podsumowanie miesiąca (S-09) mogło pokazać plan zatwierdzony obok końcowego — ochrona przed „podnoszeniem planu, by liczba wychodziła na plus” to widoczność, nie blokada.

## Starting Point

S-01 zapisuje plan dopiero przy zatwierdzeniu, z dwiema kolumnami kwot (`approved_*` i `current_*`, dziś równe). Jedyny zapis to `approve`, który odrzuca plan już zatwierdzony; strona pokazuje zatwierdzony plan „tylko do odczytu”. Odczyt, „do wydania” i propozycja następnego miesiąca już czytają kwotę bieżącą.

## Desired End State

Na zatwierdzonym bieżącym albo przyszłym miesiącu jest „Zmień plan”: pola kwot (także dla kategorii utworzonych po zatwierdzeniu), podgląd „do wydania”, „Zapisz zmiany” / „Anuluj”. Zapis zmienia tylko kwoty bieżące. Pozycja o kwocie innej niż zatwierdzona pokazuje „Zatwierdzono: X”. Miniony miesiąc zostaje tylko do odczytu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Które miesiące | Bieżący i przyszłe zatwierdzone; miniony zablokowany | Ta sama reguła co `can_change_kind`, a miniony to plan końcowy | Plan |
| Kategorie bez pozycji | Niezarchiwizowana z kwotą > 0 dołącza z approved = 0, current = kwota; kwota 0 nie tworzy pozycji | ADR-0033 „joins at 0”, bez migracji i podbicia snapshotu | Plan |
| Widok kwoty zatwierdzonej | „Zatwierdzono: X” pod kwotą, gdy current ≠ approved | Różnica widoczna od pierwszej zmiany bez nowej kolumny | Plan |
| UX zmiany | Tryb „Zmień plan” z „Zapisz zmiany” / „Anuluj” | Strona zostaje stabilnym odczytem; podgląd wyraźnie jest szkicem | Plan |
| Set-aside / S-02 | S-03 tylko kwoty kategorii; drugi z wycinków dopisuje Set-aside | S-03 nie czeka na S-02 i nie podbija snapshotu | Plan |
| Schemat | Bez migracji; kolumny są już rozdzielone | S-01 rozdzielił je dla S-03 | Research |
| Zarchiwizowane | Pozycja w planie edytowalna; bez pozycji nie dołącza | ADR-0033: zarchiwizowana zostaje tam, gdzie ma plan | Plan |
| Pozycje nieprzesłane | Bez zmian | Kategoria utworzona w innej karcie nie psuje zapisu | Plan |
| Kopia TS „do wydania” | Używana też w trybie zmiany; powód dopisany w `AGENTS.md` | Podgląd musi reagować przed nadpisaniem planu końcowego | Research |

## Scope

**In scope:**
- Trasa `PATCH month-plans.update`, `UpdateMonthPlanRequest`, akcja `ChangeMonthPlan`
- Propsy `can_change`, `approved_minor_units`, `is_join_candidate` (PHP + TS + `assertInertia`)
- Tryb „Zmień plan” w `MonthPlan.vue`, teksty w `lang/pl/ui.php`
- Uzasadnienie kopii TS w `AGENTS.md` i docblocku

**Out of scope:**
- Set-aside, zmiana minionego miesiąca, cofnięcie zatwierdzenia
- Migracja, historia zmian, `NULL` w kwocie zatwierdzonej, snapshot v6
- Kolumna „Zatwierdzony” i porównania (S-04/S-09)
- Zmiana wzoru „do wydania”

## Architecture / Approach

Nowa ścieżka zapisu obok zatwierdzenia: `ChangeMonthPlan` w transakcji blokuje wiersz `month_plans`, aktualizuje `current_amount_minor_units` przesłanych pozycji i tworzy pozycje dołączających kategorii z `approved = 0`. `MonthPlanReading` dostaje mapę kwot zatwierdzonych; kontroler wylicza flagę zmiany i kandydatów do dołączenia. Vue rozszerza formularz propozycji o flagę `isChanging`, a `<Form>` przełącza akcję `approve`/`update`. Przykład: Jedzenie 150 000 → 180 000 i nowa kategoria 50 000 zmieniają „do wydania” z 250 000 na 170 000; zatwierdzone kwoty zostają.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Ścieżka zmiany na serwerze | Trasa, request, akcja, kontroler, testy na PostgreSQL | Niezmiennik: `approved_*` istniejącej pozycji nigdy nie jest zapisywane |
| 2. Tryb zmiany na stronie | Propsy, tryb „Zmień plan”, podpowiedź, teksty, ręczne sprawdzenie | Podział edytowalnych wierszy między propozycją a zmianą; reset pól po zapisie |

**Prerequisites:** S-01 (na `development`), działający Sail z PostgreSQL, kroki „Fresh checkout” w worktree.
**Estimated effort:** ~2 sesje w 2 fazach.

## Open Risks & Assumptions

- S-02 dotknie tych samych plików; spodziewany konflikt tekstowy w `MonthPlan.vue` dla wycinka, który wyląduje drugi.
- S-09 nie odróżni „zatwierdzono 0” od „dołączono później” — zaakceptowane świadomie.
- Zmiana miesiąca M zmienia niezatwierdzone propozycje M+1… (ADR-0033), bez sygnału w UI.

## Success Criteria (Summary)

- Użytkownik zmienia kwoty zatwierdzonego bieżącego/przyszłego miesiąca i od razu widzi nowe „do wydania”.
- Kwoty zatwierdzone zostają nienaruszone i widoczne przy zmienionych pozycjach.
- Miniony miesiąc pozostaje tylko do odczytu.
