# Próba przełomu roku 2026→2027 — Plan Brief

> Full plan: `context/changes/year-turn-rehearsal/plan.md`
> Research: `context/changes/year-turn-rehearsal/research.md`

## What & Why

Pierwszy przełom roku na prawdziwych danych wypada 31.12.2026 → 1.01.2027. Aplikacja nie ma zdarzenia przełomu: start wykonania 2027 i zatwierdzenie planów grudnia i stycznia to ręczne bramki z twardą kolejnością. Pomyłka może kosztować bezpowrotnie utracony grudzień albo odrzucony Import stycznia. Zmiana przećwiczy przełom na kopii Twoich danych przed terminem, przypnie zachowanie testami i zostawi procedurę na każdy kolejny rok.

## Starting Point

Research zmapował przełom w kodzie:

- serwer działa w UTC, więc rok zmienia się o 01:00 PL;
- niezatwierdzony grudzień po 00:00 UTC 1 stycznia nie da się zatwierdzić;
- przychód i wydatek z 2027 są odrzucane, dopóki 2027 jest szkicem;
- Zebrane przechodzi przez rok samo.

Dashboard ma już stan szkicu (odznaka „Szkic”, karta „Budżet jest w szkicu”). Uruchomiona aplikacja nie ma sposobu na ustawienie „dziś”, a testy nie obejmują całej ścieżki przez przełom.

## Desired End State

Masz:

- testy przełomu na PostgreSQL;
- lokalną komendę `app:rehearsal-clock`;
- `rehearsal.md` z wynikiem każdego kroku próby na kopii produkcji;
- sekcję README „Przełom roku”, z której robisz prawdziwy przełom 30.12–2.01 bez zaglądania do archiwum.

Kod produktu się nie zmienia, chyba że próba znajdzie błąd blokujący przełom.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Metoda próby | Testy Feature + jedno przejście w przeglądarce na kopii produkcji | Prawdziwe dane łapią to, czego fabryki nie mają, a testy zostają na 2027→2028 | Plan (opcja (c) z Research) |
| Źródło danych | `user:backup` JSON z VPS → nowy użytkownik „Próba” w bazie dev | Bez `DROP SCHEMA`, bez przepinania serwera WWW, logowanie lokalnym hasłem | Plan |
| Zegar w uruchomionej aplikacji | Plik + `Date::setTestNow` tylko przy `APP_ENV=local`, komenda `app:rehearsal-clock` | Zmiana chwili bez edycji `.env` i restartu; Carbon 3.15.1 przesuwa też `CarbonImmutable` | Plan (agent, ze wzorca `/dev`) |
| Luki produktu 1–5 | Żadnej poprawki; zapis w `rehearsal.md` jako kandydaci | Stan szkicu na Dashboardzie już istnieje, a reszta to osobne decyzje (ADR-0075, strefa czasu) | Plan (po korekcie faktu o luce 1) |
| Kolejność na produkcji | (B): zatwierdź grudzień → start 2027 dnia 30–31.12 → od 1.01 import grudnia → zatwierdź styczeń | 1 stycznia nie ma okna, w którym Import albo Dashboard nie działa | Plan |
| Pułapka (B) | Bez blokady w kodzie, ostrzeżenie w README i test, który ją dokumentuje | Zatwierdzenie stycznia w grudniu bierze grudniowe opłaty stałe przed ich wpływem | Plan |
| Instrukcja | Sekcja README „Przełom roku” (N→N+1); zmiana zamyka się po próbie | Procedura służy co roku i leży obok innych operacji produkcyjnych | Plan |
| Znaleziska | Błąd blokujący → nieprzechodzący test → poprawka w tej zmianie; reszta do raportu | Zasada „najpierw dowód, potem test, potem poprawka” | Plan |

## Scope

**In scope:**

- `YearTurnTest`, a w `CsvImportTest` i `SelectedBudgetYearTest` przypadki przełomu
- `RehearsalClock`, komenda `app:rehearsal-clock` i ich testy
- dwa przejścia na kopii: (B) w krokach T0–T4 oraz „zapomniany start” w kroku T5
- `rehearsal.md`, README „Przełom roku”, dopisek w `development-directions.md`

**Out of scope:**

- powiadomienie o szkicu, zmiana roku w sesji, strefa `Europe/Warsaw`, poprawka „Lat”
- blokada zatwierdzania stycznia w grudniu, data w `StartBudgetExecution`, Year Close
- kopia całej instancji, test E2E w zestawie, banner zegara w UI
- prawdziwy przełom na produkcji (robisz go według README po zamknięciu zmiany)

## Architecture / Approach

Testy przechodzą przez `2026-12-31 23:59:59 → 2027-01-01 00:00:00 UTC` z `travelTo` na fabrykach. Zegar próby to plik `storage/app/private/rehearsal-clock`, który `AppServiceProvider` czyta tylko lokalnie. Zegar przeglądarki ustawia `playwright-cli` (`page.clock`), w kroku T2 celowo z przesunięciem UTC/PL. Próba biegnie na stacku Sail głównego checkoutu, z gałęzią zmiany i z danymi przywróconymi na osobnego użytkownika. Liczby kontrolne sprawdzają, że start 2027 nie rusza historii.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Testy przełomu | Ścieżka (B), granica grudnia, pułapka stycznia, zapomniany start, Import 29.12–02.01, rok w sesji, wszystko na PostgreSQL | Test ujawni błąd, który trzeba naprawić przed grudniem |
| 2. Lokalny zegar próby | `app:rehearsal-clock` i hak tylko w `local`, z testami | Hak działający poza `local`; pilnują go testy dla `testing` i `production` |
| 3. Próba na kopii produkcji | `rehearsal.md` z T0–T5, znaleziska, luki 1–5, sprzątanie | Dane Twojego domu w bazie dev; sprzątanie i raport bez kwot |
| 4. Instrukcja w README | „Przełom roku” N→N+1 z godzinami UTC/PL i drogami naprawy | Instrukcja niepełna bez wyników próby, dlatego jest ostatnia |

**Prerequisites:**

- dostęp SSH do VPS i `scripts/artisan.sh`;
- stack Sail z gałęzią zmiany w głównym checkoutcie;
- świeży eksport mBank albo BNP z `storage/app/private/statements/`, z datami przesuniętymi na 29.12–02.01, poza gitem.

**Estimated effort:** ~3 sesje: fazy 1–2 razem, faza 3 (ok. 1 h Twojego czasu przy ekranach) i faza 4 krótko. Termin to przed świętami, a najpóźniej 30.12.

## Open Risks & Assumptions

- Research przyjął stan produkcji z 12.11 (2026 w wykonaniu, 2027 szkic) i go nie sprawdzał. Snapshot w fazie 3 to potwierdzi.
- Przelew własny i ruch kapitału w roku-szkicu mają przejść, ale tego nie sprawdzono. Odmowa trafi do README, nie do poprawki.
- Kopia jest z listopada, więc grudzień trzeba w niej zatwierdzić na zegarze próby. Kwoty grudnia na kopii nie odpowiadają prawdziwemu grudniowi.
- Poprawka znaleziska blokującego może powiększyć fazę 3. Termin 30.12 zostawia na to miejsce.

## Success Criteria (Summary)

- Przełom 2026→2027 przeszedł na kopii Twoich danych w kolejności (B), z planem stycznia z grudnia i Zebranym przeniesionym na nowy rok, bez ręcznych poprawek.
- Każdy krok ma test albo wynik w `rehearsal.md`, a każdy błąd blokujący ma test i poprawkę.
- 30 grudnia otwierasz README „Przełom roku” i robisz przełom tylko z niej.
