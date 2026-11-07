# month-plan-match-actual — Plan Brief

> Full plan: `context/changes/month-plan-match-actual/plan.md`
> Research: `context/changes/month-plan-match-actual/research.md`

## What & Why

W trybie zmian zatwierdzonego Planu miesiąca ma być łatwo ustawić plan pozycji na obecną sumę wydatków. Pozycje wydatków ponad plan startują z planem równym wydatkom, w wierszu jest przycisk „Ustaw plan na wydatki”, a ikona potwierdza podniesienie. Nic nie zapisuje się samo. Zmienia to znaczenie sygnału „pozycja ponad plan”, więc najpierw glosariusz i ADR-0062.

## Starting Point

Tryb zmian (`MonthPlan.vue`) trzyma kwoty jako tekst w `amountInputs`. Z nich, przez `parsedAmount`, liczą się To Spend, tempo, odchylenie, status, strzałka z ADR-0060 i wysyłka. W wierszu nie ma przycisku ani znacznika. Serwer przyjmuje dowolną kwotę 0…1e12 i nie porównuje jej z wydatkami.

## Desired End State

Po „Zmień” wiersz wydatku z wydatkami ponad plan ma w polu kwotę wydatków, a w komórce „Faktycznie” neutralną ikonę `Equal` z tooltipem „Plan podniesiony do wydatków”. „Zapisz zmiany” jest od razu aktywne. Po ręcznym obniżeniu (albo w kandydacie do dołączenia) w tym samym miejscu jest przycisk, który wpisuje wydatki i przenosi fokus na pole. „Anuluj” zrzuca podniesienia, a zapis zmienia tylko Bieżący plan.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Punkt zaczepienia | Wpisanie kwoty do `amountInputs` w `startChanging` | Jedno miejsce zasila wszystkie podglądy i wysyłkę bez zmian w kalkulatorach | Research |
| Serwer | Bez zmian (bez propsa, migracji, `DashboardNotices`) | Walidacja już dopuszcza każdą kwotę, a pola są w propsach | Research |
| Zakres stanów | Tylko tryb zmian planu zatwierdzonego | Zgodne ze zleceniem; nie rusza reguły propozycji z ADR-0033 | Plan |
| Kandydaci do dołączenia | Tylko przycisk, bez automatu | Automat nie może po cichu dołączać kategorii do planu | Plan |
| Kierunek przycisku | Tylko w górę: widoczny, gdy wydatki > kwota w polu | Jedno znaczenie; nie obniża planu Running w środku miesiąca; wydatki ujemne nie dają przycisku | Plan |
| Dashboard i Podsumowanie | Akceptujemy: zapisany podniesiony plan gasi `month_item_over_plan` i zeruje odchylenie | Podniesienie jest świadomym nowym planem; ślad zostaje w strzałce względem Zatwierdzonego | Plan |
| Znacznik | Ze stanu pola (pole = wydatki > zapisany plan), `Equal` w `muted-foreground` | Bez nowego stanu i bez nowego znaczenia koloru; ton niesie strzałka | Plan |
| Miejsce w wierszu | Komórka „Faktycznie”, przed kwotą; poniżej `xl` w linii podpisu | Nie ściska pola planu (ADR-0060), powtarza wzorzec strzałki | Plan |
| Dochody, odkładanie | Poza zakresem | „Ponad plan” dotyczy tylko wydatku | Research |
| Testy | Czysta funkcja w `lib` z testami + kitchen sink; bez E2E | Testy JS obejmują tylko `lib`; brak danych E2E z przekroczeniem | Plan |

## Scope

**In scope:**
- Glosariusz („Current Month Plan”, „To Spend”) i ADR-0062
- `lib/month-plan-match-actual.ts` z testami
- Podłączenie w `MonthPlan.vue` (`startChanging`, `matchActual`, `rowState.matchActual`)
- Komponent `MonthPlanMatchActual.vue`, emity w wierszu i tabeli, klasa w `app.css`, teksty w `lang/pl/ui.php`
- Listy stróżujące, kitchen sink `/dev/month-plan`, wpis w `design-system.md`

**Out of scope:**
- Propozycja, dochody, odkładanie, automat dla kandydatów, obniżanie jednym kliknięciem
- Zmiany serwera, zapis śladu podniesienia, „ponad plan” względem Zatwierdzonego
- Nowy kolor, podsumowanie podniesień w pasku edycji, ponowne podnoszenie przy późnych wydatkach
- E2E i ochrona przed opuszczeniem strony

## Architecture / Approach

Reguła z ADR-0062 żyje w jednej czystej funkcji: kwoty startowe trybu zmian, stan `'offer' | 'raised' | null`. Strona woła ją w `startChanging` (kwoty w polach) i w `rowState` (stan wiersza). `MonthPlanTable` przekazuje stan i emit `match-actual`, a `MonthPlanItemRow` renderuje `MonthPlanMatchActual` w komórce „Faktycznie” i po kliknięciu przenosi fokus na pole. Kalkulatory, serwer i propsy się nie zmieniają.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Glosariusz i ADR-0062 | Reguła i jej skutki zapisane przed kodem | Brzmienie wymaga akceptacji użytkownika |
| 2. Reguła w lib i strona | Podniesienie działa w polach i podglądach; stan w `rowState` | Kandydaci są w `planRows` przy `startChanging`, a funkcja nie może ich podnieść |
| 3. Przycisk i znacznik | UI w „Faktycznie”, sink, listy stróżujące, design system | Miejsce przy 390 px i pole trafienia ≥ 24 px bez podnoszenia wiersza |

**Prerequisites:** zmiana `ui-fixes-2` zamknięta (ADR-0060, kolumna „Zatwierdzony” na `development`); Sail do bramki.
**Estimated effort:** ~2 sesje w 3 fazach (faza 1 krótka, fazy 2–3 po jednej sesji).

## Open Risks & Assumptions

- Szerokość komórki „Faktycznie” przy 390 px to rachunek, nie pomiar; potwierdza ją ręczna weryfikacja fazy 3.
- Wejście w tryb zmian z podniesieniami od razu czyni formularz „brudnym”; strona nie ma ochrony przed opuszczeniem, a zmiana jej nie dodaje.
- Wydatki dochodzące w trakcie trybu zmian (inna karta) nie podnoszą planu ponownie; trzeba wyjść i wejść ponownie.

## Success Criteria (Summary)

- W trybie zmian pozycje wydatków ponad plan mają plan równy wydatkom i widoczny znacznik, zanim użytkownik cokolwiek zapisze.
- Jedno kliknięcie (myszą lub klawiaturą) podnosi plan do wydatków tam, gdzie jest niższy, a „Anuluj” wszystko cofa.
- Po zapisie podniesiona pozycja nie jest „ponad plan”, Zatwierdzony plan się nie zmienia, a bramka `composer ci:check` przechodzi.
