---
paths:
    - 'resources/js/components/AppSidebar*.vue'
    - 'resources/js/components/ui/sidebar/**'
    - 'resources/js/layouts/**/*.vue'
    - 'resources/js/lib/nav-active*.ts'
---

# Sidebar

## Sidebar colours are the `--sidebar-*` tokens

Sidebar colours are `--sidebar-*` tokens from resources/css/app.css (roles in context/foundation/design-system.md, "Sidebar"); there is no `--sidebar` token, the background is `--sidebar-background`. No `dark:` variants and no literals in sidebar files: `npm run check:ui` guards them (they are on the list in scripts/check-ui-literals.mjs).

## Header and footer slots survive the collapsed strip

Whatever goes into the `SidebarHeader` / `SidebarFooter` slots is built from `SidebarMenuButton` or handles `group-data-[collapsible=icon]` itself, otherwise it breaks in the 3rem collapsed strip.

## The active item comes from isCurrentSection

The active menu item uses `sidebar-primary` (also on hover); the app name, the user and the selected year use `sidebar-strong`; the sidebar has no `variant="inset"`, it sits flush against the content. The active menu item comes from `isCurrentSection` (resources/js/lib/nav-active.ts), not from `isCurrentUrl`, so sub-pages like `/operations/import` keep their section highlighted. Check the icon mode, the empty year state and both themes in the kitchen sink at `/dev/ui/sidebar` (`local` only).
