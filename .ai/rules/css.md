---
paths:
    - resources/css/app.css
---

# Css

## New colours are tokens in both :root and .dark

Add --name in :root and .dark plus --color-name in @theme inline, then use it as a utility. Text colours must reach 4.5:1 on card; the values come from the design system (ADR-0035), so check contrast in both themes when you change one. Record any token change in context/foundation/design-system.md.

## Typography styles and control sizes are named in app.css

Typography is the named styles `page-title`, `display`, `heading`, `lead`, `card-title`, `section-title`, `table-header`, `label-caps`, `meta`, `mark-label`, `brand-mark`, `kpi-lg`, `kpi`, `amount` and `amount-sum` (table in context/foundation/design-system.md); sizes are the tokens `--control-height` (32px), `--control-height-sm` (26px) and `--row-height` (34px), `--sidebar-panel-width` (224px), `--brand-mark-size` (26px), `--auth-aside-width` (440px) and `--auth-card-width` (400px), which are dimensions and have no `.dark` variant. Add a new style or size here and use it by name; there is no `column-caption` (ADR-0037).
