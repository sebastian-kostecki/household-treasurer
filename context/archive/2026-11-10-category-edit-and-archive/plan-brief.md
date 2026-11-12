# Category Edit and Archive — Plan Brief

> Full plan: `context/changes/category-edit-and-archive/plan.md`

## What & Why

S-19 (FR-002, FR-003, FR-019, FR-022): po S-18 lista kategorii przez lata istnieje, ale „Kategorie” są tylko do odczytu, nowej kategorii nie da się wybrać w roku, w którym nie ma pozycji, a operacji na zarchiwizowanej kategorii nie da się zapisać. Ta zmiana robi z „Kategorii” miejsce zarządzania listą i dopina wybór w operacjach, Imporcie i planie miesiąca do ADR-0063.

## Starting Point

Archiwum i przywracanie są już na liście, ale wywołuje je tylko Budżet. Tworzenie, edycja i kolejność są przypięte do budżetu, a opisu nie ma w bazie. Selektory biorą pozycje budżetów w wykonaniu, walidacja odrzuca każdą archiwalną kategorię, a plan miesiąca sprawdza tylko archiwum pozycji.

## Desired End State

W „Kategoriach” dodajesz „Domeny” z opisem, przesuwasz ją, zmieniasz kolor, archiwizujesz, przywracasz albo usuwasz literówkę bez historii. Pierwsza operacja z 2026 na „Domenach” (ręcznie albo z Importu) tworzy pozycję 2026 z planem 0, a kwota w planie miesiąca robi to samo. Edycja „Hotel Zakopane” na zarchiwizowanych „Wakacjach” zapisuje poprawkę. `user:backup` pisze v7 z opisem.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Dołączanie przy pierwszym użyciu | Operacja ręczna, Import i plan miesiąca (kwota > 0, przy zatwierdzeniu i zmianie) | Dokładnie ADR-0063 i FR-019; nowa kategoria działa wszędzie od razu |
| Usuwanie z listy | Tak, gdy brak operacji w każdym roku i pozycji w wykonaniu | Literówki znikają i nie blokują nazwy; warunek już jest (`CategoryTypeChange`) |
| Opis | Do 500 znaków, widoczny tylko w „Kategoriach” | Spełnia FR-001/FR-002 najmniejszym kosztem; selektory poczekają na grupy (S-22) |
| Kategorie a Budżet | Oba dialogi zostają; Budżet bez opisu | Zero regresu na Budżecie; S-20/S-24 i tak ruszą jego dialog |
| Kolejność | Przeciąganie także w „Kategoriach” | Da się ustawić każdą aktywną kategorię, także bez pozycji w bieżącym budżecie |
| Edycja operacji na archiwalnej | Niezmieniona archiwalna przechodzi, gdy rok daty ma jej pozycję | Poprawki działają bez przywracania; archiwum decyduje tylko o wyborze |
| Archiwum | Dla każdej aktywnej kategorii, bez warunku pozycji w wykonaniu | ADR-0063 go nie wymaga; świeża kategoria też da się zarchiwizować |
| Zapis decyzji | ADR-0066 + zdania w słowniku przed kodem | Twarda zasada AGENTS.md dla reguł słownika |
| Struktura kodu | `JoinBudgetAtFirstUse`, `CategoryChoiceOptions`, `OperationCategoryChoice` | Jedno miejsce dołączania i jedna reguła wyboru zamiast czterech kopii walidacji |

## Scope

**In scope:**
- ADR-0066, słownik, kolumna `categories.description`, snapshot v7 (v1–v6 się wczytują)
- trasy i akcje listy: tworzenie, edycja, kolejność, usuwanie; archiwum i przywracanie z `back()`
- widok „Kategorie”: dialog, akcje wiersza, przeciąganie, opis, kitchen sink, design system, E2E
- opcje z aktywnych kategorii listy w Operacjach i Imporcie, dołączanie w `lockLine`, zapis niezmienionej archiwalnej, dopisek „(zarchiwizowana)”
- plan miesiąca: wiersze dołączenia z listy (z poprawką archiwum listy), pozycja przy kwocie > 0

**Out of scope:** grupy (S-22), rytm (F-05, S-24), scalanie (S-21), nowa zasada budżetu (S-20), podpowiedzi Importu (S-23), opis w selektorach i Budżecie, edycja archiwalnej kategorii, zmiana `MonthPlanProposal` i reguły „do wydania”, Cash Expectation.

## Architecture / Approach

```
Kategorie ──► CategoryListController (store/update/reorder/destroy) ──► categories (+ description)
                                                                         └─ JoinDraftBudgets (szkice, plan 0)
Operacje / Import ─ CategoryChoiceOptions (pozycje + „dołączy”) ─► OperationCategoryChoice (walidacja)
                                                                   └─► ManageFinancialOperation::lockLine ─► JoinBudgetAtFirstUse
Plan miesiąca ─ kandydaci (pozycje + „dołączy”) ─► Approve/ChangeMonthPlan (kwota > 0) ─► JoinBudgetAtFirstUse
```

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Domena i dane | ADR-0066, słownik, kolumna opisu, snapshot v7 | Pominięta wersja w listach `RestoreUserData` |
| 2. Zapisy listy (backend) | Trasy, akcje, polityki, propsy „Kategorii” | Regres dialogu Budżetu po wydzieleniu części listy |
| 3. Widok „Kategorie” | Dialog, akcje, przeciąganie, opis, sink, E2E | Siatka z uchwytem i akcjami na telefonie |
| 4. Wybór w operacjach i Imporcie | Opcje z listy, dołączanie, archiwalna na edycji | Podgląd ostrzeżeń różny od stanu po zapisie dla kategorii, która dołączy |
| 5. Plan miesiąca | Wiersze dołączenia, pozycja przy kwocie > 0 | Rozjazd zbioru propozycji przy zatwierdzeniu |

**Prerequisites:** S-18 zamknięty (jest), Sail z PostgreSQL do testów migracji i zapytań.
**Estimated effort:** ok. 5–6 sesji w 5 fazach; fazy 3 i 4 to większość pracy.

## Open Risks & Assumptions

- Kategoria bez pozycji w roku nie jest jeszcze podpowiadana w Imporcie (`ImportCategorySuggester` bez zmian do S-23).
- Pozycja zarchiwizowana w starszym roku po przywróceniu w nowszym nie jest do wyboru w tym starszym roku (archiwum pozycji wygrywa dla swojego roku).
- Snapshot v7 nie wczyta się w kodzie sprzed S-19; wycofanie przez `down()`.
- Każda zmiana typu albo usunięcie zależy od `CategoryTypeChange`; pierwsza operacja na nowej kategorii blokuje jej typ.

## Success Criteria (Summary)

- W „Kategoriach” tworzysz, edytujesz z opisem, przesuwasz, archiwizujesz, przywracasz i usuwasz kategorie, a Budżet działa jak przed zmianą.
- Nowa kategoria jest od razu do wyboru w operacji, Imporcie i planie miesiąca i dołącza z planem 0; archiwalna znika z wyboru, a na istniejącej operacji zostaje i da się zapisać.
- `user:backup` / `user:restore` obejmują opis, a starsze snapshoty się wczytują.
