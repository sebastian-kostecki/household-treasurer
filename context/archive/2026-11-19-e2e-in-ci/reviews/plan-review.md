<!-- PLAN-REVIEW-REPORT -->
# Plan Review: E2E w CI — plan implementacji

- **Plan**: context/changes/e2e-in-ci/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: SOUND
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

9/9 paths ✓, 8/8 symbols ✓ (`openYearMenu` jest prywatny w `tests/e2e/support/budget-year.ts:12`, ale nowy pomocnik powstaje w tym samym pliku), brief↔plan ✓, Progress↔Fazy ✓.

Twierdzenia potwierdzone w kodzie:
- etykiety Importu („Zaznacz wiersz: …”, „Kategoria / konto: …”, „Zatwierdź import”) i toast „2 operacje zaimportowane.”;
- przekierowanie na `/operations` i filtr `q`;
- brak reguły odrzucającej `<rok>-01-01`: walidacja sprawdza otwarcie rachunku (`<`), `before_or_equal:today` i Execution budżetu;
- nagłówek domyślnie włączony;
- tagi w `grep`/`grepInvert` projektu oraz `--no-deps` w Playwright 1.65.0;
- strefa `UTC`;
- `manifest.json` w `public/build/`.

Specy-bramki są powtarzalne. Każdy przebieg `month-plan-groups` i `irregular-collected` zostawia jedną zarchiwizowaną kategorię `E2E …`, ale nie łamie to kolejnych rund.

## Findings

### F1 — Nowy skrypt serwera CI uruchomi się pierwszy raz dopiero na GitHubie

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 4 §2 (`scripts/e2e-server-ci.sh`), §4 (próba w trybie CI)
- **Detail**: `webServer` wybiera `e2e-server-ci.sh` tylko przy `GITHUB_ACTIONS`, więc próba z `CI=1` idzie przez `e2e-server.sh` w Sail. Nowy skrypt nie jest wykonany przed pushem `development` (105 commitów). Dotyczy to kontroli `public/hot` i manifestu, `php -S` z routerem, bazy ze zmiennych joba oraz `.env` z `.env.example`. Błąd w skrypcie dałby czerwony job `e2e` na tym pushu.
- **Fix**: Smoke skryptu w obrazie `sail-8.5/app` na sieci Sail, ze zmiennymi bazy jak w jobie: `curl` na `/login` i na plik z manifestu w tym samym kontenerze oraz sprawdzenie błędu przy `public/hot`. Nowy krok Progress 4.6.
  - Strength: Sprawdza wszystko, co nowe w skrypcie, bez publikowania czegokolwiek.
  - Tradeoff: Nie sprawdza Chromium ani usługi `pgsql` runnera (zostają w 5.4).
  - Confidence: HIGH — `e2e-server.sh` działa w tym samym obrazie na tej samej zasadzie.
  - Blind spot: rozszerzenia PHP z `setup-php` mogą się różnić od obrazu Sail.
- **Decision**: FIXED (Fix in plan — Faza 4 §4 krok 5, kryterium i Progress 4.6)

### F2 — Gałąź z celowym zepsuciem nie uruchomi workflow

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 5, Manual Verification 5.5; Manual Testing Steps 2
- **Detail**: `tests.yml` startuje na push tylko do `main` i `development` oraz na `pull_request` (`:3-9`), a plan nie zmienia wyzwalaczy. Sam push tymczasowej gałęzi nie uruchomi joba `e2e`.
- **Fix**: Tymczasowa gałąź + draft PR do `development`. Push i PR robi użytkownik po pushu `development`, a po sprawdzeniu PR jest zamknięty i gałąź usunięta.
- **Decision**: FIXED (Fix in plan — 5.5, Manual Testing Steps 2, Progress 5.5)

### F3 — test-stack.md: sekcja E2E należy do /10x-e2e-setup

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 5 §2
- **Detail**: `## E2E` w `test-stack.md` ma komentarz „Re-run it to change this section; other skills only read it”. Plan dopuszczał dopisanie faktów CI w tej sekcji, a ponowne `/10x-e2e-setup` mogłoby je nadpisać.
- **Fix**: Fakty CI w osobnej sekcji `## E2E w CI`. W `## E2E` zmieniają się tylko opis `seed` przy #7 (wymóg `test-plan.md:90`) i `updated:`.
- **Decision**: FIXED (Fix in plan — Faza 5 §2 Contract)
