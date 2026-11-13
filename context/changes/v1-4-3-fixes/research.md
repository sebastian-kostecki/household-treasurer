---
date: 2026-11-13T21:01:49+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 85e7a673aad6d373e5b05c0cc0f5995f922bfac4
branch: worktree-v1-4-3-fixes
repository: household-treasurer
topic: "Audyt dwukierunkowy /10x-ui dla v1-4-3-fixes: ciemny motyw (color-scheme, kolory kategorii), pickery (kategorie, konta), Plan miesiąca (kwoty, odchylenie)"
tags: [research, ui-audit, dark-mode, atrament, month-plan, operations-filters, category-appearance, account-avatar]
status: complete
last_updated: 2026-11-13
last_updated_by: Claude (claude-opus-5-5)
last_updated_note: "Decyzje użytkownika D1–D4 do otwartych pytań (sekcja Decisions); Open Questions rozstrzygnięte"
---

# Research: audyt dwukierunkowy /10x-ui dla v1-4-3-fixes

**Date**: 2026-11-13T21:01:49+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 85e7a673aad6d373e5b05c0cc0f5995f922bfac4 (`development` c64ccd6 + commit otwarcia zmiany)
**Branch**: worktree-v1-4-3-fixes
**Repository**: household-treasurer

## Research Question

Audyt dwukierunkowy wg `/10x-ui`, zarzuty w `## Charges` z plik:linia i wpływem na użytkownika. Weryfikacja w przeglądarce w ciemnym motywie:

1. brak `color-scheme` w `.dark` (`resources/css/app.css:191`) → pola daty i natywne `<select>`;
2. `categoryTileStyle` (`resources/js/lib/category-appearance.ts:310`) bez wersji dla ciemnego motywu;
3. natywny filtr kategorii (`resources/js/components/OperationsFilters.vue:445`) kontra `CategorySelect` i jego kolejność;
4. gdzie giną ikonki kont mimo `AccountAvatar` w `resources/js/components/AccountSelect.vue:110`;
5. Plan miesiąca: zlewające się kwoty (`.month-plan-grid`) i odchylenie bez koloru dla wydatku poniżej planu (`resources/js/components/MonthPlanItemRow.vue:116`); czy zmiana etykiety odchylenia dotyka glosariusza (ADR).

Założenie do sprawdzenia: skan wartości na sztywno = 0.

## Summary

- **(1) potwierdzone.** `color-scheme` nie występuje w `app.css` ani w `resources/views/app.blade.php`. W przeglądarce w ciemnym motywie `getComputedStyle(...).colorScheme` to `normal` dla `<html>`, natywnego `<select>`, `<option>` i `<input type="date">`. Ikona kalendarza w polu daty jest ciemna na ciemnym polu (zrzut). Lista natywnego selecta to okno systemowe, którego headless Chromium nie fotografuje; jej wygląd (jasny tekst `#e6ecf3` na tle UA w schemacie jasnym) jest wnioskiem z wyliczonych stylów, nie obserwacją.
- **(2) potwierdzone co do kodu, przesunięte co do wpływu.** `categoryTileStyle` skleja hex z danych z alfą `26` (15%) bez tokenu i bez wariantu motywu. Ikona na kafelku ma jednak w ciemnym motywie kontrast ≥ 3:1 dla 23 z 24 kolorów palety (wyjątek `#be185d`, 2,69:1), a w jasnym tylko dla 9 z 24. Rzeczywisty problem ciemnego motywu jest w `CategoryColorPicker`: próbka to samo tło 15% (1,08–1,33:1 wobec karty), więc 24 kolory wyglądają jak podobne ciemne kwadraty (zrzut).
- **(3) potwierdzone, z blokadą decyzyjną.** Filtr jest natywny, ma dwie `optgroup` (Dochód/Wydatek) i sortuje po typie, potem po `name` operatorem `<=>` (`app/Support/OperationsFilterOptions.php:78`), a nie w kolejności listy kategorii. Każdy inny picker kategorii używa `CategorySelect` z kolejnością listy i grupami. **ADR-0069:13 każe filtrowi Operacji zostać płaskim**, więc grupowanie wymaga nowego ADR (lub poprawki ADR-0069).
- **(4) `AccountSelect` nie gubi ikon.** W przeglądarce: trigger, opcje i chip aktywnego filtra pokazują logo (BNP, mBank) albo `Coins` (gotówka). Ikony giną w trzech natywnych selectach **lokat** (decyzja ADR-0054:13, payload bez `bank`/`mark_url`), w znakach bez logo z założenia (ING, Pekao, Santander, Inny) oraz **w ciemnym motywie dla logo Revolut** (czarne „R” na `--muted` `#19243a`, ok. 1,35:1) i słabiej Lokaty Facto (ok. 2,1:1).
- **(5a) potwierdzone poniżej `xl`.** Przy 390 px cztery kolumny kwot mają po ok. 72 px i 12 px odstępu: pole planu ucina `123456,78`, odchylenie `-123 299,98` łamie się na dwie linie (zrzut). Poniżej `xl` etykiety „Zatwierdzony”/„Faktycznie” stoją niżej niż „Plan”/„Odchylenie”, a pole planu pokazuje `8500,00` bez separatora obok `2 500,00`. Na 1440 px przy tych samych kwotach kolumny się nie stykają.
- **(5b) potwierdzone i świadome.** Wydatek poniżej planu (`-1 343,20`) ma kolor `foreground`; tak postanowiono w S-04 („ujemne bez koloru”, bo design system nie miał takiego znaczenia). Pomalowanie go na `success` to nowe znaczenie tokenu: wpis w `design-system.md` + ADR (precedens ADR-0060, ADR-0074). Zmiana etykiety na „zostało / przekroczono” **dotyka glosariusza**: „Pozostało” jest terminem _Avoid_ dla To Spend i Year Result, „zostało X na Y mies.” już stoi w tym samym wierszu (Even Annual), a znak odchylenia nie oznacza przekroczenia dla Even Annual (przekroczenie liczone rokiem) i Irregular (odchylenie ≥ 0). Zostawienie etykiety „Odchylenie” glosariusza nie dotyka.
- **Skan wartości na sztywno: 0 w 18 plikach `.vue` widoków; 1 trafienie w `category-appearance.ts:145`** (`DEFAULT_CATEGORY_COLOR = '#868e96'`, wartość danych z palety). Skan nie łapie doklejanej alfy `${color}26` (`category-appearance.ts:315`), więc „0” nie dowodzi, że kafelek kategorii korzysta z tokenów.

## Środowisko weryfikacji

- Podgląd worktree na `http://localhost:8090` (`php -S` w obrazie `sail-8.5/app`, kontener `ht-preview-v143`), baza `preview_v1_4_3_fixes` po `migrate:fresh --seed` (`DemoHouseholdSeeder`, `test@example.com`). Worktree `.env` ma `DB_DATABASE=preview_v1_4_3_fixes`. Że serwer czyta tę bazę, potwierdza sesja użytkownika 1 w jej tabeli `sessions` (`SESSION_DRIVER=database`).
- Przeglądarka: Playwright Chromium (bundled), ciemny motyw przez cookie i `localStorage` `appearance=dark` (mechanizm: `resources/js/composables/useAppearance.ts:24-29, 111-114`, `resources/views/app.blade.php:2`).
- Dane demo: 3 konta (mBank, BNP Paribas, Gotówka), 8 kategorii bez grup, plan listopada 2026 w trybie propozycji. Kwoty w teście obciążenia wpisane w pole planu bez zatwierdzania.
- Zrzuty: `context/changes/v1-4-3-fixes/screenshots/research-*.png`.
- Kontrast liczony wzorem WCAG 2.x z wartości `:root`/`.dark` i palety `app/Support/Categories/CategoryColors.php:12-37`, kafelek jako mieszanina koloru z alfą 0x26/255 na `--card` (`#ffffff` / `#111a27`).

## Charges

### C1. Ciemny motyw nie ustawia `color-scheme` — natywne kontrolki zostają jasne (brakujący token)

- **Dowód (źródło):** blok `.dark` (`resources/css/app.css:191-242`) i `:root` (`app.css:115-189`) nie mają `color-scheme`; `app.blade.php:22-30` ustawia tylko `background-color` dla `html` i `html.dark`. Wyszukiwanie `color-scheme|colorScheme` w `resources/` i `app/`: 0 trafień.
- **Dowód (przeglądarka, `/operations`, filtry rozwinięte):** `colorScheme: normal` na `<html>`, `#operations-filter-category`, jego `<option>` i `#operations-filter-date-from`. `<select>` ma tło `input/30` (półprzezroczyste, `app.css:703-705` `.native-select … dark:bg-input/30 … bg-transparent`), `<option>` tło `rgba(0,0,0,0)` i tekst `rgb(230,236,243)`. Ikona kalendarza w polu daty jest ciemna na ciemnym polu: `screenshots/research-operations-filters-dark.png`.
- **Wniosek (nieobserwowany w headless):** okno listy natywnego selecta rysuje przeglądarka/system w schemacie jasnym; opcje z przezroczystym tłem dostają tło UA (jasne), a tekst dziedziczy jasny `foreground`. Do potwierdzenia ręcznie w Chrome/Edge na Windows.
- **Zasięg:** `.native-select` w 13 miejscach (`OperationsFilters.vue:425, 447, 511`; `CapitalMovementFormDialog.vue:362`; `OperationsImport.vue:249`; `ImportColumnMapping.vue:88, 112, 136, 181, 206, 230`; `ImportPreviewRow.vue:422, 562`) i `type="date"` w 10 (`OperationsFilters.vue:535, 543`; `OwnTransferFormDialog.vue:273`; `TermDepositFormDialog.vue:211, 229`; `IncomeFormDialog.vue:471`; `CapitalMovementFormDialog.vue:326`; `BalanceReconciliationDialog.vue:144`; `ExpenseFormDialog.vue:581`; `AccountFormDialog.vue:236`). Pasek przewijania i inne kontrolki UA podlegają tej samej właściwości.
- **Wpływ na użytkownika:** w ciemnym motywie nie widać ikony kalendarza w żadnym polu daty, a lista roku, kategorii, lokaty i mapowania kolumn importu otwiera się jako jasne okno z jasnym tekstem (wniosek), więc wybór jest zgadywaniem.
- **Kierunek poprawki:** warstwa tokenów — `color-scheme: light` w `:root`, `color-scheme: dark` w `.dark` (i odpowiednio w stylu inline `app.blade.php`, żeby nie było błysku). Bez edycji klas komponentów.

### C2. Kolor kategorii liczony poza tokenami — w ciemnym motywie próbki palety są nieodróżnialne (brakujący token)

- **Dowód (źródło):** `categoryTileStyle` (`resources/js/lib/category-appearance.ts:310-318`) zwraca `{ backgroundColor: \`${color}26\`, color }`; ta sama alfa w obu motywach, kolor z danych (`CategoryAppearance.php:25`, paleta `CategoryColors.php:12-37`). Konsumenci: `CategoryChip.vue:46` (kafelek z ikoną), `CategoryColorPicker.vue:39` (próbka bez ikony), `CategoryIconPicker.vue:35`. Reguła: `context/foundation/design-system.md:66` („kafelek ikony w `CategoryChip` (tło 15%)”).
- **Dowód (liczby, 24 kolory):** kontrast ikony do kafelka — ciemny: 23/24 ≥ 3:1 (min 2,69:1 `#be185d`, max 6,87:1), jasny: 9/24 ≥ 3:1 (min 1,74:1 `#eab308`). Kafelek wobec karty: ciemny 1,08–1,33:1, jasny 1,10–1,28:1.
- **Dowód (przeglądarka):** w Planie miesiąca chipy są czytelne w ciemnym motywie (`screenshots/research-month-plan-dark-1440.png`). W oknie „Nowa kategoria” (`/dev/ui/dialogs?open=category-list-new`) 24 próbki to ciemne, zbliżone kwadraty (`screenshots/research-category-colour-picker-dark.png`); `aria-label` próbki to sam numer (`CategoryColorPicker.vue:40-45`).
- **Korekta założenia:** „brak wersji dla ciemnego motywu” jest prawdą w kodzie, ale ikony chipów w ciemnym motywie są czytelniejsze niż w jasnym. Ciemny motyw psuje próbki wyboru koloru (sam odcień 15% bez ikony).
- **Wpływ na użytkownika:** wybierając kolor kategorii w ciemnym motywie, użytkownik nie widzi, jaki kolor wybiera, i dowiaduje się tego dopiero z chipa po zapisie; kategoria w `#be185d` ma w ciemnym motywie ikonę poniżej 3:1.
- **Kierunek poprawki:** alfa (lub `color-mix`) jako zmienna w `:root`/`.dark` zamiast literału `26`; próbka w `CategoryColorPicker` pokazująca kolor pełny (np. kropka/ikona w kolorze) zamiast samego tła. Zmiana zdania „tło 15%” w `design-system.md:66` idzie na tę stronę; ADR, jeśli zmienia decyzję. Kolor kategorii nadal tylko tożsamość (`design-system.md:66`, ADR-0071).

### C3. Filtr kategorii Operacji jako natywny select w innej kolejności niż reszta aplikacji (brakujący współdzielony komponent + przypadkowa architektura)

- **Dowód (widok):** `OperationsFilters.vue:438-478` — natywny `<select>` z `optgroup` „Dochód”/„Wydatek” (`incomeCategories`/`expenseCategories`, `:83-88`), same nazwy, bez ikon i grup. W przeglądarce opcje: Dochód [Odsetki, Pensja], Wydatek [Inne, Mieszkanie, Rozrywka, Transport, Zdrowie, Żywność].
- **Dowód (dane):** `OperationsFilterOptions::categories` (`app/Support/OperationsFilterOptions.php:32-81`) — kategorie z operacjami (opcjonalnie w roku), z archiwalnymi, plus wybrana; `usort` w `:78` po typie, potem `name` operatorem `<=>`. To porównanie bajtowe, nie kolejność listy (`sort_order`, `name_key`, id — `CategoryListReading.php:21-24`) i nie porządek polski: nazwy na Ł/Ś/Ż trafiają za Z, wielkie litery przed małymi (wniosek z semantyki `<=>` na UTF-8, nieuruchomione). Kształt `OperationsFilterCategory` (`OperationsFilters.vue:35-41`) nie ma `group`.
- **Porównanie:** wszystkie inne pickery kategorii (`ExpenseFormDialog.vue:599`, `IncomeFormDialog.vue:489`, `OperationCategoryDialog.vue:207`, `ImportPreviewRow.vue:606, 627`, `CategoryMergeDialog.vue:79`) używają `CategorySelect` z `CategoryChip` i grupami z `groupSelectOptions` (`resources/js/lib/category-tree.ts:162-192`; nie sortuje, zachowuje kolejność serwera = kolejność listy). `design-system.md:16`: `Select` (Reka), gdy opcja pokazuje ikonę.
- **Blokada decyzyjna:** `context/foundation/adr/0069-category-group-order-and-budget-rows.md:13` — „the Operations filter and the command palette stay flat”; powtórzone w `.ai/rules/reports.md:28`. Grupowanie filtra zmienia zapisaną decyzję → nowy ADR (lub poprawka ADR-0069) przed kodem. Ikony i kolejność listy bez grup ADR-0069 nie łamią.
- **Czego brakuje `CategorySelect`:** opcji „wszystkie” (wzór `AccountSelect.vue:26, 39-57, 100-105`), sekcji typu (dochód/wydatek), sufiksu archiwalnej (wzór `archivedSuffix`, `AccountSelect.vue:27, 63-69`). Po stronie serwera: `group` i kolejność listy w payloadzie filtra.
- **Testy:** `tests/Feature/OperationsFilterTest.php:193-194, 237-239, 449-454, 481-482` przypinają `filterCategories` po indeksie (indeks `:237-239` zależy od obecnego sortowania); żaden test nie przypina reguły kolejności ani markupu `<select>`.
- **Wpływ na użytkownika:** w filtrze kategorie są w innej kolejności i bez ikon niż na liście Kategorii i w oknie operacji, więc szuka się ich od nowa; nazwa na Ł/Ś stoi za „Zdrowie”; w ciemnym motywie lista dodatkowo dziedziczy C1.

### C4. Ikonki kont giną poza `AccountSelect`, a w ciemnym motywie ciemne logo znika na kafelku (przypadkowa architektura + brakujący token)

- **`AccountSelect` działa (przeglądarka):** lista „Konto” w filtrze Operacji pokazuje `Coins` dla Gotówki i logo BNP oraz mBank (obrazy załadowane); po wyborze konta trigger i chip aktywnego filtra pokazują logo (`screenshots/research-operations-account-filter-dark.png`). Kod: slot `SelectValue` (`AccountSelect.vue:82-97`), `AccountAvatar` w opcji (`:112-118`), payload `AccountIdentity::payload()` (`app/Support/AccountIdentity.php:33-42`) we wszystkich pickerach kont.
- **Gdzie giną (źródło):**
  - natywne selecty lokat: `OperationsFilters.vue:509-525`, `CapitalMovementFormDialog.vue:358-375`, `ImportPreviewRow.vue:558-575` — z decyzji `context/foundation/adr/0054-account-avatar-with-bank-logo.md:13` („stay native and show the name only, because they need a payload change”); payloady lokat bez `bank`/`mark_url`: `OperationsController.php:150-158`, `OperationsFilterOptions.php:98-112`, `CsvImportController.php:490-493`;
  - banki bez logo z założenia: `app/Enums/Bank.php` (`markPath()` = `null` dla ING, Pekao, Santander, Inny) → skrót `mark-label` lub `Building2`.
  - poza zakresem pickerów: wiersz listy Operacji pokazuje konto tekstem („Kawiarnia · Gotówka”, `screenshots/research-operations-account-filter-dark.png`).
- **Ciemny motyw (przeglądarka, `/dev/ui/accounts`, „Znaki banków”):** kafelek `AccountAvatar` ma tło `--muted` `rgb(25,36,58)`; logo Revolut (czarne „R”) prawie niewidoczne (ok. 1,35:1), Lokaty Facto słabe (ok. 2,1:1 dla `#00569D`), Alior, BNP, mBank, PKO BP czytelne (`screenshots/research-bank-marks-dark.png`). Kolory logo SVG wg raportu agenta: `revolut.svg` `#000000`, `lokaty-facto.svg` `#00569D`/`#5488C7`.
- **Wpływ na użytkownika:** wybierając lokatę (filtr, ruch kapitału, import) użytkownik widzi same nazwy, inaczej niż przy kontach; w ciemnym motywie konto Revolut ma pusty kafelek w każdym pickerze, tabeli Kont i Dashboardzie.
- **Kierunek poprawki:** powierzchnia znaku jako token (np. jasna płytka pod logo w `.dark`) zamiast wspólnego `--muted`; awatary lokat wymagają zmiany ADR-0054 (nowy ADR) i payloadów.

### C5. Plan miesiąca: kwoty ciasne poniżej `xl`, odchylenie wydatku poniżej planu bez znaczenia (brakujący token/układ + decyzja glosariusza)

**C5a — kwoty**

- **Dowód (źródło):** `.month-plan-grid` poniżej `xl` to `repeat(4, minmax(0, 1fr))` z `gap-x-3` (12 px) (`app.css:857-870`); od `xl` stałe kolumny `9rem 7rem 8rem 7rem 7rem 8rem` (`:872-878`). Komórki „Zatwierdzony” i „Faktycznie” to `flex flex-wrap items-baseline` (`app.css:890-903`), „Plan” i „Odchylenie” mają etykietę `block` (`MonthPlanItemRow.vue:240, 284`).
- **Dowód (przeglądarka):** przy 390 px kolumna ma ok. 72 px; po wpisaniu `123456,78` pole planu ucina wartość (`scrollWidth > clientWidth`), a `-123 299,98` łamie się na „-123 / 299,98” (`screenshots/research-month-plan-stress-390.png`). Przy 1100 i 390 px etykiety „Plan”/„Odchylenie” stoją wyżej niż „Zatwierdzony”/„Faktycznie” (`research-month-plan-dark-1100.png`, `-390.png`). Pole planu pokazuje `8500,00` bez separatora obok `2 500,00` w „Faktycznie” (`research-month-plan-dark-1440.png`). Na 1440 px te same kwoty mają ≥ 38 px odstępu między „Faktycznie” a „Odchylenie”.
- **Wpływ na użytkownika:** na telefonie i tablecie kwoty pięciocyfrowe nachodzą na siebie lub łamią się, a nierówne etykiety utrudniają odczytanie, która liczba jest planem, a która faktem.

**C5b — kolor i etykieta odchylenia**

- **Dowód (źródło):** `deviationClass` (`MonthPlanItemRow.vue:116-130`): `null` → `muted-foreground`, `overPlan` → `destructive`, dochód > 0 → `success`, w pozostałych przypadkach brak klasy. Znak: faktycznie − plan dla obu typów (`app/Support/MonthItemDeviation.php:23-40`, `resources/js/lib/month-item-deviation.ts:18-37`).
- **Dowód (przeglądarka):** „Żywność” (wydatek poniżej planu) `-1 343,20` w `rgb(230,236,243)` (`foreground`); „Mieszkanie” po obniżeniu planu do `1500,00` przy fakcie `2 500,00` ma `+1 000,00` w `destructive`.
- **Historia:** brak koloru był świadomy — `context/archive/2026-11-02-month-items-plan-vs-actual/plan-brief.md:23` („wydatek ponad plan destructive, dochód > 0 success, ujemne bez koloru”, uzasadnienie „kolory z design-system”), `…/research.md:142` (design system nie określał „poniżej planu”).
- **Kolor:** `design-system.md:55` wylicza znaczenia `success`; wydatek poniżej planu wśród nich nie ma. Dodanie go to nowe znaczenie tokenu → wpis na tej stronie + ADR (AGENTS.md, „Hard rules”). Precedens kolorowania wg korzyści dla typu: ADR-0060:9, ADR-0074:13. `design-system.md:78` zastrzega kolor kwot ze znakiem dla `amountToneClass` (`> 0` → success) — dla wydatku ta reguła daje odwrotny sens, więc potrzebny jest wariant zależny od typu, nie lokalny helper. Rozjazd już istniejący: `MonthSummaryCard.vue:75-80` (wg raportu agenta: tylko gorzej niż plan → `destructive`, bez `success`).
- **Etykieta — glosariusz:** `glossary.md` nie ma hasła Deviation/Odchylenie (odchylenie pojawia się w :113, :149). „Pozostało” jest _Avoid_ w To Spend (`glossary.md:114`) i Year Result (`:162`); Month Remaining ma copy „Pozostało w miesiącu” (`:88-90`); Even Annual pokazuje „zostało X na Y mies.” (`:67`, `MonthPlanItemRow.vue:169-181`) w tym samym wierszu. Znak odchylenia nie oznacza przekroczenia dla Even Annual (przekroczenie wg roku; `tests/Feature/MonthPlanPageTest.php:1057-1059`: odchylenie +21 667 przy `is_over_plan` false) ani Irregular (odchylenie = nadwyżka ≥ 0). **Werdykt:** „zostało / przekroczono” w tej kolumnie dotyka glosariusza → najpierw hasło w `glossary.md` i nowy ADR. Zostawienie etykiety „Odchylenie” i zmiana tylko koloru → `design-system.md` + ADR, glosariusz bez zmian.
- **Wpływ na użytkownika:** oszczędność na wydatku wygląda tak samo jak brak danych, więc trzeba czytać znak, żeby wiedzieć, czy jest dobrze; przekroczenia są sygnalizowane podwójnie (czerwone odchylenie i odznaka „ponad plan”, `resources/js/lib/month-plan-status.ts:33-66`), wynik poniżej planu wcale.
- **Testy:** brak testu komponentu na `deviationClass`; `tests/e2e/irregular-collected.spec.ts:112-115` sprawdza `.month-plan-cell-deviation` przez `toContainText('200,00')`; strażnicy: `tests/Unit/MonthPlanHardcodedUiTest.php`, `resources/js/ui-contract.test.ts:458-468` (tylko `format*Amount`), kitchen sink `pages/dev/MonthPlanSink.vue:311-373`.

## Skan wartości na sztywno

Wyrażenie z `/10x-ui` uruchomione na 19 plikach: `pages/MonthPlan.vue`, `components/MonthPlan*.vue` (7), `MonthSummaryCard.vue`, `MonthYearSummary.vue`, `OperationsFilters.vue`, `OperationsActiveFilterChips.vue`, `AccountSelect.vue`, `AccountAvatar.vue`, `CategorySelect.vue`, `CategoryChip.vue`, `CategoryColorPicker.vue`, `CategoryIconPicker.vue`, `lib/category-appearance.ts`.

- `.vue` (18 plików): **0 trafień** — założenie potwierdzone.
- `category-appearance.ts:145`: `DEFAULT_CATEGORY_COLOR = '#868e96'` — wartość danych równa kolorowi palety (`CategoryColors.php:29`), nie styl widoku.
- Ślepa plamka: alfa doklejana do koloru danych (`category-appearance.ts:315`, `${color}26`) nie pasuje do wzorca. C2 to zarzut brakującego tokenu mimo zerowego skanu.

## Źródło → widoki

Liczba klas tokenów kolorów (`bg|text|border|ring|outline-<token>`) / importów `@/components/ui/` na plik: `MonthPlanItemRow.vue` 13/1, `MonthPlanTable.vue` 9/3, `MonthSummaryCard.vue` 8/0, `OperationsFilters.vue` 7/6, `MonthPlanMatchActual.vue` 6/1, `AccountAvatar.vue` 4/0, `CategoryIconPicker.vue` 4/0, `MonthPlanStatPanel.vue` 3/1, `MonthPlanGroupRow.vue` 2/1, `MonthYearSummary.vue` 2/0, `CategoryColorPicker.vue` 2/0, `OperationsActiveFilterChips.vue` 2/1, `MonthPlanTitleRow.vue` 1/1, `pages/MonthPlan.vue` 1/3, `AccountSelect.vue` 0/1, `CategorySelect.vue` 0/1, `MonthPlanEditBar.vue` 0/2, `CategoryChip.vue` 0/0, `category-appearance.ts` 0/0. Tokeny są czytane; jedyna ścieżka koloru omijająca tokeny to kafelek kategorii (C2). Plik zasad agenta nie zachęca do wartości jednorazowych: `design-system.md:19` i `.ai/rules/js-month-plan.md` zakazują wartości arbitralnych w widokach.

## Code References

- `resources/css/app.css:115-189` `:root`, `:191-242` `.dark` — brak `color-scheme`
- `resources/css/app.css:703-705` `.native-select` (`dark:bg-input/30`, `bg-transparent`)
- `resources/css/app.css:857-878` `.month-plan-grid` (4×1fr poniżej `xl`, stałe kolumny od `xl`), `:890-913` komórki
- `resources/views/app.blade.php:2, 22-30` klasa `dark` i tło bez `color-scheme`
- `resources/js/lib/category-appearance.ts:145, 310-318` domyślny kolor i `categoryTileStyle`
- `resources/js/components/CategoryChip.vue:46-57`, `CategoryColorPicker.vue:29-48`, `CategoryIconPicker.vue:27-41`
- `app/Support/Categories/CategoryColors.php:12-37` paleta 24 barw
- `resources/js/components/OperationsFilters.vue:35-41, 83-88, 438-478, 488-498, 509-525`
- `app/Support/OperationsFilterOptions.php:32-81` (sort `:78`), `:98-112` lokaty
- `resources/js/components/CategorySelect.vue:40-107`, `resources/js/lib/category-tree.ts:162-192`
- `resources/js/components/AccountSelect.vue:39-57, 82-120`, `app/Support/AccountIdentity.php:33-42`, `app/Enums/Bank.php`
- `resources/js/components/MonthPlanItemRow.vue:112-130, 207-290`
- `app/Support/MonthItemDeviation.php:23-40, 56-78`, `resources/js/lib/month-item-deviation.ts:18-70`, `resources/js/lib/month-plan-status.ts:33-66`

## Architecture Insights

- Ciemny motyw żyje w warstwie tokenów (`.dark` → `@theme inline`), poprawnie wg kontraktu Tailwind v4; brakuje mu tylko `color-scheme`, które przełącza kontrolki UA. Poprawka C1 jest jedną zmianą w źródle tokenów.
- Kolor kategorii to dane użytkownika, nie token; jego ekspozycja (alfa, próbka) to jedyne miejsce, gdzie motyw nie ma wpływu.
- Pickery z ikoną mają współdzielone komponenty (`CategorySelect`, `AccountSelect`); dwa wyjątki (filtr kategorii, selecty lokat) są zapisanymi decyzjami (ADR-0069, ADR-0054), nie przeoczeniem — ich zmiana zaczyna się od ADR.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-items-plan-vs-actual/plan-brief.md:23` — reguła koloru odchylenia (wydatek ponad plan `destructive`, dochód > 0 `success`, reszta bez koloru) — **nadal obowiązuje w kodzie** (`MonthPlanItemRow.vue:116-130`).
- `context/archive/2026-11-02-month-items-plan-vs-actual/research.md:142` — „poniżej planu” pozostawione otwarte, bo design system go nie określał — **nadal prawda** (`design-system.md:55`).
- ADR-0054:13 — selecty lokat natywne, tylko nazwa — **obowiązuje**; ADR twierdzi, że kontrast znaków sprawdzono w obu trybach w `/dev/ui/accounts` — **podważone** dla Revolut w ciemnym motywie (zrzut `research-bank-marks-dark.png`).
- ADR-0069:13 — filtr Operacji i paleta poleceń płaskie — **obowiązuje**.
- ADR-0060:9, ADR-0074:13 — kolor wg korzyści dla typu kategorii; precedens dla C5b.

## Related Research

- `context/archive/2026-11-02-month-items-plan-vs-actual/research.md`
- `context/archive/2026-11-03-month-plan-ui-contract/` (zrzuty Planu miesiąca 390/1280)

## Decisions

Podjęte przez użytkownika 2026-11-13 po porównaniu wariantów (szkice na danych demo). C1 (`color-scheme`) i C5a (układ kwot poniżej `xl`) nie wymagały decyzji: wchodzą do planu jako poprawki.

- **D1 (C5b) — kolor wg korzyści, etykieta „Odchylenie” bez zmian.** Liczba ze znakiem zostaje. Wiersz z przekroczeniem (`overPlan`, także roczna przekroczona rokiem przy ujemnym odchyleniu miesiąca) → `destructive`; wydatek poniżej planu bez przekroczenia → `success`; dochód jak dziś (> 0 `success`). Kolor wynika ze statusu, nie z samego znaku. Wymaga wpisu w `design-system.md:55` (nowe znaczenie `success`) i nowego ADR (precedens ADR-0060, ADR-0074). Glosariusz bez zmian. Odrzucone: „zostało / przekroczono” (glosariusz + ADR, kolizja z „zostało X na Y mies.” w tym samym wierszu, słowo przeczące odznace dla rocznej i nieregularnej) oraz pozostawienie bez koloru.
- **D2 (C3) — filtr kategorii jak w oknie operacji.** Reka `Select` z `CategoryChip`, opcja „Wszystkie kategorie”, sekcje Dochody/Wydatki, w nich kolejność listy Kategorii i nagłówki grup, archiwalne z sufiksem. Nowy ADR zmienia ADR-0069:13 tylko dla filtra Operacji (paleta poleceń zostaje płaska). Serwer (`OperationsFilterOptions::categories`) dosyła `group` i kolejność listy zamiast `usort` po nazwie; `tests/Feature/OperationsFilterTest.php` z indeksami do przejrzenia. Odrzucone: ikony bez grup, samo sortowanie.
- **D3 (C4) — jasna płytka pod logo banku w ciemnym motywie i ikonki lokat.** Powierzchnia znaku w `AccountAvatar` jako token (jasna w `.dark`), tak aby Revolut i Lokaty Facto były widoczne; trzy natywne selecty lokat (`OperationsFilters.vue:509-525`, `CapitalMovementFormDialog.vue:358-375`, `ImportPreviewRow.vue:558-575`) zamienione na Reka `Select` z `AccountAvatar`. Nowy ADR zmienia ADR-0054:13; payloady lokat (`OperationsController.php:150-158`, `OperationsFilterOptions.php:98-112`, `CsvImportController.php:490-493`) dostają `bank` i `mark_url`. Odrzucone: sama płytka, bez zmian.
- **D4 (C2) — kolory kategorii w obu motywach.** Próbka w `CategoryColorPicker` pokazuje pełny kolor; tło i ikona kafelka z pary tokenów w `:root`/`.dark` zamiast literału `26`, tak aby ikona miała ≥ 3:1 dla wszystkich 24 kolorów palety w obu motywach (test czystej funkcji). Zmiana zdania „tło 15%” w `design-system.md:66`; kolor kategorii nadal tylko tożsamość. Odrzucone: tylko ciemny motyw, tylko próbki.

## Open Questions

Rozstrzygnięte w `## Decisions` (D1–D4).

Luki: wygląd okna listy natywnego selecta w ciemnym motywie jest wnioskiem (headless nie fotografuje okien systemowych); sortowanie `<=>` dla Ł/Ś nie zostało uruchomione na danych; Revolut/Lokaty Facto nie występują w danych demo, więc dowód pochodzi z kitchen sinka `/dev/ui/accounts`.
