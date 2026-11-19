# Ten sam dzień w menu, na Dashboardzie i w dialogach (S-39) — Plan Brief

> Full plan: `context/changes/one-app-day/plan.md`
> Reguła (pełni rolę frame): `context/foundation/adr/0103-one-app-day-in-europe-warsaw.md`
> Spis miejsc (pełni rolę research): `context/archive/2026-11-18-app-day-decision/plan.md`, „Spis miejsc w kodzie”

## What & Why

Aplikacja ma trzy zegary: serwer liczy „dziś” w UTC, menu roku bierze czas lokalny przeglądarki, a siedem dialogów dzień UTC przeglądarki. Próba przełomu (T2, luka 4) pokazała, że o 00:30 1 stycznia 2027 menu roku mówi już „2027 bieżący”, a Dashboard i domyślna data dialogu wciąż 31 grudnia 2026. S-39 wprowadza do kodu regułę z ADR-0103: jeden App Day, czyli data w Europe/Warsaw, wszędzie. To gwiazda przewodnia M-5, na której budują S-40, S-41 i S-43.

## Starting Point

Decyzja jest zapisana (ADR-0103, wpis App Day w słowniku), a spis z F-08 zgadza się z kodem na `863a422`: 20 × `today('UTC')`, 15 × `before_or_equal:today`, 4 inne odczyty zegara, 3 miejsca, gdzie `archived_at` staje się dniem, 7 dialogów i 7 innych odczytów zegara w przeglądarce. Po stronie serwera obliczenia przyjmują już `$today` jako parametr, więc zmienia się tylko miejsce odczytu. Brakuje źródła dnia i propsa dla przeglądarki.

## Desired End State

O 00:30 czasu polskiego 1 stycznia 2027 Dashboard, menu roku, paleta i domyślne daty wszystkich dialogów mówią 2027-01-01, a wydatek z wyciągu z tą datą zapisuje się, zamiast być „przyszły”. Przeglądarka nie ma własnego zegara: dzień przychodzi w `shell.today`. Gdy ktoś wpisze nowe `today('UTC')` w `app/` albo `new Date()` w `resources/js`, `composer ci:check` jest czerwone. README „Przełom roku” mówi o północy w Polsce.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Który dzień jest „dziś” | Data bieżącej chwili w Europe/Warsaw, `config/app.timezone` zostaje UTC | Znaczniki czasu zostają chwilami w UTC bez migracji | Frame (ADR-0103) |
| Przeglądarka | `shell.today` z serwera, bez `Intl` ze strefą i bez `page.clock` | Jedna implementacja reguły, zegar próby działa sam | Frame (ADR-0103) |
| Kształt wartości `AppDay` | Data z Warszawy o 00:00 w strefie aplikacji (UTC), nie północ w Warszawie | Porównania z `CarbonImmutable::create($rok, $mies, 1)` w Propozycji i w Collected działają bez zmian | Plan |
| Komunikat przy dacie z przyszłości | `before_or_equal` w `lang/pl/validation.php`: „Data nie może być w przyszłości.” | Ten sam tekst co w Imporcie, zamiast angielskiego z datą ISO | Plan |
| Strażniki zegara | Dwa testy Unit w `tests/Unit/AppDayClockGuardTest.php`: PHP (`app/`) i JS (`resources/js` bez testów) | Regresja trzeciego zegara czerwieni CI, nie zależy od tego, czy agent przeczyta regułę | Plan |
| Specy e2e z zegarem runnera | Bez zmian w S-39; trzy specy-bramki poprawia S-44 | Zgodne z planem testów (§5, §6.3), bez konfliktu z równoległym S-44 | Plan |
| Testy granic | `travelTo` 22:59:59/23:00 UTC (zima), 21:59:59/22:00 UTC (lato), dni zmiany czasu; testy północy UTC przepisane jako zmiana wymagania | Ryzyko #9 planu testów | Research (test-plan §6.2) |
| Strona otwarta przez północ | Zostaje przy swoim dniu do pełnej wizyty | Wczorajsza data jest poprawna, o przyszłej decyduje serwer | Frame (ADR-0103) |

## Scope

**In scope:**
- `App\Support\AppDay` (`today()`, `of()`) i wszystkie odczyty dnia dla domeny w `app/`
- Dzień archiwizacji Kategorii w Propozycji, Collected, liście Kategorii i `ArchiveCategory`
- 15 reguł „nie później niż dziś”, uzgodnienie salda, podgląd Importu, polski komunikat
- `shell.today` (PHP + TS + `assertInertia`), 7 dialogów, menu roku, paleta, nowy Budżet, presety filtrów, etykiety dni, logowanie, strona powitalna, kitchen sinki
- Strażniki PHP i JS, README „Przełom roku”, `.ai/rules/app-day.md`, poprawka `js-auth.md`, cookbook planu testów

**Out of scope:**
- `tests/e2e` (S-44), rok w sesji po przełomie (S-40), przypomnienia przed przełomem (S-41), „Lata” na początku stycznia (S-43)
- `config/app.timezone`, `TZ`, migracje, seedery i fabryki
- Odświeżanie dnia na stronie otwartej przez północ

## Architecture / Approach

Serwer: kontrolery, czytniki i akcje biorą `$today` z `AppDay::today()` i przekazują go dalej jak dotąd, znacznik czasu staje się dniem przez `AppDay::of()`. `ShellState` dokłada `today` do współdzielonego `shell` (także dla gościa i tras `dev/...`). Przeglądarka: komponenty czytają `useShell().value.today`, czyste funkcje w `lib/app-day.ts` wyprowadzają z niego rok i zakres miesiąca, a funkcje formatujące przyjmują dzień jako argument.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. `AppDay` i dzień na serwerze | Jedno źródło dnia, około 25 odczytów, `archived_at` jako dzień, testy na polskiej północy | Zły kształt wartości (północ w Warszawie) przesunąłby porównania miesięcy o dzień |
| 2. „Nie później niż dziś” i strażnik PHP | Operacja z datą App Day przyjmowana o 00:30, polski komunikat, CI pilnuje `app/` | Reguła tekstowa ze strefą odrzuca sam App Day |
| 3. `shell.today` i przeglądarka | Props po obu stronach, wszystkie odczyty zegara w JS zastąpione, strażnik JS | Komponent w layoucie z nieaktualnym snapshotem `shell` |
| 4. Dokumenty, reguły i próba | README, reguły agentów, cookbook, sprawdzenie w przeglądarce na zegarze próby | Próba wymaga ponownego logowania po skoku zegara |

**Prerequisites:** F-08 zamknięte (ADR-0103 i App Day w słowniku są w `development`); baza `testing_one_app_day` w kontenerze pgsql.
**Estimated effort:** ~3–4 sesje w 4 fazach; zmiany mechaniczne, najwięcej czasu zajmą testy granic i strażniki.

## Open Risks & Assumptions

- Kod wchodzący do `development` w trakcie (S-42 już jest, S-44 równolegle) może dodać nowe odczyty zegara; strażniki złapią je przy rebase przed scaleniem.
- Wdrożenie musi zdążyć przed 31.12.2026, inaczej przełom przejdzie na starych zegarach (roadmapa, Open Questions 2).
- Pięć speców e2e liczy rok z zegara runnera do czasu S-44; uruchomione między 00:00 a 01:00 czasu polskiego mogą się rozjechać z aplikacją.

## Success Criteria (Summary)

- O 00:30 czasu polskiego 1 stycznia menu roku, Dashboard i każdy dialog pokazują ten sam dzień i rok, co potwierdzają test `assertInertia` na granicach i próba w przeglądarce na zegarze próby.
- Operacja z datą App Day jest przyjmowana, a data jutrzejsza odrzucana z polskim komunikatem.
- Nowy zegar w `app/` albo `resources/js` czerwieni `composer ci:check`.
