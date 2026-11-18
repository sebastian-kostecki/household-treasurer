# Jeden dzień aplikacji (App Day) — Plan Brief

> Full plan: `context/changes/app-day-decision/plan.md`

## What & Why

Aplikacja ma trzy różne „dziś”: serwer liczy w UTC, menu roku bierze rok z lokalnego zegara przeglądarki, a dialogi datę UTC przeglądarki. Próba przełomu roku (T2, luka 4) pokazała, że o 00:30 1 stycznia czasu polskiego menu mówi 2027, a Dashboard i dialog jeszcze 31 grudnia 2026. Ta zmiana (F-08, MS-01) zapisuje w ADR i słowniku jedną odpowiedź, zanim cztery wycinki strumienia A (S-39, S-40, S-41, S-43) rozstrzygną północ każdy po swojemu.

## Starting Point

Serwer: `config/app.php:68` `'timezone' => 'UTC'`, 20 × `today('UTC')`, 15 × `before_or_equal:today` i 4 odczyty niezgodne z resztą. Przeglądarka: 7 dialogów z `toISOString().slice(0, 10)` i 5 miejsc z czasem lokalnym, bez wspólnego helpera i bez propsa „dziś”. Sześć ADR ustala „dziś w UTC” osobno (0074, 0075, 0076, 0077, 0081, 0097), ADR-0098 bierze rok z `new Date().getFullYear()`, słownik nie definiuje „today”.

## Desired End State

ADR-0103 i wpis **App Day** w słowniku mówią: „dziś” to data w Europe/Warsaw, liczona na serwerze w jednym miejscu i wysyłana do przeglądarki w `shell.today`. Siedem starszych ADR ma linię „Superseded in part by ADR-0103”. Pytanie „który dzień jest dziś 31.12.2026 o 23:30 UTC” ma jedną odpowiedź w dokumentach: 2027-01-01, wszędzie. Kod zmienia S-39 według spisu miejsc w planie.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Który dzień jest „dziś” | Data w Europe/Warsaw | Zgadza się z datami na wyciągach i z zegarkiem: płatność o 00:20 1.01 nie jest „przyszła”, termin grudnia to północ czasu polskiego. | Plan |
| Liczenie na serwerze | Osobne źródło `App\Support\AppDay`, `config/app.timezone` zostaje UTC | Zapisane `timestamp without time zone` nie zmieniają znaczenia, a S-39 ma jedno miejsce do testu (DST, zegar próby). | Plan |
| Dzień w przeglądarce | Z serwera w `shell.today` (`YYYY-MM-DD`) | Jedna reguła w PHP; zegar próby i zły zegar urządzenia nie rozjeżdżają UI z serwerem. | Plan |
| Strona otwarta przez północ | Zostaje przy swoim dniu do następnej wizyty | Wczorajsza data jest poprawna, o „przyszłej” decyduje walidacja serwera. | Plan (konsekwencja wyboru `shell.today`) |
| Strony gościa | Logowanie i strona powitalna zostają przy zegarze przeglądarki | Kosmetyczne, bez `shell`. | Plan |
| Starsze ADR | Linia „Superseded in part by ADR-0103” po pierwszym akapicie | Konwencja z ADR-0003 i ADR-0016. | Repo |
| README i testy północy UTC | Zmieniają się w S-39 z kodem | Opisują działające zachowanie; zmiana teraz byłaby nieprawdą. | Plan |

## Scope

**In scope:**

- `context/foundation/adr/0103-one-app-day-in-europe-warsaw.md`
- wpis App Day i dwie zamiany „(UTC)” w `context/foundation/glossary.md`
- linie „Superseded in part” w ADR 0074, 0075, 0076, 0077, 0081, 0097, 0098
- spis miejsc w kodzie dla S-39 (w `plan.md`)

**Out of scope:**

- kod, konfiguracja, Docker, testy, README „Przełom roku” (S-39)
- reguła `.ai/rules` przeciw zegarowi w domenie (S-39)
- luki 2, 3, 5 (S-40, S-41, S-43), zdublowany ADR 0008 (S-46)
- edycja `roadmap.md` (F-08 jest tylko w niezacommitowanej kopii w głównym checkoucie)

## Architecture / Approach

Reguła: App Day = data bieżącej chwili w Europe/Warsaw. Serwer: `AppDay::today()` w `app/Support`, a kontrolery przekazują `$today` do istniejących obliczeń jak dziś; `before_or_equal:today` porównuje z datą App Day; znaczniki czasu zostają chwilami w UTC. Przeglądarka: `shell.today` zasila domyślne daty 7 dialogów, „bieżący” w menu roku, paletę, dialog nowego Budżetu, presety miesiąca i etykiety dni. Zegar próby ustawia chwilę, a App Day i UI idą za nią.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0103 i słownik | Decyzja i termin App Day | ADR za ogólny dla S-39 (brak kontraktu `AppDay`/`shell.today`) albo za szczegółowy (implementacja w ADR) |
| 2. Starsze ADR i spis dla S-39 | 7 linii „Superseded in part”, sprawdzony spis miejsc | Spis nieaktualny po merge'ach do `development` przed S-39 |

**Prerequisites:** brak; F-08 nie czeka na nic.
**Estimated effort:** jedna krótka sesja, dwie fazy, tylko dokumenty.

## Open Risks & Assumptions

- Dokumenty wyprzedzają kod: do wdrożenia S-39 słownik mówi Europe/Warsaw, a aplikacja liczy w UTC. AGENTS.md na to pozwala („Docs describe more than the code”), a ADR mówi wprost, że README i testy idą z S-39.
- S-39 musi zdążyć przed 31 grudnia 2026; inaczej przełom 2026→2027 przebiega według README (UTC, 01:00 czasu polskiego).
- Nazwa `App\Support\AppDay` i props `shell.today` to kontrakt dla S-39; zmiana nazwy w S-39 wymaga poprawki ADR-0103.

## Success Criteria (Summary)

- O 00:30 1 stycznia czasu polskiego dokumenty dają jedną odpowiedź: 1 stycznia na serwerze, w menu roku, na Dashboardzie i w dialogach.
- Żaden ADR ani wpis słownika nie ustala „dziś” w UTC bez wskazania na ADR-0103.
- `/10x-plan one-app-day` może planować na spisie miejsc bez ponownego przeszukiwania kodu.
