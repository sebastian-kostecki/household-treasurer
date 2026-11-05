# Kontrakt modali według Atramentu — CategoryFormDialog jako widok wzorcowy — Implementation Plan

## Overview

Wszystkie modale przechodzą na kontrakt systemu „C · Atrament i morski” (ADR-0040): powłoka `ui/dialog` (460 px, `radius-xl`, `shadow-dialog`, nakładka `scrim`, ramka nad stopką), fokus 2 px w `ui/*`, hover `secondary` i wygląd wyłączonego przycisku. Dochodzą: wspólny `FormField` (etykieta, podpowiedź, błąd z `id`, `aria-describedby`, wariant kwoty z „zł”), pole 34 px tylko w polach formularza oraz `SegmentedControl` `block` jako wybór w formularzu. Widok wzorcowy to `CategoryFormDialog`; w tym samym kontrakcie są `CreateBudgetDialog`, `ConfirmActionDialog` i pięć ręcznych potwierdzeń strony Budżetu. Stany ogląda się w nowym sinku `/dev/ui/dialogs`.

## Current State Analysis

Audyt (`research.md`, zarzuty C1–C5):

- **C1** Powłoka nie czyta tokenów Atramentu: `ui/dialog/DialogContent.vue:37` (`rounded-2xl border-border/60 shadow-lg sm:max-w-lg p-6`), `DialogOverlay.vue:17` (`bg-black/40` mimo tokenu `scrim`, `app.css:63,146,202`), `DialogTitle.vue:19` (`text-lg`), `DialogHeader.vue:13` (wyśrodkowany na telefonie), `DialogFooter.vue:18` (bez ramki; angielskie „Close” `:23`). `DialogScrollContent.vue` nie ma konsumentów.
- **C2** Fokus 3 px `ring/50` w `ui/button/index.ts:7`, `ui/input/Input.vue:28`, `SelectTrigger`, `Checkbox`, `Switch`, `Badge`, `InputOTPSlot`, `navigation-menu`, `app.css:300` (`.native-select`), `app.css:341` (`.segmented-control-item`), X dialogu (`DialogContent.vue:46`), `PasswordInput.vue:36`, `DashboardNotices.vue:331`. Pola mają 32 px (ADR-0037).
- **C3** Brak współdzielonego pola: siedem bloków `div.grid.gap-2 > Label + kontrolka + InputError` (`CategoryFormDialog.vue:180-302`); `InputError.vue:8-9` bez `id`; brak `aria-invalid`; Typ (`:194-207`) i Rodzaj pozycji (`:285-297`) to `native-select` z dwiema opcjami; kwota (`:246-252`) bez kroju mono i „zł”.
- **C4** `syncPlannedAmount` (`CategoryFormDialog.vue:124-130`) po cichu zostawia starą kwotę; brak spinnera i blokady zamykania w trakcie zapisu (`CategoryFormDialog`, `CreateBudgetDialog`); „Anuluj” `secondary`; `Spinner.vue` `aria-label="Loading"`.
- **C5** Pięć ręcznych potwierdzeń (`BudgetCategoryRowActions.vue:134,162,190`, `pages/Budget.vue:208,325`) bez odstępu, spinnera i blokady; podgląd kategorii i siatka ikon z `border-border/50 rounded-xl` (`CategoryFormDialog.vue:166-178`, `CategoryIconPicker.vue:18,24` z literałem `/[0.04]`); `aria-label` koloru = hex (`CategoryColorPicker.vue:28,32`); `CategoryFormDialog`, pickery i `ui/dialog/*` poza `check:ui`; żaden sink nie renderuje dialogu.

## Desired End State

Każdy modal w aplikacji ma powłokę Atramentu w obu motywach: okno 460 px na `card` z pełną ramką, `radius-xl` i cieniem `shadow-dialog`, nakładkę `scrim`, tytuł 15/20 600 wyrównany do lewej także na telefonie i stopkę z ramką u góry na całą szerokość okna. Fokus w całej aplikacji to obrys 2 px `ring`: przyciski z odstępem 2 px, pola bez odstępu, ze schowaną ramką. Przycisk `outline` to `card` + `border` z hoverem `secondary`, a wyłączony przycisk ma `muted` + `muted-foreground`.

Dialog kategorii: podgląd na tle `background` (`radius-md`), pola w `FormField` (34 px, etykieta 12/16 500, podpowiedź i błąd 12/16 powiązane `aria-describedby`, `aria-invalid` przy błędzie), Typ i Rodzaj pozycji jako `SegmentedControl` `block` primary, kwota w JetBrains Mono do prawej z „zł” w polu i komunikatem „Wpisz kwotę, np. 1200,50.” przy złej wartości (wtedy „Zapisz” jest wyłączony), spinner na „Zapisz” i blokada zamykania w trakcie zapisu, „Anuluj” `outline`. `CreateBudgetDialog` ma to samo. Pięć potwierdzeń Budżetu to `ConfirmActionDialog`; „Rozpocznij realizację” pokazuje ostrzeżenie o deficycie jako `Alert` `warning`.

Weryfikacja: `/dev/ui/dialogs` (`local`) otwiera każdy stan przyciskiem albo `?open=<stan>`; zrzuty w `context/changes/category-dialog-atrament/screenshots/`; `check:ui` i `ui-contract.test.ts` obejmują pliki dialogów.

### Key Discoveries:

- Konsumenci owijają nagłówek, pola i stopkę w jeden `<Form class="space-y-4">` (16 plików, `research.md` „Zasięg prymitywów”), więc sekcje Atramentu (head/body/foot z własnym paddingiem) wymagałyby `DialogBody` w każdym z nich; stopka sięgająca krawędzi przez ujemny margines daje ten sam wygląd bez dotykania konsumentów.
- `cn()` to `twMerge(clsx())` (`resources/js/lib/utils.ts:6-8`); `twMerge` nie zna własnych klas wysokości (`h-control`), a `@layer components` przegrywa z utilities — wysokości 34 px nie da się nadpisać klasą ani regułą potomka.
- Tailwind 4.3.4: `outline-none` ustawia `--tw-outline-style: none`, a `outline-2` używa `outline-style: var(--tw-outline-style)`, więc sam `focus-visible:outline-2` nic nie rysuje.
- `@theme inline` publikuje cienie w przestrzeni `--shadow-*`; token w `:root` o tej samej nazwie (`--shadow-dialog: var(--shadow-dialog)`) to cykl.
- `ConfirmActionDialog` ma już `v-model:open`, `busy`, blokadę zamykania i spinner (`ConfirmActionDialog.vue:28-38,44,76`), ale nie ma slotu na treść; „Rozpocznij realizację” ma w treści ostrzeżenie (`pages/Budget.vue:110-130,229-234`).
- `UpdateCategoryRequest.php:35-37`: w realizacji `type` musi równać się obecnemu, więc zablokowany wybór typu nadal musi wysyłać wartość.
- Inertia `Form` z odpowiedzią walidacji ustawia `errors` i `processing` sama; sink może dostać prawdziwe stany przez trasę dev, która czeka i zwraca błędy, jeśli dialog przyjmie nadpisanie `action`/`method`.
- Wzorce: błąd kwoty `AccountFormDialog.vue:50-52,206-224,245`; rejestracja sinka `AccountsKitchenSinkController.php` + `routes/web.php:25-28`; test 404 `tests/Feature/BudgetKitchenSinkTest.php`.

## What We're NOT Doing

- Pól pozostałych dialogów formularzy (`ExpenseFormDialog`, `IncomeFormDialog`, `AccountFormDialog`, `TermDepositFormDialog`, `OwnTransferFormDialog`, `CapitalMovementFormDialog`, `BudgetCategoryAlignPlanAction`, `TwoFactorSetupModal`, `PasskeyItem`, `DeleteUser`): dostają nową powłokę, fokus i stany przycisku z prymitywów, ale `FormField`, 34 px, „Anuluj” `outline` i blokada zamykania — w osobnych zmianach.
- `DialogBody` i przebudowy konsumentów pod sekcje head/body/foot.
- Podglądu „przed → po” z `Dialog/README.md` (dialog kategorii nie liczy odczytu).
- `ui/sheet/SheetOverlay.vue` (`bg-black/80`).
- Pozostałych wariantów `dark:` w `ui/button` (`destructive`, `ghost`) i `ui/input` oraz wyglądu wariantów `destructive`/`ghost` poza fokusem i stanem wyłączonym.
- Nazw ikon w `aria-label` siatki ikon (klucz ikony zostaje).
- Angielskich komunikatów walidacji serwera (`lang/pl/validation.php`).
- Zmian payloadu formularzy, propsów Inertia i kodu serwera poza sinkiem.

## Implementation Approach

Najpierw kontrakt w dokumentach i tokenach (ADR-0040, `design-system.md`, `app.css`), bo `.docs/design-system.md:3` nazywa artefakt Atrament „tylko podgląd, nie źródło wartości”. Potem prymitywy globalnie razem z sinkiem, żeby każdą kolejną fazę oglądać w tym samym miejscu i na nietkniętym konsumencie (`AccountFormDialog`). Następnie elementy pola, potem widoki Budżetu, na końcu straże, reguła i zrzuty.

## Critical Implementation Details

- **Wysokość 34 px przez zmienną kontekstową.** Input, `SelectTrigger` i `.native-select` biorą wysokość ze zmiennej CSS (domyślnie `--control-height`), a `.form-field` ustawia ją na `--field-height`. Pola w wierszach tytułu i komórkach tabel zostają 32/28 px bez zmian w ich kodzie.
- **Fokus przez `outline`, nie `ring-offset`.** `ring-offset` potrzebuje koloru powierzchni (przycisk stoi na `card` i na `background`); obrys z `outline-offset` ma przezroczysty odstęp. Każdy `focus-visible:outline-2` potrzebuje `focus-visible:outline-solid`.
- **Stopka do krawędzi.** `DialogFooter` dostaje ujemny margines równy poziomemu i dolnemu paddingowi `DialogContent`; działa, dopóki stopka jest ostatnim elementem okna — sprawdzić w sinku na `AccountFormDialog` i na `TwoFactorSetupModal`, `PasskeyItem`, `DeleteUser`.
- **Override szerokości.** `TwoFactorSetupModal.vue:114` nadpisuje szerokość klasą `sm:max-w-md`; szerokość okna musi zostać w klasie `max-w-*`, żeby `twMerge` nadal zastępował ją override'em.

## Faza 1: Kontrakt w dokumentach i tokenach

### Overview

ADR-0040 i `design-system.md` zapisują decyzje przed kodem; `app.css` dostaje tokeny i nazwany styl tytułu.

### Changes Required:

#### 1. ADR-0040

**File**: `.docs/adr/0040-dialog-shell-form-fields-and-focus-follow-atrament.md`

**Intent**: Zapisać kontrakt modali i pól według Atramentu jako decyzję dla całej aplikacji, w formacie i języku ADR-0037.

**Contract**: Treść: powłoka 460 px, `card`, pełny `border`, `radius-xl` (12 px), `shadow-dialog` (jasny `0 24px 60px rgba(15, 27, 45, 0.25)`, ciemny `0 24px 60px rgba(0, 0, 0, 0.5)`), nakładka `scrim`, tytuł 15/20 600 wyrównany do lewej, stopka z ramką u góry; pole formularza 34 px tylko w `FormField` (wiersze tytułu i tabel zostają 32/28 px, powód z ADR-0037); fokus 2 px `ring` (przyciski i elementy wyboru z odstępem 2 px, pola bez odstępu z ramką schowaną); `outline` = `card` + `border`, hover `secondary`; wyłączony przycisk `muted` + `muted-foreground` (`ghost` i `link` tylko `muted-foreground`); „Anuluj” `outline` (odstępstwo od `ghost` z `Button/README.md`, za `context/archive/2026-11-02-approved-plan-changes/plan.md:179`); wybór w formularzu = `SegmentedControl` `block` w tonie `primary` (odstępstwo od wyglądu `ht-choice` w `bundle.css`); potwierdzenia, także z treścią (deficyt), przez `ConfirmActionDialog`. Zastępuje część ADR-0037 („the Dialog keeps its radius”) i zdanie „Fokus = pierścień 3 px `ring/50`”. Odrzucone: `DialogBody` w 16 konsumentach, 34 px dla wszystkich pól, `ring-offset`.

#### 2. Strona systemu

**File**: `.docs/design-system.md`

**Intent**: Strona opisuje nowy stan, żeby agent nie czytał starego przepisu.

**Contract**: Linia ADR w nagłówku (`:3`) z ADR-0040; `:15` — `--field-height` 34 px w polach `FormField`, fokus jak w ADR-0040; nowy punkt o dialogu (powłoka, stopka, „Anuluj” `outline`, potwierdzenia przez `ConfirmActionDialog`); `FormField` i `SegmentedControl` `block` w „Gotowe elementy”; `/dev/ui/dialogs` w „Zmiana systemu” pkt 4.

#### 3. Tokeny i styl

**File**: `resources/css/app.css`

**Intent**: Wartości z ADR-0040 jako tokeny, żeby prymitywy nie miały literałów.

**Contract**: W `:root` i `.dark` cień dialogu pod nazwą spoza przestrzeni `--shadow-*` (np. `--dialog-shadow`), publikowany w `@theme inline` jako `--shadow-dialog` (utility `shadow-dialog`); w `@theme inline` `--radius-xl` nazwany względem `--radius` (12 px); w `:root` wymiary bez wariantu `.dark`: `--field-height: 34px`, `--dialog-width: 460px` oraz zmienna wysokości pola (domyślnie `--control-height`); `@utility dialog-title` (15/20, 600).

#### 4. Test tokenów

**File**: `tests/Unit/DesignTokensTest.php`

**Intent**: Pilnować, że cień dialogu ma wartość w obu motywach, a wymiary są w `:root`.

**Contract**: Asercje: cień dialogu w `:root` i `.dark`; `--field-height: 34px` i `--dialog-width: 460px` w `:root`; `--radius-xl` w `@theme inline`.

### Success Criteria:

#### Automated Verification:

- Test tokenów zielony: `./vendor/bin/sail artisan test --compact tests/Unit/DesignTokensTest.php`
- Formatowanie i statyka frontu: `npm run check`

#### Manual Verification:

- ADR-0040 i `design-system.md` opisują wszystkie decyzje z `research.md` „Decisions” oraz odpowiedzi z planowania (hover `outline`, wyłączony przycisk, deficyt jako `warning`, usunięcie `DialogScrollContent`)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: Prymitywy globalnie i sink dialogów

### Overview

Powłoka, fokus i stany przycisku zmieniają się w `ui/*`; sink `/dev/ui/dialogs` pokazuje istniejące dialogi otwarte.

### Changes Required:

#### 1. Powłoka dialogu

**File**: `resources/js/components/ui/dialog/DialogContent.vue`, `DialogOverlay.vue`, `DialogHeader.vue`, `DialogTitle.vue`, `DialogFooter.vue`

**Intent**: Każdy modal dostaje przepis Atramentu bez zmian w konsumentach.

**Contract**: `DialogContent`: `bg-card`, `border`, `rounded-xl`, `shadow-dialog`, szerokość z `--dialog-width` w klasie `max-w-*` (mobilnie 100% − 2 rem), padding 16 px pionowo / 18 px poziomo; X jako przycisk ikony 26 px (`muted-foreground`, hover `secondary` + `foreground`, fokus jak przycisk), bez `opacity-70`, etykieta „Zamknij”. `DialogOverlay`: `bg-scrim`. `DialogHeader`: `gap-1`, tekst do lewej na każdej szerokości. `DialogTitle`: `dialog-title`. `DialogFooter`: ramka u góry, padding 12/18, ujemny margines do krawędzi okna, `gap-2`; przycisk `showCloseButton` z etykietą „Zamknij”.

#### 2. Usunięcie `DialogScrollContent`

**File**: `resources/js/components/ui/dialog/DialogScrollContent.vue`, `resources/js/components/ui/dialog/index.ts`

**Intent**: Bez drugiej powłoki ze starym przepisem.

**Contract**: Plik i eksport usunięte; `grep -r DialogScrollContent resources/js` pusty.

#### 3. Fokus 2 px

**File**: `ui/button/index.ts`, `ui/input/Input.vue`, `ui/select/SelectTrigger.vue`, `ui/checkbox/Checkbox.vue`, `ui/switch/Switch.vue`, `ui/badge/index.ts`, `ui/input-otp/InputOTPSlot.vue`, `ui/navigation-menu/index.ts`, `resources/css/app.css` (`.native-select`, `.segmented-control-item`), `PasswordInput.vue`, `DashboardNotices.vue`, `CategoryColorPicker.vue`, `CategoryIconPicker.vue`

**Intent**: Jeden fokus w aplikacji według ADR-0040.

**Contract**: Przyciski i elementy wyboru: `focus-visible:outline-solid outline-2 outline-ring outline-offset-2` (bez `ring-[3px]`, `ring-3`, `ring/50`); pola (Input, `SelectTrigger`, `.native-select`, `InputOTPSlot`): obrys 2 px `ring`, `outline-offset-0`, ramka `transparent` przy fokusie; `aria-invalid` zostawia ramkę `destructive`. Bez fokusowego pierścienia `destructive` w wariancie `destructive`. `grep -rE 'ring-\[3px\]|ring-3|ring-ring/50' resources/js resources/css` pusty.

#### 4. Przycisk: `outline` i stan wyłączony

**File**: `resources/js/components/ui/button/index.ts`

**Intent**: Przycisk zarysowany i wyłączony jak w `Button/README.md` Atramentu.

**Contract**: `outline`: `border` + `bg-card` + `text-foreground`, hover `bg-secondary`, bez wariantów `dark:`. Zamiast `disabled:opacity-50`: `default`, `destructive`, `outline`, `secondary` przy `disabled` i `aria-disabled="true"` mają `bg-muted`, `border-border`, `text-muted-foreground`; `ghost` i `link` tylko `text-muted-foreground`.

#### 5. Spinner po polsku

**File**: `resources/js/components/ui/spinner/Spinner.vue`

**Intent**: Czytnik ekranu nie czyta angielskiego słowa w polskim UI.

**Contract**: `aria-label="Ładowanie"`.

#### 6. Sink dialogów

**File**: `app/Http/Controllers/Dev/DialogsKitchenSinkController.php`, `routes/web.php`, `resources/js/pages/dev/DialogsKitchenSink.vue`, `tests/Feature/DialogsKitchenSinkTest.php`

**Intent**: Każdy stan dialogu da się obejrzeć i sfotografować bez danych w bazie.

**Contract**: Trasy w grupie `auth` jak `dev.ui.accounts`: `GET dev/ui/dialogs` (`dev.ui.dialogs`, props `categoryAppearanceOptions` z `CategoryAppearance::formOptions()`) i `POST dev/ui/dialogs/validate` (`dev.ui.dialogs.validate`); obie `abort_unless(app()->isLocal(), 404)`. `validate` czeka ok. 1,5 s i rzuca `ValidationException` z komunikatem dla każdego przesłanego pola formularza (bez pól: klucz ogólny). Strona: przyciski otwierające `CategoryFormDialog` (nowa, edycja, typ zablokowany), `CreateBudgetDialog`, `ConfirmActionDialog` (zwykły, destrukcyjny, zapis w toku przez trasę `validate`) i `AccountFormDialog` (konsument odroczony, kontrola powłoki); `?open=<stan>` otwiera stan przy wejściu. Test: obie trasy 404 poza `local`.

### Success Criteria:

#### Automated Verification:

- Test sinka zielony: `./vendor/bin/sail artisan test --compact tests/Feature/DialogsKitchenSinkTest.php`
- Brak starego fokusu: `grep -rE 'ring-\[3px\]|ring-3|ring-ring/50' resources/js resources/css` nic nie zwraca
- Typy i statyka: `npm run build && npm run types:check && npm run check`
- Testy frontu: `npm test`
- Sloppy bez nowych zgłoszeń: `vendor/bin/sloppy ci`
- Pint: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- `/dev/ui/dialogs` w obu motywach: okno 460 px, `radius-xl`, cień, nakładka `scrim`, tytuł do lewej przy 390 px, stopka z ramką na całą szerokość także w `AccountFormDialog`
- Fokus klawiaturą: przycisk ma obrys z odstępem, pole obrys bez odstępu; X dialogu ma widoczny fokus
- „Zapis w toku” w `ConfirmActionDialog` (trasa `validate`): spinner, X ukryty, `Esc` nie zamyka
- Przycisk `outline` na stronie Budżetu („Dodaj kategorię”) i w Operacjach wygląda poprawnie w obu motywach; wyłączony przycisk ma `muted`
- `TwoFactorSetupModal`, `PasskeyItem` i `DeleteUser` (ustawienia) mają poprawną stopkę i szerokość

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 3: Wspólne elementy pola

### Overview

`FormField`, `InputError` z `id`, wariant `block` w `SegmentedControl` i dostępne pickery — gotowe do użycia w dialogach.

### Changes Required:

#### 1. `InputError` z `id`

**File**: `resources/js/components/InputError.vue`

**Intent**: Błąd można powiązać z polem przez `aria-describedby`.

**Contract**: Opcjonalne propsy `id?: string` (na elemencie komunikatu) i `size?: 'sm' | 'xs'` (domyślnie `sm`, dotychczasowy wygląd).

#### 2. `FormField`

**File**: `resources/js/components/FormField.vue`, `resources/css/app.css` (`.form-field`)

**Intent**: Jedno źródło anatomii pola Atramentu (`Field/README.md`) zamiast kopiowanych bloków.

**Contract**: Propsy: `label`, `id?` (domyślnie `useId()`), `hint?`, `error?`, `group?: boolean` (etykieta bez `for` dla grupy kontrolek), `money?: boolean`. Renderuje `.form-field` (kolumna, odstęp 5 px, ustawia zmienną wysokości pola na `--field-height`), etykietę 12/16 500 z `id`, slot kontrolki, podpowiedź 12/16 `muted-foreground` z `id`, `InputError` `size="xs"` z `id`. Slot z zakresem: `{ id, labelId, describedBy, invalid, controlClass }` — `describedBy` łączy `id` podpowiedzi i błędu, gdy są; `invalid` to `'true'` przy błędzie, inaczej `undefined`; `controlClass` przy `money` to klasy znane `twMerge` (`pr-8 text-right font-mono tabular-nums`). `money` renderuje „zł” wewnątrz pola po prawej (`muted-foreground`, bez zdarzeń wskaźnika); jednostka pochodzi z eksportu w `lib/format.ts`, którego używa też `formatMoney`.

#### 3. Wysokość pól ze zmiennej

**File**: `resources/js/components/ui/input/Input.vue`, `resources/js/components/ui/select/SelectTrigger.vue`, `resources/css/app.css` (`.native-select`)

**Intent**: 34 px w `FormField`, 32 px wszędzie indziej, bez klas nadpisujących.

**Contract**: Wysokość czytana ze zmiennej z Fazy 1 (domyślnie `--control-height`); `.form-field` ustawia ją na `--field-height`.

#### 4. `SegmentedControl` `block` i `name`

**File**: `resources/js/components/SegmentedControl.vue`, `resources/css/app.css`

**Intent**: Wybór jednej z kilku opcji formularza jako pełna szerokość z równymi kolumnami, wysyłany z formularzem.

**Contract**: Prop `block?: boolean` → klasa `.segmented-control-block` (pełna szerokość, równe kolumny, wysokość `--field-height`); prop `name?: string` → ukryte pole z bieżącą wartością, wysyłane także przy `disabled`; prop `ariaLabelledby?: string` jako alternatywa dla `ariaLabel` (jedno z dwóch wymagane). Ton `primary` bez zmian.

#### 5. Pickery koloru i ikony

**File**: `resources/js/components/CategoryColorPicker.vue`, `resources/js/components/CategoryIconPicker.vue`, `lang/pl/ui.php`

**Intent**: Czytnik ekranu nazywa grupę i opcję koloru po polsku; siatka ikon bez literałów.

**Contract**: Oba: `role="group"` i prop `ariaLabelledby`. Kolor: `aria-label` „Kolor N” z klucza `budget.category_color_option` („Kolor :number”). Ikony: kontener bez `border-border/50 rounded-xl` (tło `background`, `radius-md`), hover `secondary` zamiast `hover:bg-foreground/[0.04]`.

### Success Criteria:

#### Automated Verification:

- Literały w nowych i zmienionych plikach: `node scripts/check-ui-literals.mjs resources/js/components/FormField.vue resources/js/components/SegmentedControl.vue resources/js/components/CategoryColorPicker.vue resources/js/components/CategoryIconPicker.vue resources/js/components/InputError.vue`
- Typy i statyka: `npm run types:check && npm run check`
- Testy frontu: `npm test`

#### Manual Verification:

- Wiersze tytułu (Budżet, Operacje) i komórki planu miesiąca mają nadal 32/28 px; pola w `AccountFormDialog` (bez `FormField`) mają 32 px
- `SegmentedControl` neutral (zakładki Operacji) i primary (plan miesiąca) wyglądają jak przed zmianą

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 4: Dialogi Budżetu

### Overview

`CategoryFormDialog`, `CreateBudgetDialog` i `ConfirmActionDialog` w kontrakcie; pięć ręcznych potwierdzeń przechodzi na `ConfirmActionDialog`; sink dostaje stany błędów i zapisu.

### Changes Required:

#### 1. `ConfirmActionDialog`

**File**: `resources/js/components/ConfirmActionDialog.vue`

**Intent**: Jedno potwierdzenie dla wszystkich akcji, także z treścią.

**Contract**: Domyślny slot na treść między nagłówkiem a błędami; „Anuluj” `outline`; `Form` z odstępem między sekcjami (nagłówek, treść, stopka nie stoją bez odstępu).

#### 2. `CategoryFormDialog`

**File**: `resources/js/components/CategoryFormDialog.vue`, `lang/pl/ui.php`

**Intent**: Widok wzorcowy kontraktu: pola, wybory, kwota, stany zapisu.

**Contract**: Każde pole przez `FormField` (`error` z `errors.<pole>`, slot wiąże `id`, `aria-describedby`, `aria-invalid`); Typ i Rodzaj pozycji: `SegmentedControl` `block` z `name` (`type`, `month_item_kind`), Typ `disabled` przy `lockType` (zastępuje osobne ukryte pole `type`); Kolor i Ikona: `FormField` `group`, pickery z `ariaLabelledby`; kwota: `FormField` `money`, komunikat klienta `budget.planned_amount_invalid` („Wpisz kwotę, np. 1200,50.”) gdy `parseMoneyInputToMinorUnits` zwraca `null` — przed błędem serwera, „Zapisz” wyłączony (wzorzec `AccountFormDialog.vue:50-52,206-224,245`); podgląd jako `background` + `radius-md` bez ramki; zapis: `busy` z `@start`/`@finish`, blokada zamykania jak `ConfirmActionDialog.vue:32-38,44`, `Spinner` na „Zapisz”; „Anuluj” `outline`. Opcjonalny prop `form?: Pick<FormBinding, 'action' | 'method'>` nadpisuje powiązanie Wayfinder (tylko sink). Nazwy pól payloadu bez zmian.

#### 3. `CreateBudgetDialog`

**File**: `resources/js/components/CreateBudgetDialog.vue`

**Intent**: Drugi dialog formularza Budżetu w tym samym kontrakcie.

**Contract**: Rok i źródło kopii przez `FormField` (opis źródła jako `hint`); blokada zamykania i spinner jak wyżej; „Anuluj” `outline`; opcjonalny prop `form` jak w `CategoryFormDialog`. Payload bez zmian (`context/archive/2026-11-03-budget-view-ui/plan.md:225`).

#### 4. Pięć potwierdzeń na `ConfirmActionDialog`

**File**: `resources/js/components/BudgetCategoryRowActions.vue`, `resources/js/pages/Budget.vue`

**Intent**: Potwierdzenia Budżetu zachowują się jak w Kontach i Lokatach (spinner, blokada, błędy serwera).

**Contract**: Archiwizacja, przywrócenie, usunięcie kategorii: `ConfirmActionDialog` z `v-model:open` (otwierane z `DropdownMenuItem`) i `CategoryController.<akcja>.form(category)`; usunięcie budżetu: `v-model:open`; rozpoczęcie realizacji: slot `trigger` z przyciskiem i ostrzeżenie `deficitWarningMessage` w slocie treści jako `<Alert variant="warning">`. Usunięcia `destructive`. Etykiety i tytuły z obecnych kluczy. Oba pliki nie importują już `DialogContent`.

#### 5. Stany w sinku

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Stany z macierzy dialogu do obejrzenia i zrzutów.

**Contract**: Dochodzą stany: kategoria z błędami pól (prop `form` na trasę `validate`), kategoria z zapisem w toku, kategoria ze złą kwotą (wpisana wartość), rodzaj pozycji przy typie wydatku, `CreateBudgetDialog` z błędami, `ConfirmActionDialog` ze slotem ostrzeżenia (deficyt). Każdy ma klucz `?open=`.

### Success Criteria:

#### Automated Verification:

- Testy kategorii i budżetu (payload): `./vendor/bin/sail artisan test --compact tests/Feature/BudgetTest.php tests/Feature/CategoryAppearanceTest.php tests/Feature/CategoryPacedFlagTest.php tests/Feature/CategoryMonthItemKindTest.php`
- Literały: `node scripts/check-ui-literals.mjs resources/js/components/CategoryFormDialog.vue resources/js/components/CreateBudgetDialog.vue resources/js/components/ConfirmActionDialog.vue resources/js/pages/dev/DialogsKitchenSink.vue`
- Typy i statyka: `npm run build && npm run types:check && npm run check`
- Testy frontu: `npm test`

#### Manual Verification:

- Na stronie Budżetu: dodanie i edycja kategorii zapisują dane jak wcześniej, także w realizacji z zablokowanym typem
- „12,345” i „abc” w kwocie dają komunikat pod polem, `aria-invalid` i wyłączony „Zapisz”; poprawna kwota zapisuje się
- Archiwizacja, przywrócenie, usunięcie kategorii, usunięcie budżetu i rozpoczęcie realizacji (z ostrzeżeniem o deficycie) działają i mają spinner oraz blokadę zamykania
- Czytnik ekranu (lub drzewo dostępności w przeglądarce): pole kwoty ogłasza błąd, grupy Typ, Kolor, Ikona mają nazwę z etykiety, kolor to „Kolor N”
- Fokus po otwarciu na pierwszym polu; `Esc` i „Anuluj” zamykają poza zapisem

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 5: Straże, reguła i zrzuty

### Overview

Następny agent dostaje sygnał przy literale w dialogu albo ręcznym potwierdzeniu; reguła i zrzuty zamykają kontrakt.

### Changes Required:

#### 1. Straż literałów

**File**: `scripts/check-ui-literals.mjs`

**Intent**: Pliki oczyszczone w tej zmianie są pilnowane w CI.

**Contract**: Lista `FILES` z `CategoryFormDialog.vue`, `FormField.vue`, `CategoryColorPicker.vue`, `CategoryIconPicker.vue`, `InputError.vue`, `pages/dev/DialogsKitchenSink.vue` i `components/ui/dialog/*.vue`.

#### 2. Test kontraktu UI

**File**: `resources/js/ui-contract.test.ts`

**Intent**: Pilnować decyzji 4 (potwierdzenia przez `ConfirmActionDialog`) i literałów dialogu kategorii.

**Contract**: `CategoryFormDialog.vue` i pickery w liście skanowanych plików Budżetu; nowy test: `pages/Budget.vue` i `components/BudgetCategoryRowActions.vue` nie importują `DialogContent` (komunikat wskazuje `ConfirmActionDialog`).

#### 3. Reguła dla agentów

**File**: `.ai/rules/js.md` (przez `record-rule`, gdy Boost jest dostępny)

**Intent**: Kolejny dialog formularza powstaje na `FormField` i kontrakcie ADR-0040.

**Contract**: Wpis: pole formularza w dialogu to `FormField` (34 px, `aria-describedby`, wariant `money`), wybór w formularzu to `SegmentedControl` `block` z `name`, „Anuluj” `outline`, zapis ze spinnerem i blokadą zamykania, sink `/dev/ui/dialogs` dla nowych stanów; fokus 2 px przez `outline` z `outline-solid`. Zgodny z `design-system.md`.

#### 4. Zrzuty

**File**: `context/changes/category-dialog-atrament/screenshots/`

**Intent**: Bramka zrzutu `/10x-ui`.

**Contract**: Każdy stan `/dev/ui/dialogs?open=<stan>` w obu motywach przy 1280 i 390 px oraz strona Budżetu z otwartym dialogiem kategorii; jednorazowy Playwright z cache `~/.cache/ms-playwright`, bez dodawania zależności (wzorzec `context/archive/2026-11-05-ui-operations-atrament/plan.md:186`).

### Success Criteria:

#### Automated Verification:

- Straż literałów: `npm run check:ui`
- Test kontraktu UI: `npm test -- resources/js/ui-contract.test.ts`
- Pełna bramka CI na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test composer ci:check`
- Sloppy: `vendor/bin/sloppy ci`

#### Manual Verification:

- Zrzuty obejrzane: wszystkie stany sinka w obu motywach i obu szerokościach zgodne z Atramentem (Dialog, Field)
- Reguła w `.ai/rules/js.md` zgodna z `design-system.md` i ADR-0040

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `DesignTokensTest`: cień dialogu w obu motywach, wymiary w `:root`, `--radius-xl`.
- `ui-contract.test.ts`: brak literałów w plikach dialogu kategorii; Budżet bez ręcznych `DialogContent`.

### Integration Tests:

- `DialogsKitchenSinkTest`: `dev/ui/dialogs` i `dev/ui/dialogs/validate` zwracają 404 poza `local`.
- Istniejące testy Feature kategorii i budżetu: payload bez zmian.

### Manual Testing Steps:

1. Otworzyć `/dev/ui/dialogs`, przejść stany w obu motywach przy 1280 i 390 px.
2. Na Budżecie dodać i edytować kategorię (szkic i realizacja), wpisać złą kwotę, zapisać.
3. Przejść pięć potwierdzeń Budżetu, sprawdzić spinner i blokadę.
4. Przejść klawiaturą dialog kategorii i jeden nietknięty dialog (`ExpenseFormDialog`), sprawdzić fokus.

## Performance Considerations

Brak: zmiana wizualna i jedna trasa dev.

## Migration Notes

Brak migracji danych. Zmiana prymitywów obejmuje 16 konsumentów `ui/dialog`; odroczone dialogi formularzy dostają nową powłokę bez zmian w swoim kodzie.

## References

- Research: `context/changes/category-dialog-atrament/research.md`
- Atrament: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (`components/Dialog/README.md`, `components/Field/README.md`, `components/Button/README.md`, `components/bundle.css` `.ht-dialog*`, `.ht-field*`, `.ht-input*`, `.ht-choice*`, `.ht-btn*`)
- ADR-0037: `.docs/adr/0037-panel-controls-table-header-and-budget-layout.md`
- Wzorzec błędu kwoty: `resources/js/components/AccountFormDialog.vue:50-52,206-224,245`
- Wzorzec blokady zapisu: `resources/js/components/ConfirmActionDialog.vue:28-38,44,76`
- Wzorzec sinka: `app/Http/Controllers/Dev/AccountsKitchenSinkController.php`, `tests/Feature/BudgetKitchenSinkTest.php`
- Poprzednia zmiana UI Atramentu: `context/archive/2026-11-05-ui-operations-atrament/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kontrakt w dokumentach i tokenach

#### Automated

- [x] 1.1 Test tokenów zielony: `./vendor/bin/sail artisan test --compact tests/Unit/DesignTokensTest.php` — 243caaa
- [x] 1.2 Formatowanie i statyka frontu: `npm run check` — 243caaa

#### Manual

- [x] 1.3 ADR-0040 i `design-system.md` opisują wszystkie decyzje z `research.md` „Decisions” oraz odpowiedzi z planowania (hover `outline`, wyłączony przycisk, deficyt jako `warning`, usunięcie `DialogScrollContent`) — 243caaa

### Phase 2: Prymitywy globalnie i sink dialogów

#### Automated

- [x] 2.1 Test sinka zielony: `./vendor/bin/sail artisan test --compact tests/Feature/DialogsKitchenSinkTest.php` — b2f7dd6
- [x] 2.2 Brak starego fokusu: `grep -rE 'ring-\[3px\]|ring-3|ring-ring/50' resources/js resources/css` nic nie zwraca — b2f7dd6
- [x] 2.3 Typy i statyka: `npm run build && npm run types:check && npm run check` — b2f7dd6
- [x] 2.4 Testy frontu: `npm test` — b2f7dd6
- [x] 2.5 Sloppy bez nowych zgłoszeń: `vendor/bin/sloppy ci` — b2f7dd6
- [x] 2.6 Pint: `vendor/bin/pint --dirty --format agent` — b2f7dd6

#### Manual

- [x] 2.7 `/dev/ui/dialogs` w obu motywach: okno 460 px, `radius-xl`, cień, nakładka `scrim`, tytuł do lewej przy 390 px, stopka z ramką na całą szerokość także w `AccountFormDialog` — b2f7dd6
- [x] 2.8 Fokus klawiaturą: przycisk ma obrys z odstępem, pole obrys bez odstępu; X dialogu ma widoczny fokus — b2f7dd6
- [x] 2.9 „Zapis w toku” w `ConfirmActionDialog` (trasa `validate`): spinner, X ukryty, `Esc` nie zamyka — b2f7dd6
- [x] 2.10 Przycisk `outline` na stronie Budżetu („Dodaj kategorię”) i w Operacjach wygląda poprawnie w obu motywach; wyłączony przycisk ma `muted` — b2f7dd6
- [x] 2.11 `TwoFactorSetupModal`, `PasskeyItem` i `DeleteUser` (ustawienia) mają poprawną stopkę i szerokość — b2f7dd6

### Phase 3: Wspólne elementy pola

#### Automated

- [x] 3.1 Literały w nowych i zmienionych plikach: `node scripts/check-ui-literals.mjs resources/js/components/FormField.vue resources/js/components/SegmentedControl.vue resources/js/components/CategoryColorPicker.vue resources/js/components/CategoryIconPicker.vue resources/js/components/InputError.vue`
- [x] 3.2 Typy i statyka: `npm run types:check && npm run check`
- [x] 3.3 Testy frontu: `npm test`

#### Manual

- [x] 3.4 Wiersze tytułu (Budżet, Operacje) i komórki planu miesiąca mają nadal 32/28 px; pola w `AccountFormDialog` (bez `FormField`) mają 32 px
- [x] 3.5 `SegmentedControl` neutral (zakładki Operacji) i primary (plan miesiąca) wyglądają jak przed zmianą

### Phase 4: Dialogi Budżetu

#### Automated

- [ ] 4.1 Testy kategorii i budżetu (payload): `./vendor/bin/sail artisan test --compact tests/Feature/BudgetTest.php tests/Feature/CategoryAppearanceTest.php tests/Feature/CategoryPacedFlagTest.php tests/Feature/CategoryMonthItemKindTest.php`
- [ ] 4.2 Literały: `node scripts/check-ui-literals.mjs resources/js/components/CategoryFormDialog.vue resources/js/components/CreateBudgetDialog.vue resources/js/components/ConfirmActionDialog.vue resources/js/pages/dev/DialogsKitchenSink.vue`
- [ ] 4.3 Typy i statyka: `npm run build && npm run types:check && npm run check`
- [ ] 4.4 Testy frontu: `npm test`

#### Manual

- [ ] 4.5 Na stronie Budżetu: dodanie i edycja kategorii zapisują dane jak wcześniej, także w realizacji z zablokowanym typem
- [ ] 4.6 „12,345” i „abc” w kwocie dają komunikat pod polem, `aria-invalid` i wyłączony „Zapisz”; poprawna kwota zapisuje się
- [ ] 4.7 Archiwizacja, przywrócenie, usunięcie kategorii, usunięcie budżetu i rozpoczęcie realizacji (z ostrzeżeniem o deficycie) działają i mają spinner oraz blokadę zamykania
- [ ] 4.8 Czytnik ekranu (lub drzewo dostępności w przeglądarce): pole kwoty ogłasza błąd, grupy Typ, Kolor, Ikona mają nazwę z etykiety, kolor to „Kolor N”
- [ ] 4.9 Fokus po otwarciu na pierwszym polu; `Esc` i „Anuluj” zamykają poza zapisem

### Phase 5: Straże, reguła i zrzuty

#### Automated

- [ ] 5.1 Straż literałów: `npm run check:ui`
- [ ] 5.2 Test kontraktu UI: `npm test -- resources/js/ui-contract.test.ts`
- [ ] 5.3 Pełna bramka CI na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test composer ci:check`
- [ ] 5.4 Sloppy: `vendor/bin/sloppy ci`

#### Manual

- [ ] 5.5 Zrzuty obejrzane: wszystkie stany sinka w obu motywach i obu szerokościach zgodne z Atramentem (Dialog, Field)
- [ ] 5.6 Reguła w `.ai/rules/js.md` zgodna z `design-system.md` i ADR-0040
