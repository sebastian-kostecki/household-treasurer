---
paths:
    - resources/css/app.css
---

# Css

## New colours are tokens in both :root and .dark

Add --name in :root and .dark plus --color-name in @theme inline, then use it as a utility. Text colours must reach 4.5:1 on card; the values come from the design system (ADR-0035), so check contrast in both themes when you change one. Record any token change in .docs/design-system.md.
