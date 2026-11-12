# Widok Raporty: stany, miejsce na wykres i sink na prawdziwej stronie — Plan Brief

> Full plan: `context/changes/reports-view/plan.md`
> Research: `context/changes/reports-view/research.md`

## What & Why

Audyt 10x-ui widoku Raporty potwierdził pięć wad (C1–C5).

- **C1:** w drafcie i bez Budżetu strona nie ma `h1` ani przełącznika, a „12 miesięcy” otwiera się w drafcie tylko z URL, i to w jedną stronę.
- **C2/C4:** po doładowaniu fragmentu wykresu tabela zjeżdża o 300–400 px. Stałe rozmiarów wykresu żyją poza strażnikami.
- **C3:** kitchen sink kopiuje stronę i już pokazuje inne tytuły niż ona.
- **C5:** w 22rem tabela ucina nazwy kategorii.

Plan usuwa wszystkie pięć. Zaczyna od architektury, żeby każda poprawka trafiła tylko w jedno miejsce.

## Starting Point

- `Reports.vue:125-130` renderuje stany puste zamiast wiersza tytułu.
- Wykresy idą przez goły `defineAsyncComponent`, bez placeholdera.
- `ReportsKitchenSink.vue:150-437` trzyma trzy ręczne kopie kompozycji strony.
- `.report-grid` zostawia nazwie około 96 px w 22rem.
- Serwer już podaje w drafcie wszystko, czego potrzeba (`ReportController.php:56-68`).

## Desired End State

- W każdym stanie z Budżetem użytkownik widzi tytuł widoku i przełącznik z czterema segmentami. W drafcie „12 miesięcy” pokazuje raport, a pozostałe segmenty pokazują kartkę „Budżet w przygotowaniu”. Bez Budżetu widać sam `h1` „Raporty” i kartkę.
- Przy pierwszym wejściu placeholder zajmuje dokładnie miejsce wykresu, więc tabela się nie przesuwa.
- `/dev/ui/reports` renderuje prawdziwą stronę w każdym scenariuszu.
- Na telefonie nazwy kategorii mieszczą się w całości, a „udział X%” stoi pod kwotą.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Stany draft / brak Budżetu | `h1` i pełny przełącznik nad kartką draftu; bez Budżetu sam `h1` | Daje drogę do i z „12 miesięcy” bez segmentów wyłączonych i bez drugiego wejścia | Research (Decisions 1) |
| Kitchen sink | Renderuje `Reports.vue` z pełnych `ReportsPageProps` w fixture, z nowym scenariuszem `draft-last-12` | Usuwa kopie i dryf; wzorzec `/dev/ui/auth` (ADR-0056) | Research (Decisions 2) |
| Miejsce na wykres | `reportChartHeightRem` w `@/lib/report-chart` i `Skeleton` jako `loadingComponent` z `delay: 0`; Trend `h-64` | Dokładna wysokość jest znana synchronicznie; stałe C4 trafiają do tej samej funkcji | Research (Decisions 3) |
| Legenda w placeholderze | Wspólny `ReportChartLegend` dla wykresu i placeholdera | Rezerwuje wysokość legendy dokładnie, bez kopii znacznika | Plan |
| Udział < 36rem bez porównania | Druga linia pod kwotą, „udział X%” | Nazwa dostaje ~184 px zamiast ~96 px | Research (Decisions 4) |
| Udział < 36rem z porównaniem | Trzecia linia pod kwotą; rok wcześniej i różnica zostają w drugiej | Ta sama reguła co bez porównania, układ ADR-0074 zostaje | Plan (pytanie) |
| Zapis decyzji | Jeden nowy ADR-0083, który dopisuje się do ADR-0070, 0071, 0074 i 0081 | W repo nowy ADR zwykle „adds to” starsze; zmienia zakres S-32 | Plan |
| Test stanów draft / bez Budżetu | E2E na scenariuszach sinka (prawdziwa strona) | Baza E2E nie ma draftu ani użytkownika bez Budżetu; propsy serwera już testuje `ReportsPageTest` | Plan |

## Scope

**In scope:**
- `Reports.vue`, `ReportBarChart`, `ReportTrendChart`, `ReportTrendSummary`, `ReportCategoryRow`, `ReportGroupRow`, `ReportEntryTable`
- nowe komponenty `ReportChartLegend`, `ReportBarChartPlaceholder`, `ReportTrendChartPlaceholder`
- `@/lib/report-chart`, fixture i sink
- `.report-grid` w `app.css`, klucz `reports.share_label`
- ADR-0083, strażniki, `.ai/rules/reports.md`, listy sinków
- nowy `tests/e2e/reports-view.spec.ts`

**Out of scope:**
- serwer, propsy Inertia, link do „12 miesięcy” spoza przełącznika
- `errorComponent` dla nieudanego fragmentu
- zmiany w `CategoryChip`, `components/ui/*` i tabeli Trendu
- draft w bazie E2E

## Architecture / Approach

Faza 1 zamienia sink w powłokę, która renderuje `<Reports v-bind="scenario.props" />` w każdej sekcji. Od tej chwili sink pokazuje dokładnie to, co strona, i może być bramką E2E dla stanów, których nie ma w bazie E2E.

Fazy 2–4 zaczynają od testu, który na obecnym kodzie nie przechodzi:
- **Faza 2:** E2E na scenariuszach sinka, potem jedna zmiana kolejności w szablonie `Reports.vue`.
- **Faza 3:** test jednostkowy funkcji wysokości i E2E z wstrzymanym fragmentem, potem placeholdery jako `loadingComponent`. Vue podaje im propsy wykresu, więc same liczą swoją wysokość.
- **Faza 4:** E2E na ramkach telefonu, potem obszary siatki `@container` w `app.css`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0083 i sink na `Reports.vue` (C3) | Jedno źródło kompozycji, scenariusz `draft-last-12`, ADR przed kodem | Przebudowa ~30 scenariuszy fixture na pełne propsy |
| 2. Wiersz tytułu w każdym stanie (C1) | `h1` i przełącznik nad kartką draftu, bez drogi w jedną stronę | E2E na sinku, a nie na prawdziwym drafcie z bazy |
| 3. Miejsce na wykres (C2 + C4) | Placeholdery o dokładnej wysokości, stałe w `@/lib/report-chart` | Bez `delay: 0` albo z innym warunkiem obecności przeskok zostaje |
| 4. Udział pod kwotą < 36rem (C5) i reguły | Pełne nazwy w 22rem, reguły i listy sinków | Obszary siatki dziś zależą od `.report-grid-compare` |

**Prerequisites:** worktree `worktree-reports-view` z buildem (`npm ci`, `npm run build`) i bazą testową `testing_reports_view`. Do E2E potrzebne są dane logowania z głównego `.env`. Do ręcznych kroków potrzebna jest baza podglądu `preview_reports_view` z draftem 2027 i `nobudget@example.com`.

**Estimated effort:** około 2–3 sesji na cztery fazy. Faza 1 i faza 3 są największe.

## Open Risks & Assumptions

- Test E2E przeskoku zakłada nazwy fragmentów `ReportBarChart-*.js` i `ReportTrendChart-*.js` w buildzie, tak jak w pomiarach researchu.
- Fragment, który się nie załaduje, zostawia pulsujący placeholder. Błąd idzie przez obsługę błędów Vue, a tabela nadal niesie dane. To świadomie poza zakresem.
- W wąskim układzie trzeba odpiąć obszary `report-area-*` od `.report-grid-compare`. Przy tym ruchu może się wywrócić wąski układ porównania, więc musi go złapać `reports.spec.ts` i ręczny przegląd `compared-phone`.

## Success Criteria (Summary)

- Użytkownik w drafcie i bez Budżetu zawsze wie, że jest na Raportach. W drafcie przechodzi do „12 miesięcy” i z powrotem samym przełącznikiem.
- Tabela pod wykresem nie przesuwa się przy pierwszym wejściu (≤ 1 px w E2E z wstrzymanym fragmentem).
- Na telefonie każda nazwa kategorii w tabeli wydatków jest czytelna w całości, a sink pokazuje to samo co strona.
