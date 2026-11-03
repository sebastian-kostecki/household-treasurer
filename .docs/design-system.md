# Household Treasurer — Design system

Jedna strona zasad wyglądu aplikacji, dla ludzi i agentów. Decyzje stojące za nimi są w ADR-0001 (język wizualny OpenShip, turkus), ADR-0003 (przepis na powierzchnie), ADR-0023 (Geist) i ADR-0032 (poziomy alertów). Wizualny wzorzec: kanwa „Treasurer Design System” na claude.ai (tylko podgląd, nie źródło wartości).

## Źródła prawdy

- **Wartości** (kolory, promienie): wyłącznie tokeny w `resources/css/app.css`, w `:root` i `.dark`. W kodzie i w tej dokumentacji używaj nazw tokenów (`bg-primary`, `text-destructive`), nigdy wartości hex ani kolorów palety Tailwinda (`red-500`, `amber-700`).
- **Komponenty bazowe**: `resources/js/components/ui/*` (shadcn-vue na reka-ui). Nie edytuj ich pod jeden ekran; zmiana tam zmienia całą aplikację.
- **Paleta kategorii**: `app/Support/Categories/CategoryColors.php`.
- **Kwoty**: `resources/js/lib/format.ts`.

## Powierzchnie i rytm

- Szare tło strony (`background`), białe karty (`card`, `rounded-2xl`, obramowanie `border/60`). Bez cieni spoczynkowych: głębię daje kontrast tła i karty.
- Kontrolki (przyciski, pola, selecty): wysokość 36 px (`h-9`), `rounded-xl`, fokus = pierścień 3 px `ring/50`.
- Selecty: `.native-select` dla prostych wyliczeń bez ikon; `Select` (Reka) gdy opcja pokazuje ikonę lub logo (np. `AccountBankSelect`). Oba mają `rounded-xl`; `.native-select` stylizuje też `aria-invalid` (czerwona ramka).
- Lista Budżetu używa `.budget-category-grid` (jeden markup na każdej szerokości, jak `.operations-grid` w Operacjach): na telefonie wiersz ma 2–3 linie, od `md` kolumny. Zarchiwizowane wiersze są w całości `muted-foreground`, bez kolorów statusu i bez `opacity`; reguły kolorów komórek są w `resources/js/lib/budget-category-cells.ts`.
- Listy i tabele: `ListSurface` z wierszami na siatce (`.operations-grid`, `.budget-category-grid`); wiersze oddzielone linią `border/60`, hover `foreground/3%`. Nagłówki kolumn i podpisy mobilne nad wartościami (11 px, wersaliki, `muted-foreground`) przez klasę `column-caption` z `app.css`, nigdy ręcznie złożoną z `text-xs … uppercase`.
- Siatka wiersza planu miesiąca to `.month-plan-grid`; etykieta sekcji `.section-label`, nagłówek kolumny `.column-header`, tabele `.list-surface-table`. W widokach nie ma wartości arbitralnych (`text-[11px]`, `grid-cols-[...]`), tylko nazwane klasy z `app.css`.
- Odstępy: 16 px między kartami i na krawędzi strony, 20 px od `md`, 24 px wewnątrz karty.

## Typografia

Geist, wagi 400/500/600. Każda kwota ma `tabular-nums`. Skala: liczby w kafelkach `text-3xl`, nagłówek strony domenowej `text-lg font-semibold` (strony dev mogą mieć `text-xl`), tytuł karty `text-base font-semibold`, treść i kontrolki `text-sm`, podpisy `text-xs text-muted-foreground`.

## Kolor znaczy coś tylko w trzech miejscach

| Token                | Znaczenie                                                                                                | Nie używaj do                                                             |
| -------------------- | -------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `primary` (turkus)   | akcja, zaznaczenie, pasek realizacji w normie                                                            | dekoracji, statusu pieniędzy                                              |
| `success`            | dochód zaksięgowany, dochód ponad plan                                                                   | „wszystko OK” poza kwotami                                                |
| `destructive`        | przekroczenie: ujemne saldo, ponad limit, ujemne „Pozostało w miesiącu”; akcje usuwające                 | ostrzeżeń, które jeszcze nie są przekroczeniem                            |
| `warning` (bursztyn) | ostrzeżenie: w granicach, ale blisko przekroczenia; ostrzeżenia w formularzach, które nie blokują zapisu | przekroczeń już zaksięgowanych; typu operacji „Zwrot” (ma token `refund`) |
| `refund`, `transfer` | kafelki typów operacji „Zwrot” i „Przelew własny” (`OperationTypeTile`)                                  | czegokolwiek poza typem operacji                                          |
| `muted-foreground`   | podpisy, etykiety, rzeczy zarchiwizowane (cały wiersz, także kwoty i %, bez `success`/`destructive`)     | kwot aktywnych wierszy                                                    |

Kolor kategorii (paleta 24 barw) to tylko tożsamość kategorii: kafelek ikony w `CategoryChip` (tło 15%). Nigdy nie oznacza statusu i nie maluje pasków ani kwot.

Tekst na białym musi mieć kontrast co najmniej 4,5:1. Dlatego `destructive` w trybie jasnym to red-600, a `warning` to amber-700. Nowy kolor dodawaj jako token w obu trybach, nie jako klasę palety.

## Kwoty

Zapisywane jako całkowite Minor units. Wyświetlane przez `formatMoney`: `1 234,56 zł`, spacja tysięcy, przecinek, zawsze dwa miejsca, wyrównanie do prawej. Pola edycji: `minorUnitsToMoneyInput` / `parseMoneyInputToMinorUnits`. Znak minus przed kwotą, nie nawias.

Kolor kwoty ze znakiem (saldo, „do wydania”, różnica) daje wyłącznie `amountToneClass` z `resources/js/lib/amount-tone.ts`: `> 0` → `text-success`, `0` → bez koloru, `< 0` → `text-destructive`. Zero nigdy nie jest zielone. Nie pisz lokalnych helperów „znak → kolor”.

## Alerty (Dashboard notices)

Dwa poziomy, nadawane przez serwer w polu `severity` (ADR-0032):

- **`exceeded`**: coś już jest przekroczone. Kwota w `text-destructive`.
- **`warning`**: jeszcze w granicach, ale blisko. Kwota (jeśli jest) w `text-warning`. Emitują go `month_plan_not_approved` i `month_pace_ahead` (progi w `CONTEXT.md`).

Strefa „Wymaga uwagi” ma w nagłówku po jednej pigułce na obecny poziom („Przekroczone · n” jako `<Badge variant="destructive-soft">`, „Ostrzeżenia · n” jako `<Badge variant="warning">`; pigułek wagi nie buduje się ręcznie), płaską siatkę kart (jedna kolumna na mobile, dwie od `sm`, trzy od `lg`) z sześcioma widocznymi kartami i „Pokaż wszystkie (n)” z łączną liczbą. Karty są ułożone „exceeded” przed „warning”, bez podnagłówków grup. Każda karta jest linkiem do przyczyny i ma u góry etykietę rodzaju (np. „Saldo ujemne”, „Ponad plan miesiąca”, „Plan niezatwierdzony”, „Tempo przed upływem dni”, „Do wydania poniżej zera”, „Ponad limit”), a kolor i tło bierze tylko z wagi. Nowy rodzaj alertu: najpierw pojęcie i progi w `CONTEXT.md`, potem `kind` i `severity` w `App\Support\DashboardNotices`.

Ostrzeżenia w formularzach i podglądzie importu (np. „Operacja przekroczy dostępny limit kategorii”, ujemne saldo) nie blokują zapisu, więc używają `<Alert variant="warning">`. `variant="destructive"` zostaje dla prawdziwych błędów (`AlertError`), a błąd pola pokazuje `InputError` w `text-destructive`.

## Gotowe elementy

Zanim napiszesz nowy markup, sprawdź: `ListSurface`, `CategoryChip`, `CategoryMeterRow`, `OperationTypeTile`, `IconAction` (przycisk-ikona z podpowiedzią i `aria-label`), `IndexEmptyState`, `Heading`, `AccountIdentityBlock`, `StatTile` (kafelek KPI: etykieta, kwota z `amountToneClass`, sloty `label-extra` i `footer`), `SegmentedControl`, `ConfirmActionDialog` (potwierdzenie z `Form`, spinner, blokada zamykania w trakcie żądania, błędy serwera), `AccountMarkCompact` (kafelek znaku banku/gotówki). Ikony: `@lucide/vue`, 16 px w kontrolkach.

`Heading` służy sekcjom ustawień. Strony domenowe budują wiersz tytułu (`h1` + `Badge` po lewej, akcja po prawej) wg `Budget.vue`.

`SegmentedControl` (reka-ui `ToggleGroup`, jeden przystanek Tab, strzałki zmieniają opcję) ma dwa tony:

- `primary` – wybór w formularzu, który zmienia stan dopiero po zapisie (np. odkładam / dobieram);
- `neutral` – ustawienie zapisywane od razu (np. rodzaj pozycji kategorii), więc wygląda inaczej niż kontrolka formularza.

### Kitchen sink i zrzuty

`/dev/month-plan` (tylko `APP_ENV=local`) renderuje widok Plan miesiąca z danych testowych (`resources/js/lib/month-plan-fixtures.ts`) w macierzy 7 stanów (default, hover, focus-visible, disabled, error, empty, loading; komórka niemożliwa ma „N/A” z powodem). Po zmianie widoku: `npm run build`, podgląd z worktree (AGENTS.md „Preview from a worktree”), jednorazowy skrypt Playwright robi pełne zrzuty `/dev/month-plan` i `/month-plan` przy 1280 i 390 px do `context/changes/<change-id>/screenshots/`. Skrypt nie jest commitowany.

## Sidebar

Tokeny `--sidebar-*` w `app.css` (w obu trybach). Tło to `--sidebar-background`; nie ma tokenu `--sidebar`.

| Token (utility)                                  | Rola                                                                 |
| ------------------------------------------------ | -------------------------------------------------------------------- |
| `sidebar` (z `--sidebar-background`)             | tło sidebara                                                         |
| `sidebar-foreground`                             | tekst i ikony pozycji menu                                           |
| `sidebar-primary` / `sidebar-primary-foreground` | kafelek logo; czytają je też `Welcome.vue` i `LandingAppPreview.vue` |
| `sidebar-accent` / `sidebar-accent-foreground`   | hover, aktywna pozycja menu, otwarte menu, kafelek przełącznika roku |
| `sidebar-border`                                 | obramowania i separatory sidebara                                    |
| `sidebar-ring`                                   | fokus                                                                |

- Glif na `sidebar-primary` jest jasny w obu trybach. W trybie ciemnym ma ok. 3,3:1, co wystarcza dla grafiki (WCAG 1.4.11), więc na `sidebar-primary` w trybie ciemnym nie kładziemy tekstu.
- Wszystko w slotach `SidebarHeader` / `SidebarFooter` budujemy z `SidebarMenuButton` (albo obsługujemy `group-data-[collapsible=icon]`), żeby działało w pasie 3rem po zwinięciu.
- Aktywna pozycja menu: `isCurrentSection` (ścieżka i jej podstrony; `resources/js/lib/nav-active.ts`).

## Tryb ciemny

Jasny jest domyślny. Ciemny używa tych samych nazw tokenów z wartościami z `.dark`, więc komponent nie potrzebuje wariantów `dark:`, jeśli używa tokenów. Kolorów palety Tailwinda w komponentach nie ma, więc warianty `dark:` do kolorów nie są potrzebne.

## Zmiana systemu

1. Zmień token w `app.css` w obu trybach.
2. Zaktualizuj tę stronę, a jeśli to decyzja, dopisz ADR.
3. Zasady dla agentów żyją w `.ai/rules` (zapis przez `record-rule` z Laravel Boost); trzymaj je zgodne z tą stroną.
4. Wzorce widoków ogląda się w kitchen sink pod `/dev/ui/dashboard` i `/dev/ui/accounts`, a sidebar (tryb ikon, stan pusty przełącznika roku, oba motywy) pod `/dev/ui/sidebar`. `npm run check:ui` skanuje oczyszczone pliki pod kątem literałów (kolory, klasy palety, px/rem w nawiasach); nowo oczyszczone widoki dopisz do listy w `scripts/check-ui-literals.mjs`.
