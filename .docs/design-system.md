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
- Listy i tabele: `ListSurface` / `.list-surface-table`; wiersze oddzielone linią `border/60`, hover `foreground/3%`, nagłówki kolumn 11 px wersalikami w `muted-foreground`.
- Odstępy: 16 px między kartami i na krawędzi strony, 20 px od `md`, 24 px wewnątrz karty.

## Typografia

Geist, wagi 400/500/600. Każda kwota ma `tabular-nums`. Skala: liczby w kafelkach `text-3xl`, nagłówek strony `text-xl font-semibold`, tytuł karty `text-base font-semibold`, treść i kontrolki `text-sm`, podpisy `text-xs text-muted-foreground`.

## Kolor znaczy coś tylko w trzech miejscach

| Token | Znaczenie | Nie używaj do |
| --- | --- | --- |
| `primary` (turkus) | akcja, zaznaczenie, pasek realizacji w normie | dekoracji, statusu pieniędzy |
| `success` | dochód zaksięgowany, dochód ponad plan | „wszystko OK” poza kwotami |
| `destructive` | przekroczenie: ujemne saldo, ponad limit, ujemne „Pozostało w miesiącu”; akcje usuwające | ostrzeżeń, które jeszcze nie są przekroczeniem |
| `warning` (bursztyn) | ostrzeżenie: w granicach, ale blisko przekroczenia; ostrzeżenia w formularzach, które nie blokują zapisu | przekroczeń już zaksięgowanych; typu operacji „Zwrot” (ma token `refund`) |
| `refund`, `transfer` | kafelki typów operacji „Zwrot” i „Przelew własny” (`OperationTypeTile`) | czegokolwiek poza typem operacji |
| `muted-foreground` | podpisy, etykiety, rzeczy zarchiwizowane | kwot |

Kolor kategorii (paleta 24 barw) to tylko tożsamość kategorii: kafelek ikony w `CategoryChip` (tło 15%). Nigdy nie oznacza statusu i nie maluje pasków ani kwot.

Tekst na białym musi mieć kontrast co najmniej 4,5:1. Dlatego `destructive` w trybie jasnym to red-600, a `warning` to amber-700. Nowy kolor dodawaj jako token w obu trybach, nie jako klasę palety.

## Kwoty

Zapisywane jako całkowite Minor units. Wyświetlane przez `formatMoney`: `1 234,56 zł`, spacja tysięcy, przecinek, zawsze dwa miejsca, wyrównanie do prawej. Pola edycji: `minorUnitsToMoneyInput` / `parseMoneyInputToMinorUnits`. Znak minus przed kwotą, nie nawias.

## Alerty (Dashboard notices)

Dwa poziomy, nadawane przez serwer w polu `severity` (ADR-0032):

- **`exceeded`**: coś już jest przekroczone. Kwota w `text-destructive`.
- **`warning`**: jeszcze w granicach, ale blisko. Kwota w `text-warning`. Żaden rodzaj alertu go jeszcze nie emituje.

Strefa „Wymaga uwagi” ma w nagłówku po jednej pigułce na obecny poziom („Przekroczone · n” w `destructive`, „Ostrzeżenia · n” w `warning`), trzy widoczne wiersze i „Pokaż wszystkie (n)” z łączną liczbą. Podnagłówki grup w liście pojawiają się tylko wtedy, gdy są oba poziomy. Każdy wiersz jest linkiem do przyczyny i ma pod kwotą szary powód: „Saldo ujemne”, „Pozostało w miesiącu”, „Ponad limit”. Nowy rodzaj alertu: najpierw pojęcie i progi w `CONTEXT.md`, potem `kind` i `severity` w `App\Support\DashboardNotices`.

Ostrzeżenia w formularzach i podglądzie importu (np. „Operacja przekroczy dostępny limit kategorii”, ujemne saldo) nie blokują zapisu, więc używają `<Alert variant="warning">`. `variant="destructive"` zostaje dla prawdziwych błędów (`AlertError`), a błąd pola pokazuje `InputError` w `text-destructive`.

## Gotowe elementy

Zanim napiszesz nowy markup, sprawdź: `ListSurface`, `CategoryChip`, `CategoryMeterRow`, `OperationTypeTile`, `IconAction` (przycisk-ikona z podpowiedzią i `aria-label`), `IndexEmptyState`, `Heading`, `AccountIdentityBlock`. Ikony: `@lucide/vue`, 16 px w kontrolkach.

## Tryb ciemny

Jasny jest domyślny. Ciemny używa tych samych nazw tokenów z wartościami z `.dark`, więc komponent nie potrzebuje wariantów `dark:`, jeśli używa tokenów. Kolorów palety Tailwinda w komponentach nie ma, więc warianty `dark:` do kolorów nie są potrzebne.

## Zmiana systemu

1. Zmień token w `app.css` w obu trybach.
2. Zaktualizuj tę stronę, a jeśli to decyzja, dopisz ADR.
3. Zasady dla agentów żyją w `.ai/rules` (zapis przez `record-rule` z Laravel Boost); trzymaj je zgodne z tą stroną.
