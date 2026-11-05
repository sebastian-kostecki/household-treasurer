# Wallet Master as the OpenShip surface recipe

Household Treasurer keeps ADR-0001 for teal primary, light default, and shadcn-vue on Laravel + Inertia Vue. Wallet Master is the canonical **already-implemented** OpenShip surface recipe on the same stack: grey page floor, white cards lifted without resting shadows, quiet controls, and overlay dialogs as the highest layer. This app reimplements that recipe in its own primitives; it does not copy Wallet Master source files, adopt ink/near-black primary buttons, or treat Wallet Master mint as `--primary`. Teal stays the brand and action colour here.

Superseded in part by ADR-0035: "Teal stays the brand and action colour" and the rejection of an ink `--primary` no longer stand; `primary` is ink and mint `brand` / `brand-text` carry identity and links.
