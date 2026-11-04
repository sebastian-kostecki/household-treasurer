# Kontrakt UI podglądu importu CSV — Plan Brief

> Full plan: `context/changes/import-preview-ui-contract/plan.md`
> Research: `context/changes/import-preview-ui-contract/research.md`

## What & Why

Podgląd importu CSV (`OperationsImportPreview.vue` + `ImportPreviewRow.vue`) to ostatni widok, na który wcześniejsze przebiegi UI odroczyły literały. Ma ręczne podpisy 11 px, tło „kandydat na parę” spoza systemu, surowe pole odsetek i kontrolki bez nazw. Do tego dochodzą dwa błędy funkcjonalne: ostrzeżenie „ujemne Do wydania” w wierszu nigdy się nie renderuje, a zatwierdzenie podglądu z wierszem niepoprawnym kończy się niewidocznym błędem walidacji. Zmiana doprowadza widok do kontraktu systemu projektowego i naprawia oba błędy.

## Starting Point

Repo ma już wszystko, czego widok potrzebuje: `column-caption`, wzorzec `.operations-header-row` / `.operations-row`, `ui/input`, `IndexEmptyState`, `Alert variant="warning"`, wzorzec powodu blokady (`OperationsPageActions`), kitchen sinki i `npm run check:ui`. Widok z nich nie korzysta i nie jest na liście strażnika.

## Desired End State

- Lista importu ma nazwane klasy nagłówka i wiersza, tinty z tokenów (`warning`, nowy `pair`) i zero literałów w plikach widoku.
- Każda kontrolka ma nazwę dostępną, a kwota ma kolor z `amountToneClass`.
- Alert to-spend w wierszu działa. Alert duplikatów pojawia się tylko przy duplikatach, a zastrzeżenie o granicach wykrywania zostaje jako notka w karcie.
- „Zatwierdź import” mówi, czemu jest wyłączony, i pokazuje spinner w czasie zapisu.
- Pusty podgląd to prawdziwy stan pusty z powrotem do wgrywania.
- Plik z wierszem niepoprawnym daje się zaimportować.
- `/dev/import-preview` pokazuje 7 stanów, a strażnik i reguła pilnują kontraktu.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego | Źródło |
| --- | --- | --- | --- |
| Tło „kandydat na parę” | Nowy token `--pair` (`:root` + `.dark`, `--color-pair`) | `transfer` jest zastrzeżony dla typu operacji; klasa palety nie ma wersji dark | Research |
| Kolor kwoty | `amountToneClass` ze znaku z wyciągu; `rowAmountClass` usunięty | Zero nigdy nie jest zielone; jeden helper w repo | Research |
| Zakres C6 | W tej zmianie: test 422 → poprawka → widoczne `rows.N.*` | Bez tego plik z jednym złym wierszem nie daje się zaimportować | Research |
| Nazwy selectów Reka | Opcjonalny prop `ariaLabel` w `CategorySelect` / `AccountSelect` | `aria-label` z zewnątrz trafia dziś na `div`, nie na trigger | Research |
| Poprawka C6 | `required_if:rows.*.selected,true` + `rowSelections()` tylko dla zaznaczonych | Samo poluzowanie reguły dałoby 500 w `rowSelections()` | Plan |
| Zastrzeżenie o duplikatach | Zawsze jako notka w karcie podsumowania; alert warning tylko przy > 0, z liczbą | Prawdziwy sygnał bez utraty wiedzy o granicach wykrywania | Plan |
| Powód blokady „Zatwierdź import” | Jedna linia, priorytet: brak zaznaczenia → niekompletne (z liczbą) → niepotwierdzone duplikaty | Wzorzec `OperationsPageActions`, prosty test jednostkowy | Plan |
| Środowisko podglądu | Worktree + baza `import_preview_ui_contract`, port 8090 | Izolacja od main checkoutu, sprawdzony przepis AGENTS.md | Plan |
| Logika szablonów | Do `lib/` z `*.test.ts` (`importRowHasNotes`, `importSubmitBlockReason`, `firstRowsError`) | Repo nie testuje komponentów Vue; typ wymusza wpis każdego alertu | Plan |

## Scope

**In scope:**
- C1–C6 z researchu
- token `pair` i klasy listy importu w `app.css`
- `ImportPreviewSubmitBar`
- prop `ariaLabel` w dwóch wrapperach
- klucze `lang/pl/ui.php`
- pusty stan i spinner
- kitchen sink `/dev/import-preview` z fiksturami i testem 404
- strażnik, reguła i dokumentacja

**Out of scope:**
- ekran wgrywania `OperationsImport.vue` (D3)
- polskie komunikaty serwera (D2)
- wspólna klasa wiersza dla całej aplikacji (D1)
- `h1` (D4)
- domyślne zaznaczanie wierszy
- oznaczanie niekompletnych wierszy
- kolor kwoty według typu
- zmiany dialogów, `components/ui/*` i nowe zależności

## Architecture / Approach

Kolejność `/10x-ui`: środowisko i kitchen sink → poprawka funkcjonalna C6 (test-first) → kontrakt (token, klasy, funkcje w `lib/`, prop, tłumaczenia; pokazany najpierw w sinku) → widok → stany z macierzy → utrwalenie. Każda faza wizualna kończy się zrzutami `/dev/import-preview` i prawdziwego podglądu przy 1280 i 390 px (jednorazowy Playwright przez `npx`). Pasek zatwierdzenia jest osobnym komponentem, żeby sink renderował prawdziwy przycisk w stanach disabled i loading.

## Phases at a Glance

| Faza | Co dostarcza | Główne ryzyko |
| --- | --- | --- |
| 1. Środowisko, kitchen sink, punkt odniesienia | Worktree, `/dev/import-preview` z fiksturami, test 404, zrzuty bazowe | Zrzut prawdziwego podglądu wymaga wgrania CSV w skrypcie |
| 2. Błąd zatwierdzenia (C6) | Test potwierdzający 422, poprawka reguły i `rowSelections()`, widoczne `rows.N.*` | `required_if` z wartością bool — test w obie strony |
| 3. Elementy kontraktu | `--pair`, klasy nagłówka/wiersza/tintów, 2 funkcje w `lib/` z testami, `ariaLabel`, klucze | Kolejność klas w `app.css` (nagłówek od `xl`) |
| 4. Widok (C1–C5) | Widok na klasach, `<Input>`, `amountToneClass`, nazwy, notatki, alerty, pasek z powodem | Nazwy dostępne w 6 kontrolkach wiersza |
| 5. Stany z macierzy | Pusty stan, spinner, focus/disabled, sink 7 stanów | Pusty stan musi ukryć `Form` (`rows` ma `min:1`) |
| 6. Utrwalenie | `check:ui`, reguła, design-system, statusy zarzutów, `ci:check` | Konflikty w `app.css` / `ui.php` przy scalaniu |

**Prerequisites:** działający stack Sail z PostgreSQL; Playwright Chromium w `~/.cache/ms-playwright`.
**Estimated effort:** ~3–4 sesje przez 6 faz (każda z ręczną bramką).

## Open Risks & Assumptions

- C6 to wniosek z kodu. Faza 2 zaczyna się od testu, który ma oblać na HEAD z `rows.N.type`. Jeśli nie obleje, raport fazy to odnotowuje, a poprawka idzie jako zabezpieczenie.
- Wartość `--pair` w trybie ciemnym jest startowa i do korekty po zrzucie fazy 3.
- Błędy `rows.N.*` z serwera są po angielsku (D2), a strona pokazuje je tak, jak przyjdą.

## Success Criteria (Summary)

- Użytkownik importujący wyciąg z jednym złym wierszem importuje pozostałe, a gdy nie może zatwierdzić, widzi, dlaczego.
- Ostrzeżenie o ujemnym „Do wydania” pojawia się w wierszu; „Możliwe duplikaty” tylko wtedy, gdy są duplikaty.
- `npm run check:ui` i `composer ci:check` przechodzą z plikami widoku na liście, a `/dev/import-preview` pokazuje 7 stanów w obu motywach.
