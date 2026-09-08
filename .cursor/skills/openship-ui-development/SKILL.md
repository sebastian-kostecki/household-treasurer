---
name: openship-ui-development
description: "Apply the OpenShip-inspired surface ladder and control chrome for Household Treasurer. Activates when restyling pages, lists, cards, dialogs, buttons, inputs, or theme tokens; when the user mentions OpenShip, Wallet Master surface recipe, grey floor, lifted cards, or quiet operator UI."
license: MIT
metadata:
  author: household-treasurer
---

# OpenShip UI Development (Household Treasurer)

## North star

Openship dashboard language is the original north star. **Wallet Master** (same author, Laravel + Inertia Vue) is the canonical implemented recipe for that language on this stack. Reimplement the recipe here; do **not** copy Wallet Master Vue/CSS files, Openship `@repo/ui`, or Openship theme class names.

## Teal exception (ADR-0001 + ADR-0003)

- `--primary`, `--ring`, and sidebar primary stay **teal** (`#0b7285` family).
- Do **not** adopt Wallet Master ink/near-black primary buttons or mint-as-primary.
- `--destructive` (red) and `--success` (green) keep their money/warning meanings.
- Light is the default appearance; dark uses the same ladder and semantics.

## Surface ladder

1. **Page floor** — `bg-background`. Light: near-white grey. Dark: near-black.
2. **Lifted surface** — cards and list shells on `bg-card`, no resting drop shadow, hairline border (`border-border/60`), large radius (`rounded-2xl`).
3. **Highest layer** — dropdowns, popovers, dialogs: opaque `bg-card`, real shadow, hairline border, dim overlay (~40% black).

Never white card on white page; never black card on pure black without lift.

## Radius recipe (two tiers)

- **Large surfaces** — cards, sidebar inset panels, dialogs: `rounded-2xl`.
- **Medium controls** — buttons, inputs, native `<select>` (`.native-select`), nav chips: `rounded-xl`.

Prefer rounding more over reintroducing `rounded-md` cards.

## Shared primitives

Restyle through `resources/js/components/ui/` primitives so auth, settings, and signed-in pages inherit the same chrome:

- `Card` — lifted, no resting shadow.
- `Button` — medium radius, no resting shadow on outline/ghost.
- `Input` — quieter border, medium radius, teal focus ring.
- `Dialog` — centred overlay, large radius, header / body / footer; form and confirm dialogs share this chrome.
- Native `<select>` — use the `.native-select` component class from `resources/css/app.css`; do not swap in the unused Reka Select primitive for list forms.

## Out of scope for this skill

- Sidebar IA, Budget year switcher behaviour, Dashboard KPI layout.
- Ink primary, copying Wallet Master files, new CSS frameworks or fonts.
- Replacing native selects with Reka Select in domain forms.
- Asserting Tailwind classes in Pest tests.

## References

- ADR-0001 — teal OpenShip on shadcn-vue, light default.
- ADR-0003 — Wallet Master as surface recipe; teal stays.
- Parent spec — GitHub issue #21 (list and form chrome).
