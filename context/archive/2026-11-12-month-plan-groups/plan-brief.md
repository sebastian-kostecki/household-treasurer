# Plan miesiąca w grupach — Plan Brief

> Full plan: `context/changes/month-plan-groups/plan.md`

## What & Why

S-25 (FR-008, must-have M-3): plan miesiąca pokazuje kategorie w zwijanych grupach kategorii, z sumą grupy i sygnałem „ponad plan” na wierszu grupy, także zwiniętej. Grupy z S-22 działają już w „Kategoriach”, Budżecie i Raportach. Plan miesiąca, w którym użytkownik co miesiąc przegląda i zatwierdza kwoty, jest ostatnim płaskim widokiem. Kontrargument z PRD („zwinięta grupa ukrywa przekroczenie”) rozstrzyga sygnał na wierszu grupy.

## Starting Point

Serwer wysyła płaską, uporządkowaną listę wierszy bez `group_id` i `sort_order`. Strona liczy w trybie edycji „ponad plan” i odchylenie wiersza na żywo w TS (`rowState`). Budżet ma gotowy wzorzec wiersza grupy (ADR-0069): `buildCategoryTree`, przycisk zwijania z `aria-expanded` i badge „ponad plan”, ale jego sumy pochodzą z serwera, więc nie nadążają za wpisem.

## Desired End State

Sekcje Dochody i Wydatki pokazują grupy i kategorie bez grupy w kolejności listy. Wiersz grupy ma nazwę z liczbą kategorii, sumę Planu, sumę Faktycznie i „ponad plan” w Statusie, gdy którakolwiek kategoria przekracza plan według swojego rytmu. Grupy startują rozwinięte. W trybie zmiany suma Planu nadąża za wpisem, a błędna kwota rozwija zwiniętą grupę. To Spend, „Razem w roku” i panel miar się nie zmieniają.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Kolumny wiersza grupy | Suma Planu i Faktycznie, „ponad plan” w Statusie; Zatwierdzony i Odchylenie puste | Każda liczba to dosłowna suma kolumny, a suma odchyleń (nadwyżka nieregularnej, `null` niezapłaconej stałej) nie jest „Faktycznie − Plan”. |
| Kategorie bez grupy | W jednej kolejności typu, przeplecione z grupami | Ta sama kolejność co w Kategoriach, Budżecie i Raportach (ADR-0069 odrzucił blok „Bez grupy”). |
| Wiersze zarchiwizowane i bezczynne | Poza grupami: zarchiwizowane na końcu typu, bezczynne w swojej sekcji | Grupy pokazują bieżące planowanie bez szumu, jak Budżet i ADR-0072. |
| Zwinięcie | Start rozwinięty, stan na czas wizyty, reset przy zmianie miesiąca | Jak Budżet i Raport okresu; przy zatwierdzaniu wszystkie pola są widoczne. |
| Gdzie liczyć sumy i sygnał | Czysta funkcja TS nad `rowState` wierszy | Jedna ścieżka dla odczytu i edycji, bez nowej pary kalkulatorów PHP/TS. |
| Błędne pole w grupie | Suma Planu „—”, grupa sama się rozwija | Jak sekcja bezczynnych: błąd blokuje zatwierdzenie, więc musi być widoczny. |
| Nazwa klasy | Nowa `.month-plan-category-group-row`; `.month-plan-group-row` zostaje pasmem sekcji | Bez przemianowania dokumentów ADR-0042 i reguł. |

## Scope

**In scope:**
- ADR-0079, wpis grupy w słowniku, design system i reguła `js-month-plan.md`
- `group_id` i `sort_order` w wierszu, `income_groups` / `expense_groups` w payloadzie, typy TS, `assertInertia`
- `lib/month-plan-groups.ts` z testami Vitest
- `MonthPlanGroupRow.vue`, klasy CSS, zwijanie na stronie, stany w `/dev/month-plan`, strażnicy UI
- Test E2E zwinięcia z „ponad plan” i zrzuty 1280 / 390 px

**Out of scope:** zapamiętywanie zwinięcia między wizytami, suma Zatwierdzonego i odchylenia grupy, blok „Bez grupy”, grupy w Pulpicie, plan albo limit na grupie, zmiany reguł To Spend, Pace i Collected, zmiana kolejności płaskiej listy w payloadzie.

## Architecture / Approach

Serwer dokłada tylko dane (dwa pola na wiersz i listy grup z jednego zapytania). Płaska lista zachowuje kolejność, więc istniejące testy na indeksach zostają ważne. `monthPlanGroupTree(groups, rows, readRow)` składa drzewo na `buildCategoryTree`, odkłada zarchiwizowane na koniec i liczy wiersz grupy z `readRow` (strona podaje `rowState`). `MonthPlanTable` renderuje drzewo z nowym `MonthPlanGroupRow`. Strona trzyma `collapsedGroupIds` jak `idleOpen` i rozwija grupę z nieczytelnym planem.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzja w dokumentach | ADR-0079, słownik, design system, reguła widoku | Rozjazd treści ADR z decyzjami planu |
| 2. Dane grup w propsach | Pola grupy w wierszu i kandydacie, listy grup, typy TS, testy | Klucze kandydata w innej kolejności niż wiersz linii |
| 3. Drzewo i sumy grup w TS | `monthPlanGroupTree` z testami brzegowymi | Zarchiwizowany wiersz z `group_id` wpada do grupy |
| 4. Wiersz grupy w tabeli i kitchen sinku | Komponent, CSS, zwijanie, sink, strażnicy, E2E, zrzuty | Układ na telefonie i odróżnienie od pasma sekcji |

**Prerequisites:** S-22 (grupy) i S-24 (rytm) są `done`; S-26 (wiersz nieregularny) też. Stos Sail uruchomiony dla testów PostgreSQL i E2E.
**Estimated effort:** ~2 sesje w 4 fazach (fazy 1–3 krótkie, faza 4 największa).

## Open Risks & Assumptions

- Wygląd wiersza grupy (bez pasma `muted`, wcięcie wierszy) jest ustalany w fazie 4 i potwierdzany zrzutami; może wymagać jednej iteracji.
- E2E tworzy własną grupę i kategorię na użytkowniku demo i sprząta po sobie; dom demo nie ma grup.

## Success Criteria (Summary)

- Użytkownik widzi plan miesiąca w grupach, a suma grupy równa się sumie wierszy pod nią co do grosza.
- Zwinięta grupa z przekroczoną kategorią pokazuje „ponad plan”, w odczycie i w trybie zmiany.
- Pełna bramka (`composer ci:check`, Sloppy, Vitest, E2E) przechodzi, a To Spend i istniejące testy planu miesiąca się nie zmieniają.
