# Akcje wiersza kategorii w menu „⋯”, przypisanie grupy przez okno — plan implementacji

## Overview

Widok „Kategorie” (`/categories`): link „Operacje →” i przycisk „Grupa…” znikają z wiersza kategorii i trafiają do menu „⋯”. „Zmień grupę…” otwiera okno z listą radio („Bez grupy” + grupy tego typu) i przyciskami Anuluj/Zapisz. Wiersz traci kolumnę linku, menu staje się jedynym punktem wejścia akcji wiersza, jak już obiecuje `context/foundation/design-system.md:120`. Backend przypisania zostaje bez zmian.

## Current State Analysis

- **Wiersz.** `resources/js/components/CategoryListRow.vue:110-128` ma komórkę `.categories-cell-link` z `TextLink` „Operacje →” (`href` z `operations({ query: categoryAllYearsOperationsQuery(category) })`, `:38-40`) i `CategoryGroupPicker` (tylko przy `groups`). Prop `groups` steruje też uchwytem przeciągania (`:79`), prop `pickerOpen` (`:25-26`) służy tylko kitchen sinkowi. Wiersz archiwalny (`CategoryArchiveSection.vue:55-61`) ma link, nie ma pickera.
- **Menu „⋯”.** `resources/js/components/CategoryListRowActions.vue:49-95`: Edytuj, Archiwizuj / Przywróć, Scal z inną… (wyłączone przy `canMerge === false`), Usuń (`can_delete`). Potwierdzenia to `ConfirmActionDialog` sterowane `v-model:open` poza treścią menu (`:96-123`).
- **Picker.** `resources/js/components/CategoryGroupPicker.vue:27-45` przypisuje od razu po wyborze przez `router.patch(assignGroup.url(...), { category_group_id }, { preserveScroll: true })`, błąd idzie do toastu. Jedyny użytkownik: `CategoryListRow.vue`.
- **Backend.** `PATCH categories/{category}/group` (`routes/web.php:81`), `AssignCategoryGroupRequest.php:26-35` (`category_group_id: present|nullable|integer|exists`), błędy pod `category_group_id` (`AssignCategoryGroup.php:26-36`), sukces: toast + redirect na `categories.index` (`CategoryGroupController.php:59-71`). Testy (`tests/Feature/CategoryGroupTest.php:123-174`) wysyłają `null`; ścieżki pustego tekstu z formularza (`''`, `ConvertEmptyStringsToNull`) nikt nie sprawdził.
- **Zapis kolejności.** Przeciąganie i strzałki grupy czekają na `reorderHttp.processing` (`resources/js/pages/Categories.vue:255-262,349,393`); pozycje menu nie (`context/archive/2026-11-10-category-groups/reviews/impl-review.md:69`).
- **Siatka.** `resources/css/app.css:1301-1313`: telefon `'name actions' / 'years link'`, od `md` `minmax(0,1fr) 220px auto 7rem` z `'name years link actions'`. Wiersz nagłówka (`.categories-header-row`) renderuje tylko komórki nazwy i lat, więc kolumna akcji musi mieć stałą szerokość, inaczej kolumna lat w nagłówku rozjedzie się z wierszami.
- **Kontrolka wyboru.** `components/ui` nie ma `radio-group`. Reguła `.ai/rules/js-dialogs.md:15` każe użyć `SegmentedControl block` dla wyboru w formularzu; lista grup ma zmienną długość (0..N), więc planowanie wybrało radio (patrz Key Decisions w briefie).
- **Strażnicy.** `resources/js/ui-contract.test.ts:474-482` (`CATEGORIES_VIEW_FILES`) i `:517-527` (wiersz importuje `@/routes`); `scripts/check-ui-literals.mjs:97-110`; `tests/Unit/CategoriesHardcodedUiTest.php:7-22`; E2E: `tests/e2e/categories.spec.ts:55-61,217-229,505-508`, `tests/e2e/reports.spec.ts:204-208,371-375`, `tests/e2e/month-plan-groups.spec.ts:116-119`.

## Desired End State

- Wiersz aktywnej kategorii: chip z nazwą, lata, menu „⋯”. Menu: **Operacje** (ikona `ArrowLeftRight`, jak w nawigacji), separator, Edytuj, **Zmień grupę…**, Archiwizuj, Scal z inną…, Usuń (`can_delete`). Wiersz archiwalny: Operacje, separator, Przywróć, Scal z inną…, Usuń.
- „Operacje” przechodzi do Operacji z filtrem tej kategorii za wszystkie lata, tym samym adresem co dziś.
- „Zmień grupę…” jest **nieaktywne**, gdy typ nie ma żadnej grupy, i gdy trwa zapis kolejności (`reorderHttp.processing`). W pozostałych przypadkach otwiera okno „Zmień grupę: <nazwa>” z listą radio: „Bez grupy”, potem grupy typu w kolejności listy. Obecna grupa jest zaznaczona, a „Zapisz” jest nieaktywne, dopóki wybór się od niej nie różni. Zapis pokazuje spinner, blokuje zamknięcie, po sukcesie okno się zamyka, lista się odświeża bez skoku przewijania. Błąd serwera stoi w oknie pod listą.
- Od `md` siatka ma trzy kolumny (nazwa | lata | akcje), akcje w kolumnie o szerokości przycisku `icon-sm`. Na telefonie: chip z „⋯”, pod nim lata.
- `CategoryGroupPicker.vue` nie istnieje. `ui/radio-group` istnieje i jest opisany w „Gotowych elementach” oraz w ADR-0083.
- Weryfikacja: testy Feature, Vitest, `check:ui`, typy, bramka i E2E zielone; `/dev/ui/dialogs` i `/dev/ui/categories` pokazują nowe stany.

### Key Discoveries:

- `CategoryMergeDialog.vue:63-91` to wybór jednej wartości na `ConfirmActionDialog` z `FormField`; ten sam szablon pasuje oknu grupy.
- `ConfirmActionDialog.vue:143-161` wiąże `<Form v-bind="form">` bez opcji wizyty, a Inertia `<Form>` przyjmuje prop `options` (`FormComponentOptions`, w tym `preserveScroll`).
- `SegmentedControl.vue:66` wysyła wartość jawnym `<input type="hidden" :name>` zamiast polegać na reka; ten sam sposób oddziela „Bez grupy” od wartości radia.
- `CategoryListFormDialog.vue:268-286`: `FormField group` + kontrolka z `ariaLabelledby="labelId"`, wzorzec etykiety grupy kontrolek.
- `ui/checkbox/Checkbox.vue` już ma obrys `focus-visible:outline-solid outline-2 outline-ring outline-offset-2` zamiast `ring-[3px]` z shadcn; radio dostaje to samo.
- `UserMenuContent.vue:37-42`: pozycja menu będąca linkiem to `DropdownMenuItem as-child` + `<Link class="block w-full cursor-pointer">`.
- `DialogsKitchenSinkController::validate` (`app/Http/Controllers/Dev/DialogsKitchenSinkController.php:27-49`) czeka 1,5 s i odrzuca każde przesłane pole — gotowe stany „zapis w toku” i „błąd serwera”.
- Playwright: `testDir: './tests/e2e'`, domyślny `testMatch` (`*.spec.ts`), więc plik pomocniczy bez `.spec` w `tests/e2e/support/` nie zostanie potraktowany jako test.

## What We're NOT Doing

- Zarządzanie grupami (dodaj / zmień nazwę / usuń / kolejność) bez zmian: zostaje w nagłówku sekcji i w menu wiersza grupy; żadnego okna „Zarządzaj grupami”, żadnego podmenu w „⋯”.
- Blokada w trakcie zapisu kolejności obejmuje tylko „Zmień grupę…”; Edytuj, Archiwizuj, Przywróć, Scal i Usuń zostają bez niej (reszta uwagi z `impl-review.md:69` zostaje otwarta).
- Żadnych zmian w backendzie przypisania, trasach, propsach Inertia i regule ADR-0069 (miejsce w grupie po przypisaniu).
- Literał `220px` kolumny lat (`app.css:1311`) i nieaktualne ADR-0078:23 („edit and archive stay icons”) zostają, poza zakresem.
- Skrót „Operacje” w menu nie dostaje „→” ani osobnego `aria-label`; nazwę kategorii niesie trigger menu.
- Okno bez grup z podpowiedzią „Dodaj grupę” — odrzucone; pozycja jest wtedy nieaktywna.
- `reports.operations_link_label` (`lang/pl/ui.php:405`) to Raporty — nie ruszać.

## Implementation Approach

Najpierw osobno budujemy i pokazujemy okno (faza 1): test kontraktu pustej wartości, prymityw radio, okno na `ConfirmActionDialog`, ADR i reguła, stany w `/dev/ui/dialogs`. Faza kończy się zieloną bramką bez zmiany widoku dla użytkownika. Potem (faza 2) menu „⋯” przejmuje obie akcje, wiersz traci komórkę linku, picker znika, siatka, teksty, kitchen sink Kategorii, `design-system.md` i E2E zmieniają się w jednym commicie. Dzięki temu żaden commit nie zostawia E2E na nieistniejącym przycisku.

## Critical Implementation Details

### Wartość „Bez grupy”

reka `RadioGroupItem` potrzebuje niepustej wartości, a serwer oczekuje `null`. Radio pracuje na tekstowych wartościach (`'none'` dla „Bez grupy”, `String(group.id)` dla grup), bez `name` na `RadioGroupRoot`. Pole formularza to jawne `<input type="hidden" name="category_group_id">` z `''` dla `'none'`. `ConvertEmptyStringsToNull` zamienia `''` na `null`, a reguła `present` przechodzi, bo pole jest w żądaniu. Pierwszy test fazy 1 przypina ten kontrakt na serwerze.

### Przewijanie po zapisie

Picker przypisywał z `preserveScroll: true`; redirect na `categories.index` bez tej opcji przewija listę na górę. `ConfirmActionDialog` dostaje opcjonalny prop przekazywany do `<Form :options>`. Inne okna zostają bez niego, więc nie zmieniają zachowania.

## Faza 1: Okno „Zmień grupę…” i `ui/radio-group`

### Overview

Nowe okno przypisania grupy, gotowe i pokazane w kitchen sinku okien, z nowym prymitywem radio, zapisaną decyzją projektową i strażnikami. Wiersz jeszcze go nie używa.

### Changes Required:

#### 1. Kontrakt pustej wartości na serwerze

**File**: `tests/Feature/CategoryGroupTest.php`

**Intent**: Okno wysyła „Bez grupy” jako pusty tekst z formularza, nie jako `null` w JSON. Test przypina, że `category_group_id = ''` zdejmuje kategorię z grupy i stawia ją na końcu typu (ADR-0069), tak samo jak `null`.

**Contract**: Nowy test obok `:123-135`: kategoria w grupie, `patch(route('categories.group', $member), ['category_group_id' => ''])` → `assertSessionHasNoErrors()`, `category_group_id` `null`, `sort_order` na końcu. Jeśli test jest czerwony, faza się zatrzymuje: przyczyną jest kontrakt serwera, nie okno.

#### 2. Prymityw `ui/radio-group`

**File**: `resources/js/components/ui/radio-group/RadioGroup.vue`, `RadioGroupItem.vue`, `index.ts` (nowe)

**Intent**: Komponent shadcn-vue `radio-group` (styl `new-york-v4` z `components.json`), dodany CLI `npx shadcn-vue@latest add radio-group` albo przepisany z jego źródła, jeśli CLI w powłoce agenta nie działa. Zależność npm nie dochodzi (reka-ui `^2.11.0`).

**Contract**: Eksporty `RadioGroup`, `RadioGroupItem` na `RadioGroupRoot` / `RadioGroupItem` / `RadioGroupIndicator` z reka. Jedyna zmiana względem shadcn: focus to `focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-offset-2` jak w `ui/checkbox/Checkbox.vue`, bez `ring-[3px]` i `ring-ring/50` (`js-dialogs.md`). Kolory tylko z tokenów (`border-input`, `primary`, `aria-invalid:border-destructive`).

#### 3. Okno przypisania grupy

**File**: `resources/js/components/CategoryGroupAssignDialog.vue` (nowy)

**Intent**: Okno „Zmień grupę: <nazwa>” na `ConfirmActionDialog`, wzorem `CategoryMergeDialog.vue`. Lista radio w `FormField group`, każda opcja z widoczną etykietą (`<label :for>`). Zaznaczenie startuje z obecnej grupy przy każdym otwarciu i zmianie kategorii. „Zapisz” jest nieaktywne, dopóki wybór jest równy obecnej grupie.

**Contract**:
- Props: `category: CategoryListItem | null`, `groups: CategoryGroupItem[]`, `form?: Pick<FormBinding, 'action' | 'method'>` (tylko kitchen sink), `initialGroupId?: number | null` (tylko kitchen sink, wybór inny niż obecny); `v-model:open`.
- Wiązanie: `CategoryGroupController.assign.form({ category: category.id })` (Wayfinder, jak `CategoryGroupPicker.vue:5`), wysyłka przez `ConfirmActionDialog` z zachowaniem przewijania.
- `ConfirmActionDialog` bez `destructive`; `confirm-disabled` przy wyborze równym `category.group_id`; teksty z `t.categories.group_assign_*`, Anuluj `t.categories.cancel`.
- Pole: `<input type="hidden" name="category_group_id">` (patrz „Wartość «Bez grupy»”); `RadioGroup` z `aria-labelledby` z `FormField` (`labelId`).

#### 4. `ConfirmActionDialog` — opcje wizyty

**File**: `resources/js/components/ConfirmActionDialog.vue`

**Intent**: Opcjonalne przekazanie opcji wizyty do `<Form>`, żeby okno grupy zachowało przewijanie jak dzisiejszy picker. Inne okna bez zmian.

**Contract**: Nowy prop `options?: FormComponentOptions` (typ z `@inertiajs/core`, albo `InstanceType<typeof Form>['$props']['options']`) przekazany jako `<Form :options="options">`.

#### 5. Teksty

**File**: `lang/pl/ui.php` (grupa `categories`)

**Intent**: Teksty okna i pozycji menu. Bez usuwania starych kluczy (faza 2).

**Contract**: `group_assign_action` „Zmień grupę…”, `group_assign_title` „Zmień grupę: :name”, `group_assign_description` „Kategoria stanie na końcu wybranej grupy, a bez grupy — na końcu listy typu.”, `group_assign_label` „Grupa”, `group_assign_confirm` „Zapisz”. „Bez grupy” to istniejący `group_none`.

#### 6. Decyzja i reguła

**File**: `context/foundation/adr/0083-a-choice-among-records-in-a-dialog-is-a-radio-group.md` (nowy), `.ai/rules/js-dialogs.md`, `context/foundation/design-system.md` („Gotowe elementy”)

**Intent**: Zapisać nowy wzorzec: wybór jednej pozycji z listy rekordów użytkownika o zmiennej, krótkiej długości (grupy kategorii) w oknie to `ui/radio-group` w `FormField group`, z wartością w jawnym ukrytym polu. `SegmentedControl block` zostaje dla stałych wyliczeń (rytm, typ), `CategorySelect` / reka `Select` dla długich list z ikoną, `.native-select` dla prostych wyliczeń poza oknami.

**Contract**: ADR w stylu repo (reguła, przykład „Prąd” z czterema opcjami, relacja do `js-dialogs.md:15`, odrzucone: `SegmentedControl` — ściska się przy 5+ grupach w 460 px; `.native-select` — opcje schowane, dodatkowe kliknięcie w oknie z jednym polem; podmenu w „⋯” — przypisanie bez potwierdzenia). `js-dialogs.md`: w akapicie o wyborze dopisać wyjątek z odwołaniem do ADR-0083. „Gotowe elementy”: wpis `ui/radio-group` (focus 2 px `outline`, tokeny, gdzie używany). Po edycji reguły `php artisan boost:index-rules`.

#### 7. Stany w `/dev/ui/dialogs`

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Stany okna wzorem okien grup i scalania (`states`, etykiety, `?open=`).

**Contract**: Stany `category-group-assign` (kategoria w „Opłatach stałych”, trzy grupy z `categories-fixtures.ts`, „Zapisz” nieaktywne), `category-group-assign-changed` (`initial-group-id` inny niż obecny, „Zapisz” aktywne), `category-group-assign-saving` i `category-group-assign-error` (oba z `:form="validateForm"`: spinner przez 1,5 s, potem błąd pod listą). Etykiety po polsku jak sąsiednie (`'Grupa kategorii: zmiana grupy'` itd.).

#### 8. Strażnicy

**File**: `scripts/check-ui-literals.mjs`, `tests/Unit/CategoriesHardcodedUiTest.php`, `resources/js/ui-contract.test.ts`

**Intent**: Nowe okno pod tymi samymi strażnikami co reszta widoku; okno grupy zbudowane na `ConfirmActionDialog`.

**Contract**: `CategoryGroupAssignDialog.vue` dopisany do trzech list (w `ui-contract.test.ts` do `CATEGORIES_VIEW_FILES`). Nowy test w `ui-contract.test.ts` obok `:497-505`: okno grupy zawiera `<ConfirmActionDialog` i nie zawiera `DialogContent`.

### Success Criteria:

#### Automated Verification:

- Test pustej wartości przechodzi na PostgreSQL: `docker run … artisan test --compact tests/Feature/CategoryGroupTest.php` (komenda z AGENTS.md, „PostgreSQL tests, worktree”)
- Testy kitchen sinka okien, kluczy tłumaczeń i literałów przechodzą: `php artisan test --compact tests/Feature/DialogsKitchenSinkTest.php tests/Unit/CategoriesHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php`
- Kontrakt UI i strażnik literałów przechodzą: `npm test -- ui-contract` i `npm run check:ui`
- Wayfinder i typy: `npm run build && npm run types:check`
- Bramka: `vendor/bin/pint --dirty --format agent`, `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/ui/dialogs?open=category-group-assign` w trybie jasnym i ciemnym: cztery opcje z etykietami, zaznaczona „Opłaty stałe”, „Zapisz” nieaktywne; po wyborze innej opcji aktywne
- Z klawiatury: Tab wchodzi w listę, strzałki zmieniają wybór, obrys focusu 2 px widoczny na radiu, Escape zamyka okno
- Stan zapisu pokazuje spinner i nie da się zamknąć okna; stan błędu pokazuje komunikat pod listą
- ADR-0083 i wpis w `js-dialogs.md` nie przeczą ADR-0040/ADR-0041 i `design-system.md`

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: Menu „⋯” przejmuje akcje wiersza

### Overview

Wiersz kategorii traci link i „Grupa…”, menu „⋯” dostaje „Operacje” i „Zmień grupę…”, picker znika, siatka traci kolumnę linku. Dokumentacja, kitchen sink, kontrakt UI i E2E zmieniają się razem z wierszem.

### Changes Required:

#### 1. Menu „⋯”

**File**: `resources/js/components/CategoryListRowActions.vue`

**Intent**: Pozycja „Operacje” na górze menu (aktywnego i archiwalnego), separator, potem dotychczasowe pozycje z „Zmień grupę…” po „Edytuj” w menu aktywnym. „Zmień grupę…” otwiera `CategoryGroupAssignDialog` sterowane `v-model:open` poza treścią menu, jak potwierdzenia.

**Contract**:
- Nowe props: `groups?: CategoryGroupItem[]` (grupy typu; bez niego — także w archiwum — pozycji „Zmień grupę…” nie ma), `reorderSaving?: boolean`.
- „Operacje”: `DropdownMenuItem as-child` + Inertia `<Link>` (wzorzec `UserMenuContent.vue:37-42`), `href` = `operations({ query: categoryAllYearsOperationsQuery(category) })` przeniesione z `CategoryListRow.vue:38-40`, ikona `ArrowLeftRight`, tekst `t.categories.operations_action` „Operacje”.
- „Zmień grupę…”: ikona `FolderInput`, `:disabled="groups.length === 0 || reorderSaving"`.
- Okno montowane tylko przy `!archived && groups !== undefined`.

#### 2. Wiersz i sekcje

**File**: `resources/js/components/CategoryListRow.vue`, `resources/js/pages/Categories.vue`, `resources/js/components/CategoryArchiveSection.vue`

**Intent**: Wiersz nie ma komórki `.categories-cell-link`, `TextLink`, `CategoryGroupPicker` ani propu `pickerOpen`; przekazuje `groups` i `reorderSaving` do menu. Strona przekazuje stan zapisu kolejności obu miejscom użycia wiersza. Archiwum bez zmian w markupie (menu archiwalne dostaje „Operacje” samo).

**Contract**: `CategoryListRow` props: usunięty `pickerOpen`, nowy `reorderSaving?: boolean`; `groups` dalej steruje uchwytem (`:79`). `Categories.vue:357-364,407-419`: `:reorder-saving="reorderHttp.processing"`. Docblock propu `groups` bez „Grupa…”.

#### 3. Usunięcie pickera

**File**: `resources/js/components/CategoryGroupPicker.vue` (usunięty)

**Intent**: Okno zastępuje picker, innego użytkownika nie ma.

**Contract**: Plik usunięty i wykreślony z `scripts/check-ui-literals.mjs`, `tests/Unit/CategoriesHardcodedUiTest.php` i `CATEGORIES_VIEW_FILES` w `resources/js/ui-contract.test.ts`.

#### 4. Siatka

**File**: `resources/css/app.css` (`.categories-grid`, `.categories-cell-link`, komentarze `:1295-1300`, `:1352-1353`)

**Intent**: Bez kolumny linku. Kolumna akcji ma szerokość przycisku `icon-sm` (dziś 7rem po trzech ikonach), stałą, żeby nagłówek bez komórki akcji trzymał kolumnę lat w linii z wierszami.

**Contract**: Telefon: `grid-template-columns: minmax(0, 1fr) auto`, obszary `'name actions' / 'years years'`. Od `md`: `minmax(0, 1fr) 220px var(--control-height-sm)`, obszar `'name years actions'`. `.categories-cell-link` usunięta. Komentarze: „then the years”, a pod uchwytem „below it „Zmień grupę…” in the row menu does the job”.

#### 5. Teksty

**File**: `lang/pl/ui.php` (grupa `categories`)

**Intent**: Usunąć klucze, których nikt już nie czyta, i poprawić podpowiedź pustej grupy.

**Contract**: Nowy `operations_action` „Operacje”. Usunięte: `operations_link`, `operations_link_label`, `group_picker`, `group_picker_label`, `group_assign_failed` (sprawdzić `grep -rn` w `resources/js`, `tests` i `app` przed usunięciem). `group_empty_hint` (`:678`): „Przeciągnij tu kategorię albo wybierz „Zmień grupę…” w jej menu.”

#### 6. Kitchen sink Kategorii

**File**: `resources/js/pages/dev/CategoriesKitchenSink.vue`

**Intent**: Stany pokazują nowe menu zamiast pickera.

**Contract**: Sekcja `group-states` bez `picker-open` / `pickerCategory` (okno ma stany w `/dev/ui/dialogs`); opisy stanów `:52,56,60,64,68,93` bez linku i „Grupa…”. Sekcja `row-menu` przekazuje `groups` (menu z „Operacje” i „Zmień grupę…”) i dostaje dwa wiersze: typ bez grup (`:groups="[]"`, „Zmień grupę…” nieaktywne) i zapis kolejności w toku (`reorder-saving`, „Zmień grupę…” nieaktywne). Opis sekcji wymienia te stany.

#### 7. Kontrakt UI

**File**: `resources/js/ui-contract.test.ts:517-527`

**Intent**: Wymaganie się zmieniło: link do Operacji żyje w menu wiersza. Test sprawdza Wayfinder w `CategoryListRowActions.vue`, chip zostaje sprawdzany w `CategoryListRow.vue`.

**Contract**: Tytuł „shows each category with CategoryChip and a Wayfinder link to Operations in its row menu”; `CategoryListRow.vue` zawiera `<CategoryChip`; `CategoryListRowActions.vue` zawiera `from '@/routes'` i nie zawiera `href="/operations`.

#### 8. Design system i opisy

**File**: `context/foundation/design-system.md:120-121`, docblocki `tests/Feature/CategoryGroupTest.php:13`, `app/Actions/AssignCategoryGroup.php:13`, `resources/js/lib/category-list.ts:11`

**Intent**: Opis widoku zgodny z kodem.

**Contract**: `:120`: siatka „Kategoria | Lata w budżecie | akcje”, telefon „chip z akcjami, potem lata”, pełna lista pozycji menu w kolejności z Desired End State, „kolumna akcji ma szerokość przycisku `icon-sm`”, „Operacje” jako pozycja menu. `:121`: zamiast `CategoryGroupPicker` — „Zmień grupę…” w menu „⋯” otwiera `CategoryGroupAssignDialog` (lista radio, ADR-0083), nieaktywne bez grup i w trakcie zapisu kolejności; na telefonie kategorię przenosi to okno; lista stanów i plików z nowym oknem zamiast pickera. Docblocki: „Grupa…” → „Zmień grupę…”.

#### 9. E2E

**File**: `tests/e2e/support/categories.ts` (nowy), `tests/e2e/categories.spec.ts`, `tests/e2e/reports.spec.ts`, `tests/e2e/month-plan-groups.spec.ts`

**Intent**: Jeden helper zamiast czterech kopii przepływu „Grupa kategorii X → menuitem”. Testy linku chodzą przez menu. Ryzyko, przed którym chronią, zostaje to samo.

**Contract**:
- Helper `assignCategoryToGroup(page: Page, row: Locator, categoryName: string, group: string)`: klik `Więcej działań kategorii <nazwa>` w wierszu → `menuitem` „Zmień grupę…” → w `dialog` `radio` o nazwie grupy → „Zapisz” → okno znika. Drugi helper `openCategoryOperations(page, row, name)`: menu → `menuitem` „Operacje”.
- `categories.spec.ts:55-61` (tytuł „the menu item Operacje leads to Operations…”), `:505-508` przez `openCategoryOperations`; `:217-229`, `reports.spec.ts:204-208,371-375`, `month-plan-groups.spec.ts:116-119` przez `assignCategoryToGroup`; komentarz `:176` „Zmień grupę…”.
- Nowy test w `categories.spec.ts`: po przypisaniu do grupy okno ponownie otwarte ma zaznaczoną tę grupę, a wybór „Bez grupy” + „Zapisz” przenosi kategorię na poziom typu (sprząta po sobie jak istniejące testy grup).

### Success Criteria:

#### Automated Verification:

- Kontrakt UI, kitchen sinki, klucze tłumaczeń i literały przechodzą: `npm test -- ui-contract`, `npm run check:ui`, `php artisan test --compact tests/Feature/CategoriesKitchenSinkTest.php tests/Feature/DialogsKitchenSinkTest.php tests/Unit/CategoriesHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php`
- Żaden plik nie odwołuje się do pickera ani usuniętych kluczy: `grep -rn "CategoryGroupPicker\|operations_link\b\|group_picker\|group_assign_failed\|categories-cell-link" resources app tests lang scripts context/foundation` zwraca pusto
- E2E Kategorii, Raportów i grup planu miesiąca przechodzą: `npx playwright test tests/e2e/categories.spec.ts tests/e2e/reports.spec.ts tests/e2e/month-plan-groups.spec.ts`
- Wayfinder i typy: `npm run build && npm run types:check`
- Pełna bramka na PostgreSQL: `docker run … --entrypoint composer sail-8.5/app ci:check` (AGENTS.md, „PostgreSQL tests, worktree”) i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/ui/categories` w trybie jasnym i ciemnym, przy 1280 px i 390 px: wiersz bez linku i „Grupa…”, kolumna lat w linii z nagłówkiem, na telefonie lata pod chipem na całą szerokość
- Menu aktywnego wiersza: Operacje, separator, Edytuj, Zmień grupę…, Archiwizuj, Scal z inną…, Usuń; archiwalnego: Operacje, separator, Przywróć, Scal z inną…, Usuń; „Zmień grupę…” nieaktywne w stanach „typ bez grup” i „zapis kolejności”
- Na dev (podgląd worktree, `context/foundation/agents/worktree-preview.md`): przypisanie kategorii z dołu listy do grupy nie przewija strony na górę, toast potwierdza, kategoria stoi na końcu grupy
- Z klawiatury: Tab na „⋯”, Enter, strzałki do „Zmień grupę…”, Enter, wybór strzałkami, Enter na „Zapisz”; fokus po zamknięciu okna wraca w sensowne miejsce

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed zamknięciem zmiany.

---

## Testing Strategy

### Unit Tests:

- `ui-contract.test.ts`: okno grupy na `ConfirmActionDialog`; link do Operacji przez Wayfinder w menu wiersza; nowe okno pod strażnikiem literałów i bez kwot.
- `CategoriesHardcodedUiTest.php`, `check:ui`: lista plików widoku po zmianie.
- `UiTranslationKeysTest.php`: każdy nowy `t.categories.*` istnieje w `lang/pl/ui.php`.

### Integration Tests:

- Feature: `category_group_id = ''` zdejmuje kategorię z grupy (kontrakt okna z serwerem).
- E2E: przypisanie przez okno (4 dotychczasowe przepływy przez helper), powrót do „Bez grupy” z zaznaczeniem obecnej grupy, „Operacje” z menu prowadzi do Operacji z filtrem po id za wszystkie lata, także po scaleniu.

### Manual Testing Steps:

1. `/dev/ui/dialogs?open=category-group-assign` — wygląd, klawiatura, „Zapisz” nieaktywne bez zmiany.
2. `/dev/ui/dialogs?open=category-group-assign-error` — spinner, potem błąd pod listą.
3. `/dev/ui/categories` przy 390 px — menu z obiema pozycjami, stany nieaktywne.
4. Podgląd worktree: przypisanie z dołu listy bez skoku przewijania; „Operacje” z wiersza archiwalnego.

## References

- Research: `context/changes/category-visual-fixes/research.md`
- Szablon okna wyboru: `resources/js/components/CategoryMergeDialog.vue:63-91`
- Pozycja menu jako link: `resources/js/components/UserMenuContent.vue:37-42`
- Obrys focusu prymitywu: `resources/js/components/ui/checkbox/Checkbox.vue`
- Przeniesienie akcji do „⋯” (S-21): `context/archive/2026-11-11-category-merge/plan.md`
- Grupy kategorii (S-22): `context/archive/2026-11-10-category-groups/plan-brief.md`, `reviews/impl-review.md:69`
- Reguła miejsca w grupie: ADR-0069

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Okno „Zmień grupę…” i `ui/radio-group`

#### Automated

- [x] 1.1 Test pustej wartości przechodzi na PostgreSQL — fce914e
- [x] 1.2 Testy kitchen sinka okien, kluczy tłumaczeń i literałów przechodzą — fce914e
- [x] 1.3 Kontrakt UI i strażnik literałów przechodzą — fce914e
- [x] 1.4 Wayfinder i typy — fce914e
- [x] 1.5 Bramka — fce914e

#### Manual

- [x] 1.6 `/dev/ui/dialogs?open=category-group-assign` w trybie jasnym i ciemnym — fce914e
- [x] 1.7 Z klawiatury: lista radio, strzałki, obrys focusu, Escape — fce914e
- [x] 1.8 Stan zapisu i stan błędu — fce914e
- [x] 1.9 ADR-0083 i wpis w `js-dialogs.md` bez sprzeczności — fce914e

### Phase 2: Menu „⋯” przejmuje akcje wiersza

#### Automated

- [x] 2.1 Kontrakt UI, kitchen sinki, klucze tłumaczeń i literały przechodzą
- [x] 2.2 Żaden plik nie odwołuje się do pickera ani usuniętych kluczy
- [x] 2.3 E2E Kategorii, Raportów i grup planu miesiąca przechodzą
- [x] 2.4 Wayfinder i typy
- [x] 2.5 Pełna bramka na PostgreSQL

#### Manual

- [x] 2.6 `/dev/ui/categories` w trybie jasnym i ciemnym, 1280 px i 390 px
- [x] 2.7 Kolejność pozycji menu i stany nieaktywne „Zmień grupę…”
- [x] 2.8 Przypisanie na dev bez skoku przewijania
- [x] 2.9 Przepływ z klawiatury od „⋯” do „Zapisz”
