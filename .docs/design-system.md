# Household Treasurer — Design system

Jedna strona zasad wyglądu aplikacji, dla ludzi i agentów. Decyzje stojące za nimi są w ADR-0001 (język wizualny OpenShip), ADR-0003 (przepis na powierzchnie), ADR-0023 (krój z `latin-ext`), ADR-0032 (poziomy alertów), ADR-0035 (tokeny Atrament i morski, Inter, JetBrains Mono), ADR-0037 (przepis panelu, kontrolki 32 px, `table-header`, Budżet bez odczytów miesiąca), ADR-0038 (Operacje: zakładki, wiersze dnia, wydatek w kolorze atramentu) ADR-0039 (lista Operacji bez nagłówków kolumn, „zł” w kwotach, kolorowe kafelki typu, akcje w menu „⋯”) i ADR-0040 (powłoka dialogu, pole formularza 34 px, fokus 2 px, przyciski `outline` i wyłączony); ADR-0035 zastępuje część ADR-0001, 0003 i 0023, ADR-0037 część ADR-0003 i 0033, a ADR-0040 część ADR-0037 (promień dialogu, fokus). Wizualny wzorzec: artefakt „C · Atrament i morski” na claude.ai (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, tylko podgląd, nie źródło wartości).

## Źródła prawdy

- **Wartości** (kolory, promienie): wyłącznie tokeny w `resources/css/app.css`, w `:root` i `.dark`. W kodzie i w tej dokumentacji używaj nazw tokenów (`bg-primary`, `text-destructive`), nigdy wartości hex ani kolorów palety Tailwinda (`red-500`, `amber-700`).
- **Komponenty bazowe**: `resources/js/components/ui/*` (shadcn-vue na reka-ui). Nie edytuj ich pod jeden ekran; zmiana tam zmienia całą aplikację.
- **Paleta kategorii**: `app/Support/Categories/CategoryColors.php`.
- **Kwoty**: `resources/js/lib/format.ts`.

## Powierzchnie i rytm

- Szare tło strony (`background`), panele to `card` z pełnym `border` i `rounded-lg` (`radius-lg`, 10 px; ADR-0037). Bez cieni spoczynkowych: głębię daje kontrast tła i karty.
- Kontrolki: `--control-height` 32 px dla Button `default`/`icon`, Input, SelectTrigger i `.native-select`; `--control-height-sm` 26 px dla Button `sm`/`icon-sm`; promień `radius-md`; wiersz tabeli ma `--row-height` 34 px. Pole formularza w `FormField` ma `--field-height` 34 px (pola w wierszu tytułu i w tabelach zostają 32/28 px); fokus 2 px `ring` przez `outline` (przyciski i elementy wyboru z odstępem 2 px, pola bez odstępu, z ramką schowaną), ADR-0040. Przycisk `outline` to `card` + `border` z hoverem `secondary`; wyłączony przycisk ma `muted` + `muted-foreground` (`ghost` i `link` tylko `muted-foreground`).
- Selecty: `.native-select` dla prostych wyliczeń bez ikon; `Select` (Reka) gdy opcja pokazuje ikonę lub logo (np. `AccountBankSelect`). Oba mają 32 px i `radius-md`; `.native-select` stylizuje też `aria-invalid` (czerwona ramka).
- Lista Budżetu używa `.budget-category-grid` (jeden markup na każdej szerokości, jak `.operations-grid` w Operacjach): na telefonie wiersz ma 2–3 linie, od `md` kolumny. Zarchiwizowane wiersze są w całości `muted-foreground`, bez kolorów statusu i bez `opacity`; reguły kolorów komórek są w `resources/js/lib/budget-category-cells.ts`. Wyjątek: ujemne saldo zarchiwizowanej lokaty zostaje `text-destructive` (FR-TD-08).
- Listy i tabele: `ListSurface` z wierszami na siatce (`.operations-grid`, `.budget-category-grid`); wiersze oddzielone linią `border-soft`, hover `secondary`, wiersz sumy na `muted`. Nagłówki kolumn i podpisy mobilne nad wartościami (Inter 600, 12/16, zwykła wielkość liter, jedna linia, `muted-foreground`) przez styl `table-header` z `app.css` (jedyny przepis podpisu kolumny; wersaliki `label-caps` tylko nad miarami i w sidebarze), nigdy ręcznie składany z `text-xs … uppercase`. Lista Operacji ma klasy `.operations-grid`, `.operations-row` i `.operations-day-row` (wiersz dnia na tej samej siatce, tło `muted`) i nie ma wiersza nagłówków kolumn (ADR-0039). Żadna sekcja nie ma kolumny daty: wszystkie trzy są pogrupowane w dni, a datę niesie wiersz dnia (ADR-0039). Operacje finansowe mają kolumny Opis | Kategoria | Kwota | „⋯” i wiersz dnia z sumą z serwera; transfery własne i ruchy kapitału mają Opis | Konta | Kwota | „⋯” (`.operations-grid-transfer`) i wiersz dnia bez sumy (`OperationsDayRow` bez `sumMinorUnits`, grupy z `groupRowsByDay`). Widok to jeden panel aktywnej sekcji z zakładkami nad nim (ADR-0038): `ListSurface` `flush` (wiersze do krawędzi karty, z własnym odstępem `px-4`), na górze pasek filtrów (szukajka na całą wolną szerokość, presety miesiąca i „Filtry” po prawej, aktywne filtry w osobnej linii, bez licznika wyników), pod nim wiersze, pod nimi paginacja; stopka tylko w transferach i ruchach kapitału (objaśnienie), bez „Kwoty w zł”. Lista podglądu importu ma `.import-preview-grid`, `.import-preview-header-row` (nagłówek ukryty poniżej `xl`) i `.import-preview-row` z modyfikatorami `-duplicate` (tint `warning/10`) i `-pair` (tint `pair/10`, kandydat na parę przelewu własnego).
- Siatka wiersza planu miesiąca to `.month-plan-grid`; etykieta sekcji `.section-label`, nagłówek kolumny `.column-header` (przepis `table-header`), tabele `.list-surface-table`. W widokach nie ma wartości arbitralnych (`text-[11px]`, `grid-cols-[...]`), tylko nazwane klasy z `app.css`.
- Dialog (ADR-0040): okno `--dialog-width` 460 px na `card` z pełnym `border`, `radius-xl` (12 px) i cieniem `shadow-dialog`, nakładka `scrim`, tytuł `dialog-title` (15/20, 600) do lewej także na telefonie, stopka z ramką u góry na całą szerokość okna. „Anuluj” to `outline`. Potwierdzenia, także z treścią (np. ostrzeżenie o deficycie jako `Alert` `warning`), idą przez `ConfirmActionDialog`, nigdy ręcznie złożony `DialogContent`; zapis w toku ma spinner i blokadę zamykania.
- Odstępy: 16 px między kartami i na krawędzi strony, 20 px od `md`, 24 px wewnątrz karty.

## Typografia

Inter, wagi 400/500/600, podzbiory `latin` + `latin-ext`. JetBrains Mono (`--font-mono`) jest krojem kwot, wdrażanym widok po widoku; Budżet (ADR-0037) i Operacje (ADR-0038) są wdrożone, pozostałe widoki zostają w Interze z `tabular-nums` do swojego `/10x-ui`. Każda kwota ma `tabular-nums`. Skala w widokach wdrożonych to nazwane style z `app.css`; w pozostałych: liczby w kafelkach `text-3xl`, nagłówek strony domenowej `text-lg font-semibold`, tytuł karty `text-base font-semibold`, treść i kontrolki `text-sm`, podpisy `text-xs text-muted-foreground`.

| Styl                    | Zapis                                             | Użycie                               |
| ----------------------- | ------------------------------------------------- | ------------------------------------ |
| `page-title`            | Inter 600, 18/24, -0,01em                         | h1 strony                            |
| `section-title`         | Inter 600, 13/18                                  | tytuł panelu                         |
| `table-header`          | Inter 600, 12/16, `muted-foreground`, jedna linia | nagłówek kolumny                     |
| `label-caps`            | Inter 500, 11/14, wersaliki, +0,06em              | etykieta miary, sidebar              |
| `meta`                  | Inter 400, 11/14                                  | podpis, stopka panelu                |
| `kpi-lg` / `kpi`        | mono 500, 26/32 / 22/28                           | wartość miary (pierwsza / pozostałe) |
| `amount` / `amount-sum` | mono 500 / 600, 13/18, `tabular-nums`             | kwota w komórce / w wierszu sumy     |

## Kolor znaczy coś tylko w trzech miejscach

| Token                              | Znaczenie                                                                                                                                                                                                         | Nie używaj do                                                                                                                  |
| ---------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `primary` (atrament)               | akcja, zaznaczenie; w ciemnym motywie jasny z ciemnym tekstem                                                                                                                                                     | tożsamości aplikacji, dekoracji, statusu pieniędzy                                                                             |
| `brand`, `brand-foreground`        | tożsamość i nawigacja: kafelek logo, aktywna pozycja sidebara (miętowy `#5fd4c4`)                                                                                                                                 | statusu, tekstu na jasnym tle                                                                                                  |
| `brand-text`                       | linki w treści, kreski i znaczniki w kolorze marki (morski, kontrast tekstu)                                                                                                                                      | wypełnień dużych powierzchni                                                                                                   |
| `accent`                           | tło zaznaczenia i aktywnego chipa (miętowy tint) z tekstem `accent-foreground`                                                                                                                                    | statusu                                                                                                                        |
| `success`                          | dochód zaksięgowany, dochód ponad plan; kwota dochodu i zwrotu w Operacjach, dodatnia suma dnia                                                                                                                   | „wszystko OK” poza kwotami                                                                                                     |
| `destructive`                      | przekroczenie: ujemne saldo, ponad limit, ujemne „Pozostało w miesiącu”; akcje usuwające                                                                                                                          | ostrzeżeń, które jeszcze nie są przekroczeniem; kwoty wydatku (ADR-0038; czerwony kafelek typu wydatku to tylko typ, ADR-0039) |
| `warning` (bursztyn)               | ostrzeżenie: w granicach, ale blisko przekroczenia; ostrzeżenia w formularzach, które nie blokują zapisu                                                                                                          | przekroczeń już zaksięgowanych; typu operacji „Zwrot” (ma token `refund`)                                                      |
| `refund`, `transfer`               | kafelki typów operacji „Zwrot” (`warning-soft` + `refund`) i „Przelew własny” (tint `transfer`) w `OperationTypeTile`; dochód to `success` 10% + `success`, wydatek `destructive-soft` + `destructive` (ADR-0039) | czegokolwiek poza typem operacji                                                                                               |
| `pair`                             | tło wiersza podglądu importu: kandydat na parę transferu własnego (tint /10)                                                                                                                                      | tekstu, kwot, typu operacji (to `transfer`)                                                                                    |
| `warning-soft`, `destructive-soft` | tło pigułek i alertów odpowiedniej wagi                                                                                                                                                                           | tekstu                                                                                                                         |
| `track`                            | tło pustego paska postępu i miernika                                                                                                                                                                              | obramowań                                                                                                                      |
| `border-soft`                      | najlżejsza linia: separatory wierszy i wnętrz kart                                                                                                                                                                | obramowań pól                                                                                                                  |
| `scrim`                            | półprzezroczysta nakładka pod dialogiem i arkuszem                                                                                                                                                                | tła kart                                                                                                                       |
| `muted-foreground`                 | podpisy, etykiety, rzeczy zarchiwizowane (cały wiersz, także kwoty i %, bez `success`/`destructive`). Wyjątek: ujemne saldo zarchiwizowanej lokaty zostaje `text-destructive` (FR-TD-08)                          | kwot aktywnych wierszy                                                                                                         |

Kolor kategorii (paleta 24 barw) to tylko tożsamość kategorii: kafelek ikony w `CategoryChip` (tło 15%). Nigdy nie oznacza statusu i nie maluje pasków ani kwot.

Tekst musi mieć kontrast co najmniej 4,5:1, grafika 3:1. Pary tekstu z systemu mają zmierzone co najmniej 5,03:1 w jasnym i 6,05:1 w ciemnym motywie, `ring` 6,25 i 9,74:1 (ADR-0035). `border` i `input` na `card` mają 1,37 i 1,32:1, więc są liniami dekoracyjnymi, a nie granicą kontrolki 3:1; stan pola musi wynikać także z czegoś innego niż sama ramka. Nowy kolor dodawaj jako token w obu trybach i sprawdź kontrast w obu, nie jako klasę palety.

## Kwoty

Zapisywane jako całkowite Minor units. Wyświetlane przez `formatMoney`: `1 234,56 zł`, spacja tysięcy, przecinek, zawsze dwa miejsca, wyrównanie do prawej. W komórkach tabel jednostka stoi w stopce panelu („Kwoty w zł”), więc komórka używa `formatMoneyAmount` (ta sama liczba bez „zł”) ze stylem `amount`; miary i dialogi zostają przy `formatMoney`. Wyjątek: lista Operacji ma „zł” w każdej kwocie i nie ma tej stopki (ADR-0039). Pola edycji: `minorUnitsToMoneyInput` / `parseMoneyInputToMinorUnits`. Znak minus przed kwotą, nie nawias.

Kwota operacji w Operacjach (`formatFinancialOperationAmount`, z „zł”) ma znak z typu: dochód i zwrot `+` w `text-success`, wydatek `−` w `text-foreground`, nie w `destructive` (ADR-0038; `financialOperationAmountClass`). Suma dnia w wierszu dnia (`formatOperationDaySum`, `operationDaySumClass`) ma ten sam znak i kolor: `> 0` → `+` w `text-success`, `< 0` → `−` w `text-foreground`, `0` → `0,00 zł`. To suma z serwera (prop `operationDaySums`): dochód − wydatek, zwroty netto, bez transferów i ruchów kapitału, przy aktywnych filtrach, dla całego dnia. Etykieta dnia to data słowami z `formatDayLabel` („środa, 4 listopada”, z rokiem, gdy nie bieżący).

Kolor kwoty ze znakiem (saldo, „do wydania”, różnica; nie kwota operacji ani suma dnia) daje wyłącznie `amountToneClass` z `resources/js/lib/amount-tone.ts`: `> 0` → `text-success`, `0` → bez koloru, `< 0` → `text-destructive`. Zero nigdy nie jest zielone. Nie pisz lokalnych helperów „znak → kolor”.

## Alerty (Dashboard notices)

Dwa poziomy, nadawane przez serwer w polu `severity` (ADR-0032):

- **`exceeded`**: coś już jest przekroczone. Kwota w `text-destructive`.
- **`warning`**: jeszcze w granicach, ale blisko. Kwota (jeśli jest) w `text-warning`. Emitują go `month_plan_not_approved` i `month_pace_ahead` (progi w `CONTEXT.md`).

Strefa „Wymaga uwagi” ma w nagłówku po jednej pigułce na obecny poziom („Przekroczone · n” jako `<Badge variant="destructive-soft">`, „Ostrzeżenia · n” jako `<Badge variant="warning">`; pigułek wagi nie buduje się ręcznie), płaską siatkę kart (jedna kolumna na mobile, dwie od `sm`, trzy od `lg`) z sześcioma widocznymi kartami i „Pokaż wszystkie (n)” z łączną liczbą. Karty są ułożone „exceeded” przed „warning”, bez podnagłówków grup. Każda karta jest linkiem do przyczyny i ma u góry etykietę rodzaju (np. „Saldo ujemne”, „Ponad plan miesiąca”, „Plan niezatwierdzony”, „Tempo przed upływem dni”, „Do wydania poniżej zera”, „Ponad limit”), a kolor i tło bierze tylko z wagi. Nowy rodzaj alertu: najpierw pojęcie i progi w `CONTEXT.md`, potem `kind` i `severity` w `App\Support\DashboardNotices`.

Ostrzeżenia w formularzach i podglądzie importu (np. „Operacja przekroczy dostępny limit kategorii”, ujemne saldo) nie blokują zapisu, więc używają `<Alert variant="warning">`. `variant="destructive"` zostaje dla prawdziwych błędów (`AlertError`), a błąd pola pokazuje `InputError` w `text-destructive`.

## Gotowe elementy

Zanim napiszesz nowy markup, sprawdź: `ListSurface`, `CategoryChip`, `CategoryMeterRow`, `OperationTypeTile`, `IconAction` (przycisk-ikona z podpowiedzią i `aria-label`), `IndexEmptyState`, `Heading`, `AccountIdentityBlock`, `StatTile` (kafelek KPI: etykieta, kwota z `amountToneClass`, sloty `label-extra` i `footer`), `StatPanel` (jedna powierzchnia z 2–4 miarami rozdzielonymi pionowymi liniami, pierwsza w `kpi-lg`; używa go Budżet), `ExecutionMeter` (pasek realizacji: tor `track`, wypełnienie `foreground`, przy przekroczeniu `destructive`, kreska roku `brand-text`, procent; wspólny dla Dashboardu i Budżetu), `Switch` (ustawienie zapisywane od razu, `v-model`; tor `track`, włączony `primary`), `FormField` (pole formularza w dialogu: etykieta, kontrolka, podpowiedź i błąd powiązane `aria-describedby`, wariant `money` z „zł”; 34 px), `SegmentedControl` (w formularzu wariant `block`, ton `primary`, z `name`), `ConfirmActionDialog` (potwierdzenie z `Form`, spinner, blokada zamykania w trakcie żądania, błędy serwera), `AccountMarkCompact` (kafelek znaku banku/gotówki). Ikony: `@lucide/vue`, 16 px w kontrolkach.

`Heading` służy sekcjom ustawień. Strony domenowe budują wiersz tytułu (`h1` + `Badge` po lewej, akcja po prawej) wg `Budget.vue`. W wierszu tytułu jest co najwyżej jedna akcja główna (`primary`), pozostałe są `outline` z ikoną; powód wyłączenia akcji to widoczny tekst pod przyciskami, powiązany `aria-describedby`.

`SegmentedControl` (reka-ui `ToggleGroup`, jeden przystanek Tab, strzałki zmieniają opcję) ma dwa tony; opcja może mieć licznik `count` (mono, `tabular-nums`):

- `primary` – wybór w formularzu, który zmienia stan dopiero po zapisie (np. odkładam / dobieram);
- `neutral` – ustawienie zapisywane od razu (np. rodzaj pozycji kategorii), więc wygląda inaczej niż kontrolka formularza; także zakładki sekcji Operacji, których wybór siedzi w URL (`?section=`, ADR-0038), z licznikiem rekordów i klasą `segmented-control-tabs` (bez obwódki i tła kontenera, aktywna zakładka to karta; ADR-0039).

W wierszu tytułu Operacji jedyną akcją główną jest „Dodaj operację”: `primary` z menu (Dochód, Wydatek, Transfer własny, Ruch kapitału), a „Importuj” jest `outline`. Wyłączona pozycja menu pokazuje powód jako tekst `meta` w pozycji (reka pomija wyłączone pozycje przy nawigacji klawiaturą). Akcje wiersza (Edytuj, Usuń) są w menu za przyciskiem „⋯” (`OperationRowMenu`, jak w wierszach Budżetu); Usuń otwiera `ConfirmActionDialog` sterowany przez `v-model:open`, bo treść menu znika po jego zamknięciu. Paginacja (`PaginationLinks`) to strzałki i numery stron w wysokości `control-sm`, aktywna strona wygląda jak aktywna zakładka.

### Kitchen sink i zrzuty

`/dev/kitchen-sink/budget` (tylko `local`) renderuje Budżet bez paska miesiąca w macierzy 7 stanów: realizacja (w normie, dochód ponad 100%, przekroczenie, zarchiwizowany, kategoria jednorazowa bez kreski, równomierna z kreską), szkic z kolumną „Typ”, pusty budżet, brak kategorii, Switch wł./wył., panel miar z 3 i 4 miarami.

`/dev/month-plan` (tylko `APP_ENV=local`) renderuje widok Plan miesiąca z danych testowych (`resources/js/lib/month-plan-fixtures.ts`) w macierzy 7 stanów (default, hover, focus-visible, disabled, error, empty, loading; komórka niemożliwa ma „N/A” z powodem). Po zmianie widoku: `npm run build`, podgląd z worktree (AGENTS.md „Preview from a worktree”), jednorazowy skrypt Playwright robi pełne zrzuty `/dev/month-plan` i `/month-plan` przy 1280 i 390 px do `context/changes/<change-id>/screenshots/`. Skrypt nie jest commitowany. Tak samo `/dev/operations` (`resources/js/lib/operations-fixtures.ts`) dla widoku Operacje: renderuje prawdziwe komponenty (`OperationsDayRow` i trzy wiersze) w trzech zakładkach, z dniem ze zwrotem i dodatnią sumą oraz dniem z ujemną sumą, a w macierzy 7 stanów także menu „Dodaj operację” z wyłączonymi pozycjami, pusty wynik filtrów, sekcję wyciszoną filtrem, sekcję bez rekordów i ładowanie (`aria-busy`), oraz `/dev/import-preview` (`resources/js/lib/import-preview-fixtures.ts`) dla podglądu importu CSV.

## Sidebar

Tokeny `--sidebar-*` w `app.css` (w obu trybach). Tło to `--sidebar-background`; nie ma tokenu `--sidebar`. Sidebar jest granatowy w obu motywach i przylega do treści (`variant="sidebar"`, bez `inset`); treść leży na `background`.

| Token (utility)                                  | Rola                                                                                                            |
| ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------- |
| `sidebar` (z `--sidebar-background`)             | tło sidebara                                                                                                    |
| `sidebar-foreground`                             | tekst i ikony pozycji menu w spoczynku                                                                          |
| `sidebar-strong` (`text-sidebar-strong`)         | nazwa aplikacji, użytkownik, wybrany rok i „Dodaj rok”                                                          |
| `sidebar-primary` / `sidebar-primary-foreground` | aktywna pozycja menu (także pod kursorem), kafelek logo; czytają je też `Welcome.vue` i `LandingAppPreview.vue` |
| `sidebar-accent` / `sidebar-accent-foreground`   | hover, otwarte menu, kafelek przełącznika roku                                                                  |
| `sidebar-border`                                 | obramowania i separatory sidebara                                                                               |
| `sidebar-ring`                                   | fokus                                                                                                           |

- Wszystko w slotach `SidebarHeader` / `SidebarFooter` budujemy z `SidebarMenuButton` (albo obsługujemy `group-data-[collapsible=icon]`), żeby działało w pasie 3rem po zwinięciu.
- Aktywna pozycja menu: `isCurrentSection` (ścieżka i jej podstrony; `resources/js/lib/nav-active.ts`).

## Tryb ciemny

Jasny jest domyślny. Ciemny używa tych samych nazw tokenów z wartościami z `.dark`, więc komponent nie potrzebuje wariantów `dark:`, jeśli używa tokenów. Kolorów palety Tailwinda w komponentach nie ma, więc warianty `dark:` do kolorów nie są potrzebne.

## Zmiana systemu

1. Zmień token w `app.css` w obu trybach.
2. Zaktualizuj tę stronę, a jeśli to decyzja, dopisz ADR.
3. Zasady dla agentów żyją w `.ai/rules` (zapis przez `record-rule` z Laravel Boost); trzymaj je zgodne z tą stroną.
4. Wzorce widoków ogląda się w kitchen sink pod `/dev/ui/dashboard`, `/dev/ui/accounts`, `/dev/ui/term-deposits` i `/dev/ui/dialogs` (dialogi otwarte w każdym stanie, `?open=<stan>`), a sidebar (tryb ikon, stan pusty przełącznika roku, oba motywy) pod `/dev/ui/sidebar`. `npm run check:ui` (`scripts/check-ui-literals.mjs`) skanuje oczyszczone pliki, w tym widok Budżetu i jego komponenty oraz widok Operacji (`OperationTypeTile`, `CompactAccountIdentity`, `SegmentedControl`, `OperationsDayRow` i wiersze), pod kątem literałów (kolory, klasy palety, px/rem w nawiasach); nowo oczyszczone widoki dopisz do listy w `scripts/check-ui-literals.mjs`.
