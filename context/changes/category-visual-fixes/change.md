---
change_id: category-visual-fixes
title: Akcje wiersza kategorii w menu „⋯”, przypisanie grupy przez okno
status: new
created: 2026-11-12
updated: 2026-11-12
archived_at: null
---

## Notes

widok kategorie: nie chce widziec napisu/buttonu operacje - niech bedzie w dropdownie; podobnie element grupa, tez niech bedzie w dropdownie a zarządzanie moze przez modal;

- Widok (jeden): „Kategorie”, `/categories` — `resources/js/pages/Categories.vue` z `CategoryListRow.vue`, `CategoryListRowActions.vue`, `CategoryGroupPicker.vue`, `CategoryArchiveSection.vue`.
- Źródło tokenów: Atrament, `resources/css/app.css` (`.categories-*`), kontrakt w `context/foundation/design-system.md:120-121`. Wariant: istniejący system projektowy — rozszerzamy, bez nowej palety.
- Wstępny audyt (/10x-ui, 2026-11-12): skan wartości zakodowanych na sztywno na 7 plikach widoku — 0 trafień. Zarzuty to przypadkowa architektura (akcje wiersza poza menu „⋯”), nie brakujące tokeny.
- Założenie: „zarządzanie przez modal” = pozycja „Grupa…” w menu „⋯” otwiera okno wyboru grupy tej kategorii. Alternatywa (wspólne okno „Zarządzaj grupami”) — otwarte pytanie dla researchu.
