# S-05: Tempo wydatków — Plan Brief

> Full plan: `context/changes/month-spending-pace/plan.md`
> Research: `context/changes/month-spending-pace/research.md`

## What & Why

Użytkownik ma zobaczyć, czy wydatki „bieżące” idą szybciej niż dni miesiąca (PRD FR-007). Fałszywe ostrzeżenia uczą ignorowania sygnału, dlatego tempo obejmuje tylko pozycje „bieżące” bez kategorii jednorazowych. Ostrzeżenie pojawia się dopiero przy wyprzedzeniu dni o co najmniej 10 pp.

## Starting Point

Kodu tempa nie ma. Definicje Month Pace i Month Elapsed są już w `CONTEXT.md`. Strona „Plan miesiąca” liczy „do wydania” z jednego źródła wierszy (`MonthToSpend`) i ma podgląd na żywo z kopii TS kalkulatora. `YearElapsed` daje gotowy wzorzec daty odniesienia.

## Desired End State

Pod liczbą „do wydania” na stronie „Plan miesiąca” jest pasek tempa ze znacznikiem upływu dni i tekst, np. „Tempo 59% · upływ dni 15 z 30 (50%)”. Przy wyprzedzeniu o ≥ 10 pp odczyt jest bursztynowy i ma podpis. Podczas edycji propozycji lub zmiany planu tempo reaguje na wpisywane kwoty. Serwer wysyła odczyt z predykatem `is_ahead`, który S-07 przejmie do powiadomienia Dashboardu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Definicja puli i wzoru | Wydatek ∧ Running ∧ nie One-off; Σ faktycznych / Σ planów; brak odczytu przy planie 0 | Tak brzmi `CONTEXT.md`, a F-01 potwierdził jedną pulę | Research |
| Miejsce | Tylko strona „Plan miesiąca” | S-01 zbudował stronę pod S-05; spec Dashboardu nie przewiduje kafelka tempa | Plan |
| Forma | Pasek ze znacznikiem dni i tekst z procentami | Lukę widać na pierwszy rzut oka, a tekst niesie informację bez koloru | Plan |
| Sygnał na stronie | Kolor `warning` i podpis przy Pace − Elapsed ≥ 10 pp | Spełnia „ostrzeżenie” z FR-007 tam, gdzie jest odczyt, i ustala predykat raz dla S-07 | Plan |
| Skład puli | Wszystkie wiersze „bieżące” miesiąca, także zarchiwizowane i z planem 0 | Dosłowna definicja i spójność z „do wydania”; nieplanowane wydatki nie znikają z tempa | Plan |
| Podgląd na żywo | Para PHP/TS `MonthPaceCalculator` z testami w obu miejscach | Propozycja jest zawsze edytowalna, więc tempo z samego serwera stałoby nieaktualne obok żywego „do wydania” | Plan |
| Miesiące | Bieżący i miniony z planem; przyszły ukryty | Przyszłe tempo to zawsze „0% · 1 z n dni”; końcowe tempo minionego to realna informacja do S-09 | Plan |
| Zaokrąglenie a próg | Próg na liczbach całkowitych przez mnożenie krzyżowe, zaokrąglenie tylko do wyświetlania | Bez tego surowe 39,6% wyświetlone jako 40% dałoby fałszywe ostrzeżenie | Plan |

## Scope

**In scope:**
- `MonthElapsed` (data odniesienia jak `YearElapsed`)
- `MonthPaceCalculator` w parze PHP/TS z testami
- odczyt `MonthPace` z `MonthToSpend`
- prop `monthPlan.month_pace` i typ TS
- komponent `MonthPaceMeter`
- podgląd na żywo
- teksty PL
- wpisy w `AGENTS.md` (czwarta para) i `CONTEXT.md` (kopia „upływ dni”)

**Out of scope:**
- tempo na Dashboardzie i stronie Budżet
- powiadomienie `warning` w `DashboardNotices` (S-07)
- tempo per kategoria
- tempo przyszłych miesięcy
- kolor `destructive` dla tempa > 100%
- refaktor zduplikowanej klasyfikacji miesiąca
- migracje i snapshot

## Architecture / Approach

`MonthPace::forMonth(MonthToSpend, rok, miesiąc, dziś)` bierze wiersze i kwoty z `MonthToSpend::rowAmounts` (te same co „do wydania”) oraz dzień i liczbę dni z `MonthElapsed`. Następnie woła czysty `MonthPaceCalculator`: sumy puli, procent do wyświetlania, predykat `10·actual·dni − 10·dzień·plan ≥ plan·dni`. Kontroler wysyła wynik jako `monthPlan.month_pace` (dla przyszłego miesiąca `null`). `MonthPlan.vue` poza edycją pokazuje wartości z serwera, a w edycji przelicza je kopią TS z wpisanych kwot i upływu dni z propsa.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Odczyt tempa na serwerze i wzór w parze PHP/TS | `MonthElapsed`, kalkulator PHP i TS z testami, `MonthPace`, prop `month_pace` z `assertInertia` | Rozjazd kopii przy zaokrągleniu ujemnych połówek (PHP `round` vs JS `Math.round`) |
| 2. Tempo na stronie „Plan miesiąca” | Pasek, znacznik, tekst, `warning` z podpisem, podgląd na żywo, teksty, dokumenty, sprawdzenie w podglądzie | Odczyt zagęszcza kartę „do wydania” na wąskim ekranie |

**Prerequisites:**
- S-04 jest zrobiony.
- Worktree przygotowano według `AGENTS.md` „Fresh checkout or worktree” (`composer install`, `.env`, `key:generate`, `npm ci`, `npm run build`).
- Sail działa (PostgreSQL dla testów i podglądu).

**Estimated effort:** ~1–2 sesje w 2 fazach.

## Open Risks & Assumptions

- „Plan” w puli oznacza Current Month Plan albo propozycję przed zatwierdzeniem, jak w To Spend. To wniosek z `CONTEXT.md:64,80`, bo wpis Month Pace mówi tylko „plan amounts”.
- Wydatek na pozycji bieżącej z planem 0 podnosi tempo. Wiersz ma wtedy też sygnał „ponad plan”, więc użytkownik dostaje dwa spójne sygnały, a nie sprzeczne.
- Iloczyny predykatu w TS mieszczą się w `Number.MAX_SAFE_INTEGER` dla realnych kwot domowych; `BigInt` nie jest dodawany.

## Success Criteria (Summary)

- Na stronie „Plan miesiąca” bieżącego miesiąca widać tempo względem upływu dni, a jednorazowe wydatki, rachunki i dochody go nie zmieniają.
- Ostrzeżenie (bursztyn i podpis) pojawia się dokładnie przy ≥ 10 pp, zgodnie z przykładem F-01 (dzień 9 tak, dzień 10 nie). Podczas planowania reaguje na wpisywane kwoty.
- S-07 może zbudować powiadomienie z `MonthPace::forMonth` i `is_ahead` bez nowej definicji.
