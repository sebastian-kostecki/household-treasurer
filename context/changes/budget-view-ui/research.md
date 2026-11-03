---
date: 2026-11-03T13:03:51+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 547d3ef7358dad06a6e6c4217942f3256673179a
branch: worktree-budget-view-ui (odgałęziona z development @ 547d3ef)
repository: household-treasurer
topic: "budget-view-ui: dwukierunkowy audyt widoku /budget względem kontraktu systemu projektowego — zarzuty (plik:linia + wpływ) do planu"
tags: [research, codebase, ui, budget, design-system, budget-category-list, checkbox, reka-ui, empty-state, a11y, mobile]
status: complete
last_updated: 2026-11-03
last_updated_by: Claude (claude-opus-5-5)
---

# Research: budget-view-ui — audyt widoku /budget

**Date**: 2026-11-03T13:03:51+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 547d3ef7358dad06a6e6c4217942f3256673179a (lokalny `development`; kotwice `plik:linia` są lokalne, bez permalinków)
**Branch**: worktree-budget-view-ui (wskazuje na `development` @ 547d3ef)
**Repository**: household-treasurer

## Research Question

Brief z `/10x-ui budget`: dwukierunkowy audyt widoku `/budget` (`resources/js/pages/Budget.vue` + `resources/js/components/Budget*.vue`, `CreateBudgetDialog.vue`) w wariancie „istniejący system projektowy” (tokeny `resources/css/app.css` `:root`/`.dark` → `@theme inline`; komponenty `resources/js/components/ui` + `ListSurface`/`IndexEmptyState`). Potwierdzić skan literałów (0 trafień), zweryfikować pięć wstępnych zarzutów i zapisać je w `## Charges` z plikiem, linią i wpływem na użytkownika. Dodatkowo: wejście z linku z `?month=` / `?show_archived=`, rok bez budżetu, stan ładowania przy zmianie miesiąca. Badanie nie wybiera rozwiązań — to zadanie `/10x-plan`.

## Summary

1. **Tokeny: skan czysty, kategoria „brakujące tokeny” prawie pusta.** Skan wartości zakodowanych na sztywno (regex z `/10x-ui`) daje 0 trafień w każdym z 11 plików widoku (lista w *Detailed Findings → Skan*). Kolory idą przez role (`text-destructive`, `text-success`, `text-muted-foreground`…). Skan nie łapie dwóch drobnych dryfów: `hover:bg-foreground/[0.04]` na uchwytach przeciągania (`BudgetCategoryList.vue:271`, `:622`) wobec `/[0.03]` w kontrakcie listy (`resources/css/app.css:211`) oraz trzech wariantów stylu „kicker” (patrz C4/odroczone).
2. **Przełącznik „Pokaż zarchiwizowane” jest martwy** (`Budget.vue:307-311`): reka-ui 2.11 `CheckboxRoot` nie ma propu `checked` ani zdarzenia `update:checked` — emituje wyłącznie `update:modelValue` (`node_modules/reka-ui/dist/Checkbox/CheckboxRoot.js:73`). Pole jest niekontrolowane: klik zaznacza je wizualnie, ale `toggleShowArchived` (`Budget.vue:143-154`) nigdy się nie wykonuje. Ten sam wzorzec jest w `Accounts.vue:126-127`, `TermDeposits.vue:128-129`, `OperationsImport.vue:354-356`; Dashboard został już raz naprawiony na `v-model` (commit `9abd259`). Żaden test tego nie łapie (brak testów przeglądarkowych).
3. **`BudgetCategoryList.vue` (1015 linii) to dwa równoległe drzewa — desktop (`:212-576`) i mobile (`:578-1012`) — zawsze zamontowane.** Komórki kwot (plan / plan początkowy / bieżący / wykonanie + % / pozostało) są wyrenderowane w 6 kopiach, nagłówek z filtrem w 2. Kopie już się rozjechały (tabela w C2).
4. **Stan „brak budżetu” (`Budget.vue:169-192`) to doraźny `Card max-w-lg` bez `<h1>`**, podczas gdy issue #21 (story 14) wymaga „Budget empty states on the floor”, a pozostałe indeksy używają `IndexEmptyState`. Gałąź pojawia się wyłącznie, gdy użytkownik nie ma żadnego budżetu (`app/Support/SelectedBudgetYear.php:18-34`), a tytuł brzmi „Brak budżetu na wybrany rok” (`lang/pl/ui.php:188`).
5. **Korekta zarzutu 5 z pre-audytu.** Karta na kategorię na telefonie nie jest dryfem względem ADR-0003 — issue #25 dopuszcza „dual stacked card/row layout like Operations”. Zarzutem jest drugi, osobny layout poza `ListSurface` (Operations ma jeden markup z obszarami siatki) — patrz C5.
6. **Architektura wejścia:** trasa jest za `auth` + `verified` (`routes/web.php:19`); `?month=` z wartością spoza 1–12 cicho wraca do miesiąca domyślnego (`app/Support/BudgetViewedMonth.php:9-33`); `?show_archived=1` działa po stronie serwera, ale checkbox i tak renderuje się jako odznaczony, więc widoku archiwalnego nie da się wyłączyć z UI (przełącznik miesięcy przenosi `show_archived` dalej, `BudgetMonthPicker.vue:28-39`). Zmiana miesiąca nie ma stanu oczekiwania poza globalnym paskiem postępu Inertii (`resources/js/app.ts:24-26`).

## Charges

Kolejność według wpływu na użytkownika. Każdy zarzut ma kategorię z `/10x-ui`: **T** — brakujące tokeny, **K** — brakujący współdzielony komponent, **A** — przypadkowa architektura. Stany z macierzy 7-stanowej oznaczono tam, gdzie zarzut ich dotyczy.

### C1 (A) — martwy przełącznik „Pokaż zarchiwizowane”

- **Dowód:** `resources/js/pages/Budget.vue:307-311` — `<Checkbox id="show-archived-budget" :checked="show_archived" @update:checked="toggleShowArchived" />`. Wrapper `resources/js/components/ui/checkbox/Checkbox.vue:9-14` forwarduje tylko `CheckboxRootProps`/`CheckboxRootEmits`. reka-ui 2.11.0: propsy `CheckboxRoot` to `defaultValue, modelValue, disabled, value, id, trueValue, falseValue, asChild, as, name, required` (`node_modules/reka-ui/dist/Checkbox/CheckboxRoot.js:22-72`), jedyne zdarzenie `update:modelValue` (`:73`). Bez `modelValue` stan jest lokalny (`useVModel … passive`, `:80-83`), startuje od `false`, a klik przełącza go bez wywołania handlera (`:104-117`).
- **Wpływ:** użytkownik klika „Pokaż zarchiwizowane”, pole się zaznacza, lista się nie zmienia — nie dotrze do zarchiwizowanych kategorii ani ich nie przywróci z tego widoku. Po wejściu z linku `?show_archived=1` lista pokazuje archiwalne wiersze, a pole jest odznaczone i nie da się go wyłączyć bez edycji URL. Z `preserveState: true` przy zmianie miesiąca lokalne „zaznaczenie” przetrwa obok listy bez archiwalnych wierszy.
- **Stany:** default (rozbieżny z danymi), focus-visible (do sprawdzenia po naprawie).
- **Wzorzec naprawy w repo:** `v-model` jak w `Dashboard.vue:353-355`, `CategoryFormDialog.vue:263`, `ImportPreviewRow.vue:275-278`.
- **Poza zakresem tego widoku → odroczone (patrz niżej):** `Accounts.vue:124-127`, `TermDeposits.vue:126-129`, `OperationsImport.vue:351-356`.

### C2 (K) — komórki kwot ×6 i nagłówek z filtrem ×2 bez wspólnego komponentu; kopie już się rozjechały

- **Dowód — mapa kopii** (`resources/js/components/BudgetCategoryList.vue`):

  | Kopia | Plan (szkic) | Plan początkowy (+hint) | Bieżący | Wykonanie + % | Pozostało |
  |---|---|---|---|---|---|
  | Wiersz desktop | 286-295 | 297-313, `PlanChangedHint` | 314 | 321-354, `text-success` dla przychodu, `percentSuffixClass` | 355, `remainingClass` |
  | Wiersz archiwalny desktop | 501-510 | 512-518, **bez hintu** | 519 | 528-552, **bez `text-success` i bez koloru %** (cały `tr` wyciszony, 487) | 553-560, `remainingClass` — **nadal może być `text-destructive`** |
  | Suma desktop (tfoot) | 379 | 385 | 394 | 401-433, % z **dodatkowym `font-medium`** (421) | 434, `totalsRemainingClass` |
  | Karta mobile | 641-652 | 730-749, **hint luzem w `<p>` (744)**, **na końcu** | 654-667 | 668-712, `font-medium` | 713-729 |
  | Suma mobile (`Card border-dashed`) | 770-779 | 844-857, `font-semibold` | 781 | 793-828, `font-semibold` | 829-843 |
  | Karta archiwalna mobile | 911-922 | 987-1002, bez hintu | 924-937 | 938-969, bez `text-success`; % na sztywno `text-muted-foreground` (958) | 970-986 |

  Nagłówek (tytuł, opis formuły, przycisk dodawania, filtr) jest identyczny w `:214-244` i `:579-602`. Inne powtórzenia: warunek koloru wykonania przychodu ×4 (`:324`, `:404`, `:677`, `:800`); lokalne `remainingClass` (`:140-146`) przesłania helper `lib/dashboard-categories.ts:121` z inną wartością domyślną (`text-muted-foreground` vs `''`); `totalsRemainingClass` (`:148`) powiela `DashboardCategorySection.vue:61`.
- **Rozjazdy już widoczne dla użytkownika:**
  - kolejność kolumn: desktop plan początkowy → bieżący → wykonanie → pozostało (`BudgetCategoryTableHeader.vue:45-68`), mobile bieżący → wykonanie → pozostało → plan początkowy;
  - archiwalna kategoria: kolor % wyciszony, ale „pozostało” może świecić na czerwono (`:553-560`, `:970-986`);
  - wyciszenie archiwum: `text-muted-foreground` na desktopie (`:487`), `opacity-60` na mobile (`:892`), `opacity-70` w `CategoryMeterRow.vue:35`;
  - etykiety mobile nie mają podpowiedzi `ColumnHeaderHint` dostępnych na desktopie.
- **Wpływ:** ta sama kategoria wygląda i czyta się inaczej na telefonie, na desktopie i w archiwum; każda kolejna poprawka (kolor, podpowiedź, nowa kolumna) trafia do jednej z sześciu kopii. Historia pliku (`git log`, 17 commitów od `c59dda2`) pokazuje, że każda funkcja lądowała w obu drzewach osobno.
- **Komponenty, które już istnieją i mogą to pokryć:** `CategoryChip` (ma `size="sm"`, nieużywany tu), `PlanChangedHint`, `ColumnHeaderHint`, wzorzec siatki `.operations-grid` (`resources/css/app.css:297-330`, użycie `FinancialOperationRow.vue:49`) i `.category-meter-row` (`app.css:425+`, `CategoryMeterRow.vue`) — ten drugi nie ma kolumn planu początkowego, akcji ani uchwytu, a `.docs/specs/dashboard-hierarchy.md:57` zastrzega, że „Budget page rows must not change if they share the class”.

### C3 (K + A) — stan „brak budżetu” poza `IndexEmptyState`, bez `<h1>`, z mylącym tytułem

- **Dowód:** `resources/js/pages/Budget.vue:169-192` — `<Card class="w-full max-w-lg">` z `CardTitle` (renderuje `<h3>`) + `CreateBudgetDialog`; `<h1>` istnieje tylko w gałęzi z budżetem (`:197`). `SelectedBudgetYear::resolve` zwraca `null` tylko przy braku jakiegokolwiek budżetu (`app/Support/SelectedBudgetYear.php:18-21`), w przeciwnym razie istniejący rok z sesji albo najstarszy (`:24-34`). Tytuł: `'no_budget' => 'Brak budżetu na wybrany rok'` (`lang/pl/ui.php:188`).
- **Kontrakt:** issue #21 story 14 — „Budget empty states on the floor”; `IndexEmptyState` na liście „sprawdź najpierw” (`.docs/design-system.md:55`, `.ai/rules/js.md:20`). Pustą listę kategorii ten sam widok już renderuje przez `IndexEmptyState` (`BudgetCategoryList.vue:199-209`); MonthPlan dla braku budżetu też (`MonthPlan.vue:455-467`). `max-w-lg` pochodzi z `c5f6690` (sprzed restyle'u #25, który tej gałęzi nie przeniósł).
- **Ograniczenie z kontraktu:** `.docs/specs/copy-categories-on-budget-create.md:25,35` — ta gałąź jest „Budget empty state”, który musi dzielić `CreateBudgetDialog` i payload z przełącznikiem roku w sidebarze.
- **Wpływ:** nowy użytkownik na pierwszym wejściu do Budżetu widzi kartę wyrównaną do lewej, inną niż każdy pusty stan w aplikacji (łącznie z pustą listą kategorii na tej samej stronie); strona nie ma nagłówka dla czytnika ekranu; tekst sugeruje, że wybrał zły rok, choć nie ma żadnego budżetu.
- **Stany:** empty.

### C4 (K + stany) — kontrolki bez nazwy, zgaszony hover, uchwyt bez fokusu, ciche błędy i brak stanów oczekiwania

- **Filtr bez dostępnej nazwy:** `BudgetCategoryList.vue:238-243` i `:597-602` — `Input type="search"` z samym `placeholder` (`t.budget.filter_by_name`), bez `Label`/`aria-label`. `ui/input/Input.vue` przepuszcza atrybuty do `<input>`, więc `aria-label` zadziała. Wpływ: czytnik ekranu ogłasza „pole wyszukiwania” bez nazwy. Stan: focus-visible/nazwa.
- **Wyzwalacz archiwum:** `:456-475` (i mobile `:868-886`) — `<h4>` wewnątrz `<Button>` (nagłówek w treści przycisku traci rolę) oraz `class="… hover:bg-transparent"`. Wariant ghost ma `hover:bg-accent … dark:hover:bg-accent/50` (`ui/button/index.ts:19-20`) — twMerge usuwa tylko pierwszy, więc w jasnym motywie hover znika, a w ciemnym zostaje. Precedens: `MonthPlan.vue:738-747` używa `<span>`. Wpływ: brak widocznej reakcji na najechanie w jasnym motywie, niespójność między motywami. Stan: hover.
- **Uchwyt przeciągania:** `:268-275`, `:619-626` — natywny `<button>` z `aria-label`, ale bez klas `focus-visible` (domyślny obrys przeglądarki zamiast tokenu `--ring`), `rounded-md` (kontrakt: `rounded-xl` dla kontrolek, `.docs/design-system.md:15`, `SKILL.md:30-35`), `hover:bg-foreground/[0.04]` (kontrakt listy `/[0.03]`, `app.css:211`). SortableJS nie daje przestawiania z klawiatury — tab zatrzymuje się na kontrolce, która nic nie robi. Podczas filtrowania uchwyt znika, zostaje pusta komórka `w-10` (`:267`). Stany: focus-visible, hover, disabled.
- **Błąd zmiany kolejności:** `onDragEnd` (`:180-195`) przy wyjątku cicho przywraca poprzednią kolejność — brak komunikatu; `reorderHttp.processing` nieużywane, przeciąganie aktywne w trakcie PATCH. `CategoryController::reorder` zwraca 204 bez flash (`app/Http/Controllers/CategoryController.php:141-155`). Wpływ: użytkownik widzi, że kolejność „odskoczyła”, bez wyjaśnienia. Stany: error, loading.
- **Brak stanu „brak wyników filtra”:** wiersze ukrywane przez `v-show`, sumy liczą zbiór niefiltrowany. Wpływ: po wpisaniu frazy bez trafień zostaje pusta tabela z sumami. Stan: empty.
- **Zmiana miesiąca bez stanu oczekiwania:** `BudgetMonthPicker.vue:28-40` — `router.get` z `preserveState`, bez `processing`/`disabled`; `aria-current` i wariant przycisku zmieniają się dopiero po odpowiedzi (`:54-56`); jedyny wskaźnik to globalny pasek Inertii (`resources/js/app.ts:24-26`). W `BudgetMonthStrip.vue:64-117` blok „Do wydania” zmienia wysokość między stanem `none` (jedna linia) a pozostałymi. Wpływ: klik w miesiąc nie daje natychmiastowej reakcji, a pasek może skoczyć. Stan: loading. (Wniosek z kodu, bez pomiaru w przeglądarce.)
- **Menu akcji wiersza:** `BudgetCategoryRowActions.vue:78-85` — `Button size="icon-sm"` z `aria-label`, bez tooltipa (w odróżnieniu od `IconAction`), ikona bez `aria-hidden`. Niski wpływ.

### C5 (A) — drugi layout mobilny poza `ListSurface`, zawsze zamontowany

- **Dowód:** desktop `<ListSurface class="hidden md:block">` (`BudgetCategoryList.vue:212`); mobile to goły `div.space-y-3` (`:578`) z osobnym `draggable` kart (`:604-759`), sumą w `Card class="border-dashed"` (`:761-861`) i drugim `Collapsible` archiwum (`:863-1012`). Oba drzewa są ukrywane wyłącznie CSS-em, więc w DOM są jednocześnie: 2 instancje `draggable`, 2 pola filtra na tym samym `nameFilter`, 2× `BudgetCategoryRowActions` na kategorię (każdy z dialogami archiwizacji/przywrócenia/usunięcia).
- **Kontrakt — korekta pre-audytu:** issue #25 („Budget lists: quiet tables and mobile rows”, zamknięte) wymaga „Operations-style stacked rows on a phone” i „a dual stacked card/row layout like Operations, not a sideways-only table” — **karta na kategorię jest więc dopuszczona**, a twierdzenie z pre-audytu „wbrew ADR-0003” jest nietrafne w tej części. Operations realizuje to jednak jednym markupem (`.operations-grid` w `ListSurface`, `Operations.vue:226-264`, `FinancialOperationRow.vue:49`); wśród sprawdzonych list `/budget` jako jedyna ma osobne drzewo mobilne (sprawdzone, jeden markup: `CategoryMeterRow.vue:55-65`, `DashboardCategorySection.vue:120-127`, `Accounts.vue:149`, `MonthPlanItemRow.vue:61`). Lista na mobile nie leży na żadnej powierzchni — karty stoją bezpośrednio na szarym tle, wbrew `.docs/design-system.md:16` (listy w `ListSurface`).
- **Wpływ:** na telefonie z ~20 kategoriami na listę użytkownik przewija dziesiątki osobnych kart, zanim zobaczy sumy (osobna karta na końcu); każda kontrolka istnieje w DOM dwa razy (podwójne ID fokusu, podwójne dialogi), a C2 jest bezpośrednią konsekwencją tego podziału.

### Kandydaci drobni (nie osobne zarzuty; plan może je wchłonąć lub odroczyć)

- **T — trzy warianty stylu „kicker”:** `text-xs font-medium tracking-wider uppercase` (`BudgetMonthStrip.vue:42`, `:76`, `BudgetPlanSummaryStrip.vue:34`), `text-[11px] … tracking-wider uppercase` (`app.css:207`), `text-xs … tracking-wide uppercase` (Dashboard).
- **K — powtarzany wzorzec „etykieta + wartość”** (`text-muted-foreground text-xs` + `font-semibold tabular-nums`): mobile kart `BudgetCategoryList.vue:642-645` i dalej, `BudgetPlanSummaryStrip.vue:40-61`, `BudgetMonthResultSummary.vue:30-49`.
- **T — doraźne wypełnienia:** `pt-6` na `CardContent` (`BudgetCategoryList.vue:762`, `BudgetMonthStrip.vue:39`, `BudgetPlanSummaryStrip.vue:31`), `rounded-lg` w `BudgetMonthPicker.vue:57`.

### Odroczone (poza zakresem jednego widoku — nie usuwać)

| Zarzut | Kotwica | Powód odroczenia |
|---|---|---|
| Ten sam martwy `:checked`/`@update:checked` | `Accounts.vue:124-127`, `TermDeposits.vue:126-129` | inne widoki; jedna zmiana = jeden widok. Wpływ identyczny jak C1. |
| `OperationsImport.vue` — `:checked="hasHeader"` | `OperationsImport.vue:351-356`, `:36`, `:362-367` | inny widok, ale **możliwy błąd danych**: ukryte pole formularza reka-ui idzie za stanem wizualnym, a etykiety kolumn (`:124`, `:152`) za nieaktualnym `hasHeader` — import może dostać inny `has_header` niż pokazuje UI (wniosek z kodu). Zalecana osobna zmiana o wyższym priorytecie. |
| Nagłówek strony: `Heading.vue` (`<h2 text-xl mb-8`) vs surowe `<h1 class="text-lg">` na każdej stronie niebędącej ustawieniami vs `.docs/design-system.md:21` (`text-xl`) | `Heading.vue:14-27`, np. `Operations.vue:134` | rozjazd dokumentu, komponentu i kodu dotyczy wszystkich stron — decyzja globalna. |
| Kontrakt listy sam używa wartości arbitralnych (`text-[11px]`, `/[0.03]`, `/[0.02]`) | `app.css:207`, `:211`, `:279` | źródło tokenów, nie widok; zmiana dotknęłaby wszystkich list. |
| Przestawianie kolejności z klawiatury | `BudgetCategoryList.vue:254-265` (vuedraggable) | wymaga nowego mechanizmu (np. „przesuń w górę/dół” w menu akcji) — osobna decyzja produktowa. |
| `?month[]=1` → możliwy 500 | `BudgetController.php:70-74`, `BudgetViewedMonth.php` `?string` | backend, nie UI; nieprzetestowane. |

## Detailed Findings

### Skan wartości zakodowanych na sztywno (kierunek widok → źródło)

Regex z `/10x-ui` uruchomiony na 11 plikach: `pages/Budget.vue`, `components/BudgetCategoryList.vue`, `BudgetCategoryRowActions.vue`, `BudgetCategoryTableHeader.vue`, `BudgetMonthStrip.vue`, `BudgetMonthPicker.vue`, `BudgetPlanSummaryStrip.vue`, `BudgetMonthResultSummary.vue`, `BudgetCategoryAlignPlanAction.vue`, `BudgetYearSwitcher.vue`, `CreateBudgetDialog.vue` — **0 trafień w każdym** (dwa niezależne przebiegi). Użycia klas ról tokenów w tych plikach: 70 (39 w `BudgetCategoryList.vue`); importy z `@/components/ui/`: 27. Linia bazowa dla faz implementacji: **0** — każde trafienie po fazie to regresja.

### Źródło → widoki

- Tokeny: `resources/css/app.css` — `@theme inline` od `:10`, `:root` od `:99`, `.dark` od `:142`, warstwa komponentów od `:193`. Role domenowe `success`, `warning`, `refund`, `transfer` są opublikowane. Podział wartości/publikacji jest poprawny (żadnego surowego koloru w `@theme inline`).
- Komponenty: `resources/js/components/ui/*` (shadcn-vue na reka-ui; nie edytować dla jednego ekranu — `.docs/design-system.md:8`) oraz współdzielone `ListSurface`, `IndexEmptyState`, `CategoryChip`, `CategoryMeterRow`, `IconAction`, `Heading`, `AccountIdentityBlock`, `OperationTypeTile` (`.docs/design-system.md:55`, `.ai/rules/js.md:18-20`).
- Użycie na stronach (bez ustawień/auth): `IndexEmptyState` — Accounts, MonthPlan ×3, Operations ×2, TermDeposits, Budget (tylko w `BudgetCategoryList`); `ListSurface` — Accounts, MonthPlan, Operations ×3, OperationsImportPreview, TermDeposits, Budget (tylko desktop); `Heading` — żadna strona poza ustawieniami.

### Architektura wejścia

- Ochrona: `Route::middleware(['auth', 'verified'])` (`routes/web.php:19`), `budget` w `:23`; gość → logowanie (`tests/Feature/BudgetTest.php:19-23`). Ścieżka niezweryfikowanego e-maila niesprawdzona.
- `?month=`: `BudgetViewedMonth::resolve` wymaga `ctype_digit` i 1–12, w przeciwnym razie cicho: bieżący miesiąc dla bieżącego roku, styczeń dla przyszłego, grudzień dla przeszłego (`app/Support/BudgetViewedMonth.php:9-33`); test dla `'nope'` (`tests/Feature/BudgetMonthRemainingTest.php:83-97`), brak testu dla 0/13. Szkic przyjmuje dowolny miesiąc; `monthToSpend` jest `null` dla szkicu (`BudgetController.php:128-130`), więc `BudgetMonthStrip` renderuje się zawsze, gdy budżet istnieje, bez bloku „Do wydania” w szkicu.
- `?show_archived=1`: `$request->boolean('show_archived')` (`BudgetController.php:68`), filtr `:82-86`; testy serwera: `ArchiveCategoryTest.php:92-109`, `BudgetTest.php:875-895`, `BudgetMonthResultTest.php:116` — żaden nie dotyka wiązania Vue.
- Rok bez budżetu: niemożliwy przez UI — `selectYear` waliduje `exists:budgets,year` dla użytkownika (`BudgetController.php:173-184`); `BudgetYearSwitcher` w `AppSidebar.vue:79` pokazuje „Brak roku budżetu” + tworzenie przy zerze lat (`BudgetYearSwitcher.vue:21-43`), w przeciwnym razie `.native-select` blokowany w trakcie wysyłki (`:44-89`, `:58`).

### Pliki zasad agenta i miejsce na zabezpieczenie

- `CLAUDE.md` jest w `.gitignore` (`.gitignore:35`) i w całości składa się z bloku 10x-cli — reguła tam byłaby lokalna i nadpisywana.
- `AGENTS.md` (śledzony) kieruje trwałe reguły do `.ai/rules/<area>.md` (`AGENTS.md:77`); blok `<laravel-boost-guidelines>` zaczyna się w `:93` (generowany).
- Najlepsze miejsce na regułę UI: `.ai/rules/js.md` (glob `resources/js/**/*.vue`, `.ai/rules/index.md`), z odbiciem w `.docs/design-system.md` (`:65` — reguły agenta mają być zsynchronizowane z tą stroną). Żadna obecna reguła nie zachęca do wartości jednorazowych.
- Kontrola: `vp check` (oxlint, `vite.config.ts:57-74`, `denyWarnings: true`) — nie sprawdzono, czy oxlint widzi klasy w szablonach Vue. Alternatywa: test `vp test` (`*.test.ts`) skanujący wskazane pliki `.vue`; `npm test` i `npm run check` są w CI przez `composer ci:check` (`.github/workflows/tests.yml:90-91`, `composer.json:67-73`). Brak hooka pre-commit.
- Bramka wizualna: w repo nie ma kitchen sink ani Playwrighta; specyfikacje wprost odrzucają asercje zrzutów ekranu (`.docs/specs/app-shell-openship-visual-language.md:73`, `copy-categories-on-budget-create.md:44`, `dashboard-hierarchy.md:64`, issue #25). Jedyny podgląd to zewnętrzny canvas „Treasurer Design System”, oznaczony jako „tylko podgląd, nie źródło wartości” (`.docs/design-system.md:3`).

## Code References

- `resources/js/pages/Budget.vue:143-154` — `toggleShowArchived` (nieosiągalny)
- `resources/js/pages/Budget.vue:169-192` — gałąź „brak budżetu” (Card, bez h1)
- `resources/js/pages/Budget.vue:307-311` — martwy Checkbox
- `resources/js/components/ui/checkbox/Checkbox.vue:9-14` — forwardowanie propsów reka-ui
- `node_modules/reka-ui/dist/Checkbox/CheckboxRoot.js:22-83` — propsy, emit `update:modelValue`, `useVModel`
- `resources/js/components/BudgetCategoryList.vue:140-169` — lokalne helpery kolorów (`remainingClass`, `totalsRemainingClass`, `totalsAreOverLimit`)
- `resources/js/components/BudgetCategoryList.vue:180-195` — `onDragEnd` z cichym rollbackiem
- `resources/js/components/BudgetCategoryList.vue:212-576` — drzewo desktop
- `resources/js/components/BudgetCategoryList.vue:578-1012` — drzewo mobile
- `resources/js/components/BudgetCategoryList.vue:456-475` — wyzwalacz archiwum (`h4` w `Button`, `hover:bg-transparent`)
- `resources/js/components/BudgetCategoryTableHeader.vue:45-68` — kolejność kolumn desktop
- `resources/js/components/BudgetMonthPicker.vue:28-57` — nawigacja miesiąca bez stanu oczekiwania
- `resources/js/components/IndexEmptyState.vue:1-25` — pusty stan „na podłodze”
- `resources/css/app.css:193-460` — kontrakt list (`list-surface-table`, `.operations-grid`, `.category-meter-row`)
- `app/Support/SelectedBudgetYear.php:10-35` — rozwiązywanie roku
- `app/Support/BudgetViewedMonth.php:9-33` — rozwiązywanie miesiąca
- `lang/pl/ui.php:188-189` — teksty pustego stanu

## Architecture Insights

- System projektowy jest zdrowy na warstwie tokenów; dryf w `/budget` jest strukturalny (duplikacja drzew i komórek), nie kolorystyczny. Kolejność faz `/10x-ui` (środowisko → tokeny → widok → stany) w tym widoku oznacza praktycznie pustą fazę tokenów — poza ewentualnym ujednoliceniem „kickera” i wyciszenia archiwum, jeśli plan zdecyduje się je wprowadzić jako klasy komponentów w `app.css`.
- Repo ma już dwa wzorce responsywnego wiersza z jednym markupem (`.operations-grid`, `.category-meter-row`); `/budget` jest jedynym wyjątkiem z dwoma drzewami. `.category-meter-row` jest zastrzeżony dla Dashboardu (`dashboard-hierarchy.md:57`), więc wspólny wiersz Budżetu potrzebuje własnych obszarów siatki albo wariantu.
- Błąd `:checked` to klasa błędu migracji radix-vue → reka-ui; naprawiono go raz punktowo (`9abd259`) bez reguły, więc wrócił/przetrwał w czterech miejscach — kandydat na regułę w `.ai/rules/js.md` i wpis do `lessons.md`.

## Historical Context (from prior changes)

- `context/archive/**` i `context/foundation/*.md` nie zawierają decyzji o layoucie `/budget`, kartach mobilnych, pustym stanie ani duplikacji (przeszukane: archiwum, `prd.md`, `roadmap.md`, `health-check.md`). Trafienia są domenowe: wiersz „Do wydania” (`context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:8,43-48`), `Collapsible` archiwum jako wzorzec do ponownego użycia (`context/archive/2026-11-02-month-items-plan-vs-actual/research.md:33,115`).
- Źródłem decyzji UI są issue GitHub #21 (stories 13, 14, 16, 17) i #25 — zweryfikowane przez `gh issue view 25` (treść cytowana w C5). Commit `c59dda2` wdrożył #25 jako „desktop list-surface tables, mobile stacked cards, and floor empty states” — od niego pochodzą dwa drzewa. Kryterium #25 „On a phone each list uses a dual stacked card/row layout like Operations” jest spełnione co do kształtu (karty), nie co do sposobu (Operations: jeden markup).
- `9abd259` — wcześniejsza naprawa tego samego błędu Checkboxa na Dashboardzie (supported: commit zastąpił `:checked`/`@update:checked` przez `v-model` tylko w `Dashboard.vue`).

## Related Research

- `context/archive/2026-11-02-month-items-plan-vs-actual/research.md` — wzorzec `Collapsible` archiwum.
- Brak wcześniejszego researchu UI dla `/budget`.

## Open Questions

Wybory dla `/10x-plan` (nierozstrzygnięte przez dokumenty):

1. **Kształt mobile (C5):** jeden markup z siatką obszarów w stylu `.operations-grid` (wiersze w `ListSurface`) czy zachowanie kart, ale jako jedno drzewo z wariantami responsywnymi? Oba spełniają #25; pierwszy usuwa duplikację całkowicie.
2. **Reguła wyciszenia archiwum:** czy w zarchiwizowanej kategorii „pozostało” ma nadal sygnalizować przekroczenie (`text-destructive`), skoro % jest wyciszony? Jeden kolor/`opacity` dla archiwum w całej aplikacji (60 vs 70)?
3. **Tekst pustego stanu (C3):** „Brak budżetu na wybrany rok” jest nieprawdziwy dla jedynego przypadku, w którym się pojawia — nowy klucz tłumaczenia czy zmiana istniejącego (używany też w `ui.php:129`, `:352`)?
4. **Bramka wizualna:** gdzie postawić kitchen sink, skoro specyfikacje odrzucają asercje zrzutów — trasa tylko dla środowiska lokalnego (np. `app()->isLocal()`), ze zrzutami ręcznymi jako dowodem do przeglądu?
5. **Kontrola w lintrze:** test `vp test` skanujący pliki widoku (bez nowej zależności) czy reguła oxlint (niezweryfikowane, czy widzi szablony Vue)?
6. **Zakres C1:** naprawić tylko `/budget` (zgodnie z zasadą jednego widoku) i otworzyć osobną zmianę dla `Accounts`/`TermDeposits`/`OperationsImport`, czy uznać to za błąd jednej klasy i poprawić wszystkie cztery miejsca razem (poza kontraktem `/10x-ui`)?
