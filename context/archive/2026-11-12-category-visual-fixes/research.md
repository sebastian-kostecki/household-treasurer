---
date: 2026-11-12T13:35:10+01:00
researcher: Claude (Opus 5.5)
git_commit: afdf252
branch: worktree-category-visual-fixes
repository: household-treasurer
topic: "Kategorie: „Operacje” i „Grupa…” z wiersza do menu „⋯”, przypisanie grupy przez okno"
tags: [research, ui, categories, CategoryListRow, CategoryListRowActions, CategoryGroupPicker, dialog]
status: complete
last_updated: 2026-11-12
last_updated_by: Claude (Opus 5.5)
---

# Research: Kategorie — akcje wiersza w menu „⋯”, przypisanie grupy przez okno

**Date**: 2026-11-12T13:35:10+01:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: afdf252 (gałąź `worktree-category-visual-fixes`, baza `development` 042ee43)
**Branch**: worktree-category-visual-fixes
**Repository**: household-treasurer

## Research Question

Widok „Kategorie” (`/categories`): użytkownik nie chce widzieć w wierszu kategorii linku „Operacje →” ani przycisku „Grupa…”. Oba mają trafić do menu „⋯” wiersza; przypisanie grupy ma odbywać się w oknie (modal). Co trzeba zmienić, co tego pilnuje i jakie wzorce repo obowiązują?

**Decyzja użytkownika (2026-11-12, pytanie w /10x-research):** wariant „Okno wyboru grupy” — „⋯” → „Zmień grupę…” otwiera okno z listą „Bez grupy” + grupy tego typu, przyciski Anuluj/Zapisz. Odrzucone: podmenu w „⋯” (przypisanie od razu) i wspólne okno „Zarządzaj grupami” (CRUD grup). Zarządzanie grupami (dodaj / zmień nazwę / usuń) zostaje bez zmian w nagłówku sekcji i menu „⋯” wiersza grupy.

## Summary

- Zmiana jest czysto frontendowa. Endpoint `PATCH categories/{category}/group` (`routes/web.php:81`) z walidacją `category_group_id: present|nullable|integer|exists(user)` (`app/Http/Requests/AssignCategoryGroupRequest.php:26-35`) i akcją zwracającą błędy pod kluczem `category_group_id` (`app/Actions/AssignCategoryGroup.php:26-36`) wystarcza oknu; sukces to toast + redirect na `categories.index` (`app/Http/Controllers/CategoryGroupController.php:59-71,108-116`). Pokrycie: `tests/Feature/CategoryGroupTest.php:123-174`. Nie znaleziono `ConvertEmptyStringsToNull` wyłączonego w `bootstrap/`, `app/`, `config/` — domyślnie działa, więc pusta wartość pola dotrze jako `null` (wniosek z domyślnego middleware Laravel, nie uruchomiony test).
- Żaden dokument nie uzasadnia trzymania linku i „Grupa…” poza menu: oba weszły do wiersza, zanim wiersz miał menu „⋯” (S-18, S-22); S-21 przeniósł do menu edycję/archiwum/scalanie/usuwanie, linku i pickera nie ruszył. Wiążące są dwa powody z S-22: „Grupa…” to droga **z klawiatury** (uchwyt ma `tabindex="-1"`) i **na telefonie** (uchwyt widoczny od `md`). Pozycja w „⋯” z oknem zachowuje obie.
- Wpływ: 4 komponenty (`CategoryListRow`, `CategoryListRowActions`, `CategoryGroupPicker` → nowe okno, `CategoryArchiveSection` pośrednio), siatka `.categories-grid` w `app.css`, 2 kitchen sinki, 4 testy E2E w 3 plikach (+ 2 testy linku), `ui-contract.test.ts`, listy plików w `check:ui` i `CategoriesHardcodedUiTest.php`, `design-system.md:120-121`, 1 tekst w `lang/pl/ui.php`.
- Rzeczywisty wybór do planu: kontrolka wyboru w oknie. Reguła `js-dialogs.md:15` każe użyć `SegmentedControl` `block` z `name`; lista grup ma zmienną długość (0..N), co do poziomego przełącznika pasuje słabo. Alternatywy: `.native-select` (`js.md:58`, proste wyliczenie bez ikon) albo nowy `ui/radio-group` (makieta użytkownika ma radio; reka-ui jest zainstalowane, ale to nowy wzorzec dla `design-system.md`).

## Detailed Findings

### Wiersz kategorii dziś

- `resources/js/components/CategoryListRow.vue:110-128` — komórka `.categories-cell-link`: `TextLink` „Operacje →” (`href` z `operations({ query: categoryAllYearsOperationsQuery(category) })`, `:38-40`, `aria-label` „Operacje kategorii :name, wszystkie lata”) i `CategoryGroupPicker`, renderowany tylko gdy podano `groups` (`:121-126`). Prop `groups` steruje też uchwytem przeciągania (`:79`) — zostaje. Prop `pickerOpen` (`:25-26`) służy tylko kitchen sinkowi.
- `resources/js/components/CategoryListRowActions.vue:49-110` — menu „⋯” (trigger `ghost` `icon-sm`, `aria-label` z nazwą): Edytuj, Archiwizuj / Przywróć, Scal z inną…, Usuń (`can_delete`). Potwierdzenia przez `ConfirmActionDialog` z `v-model:open` (`:111-138`).
- `resources/js/components/CategoryGroupPicker.vue:28-44,56-87` — osobny `DropdownMenu` z „Bez grupy” + grupami, znacznik `Check` z `invisible`; `router.patch(assignGroup.url(...))` od razu po wyborze, błąd jako `toast.error` (fallback `group_assign_failed`). Jedyny użytkownik: `CategoryListRow.vue`.
- `resources/js/components/CategoryArchiveSection.vue:55-61` — wiersze archiwum to `CategoryListRow archived` bez `groups`: mają link „Operacje →”, nie mają pickera. Menu archiwalne potrzebuje więc pozycji „Operacje”.
- `resources/js/pages/Categories.vue:357-364,407-419` — jedyne miejsca użycia wiersza na stronie; przekazują `groups`, `can-merge`, `@edit`, `@merge`.

### Ochrona przed zmianą w trakcie zapisu kolejności

- `context/archive/2026-11-10-category-groups/reviews/impl-review.md:69` — przeciąganie jest wyłączone na czas zapisu (`reorderHttp.processing`, `Categories.vue:344,392`), pozycje menu, w tym „Grupa…”, nie; druga zmiana może nadpisać `treeBeforeChange`. `moveGroup` ma osobny guard (`Categories.vue:268-276`). Dzisiejszy picker go nie ma — nowe okno trzeba z tym zestawić świadomie (np. wyłączyć „Zmień grupę…” w trakcie `reorderHttp.processing`) albo zapisać jako odroczone.

### Siatka i CSS (`resources/css/app.css`)

- `:1301-1313` — `.categories-grid`: telefon `'name actions' / 'years link'`, od `md` `minmax(0,1fr) 220px auto 7rem` z `'name years link actions'`. Kolumna `auto` mieści tylko link i „Grupa…”.
- `:1344-1347` — `.categories-cell-link`, używana wyłącznie w `CategoryListRow.vue:110`.
- `:1316-1324` — `.categories-header-row` (w `Categories.vue:334`, `CategoryArchiveSection.vue:47`) renderuje tylko komórki nazwy i lat; po zmianie kolumn zostaje poprawny.
- Komentarze do poprawy: `:1295-1300` („then years + link”), `:1352-1353` („below it the "Grupa…" menu does the job”).
- Reguły: `css.md:14` (kolumny rosną wagami `fr`, rozmiary to nazwane tokeny); `220px` to istniejący literał niezgodny z tą regułą — poza zakresem, chyba że plan go ruszy przy okazji. Kolumna akcji 7rem pochodzi z czasów trzech ikon (`context/archive/2026-11-11-category-merge/plan.md:14`); dziś mieści jeden przycisk „⋯”.

### Wzorce okna (`*Dialog.vue`)

- Reguły `js-dialogs.md:15`: pole to `FormField`; **wybór w formularzu to `SegmentedControl` `block` z `name`**; „Anuluj” `ghost` (ADR-0045, pilnuje `ui-contract.test.ts:150-184`); zapis pokazuje spinner i blokuje zamknięcie; focus 2 px `outline`; powłoka z `ui/dialog` bez nadpisań; nowe stany w `/dev/ui/dialogs`. `js.md:21` — nie edytować `components/ui/*` dla jednego ekranu (dodanie komponentu nie jest zakazane; precedens: `ui/textarea` w 5f5fc0e, `ui/command` w 732469d).
- Najbliższe szablony:
  - `CategoryGroupFormDialog.vue` — własny `Dialog` + `<Form v-bind>` z Wayfinder `.form()` i propem `form?` dla sinka (`:22-30`), `busy` z `@start/@finish` i blokada zamknięcia (`:64-84`), `@success="closeDialog"` (`:85`), reset w `watch(..., { immediate: true })` (`:53-62`), błędy w `FormField :error` (`:112-127`), Anuluj `ghost` z `clearErrors()` (`:129-144`), spinner (`:145-148`).
  - `CategoryMergeDialog.vue` na `ConfirmActionDialog` — wybór jednej opcji (`CategorySelect name="target_id"` w `FormField`, `:64-91`), reset w `watch([open, source.id])` (`:36-42`). Mniej kodu: spinner, blokada zamknięcia i lista błędów z `ConfirmActionDialog.vue:35-69`.
  - `CategoryListFormDialog.vue:268-286` — precedens `FormField group` + `SegmentedControl block name="rhythm"`.
- Wiązanie: `CategoryGroupController.assign.form({ category: id })` (Wayfinder, `wayfinder.md:12` — kopiuj wzorzec istniejącej strony).

### Kontrolka wyboru grupy

- `resources/js/components/ui` nie ma `radio-group`; w `resources/js` brak `role="radio"`/`type="radio"`. `DropdownMenuRadioItem` istnieje, nieużywany.
- Dostępne: `SegmentedControl` (reka `ToggleGroup`, opcje `{value,label,count?}`, `block`, `name`; brak propu orientacji — sprawdzone `SegmentedControl.vue` defineProps), `.native-select` (`js.md:58`), reka `Select` (`CategorySelect`, `AccountSelect`).
- `reka-ui` `^2.11.0` w `package.json:26`; `npx shadcn-vue add radio-group` dodałby pliki `ui/radio-group` bez nowej zależności npm (wniosek, nie uruchomione). `design-system.md` nie zna radio — byłby to nowy wzorzec do opisania (i ewentualnie ADR wg AGENTS.md).

### Pozycja menu będąca linkiem

- Jedyny precedens: `resources/js/components/UserMenuContent.vue:37-42` — `DropdownMenuItem :as-child="true"` + `<Link class="block w-full cursor-pointer" :href>`; reka nadaje `<a>` rolę `menuitem`, nazwa dostępna = tekst. Inne menu (`BudgetCategoryRowActions.vue:79-115`, `OperationRowMenu.vue:50-60`) mają tylko `@select`.
- Nazwa dostępna triggera „⋯” już niesie nazwę kategorii (`CategoryListRowActions.vue:55-60`), więc pozycja „Operacje” nie potrzebuje `aria-label` z `:name`; „→” w etykiecie menu jest zbędne (wniosek).

### Strażnicy, którzy przypinają obecny układ

E2E (`tests/e2e`):
- `categories.spec.ts:55-61` — test „the link Operacje → leads to Operations…”: `getByRole('link', { name: /^Operacje kategorii Rozrywka/ })` w wierszu. Treść menu jest portalowana poza wiersz, więc zakres wiersza i rola `link` przestaną pasować.
- `categories.spec.ts:505-508` — test scalania klika ten sam link na wierszu celu.
- `categories.spec.ts:176` (komentarz), `:217-229` — przycisk `Grupa kategorii Rozrywka` → `menuitem` grupy.
- `reports.spec.ts:190,204-208` i `:357,371-375`, `month-plan-groups.spec.ts:116-119` — ten sam przepływ „Grupa kategorii …” jako przygotowanie danych. 4 miejsca z tym samym przepływem → kandydat na wspólny helper.

Unit / kontrakt:
- `resources/js/ui-contract.test.ts:474-482` — lista plików z `CategoryListRow.vue` i `CategoryGroupPicker.vue` (`:481`); `:517-527` — test „shows each category with CategoryChip and a Wayfinder link to Operations” wymaga `from '@/routes'` w `CategoryListRow.vue`; po przeniesieniu `operations()` do `CategoryListRowActions.vue` test trzeba przepiąć (zmiana wymagania, nie poprawianie pod kod).
- `scripts/check-ui-literals.mjs:97-110` (`npm run check:ui`, `package.json:11`) i `tests/Unit/CategoriesHardcodedUiTest.php:7-22` — listy plików z `CategoryListRow.vue`, `CategoryListRowActions.vue`, `CategoryGroupPicker.vue`; nowe okno dopisać, usunięty picker wykreślić.
- `tests/Unit/UiTranslationKeysTest.php` — każdy `t.x.y` z `resources/js` musi istnieć w `lang/pl/ui.php`; nieużywanych kluczy nie wykrywa.
- Tylko opisy (docblock): `tests/Feature/CategoryGroupTest.php:13`, `app/Actions/AssignCategoryGroup.php:13`, `resources/js/lib/category-list.ts:11`.

Kitchen sinki:
- `resources/js/pages/dev/CategoriesKitchenSink.vue` — `:46` `pickerCategory`, `:141-167` sekcja `group-states` z `CategoryListRow nested picker-open`; `:252-266` sekcja `row-menu` (menu dostanie Operacje i Zmień grupę…); opisy stanów wspominające link/„Grupa…”: `:52,56,60,64,68,93`. `resources/js/lib/categories-fixtures.ts` — bez zmian (dane grup już są).
- `resources/js/pages/dev/DialogsKitchenSink.vue` (`/dev/ui/dialogs`, `routes/web.php:45-46`) — stany w `states` (`:54-84`), etykiety (`:128-166`), formy `savingForm`/`validateForm` (`:400-406`), `?open=` (`:413-426`), okna grup montowane w `:624-637`. Nowe okno dopisuje swoje stany tym wzorcem.

Dokumentacja i tłumaczenia:
- `context/foundation/design-system.md:120` — opis siatki „(Kategoria | Lata w budżecie | link „Operacje →” i „Grupa…” | akcje …)”, lista pozycji menu bez Operacji i grupy, „kolumna akcji ma 7rem”, focus „linki z obrysem ring”, „Link „Operacje →” prowadzi do Operacji…”. `:121` — „„Grupa…” w wierszu aktywnej kategorii to `CategoryGroupPicker`…; na telefonie… kategorię przenosi to menu”, stan „menu „Grupa…””, lista plików z `CategoryGroupPicker.vue`.
- ADR-0069 (`:7,9`) — tylko reguła zachowania („Assigning a Category to a group puts it at the end of that group; „Bez grupy” puts it at the end of the sequence”); o miejscu kontrolki milczy, zostaje ważny. ADR-0067 — milczy o wierszu. ADR-0078:23 („edit and archive stay icons”) jest już nieaktualny wobec kodu (commit 48a67aa przeniósł wszystko do „⋯”) — niezależnie od tej zmiany.
- `lang/pl/ui.php`: `categories.operations_link` (`:612`), `operations_link_label` (`:613`) — tylko `CategoryListRow.vue`; `group_picker` (`:680`), `group_picker_label` (`:681`, nazwa używana przez 4 testy E2E), `group_none` (`:682`), `group_assign_failed` (`:698`) — tylko `CategoryGroupPicker.vue`; `group_empty_hint` (`:678`) „…albo wybierz „Grupa…” w jej wierszu.” — przestanie być prawdą. `reports.operations_link_label` (`:405`) to inna grupa (Raporty) — nie ruszać.

## Code References

- `resources/js/components/CategoryListRow.vue:38-40,79,110-128` — href Operacji, uchwyt, komórka linku i pickera
- `resources/js/components/CategoryListRowActions.vue:49-138` — menu „⋯” i potwierdzenia
- `resources/js/components/CategoryGroupPicker.vue:28-87` — obecne przypisanie grupy
- `resources/js/components/CategoryArchiveSection.vue:55-61` — wiersze archiwum z linkiem
- `resources/js/pages/Categories.vue:268-276,344,357-364,392,407-419` — guard zapisu kolejności, użycia wiersza
- `resources/css/app.css:1295-1353` — siatka i klasy Kategorii
- `app/Http/Controllers/CategoryGroupController.php:59-71,108-116`, `app/Http/Requests/AssignCategoryGroupRequest.php:26-35`, `app/Actions/AssignCategoryGroup.php:20-40` — backend przypisania
- `resources/js/components/CategoryGroupFormDialog.vue`, `CategoryMergeDialog.vue`, `ConfirmActionDialog.vue`, `CategoryListFormDialog.vue:268-286` — wzorce okien
- `resources/js/components/UserMenuContent.vue:37-42` — pozycja menu jako link
- `tests/e2e/categories.spec.ts:55-61,176,217-229,505-508`, `tests/e2e/reports.spec.ts:204-208,371-375`, `tests/e2e/month-plan-groups.spec.ts:116-119`
- `resources/js/ui-contract.test.ts:474-482,517-527`, `scripts/check-ui-literals.mjs:97-110`, `tests/Unit/CategoriesHardcodedUiTest.php:7-22`
- `resources/js/pages/dev/CategoriesKitchenSink.vue:46,52-93,141-167,252-266`, `resources/js/pages/dev/DialogsKitchenSink.vue:54-166,400-426,624-637`
- `context/foundation/design-system.md:120-121`, `lang/pl/ui.php:612-613,678-698`

## Architecture Insights

- Wiersz dorastał funkcja po funkcji: S-18 (tylko odczyt, link jako jedyna akcja), S-19 (ikony akcji), S-22 („Grupa…” obok, bo menu wiersza nie było), S-21 (jedno menu „⋯”, link i picker pominięte). To klasyczna „przypadkowa architektura” z /10x-ui — widok jest czysty tokenowo (skan wartości: 0 trafień w 7 plikach), problem to rozproszone punkty wejścia akcji.
- Kontrakt: wszystkie akcje wiersza w jednym menu „⋯” (tak już twierdzi `design-system.md:120`), potwierdzenia/formularze w oknach sterowanych `v-model:open` poza treścią menu (treść menu znika po zamknięciu) — wzorzec `CategoryListRowActions.vue:111-138`.
- Okna repo zawsze wysyłają przez `<Form>` z Wayfinder `.form()` i mają prop `form?` dla kitchen sinka; żadne nie używa `router`/`useForm`.

## Historical Context (from prior changes)

- `context/archive/2026-11-09-category-list-after-migration/plan.md:325`, `plan-brief.md:26` — link „Operacje →” wszedł, by pokazać działanie nowego filtra po id po migracji (cel weryfikacyjny); strona była tylko do odczytu. Aktualne: link nadal w wierszu.
- `context/archive/2026-11-10-category-groups/plan-brief.md:22,24,71`, `plan.md:252` — „Grupa…” w wierszu, bo S-22 nie zależał od menu wiersza z S-19; „droga z klawiatury” i „na telefonie zamiast przeciągania”; „S-22 nie przesądza kształtu” relacji z przyszłym menu. Aktualne co do potrzeby (uchwyt `tabindex=-1`, ukryty poniżej `md`), nie co do miejsca.
- `context/archive/2026-11-10-category-groups/reviews/impl-review.md:69` — brak guardu zapisu w pozycjach menu; aktualne.
- `context/archive/2026-11-11-category-merge/plan-brief.md:26`, `plan.md:14,413` — kolumna 7rem uzasadniona trzema ikonami / menu; o kolumnie linku nikt nie decydował.
- PRD `context/foundation/prd-v3.md:159,161,165,202` — FR-001/002/004 bez wymogu widoczności linku ani przypisania; FR-018 dotyczy Raportów. Brak wymogu „jedno kliknięcie”.

## Related Research

- `context/archive/2026-11-10-category-groups/` i `context/archive/2026-11-11-category-merge/` — plany i przeglądy wiersza kategorii.

## Charges

Z wstępnego audytu /10x-ui (2026-11-12), potwierdzone tym badaniem:

1. **Przypadkowa architektura — akcje poza menu.** `resources/js/components/CategoryListRow.vue:110-128`: link „Operacje →” i przycisk „Grupa…” stoją w komórce `.categories-cell-link` obok menu „⋯” (`CategoryListRowActions.vue:49-110`). Wpływ: każdy wiersz ma trzy punkty wejścia akcji, kolumna powtarzanych napisów zagłusza nazwy kategorii; `design-system.md:120` obiecuje jedno menu ze wszystkimi akcjami.
2. **Przypadkowa architektura — drugie menu zamiast okna.** `resources/js/components/CategoryGroupPicker.vue:56-87`: osobne menu na wiersz, przypisanie bez potwierdzenia, ręczny znacznik `Check invisible`, błąd tylko w toaście. Wpływ: łatwo przenieść kategorię przypadkiem; błąd nie stoi przy wyborze. Poprawka: „Zmień grupę…” w „⋯” otwiera okno (decyzja użytkownika); okno zachowuje rolę drogi z klawiatury i z telefonu (`design-system.md:121`, archiwum S-22 `plan-brief.md:24`).
3. **Siatka pod znikającą kolumnę.** `resources/css/app.css:1301-1313,1344-1347`: obszar `link` i kolumna `auto`; druga linia na telefonie `'years link'`; akcje 7rem dla jednego przycisku. Wpływ: po usunięciu linku pusta kolumna / zbędny odstęp, lata ściśnięte na telefonie.
4. **Archiwum też ma link.** `resources/js/components/CategoryArchiveSection.vue:55-61`: wiersz archiwalny pokazuje „Operacje →”. Wpływ: po zmianie bez pozycji „Operacje” w menu archiwalnym użytkownik traci drogę do operacji zarchiwizowanej kategorii.

Odroczone: brak. (Literał `220px` w `.categories-grid`, `app.css:1311`, i nieaktualny ADR-0078:23 są poza tą zmianą — do decyzji planu, czy dotknąć przy okazji.)

## Open Questions

1. **Kontrolka wyboru w oknie** (do planu): `SegmentedControl block name` (zgodne z `js-dialogs.md:15`, słabe dla wielu grup), `.native-select` w `FormField` (`js.md:58`, kompaktowe, bez nowego wzorca) czy nowy `ui/radio-group` (zgodne z makietą użytkownika, wymaga wpisu w `design-system.md`, ewentualnie ADR i wyjątku od `js-dialogs.md:15`).
2. **Szablon okna:** własny `Dialog` jak `CategoryGroupFormDialog` czy nakładka na `ConfirmActionDialog` jak `CategoryMergeDialog`.
3. **Guard zapisu kolejności:** czy „Zmień grupę…” ma być wyłączone w trakcie `reorderHttp.processing` (zalecenie z `impl-review.md:69`), czy odroczyć.
4. **Okno bez grup:** typ bez żadnej grupy — pozycja „Zmień grupę…” ukryta, wyłączona z powodem, czy okno z samym „Bez grupy” i podpowiedzią „Dodaj grupę”.
5. **Kolumna akcji:** zmniejszyć 7rem (np. do szerokości `icon-sm`) czy zostawić.
