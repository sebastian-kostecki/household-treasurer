---
change_id: sidebar-ui-contract
title: Sidebar UI contract pass (tokens, nav state, year switcher)
status: archived
created: 2026-11-03
updated: 2026-11-03
archived_at: 2026-11-03T16:05:38Z
---

## Notes

Zmiana wizualna prowadzona przez `/10x-ui sidebar`.

- **Widok (jeden):** powłoka sidebara `resources/js/components/AppSidebar.vue` wraz z komponentami renderowanymi tylko przez nią: `NavMain.vue`, `NavUser.vue`, `UserInfo.vue`, `UserMenuContent.vue`, `AppLogo.vue`, `BudgetYearSwitcher.vue` oraz `AppSidebarHeader.vue` (layout `resources/js/layouts/app/AppSidebarLayout.vue`). `NavFooter.vue` jest nieużywany — kandydat do usunięcia.
- **Źródło tokenów:** `resources/css/app.css` — blok `--sidebar-*` w `:root` / `.dark`, publikowany przez `@theme inline` jako `--color-sidebar-*`; notatki w `.docs/design-system.md`.
- **Wariant kontraktu:** istniejący system projektowy (Tailwind v4 + shadcn-vue, `resources/js/components/ui/sidebar/*`). Rozszerzamy, bez drugiej palety i bez `shadcn init`; nie edytujemy prymitywów `ui/` pod jeden ekran.
- **Reguły agenta:** `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/skills/openship-ui-development/SKILL.md` (sidebar jest dziś wyłączony z jego zakresu — linia 55).
- **Wstępne zarzuty z `/10x-ui`:** `AppLogo.vue:12` i `UserInfo.vue:27` (literały `dark:`), martwy `--sidebar` w `app.css:139/181`, `BudgetYearSwitcher` bez obsługi `collapsible=icon` (do potwierdzenia zrzutem), `NavMain.vue:28` dokładne dopasowanie URL (brak aktywnej pozycji na `/operations/import*`), nieużywany `NavFooter.vue`.
- **Kolejność:** `dashboard-ui-contract` jest w trakcie i też dotyka `app.css`; implementację tej zmiany zacząć po fazie tokenów dashboardu. Kitchen sink wg wzorca `/dev/ui/dashboard`.
- **Gałąź:** worktree `.claude/worktrees/sidebar-ui-contract` od `development` @ 547d3ef.
