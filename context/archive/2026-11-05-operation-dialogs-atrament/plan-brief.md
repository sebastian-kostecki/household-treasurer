# Dialogi operacji według Atramentu — Plan Brief

> Full plan: `context/changes/operation-dialogs-atrament/plan.md`
> Research: `context/changes/operation-dialogs-atrament/research.md`

## What & Why

Cztery dialogi operacji (wydatek/zwrot, dochód, transfer własny, ruch kapitału) przechodzą na kontrakt ADR-0040, który mają już dialogi Budżetu. Dochodzi pierwszy podgląd „przed → po”: Do wydania, roczny limit kategorii i saldo konta lub lokaty, z ostrzeżeniami w środku bloku. Dziś użytkownik nie widzi skutku wpisu, dopóki wynik nie spadnie poniżej zera. Może też zamknąć dialog w połowie zapisu, a literówka w kwocie po cichu zamienia się na 0.

## Starting Point

Dialogi dostały tylko nową powłokę z `category-dialog-atrament`. Pola są ręcznymi blokami bez aria, typ operacji to checkboxy, a kierunek i lokata to reka `Select`. Ostrzeżenia stoją w osobnym `Alert`, a stopka nie ma spinnera ani blokady. Biblioteki `entry-to-spend-warning.ts` i `hand-entry-warning-preview.ts` liczą wartości „przed” i „po”, ale zwracają tylko flagi ostrzeżeń. Żadnego z tych dialogów nie ma w sinku ani w strażach.

## Desired End State

Każdy dialog ma pola w `FormField` w kolejności z Atramentu: typ na górze, potem rząd „Kwota | Data”. Typ to `SegmentedControl`. Blok podglądu pokazuje wiersze typu „Do wydania w listopadzie 2026: 2 340,50 zł → 2 250,60 zł”, a pod wierszem stoi ostrzeżenie, którego dotyczy. Edycja bez zmian pokazuje równe wartości. Zła kwota daje komunikat pod polem i wyłączony „Zapisz”. Zapis pokazuje spinner i blokuje zamykanie. Każdy stan da się otworzyć w `/dev/ui/dialogs?open=<stan>`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Ostrzeżenia | Tekst `warning` w bloku podglądu, bez `Alert` (ADR-0041) | Ostrzeżenie stoi przy odczycie, którego dotyczy, jak w Atramencie. | Research |
| Odczyty | Do wydania, roczny limit, saldo konta/lokaty; Month Remaining tylko jako ostrzeżenie | Bez nowych obliczeń, tylko wystawienie wartości już liczonych. | Research |
| Typ operacji | `SegmentedControl` `block` `primary`, dialogi zostają osobne | Typ zmienia kategorię, znak i tytuł, więc musi być widoczny. | Research |
| Opis z wyciągu | Statyczny blok tekstu `.form-readonly-text` | Pole tylko do odczytu nie udaje edytowalnej kontrolki. | Research |
| „Przed” w edycji | Stan zapisany (limit 700 → 700 przy edycji bez zmian) | Jedno znaczenie dla trzech wierszy, zgodne ze stroną Budżetu. | Plan |
| Etykieta miesiąca | „Do wydania w listopadzie 2026” (12 kluczy w miejscowniku) | Brzmienie Atramentu i jednoznaczność na przejściu roku. | Plan |
| Kolejność pól | Układ Atramentu: Typ → Kwota \| Data → Kategoria → Konto → Opis… | Najpierw to, co zmienia odczyt; rząd składa się poniżej 390 px. | Plan |
| Zła kwota | Nieczytelna lub ujemna; zero odrzuca serwer | Bez błędu zaraz po otwarciu, minus nie znika po cichu. | Plan |
| Kolor wartości | `amountToneClass` (dodatnia zielona) | `design-system.md:66` wymaga tego dla salda i „do wydania”. | Plan |
| Import | `previewToSpendWarnings` bez zmian, odczyty w nowej funkcji obok | Import korzysta z tej samej funkcji. | Research |

## Scope

**In scope:**
- 4 dialogi operacji: pola, wybory, kolejność, kwota, stopka, blokada zapisu
- `FormPreview`, `lib/operation-dialog-preview.ts`, klasy `app.css`, propy aria w `AccountSelect`/`CategorySelect`
- Odczyty w `entry-to-spend-warning.ts` i `hand-entry-warning-preview.ts` z testami (pierwsze dla drugiego pliku)
- ADR-0041, `design-system.md`, `.ai/rules/js.md`, klucze `lang`, sink, `check:ui`, `ui-contract.test.ts`, zrzuty

**Out of scope:**
- Jeden dialog „Nowa operacja” (ADR-0039), `AccountFormDialog`, `TermDepositFormDialog`
- Month Remaining przed → po, saldo konta docelowego transferu, podgląd Importu
- Kod PHP, reguły serwera, payload, propsy Inertia; błąd roku w `hand-entry-month-remaining-preview.ts:435` (osobne issue)

## Architecture / Approach

Biblioteki wystawiają odczyty: `previewToSpendReadings` per miesiąc oraz salda i limit „przed/po” w `HandEntryPreviewResult`. Czyste funkcje w `operation-dialog-preview.ts` zamieniają je na wiersze z etykietami i przypiętymi ostrzeżeniami. `FormPreview` je renderuje. Dialogi łączą to z `FormField`, `SegmentedControl` i wzorcem stopki oraz `form?` z `CategoryFormDialog`. Sink karmi dialogi fixture'ami liczonymi względem dzisiejszej daty.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kontrakt w dokumentach i CSS | ADR-0041, strony systemu, klasy podglądu, rzędu i tekstu | Kontrast `warning` na `background` |
| 2. Odczyty w bibliotekach | `previewToSpendReadings`, salda i limit przed/po, testy | Zmiana wyniku dla Importu |
| 3. Wspólne elementy | `FormPreview`, pomocnik wierszy, aria w selectach, `lang`, fixture'y | Fixture'y bez wiersza Do wydania (data) |
| 4. Wydatek i dochód | Pełny kontrakt i podgląd, stany w sinku | Konwersje zwrot ↔ dochód w payloadzie |
| 5. Transfer, kapitał, straże, zrzuty | Pełny kontrakt, `check:ui`, test kontraktu, reguła, zrzuty | `native-select` lokaty i `name="direction"` |

**Prerequisites:** kroki „Fresh checkout or worktree” z AGENTS.md (composer, `.env`, `npm ci`, `npm run build`), uruchomiony Sail (sieć `household-treasurer_sail`) i podgląd z worktree do kroków ręcznych.
**Estimated effort:** ~3–4 sesje w 5 fazach.

## Open Risks & Assumptions

- `text-warning` może nie mieć kontrastu 4,5:1 na `background` (zmierzony był na `card`). Wtedy potrzebny jest token, co rozszerza Fazę 1.
- Rząd dwóch pól przy 390 px zakłada, że pole daty mieści się w około 155 px. Sprawdzają to zrzuty.
- `UiTranslations.operations` to `Record<string, string>`, więc brakujący klucz nie wyjdzie w `vue-tsc`. To ryzyko łapią stany sinka.

## Success Criteria (Summary)

- Przed zapisem użytkownik widzi, jak wpis zmieni Do wydania, limit i saldo, a ostrzeżenie stoi przy odczycie.
- Nie da się zamknąć dialogu w trakcie zapisu, a zła kwota daje komunikat zamiast cichego zera.
- Każdy stan 4 dialogów jest w sinku i na zrzutach, a CI pilnuje literałów i braku `Alert` oraz checkboxów.
