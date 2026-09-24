# Dashboard notices carry a severity

Every Dashboard notice carries `severity`: `exceeded` (a limit or balance is already broken) or `warning` (still within bounds, close to breaking). The server decides it, next to `kind`, in `App\Support\DashboardNotices`; the UI only colours and groups by it. `exceeded` uses the `destructive` token, `warning` the `warning` token (amber). The four kinds from `dashboard-hierarchy.md` are all `exceeded`; no kind emits `warning` yet, and adding one is a domain decision (thresholds, which Categories) recorded in `CONTEXT.md` before code.

The attention zone heading shows one pill per severity present (“Przekroczone · n”, “Ostrzeżenia · n”) instead of a single total count. Severity sub-headings inside the list appear only when both severities are present, exceeded first. A negative Account or Term Deposit row shows “Saldo ujemne” under its amount, like the Category rows show their reason. Both points supersede `dashboard-hierarchy.md` (heading total count; no text label on negative holdings). Three visible rows and “Pokaż wszystkie (n)” with the total count stay as that spec says.

We rejected deriving severity from `kind` in the frontend (the rule would live in two places once warning kinds arrive) and two separate attention zones (two headings compete for the one “does anything need me” signal the spec gives Dashboard).
