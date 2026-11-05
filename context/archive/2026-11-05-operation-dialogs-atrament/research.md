---
date: 2026-11-05T17:32:50+01:00
researcher: Sebastian Kostecki (Claude Opus 5.5)
git_commit: df79cda03bf547e30b966ec9b73193624bafaf25
branch: operation-dialogs-atrament
repository: household-treasurer
topic: "Audyt dialogów operacji (Expense, Income, OwnTransfer, CapitalMovement) względem Atramentu i ADR-0040"
tags: [research, ui, dialogs, atrament, ExpenseFormDialog, IncomeFormDialog, OwnTransferFormDialog, CapitalMovementFormDialog, FormField, SegmentedControl, entry-to-spend-warning]
status: complete
last_updated: 2026-11-05
last_updated_by: Sebastian Kostecki (Claude Opus 5.5)
---

# Research: Audyt dialogów operacji względem Atramentu i ADR-0040

**Date**: 2026-11-05T17:32:50+01:00
**Researcher**: Sebastian Kostecki (Claude Opus 5.5)
**Git Commit**: df79cda03bf547e30b966ec9b73193624bafaf25
**Branch**: operation-dialogs-atrament
**Repository**: household-treasurer

## Research Question

Audyt `resources/js/components/ExpenseFormDialog.vue` (oraz `IncomeFormDialog.vue`, `OwnTransferFormDialog.vue`, `CapitalMovementFormDialog.vue`) względem design systemu Atrament (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, komponenty Dialog, Field, Amount) i ADR-0040: zweryfikować zarzuty C1–C5 ze wstępnego audytu `/10x-ui` (FormField/money, checkboxy i `Select` → `SegmentedControl` primary, podgląd „Do wydania przed → po” i limitu z `handEntryToSpendChanges` / `hand-entry-warning-preview`, stopka `outline` + `Spinner` + blokada zamykania, błąd złej kwoty, textarea), ustalić, co dodać do `/dev/ui/dialogs` i `check-ui-literals`, oraz czy podgląd wymaga ADR. Wynik: `## Charges`.

## Summary

- **Tokeny nie są problemem.** Skan literałów z `/10x-ui` dał 0 trafień w 4 plikach dialogów (wstępny audyt, `change.md`). Wszystkie zarzuty dotyczą brakujących współdzielonych elementów i kontraktu ADR-0040, który te 4 dialogi świadomie odroczyły (`context/changes/category-dialog-atrament/research.md:86`, `plan.md:38`).
- **Pola (C1):** w 4 dialogach żadne pole nie jest `FormField` i żadna kontrolka nie dostaje `aria-invalid` ani `aria-describedby` (przegląd pełnych plików, tabela niżej). `AccountSelect` nie ma propów aria na triggerze (`AccountSelect.vue:13-24,48`), `CategorySelect` ma tylko `ariaDescribedby` (`CategorySelect.vue:24,54`).
- **Wybory (C2):** trzy surowe checkboxy (`ExpenseFormDialog.vue:534-539`, `:579-584`, `IncomeFormDialog.vue:394-399`) sterują flagami, które się wzajemnie wykluczają (watchery `ExpenseFormDialog.vue:379-396`). Kierunek ruchu kapitału to reka `Select` z dwiema opcjami tekstowymi (`CapitalMovementFormDialog.vue:277-295`), a lokata to reka `Select` bez ikon (`:318-336`). Reguła `.ai/rules/js.md` każe w tym miejscu użyć `native-select`.
- **Podgląd (C3):** `previewToSpendWarnings` liczy `before` i `after` dla każdego dotkniętego miesiąca z planem, ale zwraca tylko ostrzeżenie, gdy `after < 0 && after < before` (`resources/js/lib/entry-to-spend-warning.ts:208-218`). Saldo „po” jest liczone w `hand-entry-warning-preview.ts`, ale zwracana jest tylko flaga (`:274-285`, `:312`, `:355`, `:394`). W `app.css` nie ma odpowiednika `ht-preview`, a pole limitu jest ręcznym pudełkiem (`ExpenseFormDialog.vue:614`).
- **Stopka i stany (C4):** w 4 dialogach „Anuluj” jest `secondary` i nie jest wyłączany, „Zapisz” nie ma spinnera, a w trakcie zapisu dialog zamyka `Esc`, X, nakładka i „Anuluj”. Zła kwota zamienia się po cichu na 0 w 4 różnych wariantach `syncAmount`, a w grupie `operations` brakuje klucza błędu kwoty.
- **Bramka (C5):** żaden z 4 dialogów nie jest renderowany w `/dev/ui/dialogs` (`resources/js/pages/dev/DialogsKitchenSink.vue:116-236`) ani nie figuruje na liście `scripts/check-ui-literals.mjs:9-91`. Żaden test nie odwołuje się do tych plików. Dialogi mają na stałe przypięte akcje Wayfinder bez propa `form?`, więc sink nie może skierować ich na `dev.ui.dialogs.validate`.
- **ADR:** podgląd „przed → po” jako odczyt **nie zmienia** reguły domeny (`CONTEXT.md:80` określa warunek ostrzeżenia, a nie zakaz pokazywania odczytu). Natomiast decyzja 1 (ostrzeżenia w bloku podglądu, jak w DS) zmienia regułę `.docs/design-system.md:77` i `.ai/rules/js.md:24` dla dialogów, więc potrzebny jest **ADR-0041** przed kodem.

## Charges

### C1. Brakujący współdzielony komponent: pola formularza (kategoria: brakujący współdzielony komponent)

Wzorzec kontraktu: `FormField` ze slotem `{id, describedBy, invalid, controlClass}`, kontrolki z `:aria-describedby` i `:aria-invalid` (`CategoryFormDialog.vue:209-224`), kwota przez `money` + `controlClass` (`:274-303`).

Ręczne bloki `div.grid gap-2 > Label + kontrolka + InputError` (sprawdzone w pełnych plikach):

| Dialog | Pola (linie) | Kwota |
| --- | --- | --- |
| `ExpenseFormDialog.vue` | data 459-471, opis 473-485, kontrahent 487-498, konto 518-531, kategoria 552-576 | 590-610, zwykły `Input` + ukryty `amount_minor_units` |
| `IncomeFormDialog.vue` | data 319-331, opis 333-345, kontrahent 347-358, konto 378-391, kategoria 412-436 | 438-454 |
| `OwnTransferFormDialog.vue` | data 193-203, opis 205-217, konto źródłowe 240-252, docelowe 254-266 | 268-286 |
| `CapitalMovementFormDialog.vue` | data 247-257, opis 259-271, kierunek 273-298, konto 300-312, lokata 314-343 | 345-363 |

- W tych 4 plikach żadna kontrolka nie ma `aria-invalid` ani `aria-describedby`.
- `AccountSelect` przyjmuje `ariaLabel`, ale nie przyjmuje `ariaDescribedby` ani `ariaInvalid`. Korzeniem jest `<div>`, więc atrybut przekazany z zewnątrz trafi na div, a nie na trigger (`AccountSelect.vue:13-24,48`). `CategorySelect` ma `ariaDescribedby` (`CategorySelect.vue:24,54`), ale nie ma `ariaInvalid`. Oba trzeba rozszerzyć, żeby przyjęły `invalid` z `FormField`.
- „Opis z wyciągu” (tylko do odczytu, w edycji) to identyczny ręczny `<textarea readonly rows="3">` z klasami `border-input w-full rounded-xl border bg-transparent px-3 py-2 text-base md:text-sm` w 3 dialogach: `ExpenseFormDialog.vue:509-515`, `IncomeFormDialog.vue:369-375` i `OwnTransferFormDialog.vue:229-237` (ten ostatni łączy `raw_statement_descriptions` przez `join('\n\n')`). `rounded-xl` to promień okna dialogu, a nie pola (`Input.vue:143` ma `rounded-md`). W `components/ui` nie ma prymitywu textarea. Decyzja 4: statyczny blok tekstu.
- Klasa formularza to `space-y-4`, a kontrakt ma `grid gap-4` (`CategoryFormDialog.vue:169`).

Wpływ: błąd pola nie jest powiązany z kontrolką dla czytnika ekranu, pola mają 32 px zamiast 34 px (`--field-height` działa tylko wewnątrz `.form-field`, `app.css:164`, `:323`), kwota nie ma „zł” ani kroju Mono, a pole tylko do odczytu udaje edytowalną kontrolkę z promieniem okna.

### C2. Brakujący współdzielony komponent: wybór jednej z kilku opcji (kategoria: brakujący współdzielony komponent)

- `ExpenseFormDialog.vue:533-543` to „zamień na dochód”, widoczne tylko w edycji zwrotu (`editingRefund`, `:109-115`). `:578-588` to „Zwrot”, widoczne, gdy `!isIncome`. Oba to `<input type="checkbox" class="size-4 rounded border">`. Watchery `:379-390` i `:392-396` wykluczają `isRefund` i `isIncome` nawzajem, więc to jeden wybór: w dodawaniu Wydatek | Zwrot, w edycji zwrotu Wydatek | Zwrot | Dochód. Wybór steruje `availableCategories` (`:117-133`), `proposalKind` (`:151-157`), tytułem (`:437-449`) i ukrytym `convert_to_income` (`:545-550`).
- `IncomeFormDialog.vue:393-403` to „zwrot wydatku”, tylko w edycji (`isExpenseRefund`, `:88`, watcher `:266-272`), z ukrytym `convert_to_expense_refund` (`:405-410`).
- Błędy serwera `convert_to_income` (`app/Http/Requests/UpdateExpenseRequest.php:45`) i `convert_to_expense_refund` (`UpdateIncomeRequest.php:45,60`) nie są wyświetlane w żadnym z tych dialogów (grep po obu plikach `.vue`).
- `CapitalMovementFormDialog.vue:277-295`: kierunek to reka `Select` z 2 statycznymi opcjami tekstowymi i ukrytym `direction` (`:296`). `:318-336`: lokata to reka `Select` z opcjami `termDeposit.name` bez ikon. Reguła `.ai/rules/js.md` „Dropdowns” wymaga `.native-select` dla prostych wyliczeń, a ADR-0040 wymaga `SegmentedControl` `block` `primary` z `name` dla wyboru jednej z kilku opcji.
- `ui/checkbox/Checkbox.vue` istnieje (fokus 2 px `outline-ring`). Surowe checkboxy go nie używają, więc fokus pokazuje domyślny obrys przeglądarki.

Wpływ: typ operacji, od którego zależą lista kategorii, znak kwoty i tytuł, jest schowany w checkboxie pod kontem albo nad kwotą. Fokus klawiatury nie ma tokenu, a kierunek ruchu kapitału wymaga otwarcia listy, by zobaczyć dwie opcje.

Uwaga zakresowa: jeden dialog „Nowa operacja” z wyborem Wydatek/Dochód/Zwrot, który pokazuje `Dialog/preview.html` w DS, odrzucono przy `ui-operations-atrament` (`context/archive/2026-11-05-ui-operations-atrament/research.md:89,105`; sekcja „We rejected” ADR-0039 wg `plan.md:79`). Ta zmiana zostaje przy osobnych dialogach (decyzja 3).

### C3. Brakujący współdzielony komponent i reguła: podgląd „przed → po” (kategoria: brakujący współdzielony komponent)

Wzorzec DS (`components/Dialog/README.md`, `preview.html`, `bundle.css:206-210`): blok `ht-preview` (tło `background`, `radius-md`, padding 10/12) z etykietą 12/16 `muted-foreground`, wartością 600 `ht-num` w formie „2 340,50 zł → 2 250,60 zł”, ostrzeżeniem 12/16 w `warning` w środku bloku i wartością w `destructive`, gdy wynik jest ujemny (`ht-preview--neg`).

Stan obecny:
- `ExpenseFormDialog.vue:612-631` to ręczne pudełko `bg-muted/40 rounded-md border p-3 text-sm` z „Dostępny limit teraz” / „Przewidywany limit po operacji” (`lang/pl/ui.php:625-627`). Pokazuje się, gdy wybrana jest kategoria i limit nie jest null (wydatek i zwrot). W `IncomeFormDialog.vue:456-479` to samo pudełko pokazuje się tylko przy `isExpenseRefund`.
- Ostrzeżenia to osobny `Alert warning`: `ExpenseFormDialog.vue:633-685`, `IncomeFormDialog.vue:481-525`, `OwnTransferFormDialog.vue:288-296`, `CapitalMovementFormDialog.vue:365` i `:375-386`.
- Do wydania pokazuje się tylko jako ostrzeżenie: `previewToSpendWarnings` (`entry-to-spend-warning.ts:151-222`) liczy lokalne `before` (`:208`) i `after` (`:214`) dla każdego dotkniętego miesiąca z planem, a zwraca `{year, month, toSpendAfterMinorUnits}` (`:39-43`) tylko przy `after < 0 && after < before` (`:216-218`). Wpis dotyka 1 miesiąca, a edycja do 2 (data pierwotna i nowa, także grudzień → styczeń, `:156-171`). Miesiąc bez wpisu w danych jest pomijany (`:176-178`).
- **Import korzysta z tej samej funkcji** (`resources/js/lib/import-to-spend-warnings.ts:81`). Zmiana jej kształtu zwrotu dotyka Importu, więc bezpieczniej dodać osobną eksportowaną funkcję z listą `{year, month, before, after}` per miesiąc i z niej wyprowadzić ostrzeżenie. Testy: `entry-to-spend-warning.test.ts` (m.in. `:153`, `:171`, `:198`, `:265-332`).
- Saldo konta: „przed” jest w propsach (`current_balance_minor_units`, `OperationsController.php:138-146`). „Po” jest liczone w `hand-entry-warning-preview.ts`, ale wychodzi tylko jako flaga: wydatek `:394`, edycja dochodu `:355`, konto źródłowe transferu `:312`, konto i lokata ruchu kapitału `:274-285`. Typ zwrotu to `:52-58`. Dodawanie dochodu zwraca same null/false (`:333-341`). Plik nie ma testów (grep po `resources/js` i `tests`), co zgadza się z AGENTS.md.
- Dane z serwera: `EntryToSpendInputs::forUser` (`app/Support/EntryToSpendInputs.php:35-122`) daje miesiące do bieżącego włącznie, z odczytem planu innym niż `none` (`:69`, `:75`). To wystarcza, bo data wydatku i dochodu jest `before_or_equal:today` (`ExpenseValidationRules.php:24`, `IncomeValidationRules.php:24`). `entryToSpend` trafia tylko do dialogów dochodu i wydatku (`Operations.vue:198-234`). Transfer własny i ruch kapitału nie zmieniają Do wydania (`CONTEXT.md:80`).
- Etykieta: w `lang` i `resources/js/lib` nie ma miejscownika miesięcy („w listopadzie”), są tylko mianowniki `budget.month_name_*` (`lang/pl/ui.php:286`). W grupie `operations` nie ma też etykiety „Do wydania” ani „Saldo konta”. Istnieje `month_plan.to_spend` „Do wydania” (`:303`).
- `app.css` nie ma klasy podglądu (grep `preview` znajduje tylko `.import-preview-*`, `:738-799`).

Wpływ: przed zapisem użytkownik nie widzi, jak wpis zmieni Do wydania i saldo, dopóki wynik nie spadnie poniżej zera. Ostrzeżenia leżą w osobnym `Alert` daleko od odczytu, którego dotyczą.

Reguły i ADR:
- Pokazanie Do wydania przed → po nie zmienia reguły domeny. `CONTEXT.md:80` określa, kiedy pojawia się ostrzeżenie („negative and lower than before”), a ten warunek zostaje. FR-TRX-08 (`.docs/prd.md:191`) już mówi o pokazywaniu limitu „aktualny … i przewidywany po operacji”. Zmiana domeny (`CONTEXT.md` + ADR) byłaby potrzebna dopiero przy zmianie warunku ostrzeżenia, przy pokazywaniu Month Remaining w miesiącu z planem albo przy etykiecie „Pozostało” (`_Avoid_`, `CONTEXT.md:81`).
- Decyzja 1 (ostrzeżenia wewnątrz bloku podglądu, bez `Alert`) zmienia regułę wyglądu `.docs/design-system.md:77` („Ostrzeżenia w formularzach … używają `<Alert variant="warning">`”) i `.ai/rules/js.md:24` („Non-blocking warnings use the warning Alert variant”) dla dialogów formularzy. Następny wolny numer to **ADR-0041** (najwyższy to `0040-dialog-shell-form-fields-and-focus-follow-atrament.md`). Podgląd importu nie jest dialogiem formularza, więc reguła `Alert` / `bg-warning/10` zostaje tam bez zmian, o ile plan tego nie rozszerzy.
- Kolor wartości: `.docs/design-system.md:66` wymaga, by kwota ze znakiem („do wydania” też) brała kolor tylko z `amountToneClass`. DS przewiduje `destructive` dla ujemnego wyniku, co się z tym zgadza.

### C4. Kontrakt ADR-0040 łamany w stopce i stanach (kategoria: przypadkowa architektura)

Wzorzec (`CategoryFormDialog.vue`): `busy` z `<Form @start @finish>` (`:74`, `:171-172`), `onOpenChange` ignoruje zamknięcie przy `busy` (`:151-157`, `:165`, obejmuje `Esc` i nakładkę), `:show-close-button="!busy"` (`:166`, prop `ui/dialog/DialogContent.vue:18-20`), „Anuluj” `outline` i `:disabled="processing"` (`:345-348`), `<Spinner v-if="processing"/>` (`:364`), „Zapisz” `:disabled="processing || plannedAmountInvalid"` (`:362`). Ten sam mechanizm mają `ConfirmActionDialog.vue:526-545` i `CreateBudgetDialog.vue:57,104-128`.

W każdym z 4 dialogów (sprawdzone w pełnych plikach):
- `<Dialog @update:open="emit('update:open', $event)">` bez blokady, `<DialogContent>` bez `show-close-button`, `<Form>` bez `@start` / `@finish`. W trakcie zapisu dialog zamykają więc `Esc`, X, nakładka i „Anuluj”.
- „Anuluj” `variant="secondary"` i nie jest wyłączany: `ExpenseFormDialog.vue:691`, `IncomeFormDialog.vue:530`, `OwnTransferFormDialog.vue:302`, `CapitalMovementFormDialog.vue:392`. „Zapisz” bez `Spinner`, wyłączony tylko przy `processing`: `:703`, `:542`, `:314`, `:404`.
- Transfer i ruch kapitału biorą etykiety stopki z modułu `accounts` (`t.accounts.cancel` / `t.accounts.save`, `OwnTransferFormDialog.vue:311,315`, `CapitalMovementFormDialog.vue:401,405`).

Zła kwota (`parseMoneyInputToMinorUnits` zwraca `null` dla „12,345” i „abc”, `lib/format.ts:51-70`):
- `ExpenseFormDialog.vue:398-406`: `null` → 0, `Math.abs` zamienia „-5” na 5. Znak nakłada `prepareSubmit` na `@before` (`:408-418`, `:433`).
- `IncomeFormDialog.vue:274-282`: `null` → 0, wartość ujemna przechodzi bez zmian. `@before` jest na `:297`.
- `OwnTransferFormDialog.vue:151-159` i `CapitalMovementFormDialog.vue:204-212` przyjmują tylko `> 0`, inaczej 0, i nie mają `@before` (`:169-175`, `:222-228`).
- W żadnym z 4 dialogów nie ma komunikatu po stronie klienta, a „Zapisz” zostaje włączony. Odrzuca dopiero serwer (`ExpenseValidationRules.php:25` `notIn([0])` i `:68` `min:1`, `IncomeValidationRules.php:25`, `OwnTransferValidationRules.php:18`).
- „Wpisz kwotę, np. 1200,50.” istnieje tylko pod kluczami modułowymi `budget.planned_amount_invalid` (`lang/pl/ui.php:232`), `month_plan.amount_invalid` (`:332`), `accounts.opening_balance_invalid` (`:391`) i `term_deposits.opening_balance_invalid` (`:469`). Grupa `operations` (od `:472`) takiego klucza nie ma. `UiTranslations.operations` to `Record<string,string>` (`types/shell.ts:46`), więc brak klucza nie wyjdzie w `vue-tsc`.

Wpływ: można zamknąć dialog w połowie zapisu i nie wiedzieć, czy operacja się zapisała. Literówka w kwocie nie daje komunikatu pod polem i kończy się błędem serwera o kwocie 0. „Anuluj” wygląda inaczej niż w dialogach Budżetu.

### C5. Brak bramki stanów i kontroli literałów (kategoria: przypadkowa architektura)

- `/dev/ui/dialogs` (`routes/web.php:28-29`, `app/Http/Controllers/Dev/DialogsKitchenSinkController.php:14-44`) renderuje 14 stanów: `CategoryFormDialog` ×7, `CreateBudgetDialog` ×2, `ConfirmActionDialog` ×4 i `AccountFormDialog` ×1 (`DialogsKitchenSink.vue:18-52`, `:116-236`). Nie ma wśród nich żadnego z 4 dialogów operacji. Dialog otwiera `?open=<stan>` (`:69-87`).
- Żaden z 4 dialogów nie ma propa `form?`. Każdy ma na stałe przypiętą akcję Wayfinder (`ExpenseFormDialog.vue:105-106`, `IncomeFormDialog.vue:95-96`, `OwnTransferFormDialog.vue:67-68`, `CapitalMovementFormDialog.vue:89-90`). Stany „zapis w toku” i „błędy serwera” wymagają więc propa jak `CategoryFormDialog.vue:55-58` (`form?`, wartość początkowa kwoty), który kieruje na `dev.ui.dialogs.validate`.
- Brak fixture'ów propsów dialogów: `resources/js/lib/operations-fixtures.ts` ma tylko wiersze listy, a jego konta (`:14-26`) nie są eksportowane i nie mają `id` ani salda. Pomocniki `EntryToSpendInputs` są lokalne w `entry-to-spend-warning.test.ts:14-58`. Brak też fixture'ów `handEntryMonthRemaining`.
- `scripts/check-ui-literals.mjs:9-91` (lista plików, `npm run check:ui`) nie zawiera tych 4 dialogów, a `resources/js/ui-contract.test.ts:13-32` skanuje literały tylko w plikach Budżetu. `tests/Feature/DialogsKitchenSinkTest.php` sprawdza tylko 404 poza `local` (`:13-29`). Żaden test nie odwołuje się do 4 plików dialogów.
- Tytuły dialogów wydatku i dochodu to zagnieżdżone ternary (`ExpenseFormDialog.vue:437-449`, `IncomeFormDialog.vue:301-309`). Po C2 tytuł wynika z jednego wybranego typu.

Wpływ: stanów ostrzeżenia, zapisu w toku, błędu i złej kwoty nie da się zobaczyć ani zrobić im zrzutu bez ręcznego wypełniania formularza w Operacjach na prawdziwych danych. Następny agent może wprowadzić literał bez czerwonego CI.

### Odroczone

- **Jeden dialog „Nowa operacja” z wyborem typu (jak w DS):** odrzucony w `ui-operations-atrament` (decyzja 2) i w ADR-0039. Zostaje odrzucony (decyzja 3).
- **Month Remaining przed → po (FR-TRX-08, `.docs/prd.md:191`):** `hand-entry-month-remaining-preview.ts` zwraca tylko dwie flagi (`:577-580`, `:742-745`), „po” jest lokalne (`:639`, `:731`), „przed” nie istnieje, a plik nie ma testów. Odroczone decyzją 2. Luka FR-TRX-08 zostaje zapisana.
- **Możliwy błąd roku w `hand-entry-month-remaining-preview.ts:435`:** edytowany rekord niesie `operation_month` bez roku, więc edycja przenosząca datę między latami może trafić w zły rok. Wniosek pracownika badającego ten plik, bez testu. Poza zakresem UI, do zgłoszenia jako issue.
- **`AccountFormDialog`, `TermDepositFormDialog`:** poza tą zmianą (`change.md`).
- **Saldo konta docelowego transferu:** `hand-entry-warning-preview.ts` przewiduje tylko konto źródłowe (`:312`). Konto docelowe tylko rośnie, więc podgląd go nie potrzebuje.

## Decisions

Odpowiedzi użytkownika z 2026-11-05 (pytania po researchu):

1. **Ostrzeżenia w bloku podglądu, jak w DS:** ostrzeżenie jako tekst `warning` wewnątrz bloku „przed → po”, bez osobnego `Alert`. Wymaga ADR-0041 przed kodem (zmiana `.docs/design-system.md:77` i `.ai/rules/js.md:24` dla dialogów formularzy).
2. **Odczyty w podglądzie:** Do wydania przed → po (dochód i wydatek, miesiąc z planem; przy edycji do 2 miesięcy), roczny limit kategorii (wydatek i zwrot) i saldo konta przed → po we wszystkich 4 dialogach (transfer: konto źródłowe; ruch kapitału: konto i lokata). Bez nowych obliczeń, tylko wystawienie wartości już liczonych. Month Remaining zostaje przy samym ostrzeżeniu.
3. **Typ operacji:** `SegmentedControl` `block` `primary` zamiast checkboxów (wydatek: Wydatek | Zwrot, w edycji zwrotu także Dochód; dochód w edycji: Dochód | Zwrot wydatku). Dialogi zostają osobne.
4. **„Opis z wyciągu”:** statyczny blok tekstu (etykieta + tekst na `muted`, `rounded-md`, nazwana klasa w `app.css`) zamiast `textarea readonly`.

## Code References

- `resources/js/components/ExpenseFormDialog.vue:398-418` — `syncAmount` / `prepareSubmit`, cicha zła kwota
- `resources/js/components/ExpenseFormDialog.vue:533-588` — checkboxy „zamień na dochód” i „Zwrot”
- `resources/js/components/ExpenseFormDialog.vue:612-685` — pudełko limitu i `Alert` ostrzeżeń
- `resources/js/components/ExpenseFormDialog.vue:687-706` — stopka bez spinnera i blokady
- `resources/js/components/CapitalMovementFormDialog.vue:277-336` — reka `Select` kierunku i lokaty
- `resources/js/components/CategoryFormDialog.vue:74,151-172,345-364` — wzorzec blokady zamykania, stopki i błędu kwoty
- `resources/js/components/FormField.vue` — pole kontraktu (slot `id`, `describedBy`, `invalid`, `controlClass`; `money`)
- `resources/js/components/AccountSelect.vue:13-24,48` — brak propów aria na triggerze
- `resources/js/lib/entry-to-spend-warning.ts:151-222` — `before`/`after` liczone i odrzucane
- `resources/js/lib/import-to-spend-warnings.ts:81` — drugi konsument `previewToSpendWarnings`
- `resources/js/lib/hand-entry-warning-preview.ts:52-58,274-285,312,355,394` — saldo „po” liczone, zwracane tylko jako flaga
- `resources/js/pages/dev/DialogsKitchenSink.vue:18-52,116-236` — stany sinka
- `scripts/check-ui-literals.mjs:9-91` — lista plików kontroli literałów
- `lang/pl/ui.php:232,332,391,469,625-633` — klucze złej kwoty i podglądu
- `CONTEXT.md:80-81` — reguła To Spend i terminy `_Avoid_`
- `.docs/design-system.md:66,77` — kolor kwoty ze znakiem, ostrzeżenia formularzy jako `Alert`

## Architecture Insights

- Kontrakt dialogów ma jedno źródło: `ui/dialog` (powłoka), `FormField` (pole), `SegmentedControl` (wybór), `Spinner` + `busy` (zapis). Brakuje mu trzech elementów, których te dialogi potrzebują: bloku podglądu (klasa w `app.css` i, wobec 4 konsumentów, mały komponent w `components/`), bloku tylko do odczytu oraz propów aria w `AccountSelect` / `CategorySelect`.
- Kopie kalkulatorów w TS istnieją dla podglądu przed zapisem (AGENTS.md „Month logic lives twice”). Podgląd „przed → po” nie potrzebuje nowej kopii, tylko wystawienia wartości z `entry-to-spend-warning.ts` i `hand-entry-warning-preview.ts`. Zmiana w `entry-to-spend-warning.ts` dotyka Importu, więc nowa funkcja musi stać obok, a nie zamiast `previewToSpendWarnings`.
- Sink kategorii pokazał, jak kierować dialog na trasę walidacyjną (`form?` + `dev.ui.dialogs.validate`). Dialogi operacji potrzebują tego samego propa i modułu fixture'ów propsów (`entryToSpend`, `handEntryMonthRemaining`, konta z saldem, kategorie z planem i realizacją).

## Historical Context (from prior changes)

- `context/changes/category-dialog-atrament/research.md:86` — pola dialogów Expense, Income, OwnTransfer, CapitalMovement (oraz Account, TermDeposit) odroczone „w osobnych zmianach”. Potwierdzone: te 4 pliki dostały tylko powłokę z prymitywów.
- `context/changes/category-dialog-atrament/plan.md:38` — odroczone także „blokada zamykania”. Potwierdzone: brak blokady (C4).
- `context/changes/category-dialog-atrament/research.md:89` — podgląd „przed → po” N/A dla dialogu kategorii. Nadal aktualne. Ta zmiana wprowadza go pierwszy raz.
- `context/changes/category-dialog-atrament/plan-brief.md:33` — klucz złej kwoty modułowy (`budget.planned_amount_invalid`). Wzorzec do powtórzenia jako `operations.amount_invalid`.
- `context/archive/2026-11-05-ui-operations-atrament/research.md:89,105` — „dialogi bez zmian” i odrzucony jeden dialog z wyborem typu. Odrzucenie nadal obowiązuje. „Bez zmian” dotyczyło tamtej zmiany.
- `context/archive/2026-11-03-month-plan-ui-contract/plan.md:76-82`, `context/changes/category-dialog-atrament/plan.md:364` — przepis na zrzuty: jednorazowy Playwright z cache `~/.cache/ms-playwright`, 1280 i 390 px, oba motywy, pliki `<stan>-<light|dark>-<1280|390>.png`. Playwright nie jest w `package.json`.

## Related Research

- `context/changes/category-dialog-atrament/research.md` — kontrakt dialogów (ADR-0040), C1–C5 dla powłoki i dialogu kategorii.
- `context/archive/2026-11-05-ui-operations-atrament/research.md` — widok Operacji, decyzje o dialogach.

## Open Questions

Do rozstrzygnięcia w planie (nie wymagają nowych faktów):

- Etykieta Do wydania: miejscownik „w listopadzie” wymaga 12 nowych kluczy (ich brak sprawdzony w `lang` i `resources/js/lib`). Alternatywa to „Do wydania (listopad 2026)” z istniejących mianowników (`budget.month_name_*`). Przy edycji przenoszącej datę między miesiącami podgląd ma 2 wiersze.
- Kształt nowej funkcji w `entry-to-spend-warning.ts` (np. `previewToSpendReadings` zwracające `{year, month, beforeMinorUnits, afterMinorUnits}[]`) i wyprowadzenie z niej ostrzeżenia bez zmiany wyniku dla Importu. Test w `entry-to-spend-warning.test.ts`.
- Saldo „po” z `hand-entry-warning-preview.ts`: rozszerzenie typu zwrotu (`:52-58`) i pierwsze testy tego pliku (dziś nie ma żadnych).
- Komponent podglądu: klasa w `app.css` (`@layer components`) plus mały komponent w `components/` (np. `FormPreview` z wierszami odczytów i ostrzeżeniami) dla 4 dialogów. Treść ADR-0041.
- Kolejność pól według DS (wybór typu na górze, kwota i data w jednym rzędzie `ht-field-row`) a obecna kolejność (data, opis, kontrahent, konto, kategoria, kwota). Ta decyzja wymaga przeglądu planu.
