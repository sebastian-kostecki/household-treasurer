---
change_id: import-start-atrament-ui
title: Import start atrament ui
status: preparing
created: 2026-11-06
updated: 2026-11-06
archived_at: null
---

## Notes

Zmiana UI przez `/10x-ui`, jeden widok: `resources/js/pages/OperationsImport.vue` (krok 1 importu: format, plik, konto, mapowanie kolumn dla formatu „Inny”). Krok 2 (`OperationsImportPreview.vue`) jest poza zakresem.

- **Wariant kontraktu:** istniejący system projektowy. Rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Źródło tokenów:** `resources/css/app.css` (`:root` / `.dark`, `@theme inline`, `@utility` `page-title`, `meta`; `.form-field`, `.form-preview*`, `.native-select` w `@layer components`); wartości z design systemu C · Atrament i morski: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (podgląd, nie źródło wartości).
- **Wzorzec:** karta `PageImportStart`: tytuł „Import operacji” z opisem kroku 1 i „Wróć do operacji” (ikona strzałki); panel o szerokości 640 z nagłówkiem zależnym od formatu (h2 + `meta`); „Format importu”, „Plik importu (CSV lub XLSX)”, „Konto” z kafelkiem banku; blok „Rozpoznawane pola” na `background`; „Pokaż podgląd” w stopce panelu. Format „Inny”: „Pierwszy wiersz zawiera nagłówki kolumn”, zestaw „Kolumny”, układ kwoty jako wybór `primary`. Precedensy: ADR-0040 (anatomia pola, `FormField`), ADR-0044, zmiana `accounts-atrament-ui`.
- **ADR:** nowy ADR ma numer **0048** (0046 i 0047 zajmują inne zmiany w toku).
- **Klucze tłumaczeń:** dopisać brakujące `import_optional_column` i `import_no_column` w `lang/pl/ui.php`.

Wstępny audyt (2026-11-06): skan literałów 0 trafień; użycia stylów Atramentu (`page-title`, `meta`, `FormField`, `SegmentedControl`, `.form-preview`) 0; style sprzed Atramentu 17; widok bez zabezpieczeń (brak w `scripts/check-ui-literals.mjs`, brak `*HardcodedUiTest`, brak kitchen sinka kroku 1 — `routes/web.php:48` ma tylko `/dev/import-preview`). Zarzuty do potwierdzenia w researchu:

1. Brakujące tłumaczenia: `OperationsImport.vue:428` i `:439` czytają `t.operations.import_optional_column` / `import_no_column`, których nie ma w `lang/pl/ui.php` (sąsiedzi `:607–609`). W formacie „Inny” etykieta brzmi „Nadawca / odbiorca ()”, a pierwsza opcja listy jest pusta — użytkownik nie wie, że kolumnę można pominąć. Propozycja: „opcjonalnie”, „Brak kolumny”. Research: test, który wyłapie brakujący klucz.
2. Martwy checkbox nagłówka: `:351–356` `Checkbox` z `:checked` / `@update:checked` (nazwy radix, reka je ignoruje, `js.md`). Pole jest odznaczone, gdy `hasHeader` = `true`; watcher `:152` nie rusza, etykiety kolumn się nie przeliczają; do ustalenia, co trafia na serwer w `has_header` (`:362`). `resources/js/ui-contract.test.ts:50` trzyma ten plik jako jedyny wyjątek (issue #186) — usunięcie wyjątku to czerwony test.
3. Tytuł, panel i pola poza Atramentem: `:199` `text-lg font-semibold` i `:202` zamiast `page-title` / `meta`; „Wróć do operacji” bez ikony; `Card` z `CardTitle class="text-base"` (`:213–233`) zamiast panelu 640 z nagłówkiem h2 + `meta`; „Pokaż podgląd” w treści (`:565–572`), nie w stopce; osiem pól jako `div.grid gap-2` + `Label` + `InputError` (`:240`, `:264`, `:279`, `:377`, `:399`, `:423`, `:484`, `:510`, `:536`) zamiast `FormField` (ADR-0040: wysokość pola, `aria-describedby`).
4. Ręczne bloki zamiast przepisów: „Rozpoznawane pola” dwa razy jako `rounded-lg border p-4 … list-disc` (`:294–347`) zamiast `.form-preview` na `background`; układ kwoty jako surowe `<input type="radio">` (`:454–482`) bez fokusu z tokenów zamiast `SegmentedControl` tone `primary` (`js.md`) — research: czy `SegmentedControl` niesie wartość w formularzu (`name="amount_layout"`).
5. Przypadkowa architektura: bez aktywnego konta `:37` daje `accountId = ''` — cały formularz się renderuje, a przycisk jest wyłączony bez powodu; brak stanu pustego (`IndexEmptyState` z linkiem do Kont). Brak kitchen sinka i strażników (patrz wyżej).

Bez zarzutu: wejście bez logowania (`authorize` w `CsvImportController::create`), konto obce lub zarchiwizowane przy wysyłce (`firstOrFail` w `preview`).
