---
name: openship-ui-development
description: 'Restyle a Household Treasurer view to the Atrament design system: tokens, surfaces, controls, dialogs, typography. Activates when the user asks for a restyle, a theme or token change, dark mode, a design-system pass, or mentions Atrament, OpenShip or the surface recipe.'
license: MIT
metadata:
    author: household-treasurer
---

# UI development (Household Treasurer)

The visual language started as OpenShip (ADR-0001, ADR-0003) and is now the Atrament design system (ADR-0035 and later). The single page of rules, for humans and agents, is `context/foundation/design-system.md`; this skill only says where to look and in what order.

## Read first

1. `context/foundation/design-system.md`: tokens, surface recipe, control sizes, typography styles, colour meaning, ready-made components. It names the ADRs behind every rule and which ADR supersedes which.
2. `.ai/rules/css.md` for `resources/css/app.css`, and `.ai/rules/js.md` plus the view rule (`js-month-plan.md`, `js-operations.md`, `js-import.md`, `js-dialogs.md`, `js-sidebar.md`) for the view you touch.
3. The visual reference artifact linked at the top of `context/foundation/design-system.md` is a preview only, never the source of a value.

## Working rules

- Values come from tokens and named styles in `resources/css/app.css`; a new size, grid or label style gets a named class there, not an arbitrary value in a template. `npm run check:ui` guards the cleaned views (list in `scripts/check-ui-literals.mjs`).
- Restyle through `resources/js/components/ui/*` primitives so every page inherits the same chrome; do not edit a primitive for a single screen.
- Every view has a kitchen sink under `/dev/...` (`APP_ENV=local`) with its state matrix; add new states there.
- A change to a token or to colour meaning is recorded in `context/foundation/design-system.md` and, when it changes a decision, in a new ADR.
