---
project: 'Household Treasurer'
version: 5
status: draft
created: 2026-11-02
updated: 2026-11-19
prd_version: —
main_goal: quality
top_blocker: none
milestone_id: first-full-cycle-on-production
milestone_seq: 5
milestone_status: open
---

# Roadmap: Household Treasurer

> Derived from `context/foundation/development-directions.md` (sekcja „Na już”, wiersze 2–6, i rekomendacja agenta) and `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` (luki 1–5 i kandydaci), + codebase baseline re-checked in the repo.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.
> Identyfikatory kontynuują numerację: M-1 to F-01 i S-01…S-10, porzucony M-2 to F-02 i S-11…S-17, M-3 to F-03…F-06 i S-18…S-33, M-4 to F-07 i S-34…S-38 (w `## Done` i `## Milestone History`), więc M-5 zaczyna od F-08 i S-39.

## Milestone

**M-5: Pierwszy pełny cykl na produkcji** — Status: open

- **Intent:** Pierwszy przełom roku na prawdziwych danych (2026→2027) przechodzi na produkcji bez ręcznych poprawek, a aplikacja ma siatkę jakości, która łapie błędy, zanim zrobi to użytkownik: E2E w CI, śledzenie błędów na produkcji, aktualny plan testów i dokumenty zgodne z kodem. Funkcja na następny kamień wynika z listy tarć zebranej w tym cyklu, nie z domysłu.
- **Source materials:** `context/foundation/development-directions.md` (wiersze 2–6 „Na już”, rekomendacja „kamień jakości”, decyzja użytkownika z 2026-11-18 „A. Kamień jakości”) i `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` (luki 1–5, obserwacje faz 1 i 3). Bez PRD.
- **Done when:** każdy element F-NN i S-NN poniżej ma status `done`, plan stycznia 2027 jest zatwierdzony na produkcji bez ręcznych poprawek po przełomie roku, a lista tarć z grudnia i stycznia (MS-10) jest zapisana.
- **Scope anchors:**
  - MS-01: Serwer, menu roku i domyślne daty dialogów zgadzają się, który dzień i rok jest „dziś”, także między 00:00 a 01:00 czasu polskiego 1 stycznia (luka 4; dziś serwer liczy w UTC, menu roku bierze rok lokalny przeglądarki, dialogi datę UTC przeglądarki).
  - MS-02: Rok w sesji przechodzi po przełomie na nowy rok, zamiast zostawać na 2026, gdy Dashboard jest już w 2027 (luka 3, `SelectedBudgetYearTest::…_known_gap`).
  - MS-03: Przed przełomem aplikacja mówi, co czeka: niezatwierdzony plan grudnia (po północy nie ma już „Zatwierdź”), szkic następnego roku albo uruchomiony Budżet czekający na rok kalendarzowy, oraz że Import nie przyjmie wierszy roku-szkicu (luki 1 i 2, kandydat z fazy 3 o menu roku i Dashboardzie po T1).
  - MS-04: Import wyciągu z przełomu przy szkicu nowego roku nie odrzuca całego zapisu, a komunikat mówi, że trzeba rozpocząć realizację Budżetu tego roku (obserwacja fazy 1, `CsvImportTest::…_known_gap`, kandydat z fazy 3).
  - MS-05: „Lata” w trybie „do tego samego dnia” i raport rok do roku dają w pierwszych dniach stycznia odczyt, który ma sens, a nie jeden dzień poprzednich lat (luka 5, pominięty T3).
  - MS-06: Plan testów odpowiada stanowi repo (wiersz 2).
  - MS-07: CI uruchamia testy E2E (wiersz 3).
  - MS-08: Błędy na produkcji trafiają do śledzenia błędów, nie tylko do logu plikowego (wiersz 4).
  - MS-09: Dokumenty bez pułapek: zdublowany numer ADR 0008 i terminy słownika, których nie ma w kodzie (wiersz 5).
  - MS-10: Użytkownik zapisuje przez grudzień i styczeń, co go spowalnia; z tej listy powstaje wybór następnej funkcji (wiersz 6). Bez wycinka: to praca użytkownika, sprawdzana w „Done when”.

## Vision recap

Cztery kamienie milowe powstały w dwa tygodnie, a aplikacja nie przeszła jeszcze ani jednego przełomu roku na prawdziwych danych. Próba na kopii produkcji (2026-11-18) nie znalazła luk blokujących, ale potwierdziła pięć miejsc, w których 1 stycznia aplikacja zachowa się nieoczywiście, głównie dlatego, że serwer, menu roku i dialogi mają trzy różne „dzisiaj”. Ten kamień usuwa te pułapki przed przełomem, dokłada siatkę jakości (E2E w CI, śledzenie błędów) i zbiera listę tarć, z której w styczniu wynika następna funkcja.

## North star

**S-39: użytkownik o 00:30 1 stycznia widzi w menu roku, na Dashboardzie i jako domyślną datę w dialogach ten sam dzień i rok** — trzy zegary w kodzie to wspólna przyczyna luk 2, 3, 4 i 5, więc przy celu „jakość” ten wycinek stoi zaraz po decyzji, który dzień jest „dziś”.

> Gwiazda przewodnia (north star) to najmniejszy wycinek od początku do końca, którego dostarczenie dowodzi, że kamień milowy działa. Stoi tak wcześnie, jak pozwalają warunki wstępne, bo reszta ma sens tylko wtedy, gdy on działa.

## At a glance

| ID   | Change ID                       | Outcome (user can …)                                                                                          | Prerequisites | PRD refs | Status   |
| ---- | ------------------------------- | ------------------------------------------------------------------------------------------------------------- | ------------- | -------- | -------- |
| F-08 | app-day-decision                | (foundation) ADR i słownik ustalają jeden „dzień aplikacji” dla serwera i przeglądarki w miejsce „dziś w UTC”  | —             | MS-01    | done     |
| F-09 | test-plan-refresh               | (foundation) plan testów odpowiada stanowi repo i wskazuje przepływy E2E, które są bramką CI                   | —             | MS-06    | done     |
| S-39 | one-app-day                     | użytkownik widzi ten sam dzień i rok w menu roku, na Dashboardzie i w domyślnej dacie dialogów wokół północy   | F-08          | MS-01    | ready    |
| S-40 | session-year-follows-year-turn  | użytkownik po przełomie otwiera aplikację w nowym roku bez ręcznej zmiany roku                                 | F-08, S-39    | MS-02    | proposed |
| S-41 | year-turn-notices               | użytkownik widzi przed przełomem, co czeka: plan grudnia, Budżet następnego roku i Import przy szkicu          | F-08, S-39    | MS-03    | proposed |
| S-42 | import-across-draft-year        | użytkownik importuje wyciąg z przełomu przy szkicu nowego roku i wie, co zrobić z wierszami nowego roku        | —             | MS-04    | done     |
| S-43 | years-report-in-early-january   | użytkownik widzi w „Latach” i w roku do roku sensowny odczyt w pierwszych dniach stycznia                     | F-08, S-39    | MS-05    | proposed |
| S-44 | e2e-in-ci                       | użytkownik (właściciel repo) widzi czerwone CI, gdy zmiana psuje przepływ w przeglądarce                       | F-09          | MS-07    | ready    |
| S-45 | production-error-tracking       | użytkownik (właściciel) dostaje zgłoszenie wyjątku z produkcji ze stosem wywołań i kontekstem żądania          | —             | MS-08    | blocked  |
| S-46 | docs-match-code                 | użytkownik i agent czytają słownik i ADR bez zdublowanych numerów i terminów, których nie ma w kodzie          | —             | MS-09    | done     |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                 | Chain                                                   | Note                                                                                                                         |
| ------ | --------------------- | ------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| A      | Przełom roku          | `F-08` → `S-39` → `S-40` · `S-41` · `S-43`; `S-42`       | Przy celu „jakość” najpierw decyzja o dniu; `S-40`, `S-41`, `S-43` idą równolegle po `S-39`, który wprowadza `AppDay` (ADR-0103); `S-42` nie czeka na nic. |
| B      | Siatka testów         | `F-09` → `S-44`                                         | Odświeżony plan wybiera przepływy-bramki; E2E w CI chroni wdrożenia poprawek strumienia A.                                    |
| C      | Porządek produkcji    | `S-45` · `S-46`                                         | Dwa samodzielne wycinki; `S-45` czeka na wybór dostawcy, `S-46` może iść od razu.                                             |

## Baseline

What's already in place in the codebase as of `2026-11-18` (re-checked in the repo, user-confirmed; zastępuje stan z 2026-11-14 z M-4).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — strony Inertia/Vue, menu roku `BudgetYearSwitcher.vue` (stan roku z S-38), dialogi operacji, rachunku, lokaty, ruchu kapitału i uzgodnienia salda. Rok w menu bierze lokalny zegar przeglądarki (`BudgetYearSwitcher.vue:82`), domyślne daty dialogów datę UTC przeglądarki (`IncomeFormDialog.vue:332` i cztery inne dialogi).
- **Backend / API:** present — „dziś” liczone w UTC (`config/app.php:68`, `SelectedBudgetYear.php:36`; ADR-0074, ADR-0075, ADR-0077, ADR-0081, ADR-0097); zegar próby `app:rehearsal-clock` z year-turn-rehearsal.
- **Data:** present — budżety 2019–2027 (2027 szkic, 43 pozycje), lata 2019–2025 wczytane z arkuszy, snapshot v13.
- **Auth:** present — Fortify, 2FA, passkeys, polityki w `app/Policies`.
- **Deploy / infra:** partial — ręczne `scripts/deploy.sh` z kopią bazy; `.github/workflows/tests.yml` uruchamia `composer ci:check` na PostgreSQL i Sloppy, ale nie Playwrighta; 15 plików `tests/e2e/*.spec.ts` i `playwright.config.ts` działają tylko lokalnie.
- **Observability:** partial — log plikowy (`LOG_STACK=single`), `/up`, e-mail z `backup:monitor`; brak pakietu śledzenia błędów i powiadomień aplikacji.
- **Testy (poza tabelą warstw):** partial — `test-plan.md` ma datę 2026-11-17, ale faza 1 ma status „change opened”, choć jest w archiwum (`2026-11-17-testing-import-integrity`), a fazy 2–5 odwołują się do S-11 i S-14 z porzuconego M-2.

## Foundations

### F-08: Jeden dzień aplikacji

- **Outcome:** (foundation) nowy ADR ustala, który dzień kalendarzowy jest „dziś” dla aplikacji (dzień w Polsce albo UTC) i że przeglądarka bierze ten sam dzień co serwer, a słownik i ADR-0074, ADR-0075, ADR-0077, ADR-0081, ADR-0097 przestają mówić „dziś w UTC” na własną rękę.
- **Change ID:** app-day-decision
- **PRD refs:** MS-01
- **Unlocks:** S-39, S-40, S-41, S-43; rozstrzyga, gdzie wypada północ dla luki 2 (dziś 00:00 UTC to 01:00 w Polsce).
- **Prerequisites:** —
- **Parallel with:** F-09, S-42, S-45, S-46
- **Blockers:** —
- **Unknowns:**
  - Czy „dzień aplikacji” to dzień w Polsce, czy zostaje UTC, a tylko przeglądarka dopasowuje się do serwera? — Owner: user. Block: no (to jest treść tej decyzji).
- **Risk:** Stoi przed kodem, bo AGENTS.md wymaga wpisu w słowniku i ADR przed zmianą reguły, a cztery wycinki strumienia A zależą od tej samej odpowiedzi; bez niej dwa równoległe plany rozstrzygnęłyby północ inaczej. Tylko dokumenty.
- **Status:** done

### F-09: Odświeżony plan testów

- **Outcome:** (foundation) `test-plan.md` odpowiada stanowi repo: faza 1 zamknięta z odnośnikiem do archiwum, fazy 2–5 bez odwołań do porzuconego M-2, z miejscem na testy przełomu roku i z listą przepływów E2E, które są bramką CI.
- **Change ID:** test-plan-refresh
- **PRD refs:** MS-06
- **Unlocks:** S-44 (które spece blokują CI); ścieżka weryfikacji dla S-39…S-43 (wzór testu przełomu roku w §6 planu).
- **Prerequisites:** —
- **Parallel with:** F-08, S-42, S-45, S-46
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Biegnie przez `/10x-test-plan --refresh`, nie `/10x-plan`; jeśli zostanie pominięty, E2E w CI wybierze bramki bez strategii i każdy flaky spec zablokuje wdrożenia poprawek przełomu.
- **Status:** done

## Slices

### S-39: Ten sam dzień w menu, na Dashboardzie i w dialogach

- **Outcome:** użytkownik o 00:30 1 stycznia (i o każdej porze wokół północy) widzi w menu roku, na Dashboardzie i jako domyślną datę w dialogach operacji, rachunku, lokaty, ruchu kapitału i uzgodnienia salda ten sam dzień i rok
- **Change ID:** one-app-day
- **PRD refs:** MS-01
- **Prerequisites:** F-08
- **Parallel with:** S-42, F-09, S-44, S-45, S-46
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Dotyka każdego dialogu z domyślną datą i współdzielonego props sidebara; poprawka sprawdzona tylko w jednym dialogu zostawi resztę na zegarze przeglądarki, więc dowód wymaga zegara próby po stronie serwera i przeglądarki jak w year-turn-rehearsal.
- **Status:** ready

### S-40: Rok w sesji po przełomie

- **Outcome:** użytkownik, który w grudniu pracował w roku 2026, po przełomie otwiera aplikację w roku 2027 bez ręcznej zmiany roku, a test `_known_gap` luki 3 przechodzi jako zwykły test
- **Change ID:** session-year-follows-year-turn
- **PRD refs:** MS-02
- **Prerequisites:** F-08, S-39 (`AppDay` z ADR-0103)
- **Parallel with:** S-41, S-42, S-43, F-09, S-44, S-45, S-46
- **Blockers:** —
- **Unknowns:**
  - Czy przeskakuje tylko rok, który był bieżący przed przełomem, czy także rok wybrany ręcznie do przeglądania (np. 2023)? — Owner: user. Block: no.
- **Risk:** Zmienia ADR-0075 (rok z sesji ma pierwszeństwo); za szeroka reguła wyrzuci użytkownika z roku, który świadomie przegląda.
- **Status:** proposed

### S-41: Przypomnienia przed przełomem

- **Outcome:** użytkownik widzi w grudniu w „Wymaga uwagi”, że plan grudnia czeka na zatwierdzenie i po północy nie da się go już zatwierdzić, czy Budżet następnego roku jest szkicem (Import nie przyjmie jego wierszy), a po uruchomieniu menu roku i Dashboard mówią, że Budżet czeka na rok kalendarzowy
- **Change ID:** year-turn-notices
- **PRD refs:** MS-03
- **Prerequisites:** F-08, S-39 (`AppDay` z ADR-0103, te same widoki: menu roku i Dashboard)
- **Parallel with:** S-40, S-42, S-43, F-09, S-44, S-45, S-46
- **Blockers:** —
- **Unknowns:**
  - Czy miniony miesiąc bez zatwierdzonego planu ma dać się zatwierdzić po północy (zmiana reguły), czy wystarczy ostrzeżenie przed nią? — Owner: user. Block: no.
- **Risk:** Powiadomienia Dashboardu mają własne reguły wagi i progów (F-01); trzy nowe powiadomienia naraz w grudniu nie mogą zasłonić istniejących ostrzeżeń planu miesiąca.
- **Status:** proposed

### S-42: Import wyciągu z przełomu przy szkicu roku

- **Outcome:** użytkownik importuje wyciąg z 29.12–02.01, gdy 2027 jest szkicem, zapisuje wiersze grudnia, a przy wierszach stycznia widzi, że trzeba rozpocząć realizację Budżetu 2027; test `_known_gap` z `CsvImportTest` przechodzi jako zwykły test
- **Change ID:** import-across-draft-year
- **PRD refs:** MS-04
- **Prerequisites:** —
- **Parallel with:** F-08, F-09, S-39, S-40, S-41, S-43, S-44, S-45, S-46
- **Blockers:** —
- **Unknowns:**
  - Zapis częściowy (wiersze starego roku przechodzą, nowego czekają) czy tylko lepszy komunikat z odznaczeniem wierszy stycznia? — Owner: user. Block: no.
- **Risk:** Zapis częściowy podważa zasadę z fazy 1 planu testów („Import nie zostawia połowicznych zestawów”), więc wybór musi zapaść z nią w ręku, a nie przy kodzie.
- **Status:** done

### S-43: „Lata” i rok do roku w pierwszych dniach stycznia

- **Outcome:** użytkownik 1–N stycznia widzi w „Latach” i w raporcie rok do roku odczyt, który porównuje coś więcej niż jeden dzień poprzednich lat, a wynik rok do roku na 1 stycznia jest sprawdzony
- **Change ID:** years-report-in-early-january
- **PRD refs:** MS-05
- **Prerequisites:** F-08, S-39 (`AppDay` z ADR-0103)
- **Parallel with:** S-40, S-41, S-42, F-09, S-44, S-45, S-46
- **Blockers:** —
- **Unknowns:**
  - Co „Lata” pokazują na początku stycznia: domyślnie „Pełne lata”, cięcie do końca poprzedniego miesiąca czy jak dziś z podpowiedzią? — Owner: user. Block: no.
- **Risk:** Zmienia ADR-0097 (Same-day Cut) i może dotknąć ADR-0074; sumy w „Latach” muszą dalej równać się raportowi Rok.
- **Status:** proposed

### S-44: E2E w CI

- **Outcome:** użytkownik (właściciel repo) widzi czerwone CI na pushu i PR do `development`, gdy zmiana psuje przepływ, który odświeżony plan testów wskazał jako bramkę
- **Change ID:** e2e-in-ci
- **PRD refs:** MS-07
- **Prerequisites:** F-09
- **Parallel with:** F-08, S-39, S-40, S-41, S-42, S-43, S-45, S-46
- **Blockers:** —
- **Unknowns:**
  - Skąd CI bierze konto testowe dla `auth.setup.ts` (lokalnie dane logowania są w `.env`)? — Owner: user. Block: no.
- **Risk:** Flaky spec w bramce zatrzyma wdrożenia poprawek przełomu w grudniu; bramka obejmuje najpierw tylko stabilne przepływy, reszta zostaje lokalna.
- **Status:** ready

### S-45: Śledzenie błędów na produkcji

- **Outcome:** użytkownik (właściciel) dostaje zgłoszenie wyjątku z produkcji ze stosem wywołań i kontekstem żądania, bez szukania go w pliku logu, także dla błędów, które dziś kod zamienia w przekierowanie albo cichy `catch`
- **Change ID:** production-error-tracking
- **PRD refs:** MS-08
- **Prerequisites:** —
- **Parallel with:** F-08, F-09, S-39, S-40, S-41, S-42, S-43, S-44, S-46
- **Blockers:** —
- **Unknowns:**
  - Który dostawca (usługa zewnętrzna, własny serwer czy e-mail z aplikacji) i za jaką cenę? Nowa zależność wymaga zgody (AGENTS.md). Ścieżka: `/10x-observability-audit` → `/10x-infra-research`. — Owner: user. Block: yes.
- **Risk:** Bez audytu najpierw tracker złapie tylko wyjątki, które dziś i tak lądują w logu; audyt pokazuje, gdzie kod je połyka. Wdrożony przed przełomem łapie błędy 1 stycznia, a nie tydzień później.
- **Status:** blocked

### S-46: Dokumenty zgodne z kodem

- **Outcome:** użytkownik i agent czytają słownik i ADR bez pułapek: dwa ADR 0008 mają osobne numery z poprawionymi odnośnikami, a Year Close, Reserve, Earmark, Agenda i Cash Expectation są w słowniku oznaczone jako „nie w kodzie” albo usunięte
- **Change ID:** docs-match-code
- **PRD refs:** MS-09
- **Prerequisites:** —
- **Parallel with:** F-08, F-09, S-39, S-40, S-41, S-42, S-43, S-44, S-45
- **Blockers:** —
- **Unknowns:**
  - Oznaczyć terminy jako „nie w kodzie”, czy usunąć? Year Close łączy się z „podsumowaniem roku” z `development-directions.md` (sekcja „Rok 2027”). — Owner: user. Block: no.
- **Risk:** Zmiana numeru ADR psuje odnośniki w dokumentach i komentarzach kodu; usunięcie terminu z reguł Konta i Kategorii (Cash Expectation) zmienia treść tych reguł, więc to też zmiana słownika według AGENTS.md.
- **Status:** done

## Backlog Handoff

| Roadmap ID | Change ID                      | Suggested issue title                                                    | Ready for `/10x-plan` | Notes                                                                 |
| ---------- | ------------------------------ | ------------------------------------------------------------------------ | --------------------- | --------------------------------------------------------------------- |
| F-08       | app-day-decision               | ADR i słownik: jeden dzień aplikacji zamiast „dziś w UTC”                 | no                    | Zrobione (ADR-0103)  |
| F-09       | test-plan-refresh              | Plan testów: odświeżenie po M-4 i próbie przełomu                         | yes                   | Run `/10x-test-plan --refresh`, nie `/10x-plan`                        |
| S-39       | one-app-day                    | Przełom roku: ten sam dzień w menu, na Dashboardzie i w dialogach         | yes                    | Run `/10x-plan one-app-day`; gwiazda przewodnia, spis miejsc w ADR-0103                                                               |
| S-40       | session-year-follows-year-turn | Przełom roku: rok w sesji przechodzi na nowy rok                          | no                    | Po scaleniu S-39 (`AppDay`); plan `/10x-plan session-year-follows-year-turn` można przygotować wcześniej |
| S-41       | year-turn-notices              | Przełom roku: przypomnienia w „Wymaga uwagi” i w menu roku                | no                    | Po scaleniu S-39 (`AppDay`, ten sam widok); plan `/10x-plan year-turn-notices` można przygotować wcześniej |
| S-42       | import-across-draft-year       | Import: wyciąg z przełomu przy szkicu nowego roku                         | yes                   | Run `/10x-plan import-across-draft-year`                               |
| S-43       | years-report-in-early-january  | Raporty: „Lata” i rok do roku na początku stycznia                        | no                    | Po scaleniu S-39 (`AppDay`); plan `/10x-plan years-report-in-early-january` można przygotować wcześniej |
| S-44       | e2e-in-ci                      | CI: testy Playwright na pushu i PR                                        | yes                    | Run `/10x-plan e2e-in-ci`; bramki w test-plan.md (F-09)                                                               |
| S-45       | production-error-tracking      | Produkcja: śledzenie błędów                                               | no                    | Audyt zrobiony (`context/audits/observability/2026-11-19_import-operations-year-turn-backups.md`); teraz `/10x-infra-research`       |
| S-46       | docs-match-code                | Dokumenty: numer ADR 0008 i terminy słownika bez kodu                     | yes                   | Run `/10x-plan docs-match-code`                                        |

This table is the clean handoff to Jira/Linear or any MCP-backed backlog. Include one row for every `F-NN` and `S-NN`. It should be compact enough to copy into issues, but it must not duplicate the detailed roadmap body.

## Open Roadmap Questions

1. **Gdzie zapisujesz listę tarć (MS-10)?** Plik w repo, issue na GitHubie z etykietą czy notatka poza repo; od tego zależy, jak w styczniu z niej wybrać funkcję. — Owner: user. Block: roadmap-wide (warunek „Done when”), żaden wycinek.
2. **Ostatnie wdrożenie przed przełomem.** Do kiedy wdrażać poprawki strumienia A, żeby 31 grudnia produkcja stała na sprawdzonej wersji, i czy powtórzyć próbę przełomu na kopii po tych poprawkach? — Owner: user. Block: no; dotyczy S-39, S-40, S-41, S-42, S-43.
3. **Snapshot na VPS po próbie.** Plik `user:backup` z pełnymi danymi został w `storage/app/private/user-snapshots/` (rehearsal.md, „Sprzątanie”): usunąć czy świadomie zostawić? — Owner: user. Block: no; żaden wycinek.

## Parked

- **Nowe funkcje z `development-directions.md`** (wiersze 7–15 „Na już” i etapy „Potem”: Majątek z walutą, szybsze wprowadzanie, płynność w czasie, analityka) — Why parked: decyzja użytkownika 2026-11-18, funkcja w styczniu z listy tarć (MS-10).
- **Automatyczne wdrożenie** — Why parked: poza zakresem; M-5 dokłada E2E do CI, wdrożenie zostaje ręczne (`scripts/deploy.sh`).
- **Year Close i Reserve w kodzie** — Why parked: nigdy niezbudowane; ADR-0104 (S-46) usunął je ze słownika, więc powrót zaczyna się od nowego wpisu w słowniku i nowego ADR.
- **Pozostałe fazy planu testów** (niezmienniki kwot, backup i restore, `arch()`) — Why parked: F-09 ustawia ich kolejność; w M-5 wchodzi tylko E2E w CI (S-44).
- **Pozostałe karty Atramentu czekające na `/10x-ui`** — Why parked: kamień jakości nie zmienia widoków poza tym, czego wymagają luki przełomu.
- **Plan na grupie, liczniki mediów, nazwane cele oszczędnościowe** — Why parked: PRD v3 §Non-Goals.

## Milestone History

- **M-1: Kontrola miesiąca bez arkusza** (`month-control-without-spreadsheet`) — closed 2026-11-03. Wszystkie elementy F-01 i S-01 do S-10 mają status `done`; warunek „pełny miesiąc w aplikacji” (kryterium sukcesu PRD) do potwierdzenia w użyciu.
- **M-2: Raporty okresu i wydatki nieregularne** (`period-reports-and-irregular-expenses`) — abandoned 2026-11-08. Zastąpiony przez PRD v3 (`context/foundation/prd-v3.md`), który przebudował zakres: jedna lista kategorii zamiast powiązania 1:1, rytmy zamiast flag, raporty po grupach. Żaden z elementów F-02, S-11…S-17 nie był `done`; zmianę `period-report-by-category` usunięto w sesji shape 2026-11-07.
- **M-3: Jedna lista kategorii, rytmy i raporty** (`category-list-rhythms-reports`) — closed 2026-11-12. Wszystkie elementy F-03…F-06 i S-18…S-33 mają status `done`; kod na produkcji, pary kategorii scalone (S-21), a pięć kroków głównego kryterium sukcesu PRD v3 użytkownik przeszedł na produkcji.
- **M-4: Kategorie przez lata i widoki Atramentu** (`categories-across-years-and-atrament-views`) — closed 2026-11-15. Wszystkie elementy F-07 i S-34…S-38 mają status `done`; kod na produkcji jako v1.5.0 (`budget.kostecki.dev`), a użytkownik porównał kategorię przez lata 2020–2026 w „Latach” i doszedł z niej do panelu kategorii.

## Done

(Wpisy dodaje `/10x-archive`.)

- **F-01: (foundation) nowy ADR zastąpił ADR-0024 w części o „drugim budżecie miesięcznym”, a nowe terminy oraz progi powiadomień o wadze `warning` są w `context/foundation/glossary.md`.** — Archived 2026-11-02 → `context/archive/2026-11-02-month-plan-domain-decisions/`. Lesson: —.
- **S-01: użytkownik może zobaczyć propozycję planu, poprawić ją, zatwierdzić i odczytać „do wydania”** — Archived 2026-11-02 → `context/archive/2026-11-02-month-plan-and-to-spend/`. Lesson: —.
- **S-02: użytkownik może wpisać w planie kwotę „odkładam / dobieram” i zobaczyć jej wpływ na „do wydania”** — Archived 2026-11-02 → `context/archive/2026-11-02-set-aside-and-draw/`. Lesson: —.
- **S-03: użytkownik może zmienić zatwierdzony plan w trakcie miesiąca** — Archived 2026-11-02 → `context/archive/2026-11-02-approved-plan-changes/`. Lesson: —.
- **S-04: użytkownik może zobaczyć plan i faktyczną kwotę każdej pozycji oraz kategorie jednorazowe** — Archived 2026-11-02 → `context/archive/2026-11-02-month-items-plan-vs-actual/`. Lesson: —.
- **S-05: użytkownik może zobaczyć tempo wydatków względem upływu dni miesiąca** — Archived 2026-11-03 → `context/archive/2026-11-03-month-spending-pace/`. Lesson: —.
- **S-06: użytkownik może odczytać „do wydania” na Dashboardzie i stronie Budżet, także w miesiącu bez planu** — Archived 2026-11-02 → `context/archive/2026-11-02-to-spend-replaces-remaining/`. Lesson: —.
- **S-07: użytkownik może zobaczyć na Dashboardzie powiadomienia związane z planem miesiąca** — Archived 2026-11-03 → `context/archive/2026-11-03-month-plan-notices/`. Lesson: —.
- **S-08: użytkownik może zobaczyć przy operacji i w podglądzie Importu ostrzeżenia liczone z planu** — Archived 2026-11-03 → `context/archive/2026-11-02-plan-based-entry-warnings/`. Lesson: —.
- **S-09: użytkownik może zobaczyć podsumowanie miesiąca, w tym odkładanie zamierzone i realne** — Archived 2026-11-03 → `context/archive/2026-11-03-month-summary/`. Lesson: —.
- **S-10: użytkownik może porównać plany miesięcy z budżetem rocznym** — Archived 2026-11-03 → `context/archive/2026-11-03-month-plans-vs-year/`. Lesson: —.
- **F-03: (foundation) nowy ADR zastępuje ADR-0014 i zmienia ADR-0016 i ADR-0034, a wpisy w `context/foundation/glossary.md` opisują kategorię przez lata (bez terminu _Avoid_ „a Category identity that spans years”), grupę kategorii, archiwum globalne i regułę migracji (nazwa + typ → jedna kategoria listy).** — Archived 2026-11-09 → `context/archive/2026-11-09-category-list-domain-decisions/`. Lesson: —.
- **F-04: (foundation) wdrożenie robi kopię bazy przed migracją, przywrócenie z niej jest raz sprawdzone na kopii lokalnej, a reguły migracji danych, tożsamości kategorii i konwersji starszego snapshotu (poprawki 1 i 2 z health-check) ładują się agentowi z `.ai/rules`.** — Archived 2026-11-09 → `context/archive/2026-11-09-safe-first-data-migration/`. Lesson: —.
- **S-18: użytkownik może otworzyć „Kategorie” i zobaczyć jedną listę kategorii przez lata z sekcją archiwum, a budżet 2026, ~1 000 operacji, Budżet roczny, plan miesiąca, Import i `user:backup` / `user:restore` działają na tej liście bez ręcznej pracy i bez zmiany kwot** — Archived 2026-11-10 → `context/archive/2026-11-09-category-list-after-migration/`. Lesson: —.
- **F-05: (foundation) nowy ADR zmienia ADR-0024 i ADR-0033: cztery rytmy zastępują flagi, mapowanie flag na rytm przy migracji jest ustalone, a wpisy w `context/foundation/glossary.md` nazywają stawkę i „zebrane” (po polsku i angielsku), regułę „do wydania” dla wydatku nieregularnego i przejście zebranego przez 31 grudnia.** — Archived 2026-11-10 → `context/archive/2026-11-09-rhythm-and-irregular-decisions/`. Lesson: —.
- **S-19: użytkownik może utworzyć kategorię i edytować jej nazwę, ikonę, kolor, kolejność, typ i opis, zarchiwizować ją i przywrócić; archiwalna znika z wyboru w operacjach i planie, a na istniejącej operacji zostaje widoczna w selektorze** — Archived 2026-11-10 → `context/archive/2026-11-10-category-edit-and-archive/`. Lesson: —.
- **S-20: użytkownik może utworzyć nowy budżet, który bierze aktywne kategorie z listy i podpowiada kwoty planu z planu aktualnego poprzedniego roku** — Archived 2026-11-10 → `context/archive/2026-11-10-new-budget-from-category-list/`. Lesson: —.
- **S-22: użytkownik może utworzyć, nazwać, uporządkować i usunąć grupę (przychodów albo wydatków), przypisać do niej kategorie i widzieć kategorie w grupach z sumą grupy w „Kategoriach”, w Budżecie rocznym i w selektorze kategorii operacji** — Archived 2026-11-10 → `context/archive/2026-11-10-category-groups/`. Lesson: —.
- **S-28: użytkownik może otworzyć „Raporty”, wybrać miesiąc albo rok i zobaczyć przychody, wydatki, wynik oraz grupy z rozwinięciem do kategorii, z kwotą i udziałem % wydatków, jako wykres i tabelę** — Archived 2026-11-10 → `context/archive/2026-11-10-period-report-by-group/`. Lesson: —.
- **S-24: użytkownik może zobaczyć rytm nadany istniejącym kategoriom z dzisiejszych flag, poprawić go, a propozycja planu miesiąca liczy kwotę z rytmu (stała opłata, bieżąca, równomierna roczna z odczytem „zostało X na Y mies.”)** — Archived 2026-11-10 → `context/archive/2026-11-10-category-rhythm/`. Lesson: —.
- **S-29: użytkownik może porównać okres raportu z tym samym okresem rok wcześniej (kwota, różnica) na tej samej kategorii listy** — Archived 2026-11-11 → `context/archive/2026-11-11-year-over-year-report/`. Lesson: —.
- **S-26: użytkownik może oznaczyć kategorię jako nieregularną i podać zebrane startowe, widzieć w planie miesiąca i w Podsumowaniu miesiąca stawkę oraz „zebrane”, a rachunek zużywa zebrane i obniża „do wydania” tylko o nadwyżkę; „odkładam / dobieram” działa jak dotąd** — Archived 2026-11-11 → `context/archive/2026-11-11-irregular-expense-collected/`. Lesson: —.
- **S-27: użytkownik może zobaczyć w nowym budżecie zebrane startowe kategorii nieregularnej przeniesione ze stanu na 31 grudnia, a stawka nowego roku wynika z nowego planu rocznego** — Archived 2026-11-11 → `context/archive/2026-11-11-collected-carry-over-new-year/`. Lesson: —.
- **S-30: użytkownik może zobaczyć kwoty grup i kategorii w kolejnych miesiącach roku, domyślnie po grupach** — Archived 2026-11-11 → `context/archive/2026-11-11-report-trend-by-month/`. Lesson: —.
- **S-21: użytkownik może scalić dwie kategorie listy w jedną po podglądzie liczb (ile operacji, jakie plany), a operacje, plany i zebrane przechodzą na kategorię docelową.** — Archived 2026-11-11 → `context/archive/2026-11-11-category-merge/`. Lesson: —. Scalenie par na produkcji po F-06 i wdrożeniu.
- **S-31: użytkownik może przejść z kategorii w raporcie do jej operacji w wybranym okresie** — Archived 2026-11-12 → `context/archive/2026-11-12-report-to-operations/`. Lesson: —.
- **S-25: użytkownik może zobaczyć w planie miesiąca kategorie w zwijanych grupach z sumą grupy i kategorie bez grupy osobno, a przekroczenie kategorii widać też na wierszu zwiniętej grupy** — Archived 2026-11-12 → `context/archive/2026-11-12-month-plan-groups/`. Lesson: —.
- **F-06: (foundation) `user:restore` wczytuje snapshot także na użytkownika z danymi: id konta w tożsamości importu idzie przez mapę kont, a digest jest liczony od nowa (ADR-0080).** — Archived 2026-11-12 → `context/archive/2026-11-12-restore-import-identity-accounts/`. Lesson: —.
- **S-32: użytkownik może wybrać okres raportu „ostatnie 12 miesięcy”** — Archived 2026-11-12 → `context/archive/2026-11-12-rolling-twelve-month-report/`. Lesson: —.
- **S-33: użytkownik może ustawić domyślny procent planowanych przychodów, który propozycja planu wpisuje jako „odkładam”** — Archived 2026-11-12 → `context/archive/2026-11-12-default-set-aside-percent/`. Lesson: —.
- **S-23: użytkownik może importować operacje z podpowiedziami wskazującymi kategorię listy, trafnymi co najmniej tak jak przed zmianą** — Archived 2026-11-12 → `context/archive/2026-11-12-import-suggestions-on-category-list/`. Lesson: —.
- **S-37: użytkownik może przeglądać „Kategorie” w zakładkach Wydatki, Przychody i Archiwum z licznikami, szukać po nazwie i opisie, filtrować wydatki po rytmie i widzieć lata w budżecie jako pasek komórek** — Archived 2026-11-14 → `context/archive/2026-11-14-categories-tabs-and-filters/`. Lesson: —.
- **F-07: (foundation) ADR opisuje piątą pozycję okresu „Lata”, ranking z panelem kategorii w miejsce wykresu słupkowego i kolory wykresów (ADR-0097), a słownik nazywa nowy odczyt** — Archived 2026-11-14 → `context/archive/2026-11-14-report-redesign-decisions/`. Lesson: —.
- **S-38: użytkownik może zobaczyć w menu „Rok budżetu”, który rok jest bieżący, a który jest szkicem, z latami w grupach Ostatnie i Starsze i podpisem „szkic z planem roku N−1” przy „Dodaj rok N”** — Archived 2026-11-14 → `context/archive/2026-11-14-budget-year-menu-state/`. Lesson: —.
- **S-34: użytkownik może otworzyć Raporty → „Lata” i porównać każdą kategorię i grupę w latach 2020–2026, licząc każdy rok do tego samego dnia albo w pełnych latach, ze zmianą od 2020 i mini-wykresem, dla wydatków i przychodów** — Archived 2026-11-14 → `context/archive/2026-11-14-report-categories-across-years/`. Lesson: —.
- **S-35: użytkownik może zobaczyć raport roku jako listę kategorii od największych z paskiem udziału i różnicą do poprzedniego roku, przełączyć na grupy i otworzyć panel jednej kategorii z kwotą 2026 i 2025, różnicą, udziałem, miesiącami roku i przejściem do Operacji** — Archived 2026-11-14 → `context/archive/2026-11-14-report-ranking-and-category-panel/`. Lesson: —.
- **S-36: użytkownik może kliknąć kategorię w tabeli „Lata” i zobaczyć w panelu kategorii Raportu zakładkę „Lata” z jej latami 2020–2026** — Archived 2026-11-14 → `context/archive/2026-11-14-report-panel-years-tab/`. Lesson: —.
- **S-46: użytkownik i agent czytają słownik i ADR bez pułapek: dwa ADR 0008 mają osobne numery z poprawionymi odnośnikami, a Year Close, Reserve, Earmark, Agenda i Cash Expectation są w słowniku oznaczone jako „nie w kodzie” albo usunięte** — Archived 2026-11-19 → `context/archive/2026-11-19-docs-match-code/`. Lesson: —.
- **F-09: (foundation) `test-plan.md` odpowiada stanowi repo: faza 1 zamknięta z odnośnikiem do archiwum, fazy 2–5 bez odwołań do porzuconego M-2, z miejscem na testy przełomu roku i z listą przepływów E2E, które są bramką CI.** — Archived 2026-11-19 → `context/archive/2026-11-19-test-plan-refresh/`. Lesson: —.
- **S-42: użytkownik importuje wyciąg z 29.12–02.01, gdy 2027 jest szkicem, zapisuje wiersze grudnia, a przy wierszach stycznia widzi, że trzeba rozpocząć realizację Budżetu 2027; test `_known_gap` z `CsvImportTest` przechodzi jako zwykły test** — Archived 2026-11-19 → `context/archive/2026-11-18-import-across-draft-year/`. Lesson: —.
- **F-08: (foundation) nowy ADR ustala, który dzień kalendarzowy jest „dziś” dla aplikacji i że przeglądarka bierze ten sam dzień co serwer (ADR-0103, App Day w Europe/Warsaw), a słownik i starsze ADR przestają mówić „dziś w UTC” na własną rękę.** — Archived 2026-11-19 → `context/archive/2026-11-18-app-day-decision/`. Lesson: —. Wpis i status dopisane przez `/10x-roadmap` 2026-11-19, bo zamknięcie zmiany nie zaktualizowało roadmapy.
