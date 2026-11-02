# Month Plan Domain Decisions — Plan Brief

> Full plan: `context/changes/month-plan-domain-decisions/plan.md`

## What & Why

Element przygotowawczy F-01 roadmapy „Kontrola miesiąca bez arkusza”. Przed kodem planu miesiąca trzeba zapisać decyzje domenowe: ADR-0024 odrzucił „a second monthly Budget”, a PRD wybrał właśnie plan miesiąca obok rocznego. ADR-0032 zabrania też kodu pierwszego powiadomienia `warning`, dopóki progi nie trafią do `CONTEXT.md`.

## Starting Point

PRD (`context/foundation/prd.md`) rozstrzyga model, wzór „do wydania”, rodzaje pozycji i progi liczbowe, ale żaden dokument domeny tego nie zawiera. `CONTEXT.md` zna tylko Month Remaining i Month Result. Dashboard ma cztery rodzaje powiadomień, wszystkie `exceeded` (`app/Support/DashboardNotices.php`).

## Desired End State

Nowy ADR-0033 zastępuje klauzulę ADR-0024 i zapisuje model planu miesiąca; ADR-0024 odsyła do niego jedną linią. `CONTEXT.md` ma dziewięć nowych terminów z jednoznacznymi predykatami i progami, a sześć istniejących wpisów mu nie przeczy. S-01 i S-07 mogą ruszyć bez nowych decyzji domenowych.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Model planu | Month Plan obok rocznego; zatwierdzone kwoty zamrożone | Budżet roczny i dane historyczne zostają nienaruszone | PRD |
| Wzór „do wydania” | przychody − Bill − Running(max plan/faktyczna) − Set-aside | Rachunek po wpłacie zwalnia oszczędność, bieżąca nie | PRD |
| Month Remaining | tylko minione miesiące bez planu | „Miesiąc ma plan” obejmuje propozycję, więc bieżący zawsze ma plan | PRD |
| Wagi powiadomień | To Spend < 0 i pozycja ponad plan → `exceeded`; tempo i brak zatwierdzonego planu → `warning` | Dokładnie definicja ADR-0032: już przekroczone vs jeszcze w granicach | Plan |
| „Brak planu” | brak **zatwierdzonego** planu od 3. dnia | Propozycja istnieje zawsze, więc dosłowne „brak planu” nie zachodzi | Plan |
| One-off ponad plan | każde przekroczenie, także przy planie 0 | Niezaplanowany duży wydatek to niespodzianka, której zmiana ma zapobiegać | Plan |
| Zakres tempa | jedna pula Running bez One-off | Tygodniowe zakupy nie dają szumu; odpowiada arkuszowi | Plan |
| Upływ dni | dzień miesiąca / dni, z dziś włącznie | Ta sama reguła co Year Elapsed | Plan |
| Nazwy | Month Plan, Approved/Current Month Plan, Month Item Kind (Bill/Running), To Spend, Set-aside/Draw, Month Pace, Month Elapsed, Month Summary | Lustro istniejących par rok ↔ miesiąc w słowniku | Plan |
| Zakres dokumentów | jeden ADR-0033 + `CONTEXT.md` + odsyłacz w ADR-0024 | Minimum odblokowujące S-01 i S-07 bez opisywania nieistniejącego kodu | Plan |

## Scope

**In scope:**

- `.docs/adr/0033-month-plan-beside-annual-budget.md` (nowy)
- jedna linia odsyłacza w `.docs/adr/0024-month-remaining-is-not-available-limit.md`
- `CONTEXT.md`: 9 nowych terminów, dopiski w 6 wpisach (Category, Paced, One-off, Month Remaining, Month Result, Spendable Liquidity)

**Out of scope:**

- kod, migracje, testy, `lang/`
- `.docs/specs/dashboard-hierarchy.md`, `.docs/prd.md` (zmienią je S-06/S-07)
- reguła propozycji dla Set-aside (S-02), UI i kolejność powiadomień (S-07), przechowywanie propozycji i zaokrąglanie (S-01)

## Architecture / Approach

Decyzja przed słownikiem: ADR-0033 ustala model i granice, a `CONTEXT.md` podaje terminy, predykaty i progi, na które S-01 i S-07 będą się powoływać dosłownie. Te same frazy progów („10 percentage points”, „3rd day”) w obu dokumentach.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0033 i odsyłacz w ADR-0024 | Zapisany model planu miesiąca i zastąpiona klauzula | ADR zbyt ogólny, by S-01 nie musiał wracać do PRD |
| 2. Terminy i progi w `CONTEXT.md` | 9 terminów z progami, 6 wpisów bez sprzeczności | Rozrost w przebudowę słownika |

**Prerequisites:** brak; PRD z decyzjami jest zmergowany (PR #173).
**Estimated effort:** ~1 krótka sesja, 2 fazy, tylko dokumenty.

## Open Risks & Assumptions

- Numer 0033 koliduje z lokalną, nieaktualną gałęzią `feat/backfill` (ma już własne 0031–0033 sprzeczne z `development`); przy jej ewentualnym merge trzeba przenumerować jej pliki.
- Reguła propozycji dla Set-aside jest celowo otwarta do S-02; S-01 do tego czasu przyjmuje Set-aside = 0.
- Słownik opisuje plan miesiąca, zanim istnieje kod — zgodnie z ADR-0032, ale wbrew ogólnej przestrodze z AGENTS.md; ADR-0033 to wprost uzasadnia.

## Success Criteria (Summary)

- Agent czytający ADR-0024 albo `CONTEXT.md` nie uzna planu miesiąca za odrzucony ani niezdefiniowany.
- Przykłady z planu dają: To Spend = 1700 (z Draw 2500); Month Pace 8,3 pp bez ostrzeżenia, 11,7 pp → `warning`.
- S-01 i S-07 mogą przejść do `/10x-plan` bez nowych pytań domenowych.
