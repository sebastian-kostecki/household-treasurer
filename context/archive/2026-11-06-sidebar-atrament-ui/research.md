---
date: 2026-11-06T21:21:28+01:00
researcher: Claude (Opus 5.5)
git_commit: 4dbeb88
branch: worktree-sidebar-atrament-ui
repository: household-treasurer
topic: "Audyt sidebara (AppSidebar.vue i sloty) względem komponentu Sidebar systemu Atrament"
tags: [research, ui, atrament, sidebar, label-caps, aria-current, avatar, brand-mark, kitchen-sink, adr-0053]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude (Opus 5.5)
---

# Research: Audyt sidebara względem komponentu Sidebar systemu Atrament

**Date**: 2026-11-06T21:21:28+01:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 4dbeb88 (gałąź z `development` na e889272 + `change.md` tej zmiany)
**Branch**: worktree-sidebar-atrament-ui
**Repository**: household-treasurer

## Research Question

Potwierdzić zarzuty C1–C5 i drobny zarzut ze slotem z `change.md` dla `resources/js/components/AppSidebar.vue` i jego slotów (`AppLogo`, `AppLogoIcon`, `BudgetYearSwitcher`, `NavMain`, `NavUser`, `UserInfo`) względem komponentu `Sidebar` design systemu Atrament (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA: `project/components/Sidebar/README.md`, `.ht-side*` w `project/components/bundle.css`, `project/tokens.json`, `project/README.md`), ustalić, jak każdą poprawkę wprowadzić bez edycji `components/ui/*` pod jeden ekran, które testy i dokumenty dotyka, oraz co z macierzą 7 stanów w `/dev/ui/sidebar`. Decyzje z `change.md` (paleta ⌘K i skróty poza zmianą, rok bez segmentów) są ustalone i nie były ponownie badane.

## Summary

Wszystkie pięć zarzutów i slot są potwierdzone w kodzie z `4dbeb88`; trzy kotwice z `change.md` były błędne (pomyłka numeracji przy `cat -n` kilku plików naraz) i są tu poprawione: `NavMain.vue:25` (etykieta), `NavMain.vue:28-33` (pozycja menu), `NavUser.vue:29-34` (przycisk użytkownika). Doszedł jeden zarzut (C6: promień i grubość pozycji menu).

- **Szerokość (C1)** idzie z `ui/sidebar/utils.ts:6` (`16rem`), wpisywanej inline jako `--sidebar-width` w `SidebarProvider.vue:72-75`. Token `:root { --sidebar-width }` zostałby przesłonięty przez ten styl inline, a `SIDEBAR_WIDTH = "var(--sidebar-width)"` odwołuje się sam do siebie. Działa token pod inną nazwą (np. `--sidebar-panel-width: 224px`) podany albo przez `style` na `SidebarProvider` u konsumentów (`AppShell.vue`, dwa providery w kitchen sinku), albo jednym wierszem w `utils.ts`. Wybór należy do planu (Open Questions 1).
- **Etykiety (C2)**: `label-caps` (`app.css:262-265`) jest w kodzie użyty raz (`StatPanel.vue:41`), w sidebarze ani razu, choć ADR-0037 i `js.md:48` przeznaczają go dla sidebara. „Rok budżetu” siedzi w przycisku (`BudgetYearSwitcher.vue:124-126`).
- **`aria-current` (C3)**: nie ma go w sidebarze; `@inertiajs/vue3` 3.11.0 nie dodaje go sam (0 trafień `aria-current` w `node_modules/@inertiajs/vue3/dist/` głównego checkoutu). `SidebarMenuButton` przekazuje `$attrs` do `<a>`, więc wystarczy atrybut w `NavMain.vue`.
- **Użytkownik (C4)**: `UserInfo` ma dwóch konsumentów (sidebar `NavUser.vue:34` i białe menu `UserMenuContent.vue:32` z `show-email`), więc wygląd z sidebara wymaga wariantu, nie zmiany domyślnej.
- **Znak (C5)**: README design systemu: „Logo marki nie ma: znak to napis „HT” w JetBrains Mono na `brand`”. To zamyka odroczony C5a z `sidebar-ui-contract`. `AppLogoIcon` (`Vault`) ma jednak pięciu konsumentów poza sidebarem (auth, Welcome, martwy `AppHeader`), więc zakres podmiany to decyzja (Open Questions 2).
- **Testy**: na tej sprawdzonej liście żaden test nie zależy od markupu, klas, szerokości, awatara, logo ani „Rok budżetu”; jedyny selektor sidebara to link „Operacje” w `tests/e2e/auth.setup.ts:32` (nazwa się nie zmienia). Ograniczenia narzuca `check:ui`: `text-[11px]`, `size-[28px]`, `size-[26px]` są odrzucane, `size-7` i nazwane klasy/utility z `app.css` nie.
- **Macierz 7 stanów**: kitchen sink pokazuje default, empty, error i active; hover, focus-visible i disabled to dziś opis „zrzuty z działającej aplikacji” (`SidebarKitchenSink.vue:46-57`). Disabled da się pokazać statycznie (`aria-disabled` jest już stylowane w cva); hover i focus-visible inne sinki pokazują sekcją z instrukcją „Tab…” i notatką.
- **ADR-0053** nie musi formalnie zastępować żadnego ADR; opcjonalnie część zdania ADR-0035 o JetBrains Mono „dla kwot” (precedens rozszerzenia bez zastąpienia: ADR-0047, `mark-label`).

## Charges

Kategorie: **T** brakujący token, **K** brakujący współdzielony komponent / wzorzec, **A** przypadkowa architektura.

### C1 (T) Szerokość 16rem zamiast tokenu `sidebar-width` 224 px: potwierdzony

- `resources/js/components/ui/sidebar/utils.ts:6-8`: `SIDEBAR_WIDTH = "16rem"`, `SIDEBAR_WIDTH_MOBILE = "18rem"`, `SIDEBAR_WIDTH_ICON = "3rem"`. `SidebarProvider.vue:72-77` wpisuje `--sidebar-width` i `--sidebar-width-icon` jako `:style` na wrapperze, potem `v-bind="$attrs"` (`style` nie jest propem, trafia przez `$attrs`).
- Wzorzec: `tokens.json` → `layout.sidebar-width` = 224px („Szerokość sidebara”); `.ht-side { width: var(--sidebar-width) }` (`bundle.css:116`). `app.css` nie ma `--sidebar-width`; tokeny układu są w `app.css:157-165` (`--dialog-width`, `--aside-width`, `--settings-nav-width`). Zmiana `design-tokens-atrament` celowo pominęła tokeny `layout` (`context/archive/2026-11-05-design-tokens-atrament/research.md:46`, `plan.md:45`).
- Pułapka nazwy: `:root { --sidebar-width }` przegrywa ze stylem inline wrappera, a `var(--sidebar-width)` w `utils.ts` jest samoodwołaniem. Precedens rozwiązania: ADR-0040 nazwał token `--dialog-shadow`, nie `--shadow-*`, z tego samego powodu.
- Dwie drogi bez edycji `ui/` pod jeden ekran:
  1. `AppShell.vue:21` i oba `SidebarProvider` w `pages/dev/SidebarKitchenSink.vue` (`:163-171`, `:182`) dostają `:style="{ '--sidebar-width': 'var(--sidebar-panel-width)' }"`. Scalanie `:style` z `$attrs` w Vue daje pierwszeństwo późniejszemu kluczowi; nie zweryfikowano w przeglądarce. Nie nadpisywać na `<Sidebar>`: `Sidebar.vue:11` ma `inheritAttrs: false` i wiąże `$attrs` tylko z panelem (`:86`), a div-odstęp (`:64-73`) czyta zmienną wrappera, więc oba by się rozjechały.
  2. `utils.ts:6` → `SIDEBAR_WIDTH = "var(--sidebar-panel-width)"`. Sidebar jest całą powłoką, nie jednym ekranem; precedens edycji `ui/sidebar/*`: `design-tokens-atrament` zmienił `ui/sidebar/index.ts:39` (`plan.md:302,311`). Obejmuje AppShell i kitchen sink jednym wierszem.
- Mobile: `Sidebar.vue:34-43` wpisuje inline `--sidebar-width: SIDEBAR_WIDTH_MOBILE` (18rem) na `SheetContent`, bez propa. Wzorzec nie mówi nic o szerokości mobilnej; zostaje 18rem.
- Wpływ: przy 1024 px treść traci 32 px względem wzorca. Skutki zmiany na 224 px są korzystne lub neutralne: próg `.category-meter-table` 38rem (komentarz `app.css:1182`, „ok. 644 px” przy 1280 px liczone z sidebarem 256 px) zyskuje 32 px; liczby w ADR-0051 akapit 3 („16rem sidebar”), `js.md:38` i `term-deposits` `impl-review.md:32` zestarzeją się, decyzje nie. `--aside-width` i `.dashboard-grid` (`app.css:163`, `:1274`) nie odwołują się do szerokości sidebara.

### C2 (K) Etykiety sekcji bez `label-caps`, „Rok budżetu” w przycisku: potwierdzony

- `NavMain.vue:25`: `<SidebarGroupLabel v-if="label">` → `ui/sidebar/SidebarGroupLabel.vue:19-20`: `text-sidebar-foreground/80 … h-8 … text-xs font-medium` i w trybie ikon `group-data-[collapsible=icon]:-mt-8 opacity-0`. Klasy da się zmienić propem `class` (`cn`).
- `BudgetYearSwitcher.vue:96` (stan pusty, „Brak roku budżetu”) i `:124-126` (podpis „Rok budżetu” nad rokiem w przycisku): `text-sidebar-foreground/80 truncate text-xs`.
- Wzorzec: `.ht-side__label` = 11/14, 500, wersaliki, +0.06em, `sidebar-foreground`, padding `space-2` (`bundle.css:122`), czyli dokładnie `label-caps` (`app.css:262-265`) w kolorze `text-sidebar-foreground`. „Rok budżetu” stoi nad przełącznikiem, „Menu” nad listą (`Sidebar/preview.html`). `tokens.json` → `label-caps`: „Tylko etykieta nad miarą (KPI) i etykieta sekcji sidebara”.
- Zgodne z ADR-0037 akapit 1 i `js.md:48` (wersaliki tylko nad miarami i w sidebarze). `label-caps` ma dziś jedno użycie w kodzie: `StatPanel.vue:41`.
- Etykieta nad przełącznikiem w `SidebarHeader` musi sama znikać w trybie ikon (`js-sidebar.md`, „Header and footer slots”): `SidebarGroupLabel` robi to już przez `-mt-8 opacity-0`; zwykły element potrzebuje `group-data-[collapsible=icon]:hidden`. W mobilnym Sheet nie ma przodka `data-collapsible`, etykiety są widoczne. Tooltip `${budget_year}: ${year}` (`BudgetYearSwitcher.vue:111`) zostaje dla trybu ikon.
- Kontrast `sidebar-foreground` na `sidebar-background`: 7.50:1 jasny, 6.86:1 ciemny (z wartości `app.css:171-172`, `:224-225`). Modyfikator `/80` wybrano w `sidebar-ui-contract` (`plan.md:439`) dla 4.5:1 na `sidebar-accent`; pełny `sidebar-foreground` z wzorca ma zapas.
- Klucze: `t.nav.menu` (`lang/pl/ui.php:11`), `t.shell.budget_year` (`ui.php:14`); nowych kluczy nie trzeba.
- Wpływ: sekcje sidebara nie mają hierarchii wzorca, a dwie etykiety są złożone inaczej niż jedyny przepis etykiety sekcji.

### C3 (A) Aktywna pozycja bez `aria-current="page"`: potwierdzony

- `NavMain.vue:28-33`: `SidebarMenuButton as-child :is-active="isCurrentSection(toUrl(item.href), currentUrl)"` z `<Link>`. `SidebarMenuButtonChild.vue:28` ustawia tylko `data-active`; cva stylizuje `data-[active=true]` (`ui/sidebar/index.ts:39`).
- `@inertiajs/vue3` 3.11.0: 0 trafień `aria-current` w `dist/*.js` (sprawdzone w `node_modules` głównego checkoutu; worktree nie ma `node_modules`). Link go nie dodaje.
- `SidebarMenuButton.vue:9` ma `inheritAttrs: false` i przekazuje `$attrs` do Child (`:27`, `:33`), a Primitive z `as-child` scala je z `<a>` Linka, więc `:aria-current="active ? 'page' : undefined"` w `NavMain.vue` dociera do linku bez edycji `ui/`.
- Precedens: `layouts/settings/Layout.vue:47-49` (ADR-0049) ustawia `:aria-current` ręcznie na `Link`; też `PaginationLinks.vue:70`, `BudgetMonthPicker.vue:78`.
- `isCurrentSection` (`lib/nav-active.ts:15-28`): `/` tylko dokładnie, inne ścieżki także jako prefiks `path + '/'`; testy w `lib/nav-active.test.ts`. Ten sam warunek ma sterować `is-active` i `aria-current` (jedno wyliczenie na pozycję).
- Wpływ: czytnik ekranu nie ogłasza bieżącej sekcji; wzorzec wymaga `aria-current="page"` (`Sidebar/README.md`, „Anatomia” pkt 4; `bundle.css:129`).

### C4 (K) Blok użytkownika: kwadratowy awatar na `muted`, bez e-maila: potwierdzony

- `UserInfo.vue:25-27`: `Avatar class="h-8 w-8 overflow-hidden rounded-lg"`, `AvatarFallback class="text-foreground rounded-lg"`; domyślne tło fallbacku `bg-muted` (`ui/avatar/AvatarFallback.vue:17`), domyślny `Avatar` jest `size-8 rounded-full` (`ui/avatar/Avatar.vue:14`). E-mail za `showEmail` (`UserInfo.vue:9-14`, `:34`) w `text-muted-foreground` (`:30`).
- Konsumenci `UserInfo`: `NavUser.vue:34` (sidebar, bez `showEmail`) i `UserMenuContent.vue:32` (`:show-email="true"`, w białym `DropdownMenuLabel`). Zmiana domyślnego wyglądu przemalowałaby menu; potrzebny wariant (np. prop `variant="sidebar"`) albo osobny markup w `NavUser`.
- Wzorzec: `.ht-side__user` z linią `sidebar-border` u góry (już jest: `SidebarSeparator`, `AppSidebar.vue:97`), `.ht-side__avatar` 28 px, `radius-full`, tło `sidebar-accent`, inicjały 11 px 600; e-mail `small` 11 px `sidebar-foreground` (`bundle.css:132-134`).
- Kontrast: inicjały `sidebar-accent-foreground` na `sidebar-accent` ok. 12.8:1 / 14.7:1; e-mail `sidebar-foreground` na `sidebar-background` 7.50 / 6.86:1. Krąg `sidebar-accent` na `sidebar-background` ma ok. 1.15:1 (dekoracyjny, niesie go tekst inicjałów). Na hover i przy otwartym menu tło przycisku też jest `sidebar-accent` (`NavUser.vue:31`), więc krąg zlewa się z tłem; inicjały zostają czytelne.
- Tryb ikon: `size="lg"` zwija się do 32×32 bez paddingu (`ui/sidebar/index.ts:39,50`); awatar 28 px (`size-7`) mieści się. `size-[28px]` odrzuca `check:ui`.
- `design-system.md` („Sidebar”, wiersz `sidebar-accent`) nie wymienia awatara; nowa rola do dopisania.
- Wpływ: w jasnym motywie jasny kafelek `muted` na granacie odciąga wzrok od menu, a użytkownik nie widzi, na jakim koncie jest zalogowany.

### C5 (K) Znak `Vault` zamiast „HT”: potwierdzony; zamyka C5a z `sidebar-ui-contract`

- `AppLogo.vue:10-14`: kafelek `bg-sidebar-primary text-sidebar-primary-foreground size-8 rounded-md` z `AppLogoIcon class="size-5"` (`Vault` z `@lucide/vue`, `AppLogoIcon.vue:2,17`), obok nazwa w `text-sidebar-strong` (`:16-19`).
- Wzorzec: `project/README.md`, „Ikony”: „Logo marki nie ma: znak to napis „HT” w JetBrains Mono na `brand`”. `.ht-side__mark` = 26 px, `radius-sm`, `sidebar-primary`/`sidebar-primary-foreground`, mono 11 px 600 (`bundle.css:119`); nazwa „Household Treasurer” obok w `sidebar-strong` (`.ht-side__brand`, `bundle.css:118`).
- Historia: `Vault` był tymczasowy, a docelowy znak odroczony jako C5a (`context/archive/2026-11-03-sidebar-ui-contract/research.md:81-83`, `plan.md:42`); żaden ADR nie wybrał `Vault`.
- Konsumenci `AppLogoIcon` poza sidebarem: `AppHeader.vue:99` (martwy: `AppHeaderLayout` nikt nie importuje), `layouts/auth/AuthSimpleLayout.vue:26`, `AuthSplitLayout.vue:27`, `AuthCardLayout.vue:29` (z literałem `text-black dark:text-white`), `pages/Welcome.vue:109,324`. `AppLogo` ma dwóch: `AppSidebar.vue:81` i martwy `AppHeader.vue:150`.
- Krój: JetBrains Mono 400/500/600 ładowany w `vite.config.ts:22-26`, `font-mono` = `--font-mono` (`app.css:14`). ADR-0035 akapit 2 i `design-system.md:26` mówią o mono „dla kwot”; ADR-0047 rozszerzył to na `mark-label` (skróty banków) bez zastępowania.
- Rozmiary: 26 px i 11 px nie przejdą `check:ui` w nawiasach; potrzebna nazwana utility w `app.css` (wzór `mark-label`, `app.css:271-273`; reguła `css.md`). Tryb ikon: kafelek 26 px mieści się w przycisku 32×32.
- Kontrast: `brand-foreground` (`#062a26`, `app.css:125`) na `brand` (`#5fd4c4`, `:124`) 8.56:1; kafelek `brand` na `sidebar-background` 9.63 / 10.92:1.
- Wpływ: nagłówek pokazuje tymczasowy glif zamiast znaku produktu z design systemu.

### C6 (T) Promień i grubość pozycji menu inne niż we wzorcu: nowy, potwierdzony

- `ui/sidebar/index.ts:39`: baza cva `rounded-xl` (12 px przy `--radius` 10px, `app.css:16-19,156`) i `data-[active=true]:font-medium` (500).
- Wzorzec: pozycja menu `radius-md` (8 px), aktywna `font-weight: 600` (`bundle.css:127,129`); `tokens.json` → `radius-md`: „Przyciski, pola, pozycje menu”.
- Konsument może nadpisać przez `class` na `SidebarMenuButton` w `NavMain.vue` (i w przyciskach `size="lg"` slotów) bez edycji `ui/`; alternatywnie edycja cva jak w `design-tokens-atrament`.
- Wpływ: pozycje menu mają inny promień niż każdy przycisk i pole aplikacji (`radius-md`), a aktywna pozycja słabiej odcina się od pozostałych.

### C7 (A) `<slot />` poza `<Sidebar>`: potwierdzony (drobny)

- `AppSidebar.vue:101`: `<slot />` po `</Sidebar>` czyni komponent wielokorzeniowym (wbrew regule „Vue components must have a single root element” z wytycznych Inertia). Obaj konsumenci są samozamykający (`layouts/app/AppSidebarLayout.vue:23-26`, `pages/dev/SidebarKitchenSink.vue:167`); `AppSidebar` nie używa `$attrs`. Usunięcie bezpieczne.
- Wpływ: brak widocznego; atrybuty przekazane do `AppSidebar` nie spadłyby na żaden element.

### Odroczone (poza tą zmianą)

- **Paleta ⌘K, skróty `1`–`6`/`N`/`/`, podpowiedź skrótów**: decyzja z `change.md` → osobna zmiana `command-palette`. Brak wymagania w PRD (przeszukane: `prd-mvp.md`, `prd.md`, `prd-v2.md`, `mvp.md`, `roadmap.md`, `shape-notes.md`, `glossary.md`); powiązane NFR-ACC-01 (WCAG 2.2 AA, `prd-mvp.md:375`) i NFR-ACC-02 (klawiatura, `:376`). Istniejący skrót Ctrl/⌘+B przełącza sidebar (`ui/sidebar/utils.ts:9`, `SidebarProvider.vue:46-50`). `period-report-by-category` dodaje „Raporty” do `mainNavItems` (`context/changes/period-report-by-category/plan.md:175-182`), więc pozycji będzie 7. Wcześniejsze zmiany też wykluczały paletę (`design-tokens-atrament/plan.md:41`, `budget-atrament-ui/plan.md:45`, `dashboard-atrament-ui/plan.md:51`).
- **Rok w segmentach**: odrzucony w `change.md`; README wzorca („W kodzie”) zostawia menu `BudgetYearSwitcher`.
- **„HT” na ekranach auth i w Welcome**: zależnie od decyzji (Open Questions 2).
- **Szerokość mobilna** 18rem: wzorzec jej nie określa; bez zmian.

## Detailed Findings

### Testy i strażnicy

- Playwright (`playwright.config.ts`, `tests/e2e/`: `auth.setup.ts`, `seed.spec.ts`, `account-edit-keeps-bank.spec.ts`, `import-other-format-without-header.spec.ts`): jedyna zależność od sidebara to `getByRole('link', { name: 'Operacje' })` (`auth.setup.ts:32`). `aria-current` nie zmienia nazwy dostępnej. Zrzuty tylko `only-on-failure` (`playwright.config.ts:25-26`); brak baseline'ów `toHaveScreenshot`.
- Pest: `tests/Feature/SidebarKitchenSinkTest.php:14-38` (gość → login, produkcja → 404, local → komponent `dev/SidebarKitchenSink`, bez propsów); `tests/Feature/AppShellTest.php:46-47` (propsy `shell.*`); `tests/Unit/DesignTokensTest.php:17-30,37` (każdy `--color-X: var(--Y)` ma wartość w `:root` i `.dark`; lista tokenów z `sidebar-strong`; dotyczy kolorów, nie `--sidebar-panel-width`); `tests/Unit/UiTranslationKeysTest.php:11-45` (każdy `t.group.key` istnieje w `lang/pl/ui.php`).
- Vitest: `resources/js/ui-contract.test.ts:11,26` (regex literałów na `BudgetYearSwitcher.vue`; nie łapie `/80`), `lib/nav-active.test.ts`, `lib/budget-year-selection.test.ts`.
- `data-test="sidebar-menu-button"` (`NavUser.vue:32`) nie jest używany w żadnym teście w `tests/` ani `resources/js`.
- `scripts/check-ui-literals.mjs`: wzorce `:125-133` (hex, `rgb/hsl/oklch(`, `-[Npx|rem]`, `/[0.N]`, klasy palety, `-white`/`-black`, każde `dark:`); lista plików sidebara `:21-31` (AppSidebar, BudgetYearSwitcher, NavMain, NavUser, UserInfo, UserMenuContent, AppLogo, AppLogoIcon, AppSidebarHeader, `AppSidebarLayout`, `SidebarKitchenSink`), `CreateBudgetDialog` `:19`; `components/ui/sidebar/*` poza listą; argumenty CLI zastępują listę (`:136-137`). Uruchamiany w `composer ci:check` (`composer.json:67-74`) w CI (`.github/workflows/tests.yml:102`). Warstwy auth (`AuthCardLayout.vue`) nie ma na liście.

### Macierz 7 stanów (stan obecny → potrzeba)

| Stan | Dziś w `/dev/ui/sidebar` | Potrzeba |
| --- | --- | --- |
| default | ramki `expanded-years`, `collapsed-years` (`SidebarKitchenSink.vue:64-79`) | bez zmian; ramki dostają szerokość z C1 |
| hover | tekst „zrzuty z aplikacji” (`:46-49`) | sekcja z notatką (wzór `ImportPreviewSink.vue:147-154`, `BudgetKitchenSink.vue:221-223`) albo przycisk demo z klasami tokenów stanu (`bg-sidebar-accent text-sidebar-accent-foreground`); żaden sink nie wymusza dziś hover klasami |
| focus-visible | tekst „zrzuty z aplikacji” (`:50-53`) | sekcja „Tab…” z prawdziwymi kontrolkami (wzór `MonthPlanSink.vue:546-563`, `ImportPreviewSink.vue:156-163`); token `sidebar-ring` |
| disabled | tekst „przy spowolnionej sieci” (`:54-57`) | statycznie: `SidebarMenuButton aria-disabled="true" aria-busy="true"` (cva `aria-disabled:opacity-50`, `ui/sidebar/index.ts:39`); `pending` w `BudgetYearSwitcher` jest tylko `defineExpose` (`:41`) |
| error | przycisk „Pokaż błąd wyboru roku” (`:60-62`, `:138-145`) | bez zmian |
| empty | ramki `expanded-empty`, `collapsed-empty` (`:80-92`) | etykieta „Rok budżetu” nad pustym stanem — do sprawdzenia |
| loading | N/A (brak deferred props; `pending` pokazany jako disabled) | bez zmian |

- Ramki: `div.relative.h-160.transform-gpu.overflow-hidden.border` z własnym `SidebarProvider` (`:161-171`); sekcja `menu-states` z `SidebarProvider class="bg-sidebar min-h-0 w-64 p-2"` i dwoma `SidebarMenuButton` (`:176-198`). `w-64` to szerokość 16rem zapisana na sztywno: przy C1 do zamiany.
- Strona renderuje się w prawdziwym `AppLayout`, więc żywy sidebar też jest na stronie.

## Code References

- `resources/js/components/ui/sidebar/utils.ts:6-9` — szerokości sidebara i skrót Ctrl/⌘+B
- `resources/js/components/ui/sidebar/SidebarProvider.vue:72-77` — `--sidebar-width` inline + `$attrs`
- `resources/js/components/ui/sidebar/Sidebar.vue:11,34-43,64-73,86` — `inheritAttrs: false`, szerokość mobilna, div-odstęp, panel
- `resources/js/components/ui/sidebar/index.ts:39,50` — cva pozycji menu (`rounded-xl`, `font-medium`, `aria-disabled`, tryb ikon), rozmiar `lg`
- `resources/js/components/ui/sidebar/SidebarGroupLabel.vue:19-20` — klasy etykiety grupy
- `resources/js/components/ui/sidebar/SidebarMenuButtonChild.vue:28` — tylko `data-active`
- `resources/js/components/AppShell.vue:21` — `SidebarProvider` aplikacji
- `resources/js/components/AppSidebar.vue:81,86-89,93,97-98,101` — logo, przełącznik roku, menu, separator, użytkownik, slot
- `resources/js/components/AppLogo.vue:10-19` — kafelek znaku i nazwa
- `resources/js/components/AppLogoIcon.vue:2,17` — `Vault`
- `resources/js/components/BudgetYearSwitcher.vue:96,111,112-113,124-126` — etykiety, tooltip, `aria-disabled`/`aria-busy`
- `resources/js/components/NavMain.vue:25,28-33` — etykieta grupy, pozycja z `Link`
- `resources/js/components/NavUser.vue:29-34` — przycisk użytkownika
- `resources/js/components/UserInfo.vue:9-14,25-34` — `showEmail`, awatar, e-mail
- `resources/js/components/UserMenuContent.vue:32` — drugi konsument `UserInfo`
- `resources/js/layouts/settings/Layout.vue:47-49` — precedens `aria-current`
- `resources/js/pages/dev/SidebarKitchenSink.vue:25-58,64-93,161-171,176-198` — macierz, ramki, `w-64`
- `resources/css/app.css:14,16-19,124-125,156,157-165,171-179,224-232,262-265,271-273,1182` — mono, promienie, `brand`, tokeny układu, `--sidebar-*`, `label-caps`, `mark-label`, próg Dashboardu
- `tests/e2e/auth.setup.ts:32` — jedyny selektor sidebara w testach
- `scripts/check-ui-literals.mjs:21-31,125-133` — lista plików i wzorce

## Architecture Insights

- Prymitywy `ui/sidebar/*` mają pełną obsługę trybu ikon i stanów; dryf leży w komponentach domenowych slotów i w braku tokenu szerokości. Poprawki C2–C5 i C7 mieszczą się w komponentach domenowych; C1 i C6 mają wariant konsumencki i wariant „cała powłoka” (edycja `ui/` z precedensem w `design-tokens-atrament`).
- Rozmiary spoza skali Tailwinda (26 px znak, 11 px tekst znaku) idą przez nazwane utility w `app.css` (`mark-label`, `label-caps` jako wzór), bo `check:ui` odrzuca wartości w nawiasach.
- `UserInfo` i `AppLogoIcon` są współdzielone z powierzchniami poza sidebarem (białe menu, auth, Welcome); zmiana wyglądu w sidebarze wymaga wariantu lub nowego komponentu, nie zmiany domyślnej.

## Historical Context (from prior changes)

- `context/archive/2026-11-03-sidebar-ui-contract/` — poprzedni kontrakt sidebara: tryb ikon w slotach, `isCurrentSection`, `check:ui` dla plików sidebara, kitchen sink `/dev/ui/sidebar`. C5a (docelowy znak): wciąż otwarty w kodzie (`AppLogoIcon.vue` = `Vault`), zamyka go ta zmiana. C6 (kontrast tekstu na ciemnym `--primary`): rozwiązany przez `design-tokens-atrament`/ADR-0035 (ciemny `--primary` `#e6ecf3` z `--primary-foreground` `#111a27`, `app.css:189-190`; `design-tokens-atrament/research.md:28,73`, `plan.md:12,392`); wpis C6 w archiwum nie został oznaczony jako zamknięty.
- `context/archive/2026-11-05-design-tokens-atrament/` — tokeny `--sidebar-*` (ciemny sidebar w obu motywach), edycja cva `ui/sidebar/index.ts:39`, pominięcie tokenów `layout` (w tym `sidebar-width`), wykluczenie palety ⌘K (`plan.md:41`).
- ADR-0037 akapit 1 — `label-caps` tylko nad miarami i w sidebarze: potwierdza C2.
- ADR-0049 („Layout”) — `aria-current="page"` w podnawigacji ustawień: precedens dla C3.
- ADR-0035 akapit 2 i ADR-0047 — JetBrains Mono dla kwot, rozszerzone na `mark-label`: kontekst dla C5.
- ADR-0040 akapit 2 — token o innej nazwie, gdy prymityw zajmuje nazwę zmiennej: precedens dla C1.
- ADR-0051 akapit 3 — próg `xl` Lokat uzasadniony sidebarem 16rem: liczba się zestarzeje, decyzja zostaje.

## Related Research

- `context/archive/2026-11-03-sidebar-ui-contract/research.md`
- `context/archive/2026-11-05-design-tokens-atrament/research.md`
- `context/archive/2026-11-06-term-deposits-atrament-ui/research.md` (format zarzutów, próg `xl`)

## ADR-0053: konwencje

- Brak szablonu; treść po angielsku, polskie słowa UI w cudzysłowie, bez frontmattera (wzór ADR-0049–0052; `budget-atrament-ui/plan.md:72`). Kształt: tytuł-zdanie („… follow the Sidebar component”), akapit otwierający z linkiem do artefaktu i datą stanu karty, akapity tematyczne z etykietą („Width.”, „Section labels.”, …), „We rejected …” (paleta ⌘K, segmenty roku), zdanie o glosariuszu i propsach Inertii, ewentualne „Supersedes in part ADR-NNNN: …” z odwrotnym wpisem w zastępowanym ADR.
- `design-system.md:3`: dopisać „i ADR-0053 (…)” po ADR-0052; przy okazji brakuje tam ADR-0048. Inne miejsca do zmiany w `design-system.md`: tabela „Sidebar” (`sidebar-accent` + awatar, `sidebar-primary` „kafelek logo” → znak „HT”), `:26` (mono także dla znaku), `:140` (stany kitchen sinka). Reguła `js-sidebar.md` („The active item…”: `aria-current`, `label-caps`).
- Wolny numer: najwyższy ADR w worktree i na `development` to 0052.

## Open Questions

1. **Droga dla szerokości (C1)**: `style` na `SidebarProvider` u trzech konsumentów (`AppShell.vue`, dwa w kitchen sinku) czy jeden wiersz w `ui/sidebar/utils.ts` z tokenem `--sidebar-panel-width`? Rekomendacja: `utils.ts` — sidebar to cała powłoka, precedens edycji `ui/sidebar/index.ts:39`, jedno miejsce zamiast trzech; nazwa tokenu inna niż `--sidebar-width` w obu wariantach. Scalanie stylu (wariant 1) nie jest zweryfikowane w przeglądarce.
2. **Zakres znaku „HT” (C5)**: tylko sidebar (`AppLogo`), czy także auth (`AuthSimpleLayout`, `AuthSplitLayout`, `AuthCardLayout`) i `Welcome.vue`? Rekomendacja: wspólny komponent znaku użyty w `AppLogo`; auth i Welcome jako deferred z powodem „jeden widok na zmianę” (auth ma własne literały, `AuthCardLayout.vue:29`, i nie jest na liście `check:ui`). Decyzja produktowa do ADR-0053.
3. **Wariant `UserInfo` (C4)**: prop wariantu w `UserInfo` czy osobny markup w `NavUser`? Do planu; oba bez zmiany wyglądu menu użytkownika.
4. **C6**: nadpisanie klas w konsumentach czy w cva? Do planu, razem z decyzją 1.
5. **Konflikt z `period-report-by-category`**: obie zmiany edytują `AppSidebar.vue` (`mainNavItems`); kolejność merge do ustalenia, gdy któraś wejdzie do `development`.
