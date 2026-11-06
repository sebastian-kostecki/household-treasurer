---
change_id: sidebar-atrament-ui
title: Sidebar według komponentu Sidebar z design systemu Atrament
status: implemented
created: 2026-11-06
updated: 2026-11-06
archived_at: null
---

## Notes

- **Widok:** `resources/js/components/AppSidebar.vue` i jego sloty (`AppLogo`, `BudgetYearSwitcher`, `NavMain`, `NavUser`, `UserInfo`). Kitchen sink: `/dev/ui/sidebar` (`resources/js/pages/dev/SidebarKitchenSink.vue`).
- **Źródło tokenów:** `--sidebar-*` w `resources/css/app.css` (role w `context/foundation/design-system.md`, „Sidebar”). Wzorzec: komponent `Sidebar` (`project/components/Sidebar/README.md`, klasy `.ht-side*` w `project/components/bundle.css`) w artefakcie https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, to podgląd, nie źródło wartości.
- **Wariant kontraktu:** istniejący system projektowy (Tailwind v4 + shadcn-vue). Rozszerzamy go; bez `shadcn init`, bez edycji `components/ui/*` pod jeden ekran. Poprzednia zmiana na tym widoku: `context/archive/2026-11-03-sidebar-ui-contract/`.
- **ADR:** nowy ADR ma numer 0053.
- **Decyzja: paleta ⌘K i skróty poza zmianą.** „Szukaj i przejdź ⌘K”, skróty `1`–`6`, `N`, `/` i linijka „Skróty: …” wymagają osobnej zmiany (`command-palette`, zwykły łańcuch): nowy komponent (brak `command` w `components/ui`), globalna obsługa klawiszy omijająca pola i `Esc` dialogów, nowy prop z kategoriami i ich realizacją (PHP + `defineProps<>` + `assertInertia`), dostępność `dialog`/`combobox`/pułapka fokusu. Design system sam nazywa je „dodatkiem celowym” (`CommandPalette/README.md`). Przycisk ⌘K bez palety byłby martwą kontrolką.
- **Decyzja: rok budżetu bez segmentów.** Zostaje menu `BudgetYearSwitcher` (radio + „Dodaj rok”, `pending`/`aria-busy`, komunikat błędu), dopasowane wyglądem do wzorca. Powody: segmenty nie mieszczą się w zwiniętym pasku 3rem (reguła `js-sidebar.md`), lat przybywa bez limitu przy 224 px, „2027 +” miesza wybór z tworzeniem i wymaga danych, których `budgetYears` nie niesie; README wzorca („W kodzie”) zostawia menu.
- **Zarzuty z wstępnego audytu** (do `## Charges` w `research.md`):
  - C1 `ui/sidebar/utils.ts:6`: szerokość `16rem` zamiast tokenu `sidebar-width` 224 px; brak `--sidebar-width` w `app.css`.
  - C2 `BudgetYearSwitcher.vue:96,124`, `NavMain.vue:25`: etykiety `text-sidebar-foreground/80 text-xs` zamiast `label-caps` (`app.css:262`); „Rok budżetu” w przycisku, nie nad nim.
  - C3 `NavMain.vue:28-33`, `ui/sidebar/SidebarMenuButtonChild.vue:28`: aktywna pozycja bez `aria-current="page"`.
  - C4 `UserInfo.vue:25-27`, `NavUser.vue:29-34`: kwadratowy awatar 32 px na `bg-muted` zamiast okrągłego 28 px na `sidebar-accent`; brak e-maila (`UserInfo` jest też w menu użytkownika).
  - C5 `AppLogo.vue:10-14`: znak `Vault` 32 px zamiast „HT” w JetBrains Mono 26 px `radius-sm`; zamyka odroczony C5a z `sidebar-ui-contract`, decyzja do ADR-0053.
  - Drobne: `AppSidebar.vue:101` `<slot />` poza `<Sidebar>` (dwa korzenie, nikt nie podaje treści slotu).
- **Macierz 7 stanów:** hover, focus-visible i disabled w kitchen sinku są dziś „zrzutami z aplikacji”, nie są pokazane.
- Skan wartości zakodowanych na sztywno na starcie: 0 trafień; wszystkie pliki sidebara są na liście `scripts/check-ui-literals.mjs`.
