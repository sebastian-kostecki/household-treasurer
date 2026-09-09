# Amounts are signed bigint Minor units

Stored money is a signed bigint of Minor units (`*_minor_units`), not a PLN-specific `*_grosze` name and not a decimal of major units. MVP stays one currency (PLN, scale 2); there is no `currency` column yet. We rejected keeping `grosze` (it blocks a later Currency without a rename), `_cents` (wrong for non-cent currencies), dropping the suffix (invites major-unit floats), and designing FX or per-Account currency in this pass. PostgreSQL has no unsigned integers, so every amount column is signed `bigint`; positivity stays in validation.
