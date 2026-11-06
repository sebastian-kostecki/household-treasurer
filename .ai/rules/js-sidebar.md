---
paths:
    - 'resources/js/components/AppSidebar*.vue'
    - 'resources/js/components/AppMobileBar.vue'
    - 'resources/js/components/BudgetYear*.vue'
    - 'resources/js/components/CommandPalette*.vue'
    - 'resources/js/components/ui/sidebar/**'
    - 'resources/js/layouts/**/*.vue'
    - 'resources/js/lib/nav-active*.ts'
    - 'resources/js/lib/global-shortcuts*.ts'
    - 'resources/js/lib/command-palette*.ts'
    - 'resources/js/lib/budget-year-segments*.ts'
---

# Sidebar

## Sidebar colours are the `--sidebar-*` tokens

Sidebar colours are `--sidebar-*` tokens from resources/css/app.css (roles in context/foundation/design-system.md, "Sidebar"); there is no `--sidebar` token, the background is `--sidebar-background`. No `dark:` variants and no literals in sidebar files: `npm run check:ui` guards them (they are on the list in scripts/check-ui-literals.mjs).

## No collapse on desktop, the phone bar follows isMobile

The sidebar never collapses on desktop: `AppShell` passes `:open="true"` to `SidebarProvider` and `AppSidebar` uses `collapsible="offcanvas"`; there is no `group-data-[collapsible=icon]` and no `:tooltip` in the domain components, and the shell has no header bar or breadcrumbs (ADR-0054). On a phone the entry is `AppMobileBar`, rendered by `AppContent` when `useSidebar().isMobile` is true (not by `md:hidden`, the provider breakpoint is 768 px inclusive).

## Views come from useAppViews; shortcuts are silent in fields and layers

The menu, the global shortcuts and the palette read one list, `useAppViews` (resources/js/composables/useAppViews.ts); the shortcut number is the position in it, so the "1–n" hint and the numbers never come from literals. `AppSidebar` defines no local view array. The marker is `<kbd class="kbd" aria-hidden="true">` and the link carries `aria-keyshortcuts`, its accessible name stays the title. `resolveGlobalShortcut` (resources/js/lib/global-shortcuts.ts) keeps the shortcuts silent with Ctrl, ⌘ or Alt, during composition, on a repeated key, in a text field and under an open reka layer (dialog, menu, listbox); `/` allows Shift (Shift+7 on the Polish keyboard). Every handled shortcut calls `preventDefault`.

## The active item comes from isCurrentSection

The active menu item uses `sidebar-primary` (also on hover); the app name, the user and the selected year use `sidebar-strong`; the sidebar has no `variant="inset"`, it sits flush against the content. The active menu item comes from `isCurrentSection` (resources/js/lib/nav-active.ts), not from `isCurrentUrl`, so sub-pages like `/operations/import` keep their section highlighted. The year switcher is a `role="group"` of segments (`.sidebar-year-segments`, three per row, `aria-pressed`); the selected year segment uses `sidebar-strong`, the "+" segment opens the one shell-wide `CreateBudgetDialog` through `useCreateBudgetDialog`. Check the mobile bar, the year segments (one, many, empty, pending) and both themes in the kitchen sink at `/dev/ui/sidebar` (`local` only).

The same calculation drives `is-active` and `aria-current="page"` on the link (Inertia's `Link` does not add the attribute). Section labels are `SidebarGroupLabel` (`label-caps`), never a hand-built `text-xs` caption. The width comes only from `--sidebar-panel-width` in app.css (not from `--sidebar-width`, which `SidebarProvider` overwrites inline); do not write `w-64` or `16rem` for it. The product mark is `BrandMark`, with the name of the product next to it, not a Lucide icon (ADR-0053).
