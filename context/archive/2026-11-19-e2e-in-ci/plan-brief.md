# E2E w CI — Plan Brief

> Full plan: `context/changes/e2e-in-ci/plan.md`

## What & Why

Ryzyko #7 planu testów: krytyczny przepływ w przeglądarce (zatwierdzenie Importu, dialog operacji, plan miesiąca) psuje się po merge'u, a CI jest zielone, bo 15 speców Playwright działa tylko lokalnie. S-44 dokłada do CI bramkę z pięciu stabilnych speców, bez powtórek. Bramka musi być pewna, bo w grudniu i styczniu przez CI przechodzą poprawki przełomu roku, a flaky spec zatrzymałby wdrożenia.

## Starting Point

- **CI.** `tests.yml` ma jeden job `ci` (PHP 8.5, PostgreSQL, `composer ci:check`, ok. 3 min) i nie zna Playwrighta.
- **Serwer E2E.** Działa tylko w obrazie Sail na lokalnej sieci dockera.
- **Konto testowe.** Istnieje tylko w lokalnej bazie E2E.
- **Config.** `retries: 2` w CI, czego plan testów zabrania w bramce.
- **Specy.** Trzy z czterech istniejących bramek biorą rok z zegara runnera. Dwie nie sprawdzają błędów JS. `import-other-format-without-header` flakuje mniej więcej co 10. przebieg (stamp `Date.now()` przechodzi test Luhna i serwer wycina go jako numer karty). Speca zatwierdzenia Importu nie ma.
- **Seeder demo.** W dniach 1–14 stycznia tworzy operacje z datą 15 stycznia, czyli w przyszłości.

## Desired End State

- Każdy push i PR uruchamia obok `ci` job `e2e`. Job seeduje świeżą bazę, loguje się kontem `e2e@example.test` i uruchamia pięć speców `@gate` bez powtórek.
- Porażka zostawia artefakt z raportem HTML i trace.
- Każda bramka czerwienieje przy celowym zepsuciu swojego przepływu albo przy błędzie JS.
- Lokalnie nic się nie zmienia poza `npm run e2e:gate`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Lista bramek | `seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected`, nowy `import-approve` | Wybrane w odświeżeniu planu testów jako przepływy z ryzyka #7. | test-plan §5 (F-09) |
| Powtórki | `retries: 0`, trace `retain-on-failure`, bez `failOnFlakyTests` | Powtórka maskuje flake'a; spec, który flakuje, wychodzi z bramki. | test-plan §5 (F-09) |
| Konto w CI | Jawnie w workflow, `demo:seed-household e2e@example.test` na świeżej bazie | Baza żyje kilka minut, a sekrety nie docierają do PR Dependabota i forków. | Plan (wywiad) |
| Układ | Osobny job `e2e` równolegle do `ci` | Osobny check, wynik niezależny od `ci:check`, krótszy czas do wyniku. | Plan (wywiad) |
| Okno 1–14 stycznia | Seeder bez dat po dziś, test Feature z `travelTo` na 2027-01-01 i 2027-01-05 | Usuwa przyczynę, zamiast liczyć, że przyszłe dane nie złamią bramki w styczniu. | Plan (wywiad, z korektą) |
| Dowód styczniowy | Test Feature zamiast przebiegu bramki z zegarem próby | Zegar próby przestawia tylko serwer, więc przebieg testowałby S-39, a nie seeder. | Plan (korekta w rozmowie) |
| Dowód na GitHubie | `actionlint` + próba `CI=1` lokalnie (20 rund); przebieg GitHub ręcznie po pushu użytkownika | Lokalny `development` ma 105 niewypchniętych commitów, więc nic nie publikujemy bez zgody. | Plan (wywiad) |
| Rok w specach | Najnowszy rok bez „szkic” z menu roku | S-39 (`shell.today`) nie istnieje; po świeżym seedzie to rok serwera. | Plan (research) |
| Wybór bramek | Tag `@gate` w specu, projekt `gate` z `grep`, `chromium` z `grepInvert` | Lista widoczna obok kodu, a pełny przebieg lokalny uruchamia każdy spec raz. | Plan (research) |

## Scope

**In scope:**
- poprawka seedera z testem;
- §6.3 w czterech specach (rok z menu, `pageerror`, stamp base36, `@gate`) i celowe zepsucia;
- spec `import-approve`;
- tryb CI w `playwright.config.ts`, `scripts/e2e-server-ci.sh`, `npm run e2e:gate`;
- job `e2e` w `tests.yml`;
- `test-stack.md` oraz `test-plan.md` (§4, §5, §6.3, §6.6).

**Out of scope:**
- pozostałe 11 speców w CI;
- powtórki;
- ochrona gałęzi i wymagany check;
- zmiana wyzwalaczy;
- cache przeglądarek;
- `demo:seed-previous-year` w CI;
- S-39 (data w dialogach z zegara przeglądarki);
- push gałęzi i draft PR;
- status §3 planu testów (orkiestrator).

## Architecture / Approach

```
push / PR ─┬─ ci   (sloppy, ci:check)                                   ~3:15
           └─ e2e  pgsql → migrate → demo:seed-household e2e@example.test
                   → npm run build → playwright install chromium
                   → npm run e2e:gate  (setup → 5× @gate, retries 0)
                        webServer: scripts/e2e-server-ci.sh  (php -S, public/, server.php)
                   → przy porażce: artefakt playwright-report/ + test-results/
```

Lokalnie `webServer` dalej używa `scripts/e2e-server.sh` (Sail), bo skrypt runnera wybiera się po `GITHUB_ACTIONS`, a nie po `CI`. Dzięki temu próba z `CI=1` ma semantykę CI na serwerze z PHP 8.5.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Seeder bez dat po dziś | Test 1 i 5 stycznia 2027 (czerwony → zielony), fallback na dziś | Inne testy seedera zależne od dat 15 stycznia |
| 2. Specy-bramki zgodne z §6.3 | Rok z menu, `pageerror`, stamp base36, `@gate`, dowody zepsuć | Pomocnik roku w lokalnej bazie z wieloma latami |
| 3. Spec zatwierdzenia Importu | `import-approve.spec.ts` + wzorzec w §6.3 | Podpowiedź kategorii albo para przelewu zmienia wiersz |
| 4. Tryb CI Playwrighta | Projekt `gate`, `retries: 0`, serwer runnera, 20 rund stabilności | Flake ujawniony dopiero w powtórkach |
| 5. Job `e2e` i dokumenty | `tests.yml`, `actionlint`, `test-stack.md`, `test-plan.md` | Specyfika runnera sprawdzona dopiero po pushu |

**Prerequisites:**
- działający stos Sail z bazą `household_treasurer_e2e` (szablon dla `e2e_e2e_in_ci`);
- `E2E_USERNAME` i `E2E_PASSWORD` w `.env` głównego checkoutu;
- linuksowy Node 24 i shim `php` w powłoce agenta;
- dla kroków ręcznych push `development` przez użytkownika.

**Estimated effort:** ok. 2–3 sesje, z czego najwięcej zajmą fazy 2 i 4 (zepsucia, 20 rund).

## Open Risks & Assumptions

- **Runner sprawdzony dopiero po pushu.** Chromium, `php -S` na hoście runnera i usługa `pgsql` przejdą przez prawdziwy GitHub dopiero po pushu `development`, więc pierwsza porażka może wyjść wtedy.
- **Północ UTC w trakcie joba.** Seed zrobiony 31.12 przed 00:00 UTC i bramki po północy rozjechałyby rok seedu z datą dialogu. Okno trwa kilka minut, ryzyko przyjęte.
- **S-39 może zmienić źródło roku.** Gdy powstanie `shell.today`, pomocnik roku może przejść na rok z serwera. Do tego czasu menu bez „szkic” jest jedynym źródłem bez zegara runnera.
- **Bramka może się skurczyć.** Jeśli 20 rund ujawni flake'a bez czystej poprawki, spec traci `@gate` i bramka ma mniej niż pięć speców; §5 i issue to odnotowują.

## Success Criteria (Summary)

- Zepsuty przepływ z listy bramek daje czerwony job `e2e` na GitHubie, z raportem i trace w artefakcie.
- Bramka przechodzi 20 rund z rzędu na świeżej bazie bez powtórek, także w styczniowym oknie seedera (dowiedzionym testem).
- Plan testów i stos testów opisują bramkę taką, jaka jest w repo.
