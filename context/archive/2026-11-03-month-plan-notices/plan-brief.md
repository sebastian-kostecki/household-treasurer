# S-07 Month Plan Notices — Plan Brief

> Full plan: `context/changes/month-plan-notices/plan.md`
> Research: `context/changes/month-plan-notices/research.md`

## What & Why

Dashboard ma pokazywać w strefie „Wymaga uwagi” cztery sygnały z planu bieżącego miesiąca (FR-012, ADR-0033:9): „do wydania” poniżej zera, pozycję ponad plan, tempo wydatków o ≥ 10 pp przed upływem dni oraz plan niezatwierdzony od 3. dnia. Dwa ostatnie to pierwsze powiadomienia o wadze `warning`, więc muszą być rzadkie i trafne. Według roadmapy powiadomienia o drobiazgach uczą ignorowania strefy uwagi.

## Starting Point

`DashboardNotices` emituje dziś cztery rodzaje `exceeded`, w tym `paced_month_remaining`, które według ADR-0033 nie ma już zastosowania. Wszystkie liczby potrzebne nowym powiadomieniom liczy już `MonthToSpend`, który kontroler Dashboardu buduje dla kafelka „do wydania” i wyrzuca po `summary()`. Vue ma gotowy styl `warning`, ale etykiety, linki i tożsamość wybiera po `kind`.

## Desired End State

Przy budżecie w realizacji strefa uwagi pokazuje nowe karty: czerwone dla „Do wydania poniżej zera” i „Ponad plan miesiąca”, pomarańczowe dla „Plan niezatwierdzony” i „Tempo przed upływem dni”. Każda karta prowadzi do przyczyny: Operacje z kategorią i miesiącem albo Plan miesiąca bieżącego roku. Powiadomienia Month Remaining nie ma w kodzie ani w aktualnych dokumentach.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Definicje i progi | Jak w `CONTEXT.md:64,80,92`: dowolna wielkość przekroczenia, także przy planie 0; ≥ 10 pp i „od 3. dnia” włącznie | Ustalone przez F-01 i S-05 przed kodem `warning` (ADR-0032) | Research |
| Źródło liczb | Jeden `MonthToSpend` z kontrolera przekazany do `DashboardNotices` | Bez drugiego zestawu zapytań; predykaty mają już jedno miejsce w PHP | Research |
| `paced_month_remaining` | Usunąć całkiem (kind, klasa, copy, typ, testy) | Jego bramka zawsze oznacza miesiąc z planem, więc nie ma już przypadku | Plan |
| One-off ponad plan i ponad limit | Obie karty | Dwa fakty z dwóch okresów, z różnymi linkami; CONTEXT.md obejmuje One-off | Plan |
| Kategorie zarchiwizowane | Bez powiadomienia „ponad plan”; kwoty nadal liczą się w „do wydania” | Jedna reguła spec dla wszystkich powiadomień (`dashboard-hierarchy.md:49`) | Plan |
| Kolejność | Konta, lokaty, do wydania < 0, ponad plan, ponad limit, potem niezatwierdzony plan, tempo; płaska siatka bez podnagłówków | „Najpierw gotówka, potem plan” i „exceeded first”; pigułki wystarczają | Plan |
| Treść kart | Ten sam szkielet: kategoria albo ikona kalendarza z nazwą miesiąca; tempo „62% · upływ dni 40%”, niezatwierdzony plan bez wartości | Jeden układ siatki, liczba tylko tam, gdzie coś znaczy | Plan |
| Linki | Ponad plan → Operacje (kategoria, miesiąc); pozostałe → Plan miesiąca przez POST `select-year` | Strona Planu czyta rok z powłoki; to ten sam ruch co kafelek | Plan |
| Kafelek „do wydania” | Bez zmian | FR-012 i ADR-0033 każą emitować powiadomienia mimo kafelka | Plan |

## Scope

**In scope:**
- Cztery nowe rodzaje w `DashboardNotices` i ich kolejność
- Usunięcie `paced_month_remaining` z backendu, frontendu, copy i testów
- Karty nowych rodzajów w `DashboardNotices.vue` i copy w `lang/pl/ui.php`
- Aktualizacja `dashboard-hierarchy.md`, ADR-0031, ADR-0032 i `.ai/rules/support.md`

**Out of scope:**
- Tłumienie powiadomień przez inne; podnagłówki wag; zmiana kafelka „do wydania”
- Powiadomienia o dobieraniu ponad Spendable Liquidity i z porównania z rokiem
- Zmiany w `CONTEXT.md`, `BudgetMonthReadings`, propsach Month Remaining strony Budżet

## Architecture / Approach

`DashboardController` → `MonthToSpend::forMonth` (raz) → `DashboardNotices::forDashboard(..., ?MonthToSpend, $today)`. Ta metoda dopisuje powiadomienia w kolejności kontraktu: najpierw `exceeded`, potem `warning`, a w „ponad plan” od największego przekroczenia, potem według kolejności budżetu. Payload niesie `kind` i `severity`. Powiadomienia kategorii mają dane kategorii, powiadomienia miesiąca mają `month` (tempo dodatkowo procenty). `DashboardNotices.vue` mapuje `kind` na etykietę, tożsamość, wartość i link, a kolor bierze tylko z wagi.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend | Nowe rodzaje i kolejność w payloadzie, bez Paced, testy HTTP | Istniejące testy z dokładną listą (23 września, propozycja) dostaną nowe `warning` i trzeba je przepiąć jawnie |
| 2. Frontend | Karty nowych rodzajów, linki, copy | Link POST jako karta-przycisk musi wyglądać i działać jak karta-link (fokus, Enter) |
| 3. Dokumenty | Spec i ADR opisują nowy stan | Rozjazd między spec, ADR-0031 i ADR-0032, jeśli zmiana ominie któryś punkt |

**Prerequisites:** F-01, S-04, S-05 — `done`; `CONTEXT.md` ma terminy i progi.
**Estimated effort:** ~1–2 sesje w trzech fazach; fazy 1 i 2 wydawać razem.

## Open Risks & Assumptions

- W miesiącu z propozycją każdy wydatek One-off daje „ponad plan” (propozycja startuje od 0). To zamierzone (`CONTEXT.md:80`), ale może być głośne do zatwierdzenia planu. Na to odpowiada powiadomienie o niezatwierdzonym planie.
- Karta „ponad plan” i karta „ponad limit” tej samej kategorii One-off mogą stać obok siebie. To świadomy wybór.

## Success Criteria (Summary)

- Przy niezatwierdzonym planie od 3. dnia użytkownik widzi pomarańczową kartę, która otwiera Plan miesiąca bieżącego roku.
- Każde przekroczenie planu pozycji albo ujemne „do wydania” daje czerwoną kartę przed wszystkimi ostrzeżeniami.
- `composer ci:check` przechodzi, a `paced_month_remaining` nie występuje w kodzie ani w aktualnych dokumentach.
