---
change_id: import-preview-ui-contract
title: Import preview UI contract pass (tokens, shared components, states)
status: implemented
created: 2026-11-04
updated: 2026-11-04
archived_at: null
---

## Notes

Zmiana wizualna prowadzona przez `/10x-ui import-operations-table`.

- **Widok (jeden):** `resources/js/pages/OperationsImportPreview.vue` (trasa `operations.import.preview`) wraz z wierszem `resources/js/components/ImportPreviewRow.vue`. Ekran wgrywania pliku (`OperationsImport.vue`) poza zakresem.
- **Źródło tokenów:** `resources/css/app.css` — wartości w `:root` / `.dark`, publikowane przez `@theme inline`; nazwane klasy w `@layer components` / `@utility`; notatki w `.docs/design-system.md`.
- **Wariant kontraktu:** istniejący system projektowy (Tailwind v4 + shadcn-vue, `resources/js/components/ui/*` + komponenty domenowe w `resources/js/components/`). Rozszerzamy, bez drugiej palety i bez `shadcn init`.
- **Reguły agenta:** `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/skills/openship-ui-development/SKILL.md`.
- **Dziedziczone:** `column-caption`, `ListSurface`, `IndexEmptyState`, `amountToneClass`, wzorzec `.operations-header-row` / `.operations-row`, kitchen sinki w `resources/js/pages/dev/` (np. `OperationsSink.vue`), `npm run check:ui` + lista `FILES` w `scripts/check-ui-literals.mjs` (widoku importu jeszcze na niej nie ma).

### Wstępny audyt (wejście do `/10x-research`)

`npm run check:ui` na plikach widoku: 9 trafień — 0 hex, 1 klasa palety (`bg-sky-500/10`), `text-[11px]` ×7, `hover:bg-foreground/[0.03]` ×1. Brak kitchen sinka.

1. **Brakujący token — podpisy kolumn** (`OperationsImportPreview.vue:301`; `ImportPreviewRow.vue:296,309,324,337,370,508`): ręcznie złożone `text-[11px] … uppercase`, choć istnieje `column-caption` (`app.css:199`); brak `.import-preview-header-row` na wzór `.operations-header-row`. Zmiana skali podpisów nie dotrze do importu.
2. **Brakujący token — tło wiersza „kandydat na parę”** (`ImportPreviewRow.vue:269`): `bg-sky-500/10` (klasa palety, bez wersji dark), hover-literał w `:266`, brak nazwanej klasy wiersza. Kolor znaczący „to pewnie przelew własny” jest spoza systemu i nie wiąże się z tokenem `transfer`.
3. **Brakujący współdzielony komponent — pole odsetek** (`ImportPreviewRow.vue:461`): `class="native-input"` nie istnieje w `app.css`, a `components/ui/input` jest dostępny. „Kwota odsetek” renderuje się jako surowy input przeglądarki, bez ramki, focus ringa i stanu błędu.
4. **Przypadkowa architektura — ostrzeżenia znikają albo są zawsze**: `hasNotes` (`ImportPreviewRow.vue:157-164`) pomija `toSpendWarning`, więc alert „ujemne Do wydania” (`:551`) pojawia się tylko przy innej notatce (błąd funkcjonalny). Alert o duplikatach (`OperationsImportPreview.vue:273`) jest bezwarunkowy, nawet przy 0 duplikatów; oba alerty strony w domyślnym wariancie zamiast `variant="warning"` (`.ai/rules/js.md:24`). Stały szum zamiast jednego prawdziwego sygnału.
5. **Stany i dostępność**: checkbox wiersza (`:275`) bez etykiety; selecty typu/pary (`:340`, `:386`) z `<span>` zamiast `<label>`, ukrytym na `xl` — kontrolki bez dostępnej nazwy. „Importuj” (`OperationsImportPreview.vue:374`) wyłączony bez widocznego powodu. `rowAmountClass` (`lib/import-preview-rows.ts:212`) to lokalny helper „znak → kolor”, zero na zielono — wbrew `amountToneClass`.

Pytania o stany: loading przy zatwierdzaniu (tylko `:disabled="processing"`); empty (`:326` akapit zamiast `IndexEmptyState`); wejście z linku bez sesji — OK, `showPreview` przekierowuje do `operations.import.create`.
