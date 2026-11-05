# Kontrakt modali według Atramentu — Plan Brief

> Full plan: `context/changes/category-dialog-atrament/plan.md`
> Research: `context/changes/category-dialog-atrament/research.md`

## What & Why

Modale wyglądają jak poprzedni system obok paneli Atramentu: `rounded-2xl`, cień Tailwinda, czarna nakładka, rozmyty fokus 3 px. Dialog kategorii po cichu gubi złą kwotę, nie pokazuje zapisu i chowa drugą opcję typu w liście. Zmiana wprowadza kontrakt modali i pól z Atramentu (ADR-0040), z `CategoryFormDialog` jako widokiem wzorcowym.

## Starting Point

Tokeny Atramentu są w `app.css` (w tym `scrim`), ale `ui/dialog/*` ich nie czyta. Pola to kopiowane bloki `Label + kontrolka + InputError` bez `aria-describedby`. Na stronie Budżetu pięć potwierdzeń jest napisanych ręcznie, wbrew regule „Confirmations use ConfirmActionDialog”. Żaden sink nie pokazuje otwartego dialogu.

## Desired End State

Każdy modal ma okno 460 px z `radius-xl`, cieniem `shadow-dialog`, nakładką `scrim` i stopką z ramką. Fokus w aplikacji to obrys 2 px. Dialog kategorii ma pola 34 px w `FormField`, wybory jako pełnoszerokie segmenty, kwotę w Mono z „zł” i komunikatem błędu, spinner i blokadę zamykania. Wszystkie potwierdzenia Budżetu idą przez `ConfirmActionDialog`. Stany ogląda się pod `/dev/ui/dialogs`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Powłoka dialogu | Atrament (460 px, `radius-xl`, `shadow-dialog`, `scrim`, ramka nad stopką), ADR-0040 | Modale mają wyglądać jak reszta Atramentu; ADR-0037 zostawił to na później. | Research |
| Wysokość pola | 34 px tylko w `FormField`; wiersze i tabele 32/28 px | Powód z ADR-0037 (równe wysokości w jednym wierszu) nadal obowiązuje. | Research |
| Fokus | 2 px globalnie: przyciski z odstępem 2 px, pola bez odstępu | Fokus 3 px w 50% jest rozmyty na `card`. | Research |
| „Anuluj” | `outline` | Podgląd Dialogu w Atramencie i wcześniejsza decyzja planu. | Research |
| Ręczne potwierdzenia | Pięć na `ConfirmActionDialog` | Spinner, blokada i błędy serwera jak w Kontach i Lokatach. | Research |
| Pole formularza | Nowy `FormField` (etykieta, podpowiedź, błąd z `id`, „zł”) | Jedna anatomia zamiast kopiowanych bloków, z dostępnością. | Research |
| Hover `outline` | `secondary` globalnie, przycisk `card` + `border` | Zgodnie z Atramentem; „Anuluj” nie błyska kolorem marki. | Plan |
| Wyłączony przycisk | `muted` + `muted-foreground` teraz (`ghost`/`link` tylko tekst) | Pełna zgodność przycisku z Atramentem w jednym kroku. | Plan |
| Deficyt przy „Rozpocznij realizację” | Slot `ConfirmActionDialog` + `Alert` `warning` | Ostrzeżenie nieblokujące to `warning`, nie `destructive` (js.md). | Plan |
| Klucz złej kwoty | `budget.planned_amount_invalid` | Wzorzec modułowy, bez dotykania innych dialogów. | Plan |
| `DialogScrollContent` | Usunąć | Nieużywana druga powłoka ze starym przepisem. | Plan |
| Sink | `/dev/ui/dialogs` z `?open=<stan>` i trasą dev `validate` | Prawdziwe stany błędów i zapisu bez danych w bazie. | Plan |
| Sekcje dialogu | Bez `DialogBody`; stopka do krawędzi ujemnym marginesem | Nie trzeba ruszać 16 konsumentów. | Plan |

## Scope

**In scope:**
- ADR-0040, `design-system.md`, tokeny w `app.css`
- `ui/dialog/*`, fokus w `ui/*` i konsumentach, `ui/button` (`outline`, stan wyłączony), `Spinner`
- `FormField`, `InputError` z `id`, `SegmentedControl` `block`/`name`, pickery kategorii
- `CategoryFormDialog`, `CreateBudgetDialog`, `ConfirmActionDialog`, pięć potwierdzeń Budżetu
- Sink `/dev/ui/dialogs`, `check:ui`, `ui-contract.test.ts`, reguła w `js.md`, zrzuty

**Out of scope:**
- Pola pozostałych dialogów formularzy (dostają tylko nową powłokę, fokus i stany przycisku)
- `DialogBody`, podgląd „przed → po”, `SheetOverlay`, pozostałe `dark:` w `ui/button`/`ui/input`
- Polskie komunikaty walidacji serwera, zmiany payloadu i propsów Inertia

## Architecture / Approach

Kontrakt najpierw w ADR i tokenach, potem prymitywy (zmiana globalna widoczna od razu w sinku na nietkniętym `AccountFormDialog`), potem elementy pola, potem widoki Budżetu, na końcu straże. Wysokość 34 px idzie przez zmienną CSS ustawianą przez `.form-field`, bo `twMerge` nie zna własnych klas wysokości, a `@layer components` przegrywa z utilities. Fokus idzie przez `outline` z `outline-solid`, bo `outline-none` w Tailwind 4.3 zeruje styl obrysu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kontrakt w dokumentach i tokenach | ADR-0040, `design-system.md`, tokeny | Cykl nazw w `@theme inline` (`--shadow-*`) |
| 2. Prymitywy globalnie i sink | Powłoka, fokus, przycisk, `/dev/ui/dialogs` | Zmiana dotyka 16 dialogów i każdego przycisku `outline` |
| 3. Wspólne elementy pola | `FormField`, 34 px, segmenty `block`, pickery | Wysokość przecieka do wierszy tytułu |
| 4. Dialogi Budżetu | Dialog kategorii, budżetu, pięć potwierdzeń | Zablokowany typ musi nadal wysyłać wartość |
| 5. Straże, reguła i zrzuty | `check:ui`, test kontraktu, reguła, zrzuty | — |

**Prerequisites:** Sail z PostgreSQL działa; Playwright w cache `~/.cache/ms-playwright` do zrzutów.
**Estimated effort:** ~3–4 sesje w 5 fazach.

## Open Risks & Assumptions

- `outline` zmienia tło z `background` na `card` w całej aplikacji: widoczne na szarej podłodze (wiersze tytułu); trzeba to obejrzeć w kilku widokach.
- Stan wyłączony `muted` zmienia każdy wyłączony przycisk (import, plan miesiąca, Operacje).
- Stopka z ujemnym marginesem zakłada, że stopka jest ostatnim elementem okna; `TwoFactorSetupModal`, `PasskeyItem`, `DeleteUser` do sprawdzenia.

## Success Criteria (Summary)

- Każdy stan dialogu w `/dev/ui/dialogs` zgadza się z Atramentem w obu motywach i przy 390 px.
- Zła kwota w dialogu kategorii nie przechodzi po cichu; zapis pokazuje spinner i nie da się go przerwać zamknięciem.
- `check:ui` i `ui-contract.test.ts` łapią literał w dialogu kategorii i ręczne potwierdzenie na Budżecie.
