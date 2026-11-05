---
date: 2026-11-05T16:11:08+01:00
researcher: Claude (Opus 5.5) dla Sebastiana Kostecki
git_commit: 0d5ef0799c332cd9c248432eb444f3e54610ec07
branch: development
repository: household-treasurer
topic: "Dwukierunkowy audyt CategoryFormDialog (oraz ConfirmActionDialog, CreateBudgetDialog i prymitywów ui/dialog) względem systemu „C · Atrament i morski”"
tags: [research, codebase, dialog, form-field, design-system, atrament, ui]
status: complete
last_updated: 2026-11-05
last_updated_by: Claude (Opus 5.5)
---

# Research: kontrakt modali według Atramentu — CategoryFormDialog jako widok wzorcowy

**Date**: 2026-11-05T16:11:08+01:00
**Researcher**: Claude (Opus 5.5) dla Sebastiana Kostecki
**Git Commit**: 0d5ef0799c332cd9c248432eb444f3e54610ec07
**Branch**: development
**Repository**: household-treasurer

## Research Question

Audyt `resources/js/components/CategoryFormDialog.vue` względem design systemu Atrament (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, komponenty Dialog, Field, Button): kontrakt dla wszystkich modali — `components/ui/dialog`, pola, `ht-choice` jako `SegmentedControl` primary, pole kwoty, `InputError`, stopka; `ConfirmActionDialog` i `CreateBudgetDialog` w tym samym kontrakcie. Wynik: zarzuty (`## Charges`) i fakty dla `/10x-plan`.

## Summary

- Tokeny kolorów Atramentu już są w `resources/css/app.css` (w tym `--scrim`, `:146` / `:202`), ale **powłoka dialogu ich nie czyta**: `ui/dialog/DialogContent.vue:37` ma stary przepis karty (`rounded-2xl border-border/60 shadow-lg sm:max-w-lg`), `DialogOverlay.vue:17` ma `bg-black/40`. Brak tokenów `--shadow-dialog`, szerokości dialogu i wysokości pola 34 px; `radius-xl` (12 px) to domyślna wartość Tailwinda, nienazwana w `app.css` (sprawdzone `app.css:16-18` — zdefiniowane tylko `radius-lg/md/sm`).
- Zmiana prymitywu `ui/dialog` dotyka 16 plików spoza `components/ui` (lista niżej); jedyny override klasy `DialogContent` to `TwoFactorSetupModal.vue:114` (`sm:max-w-md`).
- `CategoryFormDialog` nie ma stanu błędu kwoty (`:124-130` po cichu zostawia starą wartość), nie ustawia `aria-invalid` na żadnym polu, nie ma spinnera ani blokady zamykania w trakcie zapisu; wybory 2-opcyjne (Typ, Rodzaj pozycji) to `native-select` zamiast `SegmentedControl` primary.
- Na stronie Budżetu 5 potwierdzeń jest napisanych ręcznie (Dialog + Form) wbrew regule `.ai/rules/js.md` („Confirmations use ConfirmActionDialog”).
- Dokumentacja: trzy rozbieżności wymagają ADR (następny wolny numer **0040**): promień dialogu (ADR-0037:7 „the Dialog keeps its radius”), wysokość pola 34 px (ADR-0037:3 — 32 px dla Input/Select) i fokus (`.docs/design-system.md:15` „Fokus = pierścień 3 px `ring/50`”). Użytkownik zdecydował wszystkie trzy (sekcja `## Decisions`).
- Żaden kitchen sink nie renderuje dialogu otwartego; `CategoryFormDialog` i `ui/dialog/*` nie są na liście `scripts/check-ui-literals.mjs`. Brak Playwright w zależnościach; wcześniejsze zmiany robiły zrzuty jednorazowym `npx -p playwright@…`.

## Charges

### C1. Brakujące tokeny: powłoka dialogu (kategoria: brakujące tokeny)

- `ui/dialog/DialogContent.vue:37`: `rounded-2xl border border-border/60 p-6 shadow-lg sm:max-w-lg`. Atrament (`components/Dialog/README.md`, `bundle.css` `.ht-dialog`): 460 px, `card`, pełna ramka `border`, `radius-xl` (12 px), `shadow-dialog` (jasny `0 24px 60px rgba(15, 27, 45, 0.25)`, ciemny `0 24px 60px rgba(0, 0, 0, 0.5)`, `tokens.json` → `shadow`).
- `ui/dialog/DialogOverlay.vue:17`: `bg-black/40`; token `scrim` istnieje (`app.css:63`, `:146`, `:202`), `.docs/design-system.md:52` przypisuje mu właśnie nakładkę dialogu. Przepięcie odroczone przez `context/archive/2026-11-05-design-tokens-atrament/plan.md:43`.
- `DialogTitle.vue:19` `text-lg leading-none` (18 px) vs Atrament 15/20 600; `DialogHeader.vue:13` `text-center sm:text-left` (wyśrodkowany nagłówek na telefonie); stopka `DialogFooter.vue:18` bez ramki nad stopką (Atrament `.ht-dialog__foot`: `border-top`, padding 12/18, gap 8).
- `DialogScrollContent.vue:30,35,54` — nieużywany (eksport w `index.ts:8`, brak konsumentów), własne `bg-black/40`, `rounded-2xl`, angielskie „Close”.

Wpływ: każdy modal wygląda jak poprzedni system obok paneli Atramentu (10 px, pełna ramka), a nakładka nie zmienia się w ciemnym motywie.

### C2. Brakujące tokeny: fokus i wysokość pola (kategoria: brakujące tokeny)

- Fokus 3 px `ring/50` jest wpisany w: `ui/button/index.ts:7`, `ui/input/Input.vue` (klasa `focus-visible:ring-[3px]`), `ui/select/SelectTrigger.vue`, `ui/checkbox/Checkbox.vue:23`, `ui/switch/Switch.vue`, `ui/badge/index.ts`, `ui/input-otp/InputOTPSlot.vue`, `ui/navigation-menu/index.ts`, `app.css:300` (`.native-select`), `app.css:341` (`.segmented-control-item`), `DialogContent.vue:46` (X, dodatkowo `opacity-70`), poza `ui/`: `PasswordInput.vue`, `DashboardNotices.vue` (wynik `grep -l` na `focus-visible:ring-[3px]|focus-visible:ring-3|ring-ring/50`).
- Atrament: przycisk — pierścień 2 px `ring` z odstępem 2 px (README „Focus”, `Button/README.md` „Stany”); pole — obrys 2 px `ring` bez odstępu, ramka chowana (`bundle.css` `.ht-input:focus`).
- Pole formularza Atramentu ma 34 px (`Field/README.md` „Anatomia”, `.ht-input`), repo ma 32 px dla Input/SelectTrigger/`.native-select` (`.docs/design-system.md:15`, ADR-0037:3). Kontrolki w wierszu tytułu zostają 32 px (`tokens.json` → `control-height`), komórka planu 28 px (`ht-cell-input`).

Wpływ: fokus 3 px w 50% przezroczystości jest rozmyty na `card`; pole formularza w dialogu jest niższe niż we wzorcu, przez co etykieta, pole i podpowiedź zlewają się w gęstym formularzu (8 pól w dialogu kategorii).

### C3. Brakujący współdzielony komponent: Field, `ht-choice`, pole kwoty (kategoria: brakujący współdzielony komponent)

- Każde pole kopiuje `div.grid.gap-2 > Label + kontrolka + InputError` (`CategoryFormDialog.vue:180-302`, siedem bloków; ten sam układ w `CreateBudgetDialog.vue:124-167`, `AccountFormDialog.vue:140-225`). Brak odpowiednika Atramentowego `Field`.
- `InputError.vue:8-9` renderuje `<div v-show><p class="text-destructive text-sm">` bez `id`, więc nie da się podpiąć `aria-describedby` (wymóg `Field/README.md`: „`aria-invalid` + `aria-describedby`”). `CategoryFormDialog` nie ustawia `aria-invalid` na żadnym polu (wzorzec istnieje w `AccountFormDialog.vue:148,161,192,206-211`).
- Typ (`CategoryFormDialog.vue:194-207`) i Rodzaj pozycji (`:285-297`) to `native-select` z dwiema opcjami; Atrament: wybór jednej z kilku opcji formularza = `ht-choice` → `SegmentedControl` tone `primary` (`Field/README.md` „W kodzie”; `.docs/design-system.md:84-87`; `.ai/rules/js.md` „A value switch … is SegmentedControl (tone primary for a form choice)”). `SegmentedControl.vue:27-49` to reka `ToggleGroupRoot` — nie wysyła wartości w formularzu (potrzebny `input type=hidden`), ma prop `disabled` (`:9`); `.segmented-control` (`app.css:336-338`) jest `inline-flex` z elementami `h-7`, a `ht-choice` to pełna szerokość z równymi kolumnami.
- Pole kwoty (`CategoryFormDialog.vue:246-252`): zwykły `Input` — bez kroju mono, wyrównania do prawej i „zł” w polu (`.ht-input--money`, `.ht-field__unit`).
- Uwaga o źródle: w `bundle.css` zaznaczony `ht-choice` to `card` z obwódką (wygląd tonu `neutral`), a `Field/README.md` mówi „SegmentedControl w tonie `primary`”. Użytkownik wskazał w argumencie `/10x-ui` ton `primary`; reguła repo mówi to samo.

Wpływ: druga opcja typu jest schowana w liście rozwijanej; czytnik ekranu nie łączy błędu z polem; kwota nie stoi jak w reszcie aplikacji (mono, do prawej).

### C4. Stany błędu i ładowania (kategoria: przypadkowa architektura — stany, których happy path nie ćwiczy)

- `CategoryFormDialog.vue:124-130` `syncPlannedAmount`: gdy `parseMoneyInputToMinorUnits` zwraca `null`, ukryte pole (`:253-257`) zostaje przy poprzedniej wartości; brak komunikatu. Użytkownik wpisuje „12,345” albo „abc”, zapisuje i dostaje poprzedni limit. Wzorzec poprawki: `AccountFormDialog.vue:50-52,206-224,245` (komunikat klienta przed błędem serwera, `aria-invalid`, wyłączony „Zapisz”). Klucze „Wpisz kwotę, np. 1200,50.” istnieją w `lang/pl/ui.php:330` (`month_plan.amount_invalid`), `:389` (`accounts.opening_balance_invalid`), `:467` (`term_deposits.opening_balance_invalid`); w bloku `budget` (`ui.php:159-298`) takiego klucza nie ma.
- Brak spinnera i blokady zamykania w trakcie zapisu: `CategoryFormDialog.vue:138,306-322` i `CreateBudgetDialog.vue:104,170-176`. Wzorzec: `ConfirmActionDialog.vue:29-38,44,66,76` (`busy`, `onOpenChange`, `:show-close-button="!busy"`, `Spinner`) oraz `TermDepositFormDialog.vue:135` (poprawka F3, `context/archive/2026-11-03-term-deposits-ui-contract/reviews/impl-review.md:63-65`).
- „Anuluj” to `variant="secondary"` w `CategoryFormDialog.vue:308`, `CreateBudgetDialog.vue:170`, `ConfirmActionDialog.vue:65`.
- `ui/spinner/Spinner.vue`: `aria-label="Loading"` (po angielsku, w polskim UI).

Wpływ: zła kwota przechodzi bez ostrzeżenia; nie widać, że zapis trwa, a dialog można zamknąć w środku żądania.

### C5. Przypadkowa architektura: ręczne potwierdzenia, ręczne panele, brak straży i sinka

- Pięć potwierdzeń na stronie Budżetu omija `ConfirmActionDialog`: archiwizacja, przywrócenie, usunięcie kategorii (`BudgetCategoryRowActions.vue:134,162,190`), rozpoczęcie realizacji (`pages/Budget.vue:208`), usunięcie budżetu (`pages/Budget.vue:325`). Ich `Form` nie ma klasy odstępu, więc nagłówek i stopka stoją bez odstępu (jedyne dziecko siatki `DialogContent`); Anuluj `secondary`, brak spinnera i blokady. Reguła: `.ai/rules/js.md` „Confirmations use ConfirmActionDialog”. `BudgetCategoryAlignPlanAction.vue:28` to dialog z treścią (nie czyste potwierdzenie) — poza tym punktem.
- Ręczne panele: podgląd kategorii `CategoryFormDialog.vue:166-178` (`border-border/50 rounded-xl border p-4`; Atrament `ht-preview`: `background`, `radius-md`), siatka ikon `CategoryIconPicker.vue:18` (`border-border/50 … rounded-xl`) i `:24` `hover:bg-foreground/[0.04]` (literał krycia, który `check-ui-literals.mjs:83-86` łapie wzorcem `/[0.x]`).
- Dostępność pickerów: `CategoryColorPicker.vue:28,32` `aria-label` = wartość hex (czytnik czyta „#c0262d”); etykiety „Kolor” i „Ikona” (`CategoryFormDialog.vue:218,228`) to `Label` bez `for`, a pickery nie mają `role="group"` ani `aria-labelledby`.
- Straż: `scripts/check-ui-literals.mjs:9-76` ma `CreateBudgetDialog.vue` (`:16`) i `ConfirmActionDialog.vue` (`:34`), nie ma `CategoryFormDialog.vue`, pickerów ani `ui/dialog/*`. `resources/js/ui-contract.test.ts:13-29` skanuje m.in. `CreateBudgetDialog.vue`, `Budget.vue`, `BudgetCategoryRowActions.vue`, nie `CategoryFormDialog.vue`.
- Sink: żaden z `pages/dev/*` nie renderuje dialogu otwartego (wszystkie refy `open` startują od `false`: `AccountsKitchenSink.vue:10`, `TermDepositsKitchenSink.vue:12`, `BudgetKitchenSink.vue:211`); `CategoryFormDialog` i `ConfirmActionDialog` nie występują w żadnym sinku.
- Wejście z linku / bez danych: dialog żyje tylko w `pages/Budget.vue:356-374` za `v-if="budget"`, trasy `categories.store` / `categories.update` (`routes/web.php:53,55`) są w grupie `auth`; walidacja serwera `app/Concerns/CategoryValidationRules.php:19-30` (`planned_amount_minor_units` `required|integer|min:0`). Brak pustej ramki ani surowego JSON — tu bez zarzutu.

Wpływ: potwierdzenia na tej samej stronie wyglądają i zachowują się inaczej niż w Kontach i Lokatach; następny agent nie dostanie sygnału, gdy dialog kategorii znowu dostanie literał; stanów dialogu nie da się obejrzeć bez klikania po aplikacji.

### Odroczone (deferred)

- Pola pozostałych dialogów formularzy: `ExpenseFormDialog`, `IncomeFormDialog`, `AccountFormDialog`, `TermDepositFormDialog`, `OwnTransferFormDialog`, `CapitalMovementFormDialog` oraz `BudgetCategoryAlignPlanAction`, `TwoFactorSetupModal`, `PasskeyItem`, `DeleteUser`. Powód: jedna zmiana = jeden widok; dostają nową powłokę (C1) i fokus (C2) z prymitywów, ale `FormField`, 34 px i Anuluj `outline` — w osobnych zmianach.
- `ui/sheet/SheetOverlay.vue:16` `bg-black/80` (mobilny sidebar, `AppHeader.vue:27`) — inny prymityw i inny widok.
- Angielskie komunikaty walidacji serwera (brak `lang/pl/validation.php`, `lang/pl` ma tylko `pagination.php` i `ui.php`; wniosek z braku pliku, nie sprawdzony w działającej aplikacji) — odroczone już w `context/archive/2026-11-03-accounts-ui-contract/plan-brief.md:43-44` jako zmiana i18n.
- Podgląd „przed → po” z `Dialog/README.md`: N/A w tej zmianie — dialog kategorii dziś nie liczy żadnego odczytu (`CategoryFormDialog.vue:75-87` liczy tylko nazwę podglądu), a dodanie odczytu to nowe zachowanie, nie kontrakt wizualny; podgląd `CategoryChip` przechodzi na wygląd `ht-preview` (C5).

## Decisions

Odpowiedzi użytkownika (2026-11-05, w rozmowie):

1. **Powłoka dialogu wg Atramentu w tej zmianie, z ADR-0040.** `DialogContent`/`DialogOverlay`/`DialogHeader`/`DialogTitle`/`DialogFooter`: 460 px, `radius-xl`, pełna ramka, nowy token `--shadow-dialog` (`:root` i `.dark`), nakładka `scrim`, ramka nad stopką. ADR-0040 częściowo zastępuje ADR-0037 (zdanie o promieniu dialogu); wpis w `.docs/design-system.md` przed kodem.
2. **Pola i fokus wg Atramentu.**
   - 34 px **tylko dla pól formularza w dialogach** (nowy token wysokości pola + wariant/klasa pola formularza); Input/select w wierszach tytułu i tabel zostają 32/28 px — powód z ADR-0037:7 (równe wysokości w jednym wierszu) nadal obowiązuje.
   - Fokus 2 px **globalnie w `ui/*`** (Button, Input, SelectTrigger, Checkbox, Switch, Badge, `.native-select`, `.segmented-control-item`, X dialogu, plus konsumenci spoza `ui/` z listy w C2): przyciski — pierścień `ring` 2 px z odstępem 2 px; pola — obrys 2 px bez odstępu, ramka chowana. W ADR-0040 i `.docs/design-system.md:15`.
3. **„Anuluj” = `outline`** (zgodnie z podglądem Dialogu w Atramencie i `context/archive/2026-11-02-approved-plan-changes/plan.md:179`), nie `ghost`.
4. **Pięć ręcznych potwierdzeń Budżetu przechodzi na `ConfirmActionDialog` w tej zmianie** (C5).
5. **Wspólny komponent `FormField`** (`components/FormField.vue`): etykieta, slot kontrolki, podpowiedź, `InputError` z `id`, `aria-describedby`; wariant kwoty z „zł” w polu.

## Detailed Findings

### Zasięg prymitywów `ui/dialog`

Importy `@/components/ui/dialog` spoza `components/ui` (16 plików): `OwnTransferFormDialog.vue:18`, `BudgetCategoryRowActions.vue:24`, `ConfirmActionDialog.vue:14`, `ExpenseFormDialog.vue:19`, `IncomeFormDialog.vue:19`, `BudgetCategoryAlignPlanAction.vue:14`, `CategoryFormDialog.vue:19`, `TwoFactorSetupModal.vue:15`, `pages/Budget.vue:23`, `TermDepositFormDialog.vue:17`, `IconAction.vue:4` (tylko `DialogTrigger`), `AccountFormDialog.vue:16`, `CapitalMovementFormDialog.vue:18`, `CreateBudgetDialog.vue:16`, `PasskeyItem.vue:13`, `DeleteUser.vue:18`.

- Override klasy `DialogContent`: tylko `TwoFactorSetupModal.vue:114` (`sm:max-w-md`), plus `DialogHeader class="flex items-center justify-center"` (`:115`) i `DialogDescription class="text-center"` (`:146`). `PasskeyItem.vue:75-76` nie ma `DialogHeader`. `DeleteUser.vue:60` `DialogHeader class="space-y-3"`.
- `:show-close-button="!busy"`: `ConfirmActionDialog.vue:44`, `TermDepositFormDialog.vue:135`.
- Odstępy: większość dialogów formularzy ma `<Form class="space-y-4">`; `Form` jest jedynym dzieckiem siatki `DialogContent` (`gap-4`), więc odstęp header–body–footer pochodzi z `Form`. Bez klasy: dialogi `Budget.vue`, `BudgetCategoryRowActions`, `BudgetCategoryAlignPlanAction`, `ConfirmActionDialog`. Wniosek dla planu: odstępy sekcji (Atrament: head / body / foot z własnym paddingiem) warto przenieść do prymitywów, żeby nie zależały od klasy `Form`.
- Każdy `DialogFooter` dostaje `class="gap-2"`, które baza już ma (`DialogFooter.vue:18`).
- `ui/sheet` nie współdzieli nakładki (`SheetOverlay.vue:5,16` — własna, `bg-black/80`).

### `CategoryFormDialog` — dane i serwer

- Jedyny konsument `pages/Budget.vue:356-363` (create, `default-type="expense"`) i `:365-374` (edit, `:lock-type="editingCategory?.can_change_type === false"`); props `CategoryFormDialog.vue:44-52`; `categoryAppearanceOptions` z `BudgetController.php:47,95` (`CategoryAppearance::formOptions()`).
- Kontroler `app/Http/Controllers/CategoryController.php:24-49` (store; domyślnie `is_paced` true i `month_item_kind` running dla wydatku), `:51-73` (update; `is_paced`/`month_item_kind` tylko dla niezarchiwizowanego wydatku).
- `UpdateCategoryRequest.php:35-37`: w realizacji `type` musi równać się obecnemu — przy `SegmentedControl` z `disabled` ukryte pole `type` musi nadal wysyłać obecną wartość (dziś `CategoryFormDialog.vue:208-213`).
- Payload formularza (nazwy pól `name`, `type`, `color`, `icon`, `planned_amount_minor_units`, `is_paced`, `month_item_kind`) się nie zmienia; zmiana jest czysto frontendowa, więc kontrakt propsów Inertia (`AGENTS.md`, „Inertia props contract”) nie jest dotknięty.
- Testy Feature kategorii: `tests/Feature/BudgetTest.php:73,95,115,130,656,689`, `CategoryAppearanceTest.php:18,42,58`, `CategoryPacedFlagTest.php`, `CategoryMonthItemKindTest.php` — testują serwer, nie markup.

### Przyciski

- `ui/button/index.ts:11-21`: `default` (primary), `destructive` (`bg-destructive text-white … dark:bg-destructive/60` — literał `text-white` i `dark:` w prymitywie), `outline` (`border bg-background hover:bg-accent …`), `secondary`, `ghost` (`hover:bg-accent`), `link`. Atrament: zarysowany ma hover `secondary`, nie `accent` (`Button/README.md` „Stany”); `outline` z `hover:bg-accent` daje morski hover na „Anuluj”. Plan powinien zdecydować, czy wariant `outline` dostaje hover `secondary` (zmiana globalna, zgodna z Atramentem).
- Wyłączony: dziś `disabled:opacity-50` (`index.ts:7`); Atrament — `muted` + `muted-foreground`.

### Pola

- `Input.vue`: `h-control … focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] … aria-invalid:border-destructive`.
- `parseMoneyInputToMinorUnits` (`lib/format.ts:49-61`) przyjmuje `1200,50`, `1 200,50`, `1200.50` (usuwa białe znaki, zamienia przecinek) i do 2 miejsc; zwraca `null` dla „12,345” i „abc” — zgodne z Atramentem.
- `.docs/design-system.md:57`: `border`/`input` na `card` mają 1,37 i 1,32:1, więc stan pola nie może wynikać z samej ramki — fokus Atramentu (obrys `ring` 6,25:1, ramka chowana) to spełnia; błąd potrzebuje komunikatu tekstowego (jest w `FormField`).
- `Checkbox` dla „Kategoria z tempem” (`CategoryFormDialog.vue:263`) zostaje: `Switch` jest dla ustawienia zapisywanego od razu (`.ai/rules/js.md`), a tu wybór zapisuje się z formularzem.

### Kitchen sink i zrzuty

- Rejestracja sinka: kontroler z `abort_unless(app()->isLocal(), 404)` (`routes/web.php:25-28`, np. `app/Http/Controllers/Dev/DashboardKitchenSinkController.php`) albo `Route::inertia` w `if (app()->isLocal())` / `app()->environment('local')` (`routes/web.php:33-35,42-46`); testy 404 poza local, np. `tests/Feature/BudgetKitchenSinkTest.php:13`.
- `CategoryFormDialog` potrzebuje propsów `categoryAppearanceOptions` (z serwera w Budżecie) — sink musi je dostać (kontroler z `CategoryAppearance::formOptions()` albo fixture).
- Zrzuty: brak Playwright w `package.json`/`composer.json`; wzór jednorazowego skryptu `context/archive/2026-11-05-ui-operations-atrament/plan.md:186`.

### Testy i straże dotknięte zmianą

- `resources/js/ui-contract.test.ts:10-11,52-58` (literały w plikach Budżetu, w tym `CreateBudgetDialog.vue`, `Budget.vue`, `BudgetCategoryRowActions.vue`), `:61-86` (wiązanie Checkbox).
- `scripts/check-ui-literals.mjs:83-86` łapie też `dark:` — dodanie `ui/button/index.ts` lub `ui/dialog/*` do listy wymagałoby usunięcia `dark:` z wariantów.
- `tests/Unit/DesignTokensTest.php:17` — każdy kolor z `@theme inline` musi istnieć w `:root` i `.dark`; `:47` `--radius: 10px`. Cień i wymiary nie są kolorami, ale `--shadow-dialog` ma wartość jasną i ciemną, więc trafia do obu bloków.
- Brak testów komponentów; `InputError`, klasy dialogu i `CategoryFormDialog` nie występują w żadnym teście.

## Code References

- `resources/js/components/ui/dialog/DialogContent.vue:37` — przepis powłoki; `:46` — X z `opacity-70` i `ring-3`
- `resources/js/components/ui/dialog/DialogOverlay.vue:17` — `bg-black/40`
- `resources/js/components/ui/dialog/DialogTitle.vue:19`, `DialogHeader.vue:13`, `DialogFooter.vue:18`
- `resources/js/components/CategoryFormDialog.vue:124-130` — cichy błąd kwoty; `:166-178` podgląd; `:194-213` Typ; `:246-258` kwota; `:285-301` Rodzaj pozycji; `:304-323` stopka
- `resources/js/components/CreateBudgetDialog.vue:104-176`
- `resources/js/components/ConfirmActionDialog.vue:29-38,44,63-79`
- `resources/js/components/InputError.vue:8-9`
- `resources/js/components/SegmentedControl.vue:4-49`; `resources/css/app.css:336-366`
- `resources/js/components/CategoryColorPicker.vue:21,28`; `CategoryIconPicker.vue:18,24`
- `resources/js/components/BudgetCategoryRowActions.vue:134,162,190`; `resources/js/pages/Budget.vue:208,325,356-374`
- `resources/js/components/AccountFormDialog.vue:50-52,206-224,245` — wzorzec błędu kwoty
- `resources/js/components/ui/button/index.ts:7-30`
- `resources/css/app.css:16-18,63,146,152-154,202,300`
- `scripts/check-ui-literals.mjs:9-86`; `resources/js/ui-contract.test.ts:13-29`; `tests/Unit/DesignTokensTest.php:17,47`
- `lang/pl/ui.php:159-298,330,389,467`
- `.docs/design-system.md:3,8,15,52,57,76,80,84-87`; `.docs/adr/0037-panel-controls-table-header-and-budget-layout.md:3,7`

## Architecture Insights

- Repo stosuje wariant „istniejący system projektowy”: wartości w `app.css` (`:root` / `.dark` → `@theme inline`), prymitywy w `components/ui/*`, domenowe w `components/`, straż literałów w `check:ui` + testach źródłowych, stany w kitchen sinkach `local`-only. Ta zmiana rozszerza ten układ, nie dodaje drugiego.
- `.docs/design-system.md:3` nazywa artefakt Atrament „tylko podgląd, nie źródło wartości” — wartości przyjęte z Atramentu (34 px, fokus 2 px, cień, 460 px) muszą najpierw trafić do ADR-0040 i `design-system.md`, potem do `app.css`.
- `.docs/design-system.md:8` zakazuje edycji `components/ui/*` „dla jednego ekranu”; tu zmiana prymitywów jest decyzją dla całej aplikacji z ADR, więc mieści się w regule.
- Wzorce do skopiowania: blokada zamykania i spinner z `ConfirmActionDialog`, błąd kwoty z `AccountFormDialog`, rejestracja sinka z `DashboardKitchenSinkController`.

## Historical Context (from prior changes)

- `context/archive/2026-11-05-design-tokens-atrament/plan.md:43,45` — przepięcie nakładek na `scrim` oraz tokeny `shadow-*` i `radius-xl` odroczone (wspierane: nadal nie zrobione).
- `.docs/adr/0037-…md:7` — „a `radius-xl` Dialog in this change (out of scope, the Dialog keeps its radius)”; odrzucone „only the buttons and not the fields” (różne wysokości w wierszu). Wspierane; ta zmiana zastępuje część o dialogu, a 34 px ogranicza do pól w dialogach, by nie łamać drugiego powodu.
- `context/archive/2026-11-05-budget-atrament-ui/plan.md:45` i `context/archive/2026-11-05-ui-operations-atrament/plan.md:47` — „Dialog bez zmian” w tamtych zmianach.
- `context/archive/2026-11-03-accounts-ui-contract/plan.md:66,166,328-329` — blokada zamykania `ConfirmActionDialog`, X dialogu z `ring-3 ring-ring/50` i „Zamknij”, wzorzec błędu kwoty z `aria-invalid`; `plan-brief.md:43-44` odroczył walidację kwot w innych formularzach i angielskie komunikaty.
- `context/archive/2026-11-03-term-deposits-ui-contract/reviews/impl-review.md:63-65` — F3: blokada zamykania w `TermDepositFormDialog`; to samo zachowanie brakuje w innych dialogach formularzy (tu: `CategoryFormDialog`, `CreateBudgetDialog`).
- `context/archive/2026-11-02-approved-plan-changes/plan.md:179` — „Anuluj” jako `outline`.
- `context/archive/2026-11-03-budget-view-ui/plan.md:225` — payload `CreateBudgetDialog` nie może się zmienić.
- ADR: najwyższy numer 0039; brak 0025–0028 (bez śladu), 0036 zarezerwowany przez aktywną zmianę `context/changes/period-report-by-category` (`context/archive/2026-11-05-budget-atrament-ui/change.md:20`); 0008 użyty dwa razy. Następny wolny: **0040**.

## Related Research

- `context/archive/2026-11-05-ui-operations-atrament/research.md` — format `## Charges` i decyzji dla zmiany UI Atramentu.
- `context/archive/2026-11-03-accounts-ui-contract/` — pierwsze wprowadzenie `ConfirmActionDialog` i błędu kwoty.

## Open Questions

Do rozstrzygnięcia w planie (nie wymagają nowych faktów):

- Hover wariantu `outline` (`ui/button/index.ts:16`, dziś `hover:bg-accent`) → `secondary` wg Atramentu: zmiana globalna — wpisać do ADR-0040 razem z fokusem.
- Wygląd wyłączonego przycisku (`disabled:opacity-50` vs `muted` + `muted-foreground`) — w tej zmianie czy odroczyć.
- Klucz komunikatu złej kwoty: nowy `budget.planned_amount_invalid` (wzór modułowy z `ui.php:389,467`) czy jeden wspólny klucz.
- `DialogScrollContent` (nieużywany): usunąć czy dostosować.
- Forma sinka: `/dev/ui/dialogs` (kontroler z `CategoryAppearance::formOptions()`) z dialogami otwartymi w każdym stanie.
