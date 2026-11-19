# Refresh brief — `context/foundation/test-plan.md`

> Wynik `/10x-test-plan --refresh` z 2026-11-19 (Fazy 1+2 od nowa). Wejście dla `/10x-research` i `/10x-plan` tej zmiany. Roadmap F-09 (MS-06), odblokowuje S-44 `e2e-in-ci`.
> Zasada #3 planu obowiązuje: brief niesie dowody i intencję odpowiedzi, nie punkty odniesienia w kodzie. Research je ugruntowuje.

## 1. Wejścia (Faza 1)

| Źródło | Typ | Sedno |
|---|---|---|
| `context/foundation/test-plan.md` (2026-11-17) | obecny przewodnik | 8 ryzyk, 5 etapów; etap 1 w §3 nadal `change opened` |
| `context/archive/2026-11-17-testing-import-integrity/` | zarchiwizowany etap 1 | Progress 29/29; luki B i C jako issue #271, #272 (oba OPEN 2026-11-19) |
| `context/foundation/roadmap.md` M-5 (na 2026-11-19 niezacommitowany w głównym checkoucie) | roadmapa | F-09: etap 1 zamknięty z odnośnikiem, etapy 2–5 bez M-2, miejsce na testy przełomu roku, lista przepływów E2E-bramek CI; Parked: pozostałe etapy, w M-5 tylko E2E w CI (S-44) |
| `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` | próba przełomu | luki 1–5 (trzy „dzisiaj”: serwer UTC, menu roku lokalnie, dialogi UTC przeglądarki), dwie przypięte testami `_known_gap` |
| `context/foundation/test-stack.md` (2026-11-06) | E2E setup | Playwright 1.65, serwer z obrazu Sail, baza `household_treasurer_e2e` z `demo:seed-household`, jeden worker, dane logowania z `.env` |
| `.github/workflows/tests.yml` | CI | audit, Sloppy, migracje, build, `composer ci:check` na PG 18; brak kroku Playwright |
| `composer.json`, `package.json` | stos | Pest 5.3.5, Larastan 3.9, vite-plus 1.6.0, @playwright/test ^1.65.0 |

Profil bazy testów: **meaningful** — 175 plików testów PHP (110 Feature, 43 Unit, reszta w Auth/Migrations/Nfr/Settings), 63 `*.test.ts`, 15 speców Playwright w `tests/e2e/`, 44 pliki testów zamrażają czas, zamrożone snapshoty v5, v6, v8, v9.

Hot-spoty (zakres jak 2026-11-06, przyjęty przez użytkownika): `app/`, `resources/js/` bez `actions/`, `routes/`, `components/ui`, plus `routes/`, `database/migrations/`; 373 commity 2026-10-20 → 2026-11-19 (było 126). Katalogi: `resources/js/components` 550 zmian plików, `resources/js/lib` 425, `resources/js/pages` 340, `app/Support` 217, `app/Http/Controllers` 136, `app/Actions` 112, `app/Support/CsvImport` 48.

**Stack grounding tools (current session):**
- Docs: Laravel Boost `search-docs` — dostępne, nieużyte w refreshu (stos bez zmiany runnera); research etapu E2E w CI sprawdza konfigurację Playwright w CI; checked: 2026-11-19
- Search: Exa (`web_search_exa`) — dostępne, nieużyte; checked: 2026-11-19
- Runtime/browser: skill `playwright-cli` (sesja `-s=household-treasurer`), brak MCP Playwright; checked: 2026-11-19
- Provider/platform: `gh` CLI (issues #271, #272, przebiegi Actions); checked: 2026-11-19

## 2. Obawy użytkownika (Faza 2, 2026-11-19)

| # | Pytanie | Odpowiedź | Implikowane ryzyko |
|---|---|---|---|
| 1 | Największa obawa | „najbardziej obawiam się o sam import — żeby dane z plików były prawidłowo importowane i operacje były realnymi operacjami” | #1 zostaje na szczycie; nowy akcent: zapisana operacja = transakcja z wyciągu (data, kwota, waluta, rachunek), nie tylko „bez duplikatów i zgubień” — #271, #272 |
| 2 | Wcześniejsze wpadki | „nie było faktycznych błędów do tej pory, ale to jest moja największa obawa” | brak incydentów; prawdopodobieństwo #1 z hot-spotów i otwartych issue, nie z historii awarii |
| 3 | Zmiany bez pewności | „nie było takiego miejsca, jak coś zmieniałem to raczej dokładałem nowe features” | brak wskazanego obszaru; prawdopodobieństwo z hot-spotów |
| 4 | Niedotestowane | „E2E tylko lokalnie” | #7: przepływ w przeglądarce psuje się po merge'u, a CI jest zielone |
| 5 | Nie wydawać na | „Bez zmian w §7” | §7 bez zmian |

## 3. Co jest nieaktualne w przewodniku

1. §3 etap 1 `change opened` → `complete`, folder `context/archive/2026-11-17-testing-import-integrity/`.
2. Odwołania do porzuconego M-2 (abandoned 2026-11-08): źródła #2 (S-14), #3 (S-11, S-12), #5 i #6 (S-14, S-16), #8 (S-11, S-13) oraz uzasadnienie kolejności §3 („przed S-14”, „czeka na S-11”, „przed v6”). Research sprawdza, czy ryzyka mają dziś inne źródła (M-3, M-4, PRD v3), czy słabną.
3. #6 mówi o wersjach 1–5 i v6; `SNAPSHOT_VERSION` = 13, zamrożone fixture v5, v6, v8, v9.
4. #7 cytuje „brak warstwy e2e”; jest 15 speców i `test-stack.md`, luka to CI (P4).
5. §4: wiersz e2e „none yet”, vite-plus 1.4 → 1.6.0, liczby 94/26 → 175/63, daty `checked:` 2026-11-06.
6. §5: bramka e2e „required after Phase 5 (planned in Phase 4)”.
7. §6.3 „TBD”, choć specy i wzorzec seed istnieją; brak wzorca testu przełomu roku (roadmap F-09 Unlocks: „wzór testu przełomu roku w §6”).
8. Linia hot-spotów w §1 (126 commitów do 2026-11-06) i §8.

## 4. Czego brakuje

- **Ryzyko przełomu roku** (nowe, kandydat na #2): 1 stycznia aplikacja pokazuje inny dzień albo rok w menu, na Dashboardzie i w dialogach, rok w sesji zostaje stary, Import przy szkicu odrzuca cały wyciąg, „Lata” liczą jeden dzień. Impact High (pierwszy przełom na prawdziwych danych), Likelihood High (rehearsal potwierdził luki 2–5). Źródła: `rehearsal.md` luki 1–5, roadmap MS-01…MS-05.
- **Lista przepływów-bramek E2E** dla S-44: które specy są stabilne i chronią krytyczne przepływy (Import, dialog operacji, plan miesiąca), a które zostają lokalne. Źródła: P4, roadmap S-44 Risk („flaky spec w bramce zatrzyma wdrożenia poprawek przełomu”), test-stack (jeden worker, wspólna sesja, dane demo).
- **Import „realne operacje”** (P1): #1 rozszerzony o wierność pól operacji wobec pliku; luki #271 (wiersz gubiony po zmianie formatu komórki) i #272 (kwota w walucie ≠ PLN jako PLN) jako znane, otwarte scenariusze.

## 5. Wskazówki odpowiedzi do zweryfikowania (nowe i zmienione)

| Ryzyko | Co dowiedzie ochrony | Należy zakwestionować | Kontekst dla `/10x-research` | Prawdopodobnie najtańsza warstwa | Antywzorzec |
|---|---|---|---|---|---|
| #1 (rozszerzone) | Każda zapisana operacja ma datę, kwotę w groszach, walutę i rachunek równe ręcznie spisanym z pliku; wiersz, którego nie da się odczytać, jest widoczny w podglądzie, a nie znika | „Liczba operacji się zgadza, więc treść też” | Co dziś pokrywa `ImportIntegrityTest` (krotki data+kwota na podglądzie), czy po zatwierdzeniu też; jak #271 i #272 objawiają się w danych | integracja na PostgreSQL z fixture | Oczekiwane krotki odczytane parserem zamiast ręcznie z pliku |
| przełom roku (nowe) | Przy zamrożonym czasie 31.12 23:30 UTC / 1.01 00:30 PL serwer, menu roku i domyślna data dialogu dają ten sam dzień; testy `_known_gap` stają się zielone wraz z S-39…S-43 bez zmiany asercji wymagań | „Zielone testy z `travelTo` w UTC dowodzą zachowania o północy w Polsce” | Trzy źródła „dziś” (PHP, menu roku, dialogi), jak testy PHP i Vitest zamrażają czas, decyzja F-08 o dniu aplikacji | unit/integracja z zamrożonym czasem po obu stronach; e2e tylko dla zgodności menu i dialogu, jeśli nie da się tego złapać niżej | Test, który zamraża czas tylko w PHP, gdy błąd żyje w zegarze przeglądarki |
| #7 (zmienione) | CI na pushu i PR do `development` uruchamia wybrane specy; spec-bramka pada, gdy przepływ (wgranie → podgląd → zatwierdzenie Importu, dialog operacji, plan miesiąca) nie kończy się skutkiem na stronie | „15 speców przechodzi lokalnie, więc wszystkie nadają się na bramkę” | Które specy zależą od danych demo i kolejności, jak CI zbuduje bazę E2E i konto bez `.env` i obrazu Sail, historia napraw speców (`v1-4-0-fixes`, `v1-4-3-fixes`) | e2e w CI (Playwright, istniejąca zależność) | Bramka ze wszystkich speców; `retries` maskujące flaky spec |

Ryzyka #2, #3, #5, #6, #8 bez zmiany treści; research sprawdza tylko ich źródła po porzuceniu M-2.

## 6. Proponowane §3 po refreshu (szkic — `/10x-plan` decyduje)

| # | Etap | Cel | Ryzyka | Typy | Status |
|---|---|---|---|---|---|
| 1 | Integralność Importu | (bez zmian) | #1, #4 | integracja + benchmark | complete |
| 2 | Przepływy-bramki E2E w CI | CI czerwieni się, gdy merge psuje krytyczny przepływ; bramka tylko ze stabilnych speców | #7 | e2e w CI | not started (zmiana `e2e-in-ci`, S-44) |
| 3 | Przełom roku | Wzorzec testu z zamrożonym czasem po obu stronach; `_known_gap` zielone z S-39…S-43 | przełom roku | unit + integracja (+ e2e, gdy niżej się nie da) | not started (testy jadą z wycinkami strumienia A) |
| 4 | Wierność Importu | #271, #272 i pola operacji wobec pliku | #1 (rozszerzone) | integracja | not started |
| 5 | Niezmienniki kwot | (bez M-2) | #2, #3 | kontrakt/parzystość + integracja | not started (Parked w M-5) |
| 6 | Backup i restore | do wersji 13 | #5, #6 | integracja | not started (Parked w M-5) |
| 7 | Bramki jakości | izolacja tras, `arch()` | #8 | integracja + bramki | not started (Parked w M-5) |

Pytania do planu: czy etap 4 to osobny etap, czy luki #271/#272 zostają issue poza planem testów; czy przełom roku to etap planu testów, czy tylko wzorzec §6 używany przez S-39…S-43.

## 7. Granice tej zmiany

- Zmiana edytuje `context/foundation/test-plan.md` (§1–§8) za wyraźną zgodą użytkownika na przepisanie §1/§2 w ramach F-09; nie pisze testów ani CI YAML (to S-44).
- §7 bez zmian (P5).
