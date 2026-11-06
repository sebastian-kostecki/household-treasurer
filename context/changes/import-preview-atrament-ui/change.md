---
change_id: import-preview-atrament-ui
title: Import preview atrament ui
status: implementing
created: 2026-11-06
updated: 2026-11-06
---

## Notes

Zmiana UI przez `/10x-ui`, jeden widok: `resources/js/pages/OperationsImportPreview.vue` (krok 2 importu) razem z `ImportPreviewRow.vue` i `ImportPreviewSubmitBar.vue`. Krok 1 (`OperationsImport.vue`) zamknęła zmiana `import-start-atrament-ui` (ADR-0048).

- **Wariant kontraktu:** istniejący system projektowy. Rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Źródło tokenów:** `resources/css/app.css` (`:root` / `.dark`, `@theme inline`, `page-title`, `meta`, `section-title`, `table-header`, `amount`, `.import-preview-*`, `.form-check-row`); wartości z design systemu C · Atrament i morski: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (podgląd, nie źródło wartości).
- **Wzorzec:** karta `PageImportPreview`: tytuł z opisem kroku 2 i „Wróć do operacji” (ikona strzałki); panel „Podgląd importu” (h2 + `meta` z kontem), pigułki liczników („Poprawne” `neutral`, „Niepoprawne” miękka czerwień) i zastrzeżenie o duplikatach; dwa ostrzeżenia; tabela z wierszem duplikatu (tint `warning`), kandydatem do pary (tint `pair`, powód pod wierszem po polsku z datą `dd.mm.rrrr`) i wierszem niepoprawnym (wyłączony checkbox, powód po polsku); kwoty według reguły Operacji (ADR-0038); „Zatwierdź import” na dole.
- **ADR:** nowy ADR ma numer **0052**.
- **Backend w zakresie:** polski powód pary w `ImportPairCandidateDetector` (dziś angielski klucz `__()`, brak `lang/pl.json`), najpierw czerwony test.

Wstępny audyt (2026-11-06): skan literałów 0 trafień; style sprzed Atramentu 18; użycia stylów Atramentu 14; pliki są w `scripts/check-ui-literals.mjs`, sink `/dev/import-preview` istnieje, brak `ImportPreviewHardcodedUiTest`. Wejście bez logowania i bez sesji importu obsłużone (`CsvImportController.php:121-127`). Zarzuty do potwierdzenia w researchu:

1. Angielskie powody: `ImportPairCandidateDetector.php:191`, `:230` (powód pary, data ISO) i `CsvImportPreviewValidator.php` (7 powodów odrzucenia wiersza) idą przez `__()` z angielskim kluczem; widoczne w `ImportPreviewRow.vue:608`, `:614`. Brak testu tekstu.
2. Tytuł i panel podsumowania poza Atramentem: `OperationsImportPreview.vue:219` `text-lg font-semibold`, `:222` `text-sm`, `:226-230` „Wróć do operacji” bez `ArrowLeft`, `:250-301` `Card` + `CardTitle class="text-base"`, `:297` `text-xs`.
3. Odznaki i kwoty: `:265` `secondary`, `:273` `destructive`, `ImportPreviewRow.vue:590`, `:596`; kwota `:363` przez `amountToneClass` zamiast reguły ADR-0038.
4. Ręczne bloki: potwierdzenie duplikatów `:387-400` (`flex gap-2` + `Label`) zamiast `.form-check-row`; `ImportPreviewRow.vue:344` data `text-sm font-medium`; podpisy `text-xs` (`:499`, `:505`, `:570`, `:606`, `:612`, `ImportPreviewSubmitBar.vue:29`).
5. Brak strażnika typografii kroku 2 (odpowiednik `ImportStartHardcodedUiTest.php`) i do sprawdzenia pełność 7-stanowej macierzy w `/dev/import-preview`.
