---
date: 2026-11-03T13:00:15+01:00
researcher: Sebastian Kostecki (with Claude Opus 5.5)
git_commit: 547d3ef7358dad06a6e6c4217942f3256673179a
branch: development
repository: household-treasurer
topic: "Dashboard — dwukierunkowy audyt widoku według kontraktu 10x-ui"
tags: [research, ui, dashboard, design-system, tokens, states]
status: complete
last_updated: 2026-11-03
last_updated_by: Sebastian Kostecki (with Claude Opus 5.5)
---

# Research: Dashboard — dwukierunkowy audyt widoku według kontraktu 10x-ui

**Date**: 2026-11-03T13:00:15+01:00
**Researcher**: Sebastian Kostecki (with Claude Opus 5.5)
**Git Commit**: 547d3ef7358dad06a6e6c4217942f3256673179a
**Branch**: development
**Repository**: household-treasurer

## Research Question

Dwukierunkowy audyt widoku Dashboard według kontraktu 10x-ui. Potwierdzić lub odrzucić zarzuty 1–5 z wstępnego audytu (wspólny kafelek KPI i reguła koloru dla 0; puste stany vs `IndexEmptyState`; brak podpisów w `CategoryMeterRow` na mobile i token podpisów kolumn; hover na nieinteraktywnych wierszach, fokus notices vs `design-system.md:15`, etykiety vs `Badge`; nawigacja POST przez `budget/year`). Policzyć klasy tokenów i importy `components/ui` w każdym pliku, sprawdzić stany disabled/loading/error widoku i zapisać 3–5 zarzutów z plikiem, linią i wpływem na użytkownika w `## Charges`.

## Summary

- **Wariant kontraktu: istniejący system projektowy.** Wartości są w `resources/css/app.css:99-182` (`:root`, `.dark`), publikowane przez `@theme inline` (`app.css:10-70`). Komponenty bazowe są w `resources/js/components/ui/*`, domenowe w `resources/js/components/`. Zasady: `.docs/design-system.md`, `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/skills/openship-ui-development/SKILL.md`. Żaden z tych plików reguł nie zachęca do wartości jednorazowych.
- **Tokeny są w dobrym stanie.** Skan wartości zakodowanych na sztywno daje 2 trafienia w 8 sprawdzonych plikach, oba w `CategoryMeterRow.vue:56,65` (`text-[11px]`). Faza „wartości tokenów” w planie będzie mała: dotyczy typografii podpisów kolumn, nie kolorów.
- **Zarzuty:** 5 zapisanych w `## Charges`. Cztery są potwierdzone w pełni (C1, C3, C4, C5), jeden częściowo (C2). Pod-zarzut „hover na nieinteraktywnych wierszach” jest **odrzucony** jako zarzut wobec Dashboardu: to udokumentowana konwencja list (`design-system.md:16`); literał `hover:bg-foreground/[0.03]` występuje w 7 plikach `resources/js`, w tym 6 poza Dashboardem. Zostaje uwaga, że wiersze kont nie prowadzą nigdzie, choć mają naturalny cel (sekcja C4).
- **C5 (nawigacja POST) to kandydat na odroczenie.** Rok budżetu jest stanem sesji. Żadna trasa GET nie przyjmuje roku, a ruch przez `budget.select-year` jest zapisaną konwencją (ADR-0031:3, `dashboard-hierarchy.md:48,50,52`). Naprawa wykracza poza „jeden widok”.
- **Decyzje produktowe do podjęcia przed planem:** kolor kwoty 0 (i dodatnich sald) w kafelkach (C1), oraz czy dodać wariant `Badge` w `components/ui` (zmiana dla całej aplikacji, C4).

## Liczby per plik (źródło → widoki)

Zakres: 7 plików widoku i jeden plik pomocniczy z klasami kolorów. Kolumny: liczba instrukcji `import … from '@/components/ui/…'`; liczba wystąpień klas tokenów kolorów (`bg|text|border|ring|fill|stroke|divide|outline` + nazwa roli z `@theme inline`, z modyfikatorem przezroczystości lub bez); trafienia skanu 10x-ui; wartości arbitralne `-[…]` lub `/[…]`.

| Plik | Importy `ui` | Klasy tokenów | Skan | Arbitralne |
| --- | --- | --- | --- | --- |
| `resources/js/pages/Dashboard.vue` | 5 | 10 | 0 | 0 |
| `resources/js/components/DashboardHoldings.vue` | 2 | 16 | 0 | 2 |
| `resources/js/components/DashboardNotices.vue` | 1 | 15 | 0 | 0 |
| `resources/js/components/DashboardCategorySection.vue` | 1 | 11 | 0 | 0 |
| `resources/js/components/BudgetPlanSummaryStrip.vue` | 1 | 6 | 0 | 0 |
| `resources/js/components/CategoryMeterRow.vue` | 1 | 7 | 2 | 2 |
| `resources/js/components/CreateBudgetDialog.vue` | 4 | 1 | 0 | 0 |
| `resources/js/lib/dashboard-categories.ts` | 0 | 15 | 0 | 0 |
| **Razem** | **15** | **81** | **2** | **4** |

- Dwie wartości arbitralne w `DashboardHoldings.vue:189,246` to `hover:bg-foreground/[0.03]`, czyli udokumentowany hover listy (`design-system.md:16`, „hover `foreground/3%`”). Ten sam literał występuje w 6 innych plikach `resources/js` (np. `Accounts.vue:157`, `FinancialOperationRow.vue:49`). To literał bez nazwanej klasy, ale nie dryf wobec kontraktu.
- Dwie pozostałe (`CategoryMeterRow.vue:56,65`) to `text-[11px]`, ten sam rozmiar co nagłówki tabel w `app.css:207`. Nie ma dla niego tokenu ani klasy komponentu (C3).
- Komponenty domenowe z listy `.ai/rules/js.md:20` używane w widoku: `ListSurface` (`DashboardHoldings.vue:169,231`, `DashboardCategorySection.vue:80`), `CategoryChip`, `CategoryMeterRow`, `AccountIdentityBlock`. Nieużywany mimo pustych stanów: `IndexEmptyState` (C2).
- Komponenty współdzielone poza Dashboardem: `BudgetPlanSummaryStrip` jest też na stronie Budżet (`resources/js/pages/Budget.vue:12,274`). Zmiana w nim zmienia Budżet. `CategoryMeterRow` jest używany tylko przez `DashboardCategorySection.vue:3` (grep `CategoryMeterRow` w `resources/js`).

## Charges

### C1 — Brakujący współdzielony komponent: kafelek KPI i kolor kwoty ze znakiem (potwierdzony)

- **Dowód:**
  - Trzy ręcznie skopiowane kafelki `Card` → `CardDescription` → `CardTitle text-3xl tabular-nums`: `resources/js/pages/Dashboard.vue:252-292`, `:294-306`, `:308-320`.
  - Sześć osobnych funkcji „znak → klasa koloru” w widoku: `Dashboard.vue:141-150` (`monthToSpendClass`), `:152-156` (`spendableLiquidityClass`), `:158-160` (`totalFundsClass`), `BudgetPlanSummaryStrip.vue:16-26`, `DashboardHoldings.vue:91-101` (`amountClass`), `:103-113` (`groupTotalClass`). Poza widokiem są jeszcze `Accounts.vue:58,75` i `TermDeposits.vue:75`.
  - Reguły dla zera się różnią. W trzech kafelkach `Dashboard.vue` zero jest zielone (`< 0 ? destructive : success`, `:143-149`, `:153-155`, `:159`). W `BudgetPlanSummaryStrip.vue:16-26` i `DashboardHoldings.vue:91-113` zero nie ma koloru.
- **Wpływ na użytkownika:** saldo 0,00 zł jest zielone w kafelku „Płynność do wydania”, a obok, w sumie kont, szare. Zielony kolor nie mówi więc konsekwentnie „na plusie”.
- **Konflikt w źródłach (decyzja produktowa):**
  - `.docs/design-system.md:28`: `success` oznacza „dochód zaksięgowany, dochód ponad plan” i nie służy do „„wszystko OK” poza kwotami”.
  - `.docs/specs/app-shell-openship-visual-language.md:41`: „income and positive amounts in green”.
  - Zielone zero w kafelku „do wydania” zostało przeniesione bez osobnej decyzji: `context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:38` („kolorowaną jak dziś (`text-success` ≥ 0, `text-destructive` < 0)”).
  - Precedens neutralnego zera jest na Planie miesiąca: `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:28`.
- **Typowa poprawka:** jeden komponent kafelka (np. `StatTile`) i jeden helper tonu kwoty w `resources/js/lib/`, z regułą zapisaną w `design-system.md`. Uwaga: `.ai/skills/openship-ui-development/SKILL.md:55` wyłącza „Dashboard KPI layout” ze swojego zakresu, więc układ kafelków (siatka, kolejność) zostaje bez zmian. Zmienia się tylko budowa z jednego komponentu.

### C2 — Puste stany bez `IndexEmptyState` i bez akcji (potwierdzony częściowo)

- **Dowód:**
  - Brak kont i lokat: `DashboardHoldings.vue:155-166`. Karta pokazuje `t.dashboard.no_accounts` w `CardDescription` i `t.accounts.empty` w akapicie, czyli dwa komunikaty o tym samym i żadnej akcji. Link „Otwórz konta” jest tylko w nagłówku widocznym od `lg` (`DashboardHoldings.vue:122`, `hidden … lg:flex`). Poniżej `lg` pusty panel nie ma żadnego linku.
  - Brak budżetu: `Dashboard.vue:399-418`. Ten stan **ma** akcję (`CreateBudgetDialog` z przyciskiem „Utwórz budżet”), więc pod względem funkcji jest poprawny. Zarzut dotyczy tylko budowy: używa `Card` + `CardDescription` zamiast `IndexEmptyState`, który `.ai/rules/js.md:20` każe sprawdzić najpierw.
  - Faza szkicu: kontroler w tym stanie wysyła puste listy kategorii (`app/Http/Controllers/DashboardController.php:89-110` wypełnia je tylko przy `isExecution()`). Widok pokazuje wtedy tylko `BudgetPlanSummaryStrip` (`Dashboard.vue:421`) i odznakę „Wersja robocza” (`:337`). Sekcje kategorii (`:430`) znikają bez żadnego tekstu.
- **Wpływ na użytkownika:** nowy użytkownik na telefonie widzi przy pustym panelu dwa szare zdania i nie ma stamtąd drogi do dodania konta. Użytkownik z budżetem w szkicu nie wie, dlaczego nie widzi kategorii ani że pojawią się po rozpoczęciu realizacji.
- **Częściowe, bo:** stan „brak budżetu” ma działającą akcję. Zarzut dotyczy tam wyłącznie zgodności z komponentem, nie funkcji.
- **Typowa poprawka:** `IndexEmptyState` z akcją (link do kont) w pustym panelu; krótki komunikat w stanie szkicu z linkiem do budżetu.

### C3 — Wiersze kategorii na telefonie bez podpisów; podpisy kolumn bez tokenu (potwierdzony)

- **Dowód:**
  - `CategoryMeterRow.vue:55-66`: podpisy „Plan” i „Wykonanie” mają `md:hidden`, ale ich komórki mają `max-md:hidden`. Podpis nie jest więc widoczny przy żadnej szerokości (martwy markup). Ten sam wzorzec działa w `ImportPreviewRow.vue:296` (`xl:hidden` w komórce widocznej poniżej `xl`).
  - `CategoryMeterRow.vue:80-82`: podpis „Pozostało” ma klasę `hidden`, czyli `display:none`, więc nie dociera też do czytnika ekranu.
  - `resources/css/app.css:447-449`: `.category-meter-header-row` ma `display: none` poniżej `md`. Na telefonie siatka ma obszary `'category remaining' / 'bar percent'` (`app.css:425-431`), więc kwota stoi bez podpisu. Tak samo wiersz sumy w `DashboardCategorySection.vue:113-178`.
  - Spec `dashboard-hierarchy.md:37` (historyjka 23) chce na telefonie dwóch linii „Category and remaining amount; bar with percent…”, ale nie wymaga, by kwota była bez podpisu. Ostatnie uzasadnienie ukrycia podpisów („values are self-explanatory from the dropdown selection”, commit 4f8db9f) dotyczyło przełącznika kolejności, którego już nie ma (`dashboard-hierarchy.md:43`).
  - Typografia podpisów kolumn: `design-system.md:16` mówi „nagłówki kolumn 11 px wersalikami w `muted-foreground`”. W widoku są cztery inne wersje: `text-xs … tracking-wide uppercase` w `DashboardHoldings.vue:179,236` i `DashboardCategorySection.vue:87`, `text-xs … tracking-wider uppercase` w `BudgetPlanSummaryStrip.vue:34`. 11 px występuje tylko jako literał (`app.css:207`, `CategoryMeterRow.vue:56,65`). Nie ma klasy ani tokenu, który by to opisał.
- **Wpływ na użytkownika:** na telefonie użytkownik widzi przy kategorii „1 234,00 zł · pasek · 63%” i musi zgadywać, czy to plan, wykonanie czy pozostała kwota. Czytnik ekranu czyta same liczby. Podpisy kolumn różnią się rozmiarem i rozstawem liter między kartami na tym samym ekranie.
- **Typowa poprawka:** jedna klasa podpisu kolumny w `app.css` (`@layer components`), użyta w nagłówkach i podpisach mobilnych; widoczny (lub co najmniej dostępny dla czytnika) podpis „Pozostało” na telefonie; usunięcie martwych podpisów lub przywrócenie ich widoczności.

### C4 — Fokus i etykiety powiadomień odbiegają od kontraktu (potwierdzony; pod-zarzut hover odrzucony)

- **Dowód:**
  - Fokus kart powiadomień: `DashboardNotices.vue:312` ma `focus-visible:ring-2 focus-visible:ring-ring`. Kontrakt `design-system.md:15` to „pierścień 3 px `ring/50`”, i tak mają `buttonVariants` (`components/ui/button/index.ts:6`), `Checkbox.vue:23` i `badgeVariants` (`components/ui/badge/index.ts:8`).
  - Etykiety ważności: `DashboardNotices.vue:274-292` buduje dwie pigułki ręcznie (`rounded-full px-2.5 py-0.5 text-xs font-medium`), czyli powtarza `Badge` (`components/ui/badge/index.ts:8`), który nie ma wariantów warning ani „soft destructive”. ADR-0032:5 wymaga po jednej pigułce na poziom ważności, ale nie mówi, z czego ją zbudować.
  - **Pod-zarzut odrzucony:** hover na wierszach kont i lokat (`DashboardHoldings.vue:189,246`) to konwencja list (`design-system.md:16`), ten sam literał jest w 6 innych plikach `resources/js`. Nie jest to dryf Dashboardu.
  - **Uwaga (nie zarzut):** te wiersze są zwykłymi `div` bez celu, choć cel istnieje. `operations?account_id={id}` jest już używany przez powiadomienie o ujemnym saldzie (`DashboardNotices.vue:213-219`), a `IndexOperationsRequest.php:30-39` przyjmuje też `term_deposit_id`. Dodanie linków to zmiana zachowania, więc decyzja produktowa, nie poprawka UI.
- **Wpływ na użytkownika:** przy nawigacji klawiaturą fokus na kartach powiadomień wygląda inaczej (cieńszy, pełny turkus) niż na przyciskach tuż obok, więc trudniej go śledzić jako jeden wzorzec. Pigułki z ręcznymi klasami rozjadą się z `Badge` przy następnej zmianie jego wyglądu.
- **Zastrzeżenie:** stan disabled kart powiadomień (`disabled:opacity-60`, `DashboardNotices.vue:307-312`) został dodany w poprzedniej zmianie z notatką „not yet checked in a browser” (`context/archive/2026-11-03-month-plan-notices/reviews/impl-review.md:84-88`). Do sprawdzenia w kitchen sink.
- **Typowa poprawka:** klasy fokusu jak w `buttonVariants`; wariant `Badge` dla warning i soft destructive. Dodanie wariantu w `components/ui/badge` zmienia komponent dla całej aplikacji, a `.ai/rules/js.md:20` zabrania edycji `components/ui/*` „for a single screen”. To decyzja do planu (alternatywa: `class` na `Badge` w miejscu użycia).

### C5 — Przypadkowa architektura: nawigacja do Planu miesiąca i Budżetu przez POST (potwierdzony; kandydat na deferred)

- **Dowód:**
  - „Plan miesiąca” w kafelku „do wydania”: `Dashboard.vue:271-290`, `<Form>` POST na `BudgetController.selectYear` z ukrytymi `year` i `destination=month-plan`, wystylizowany na `Button variant="link"`.
  - „Otwórz budżet”: `Dashboard.vue:365-383`, ten sam POST.
  - Karty powiadomień miesięcznych: `DashboardNotices.vue:297-311`, `Link as="button"` z tym samym POST.
  - `BudgetController.php:173-184` zapisuje `session('budget_year')` i przekierowuje. `SelectedBudgetYear.php:24-34` czyta rok tylko z sesji, a przy braku sesji zwraca **najwcześniejszy** rok (`$years[0]`, lista posortowana `orderBy('year')`, `:44`). Żadna trasa GET (`budget`, `month-plan`) nie przyjmuje roku (`routes/web.php:23,28`).
  - Błąd walidacji POST jest cichy. Dwa `<Form>` w `Dashboard.vue` korzystają tylko z `processing` i nie renderują `errors`; `Link` w `DashboardNotices.vue` nie ma `onError`; `selectYear` nie wysyła flasha. Osiągalne tylko przy nieaktualnej karcie (np. budżet usunięty gdzie indziej), bo wszystkie trzy kontrolki mają guardy (`Dashboard.vue:170-172,252,366`).
  - To zapisana konwencja, nie przypadek: ADR-0031:3 („the same `budget.select-year` move as ADR-0019”), `dashboard-hierarchy.md:48,50,52`, `agenda-cash-expectations.md:84`. Specyfikacja nazywa te kontrolki „linkami”, a implementacja to przyciski.
- **Wpływ na użytkownika:** „Plan miesiąca” wygląda jak link, ale nie da się go otworzyć w nowej karcie, skopiować ani otworzyć środkowym przyciskiem; czytnik ekranu ogłasza przycisk. Gdy POST się nie uda, użytkownik zostaje na Dashboardzie bez żadnego komunikatu.
- **Rekomendacja: deferred.** Pełna poprawka (rok w URL, np. `month-plan?year=`) zmienia architekturę roku powłoki, z której korzystają Budżet, Plan miesiąca, Agenda i przełącznik w sidebarze (`BudgetYearSwitcher.vue:46`). To wymaga własnej zmiany i ADR, nie przebiegu UI jednego widoku. W zakresie tej zmiany mieści się najwyżej obsługa błędu (komunikat przy nieudanym POST).

## Macierz 7 stanów — stan obecny

| Stan | Obecnie | Kotwica |
| --- | --- | --- |
| default | z tokenów i komponentów repo; wyjątki: C1 (kopie kafelków), C3 (literał 11 px), C4 (pigułki) | tabela liczb powyżej |
| hover | tokenowy: `hover:bg-foreground/[0.03]` na wierszach (konwencja), `hover:bg-destructive/10` / `hover:bg-warning/10` na kartach powiadomień | `DashboardHoldings.vue:189`, `DashboardNotices.vue:119-123` |
| focus-visible | `Button`, `Checkbox`: 3 px `ring/50`; karty powiadomień: 2 px `ring` (C4) | `DashboardNotices.vue:312` |
| disabled | `processing` na 3 formularzach; wszystkie karty miesięczne zablokowane podczas jednego POST; wygląd niesprawdzony w przeglądarce | `Dashboard.vue:217,286,379`, `DashboardNotices.vue:307-311` |
| error | inline w `CreateBudgetDialog` (`InputError`); ciche niepowodzenie POST `selectYear` (C5) | `CreateBudgetDialog.vue:135,166` |
| empty | brak kont, brak budżetu, szkic, brak kategorii (`DashboardCategorySection.vue:81`), brak powiadomień (sekcja ukryta, `Dashboard.vue:323-324`); patrz C2 | `DashboardHoldings.vue:155-166`, `Dashboard.vue:399-418,430` |
| loading | N/A dla danych strony: brak deferred props w `app/` i brak `<Deferred>` w `resources/js` (wyszukane przez pracownika); spec wyklucza skeletony (`dashboard-hierarchy.md:78`). Jedyny „loading” to stany `processing` (patrz disabled) | `.docs/specs/dashboard-hierarchy.md:78` |

## Code References

- `resources/js/pages/Dashboard.vue:141-160` — trzy funkcje koloru kafelków (zero → `text-success`)
- `resources/js/pages/Dashboard.vue:252-320` — trzy skopiowane kafelki KPI
- `resources/js/pages/Dashboard.vue:271-290,365-383` — nawigacja POST przez `selectYear`
- `resources/js/pages/Dashboard.vue:399-418,430` — stan „brak budżetu” i znikające kategorie w szkicu
- `resources/js/components/BudgetPlanSummaryStrip.vue:16-26,34` — zero bez koloru; podpis `tracking-wider` (komponent także na stronie Budżet)
- `resources/js/components/DashboardHoldings.vue:91-113,122,155-166,179,236` — kolor kwot, link tylko od `lg`, pusty stan, podpisy kolumn
- `resources/js/components/DashboardNotices.vue:274-292,297-312` — ręczne pigułki, fokus 2 px, POST w kartach miesięcznych
- `resources/js/components/CategoryMeterRow.vue:55-66,80-82` — martwe i ukryte podpisy mobilne
- `resources/js/components/DashboardCategorySection.vue:87,113-178` — nagłówek kolumn, wiersz sumy
- `resources/css/app.css:207,425-462` — 11 px w nagłówkach tabel; siatka i nagłówek wierszy kategorii
- `app/Http/Controllers/BudgetController.php:173-184`, `app/Support/SelectedBudgetYear.php:20-46` — rok jako stan sesji
- `app/Http/Controllers/DashboardController.php:89-110` — kategorie tylko w fazie realizacji

## Architecture Insights

- Tokeny kolorów, radius i powierzchnie są spójne z `design-system.md`; dryf Dashboardu jest w **kompozycji** (powtarzane kafelki, helpery tonu, pigułki) i **typografii podpisów**, nie w paletach.
- Rok budżetu to stan sesji współdzielony przez powłokę (`ShellState.php:30`, `HandleInertiaRequests.php:49`). Każde przejście z Dashboardu do widoku „rocznego” musi przejść przez POST. To ogranicza C5 do odroczenia.
- `BudgetPlanSummaryStrip` jest współdzielony z Budżetem. Zmiany typografii w nim (C3) i koloru zera (C1) zmienią też Budżet, co jest zgodne z kierunkiem, ale plan musi to wymienić.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:38` — kafelek „do wydania” zachował kolor „jak dziś” (≥ 0 zielone); bez osobnej decyzji o zerze.
- `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:28` — na Planie miesiąca zero nie ma koloru (precedens dla C1).
- `context/archive/2026-11-03-month-plan-notices/plan.md:152,157,184-185` — karty-przyciski mają wyglądać jak karty-linki; fokus klawiatury „visible” bez specyfikacji grubości.
- `context/archive/2026-11-03-month-plan-notices/reviews/impl-review.md:84-88` — disabled kart dodany, niesprawdzony w przeglądarce.
- `.docs/specs/dashboard-hierarchy.md:37,48-58,70-78` — układ, mobilne wiersze kategorii, ruch POST, wyłączenie skeletonów.
- `.docs/adr/0031-dashboard-always-shows-current-calendar-year.md:3,5`, `.docs/adr/0032-dashboard-notice-severity.md:3,5` — rok kalendarzowy Dashboardu, pigułki po wadze.
- Żaden przegląd w archiwum nie oznaczył elementu Dashboardu jako SKIPPED „kosmetyczny” (przeszukane 11 zarchiwizowanych zmian).

## Related Research

- `context/archive/2026-11-02-to-spend-replaces-remaining/research.md:75,138` — opis koloru kafelka i odczyt reguł `js.md`.

## Open Questions

1. **Kolor zera i dodatnich sald w kafelkach (C1):** neutralne zero zgodnie z `design-system.md:28` i precedensem Planu miesiąca, czy zielone ≥ 0 jak dziś i w `app-shell-openship-visual-language.md:41`? Decyzja produktowa; plan powinien zapisać ją w `design-system.md`.
2. **Wariant `Badge` (C4):** dodać warianty `warning` / soft destructive w `components/ui/badge` (zmiana dla całej aplikacji) czy nadpisać klasy na `Badge` w miejscu użycia?
3. **Wiersze kont jako linki do Operacji (C4, uwaga):** w zakresie tej zmiany czy osobno?
4. **C5:** potwierdzić odroczenie; czy w tej zmianie dodać komunikat przy nieudanym POST?
