# New Budget From Category List — Plan Brief

> Full plan: `context/changes/new-budget-from-category-list/plan.md`

## What & Why

S-20 (FR-005): nowy budżet bierze aktywne kategorie z listy zamiast kopiować pozycje budżetu wskazanego w oknie, a kwoty planu podpowiada poprzedni budżet. Bez tego kategoria aktywna na liście, której nie było w budżecie źródłowym, nie trafia do nowego roku, a okno dalej mówi o „kopiowaniu kategorii”, choć kategorie są jedną listą od S-18.

## Starting Point

`CreateBudget` kopiuje otwarte pozycje budżetu z `source_budget_id` (plan = plan aktualny, flagi źródła), a okno ma listę „Kopiuj kategorie z [rok | Bez kopiowania]” zasilaną propem powłoki `copyableBudgetSources`. Produkcja ma 40 aktywnych kategorii, budżet 2026 w wykonaniu i szkic 2027 (38 pozycji; „Jedzenie poza domem” i „Eksploatacja domu” mają w nim odpowiedniki „Restauracje” i „Utrzymanie domu”, do scalenia w S-21).

## Desired End State

Tworzysz 2028: szkic dostaje 40 pozycji, po jednej na aktywną kategorię, z planem i flagami ze szkicu 2027, a kategorie bez pozycji w 2027 mają plan 0. Okno przed kliknięciem mówi „Szkic dostanie 40 aktywnych kategorii z listy. Plan podpowie budżet 2027.” Szkic 2027 i budżet 2026 nie zmieniają się.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Zbiór pozycji | Wszystkie aktywne kategorie listy, bez wyboru źródła i bez pustego szkicu | Tak mówią FR-005 i ADR-0063 |
| Źródło planu i flag | Najnowszy wcześniejszy budżet, szkic też się liczy | Przerwa w latach nie daje 40 zer, a szkic niesie najnowsze poprawki; jak dzisiejszy domyślny wybór w oknie |
| Kategoria bez pozycji w źródle | Plan 0, flagi „równomierna” i „bieżąca” | Domyślne jak przy nowej kategorii; słownik (Month Item Kind) |
| Pozycja źródła zarchiwizowana | Plan 0, flagi z pozycji | Zarchiwizowana pozycja nie jest otwarta do wyboru; przypadek tylko ze starych danych |
| Rok wstecz (np. 2025 przy 2026) | Ta sama reguła: aktywne kategorie, plan 0 | Jedna reguła bez wyjątku od FR-005 |
| Okno | Pole roku + zdanie z liczbą kategorii i rokiem źródła (albo „z planem 0”, albo „Szkic będzie pusty”) | Skutek widać przed kliknięciem na Twoich danych |
| Prop powłoki | `copyableBudgetSources` → `activeCategoryCount`; rok źródła liczy okno z `budgetYears` | Liczba zapytań na żądanie bez zmian, a reguła roku jest prosta |
| Istniejący szkic 2027 | Bez zmian; brakujące kategorie dołączają przy pierwszym użyciu | Uzupełnienie podwoiłoby limity par przed S-21, a nowe utworzenie straciłoby poprawki |
| Dokumentacja reguły | ADR-0066 + słownik (Budget, Month Item Kind) przed kodem | „Najnowszy wcześniejszy” zmienia słowa „previous year” w słowniku (twarda reguła AGENTS.md) |
| Testy | Feature na PostgreSQL + Vitest funkcji zdania; istniejące E2E zielone | Każdy przypadek brzegowy ma szybki test, bez E2E dublującego Feature |

## Scope

**In scope:**
- ADR-0066 i wpisy słownika
- `CreateBudget` i `StoreBudgetRequest` bez `source_budget_id`; przepisane testy Feature
- prop `activeCategoryCount`, `lib/create-budget-summary.ts` z Vitest, okno, teksty, trzy stany w `/dev/ui/dialogs`

**Out of scope:**
- zmiana istniejących budżetów, akcja „Uzupełnij z listy”, ponowne tworzenie szkicu 2027
- wybór roku planu, pusty szkic, plan z późniejszego roku
- snapshot, migracje, rytm (F-05), grupy (S-22), selektory (S-19), scalanie (S-21), nowe E2E

## Architecture / Approach

```
POST /budgets {year}
  CreateBudget: źródło = budżet o najwyższym roku < year (szkic też)
    dla każdej Category z archived_at IS NULL:
      pozycja źródła istnieje i otwarta → plan + flagi z niej
      istnieje, zarchiwizowana         → plan 0, flagi z niej
      brak                             → plan 0, Paced, Running
    jeden insert do budget_lines
powłoka: activeCategoryCount + budgetYears → createBudgetSummary() → hint pola roku
```

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Słownik i ADR-0066 | Reguła źródła, roku wstecz i szkiców zapisana przed kodem | Sformułowanie rozjedzie się z ADR-0063 |
| 2. Reguła na serwerze | `CreateBudget` z listy, żądanie bez źródła, testy PG | Nowy budżet sam jako źródło albo zapytanie w pętli |
| 3. Okno „Nowy budżet” | Prop, funkcja zdania, okno, teksty, kitchen sink | Rozjazd propu PHP i TS; E2E szukające starych nazw |

**Prerequisites:** S-18 zamknięty (jest); stack Sail dla testów PostgreSQL i E2E.
**Estimated effort:** ok. 1–2 sesje w 3 fazach; najwięcej pracy w przepisaniu testów `BudgetTest.php`.

## Open Risks & Assumptions

- Roadmapa zakłada, że budżet 2027 powstanie w grudniu, ale produkcja ma już szkic 2027, więc na produkcji nowa zasada zadziała dopiero przy budżecie 2028.
- Do S-21 szkic 2027 nie ma „Jedzenia poza domem” i „Eksploatacji domu”; dołączą z planem 0 przy pierwszej operacji.
- Rok źródła w oknie i na serwerze liczą dwie implementacje tej samej prostej reguły; pilnują ich testy po obu stronach.

## Success Criteria (Summary)

- Nowy budżet ma pozycję dla każdej aktywnej kategorii listy, z planem i flagami z najnowszego wcześniejszego budżetu albo planem 0.
- Okno przed utworzeniem mówi, ile kategorii dostanie szkic i skąd przyjdzie plan.
- Istniejące budżety, w tym szkic 2027, nie zmieniają się.
