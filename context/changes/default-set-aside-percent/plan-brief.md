# Domyślny procent „odkładam” w propozycji planu — Plan Brief

> Full plan: `context/changes/default-set-aside-percent/plan.md`

## What & Why

Użytkownik odkłada co miesiąc ok. 10 % dochodu („generalnie tylko odkładam jakieś 10 % z miesiąca”, PRD v3), a dziś wpisuje tę kwotę ręcznie albo dziedziczy ją z poprzedniego miesiąca. FR-024 (roadmapa S-33, nice-to-have) zamienia to w ustawienie: propozycja planu miesiąca sama wpisuje jako „odkładam” ustawiony procent planowanych przychodów.

## Starting Point

Propozycja kopiuje dziś dodatnie „odkładam” z poprzedniego miesiąca, a Draw lub brak źródła daje 0 (`MonthPlanProposal.php:181`, ADR-0033:5, słownik Set-aside). Aplikacja nie ma żadnego ustawienia domenowego użytkownika. Jedyny wzór to kolumna `users.first_run_checklist_dismissed_at` w snapshocie.

## Desired End State

W Ustawieniach jest zakładka „Plan miesiąca” z polem „Domyślne odkładam … %”. Przy 10 % i planowanych przychodach 9 000 zł propozycja pokazuje „odkładam” 900, a pod polem „10 % planowanych przychodów (9 000,00) · Zmień w ustawieniach”. Puste pole przywraca dzisiejsze kopiowanie. Zatwierdzone plany i wzór „do wydania” się nie zmieniają.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Relacja do kopiowania | Procent zastępuje kopiowanie, gdy jest ustawiony | Każdy miesiąc to 10 % własnych przychodów, także w miesiącu z premią; zapadka i „tylko bez źródła” odrzucone. |
| Miejsce ustawienia | Jedna wartość na użytkownika (`users.default_set_aside_percent`), zakładka Ustawienia → „Plan miesiąca” | Ustawiasz raz na wszystkie lata, a strona planu nie gęstnieje. |
| Precyzja i zaokrąglenie | Procent całkowity 0–100, kwota w dół do pełnego złotego | Okrągła kwota jak wpisywana ręcznie, nigdy więcej niż procent (8 437,55 → 843). |
| Edycja przychodów w propozycji | Procent daje tylko kwotę startową liczoną na serwerze | Bez kopii logiki w TS; pole „odkładam” zmienia się tylko, gdy sam je ruszysz. |
| Pochodzenie kwoty | Podpowiedź z linkiem pod polem, tylko w propozycji z procentem | Widać, skąd jest kwota i gdzie ją zmienić; kwota bez „zł” zgodnie z ADR-0058. |
| Wartość po wdrożeniu | Puste (= dzisiejsze kopiowanie); dozwolone 0–100, 0 % daje 0 | Wdrożenie niczego nie zmienia bez Twojej decyzji. |

## Scope

**In scope:**
- ADR-0081 i słownik (Set-aside, nowe hasło Default Set-aside Percent)
- Kolumna, zakładka Ustawień z walidacją i toastem, stan w `/dev/ui/settings`
- Snapshot v11 (v1–10 przywracają `null`), zerowanie w `user:reset-data`, README „Wycofanie S-33”
- Reguła w `MonthPlanProposal` z testami regresji
- Prop `set_aside_default` (PHP i TS) i podpowiedź w planie miesiąca, stan w `/dev/month-plan`

**Out of scope:**
- Przeliczanie na żywo przy edycji przychodów; procent dziesiętny; zaokrąglenie do grosza
- Procent na budżet roczny lub ustawiany z planu miesiąca; start z 10 % w migracji
- Zmiany Draw, „do wydania”, Podsumowania miesiąca i zatwierdzonych planów; cele odkładania

## Architecture / Approach

Procent czyta się raz w `MonthPlanProposal::forBudget`. W gałęzi propozycji `computePlan` sumuje już obliczone kwoty linii przychodów i wpisuje `intdiv(P × p, 10 000) × 100`. Wynik wraz z podstawą jedzie w `MonthPlanReading` do wszystkich odbiorców (zatwierdzenie, „do wydania”, Dashboard, ostrzeżenia operacji i Importu), więc żaden z nich nie wymaga zmian. Kontroler planu dodaje z odczytu tylko prop podpowiedzi.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Słownik i ADR-0081 | Reguła zapisana przed kodem | Rozjazd słów ADR z decyzjami planu |
| 2. Ustawienie procentu | Kolumna, zakładka Ustawień, snapshot v11, reset | Lista wersji w `RestoreUserData` bez 10 → starsze backupy nie wczytają się |
| 3. Propozycja liczy procent | Nowa kwota startowa „odkładam” z testami | Zapytanie w rekurencyjnym `computePlan` (Sloppy) |
| 4. Podpowiedź w planie miesiąca | Linia „10 % planowanych przychodów (…) · Zmień w ustawieniach” | Prop PHP/TS rozjechany bez `assertInertia` |

**Prerequisites:** S-24 done (spełnione); baza `testing_default_set_aside_percent` na PostgreSQL dla worktree.
**Estimated effort:** ~2 sesje, 4 fazy (faza 2 największa).

## Open Risks & Assumptions

- Zapis procentu od razu zmienia „odkładam” we wszystkich miesiącach, które są jeszcze propozycją, w tym w bieżącym. To zamierzone, ale łatwo to przeoczyć.
- Podstawą jest plan przychodów propozycji. Gdy wpływ różni się od planu, „odkładam” go nie śledzi.
- Restore snapshotu v1–10 na użytkownika z ustawionym procentem nadpisuje procent na `null`, bo snapshot jest stanem danych.

## Success Criteria (Summary)

- Przy 10 % propozycja wpisuje 10 % planowanych przychodów w dół do złotego i mówi, skąd jest ta kwota.
- Bez procentu wszystko działa jak dziś. Zatwierdzone miesiące i starsze snapshoty się nie zmieniają.
- Pełna bramka na PostgreSQL i Sloppy są zielone.
