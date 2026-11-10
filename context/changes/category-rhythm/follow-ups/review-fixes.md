# Review fixes: category-rhythm

Działania następcze z `reviews/impl-review.md`, które nie zamykają się w triage.

- [ ] **F1 — próba na kopii produkcji.** Pobrać świeże archiwum z VPS (`scripts/artisan.sh backup:run --disable-notifications`), przejść kroki 1–13 z `reviews/production-rehearsal.md` w bazie `restore_trial` i uzupełnić pola „…”. Warunek wdrożenia S-24 na produkcję.
- [ ] **Niestabilny E2E Importu (poza S-24, znaleziony przy weryfikacji po triage).** `tests/e2e/import-other-format-without-header.spec.ts` wstawia do opisu `Date.now()` (13 cyfr), a Import usuwa z opisów liczby 13–19 cyfr przechodzące test Luhna (`DefaultPaymentInstrumentNumberOmitter`, #142). Znacznik `1794332148458` przeszedł Luhna i został wycięty, więc `getByText(first, { exact: true })` nie znalazł wiersza; dotyczy to około 1 przebiegu na 10. Zachowanie produkcyjne jest poprawne; do zmiany są dane testu (znacznik, który nie jest samą liczbą, np. `stamp.toString(36)`).
