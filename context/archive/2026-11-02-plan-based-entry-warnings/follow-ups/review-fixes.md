# Review follow-ups: plan-based-entry-warnings

Z `reviews/impl-review.md` (2026-11-03), decyzje ACCEPTED jako działania następcze:

- [ ] F1 — `computeImportToSpendWarnings`: zastąpić odtwarzanie `pending` dla każdego wiersza stanem przyrostowym per miesiąc (O(n²) przy do 5000 wierszy, `CsvImportParser::MAX_ROWS`).
- [ ] F2 — Testy: izolacja między użytkownikami w `EntryToSpendInputsTest`; test zgodności `EntryToSpendInputs` + `ToSpendCalculator` z `to_spend_minor_units` w `MonthPlanController`; przypadki TS: edycja przez granicę roku do innego budżetu oraz kategoria oryginału nieobecna w budżecie docelowym.
